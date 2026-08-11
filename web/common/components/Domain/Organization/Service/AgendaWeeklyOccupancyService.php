<?php

namespace common\components\Domain\Organization\Service;

use common\components\Platform\Core\Product\AgendaByEncounterClassMetadata;
use common\models\Clinical\Encounter;
use common\models\ProfesionalCoberturaPlantilla;
use common\models\ProfesionalEfectorServicio;
use common\models\ProfesionalEfectorServicioAgenda;

/**
 * Horas de la grilla semanal ya tomadas por otra clase de encounter (misma persona + efector).
 * La ocupación se lee del patrón semanal (columnas lunes_2…), no de slots generados:
 * una agenda AMB en SIN_ATENCION igual ocupa el día/hora en el patrón.
 * AMB usa el espejo `profesional_efector_servicio_agenda` (igual que configurar-agenda),
 * no una versión vieja que siga vigente hasta la fecha de la grilla nueva.
 */
final class AgendaWeeklyOccupancyService
{
    public const DAY_COLUMNS = [
        1 => 'lunes_2',
        2 => 'martes_2',
        3 => 'miercoles_2',
        4 => 'jueves_2',
        5 => 'viernes_2',
        6 => 'sabado_2',
        7 => 'domingo_2',
    ];

    public const DAY_LABELS = [
        'lunes_2' => 'Lunes',
        'martes_2' => 'Martes',
        'miercoles_2' => 'Miércoles',
        'jueves_2' => 'Jueves',
        'viernes_2' => 'Viernes',
        'sabado_2' => 'Sábado',
        'domingo_2' => 'Domingo',
    ];

    public const HINT_BUSY = 'Las celdas en gris ya están tomadas por otra agenda de este profesional en el efector (otro tipo de encounter). Podés elegir horas libres; por ejemplo cobertura de noche si el ambulatorio es de día.';

    /**
     * @return array<string, list<int>> columna → horas 0–23 ocupadas
     */
    public static function busyHours(
        int $idPersona,
        int $idEfector,
        string $editingEncounterClass,
        ?int $excludePesId = null
    ): array {
        if ($idPersona <= 0 || $idEfector <= 0) {
            return self::emptyByColumn();
        }
        $editingEncounterClass = strtoupper(trim($editingEncounterClass));
        $busy = self::emptyByColumn();

        if (AgendaByEncounterClassMetadata::coberturaVsAmbSlots()
            && $editingEncounterClass !== Encounter::ENCOUNTER_CLASS_AMB
        ) {
            $busy = self::mergeByColumn($busy, self::hoursFromAmbAgendas($idPersona, $idEfector, null));
        }

        if ($editingEncounterClass === Encounter::ENCOUNTER_CLASS_AMB) {
            if (AgendaByEncounterClassMetadata::coberturaVsAmbSlots()) {
                $busy = self::mergeByColumn(
                    $busy,
                    self::hoursFromCoberturaPlantillas($idPersona, $idEfector, null)
                );
            }
            $busy = self::mergeByColumn(
                $busy,
                self::hoursFromAmbAgendas($idPersona, $idEfector, $excludePesId)
            );
        } elseif (AgendaByEncounterClassMetadata::coberturaOverlapSamePersonaEfector()) {
            $busy = self::mergeByColumn(
                $busy,
                self::hoursFromCoberturaPlantillas($idPersona, $idEfector, $editingEncounterClass)
            );
        }

        return $busy;
    }

    /**
     * @param array<string, mixed> $ui
     * @return array<string, mixed>
     */
    public static function attachToUi(
        array $ui,
        int $idPersona,
        int $idEfector,
        string $editingEncounterClass,
        ?int $excludePesId = null
    ): array {
        if ($idPersona <= 0 || $idEfector <= 0) {
            return $ui;
        }

        return self::attachBusyToWeeklySchedulerUi(
            $ui,
            self::busyHours($idPersona, $idEfector, $editingEncounterClass, $excludePesId),
            self::HINT_BUSY
        );
    }

    /**
     * @param array<string, mixed> $proposedCsv columnas lunes_2… → CSV
     * @param array<string, list<int>> $busy
     */
    public static function overlapError(array $proposedCsv, array $busy): ?string
    {
        $overlap = self::intersectingHours(self::hoursFromCsvMap($proposedCsv), $busy);
        if ($overlap === []) {
            return null;
        }

        return self::conflictMessage($overlap);
    }

