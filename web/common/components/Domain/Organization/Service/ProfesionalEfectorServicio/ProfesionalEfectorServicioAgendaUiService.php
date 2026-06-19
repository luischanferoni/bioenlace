<?php

namespace common\components\Domain\Organization\Service\ProfesionalEfectorServicio;

use common\components\Domain\Organization\Service\Authorization\ProfesionalEfectorServicioDomainAuthorizationService;
use common\components\Platform\Core\Permission\IntentRequestContextService;
use common\components\Platform\Core\Permission\IntentSubmitFieldFilter;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\models\Condiciones_laborales;
use common\components\Domain\Organization\Service\ProfesionalEfectorServicio\AgendaIntervaloMinutos;
use common\models\ProfesionalEfectorServicio as ProfesionalEfectorServicioRecord;
use common\models\ProfesionalEfectorServicioAgenda;
use common\models\ProfesionalEfectorServicioCondicionLaboral;
use common\models\Servicio;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use Yii;

/**
 * Pre-carga y persistencia de agenda / condición laboral para UI JSON
 * ({@see \frontend\modules\api\v1\controllers\ProfesionalAgendaController::actionConfigurarAgenda}).
 */
final class ProfesionalEfectorServicioAgendaUiService
{
    /**
     * Valores para inyectar en campos del descriptor (query + merge en GET).
     *
     * @return array<string, mixed>
     */
    public static function buildFieldValuesForGet(int $idEfector, array $query): array
    {
        $idServicio = isset($query['id_servicio']) ? (int) $query['id_servicio'] : 0;
        $idPesIn = isset($query['id_profesional_efector_servicio']) ? (int) $query['id_profesional_efector_servicio'] : 0;
        $idStaff = ProfesionalEfectorServicioRecord::staffContextIdFromRequestParams($query);
        if ($idPesIn > 0) {
            $idStaff = $idPesIn;
        }

        $out = [
            'id_efector' => (string) $idEfector,
        ];

        $pesAnchor = null;
        if ($idPesIn > 0) {
            $pesAnchor = ProfesionalEfectorServicioRecord::findOne(['id' => $idPesIn, 'deleted_at' => null]);
            if ($pesAnchor === null || (int) $pesAnchor->id_efector !== $idEfector) {
                throw new BadRequestHttpException('id_profesional_efector_servicio inválido para este efector.');
            }
            $out['id_profesional_efector_servicio'] = (string) $idPesIn;
            if ($idServicio <= 0) {
                $idServicio = (int) $pesAnchor->id_servicio;
            }
            if ((int) $pesAnchor->id_servicio !== $idServicio) {
                throw new BadRequestHttpException('id_servicio no coincide con la asignación profesional seleccionada.');
            }
        }

        if ($idStaff > 0) {
            self::assertStaffContextPerteneceAEfector($idStaff, $idEfector);
        } elseif ($idPesIn <= 0) {
            return $out;
        }

        if ($idServicio <= 0) {
            return $out;
        }

        $servicioPre = Servicio::findOne($idServicio);
        if ($servicioPre !== null && strtoupper(trim((string) $servicioPre->acepta_turnos)) !== 'SI') {
            throw new BadRequestHttpException('Este servicio no admite agenda de turnos; no corresponde abrir la configuración de agenda.');
        }

        if ($idStaff > 0) {
            $idPersona = ProfesionalEfectorServicioRecord::resolveIdPersonaFromStaffContextId($idStaff);
            if ($idPersona === null || $idPersona <= 0) {
                return $out;
            }
            $pesServicio = ProfesionalEfectorServicioRecord::findOneActivoPorPersonaEfectorServicio(
                $idPersona,
                $idEfector,
                $idServicio
            );
            if ($pesServicio === null) {
                return $out;
            }
        }

        $out['id_servicio'] = (string) $idServicio;

        if ($pesAnchor !== null) {
            $pes = $pesAnchor;
        } elseif ($idStaff > 0) {
            $idPersona = ProfesionalEfectorServicioRecord::resolveIdPersonaFromStaffContextId($idStaff);
            if ($idPersona === null || $idPersona <= 0) {
                return $out;
            }
            $pes = ProfesionalEfectorServicioRecord::findOneActivoPorPersonaEfectorServicio(
                $idPersona,
                $idEfector,
                $idServicio
            );
            if ($pes === null) {
                $pes = new ProfesionalEfectorServicioRecord();
                $pes->id_persona = $idPersona;
                $pes->id_efector = $idEfector;
                $pes->id_servicio = $idServicio;
                if (!$pes->save()) {
                    return $out;
                }
            }
        } else {
            return $out;
        }

        $out['id_profesional_efector_servicio'] = (string) $pes->id;

        $agenda = ProfesionalEfectorServicioAgenda::findActivaPorProfesionalEfectorServicio((int) $pes->id);

        if ($agenda !== null) {
            $out['intervalo_minutos'] = (string) ($agenda->intervalo_minutos ?? AgendaIntervaloMinutos::DEFAULT);
            $out['formas_atencion'] = (string) $agenda->formas_atencion;
            $out['acepta_consultas_online'] = $agenda->acepta_consultas_online ? '1' : '0';
            foreach (['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'] as $col) {
                $out[$col] = (string) ($agenda->$col ?? '');
            }
        }

        if ($pes !== null) {
            $laboral = ProfesionalEfectorServicioCondicionLaboral::findUltimaActivaPorPes((int) $pes->id);
            if ($laboral !== null) {
                $out['id_condicion_laboral'] = (string) $laboral->id_condicion_laboral;
                $out['fecha_inicio'] = (string) ($laboral->fecha_inicio ?? '');
                $out['fecha_fin'] = (string) ($laboral->fecha_fin ?? '');
            }
        }

        $out['vigente_desde'] = date('Y-m-d', strtotime('+1 day'));

        return $out;
    }

