<?php

namespace common\components\Domain\Organization\Service\ProfesionalCobertura;

use common\components\Domain\Organization\Service\AgendaWeeklyOccupancyService;
use common\components\Platform\Core\Product\AgendaByEncounterClassMetadata;
use common\models\Clinical\Encounter;
use common\models\Person\Persona;
use common\models\ProfesionalCobertura;
use common\models\ProfesionalEfectorServicio;
use common\models\Servicio;

/**
 * Consultas de cobertura activa y conflictos vs grilla AMB.
 */
final class ProfesionalCoberturaActivaService
{
    /**
     * Coberturas vigentes en un instante (default: ahora) para un efector y clase.
     *
     * @return list<array<string, mixed>>
     */
    public static function listarActivas(
        int $idEfector,
        string $encounterClass,
        ?string $atDateTime = null,
        ?int $idServicio = null
    ): array {
        if ($idEfector <= 0 || !AgendaByEncounterClassMetadata::isCoberturaClass($encounterClass)) {
            return [];
        }

        $at = $atDateTime !== null && trim($atDateTime) !== ''
            ? date('Y-m-d H:i:s', strtotime($atDateTime) ?: time())
            : date('Y-m-d H:i:s');

        $q = ProfesionalCobertura::find()
            ->alias('c')
            ->andWhere([
                'c.id_efector' => $idEfector,
                'c.encounter_class' => $encounterClass,
                'c.deleted_at' => null,
            ])
            ->andWhere(['<=', 'c.inicio', $at])
            ->andWhere(['>', 'c.fin', $at])
            ->orderBy(['c.inicio' => SORT_ASC, 'c.id' => SORT_ASC]);

        if ($idServicio !== null && $idServicio > 0) {
            $q->andWhere([
                'or',
                ['c.id_servicio' => $idServicio],
                ['c.id_servicio' => null],
            ]);
        }

        /** @var list<ProfesionalCobertura> $rows */
        $rows = $q->with(['persona', 'servicio'])->all();
        $out = [];
        foreach ($rows as $row) {
            $out[] = self::serializeActiva($row);
        }

        return $out;
    }

    /**
     * Payload completo (API cobertura / listados de plantel).
     *
     * @return array{title: string, encounter_class: string, at: string, items: list<array<string, mixed>>, total: int, empty_message: null, session: array<string, mixed>}
     */
    public static function panelPayload(int $idEfector, string $encounterClass, ?string $atDateTime = null): array
    {
        $at = $atDateTime !== null && trim($atDateTime) !== ''
            ? date('Y-m-d H:i:s', strtotime($atDateTime) ?: time())
            : date('Y-m-d H:i:s');
        $items = self::listarActivas($idEfector, $encounterClass, $at);
        $session = self::buildSessionGate($idEfector, $encounterClass, $at);

        return [
            'title' => $encounterClass === Encounter::ENCOUNTER_CLASS_EMER
                ? 'Plantel de guardia'
                : 'Cobertura de piso',
            'encounter_class' => $encounterClass,
            'at' => $at,
            'items' => $items,
            'total' => count($items),
            // No informar al clínico que el plantel del efector está vacío.
            'empty_message' => null,
            'session' => $session,
        ];
    }

    /**
     * Gate de plantel para home/panel: solo lo que web/móvil usan (sin listar plantel).
     *
     * @return array{session: array{tiene_cobertura: bool, mensaje_sin_cobertura?: string}}
     */
    public static function homePanelGatePayload(
        int $idEfector,
        string $encounterClass,
        ?string $atDateTime = null
    ): array {
        $at = $atDateTime !== null && trim($atDateTime) !== ''
            ? date('Y-m-d H:i:s', strtotime($atDateTime) ?: time())
            : date('Y-m-d H:i:s');
        $session = self::buildSessionGate($idEfector, $encounterClass, $at);
        $slim = [
            'tiene_cobertura' => !empty($session['tiene_cobertura']),
        ];
        $msg = isset($session['mensaje_sin_cobertura'])
            ? trim((string) $session['mensaje_sin_cobertura'])
            : '';
        if ($msg !== '') {
            $slim['mensaje_sin_cobertura'] = $msg;
        }

        return ['session' => $slim];
    }