    /**
     * Horas del patrón semanal cubiertas por un intervalo absoluto [inicio, fin).
     *
     * @return array<string, list<int>>
     */
    public static function proposedHoursFromDatetimeRange(string $inicio, string $fin): array
    {
        $start = strtotime($inicio);
        $end = strtotime($fin);
        $out = self::emptyByColumn();
        if ($start === false || $end === false || $end <= $start) {
            return $out;
        }
        $cursor = strtotime(date('Y-m-d', $start) . ' 00:00:00');
        $lastDay = strtotime(date('Y-m-d', $end - 1) . ' 00:00:00');
        if ($cursor === false || $lastDay === false) {
            return $out;
        }
        while ($cursor <= $lastDay) {
            $n = (int) date('N', $cursor);
            $col = self::DAY_COLUMNS[$n] ?? null;
            if ($col !== null) {
                for ($h = 0; $h < 24; $h++) {
                    $hStart = $cursor + ($h * 3600);
                    $hEnd = $hStart + 3600;
                    if ($hStart < $end && $hEnd > $start) {
                        $out[$col][] = $h;
                    }
                }
                $out[$col] = self::parseHourCsv(self::csvFromHours($out[$col]));
            }
            $next = strtotime('+1 day', $cursor);
            if ($next === false) {
                break;
            }
            $cursor = $next;
        }

        return $out;
    }

    /**
     * @param array<string, list<int>> $busy
     * @return array<string, string> columna → CSV
     */
    public static function toCsvMap(array $busy): array
    {
        $out = [];
        foreach (self::DAY_COLUMNS as $col) {
            $hours = $busy[$col] ?? [];
            if ($hours !== []) {
                $out[$col] = self::csvFromHours($hours);
            }
        }

        return $out;
    }

    /**
     * @param array<string, list<int>> $proposed
     * @param array<string, list<int>> $busy
     * @return array<string, list<int>> columnas con intersección
     */
    public static function intersectingHours(array $proposed, array $busy): array
    {
        $out = [];
        foreach (self::DAY_COLUMNS as $col) {
            $hit = array_values(array_intersect($proposed[$col] ?? [], $busy[$col] ?? []));
            if ($hit !== []) {
                $out[$col] = $hit;
            }
        }

        return $out;
    }

    /**
     * @param array<string, list<int>> $hoursByCol
     * @param array<string, list<int>> $busy
     * @return array<string, list<int>>
     */
    public static function subtractBusy(array $hoursByCol, array $busy): array
    {
        $out = [];
        foreach (self::DAY_COLUMNS as $col) {
            $hours = $hoursByCol[$col] ?? [];
            $block = $busy[$col] ?? [];
            if ($hours === []) {
                $out[$col] = [];
                continue;
            }
            if ($block === []) {
                $out[$col] = $hours;
                continue;
            }
            $blockSet = array_fill_keys($block, true);
            $kept = [];
            foreach ($hours as $h) {
                if (!isset($blockSet[$h])) {
                    $kept[] = $h;
                }
            }
            $out[$col] = $kept;
        }

        return $out;
    }

    /**
     * @return list<int>
     */
    public static function parseHourCsv(string $csv): array
    {
        $hours = [];
        foreach (explode(',', $csv) as $part) {
            $part = trim($part);
            if ($part === '' || !ctype_digit($part)) {
                continue;
            }
            $h = (int) $part;
            if ($h >= 0 && $h <= 23) {
                $hours[$h] = $h;
            }
        }
        $sorted = array_values($hours);
        sort($sorted, SORT_NUMERIC);

        return $sorted;
    }

    /**
     * @param list<int> $hours
     */
    public static function csvFromHours(array $hours): string
    {
        $uniq = [];
        foreach ($hours as $h) {
            $h = (int) $h;
            if ($h >= 0 && $h <= 23) {
                $uniq[$h] = $h;
            }
        }
        $sorted = array_values($uniq);
        sort($sorted, SORT_NUMERIC);

        return implode(',', $sorted);
    }

    /**
     * @param array<string, list<int>> $overlap
     */
    public static function conflictMessage(array $overlap): string
    {
        $parts = [];
        foreach (self::DAY_COLUMNS as $col) {
            if (!isset($overlap[$col]) || $overlap[$col] === []) {
                continue;
            }
            $label = self::DAY_LABELS[$col] ?? $col;
            $parts[] = $label . ' ' . self::formatHourRange($overlap[$col]);
        }
        if ($parts === []) {
            return 'Hay horarios que ya están tomados por otra agenda de este profesional en el efector.';
        }

        return 'Esos horarios ya están tomados por otra agenda (otro tipo de encounter) de este profesional en el efector: '
            . implode('; ', $parts) . '.';
    }

    /**
     * @param array<string, mixed> $ui
     * @param array<string, list<int>> $busy
     * @return array<string, mixed>
     */
    public static function attachBusyToWeeklySchedulerUi(array $ui, array $busy, string $hint): array
    {
        $csv = self::toCsvMap($busy);
        if ($csv === [] || !isset($ui['blocks']) || !is_array($ui['blocks'])) {
            return $ui;
        }
        foreach ($ui['blocks'] as &$block) {
            if (!is_array($block) || ($block['kind'] ?? '') !== 'fields' || !isset($block['fields']) || !is_array($block['fields'])) {
                continue;
            }
            foreach ($block['fields'] as &$field) {
                if (!is_array($field) || ($field['widget_id'] ?? '') !== 'weekly_scheduler') {
                    continue;
                }
                $props = is_array($field['props'] ?? null) ? $field['props'] : [];
                $props['busy'] = $csv;
                $field['props'] = $props;
                $prevHint = trim((string) ($field['hint'] ?? ''));
                $field['hint'] = $prevHint === '' ? $hint : ($prevHint . ' ' . $hint);
                $initial = is_array($field['initial_values'] ?? null) ? $field['initial_values'] : [];
                if ($initial !== []) {
                    $kept = self::subtractBusy(self::hoursFromCsvMap($initial), $busy);
                    $newInitial = [];
                    foreach (self::DAY_COLUMNS as $col) {
                        if (($kept[$col] ?? []) !== []) {
                            $newInitial[$col] = self::csvFromHours($kept[$col]);
                        }
                    }
                    $field['initial_values'] = $newInitial;
                }
            }
            unset($field);
        }
        unset($block);

        return $ui;
    }