    /**
     * Preview de impacto al cambiar intervalo / horarios (sin persistir).
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function previewAgendaConfig(int $idEfector, array $post): array
    {
        $idPes = self::resolvePesIdForAgendaSubmit($idEfector, $post);

        return ProfesionalEfectorServicioAgendaVersionService::previewImpacto($idPes, $idEfector, $post);
    }

    /**
     * Valores para precargar UI de condición laboral (solo RRHH).
     *
     * @return array<string, mixed>
     */
    public static function buildCondicionLaboralValuesForGet(
        int $idEfector,
        array $query,
        bool $allowOwnPesFallback = true
    ): array {
        $idPesIn = isset($query['id_profesional_efector_servicio']) ? (int) $query['id_profesional_efector_servicio'] : 0;
        $idStaff = ProfesionalEfectorServicioRecord::staffContextIdFromRequestParams($query);
        if ($idPesIn > 0) {
            $idStaff = $idPesIn;
        }
        $out = [
            'id_efector' => (string) $idEfector,
        ];
        if ($idPesIn > 0) {
            $pes = ProfesionalEfectorServicioRecord::findOne(['id' => $idPesIn, 'deleted_at' => null]);
            if ($pes !== null && (int) $pes->id_efector === $idEfector) {
                $out['id_profesional_efector_servicio'] = (string) $idPesIn;
            }
        }
        if (!isset($out['id_profesional_efector_servicio']) && $allowOwnPesFallback) {
            $resolvedOwn = self::resolveOwnPesIdInEfector($idEfector, $query);
            if ($resolvedOwn > 0) {
                $out['id_profesional_efector_servicio'] = (string) $resolvedOwn;
                $idStaff = $resolvedOwn;
            }
        }
        if ($idStaff <= 0 && !isset($out['id_profesional_efector_servicio'])) {
            return $out;
        }
        if ($idStaff > 0) {
            self::assertStaffContextPerteneceAEfector($idStaff, $idEfector);
        }
        if (!isset($out['id_profesional_efector_servicio']) && $idStaff > 0) {
            $idPersonaLegacy = ProfesionalEfectorServicioRecord::resolveIdPersonaFromStaffContextId($idStaff);
            if ($idPersonaLegacy !== null && $idPersonaLegacy > 0) {
                $pesAny = ProfesionalEfectorServicioRecord::find()
                    ->where([
                        'id_persona' => $idPersonaLegacy,
                        'id_efector' => $idEfector,
                        'deleted_at' => null,
                    ])
                    ->orderBy(['id' => SORT_ASC])
                    ->one();
                if ($pesAny !== null) {
                    $out['id_profesional_efector_servicio'] = (string) $pesAny->id;
                }
            }
        }

        $idPesLaboral = isset($out['id_profesional_efector_servicio']) ? (int) $out['id_profesional_efector_servicio'] : 0;
        if ($idPesLaboral > 0) {
            $laboral = ProfesionalEfectorServicioCondicionLaboral::findUltimaActivaPorPes($idPesLaboral);
            if ($laboral !== null) {
                $out['id_condicion_laboral'] = (string) $laboral->id_condicion_laboral;
                $out['fecha_inicio'] = (string) ($laboral->fecha_inicio ?? '');
                $out['fecha_fin'] = (string) ($laboral->fecha_fin ?? '');
            }
        }

        return $out;
    }