    /**
     * @return array{
     *   id_persona: int|null,
     *   tiene_cobertura: bool,
     *   proxima_cobertura_inicio: string|null,
     *   mensaje_sin_cobertura: string|null
     * }
     */
    private static function buildSessionGate(int $idEfector, string $encounterClass, string $at): array
    {
        $idPersonaSesion = 0;
        if (\Yii::$app->has('user', true)) {
            $idPersonaSesion = (int) (\Yii::$app->user->getIdPersona() ?? 0);
        }
        $sessionTiene = $idPersonaSesion > 0
            && self::personaTieneCoberturaActiva($idPersonaSesion, $idEfector, $encounterClass, $at);

        $proximaInicio = null;
        if (!$sessionTiene && $idPersonaSesion > 0) {
            $proximaInicio = self::proximaCoberturaInicio($idPersonaSesion, $idEfector, $encounterClass, $at);
        }
        $mensajeSin = $sessionTiene
            ? null
            : self::mensajeSinCoberturaParaSesion($encounterClass, [
                'proxima_inicio' => $proximaInicio,
            ]);

        return [
            'id_persona' => $idPersonaSesion > 0 ? $idPersonaSesion : null,
            'tiene_cobertura' => $sessionTiene,
            'proxima_cobertura_inicio' => $proximaInicio,
            'mensaje_sin_cobertura' => $mensajeSin,
        ];
    }

    /**
     * Texto accionable cuando la sesión no tiene plantel vigente (EMER/IMP).
     * Sin la palabra «cobertura» (UX clínico).
     *
     * @param array{proxima_inicio?: string|null} $ctx
     */
    public static function mensajeSinCoberturaParaSesion(string $encounterClass, array $ctx = []): string
    {
        $encounterClass = strtoupper(trim($encounterClass));
        $esPiso = $encounterClass === Encounter::ENCOUNTER_CLASS_IMP;
        $ambito = $esPiso ? 'de piso' : 'de guardia';
        $proxima = isset($ctx['proxima_inicio']) ? trim((string) $ctx['proxima_inicio']) : '';
        if ($proxima !== '') {
            $cuando = self::formatFechaHoraCobertura($proxima);

            return 'No estás de plantel ' . $ambito . ' ahora. Tu próximo horario es el '
                . $cuando . '. Si necesitás atender ya, configurá tus horarios en el Asistente '
                . '(«Configurar mis horarios») o pedile a coordinación / administración del centro.';
        }

        if ($esPiso) {
            return 'No tenés horario de plantel de piso cargado. Para ver internados, configurá tus '
                . 'horarios en el Asistente («Configurar mis horarios») o pedile a coordinación / '
                . 'administración del centro que te los asigne.';
        }

        return 'No tenés horario de plantel de guardia cargado. Para ver el tablero y atender, '
            . 'configurá tus horarios en el Asistente («Configurar mis horarios») o pedile a '
            . 'coordinación / administración del centro que te los asigne.';
    }

    /**
     * Próximo inicio de cobertura de la persona (después de `$at`), o null.
     */
    public static function proximaCoberturaInicio(
        int $idPersona,
        int $idEfector,
        string $encounterClass,
        ?string $atDateTime = null
    ): ?string {
        if ($idPersona <= 0 || $idEfector <= 0 || !AgendaByEncounterClassMetadata::isCoberturaClass($encounterClass)) {
            return null;
        }
        $at = $atDateTime !== null && trim($atDateTime) !== ''
            ? date('Y-m-d H:i:s', strtotime($atDateTime) ?: time())
            : date('Y-m-d H:i:s');

        $inicio = ProfesionalCobertura::find()
            ->select(['inicio'])
            ->andWhere([
                'id_persona' => $idPersona,
                'id_efector' => $idEfector,
                'encounter_class' => $encounterClass,
                'deleted_at' => null,
            ])
            ->andWhere(['>', 'inicio', $at])
            ->orderBy(['inicio' => SORT_ASC])
            ->limit(1)
            ->scalar();

        if (!is_string($inicio) || trim($inicio) === '') {
            return null;
        }

        return $inicio;
    }

    private static function formatFechaHoraCobertura(string $inicio): string
    {
        $ts = strtotime($inicio);
        if ($ts === false) {
            return $inicio;
        }
        $dias = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
        $dia = $dias[(int) date('w', $ts)] ?? '';

        return trim($dia . ' ' . date('d/m', $ts) . ' a las ' . date('H:i', $ts));
    }

