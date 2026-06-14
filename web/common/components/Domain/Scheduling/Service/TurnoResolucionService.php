<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Platform\Core\Service\Push\PushNotificationSender;
use common\components\Domain\Organization\Service\ProfesionalEfectorServicio\AgendaIntervaloMinutos;
use common\components\Domain\Organization\Service\ProfesionalEfectorServicio\AgendaSlotEngine;
use common\models\Person\Persona;
use common\models\ProfesionalEfectorServicio;
use common\models\ProfesionalEfectorServicioAgendaVersion;
use common\models\Scheduling\Turno;
use common\models\TurnoNotificacionProgramada;
use common\models\TurnoResolucion;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

/**
 * Marca turnos EN_RESOLUCION, listados y cierre (vecino, reubicación libre o cancelación).
 */
final class TurnoResolucionService
{
    /**
     * @param array<string, mixed> $opts id_agenda_version, opcion_hora_antes, opcion_hora_despues, permitir_otro_efector, permitir_otro_pes, meta_json
     */
    public static function marcarTurnoEnResolucion(Turno $turno, string $origen, array $opts = []): TurnoResolucion
    {
        if (!in_array($origen, [
            TurnoResolucion::ORIGEN_CAMBIO_AGENDA,
            TurnoResolucion::ORIGEN_GESTION_STAFF,
            TurnoResolucion::ORIGEN_LICENCIA,
        ], true)) {
            throw new \InvalidArgumentException('origen de resolución inválido');
        }
        if ($turno->estado !== Turno::ESTADO_PENDIENTE && $turno->estado !== Turno::ESTADO_EN_RESOLUCION) {
            throw new \InvalidArgumentException('Solo turnos pendientes o ya en resolución pueden entrar al flujo.');
        }

        $existente = TurnoResolucion::findPendientePorTurno((int) $turno->id_turnos);
        if ($existente !== null) {
            return $existente;
        }

        $row = new TurnoResolucion();
        $row->id_turno = (int) $turno->id_turnos;
        $row->origen = $origen;
        $row->estado = TurnoResolucion::ESTADO_PENDIENTE;
        $row->id_agenda_version = isset($opts['id_agenda_version']) ? (int) $opts['id_agenda_version'] : null;
        if ($row->id_agenda_version !== null && $row->id_agenda_version <= 0) {
            $row->id_agenda_version = null;
        }
        $row->razon_codigo = isset($opts['razon_codigo']) ? trim((string) $opts['razon_codigo']) : null;
        $row->opcion_hora_antes = self::horaParaDb($opts['opcion_hora_antes'] ?? null);
        $row->opcion_hora_despues = self::horaParaDb($opts['opcion_hora_despues'] ?? null);
        $row->permitir_otro_efector = array_key_exists('permitir_otro_efector', $opts)
            ? (bool) $opts['permitir_otro_efector']
            : true;
        $row->permitir_otro_pes = array_key_exists('permitir_otro_pes', $opts)
            ? (bool) $opts['permitir_otro_pes']
            : true;
        if (isset($opts['meta_json'])) {
            $row->meta_json = is_string($opts['meta_json'])
                ? $opts['meta_json']
                : json_encode($opts['meta_json'], JSON_UNESCAPED_UNICODE);
        }
        $row->save(false);

        $turno->estado = Turno::ESTADO_EN_RESOLUCION;
        $turno->save(false);
        TurnoNotificacionProgramada::cancelarPendientesPorTurno($turno->id_turnos);

        return $row;
    }