    /**
     * Valores para precargar UI de licencia (solo fechas; el tipo contractual ya está en el PES).
     *
     * @return array<string, mixed>
     */
    public static function buildLicenciaValuesForGet(
        int $idEfector,
        array $query,
        bool $allowOwnPesFallback = true
    ): array {
        $out = self::buildCondicionLaboralValuesForGet($idEfector, $query, $allowOwnPesFallback);
        unset($out['id_condicion_laboral']);
        $fi = isset($out['fecha_inicio']) ? trim((string) $out['fecha_inicio']) : '';
        $ff = isset($out['fecha_fin']) ? trim((string) $out['fecha_fin']) : '';
        $out['dias_licencia'] = LicenciaRangoDiasFormatter::countInclusiveCalendarDays(
            $fi !== '' ? $fi : null,
            $ff !== '' ? $ff : null
        );
        $out['dias_licencia_leyenda'] = LicenciaRangoDiasFormatter::leyendaFromIso(
            $fi !== '' ? $fi : null,
            $ff !== '' ? $ff : null
        );

        return $out;
    }

    /**
     * Persistencia: agenda del servicio seleccionado (sin condición laboral).
     *
     * @param array<string, mixed> $post
     * @return array{message: string, agenda_ui_completed: string}
     */
    public static function submitAgendaConfig(int $idEfector, array $post): array
    {
        $idServicio = (int) ($post['id_servicio'] ?? 0);
        $idPesPost = (int) ($post['id_profesional_efector_servicio'] ?? 0);
        if ($idPesPost > 0) {
            $pesFromPost = ProfesionalEfectorServicioRecord::findOne(['id' => $idPesPost, 'deleted_at' => null]);
            if ($pesFromPost !== null && $idServicio <= 0) {
                $idServicio = (int) $pesFromPost->id_servicio;
            }
        }

        if ($idServicio === 62) {
            throw new BadRequestHttpException('Servicio no editable en este flujo.');
        }

        $servicio = Servicio::findOne($idServicio);
        if ($servicio === null) {
            throw new NotFoundHttpException('Servicio inexistente.');
        }

        if (strtoupper(trim((string) $servicio->acepta_turnos)) !== 'SI') {
            throw new BadRequestHttpException('Este servicio no admite agenda de turnos; no se puede guardar configuración de agenda.');
        }

        $idPes = self::resolvePesIdForAgendaSubmit($idEfector, $post);

        return ProfesionalEfectorServicioAgendaVersionService::publicarVersion($idPes, $idEfector, $post);
    }

    /**
     * @param array<string, mixed> $post
     */
    public static function resolvePesIdForAgendaSubmitPublic(int $idEfector, array $post): int
    {
        return self::resolvePesIdForAgendaSubmit($idEfector, $post);
    }