    /**
     * ¿La persona tiene cobertura activa de la clase en el efector?
     */
    public static function personaTieneCoberturaActiva(
        int $idPersona,
        int $idEfector,
        string $encounterClass,
        ?string $atDateTime = null
    ): bool {
        if ($idPersona <= 0 || $idEfector <= 0 || !AgendaByEncounterClassMetadata::isCoberturaClass($encounterClass)) {
            return false;
        }
        $at = $atDateTime !== null && trim($atDateTime) !== ''
            ? date('Y-m-d H:i:s', strtotime($atDateTime) ?: time())
            : date('Y-m-d H:i:s');

        return ProfesionalCobertura::find()
            ->andWhere([
                'id_persona' => $idPersona,
                'id_efector' => $idEfector,
                'encounter_class' => $encounterClass,
                'deleted_at' => null,
            ])
            ->andWhere(['<=', 'inicio', $at])
            ->andWhere(['>', 'fin', $at])
            ->exists();
    }

    /**
     * Valida PES para tomar/asignar caso EMER según metadata operativa.
     *
     * @throws \InvalidArgumentException
     */
    public static function assertPesPuedeAsignarEmer(int $idPes, int $idEfector): void
    {
        if (!AgendaByEncounterClassMetadata::emerAssignRequiresCobertura()) {
            return;
        }

        $pes = ProfesionalEfectorServicio::findOne(['id' => $idPes, 'deleted_at' => null]);
        if ($pes === null || (int) $pes->id_efector !== $idEfector) {
            throw new \InvalidArgumentException('La asignación profesional no pertenece al efector.');
        }

        $idPersona = (int) $pes->id_persona;
        $plantel = self::listarActivas($idEfector, Encounter::ENCOUNTER_CLASS_EMER);
        if ($plantel === [] && AgendaByEncounterClassMetadata::emerAssignAllowWithoutAnyPlantel()) {
            return;
        }

        if (!self::personaTieneCoberturaActiva($idPersona, $idEfector, Encounter::ENCOUNTER_CLASS_EMER)) {
            throw new \InvalidArgumentException(
                'Para tomar o asignar el caso hace falta cobertura de guardia vigente. '
                . 'Cargá el plantel (entrada/salida) antes de asignar.'
            );
        }
    }

    /**
     * Solapes con la grilla semanal AMB de la misma persona en el efector
     * (patrón lunes_2…, independiente de formas_atencion / slots generados).
     *
     * @return list<array<string, mixed>>
     */
    public static function detectAmbSlotConflicts(ProfesionalCobertura $model): array
    {
        if (!AgendaByEncounterClassMetadata::coberturaVsAmbSlots()) {
            return [];
        }

        $idPersona = (int) $model->id_persona;
        $idEfector = (int) $model->id_efector;
        if ($idPersona <= 0 || $idEfector <= 0) {
            return [];
        }

        $busy = AgendaWeeklyOccupancyService::busyHours(
            $idPersona,
            $idEfector,
            (string) $model->encounter_class
        );
        $proposed = AgendaWeeklyOccupancyService::proposedHoursFromDatetimeRange(
            (string) $model->inicio,
            (string) $model->fin
        );
        $overlap = AgendaWeeklyOccupancyService::intersectingHours($proposed, $busy);
        if ($overlap === []) {
            return [];
        }

        return [[
            'kind' => 'amb_weekly_grid',
            'message' => AgendaWeeklyOccupancyService::conflictMessage($overlap),
            'hours' => AgendaWeeklyOccupancyService::toCsvMap($overlap),
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function serializeActiva(ProfesionalCobertura $row): array
    {
        $base = ProfesionalCoberturaService::toApiArray($row);
        $persona = $row->persona;
        if ($persona instanceof Persona) {
            $base['persona'] = [
                'id' => (int) $persona->id_persona,
                'nombre_completo' => trim($persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N)),
            ];
        }
        $svc = $row->servicio;
        if ($svc instanceof Servicio) {
            $base['servicio'] = [
                'id' => (int) $svc->id_servicio,
                'nombre' => (string) $svc->nombre,
            ];
        }

        return $base;
    }
}