    /**
     * @param list<int> $hours
     */
    public static function formatHourRange(array $hours): string
    {
        if ($hours === []) {
            return '';
        }
        $sorted = array_values(array_unique(array_map('intval', $hours)));
        sort($sorted, SORT_NUMERIC);
        $parts = [];
        $runStart = $sorted[0];
        $prev = $sorted[0];
        for ($i = 1; $i < count($sorted); $i++) {
            if ($sorted[$i] === $prev + 1) {
                $prev = $sorted[$i];
                continue;
            }
            $parts[] = self::formatRun($runStart, $prev);
            $runStart = $sorted[$i];
            $prev = $sorted[$i];
        }
        $parts[] = self::formatRun($runStart, $prev);

        return implode(', ', $parts);
    }

    /**
     * @return array<string, list<int>>
     */
    public static function hoursFromCsvMap(array $data): array
    {
        $out = self::emptyByColumn();
        foreach (self::DAY_COLUMNS as $col) {
            $raw = $data[$col] ?? '';
            if (!is_string($raw) && !is_numeric($raw)) {
                $raw = '';
            }
            $raw = trim((string) $raw);
            $out[$col] = $raw === '' ? [] : self::parseHourCsv($raw);
        }

        return $out;
    }

    /**
     * @return array<string, list<int>>
     */
    private static function emptyByColumn(): array
    {
        $out = [];
        foreach (self::DAY_COLUMNS as $col) {
            $out[$col] = [];
        }

        return $out;
    }

    /**
     * @param array<string, list<int>> $a
     * @param array<string, list<int>> $b
     * @return array<string, list<int>>
     */
    private static function mergeByColumn(array $a, array $b): array
    {
        $out = $a;
        foreach (self::DAY_COLUMNS as $col) {
            $out[$col] = self::parseHourCsv(
                self::csvFromHours(array_merge($a[$col] ?? [], $b[$col] ?? []))
            );
        }

        return $out;
    }

    /**
     * @return array<string, list<int>>
     */
    private static function hoursFromAmbAgendas(int $idPersona, int $idEfector, ?int $excludePesId): array
    {
        $pesQuery = ProfesionalEfectorServicio::find()
            ->select(['id'])
            ->where([
                'id_persona' => $idPersona,
                'id_efector' => $idEfector,
                'deleted_at' => null,
            ]);
        if ($excludePesId !== null && $excludePesId > 0) {
            $pesQuery->andWhere(['<>', 'id', $excludePesId]);
        }
        $pesIds = $pesQuery->column();
        if ($pesIds === []) {
            return self::emptyByColumn();
        }

        $busy = self::emptyByColumn();
        // Misma fuente que configurar-agenda: espejo actual, no versiones aún vigentes
        // con un patrón viejo (p. ej. vigente hasta mañana mientras el espejo ya es el nuevo).
        $agendas = ProfesionalEfectorServicioAgenda::find()
            ->where(['id_profesional_efector_servicio' => $pesIds])
            ->andWhere(['deleted_at' => null])
            ->all();
        foreach ($agendas as $agenda) {
            $busy = self::mergeByColumn($busy, self::hoursFromCsvMap($agenda->getAttributes(array_values(self::DAY_COLUMNS))));
        }

        return $busy;
    }

    /**
     * @return array<string, list<int>>
     */
    private static function hoursFromCoberturaPlantillas(
        int $idPersona,
        int $idEfector,
        ?string $excludeEncounterClass
    ): array {
        $q = ProfesionalCoberturaPlantilla::find()
            ->where([
                'id_persona' => $idPersona,
                'id_efector' => $idEfector,
                'deleted_at' => null,
            ]);
        if ($excludeEncounterClass !== null && $excludeEncounterClass !== '') {
            $q->andWhere(['<>', 'encounter_class', $excludeEncounterClass]);
        }
        $busy = self::emptyByColumn();
        foreach ($q->all() as $plantilla) {
            $busy = self::mergeByColumn($busy, self::hoursFromCsvMap($plantilla->getAttributes(array_values(self::DAY_COLUMNS))));
        }

        return $busy;
    }

    private static function formatRun(int $start, int $end): string
    {
        if ($start === $end) {
            return sprintf('%02d:00', $start);
        }

        return sprintf('%02d:00–%02d:00', $start, $end + 1);
    }
}
