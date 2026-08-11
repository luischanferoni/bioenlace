<?php

namespace common\components\Domain\Organization\Service\ProfesionalCobertura;

use common\components\Domain\Organization\Service\AgendaWeeklyOccupancyService;
use common\models\Clinical\Encounter;
use common\models\ProfesionalCobertura;
use common\models\ProfesionalCoberturaPlantilla;
use common\models\ProfesionalEfectorServicio;
use Yii;

/**
 * Guarda plantilla semanal de cobertura y materializa intervalos en profesional_cobertura.
 */
final class ProfesionalCoberturaPlantillaService
{
    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, plantilla?: ProfesionalCoberturaPlantilla, created?: int, errors?: array<string, list<string>>, conflicts?: list<array<string, mixed>>}
     */
    public static function guardarYMaterializar(array $data): array
    {
        $idPersona = (int) ($data['id_persona'] ?? 0);
        $idEfector = (int) ($data['id_efector'] ?? 0);
        $encounterClass = strtoupper(trim((string) ($data['encounter_class'] ?? '')));
        $vigenteDesde = trim((string) ($data['vigente_desde'] ?? ''));
        $semanas = (int) ($data['semanas'] ?? 4);
        if ($semanas < 1) {
            $semanas = 1;
        }
        if ($semanas > 12) {
            $semanas = 12;
        }

        if ($idPersona <= 0 || $idEfector <= 0) {
            return ['ok' => false, 'errors' => ['_error' => ['Persona y efector son obligatorios.']]];
        }
        if ($encounterClass !== Encounter::ENCOUNTER_CLASS_EMER
            && $encounterClass !== Encounter::ENCOUNTER_CLASS_IMP) {
            return ['ok' => false, 'errors' => ['encounter_class' => ['Elegí Urgencia/guardia o Internación.']]];
        }
        if ($vigenteDesde === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $vigenteDesde)) {
            return ['ok' => false, 'errors' => ['vigente_desde' => ['Indicá desde qué fecha aplica el patrón.']]];
        }

        $dayVals = [];
        $hasAny = false;
        foreach (ProfesionalCoberturaPlantilla::DAY_COLUMNS as $col) {
            $raw = isset($data[$col]) ? trim((string) $data[$col]) : '';
            $dayVals[$col] = $raw !== '' ? self::normalizeHourCsv($raw) : null;
            if ($dayVals[$col] !== null && $dayVals[$col] !== '') {
                $hasAny = true;
            }
        }
        if (!$hasAny) {
            return ['ok' => false, 'errors' => ['_error' => ['Marcá al menos un rango horario en la semana.']]];
        }

        $idPes = (int) ($data['id_profesional_efector_servicio'] ?? 0);
        $busy = AgendaWeeklyOccupancyService::busyHours(
            $idPersona,
            $idEfector,
            $encounterClass,
            $idPes > 0 ? $idPes : null
        );
        $overlapMsg = AgendaWeeklyOccupancyService::overlapError($dayVals, $busy);
        if ($overlapMsg !== null) {
            return ['ok' => false, 'errors' => ['_error' => [$overlapMsg]]];
        }
        $idServicio = isset($data['id_servicio']) && $data['id_servicio'] !== '' && $data['id_servicio'] !== null
            ? (int) $data['id_servicio']
            : null;
        if ($idPes > 0 && $idServicio === null) {
            $pes = ProfesionalEfectorServicio::findOne(['id' => $idPes, 'deleted_at' => null]);
            if ($pes !== null) {
                $idServicio = (int) $pes->id_servicio;
            }
        }

        $tx = Yii::$app->db->beginTransaction();
        try {
            $plantilla = self::findOrCreatePlantilla($idPersona, $idEfector, $encounterClass, $idPes > 0 ? $idPes : null);
            $plantilla->id_servicio = $idServicio;
            $plantilla->id_profesional_efector_servicio = $idPes > 0 ? $idPes : null;
            $plantilla->encounter_class = $encounterClass;
            $plantilla->vigente_desde = $vigenteDesde;
            $plantilla->semanas = $semanas;
            foreach ($dayVals as $col => $val) {
                $plantilla->$col = $val;
            }
            if (!$plantilla->save()) {
                $tx->rollBack();

                return ['ok' => false, 'errors' => $plantilla->getErrors()];
            }

            $finVentana = date('Y-m-d', strtotime($vigenteDesde . ' +' . $semanas . ' weeks'));
            self::softDeleteGeneratedInWindow(
                $idPersona,
                $idEfector,
                $encounterClass,
                $idPes > 0 ? $idPes : null,
                $vigenteDesde,
                $finVentana,
                (int) $plantilla->id
            );

            $created = 0;
            $conflicts = [];
            $intervals = self::expandIntervals($plantilla, $vigenteDesde, $semanas);
            foreach ($intervals as $interval) {
                $payload = [
                    'id_persona' => $idPersona,
                    'id_efector' => $idEfector,
                    'id_servicio' => $idServicio,
                    'id_profesional_efector_servicio' => $idPes > 0 ? $idPes : null,
                    'encounter_class' => $encounterClass,
                    'inicio' => $interval['inicio'],
                    'fin' => $interval['fin'],
                    'notas' => 'plantilla:' . (int) $plantilla->id,
                ];
                $result = ProfesionalCoberturaService::crear($payload);
                if (!$result['ok']) {
                    $tx->rollBack();

                    return [
                        'ok' => false,
                        'errors' => $result['errors'] ?? ['_error' => ['No se pudo materializar la cobertura.']],
                        'conflicts' => $result['conflicts'] ?? [],
                    ];
                }
                if (!empty($result['conflicts'])) {
                    foreach ($result['conflicts'] as $c) {
                        $conflicts[] = $c;
                    }
                }
                $created++;
            }

            $tx->commit();

            return [
                'ok' => true,
                'plantilla' => $plantilla,
                'created' => $created,
                'conflicts' => $conflicts,
            ];
        } catch (\Throwable $e) {
            $tx->rollBack();

            return ['ok' => false, 'errors' => ['_error' => [$e->getMessage()]]];
        }
    }

    public static function findActivaForContext(
        int $idPersona,
        int $idEfector,
        string $encounterClass,
        ?int $idPes
    ): ?ProfesionalCoberturaPlantilla {
        $q = ProfesionalCoberturaPlantilla::find()
            ->where([
                'id_persona' => $idPersona,
                'id_efector' => $idEfector,
                'encounter_class' => $encounterClass,
                'deleted_at' => null,
            ]);
        if ($idPes !== null && $idPes > 0) {
            $q->andWhere(['id_profesional_efector_servicio' => $idPes]);
        } else {
            $q->andWhere(['id_profesional_efector_servicio' => null]);
        }

        /** @var ProfesionalCoberturaPlantilla|null $row */
        $row = $q->orderBy(['id' => SORT_DESC])->one();

        return $row;
    }

    private static function findOrCreatePlantilla(
        int $idPersona,
        int $idEfector,
        string $encounterClass,
        ?int $idPes
    ): ProfesionalCoberturaPlantilla {
        $existing = self::findActivaForContext($idPersona, $idEfector, $encounterClass, $idPes);
        if ($existing !== null) {
            return $existing;
        }
        $m = new ProfesionalCoberturaPlantilla();
        $m->id_persona = $idPersona;
        $m->id_efector = $idEfector;
        $m->encounter_class = $encounterClass;
        $m->id_profesional_efector_servicio = $idPes;
        $m->vigente_desde = date('Y-m-d');
        $m->semanas = 4;

        return $m;
    }

    /**
     * @return list<array{inicio: string, fin: string}>
     */
    public static function expandIntervals(
        ProfesionalCoberturaPlantilla $plantilla,
        string $vigenteDesde,
        int $semanas
    ): array {
        $out = [];
        $startTs = strtotime($vigenteDesde . ' 00:00:00');
        if ($startTs === false) {
            return [];
        }
        $endTs = strtotime('+' . $semanas . ' weeks', $startTs);
        if ($endTs === false) {
            return [];
        }

        for ($ts = $startTs; $ts < $endTs; $ts = strtotime('+1 day', $ts)) {
            if ($ts === false) {
                break;
            }
            $n = (int) date('N', $ts); // 1=lun … 7=dom
            $col = ProfesionalCoberturaPlantilla::DAY_COLUMNS[$n] ?? null;
            if ($col === null) {
                continue;
            }
            $csv = trim((string) ($plantilla->$col ?? ''));
            if ($csv === '') {
                continue;
            }
            $fecha = date('Y-m-d', $ts);
            foreach (self::contiguousRangesFromHourCsv($csv) as $range) {
                $out[] = [
                    'inicio' => $fecha . ' ' . $range['inicio'],
                    'fin' => $fecha . ' ' . $range['fin'],
                ];
            }
        }

        return $out;
    }

    /**
     * Horas enteras "8,9,10,11" → bloques contiguos 08:00–12:00.
     *
     * @return list<array{inicio: string, fin: string}>
     */
    public static function contiguousRangesFromHourCsv(string $csv): array
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
        if ($hours === []) {
            return [];
        }
        $sorted = array_values($hours);
        sort($sorted);

        return self::buildRangesFromSortedHours($sorted);
    }

    /**
     * @param list<int> $sorted
     * @return list<array{inicio: string, fin: string}>
     */
    private static function buildRangesFromSortedHours(array $sorted): array
    {
        if ($sorted === []) {
            return [];
        }
        $ranges = [];
        $runStart = $sorted[0];
        $prev = $sorted[0];
        for ($i = 1; $i < count($sorted); $i++) {
            if ($sorted[$i] === $prev + 1) {
                $prev = $sorted[$i];
                continue;
            }
            $ranges[] = self::rangeFromHours($runStart, $prev);
            $runStart = $sorted[$i];
            $prev = $sorted[$i];
        }
        $ranges[] = self::rangeFromHours($runStart, $prev);

        return $ranges;
    }

    /**
     * @return array{inicio: string, fin: string}
     */
    private static function rangeFromHours(int $startHour, int $lastHour): array
    {
        $finHour = $lastHour + 1;
        if ($finHour >= 24) {
            return [
                'inicio' => sprintf('%02d:00:00', $startHour),
                'fin' => '23:59:59',
            ];
        }

        return [
            'inicio' => sprintf('%02d:00:00', $startHour),
            'fin' => sprintf('%02d:00:00', $finHour),
        ];
    }

    private static function normalizeHourCsv(string $raw): string
    {
        $hours = [];
        foreach (explode(',', $raw) as $part) {
            $part = trim($part);
            if ($part === '' || !ctype_digit($part)) {
                continue;
            }
            $h = (int) $part;
            if ($h >= 0 && $h <= 23) {
                $hours[$h] = $h;
            }
        }
        if ($hours === []) {
            return '';
        }
        $sorted = array_values($hours);
        sort($sorted);

        return implode(',', $sorted);
    }

    /**
     * Soft-delete de intervalos generados por plantilla en la ventana a regenerar.
     * Usa UPDATE masivo (evita fallos silenciosos de softDelete fila a fila).
     */
    private static function softDeleteGeneratedInWindow(
        int $idPersona,
        int $idEfector,
        string $encounterClass,
        ?int $idPes,
        string $desde,
        string $hasta,
        ?int $plantillaId = null
    ): void {
        $condition = [
            'and',
            [
                'id_persona' => $idPersona,
                'id_efector' => $idEfector,
                'encounter_class' => $encounterClass,
                'deleted_at' => null,
            ],
            ['>=', 'inicio', $desde . ' 00:00:00'],
            ['<', 'inicio', $hasta . ' 00:00:00'],
        ];
        if ($plantillaId !== null && $plantillaId > 0) {
            $condition[] = ['notas' => 'plantilla:' . $plantillaId];
        } else {
            // Prefijo estable de materialización; el `%` es comodín SQL.
            $condition[] = ['like', 'notas', 'plantilla:%', false];
        }
        if ($idPes !== null && $idPes > 0) {
            $condition[] = ['id_profesional_efector_servicio' => $idPes];
        }

        $now = date('Y-m-d H:i:s');
        $deletedBy = null;
        if (Yii::$app->has('user', true) && Yii::$app->user && !Yii::$app->user->isGuest) {
            $deletedBy = (int) Yii::$app->user->id;
        }

        ProfesionalCobertura::updateAll(
            [
                'deleted_at' => $now,
                'deleted_by' => $deletedBy,
                'updated_at' => $now,
            ],
            $condition
        );
    }
}