    /**
     * Staff: cancelación directa o paso a EN_RESOLUCION según motivo MED_*.
     *
     * @return array{estado: string, message: string}
     */
    public static function gestionarCancelacionStaff(
        Turno $turno,
        string $razonCodigo,
        string $canal,
        ?int $idUser
    ): array {
        if ($razonCodigo === '' || !TurnoCancelacionRazones::esCodigoMedicoAppValido($razonCodigo)) {
            throw new BadRequestHttpException('razon_cancelacion médica inválida.');
        }

        if (TurnoCancelacionRazones::staffCancelacionDirecta($razonCodigo)) {
            $life = new TurnoLifecycleService();
            $life->cancelar(
                $turno,
                Turno::ESTADO_MOTIVO_CANCELADO_MEDICO,
                $canal,
                $idUser,
                [
                    'razon_cancelacion' => $razonCodigo,
                    'razon_cancelacion_label' => TurnoCancelacionRazones::etiquetaMedicoApp($razonCodigo),
                ],
                false
            );

            return ['estado' => Turno::ESTADO_CANCELADO, 'message' => 'Turno cancelado.'];
        }

        self::marcarTurnoEnResolucion($turno, TurnoResolucion::ORIGEN_GESTION_STAFF, [
            'razon_codigo' => $razonCodigo,
            'permitir_otro_efector' => true,
            'permitir_otro_pes' => true,
            'meta_json' => ['canal' => $canal],
        ]);

        $push = new PushNotificationSender();
        $push->sendToPersona(
            (int) $turno->id_persona,
            ['type' => 'TURNO_REQUIERE_REUBICACION', 'id_turno' => (string) $turno->id_turnos],
            'Tu turno requiere una nueva cita',
            'El consultorio modificó tu turno del ' . $turno->fecha . '. Elegí otro horario o profesional desde la app.'
        );

        return [
            'estado' => Turno::ESTADO_EN_RESOLUCION,
            'message' => 'Turno en resolución: el paciente puede reubicar o cancelar desde la app.',
        ];
    }