    /**
     * @param array<string, mixed> $post
     */
    private static function resolvePesIdForAgendaSubmit(int $idEfector, array $post): int
    {
        $idStaff = ProfesionalEfectorServicioRecord::staffContextIdFromRequestParams($post);
        $idServicio = (int) ($post['id_servicio'] ?? 0);
        $idPesPost = (int) ($post['id_profesional_efector_servicio'] ?? 0);
        if ($idPesPost > 0) {
            $idStaff = $idPesPost;
        }

        if ($idPesPost > 0) {
            $pesFromPost = ProfesionalEfectorServicioRecord::findOne(['id' => $idPesPost, 'deleted_at' => null]);
            if ($pesFromPost === null || (int) $pesFromPost->id_efector !== $idEfector) {
                throw new BadRequestHttpException('id_profesional_efector_servicio inválido para este efector.');
            }

            return (int) $pesFromPost->id;
        }

        if ($idServicio <= 0 || $idStaff <= 0) {
            throw new BadRequestHttpException('Indique id_servicio e id_profesional_efector_servicio.');
        }

        self::assertStaffContextPerteneceAEfector($idStaff, $idEfector);
        $idPersona = ProfesionalEfectorServicioRecord::resolveIdPersonaFromStaffContextId($idStaff);
        if ($idPersona === null || $idPersona <= 0) {
            throw new BadRequestHttpException('El recurso humano no pertenece al efector en sesión.');
        }

        $pes = ProfesionalEfectorServicioRecord::findOneActivoPorPersonaEfectorServicio(
            $idPersona,
            $idEfector,
            $idServicio
        );
        if ($pes === null) {
            throw new BadRequestHttpException('No se pudo resolver la asignación profesional.');
        }

        return (int) $pes->id;
    }

    /**
     * Crear/editar condición laboral (upsert) de un RRHH (sin tocar agenda por servicio).
     *
     * @param array<string, mixed> $post
     * @return array{message: string, mensaje: string, condicion_laboral_ui_completed: string, fecha_inicio?: string|null, fecha_fin?: string|null, condicion_laboral_label?: string, servicio_detalle?: array{id_servicio: int, nombre: string}|null}
     */
    public static function submitCondicionLaboral(
        int $idEfector,
        array $post,
        ?string $defaultIntentId = null
    ): array {
        $allowOwnPesFallback = $defaultIntentId === null
            || !self::condicionLaboralIntentEsStaff((string) $defaultIntentId);
        $prepared = self::prepareCondicionLaboralSubmit($idEfector, $post, $defaultIntentId, $allowOwnPesFallback);

        return self::persistPreparedCondicionLaboral($prepared);
    }

