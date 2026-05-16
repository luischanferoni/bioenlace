<?php

namespace common\components\Services\ProfesionalEfectorServicio;

use common\models\ProfesionalEfectorServicioAgenda;
use common\models\ProfesionalEfectorServicioAgendaVersion;
use common\models\Turno;
use common\models\TurnoAgendaConflicto;
use Yii;
use yii\web\BadRequestHttpException;

/**
 * Alta de versiones de agenda, preview de impacto y conflictos con turnos futuros.
 */
final class ProfesionalEfectorServicioAgendaVersionService
{
    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function previewImpacto(int $idPes, int $idEfector, array $post): array
    {
        $vigenteDesde = self::parseVigenteDesde($post);
        $intervaloNuevo = AgendaIntervaloMinutos::normalize((int) ($post['intervalo_minutos'] ?? 0));
        $versionPropuesta = self::buildVersionFromPost($idPes, $idEfector, $post, $vigenteDesde, $intervaloNuevo);

        $versionActual = ProfesionalEfectorServicioAgendaVersion::findVigenteParaPesEnFecha($idPes, $vigenteDesde);
        $intervaloActual = $versionActual !== null
            ? $versionActual->getIntervaloMinutosEfectivo()
            : self::intervaloDesdeAgendaEspejo($idPes);

        $slotsAntes = $versionActual !== null
            ? AgendaSlotEngine::estimarSlotsPorDiaPromedio($versionActual, $intervaloActual)
            : 0;
        $slotsDespues = AgendaSlotEngine::estimarSlotsPorDiaPromedio($versionPropuesta, $intervaloNuevo);

        $turnosFuturos = self::turnosPendientesFuturos($idPes, $vigenteDesde);
        $alineados = 0;
        $conflictos = [];
        foreach ($turnosFuturos as $turno) {
            $fecha = (string) $turno->fecha;
            $hora = substr((string) $turno->hora, 0, 5);
            if (AgendaSlotEngine::horaEstaEnGrilla($versionPropuesta, $fecha, $hora, $intervaloNuevo)) {
                $alineados++;
                continue;
            }
            $vec = AgendaSlotEngine::vecinosEnGrilla($versionPropuesta, $fecha, $hora, $intervaloNuevo);
            $conflictos[] = [
                'id_turno' => (int) $turno->id_turnos,
                'fecha' => $fecha,
                'hora_actual' => $hora,
                'opcion_antes' => $vec['antes'],
                'opcion_despues' => $vec['despues'],
            ];
        }

        $cambiosAnio = self::contarCambiosIntervaloEnAnio($idPes, (int) date('Y', strtotime($vigenteDesde)));
        $cambioIntervalo = $intervaloActual !== $intervaloNuevo;

        return [
            'vigente_desde' => $vigenteDesde,
            'intervalo_actual' => $intervaloActual,
            'intervalo_nuevo' => $intervaloNuevo,
            'slots_por_dia_antes' => $slotsAntes,
            'slots_por_dia_despues' => $slotsDespues,
            'turnos_futuros_total' => count($turnosFuturos),
            'turnos_alineados' => $alineados,
            'turnos_en_conflicto' => count($conflictos),
            'conflictos' => $conflictos,
            'cambios_intervalo_este_anio' => $cambiosAnio,
            'cambios_intervalo_max_por_anio' => AgendaIntervaloMinutos::MAX_CAMBIOS_INTERVALO_POR_ANIO,
            'requiere_confirmacion' => count($conflictos) > 0 || $cambioIntervalo,
            'mensaje' => self::buildMensajePreview($intervaloActual, $intervaloNuevo, $slotsAntes, $slotsDespues, count($conflictos)),
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{message: string, agenda_ui_completed: string, id_agenda_version: int, preview?: array<string, mixed>}
     */
    public static function publicarVersion(int $idPes, int $idEfector, array $post): array
    {
        $preview = self::previewImpacto($idPes, $idEfector, $post);
        $vigenteDesde = (string) $preview['vigente_desde'];
        $intervaloNuevo = (int) $preview['intervalo_nuevo'];
        $confirmar = !empty($post['confirmar_cambios']) && (string) $post['confirmar_cambios'] !== '0';

        $cambioIntervalo = (int) $preview['intervalo_actual'] !== $intervaloNuevo;
        if ($cambioIntervalo) {
            $cambiosAnio = (int) $preview['cambios_intervalo_este_anio'];
            if ($cambiosAnio >= AgendaIntervaloMinutos::MAX_CAMBIOS_INTERVALO_POR_ANIO) {
                throw new BadRequestHttpException(
                    'Ya alcanzaste el máximo de ' . AgendaIntervaloMinutos::MAX_CAMBIOS_INTERVALO_POR_ANIO
                    . ' cambios de intervalo por año para esta agenda.'
                );
            }
        }

        if ((int) $preview['turnos_en_conflicto'] > 0 && !$confirmar) {
            throw new BadRequestHttpException(
                'Hay turnos que no encajan en la nueva grilla. Revise el impacto y confirme con confirmar_cambios=1.'
            );
        }

        if ($preview['requiere_confirmacion'] && !$confirmar && empty($post['forzar_sin_confirmacion'])) {
            throw new BadRequestHttpException(
                'Confirme el cambio de agenda (confirmar_cambios=1) tras revisar el impacto.'
            );
        }

        $version = self::buildVersionFromPost($idPes, $idEfector, $post, $vigenteDesde, $intervaloNuevo);
        if (!$version->validate()) {
            throw new BadRequestHttpException(implode(' ', $version->getFirstErrors()));
        }
        if (!$version->save(false)) {
            throw new \RuntimeException('No se pudo guardar la versión de agenda.');
        }

        self::sincronizarAgendaEspejo($idPes, $idEfector, $version);
        self::crearConflictosDesdePreview((int) $version->id, $preview['conflictos'] ?? []);

        return [
            'message' => 'Agenda guardada. Vigente desde ' . $vigenteDesde . '.',
            'agenda_ui_completed' => '1',
            'id_agenda_version' => (int) $version->id,
            'preview' => $preview,
        ];
    }

    /**
     * @param list<array<string, mixed>> $conflictos
     */
    private static function crearConflictosDesdePreview(int $idVersion, array $conflictos): void
    {
        foreach ($conflictos as $c) {
            $idTurno = (int) ($c['id_turno'] ?? 0);
            if ($idTurno <= 0) {
                continue;
            }
            $existente = TurnoAgendaConflicto::find()
                ->where([
                    'id_turno' => $idTurno,
                    'id_agenda_version' => $idVersion,
                    'estado' => TurnoAgendaConflicto::ESTADO_PENDIENTE,
                ])
                ->exists();
            if ($existente) {
                continue;
            }
            $row = new TurnoAgendaConflicto();
            $row->id_turno = $idTurno;
            $row->id_agenda_version = $idVersion;
            $row->estado = TurnoAgendaConflicto::ESTADO_PENDIENTE;
            $row->opcion_hora_antes = self::horaParaDb($c['opcion_antes'] ?? null);
            $row->opcion_hora_despues = self::horaParaDb($c['opcion_despues'] ?? null);
            $row->save(false);
        }
    }

    private static function sincronizarAgendaEspejo(int $idPes, int $idEfector, ProfesionalEfectorServicioAgendaVersion $version): void
    {
        $agenda = ProfesionalEfectorServicioAgenda::findActivaPorProfesionalEfectorServicio($idPes);
        if ($agenda === null) {
            $agenda = new ProfesionalEfectorServicioAgenda();
            $agenda->id_profesional_efector_servicio = $idPes;
            $agenda->id_efector = $idEfector;
        }
        $agenda->formas_atencion = $version->formas_atencion;
        $agenda->cupo_pacientes = $version->cupo_pacientes;
        $agenda->intervalo_minutos = $version->getIntervaloMinutosEfectivo();
        $agenda->duracion_slot_minutos = null;
        $agenda->acepta_consultas_online = (bool) $version->acepta_consultas_online;
        foreach (['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'] as $col) {
            $agenda->{$col} = $version->{$col};
        }
        $agenda->save(false);
    }

    /**
     * @param array<string, mixed> $post
     */
    private static function buildVersionFromPost(
        int $idPes,
        int $idEfector,
        array $post,
        string $vigenteDesde,
        int $intervaloMinutos
    ): ProfesionalEfectorServicioAgendaVersion {
        $version = new ProfesionalEfectorServicioAgendaVersion();
        $version->id_profesional_efector_servicio = $idPes;
        $version->id_efector = $idEfector;
        $version->vigente_desde = $vigenteDesde;
        $version->intervalo_minutos = $intervaloMinutos;
        $version->load($post, '');
        $version->intervalo_minutos = $intervaloMinutos;
        $version->id_profesional_efector_servicio = $idPes;
        $version->id_efector = $idEfector;
        $version->vigente_desde = $vigenteDesde;

        return $version;
    }

    /**
     * @param array<string, mixed> $post
     */
    private static function parseVigenteDesde(array $post): string
    {
        $raw = trim((string) ($post['vigente_desde'] ?? ''));
        if ($raw === '') {
            $raw = date('Y-m-d', strtotime('+1 day'));
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            throw new BadRequestHttpException('vigente_desde inválido.');
        }
        $ymd = date('Y-m-d', $ts);
        $hoy = date('Y-m-d');
        if ($ymd < $hoy) {
            throw new BadRequestHttpException('vigente_desde no puede ser anterior a hoy.');
        }

        return $ymd;
    }

    /**
     * @return Turno[]
     */
    private static function turnosPendientesFuturos(int $idPes, string $desdeYmd): array
    {
        return Turno::find()
            ->where([
                'id_profesional_efector_servicio' => $idPes,
                'estado' => 'PENDIENTE',
            ])
            ->andWhere(['>=', 'fecha', $desdeYmd])
            ->orderBy(['fecha' => SORT_ASC, 'hora' => SORT_ASC])
            ->all();
    }

    private static function contarCambiosIntervaloEnAnio(int $idPes, int $anio): int
    {
        $desde = sprintf('%04d-01-01', $anio);
        $hasta = sprintf('%04d-12-31', $anio);
        $versions = ProfesionalEfectorServicioAgendaVersion::find()
            ->where(['id_profesional_efector_servicio' => $idPes])
            ->andWhere(['between', 'vigente_desde', $desde, $hasta])
            ->orderBy(['vigente_desde' => SORT_ASC])
            ->all();
        if (count($versions) < 2) {
            return 0;
        }
        $cambios = 0;
        $prev = null;
        foreach ($versions as $v) {
            $int = $v->getIntervaloMinutosEfectivo();
            if ($prev !== null && $prev !== $int) {
                $cambios++;
            }
            $prev = $int;
        }

        return $cambios;
    }

    private static function intervaloDesdeAgendaEspejo(int $idPes): int
    {
        $agenda = ProfesionalEfectorServicioAgenda::findActivaPorProfesionalEfectorServicio($idPes);
        if ($agenda === null) {
            return AgendaIntervaloMinutos::DEFAULT;
        }
        if (isset($agenda->intervalo_minutos) && (int) $agenda->intervalo_minutos > 0) {
            return AgendaIntervaloMinutos::normalize((int) $agenda->intervalo_minutos);
        }
        $dur = isset($agenda->duracion_slot_minutos) ? (int) $agenda->duracion_slot_minutos : 0;
        if (AgendaIntervaloMinutos::isAllowed($dur)) {
            return $dur;
        }

        return AgendaIntervaloMinutos::DEFAULT;
    }

    private static function buildMensajePreview(
        int $intervaloActual,
        int $intervaloNuevo,
        int $slotsAntes,
        int $slotsDespues,
        int $conflictos
    ): string {
        $msg = sprintf(
            'Intervalo: %d → %d min. Aprox. %d → %d turnos por día con horarios cargados.',
            $intervaloActual,
            $intervaloNuevo,
            $slotsAntes,
            $slotsDespues
        );
        if ($conflictos > 0) {
            $msg .= ' ' . $conflictos . ' turno(s) futuro(s) requerirán reprogramación o cancelación.';
        }

        return $msg;
    }

  /**
   * @param mixed $hora
   */
    private static function horaParaDb($hora): ?string
    {
        if ($hora === null || trim((string) $hora) === '') {
            return null;
        }

        return TurnoAgendaConflicto::normalizarHora((string) $hora);
    }

    /**
     * Resuelve conflicto del paciente: elige hora antes o después.
     */
    public static function resolverConflictoPaciente(int $idTurno, int $idPersona, string $eleccion): array
    {
        $eleccion = strtolower(trim($eleccion));
        if (!in_array($eleccion, ['antes', 'despues', 'cancelar'], true)) {
            throw new BadRequestHttpException('eleccion debe ser antes, despues o cancelar.');
        }

        /** @var TurnoAgendaConflicto|null $conf */
        $conf = TurnoAgendaConflicto::find()
            ->alias('c')
            ->innerJoin(['t' => Turno::tableName()], 't.id_turnos = c.id_turno')
            ->where([
                'c.id_turno' => $idTurno,
                'c.estado' => TurnoAgendaConflicto::ESTADO_PENDIENTE,
                't.id_persona' => $idPersona,
            ])
            ->one();
        if ($conf === null) {
            throw new BadRequestHttpException('No hay conflicto pendiente para este turno.');
        }

        $turno = $conf->turno;
        if ($turno === null) {
            throw new BadRequestHttpException('Turno no encontrado.');
        }

        if ($eleccion === 'cancelar') {
            $turno->estado = 'CANCELADO';
            $turno->save(false);
            $conf->estado = TurnoAgendaConflicto::ESTADO_CANCELADO;
            $conf->save(false);

            return ['message' => 'Turno cancelado.', 'estado' => 'cancelado'];
        }

        $hora = $eleccion === 'antes' ? $conf->opcion_hora_antes : $conf->opcion_hora_despues;
        if ($hora === null || trim((string) $hora) === '') {
            throw new BadRequestHttpException('La opción elegida no está disponible.');
        }

        $version = $conf->agendaVersion;
        $intervalo = $version !== null ? $version->getIntervaloMinutosEfectivo() : AgendaIntervaloMinutos::DEFAULT;
        $horaNorm = substr(TurnoAgendaConflicto::normalizarHora((string) $hora), 0, 5);
        $fin = TurnoAgendaConflicto::sumarMinutos($horaNorm . ':00', $intervalo);

        if (\common\components\Services\Turnos\TurnoSlotOccupancyService::haySolapamiento(
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
        $turno->save(false);

        $conf->estado = TurnoAgendaConflicto::ESTADO_REPROGRAMADO;
        $conf->hora_elegida = $hora;
        $conf->save(false);

        return [
            'message' => 'Turno reprogramado a las ' . $horaNorm . '.',
            'estado' => 'reprogramado',
            'fecha' => $turno->fecha,
            'hora' => $horaNorm,
        ];
    }
}