    /**
     * @param list<array<string, mixed>> $conflictos
     */
    public static function crearDesdeCambioAgenda(int $idVersion, array $conflictos): void
    {
        foreach ($conflictos as $c) {
            $idTurno = (int) ($c['id_turno'] ?? 0);
            if ($idTurno <= 0) {
                continue;
            }
            $turno = Turno::findOne($idTurno);
            if ($turno === null) {
                continue;
            }
            $query = TurnoResolucion::find()->where([
                'id_turno' => $idTurno,
                'id_agenda_version' => $idVersion,
                'estado' => TurnoResolucion::ESTADO_PENDIENTE,
            ]);
            if ($query->exists()) {
                continue;
            }
            self::marcarTurnoEnResolucion($turno, TurnoResolucion::ORIGEN_CAMBIO_AGENDA, [
                'id_agenda_version' => $idVersion,
                'opcion_hora_antes' => $c['opcion_antes'] ?? null,
                'opcion_hora_despues' => $c['opcion_despues'] ?? null,
                'permitir_otro_efector' => false,
                'permitir_otro_pes' => false,
            ]);

            $push = new PushNotificationSender();
            $push->sendToPersona(
                (int) $turno->id_persona,
                ['type' => 'TURNO_REQUIERE_REUBICACION', 'id_turno' => (string) $turno->id_turnos],
                'Cambio de horario de tu turno',
                'Tu profesional actualizó la agenda. Elegí un nuevo horario para el ' . $turno->fecha . '.'
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolverEleccionVecina(int $idTurno, int $idPersona, string $eleccion): array
    {
        $eleccion = strtolower(trim($eleccion));
        if (!in_array($eleccion, ['antes', 'despues', 'cancelar'], true)) {
            throw new BadRequestHttpException('eleccion debe ser antes, despues o cancelar.');
        }

        /** @var TurnoResolucion|null $res */
        $res = TurnoResolucion::find()
            ->alias('r')
            ->innerJoin(['t' => Turno::tableName()], 't.id_turnos = r.id_turno')
            ->where([
                'r.id_turno' => $idTurno,
                'r.estado' => TurnoResolucion::ESTADO_PENDIENTE,
                't.id_persona' => $idPersona,
                't.estado' => Turno::ESTADO_EN_RESOLUCION,
            ])
            ->one();
        if ($res === null) {
            throw new BadRequestHttpException('No hay resolución pendiente para este turno.');
        }

        $turno = $res->turno;
        if ($turno === null) {
            throw new BadRequestHttpException('Turno no encontrado.');
        }

        if ($eleccion === 'cancelar') {
            $life = new TurnoLifecycleService();
            $life->cancelar(
                $turno,
                Turno::ESTADO_MOTIVO_CANCELADO_PACIENTE,
                'app',
                Yii::$app->user->id ?? null,
                ['desde_resolucion' => true],
                false
            );
            $res->estado = TurnoResolucion::ESTADO_CANCELADO;
            $res->save(false);

            return ['message' => 'Turno cancelado.', 'estado' => Turno::ESTADO_CANCELADO];
        }

        $hora = $eleccion === 'antes' ? $res->opcion_hora_antes : $res->opcion_hora_despues;
        if ($hora === null || trim((string) $hora) === '') {
            throw new BadRequestHttpException('La opción elegida no está disponible.');
        }

        $version = $res->agendaVersion;
        $intervalo = $version !== null ? $version->getIntervaloMinutosEfectivo() : AgendaIntervaloMinutos::DEFAULT;
        $horaNorm = substr(TurnoResolucion::normalizarHora((string) $hora), 0, 5);
        $fin = TurnoResolucion::sumarMinutos($horaNorm . ':00', $intervalo);

        if (TurnoSlotOccupancyService::haySolapamiento(
            (int) $turno->id_profesional_efector_servicio,
            (string) $turno->fecha,
            $horaNorm . ':00',
            $fin,
            (int) $turno->id_turnos
        )) {
            throw new BadRequestHttpException('El horario elegido ya no está disponible.');
        }

        $turno->hora = $horaNorm . ':00';
        $turno->hora_fin = $fin;
        $turno->intervalo_minutos_reserva = $intervalo;
        if ($version !== null) {
            $turno->id_agenda_version = (int) $version->id;
        }
        $turno->estado = Turno::ESTADO_PENDIENTE;
        $turno->save(false);

        $res->estado = TurnoResolucion::ESTADO_REUBICADO;
        $res->hora_elegida = $hora;
        $res->save(false);

        self::reprogramarNotificaciones($turno);

        return [
            'message' => 'Turno reprogramado a las ' . $horaNorm . '.',
            'estado' => Turno::ESTADO_PENDIENTE,
            'fecha' => $turno->fecha,
            'hora' => $horaNorm,
        ];
    }

    /**
     * Reubicación completa (otro PES/efector/horario). POST con fecha, hora, id_profesional_efector_servicio, id_efector opcional.
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function reubicarComoPaciente(int $idTurno, int $idPersona, array $post): array
    {
        $turno = Turno::findActive()->andWhere(['id_turnos' => $idTurno])->one();
        if ($turno === null || (int) $turno->id_persona !== $idPersona) {
            throw new NotFoundHttpException('Turno no encontrado');
        }
        if ($turno->estado !== Turno::ESTADO_EN_RESOLUCION) {
            throw new BadRequestHttpException('El turno no está en resolución.');
        }
        $res = TurnoResolucion::findPendientePorTurno($idTurno);
        if ($res === null) {
            throw new BadRequestHttpException('No hay resolución pendiente para este turno.');
        }

        $fecha = trim((string) ($post['fecha'] ?? ''));
        $hora = trim((string) ($post['hora'] ?? ''));
        $idPesPost = isset($post['id_profesional_efector_servicio']) ? (int) $post['id_profesional_efector_servicio'] : 0;
        if ($fecha === '' || $hora === '' || $idPesPost <= 0) {
            throw new BadRequestHttpException('fecha, hora e id_profesional_efector_servicio son requeridos.');
        }

        $pesPost = ProfesionalEfectorServicio::findOne(['id' => $idPesPost, 'deleted_at' => null]);
        if ($pesPost === null) {
            throw new BadRequestHttpException('Profesional inválido.');
        }

        $idEfectorNuevo = isset($post['id_efector']) && (int) $post['id_efector'] > 0
            ? (int) $post['id_efector']
            : (int) $pesPost->id_efector;
        $idEfectorActual = (int) ($turno->id_efector ?? 0);

        if ($idEfectorNuevo !== (int) $pesPost->id_efector) {
            throw new BadRequestHttpException('El profesional no pertenece al centro indicado.');
        }
        if ($idEfectorNuevo !== $idEfectorActual && !$res->permitir_otro_efector) {
            throw new BadRequestHttpException('Este turno no permite cambiar de centro de salud.');
        }
        if ($idPesPost !== (int) $turno->id_profesional_efector_servicio && !$res->permitir_otro_pes) {
            throw new BadRequestHttpException('Este turno solo permite elegir otro horario con el mismo profesional.');
        }

        if (isset($post['id_servicio_asignado']) && (int) $post['id_servicio_asignado'] > 0) {
            $turno->id_servicio_asignado = (int) $post['id_servicio_asignado'];
        }

        $turno->id_efector = $idEfectorNuevo;
        $turno->id_profesional_efector_servicio = $idPesPost;
        $turno->fecha = $fecha;
        $turno->hora = $hora;
        try {
            TurnoReservaSlotService::aplicarCamposReserva($turno, (int) $turno->id_turnos);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        $turno->estado = Turno::ESTADO_PENDIENTE;
        if (!$turno->save()) {
            throw new BadRequestHttpException('No se pudo guardar el turno.');
        }

        $res->estado = TurnoResolucion::ESTADO_REUBICADO;
        $res->hora_elegida = substr(TurnoResolucion::normalizarHora($hora), 0, 8);
        $res->save(false);

        self::reprogramarNotificaciones($turno);

        return [
            'success' => true,
            'id' => $turno->id_turnos,
            'fecha' => $turno->fecha,
            'hora' => substr((string) $turno->hora, 0, 5),
            'estado' => Turno::ESTADO_PENDIENTE,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listarEnResolucionParaPaciente(int $idPersona, ?string $origen = null): array
    {
        if ($idPersona <= 0) {
            return [];
        }

        $query = Turno::findActive()->alias('t')
            ->innerJoin(
                ['r' => TurnoResolucion::tableName()],
                'r.id_turno = t.id_turnos AND r.estado = :estRes',
                [':estRes' => TurnoResolucion::ESTADO_PENDIENTE]
            )
            ->where([
                't.id_persona' => $idPersona,
                't.estado' => Turno::ESTADO_EN_RESOLUCION,
            ])
            ->andWhere(['>=', 't.fecha', date('Y-m-d')])
            ->orderBy(['t.fecha' => SORT_ASC, 't.hora' => SORT_ASC]);

        if ($origen !== null && $origen !== '') {
            $query->andWhere(['r.origen' => $origen]);
        }

        $out = [];
        foreach ($query->all() as $turno) {
            $out[] = self::formatTurnoListItem($turno);
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listarEnResolucionStaff(int $idEfector, ?int $idPes = null): array
    {
        if ($idEfector <= 0) {
            throw new BadRequestHttpException('id_efector requerido.');
        }

        $query = TurnoResolucion::find()
            ->alias('r')
            ->innerJoin(['t' => Turno::tableName()], 't.id_turnos = r.id_turno')
            ->where([
                'r.estado' => TurnoResolucion::ESTADO_PENDIENTE,
                't.estado' => Turno::ESTADO_EN_RESOLUCION,
                't.id_efector' => $idEfector,
            ])
            ->andWhere(['>=', 't.fecha', date('Y-m-d')])
            ->orderBy(['t.fecha' => SORT_ASC, 't.hora' => SORT_ASC]);

        if ($idPes !== null && $idPes > 0) {
            $query->andWhere(['t.id_profesional_efector_servicio' => $idPes]);
        }

        $out = [];
        foreach ($query->all() as $res) {
            $turno = $res->turno;
            if ($turno === null) {
                continue;
            }
            $row = self::formatTurnoListItem($turno);
            $row['turno_resolucion'] = $res->toPacienteApiArray();
            $paciente = $turno->paciente;
            if ($paciente !== null) {
                $row['paciente'] = [
                    'id_persona' => (int) $paciente->id_persona,
                    'nombre' => $paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D),
                ];
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolverConflictoStaff(int $idTurno, int $idEfector, string $eleccion): array
    {
        if ($idEfector <= 0) {
            throw new BadRequestHttpException('Sesión operativa sin efector.');
        }

        $turno = Turno::findOne($idTurno);
        if ($turno === null || (int) $turno->id_efector !== $idEfector) {
            throw new NotFoundHttpException('Turno no encontrado en este efector.');
        }
        $idPersona = (int) $turno->id_persona;
        if ($idPersona <= 0) {
            throw new BadRequestHttpException('Turno sin paciente asociado.');
        }

        return self::resolverEleccionVecina($idTurno, $idPersona, $eleccion);
    }

    /**
     * @return array{id: string, name: string, meta?: array<string, mixed>}
     */
    public static function toListPickerItem(array $row): array
    {
        $id = isset($row['id']) ? (string) $row['id'] : '';
        $fecha = isset($row['fecha']) ? (string) $row['fecha'] : '';
        $hora = isset($row['hora']) ? substr((string) $row['hora'], 0, 5) : '';
        $svc = isset($row['servicio']) ? (string) $row['servicio'] : '';
        $prof = isset($row['profesional']) ? (string) $row['profesional'] : '';
        $paciente = isset($row['paciente']['nombre']) ? (string) $row['paciente']['nombre'] : '';

        $parts = array_filter([$fecha, $hora, $paciente, $svc, $prof]);
        $label = implode(' · ', $parts);
        if (!empty($row['en_resolucion'])) {
            $label = '⚠ ' . $label;
        }
        if ($label === '') {
            $label = 'Turno #' . $id;
        }

        return [
            'id' => $id,
            'name' => $label,
            'meta' => $row,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function formatTurnoListItem(Turno $turno): array
    {
        $res = TurnoResolucion::findPendientePorTurno((int) $turno->id_turnos);
        $profPersona = $turno->getProfesionalPersonaParaDisplay();
        $profesional = $profPersona
            ? $profPersona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D)
            : null;

        return [
            'id' => (int) $turno->id_turnos,
            'fecha' => $turno->fecha,
            'hora' => $turno->hora,
            'servicio' => $turno->getNombreServicioParaDisplay(),
            'profesional' => $profesional,
            'id_profesional_efector_servicio' => (int) ($turno->id_profesional_efector_servicio ?? 0) ?: null,
            'estado' => $turno->estado,
            'en_resolucion' => $turno->estado === Turno::ESTADO_EN_RESOLUCION,
            'turno_resolucion' => $res !== null ? $res->toPacienteApiArray() : null,
        ];
    }

    private static function reprogramarNotificaciones(Turno $turno): void
    {
        TurnoNotificacionProgramada::cancelarPendientesPorTurno($turno->id_turnos);
        try {
            $conf = new TurnoConfirmationService();
            $conf->ensureConfirmacionToken($turno);
            $conf->programarNotificaciones($turno);
        } catch (\Throwable $e) {
            Yii::warning('reubicar notif: ' . $e->getMessage(), 'turno-resolucion');
        }
    }

    /**
     * @param mixed $hora
     */
    private static function horaParaDb($hora): ?string
    {
        if ($hora === null || trim((string) $hora) === '') {
            return null;
        }

        return TurnoResolucion::normalizarHora((string) $hora);
    }
}