    /**
     * Valida permisos y arma el modelo de condición laboral sin persistir.
     *
     * @param array<string, mixed> $post
     * @return array{
     *   id_pes: int,
     *   intent_id: string,
     *   id_condicion: int,
     *   laboral: ProfesionalEfectorServicioCondicionLaboral,
     *   was_new: bool,
     *   fecha_inicio: string|null,
     *   fecha_fin: string|null
     * }
     */
    public static function prepareCondicionLaboralSubmit(
        int $idEfector,
        array $post,
        ?string $defaultIntentId = null,
        bool $allowOwnPesFallback = true
    ): array {
        $ctx = new IntentRequestContextService();
        $intentId = $ctx->resolveIntentId($post, $defaultIntentId);
        $userId = (int) (Yii::$app->user->id ?? 0);
        $ctx->assertUserCanIntent($userId, $intentId);

        $post['intent_id'] = $intentId;
        $post = (new IntentSubmitFieldFilter())->filter($intentId, $post);

        $idStaff = ProfesionalEfectorServicioRecord::staffContextIdFromRequestParams($post);
        $idPes = (int) ($post['id_profesional_efector_servicio'] ?? 0);
        if ($idPes > 0) {
            $idStaff = $idPes;
        } elseif ($idStaff > 0) {
            self::assertStaffContextPerteneceAEfector($idStaff, $idEfector);
            $idPersonaLegacy = ProfesionalEfectorServicioRecord::resolveIdPersonaFromStaffContextId($idStaff);
            if ($idPersonaLegacy !== null && $idPersonaLegacy > 0) {
                $pesAny = ProfesionalEfectorServicioRecord::find()
                    ->where([
                        'id_persona' => $idPersonaLegacy,
                        'id_efector' => $idEfector,
                        'deleted_at' => null,
                    ])
                    ->orderBy(['id' => SORT_ASC])
                    ->one();
                if ($pesAny !== null) {
                    $idPes = (int) $pesAny->id;
                }
            }
        }

        if ($idPes <= 0 && $allowOwnPesFallback) {
            $idPes = self::resolveOwnPesIdInEfector($idEfector, $post);
        }

        if ($idPes <= 0) {
            throw new BadRequestHttpException('Indique id_profesional_efector_servicio con PES en este efector.');
        }

        try {
            (new ProfesionalEfectorServicioDomainAuthorizationService())->assertCondicionLaboralForIntent(
                array_merge($post, [
                    'id_profesional_efector_servicio' => $idPes,
                    'id_efector' => $idEfector,
                ]),
                $intentId
            );
        } catch (DomainOperationForbiddenException $e) {
            throw new ForbiddenHttpException($e->getMessage() !== '' ? $e->getMessage() : 'No autorizado.');
        }

        $idCondicion = isset($post['id_condicion_laboral']) ? (int) $post['id_condicion_laboral'] : 0;
        if ($idCondicion <= 0 && self::condicionLaboralIntentEsLicencia($intentId)) {
            $laboralExistente = ProfesionalEfectorServicioCondicionLaboral::findUltimaActivaPorPes($idPes);
            if ($laboralExistente === null) {
                throw new BadRequestHttpException(
                    'No hay condición laboral registrada en este servicio. Pedí al administrador que complete el alta.'
                );
            }
            $idCondicion = (int) $laboralExistente->id_condicion_laboral;
        } elseif ($idCondicion <= 0) {
            throw new BadRequestHttpException('id_condicion_laboral es obligatorio.');
        }
        if (!Condiciones_laborales::find()->where(['id_condicion_laboral' => $idCondicion])->exists()) {
            throw new BadRequestHttpException('Condición laboral inválida.');
        }

        $laboral = ProfesionalEfectorServicioCondicionLaboral::find()
            ->where([
                'id_profesional_efector_servicio' => $idPes,
                'id_condicion_laboral' => $idCondicion,
                'deleted_at' => null,
            ])
            ->one();

        $wasNew = false;
        if ($laboral === null) {
            $wasNew = true;
            $laboral = new ProfesionalEfectorServicioCondicionLaboral();
            $laboral->id_profesional_efector_servicio = $idPes;
            $laboral->id_condicion_laboral = $idCondicion;
        }

        $fi = trim((string) ($post['fecha_inicio'] ?? ''));
        $ff = trim((string) ($post['fecha_fin'] ?? ''));
        $laboral->fecha_inicio = $fi !== '' ? $fi : null;
        $laboral->fecha_fin = $ff !== '' ? $ff : null;

        if (!$laboral->validate()) {
            throw new BadRequestHttpException(implode(' ', $laboral->getFirstErrors()));
        }

        return [
            'id_pes' => $idPes,
            'intent_id' => $intentId,
            'id_condicion' => $idCondicion,
            'laboral' => $laboral,
            'was_new' => $wasNew,
            'fecha_inicio' => $fi !== '' ? $fi : null,
            'fecha_fin' => $ff !== '' ? $ff : null,
        ];
    }

    /**
     * @param array{
     *   id_pes: int,
     *   intent_id: string,
     *   id_condicion: int,
     *   laboral: ProfesionalEfectorServicioCondicionLaboral,
     *   was_new: bool,
     *   fecha_inicio: string|null,
     *   fecha_fin: string|null
     * } $prepared
     * @return array{message: string, mensaje: string, condicion_laboral_ui_completed: string, fecha_inicio?: string|null, fecha_fin?: string|null, condicion_laboral_label?: string, servicio_detalle?: array{id_servicio: int, nombre: string}|null, dias_licencia?: int|null, dias_licencia_leyenda?: string, turnos_afectados?: int}
     */
    public static function persistPreparedCondicionLaboral(array $prepared): array
    {
        /** @var ProfesionalEfectorServicioCondicionLaboral $laboral */
        $laboral = $prepared['laboral'];
        if (!$laboral->save(false)) {
            throw new \RuntimeException('No se pudo guardar la condición laboral.');
        }

        return self::buildCondicionLaboralSubmitData(
            (int) $prepared['id_pes'],
            (int) $prepared['id_condicion'],
            $prepared['fecha_inicio'],
            $prepared['fecha_fin'],
            (string) $prepared['intent_id'],
            (bool) $prepared['was_new']
        );
    }

    /**
     * Payload de cierre para UI / asistente (patrón {@see \common\components\Domain\Scheduling\Service\TurnoPersistService::crear}).
     *
     * @return array{
     *   message: string,
     *   mensaje: string,
     *   condicion_laboral_ui_completed: string,
     *   fecha_inicio: string|null,
     *   fecha_fin: string|null,
     *   condicion_laboral_label: string,
     *   servicio_detalle: array{id_servicio: int, nombre: string}|null
     * }
     */
    private static function buildCondicionLaboralSubmitData(
        int $idPes,
        int $idCondicion,
        ?string $fechaInicio,
        ?string $fechaFin,
        string $intentId,
        bool $wasNew
    ): array {
        $pes = ProfesionalEfectorServicioRecord::findOne(['id' => $idPes, 'deleted_at' => null]);
        $condicion = Condiciones_laborales::findOne(['id_condicion_laboral' => $idCondicion]);
        $tipoNombre = $condicion !== null ? trim((string) $condicion->nombre) : '';
        $servicioNombre = '';
        $servicioDetalle = null;
        $profNombre = '';

        if ($pes !== null) {
            $servicio = $pes->servicio;
            if ($servicio !== null) {
                $servicioNombre = trim((string) $servicio->nombre);
                if ($servicioNombre !== '') {
                    $servicioDetalle = [
                        'id_servicio' => (int) $servicio->id_servicio,
                        'nombre' => $servicioNombre,
                    ];
                }
            }
            $persona = $pes->persona;
            if ($persona !== null) {
                $profNombre = trim(trim((string) $persona->apellido) . ', ' . trim((string) $persona->nombre));
            }
        }

        $vigencia = self::formatVigenciaPhrase($fechaInicio, $fechaFin);
        $isStaff = self::condicionLaboralIntentEsStaff($intentId);
        $diasLeyenda = self::condicionLaboralIntentEsLicencia($intentId)
            ? LicenciaRangoDiasFormatter::leyendaFromIso($fechaInicio, $fechaFin)
            : '';
        if ($diasLeyenda !== '' && $vigencia !== '') {
            $vigencia .= ' (' . $diasLeyenda . ')';
        }
        $mensaje = self::composeCondicionLaboralMensaje(
            $wasNew,
            $isStaff,
            $tipoNombre,
            $servicioNombre,
            $profNombre,
            $vigencia,
            $intentId
        );

        return [
            'message' => self::condicionLaboralIntentEsLicencia($intentId)
                ? 'Licencia guardada.'
                : 'Condición laboral guardada.',
            'mensaje' => $mensaje,
            'condicion_laboral_ui_completed' => '1',
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'dias_licencia' => LicenciaRangoDiasFormatter::countInclusiveCalendarDays($fechaInicio, $fechaFin),
            'dias_licencia_leyenda' => $diasLeyenda,
            'condicion_laboral_label' => $tipoNombre,
            'servicio_detalle' => $servicioDetalle,
        ];
    }

    private static function condicionLaboralIntentEsLicencia(string $intentId): bool
    {
        $intentId = trim($intentId);

        return $intentId !== '' && str_contains($intentId, 'licencia.cargar');
    }

    private static function condicionLaboralIntentEsStaff(string $intentId): bool
    {
        $intentId = trim($intentId);
        if ($intentId === '') {
            return false;
        }

        return str_contains($intentId, 'staff')
            || str_contains($intentId, 'cargar-para-profesional');
    }

    private static function composeCondicionLaboralMensaje(
        bool $wasNew,
        bool $isStaff,
        string $tipoNombre,
        string $servicioNombre,
        string $profNombre,
        string $vigencia,
        string $intentId = ''
    ): string {
        $servicio = $servicioNombre !== '' ? $servicioNombre : 'tu servicio';
        $vigenciaSuffix = $vigencia !== '' ? (' ' . $vigencia . '.') : '.';
        $verbo = $wasNew ? 'Registramos' : 'Actualizamos';

        if (self::condicionLaboralIntentEsLicencia($intentId)) {
            if ($isStaff && $profNombre !== '') {
                return $verbo . ' la licencia de ' . $profNombre . ' en ' . $servicio . $vigenciaSuffix;
            }

            return $verbo . ' tu licencia en ' . $servicio . $vigenciaSuffix;
        }

        $tipo = $tipoNombre !== '' ? $tipoNombre : 'condición laboral';

        if ($isStaff && $profNombre !== '') {
            return $verbo . ' la condición laboral (' . $tipo . ') de ' . $profNombre . ' en ' . $servicio . $vigenciaSuffix;
        }

        return $verbo . ' tu condición laboral (' . $tipo . ') en ' . $servicio . $vigenciaSuffix;
    }

    private static function formatVigenciaPhrase(?string $fechaInicio, ?string $fechaFin): string
    {
        $fi = self::formatFechaEsDisplay($fechaInicio);
        $ff = self::formatFechaEsDisplay($fechaFin);
        if ($fi !== '' && $ff !== '') {
            return 'del ' . $fi . ' al ' . $ff;
        }
        if ($fi !== '') {
            return 'desde el ' . $fi;
        }
        if ($ff !== '') {
            return 'hasta el ' . $ff;
        }

        return '';
    }

    private static function formatFechaEsDisplay(?string $iso): string
    {
        $s = trim((string) $iso);
        if ($s === '') {
            return '';
        }
        try {
            return (new \DateTimeImmutable($s))->format('d/m/Y');
        } catch (\Throwable $e) {
            return $s;
        }
    }

    private static function assertStaffContextPerteneceAEfector(int $idStaffContext, int $idEfector): void
    {
        if ($idEfector <= 0) {
            throw new BadRequestHttpException('No hay efector en sesión.');
        }

        if (!ProfesionalEfectorServicioRecord::staffContextTienePesEnEfector($idStaffContext, $idEfector)) {
            throw new ForbiddenHttpException('El profesional no pertenece al efector actual.');
        }
    }

    /**
     * PES propio en el efector para UI/submit «como profesional» (licencia, condición laboral).
     * Prioridad: param explícito → persona+servicio → PES de sesión operativa → único PES en efector.
     *
     * @param array<string, mixed> $params
     */
    private static function resolveOwnPesIdInEfector(int $idEfector, array $params): int
    {
        if ($idEfector <= 0) {
            return 0;
        }

        $idPes = isset($params['id_profesional_efector_servicio']) ? (int) $params['id_profesional_efector_servicio'] : 0;
        if ($idPes > 0) {
            $pes = ProfesionalEfectorServicioRecord::findOne(['id' => $idPes, 'deleted_at' => null]);
            if ($pes !== null && (int) $pes->id_efector === $idEfector) {
                return $idPes;
            }

            return 0;
        }

        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            return 0;
        }

        $idServicio = isset($params['id_servicio']) ? (int) $params['id_servicio'] : 0;
        if ($idServicio > 0) {
            $pes = ProfesionalEfectorServicioRecord::findOneActivoPorPersonaEfectorServicio(
                $idPersona,
                $idEfector,
                $idServicio
            );

            return $pes !== null ? (int) $pes->id : 0;
        }

        $idPesSesion = (int) (Yii::$app->user->getIdProfesionalEfectorServicio() ?? 0);
        if ($idPesSesion > 0) {
            $pesSesion = ProfesionalEfectorServicioRecord::findOne(['id' => $idPesSesion, 'deleted_at' => null]);
            if (
                $pesSesion !== null
                && (int) $pesSesion->id_efector === $idEfector
                && (int) $pesSesion->id_persona === $idPersona
            ) {
                return $idPesSesion;
            }
        }

        $pesRows = ProfesionalEfectorServicioRecord::find()
            ->where([
                'id_persona' => $idPersona,
                'id_efector' => $idEfector,
                'deleted_at' => null,
            ])
            ->all();
        if (count($pesRows) === 1) {
            return (int) $pesRows[0]->id;
        }

        return 0;
    }
}
