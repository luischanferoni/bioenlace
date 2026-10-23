<?php

namespace common\components\Domain\Clinical\Encounter\Application\Service;

use common\components\Domain\Clinical\Encounter\Application\Presentation\EpisodioDateTimePresenter;
use common\models\Clinical\Encounter;
use common\models\Clinical\EncounterNursingCare;
use common\models\Clinical\Emergency\EmergencyTriage;
use common\models\Clinical\Emergency\EmergencyEpisode;
use common\models\Clinical\InpatientStay;

/**
 * Serie de signos vitales del episodio (triage + enfermería de encounters).
 * Independiente de los SV longitudinales de persona.
 */
final class EpisodioSignosVitalesService
{
    public const METRIC_TA_SYS = 'ta_sys';
    public const METRIC_TA_DIA = 'ta_dia';
    public const METRIC_FC = 'fc';
    public const METRIC_FR = 'fr';
    public const METRIC_SAT = 'sat_o2';
    public const METRIC_TEMP = 'temp';
    public const METRIC_GLUC = 'glucemia';
    public const METRIC_GLASGOW = 'glasgow';

    /** @var EpisodioTimelineService */
    private $encounters;

    public function __construct(?EpisodioTimelineService $encounters = null)
    {
        $this->encounters = $encounters ?? new EpisodioTimelineService();
    }

    /**
     * @return array{
     *   parent_type: string,
     *   episodio_id: int,
     *   metric_metadata: array<string, array{label: string, unit: string, intent: string, format: string}>,
     *   sections: list<array<string, mixed>>,
     *   series: list<array<string, mixed>>,
     *   ultimos: array<string, mixed>,
     *   total_points: int
     * }|null
     */
    public function buildForGuardia(int $personaId, int $guardiaId, int $idEfector): ?array
    {
        $guardia = EmergencyEpisode::findOne(['id' => $guardiaId, 'id_efector' => $idEfector]);
        if ($guardia === null || (int) $guardia->id_persona !== $personaId) {
            return null;
        }

        $points = [];
        $this->appendTriagePoints($points, $guardiaId);
        $encounters = $this->encounters->listEncountersForParent(
            Encounter::PARENT_GUARDIA,
            $guardiaId,
            $personaId
        );
        $this->appendEnfermeriaPoints($points, $encounters);

        return $this->finalize(Encounter::PARENT_GUARDIA, $guardiaId, $points);
    }

    /**
     * @return array{
     *   parent_type: string,
     *   episodio_id: int,
     *   metric_metadata: array<string, array{label: string, unit: string, intent: string}>,
     *   series: list<array<string, mixed>>,
     *   ultimos: array<string, mixed>,
     *   total_points: int
     * }|null
     */
    public function buildForInternacion(int $personaId, int $internacionId): ?array
    {
        $internacion = InpatientStay::findOne($internacionId);
        if (
            !$internacion instanceof InpatientStay
            || (int) $internacion->id_persona !== $personaId
        ) {
            return null;
        }

        $encounters = $this->encounters->listEncountersForParent(
            Encounter::PARENT_INTERNACION,
            $internacionId,
            $personaId
        );
        $points = [];
        $this->appendEnfermeriaPoints($points, $encounters);

        return $this->finalize(Encounter::PARENT_INTERNACION, $internacionId, $points);
    }

    /**
     * @param list<array{metric: string, at: string, value: float, source: string, ref_id?: string|null}> $points
     */
    private function appendTriagePoints(array &$points, int $guardiaId): void
    {
        $triage = EmergencyTriage::findOne(['guardia_id' => $guardiaId]);
        if ($triage === null) {
            return;
        }
        $vitals = $triage->getVitalsArray();
        if (!is_array($vitals) || $vitals === []) {
            return;
        }
        $at = trim((string) ($triage->triaged_at ?? ''));
        if ($at === '') {
            $at = date('Y-m-d H:i:s');
        }
        $ref = 'triage:' . (int) $triage->id;
        $map = [
            'bp_sys' => self::METRIC_TA_SYS,
            'bp_dia' => self::METRIC_TA_DIA,
            'hr' => self::METRIC_FC,
            'rr' => self::METRIC_FR,
            'temp_c' => self::METRIC_TEMP,
            'spo2' => self::METRIC_SAT,
            'sat_o2' => self::METRIC_SAT,
            'glucose' => self::METRIC_GLUC,
            'glucemia' => self::METRIC_GLUC,
            'glasgow' => self::METRIC_GLASGOW,
        ];
        foreach ($map as $key => $metric) {
            if (!array_key_exists($key, $vitals)) {
                continue;
            }
            $value = $this->toFloat($vitals[$key]);
            if ($value === null) {
                continue;
            }
            $points[] = [
                'metric' => $metric,
                'at' => $at,
                'value' => $value,
                'source' => 'triage',
                'ref_id' => $ref,
            ];
        }
    }

    /**
     * @param list<array{metric: string, at: string, value: float, source: string, ref_id?: string|null}> $points
     * @param Encounter[] $encounters
     */
    private function appendEnfermeriaPoints(array &$points, array $encounters): void
    {
        if ($encounters === []) {
            return;
        }
        $ids = [];
        foreach ($encounters as $enc) {
            $ids[] = (int) $enc->id;
        }
        $rows = EncounterNursingCare::find()
            ->where(['encounter_id' => $ids])
            ->orderBy(['fecha_creacion' => SORT_ASC, 'id' => SORT_ASC])
            ->limit(80)
            ->all();

        foreach ($rows as $row) {
            if (!$row instanceof EncounterNursingCare) {
                continue;
            }
            $datos = $row->datos;
            if (is_string($datos)) {
                $decoded = json_decode($datos, true);
                $datos = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($datos) || $datos === []) {
                continue;
            }
            $at = $this->atencionTimestamp($row);
            $ref = 'enfermeria:' . (int) $row->id;
            foreach ($this->extractFromDatos($datos) as $metric => $value) {
                $points[] = [
                    'metric' => $metric,
                    'at' => $at,
                    'value' => $value,
                    'source' => 'enfermeria',
                    'ref_id' => $ref,
                ];
            }
        }
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, float>
     */
    private function extractFromDatos(array $datos): array
    {
        $out = [];

        $ta = $datos['TensionArterial1'] ?? $datos['TensionArterial2'] ?? null;
        if (is_array($ta)) {
            $sys = $this->toFloat($ta['271649006'] ?? $ta['sistolica'] ?? null);
            $dia = $this->toFloat($ta['271650006'] ?? $ta['diastolica'] ?? null);
            if ($sys !== null) {
                $out[self::METRIC_TA_SYS] = $sys;
            }
            if ($dia !== null) {
                $out[self::METRIC_TA_DIA] = $dia;
            }
        }
        $sysFlat = $this->toFloat($datos['sistolica'] ?? $datos['271649006'] ?? null);
        $diaFlat = $this->toFloat($datos['diastolica'] ?? $datos['271650006'] ?? null);
        if ($sysFlat !== null && !isset($out[self::METRIC_TA_SYS])) {
            $out[self::METRIC_TA_SYS] = $sysFlat;
        }
        if ($diaFlat !== null && !isset($out[self::METRIC_TA_DIA])) {
            $out[self::METRIC_TA_DIA] = $diaFlat;
        }

        $fc = $this->toFloat($datos['364075005'] ?? $datos['fc'] ?? $datos['frecuencia_cardiaca'] ?? null);
        if ($fc !== null) {
            $out[self::METRIC_FC] = $fc;
        }
        $fr = $this->toFloat($datos['86290005'] ?? $datos['fr'] ?? $datos['frecuencia_respiratoria'] ?? null);
        if ($fr !== null) {
            $out[self::METRIC_FR] = $fr;
        }
        $sat = $this->toFloat($datos['103228002'] ?? $datos['sat_o2'] ?? $datos['saturacion'] ?? null);
        if ($sat !== null) {
            $out[self::METRIC_SAT] = $sat;
        }
        $temp = $this->toFloat($datos['temperatura'] ?? $datos['703421000'] ?? $datos['temp'] ?? null);
        if ($temp !== null) {
            $out[self::METRIC_TEMP] = $temp;
        }
        $gluc = $this->toFloat($datos['glucemia_capilar'] ?? $datos['434912009'] ?? $datos['glucemia'] ?? null);
        if ($gluc !== null) {
            $out[self::METRIC_GLUC] = $gluc;
        }
        $glas = $this->toFloat(
            $datos['glasgow'] ?? $datos['escala_glasgow'] ?? $datos['926013008'] ?? null
        );
        if ($glas !== null) {
            $out[self::METRIC_GLASGOW] = $glas;
        }

        return $out;
    }

    private function atencionTimestamp(EncounterNursingCare $row): string
    {
        $fecha = trim((string) ($row->fecha_creacion ?? ''));
        if ($fecha === '') {
            return date('Y-m-d H:i:s');
        }
        if (strlen($fecha) <= 10) {
            $hora = trim((string) ($row->hora_creacion ?? ''));
            if ($hora !== '') {
                return $fecha . ' ' . substr($hora, 0, 8);
            }
        }

        return $fecha;
    }

    /**
     * @param list<array{metric: string, at: string, value: float, source: string, ref_id?: string|null}> $points
     * @return array{
     *   parent_type: string,
     *   episodio_id: int,
     *   metric_metadata: array<string, array{label: string, unit: string, intent: string}>,
     *   series: list<array<string, mixed>>,
     *   ultimos: array<string, mixed>,
     *   total_points: int
     * }
     */
    private function finalize(string $parentType, int $episodioId, array $points): array
    {
        usort($points, static function (array $a, array $b): int {
            return strcmp((string) $a['at'], (string) $b['at']);
        });

        $byMetric = [];
        foreach ($points as $p) {
            $m = (string) $p['metric'];
            if (!isset($byMetric[$m])) {
                $byMetric[$m] = [];
            }
            $byMetric[$m][] = [
                'at' => $this->formatAtDisplay((string) $p['at']),
                'value' => (float) $p['value'],
                'source' => (string) $p['source'],
                'ref_id' => $p['ref_id'] ?? null,
            ];
        }

        $meta = $this->metricMeta();
        $series = [];
        $ultimos = [];
        foreach ($meta as $metric => $info) {
            $pts = $byMetric[$metric] ?? [];
            if ($pts === []) {
                continue;
            }
            $series[] = [
                'metric' => $metric,
                'label' => $info['label'],
                'unit' => $info['unit'],
                'intent' => $info['intent'],
                'points' => $pts,
            ];
            $last = $pts[count($pts) - 1];
            $ultimos[$metric] = [
                'value' => $last['value'],
                'at' => $last['at'],
                'unit' => $info['unit'],
                'label' => $info['label'],
                'intent' => $info['intent'],
                'source' => $last['source'],
            ];
        }

        return [
            'parent_type' => $parentType,
            'episodio_id' => $episodioId,
            'metric_metadata' => $meta,
            'sections' => [[
                'key' => 'vital_signs',
                'label' => 'Signos vitales',
                'order' => 1,
                'fields' => $this->vitalSignFields($meta, $ultimos),
            ]],
            'series' => $series,
            'ultimos' => $ultimos,
            'total_points' => count($points),
        ];
    }

    /**
     * @param array<string, array{label: string, unit: string, intent: string, format: string}> $metadata
     * @param array<string, array<string, mixed>> $ultimos
     * @return list<array{
     *   key: string,
     *   label: string,
     *   value: float|null,
     *   unit: string,
     *   format: string,
     *   order: int,
     *   intent: string
     * }>
     */
    private function vitalSignFields(array $metadata, array $ultimos): array
    {
        $fields = [];
        foreach ($metadata as $metric => $info) {
            $fields[] = [
                'key' => $metric,
                'label' => $info['label'],
                'value' => isset($ultimos[$metric]) ? (float) $ultimos[$metric]['value'] : null,
                'unit' => $info['unit'],
                'format' => $info['format'],
                'order' => count($fields) + 1,
                'intent' => $info['intent'],
            ];
        }

        return $fields;
    }

    /**
     * Fecha legible es-AR: dd/MM/yyyy HH:mm (sin segundos).
     */
    private function formatAtDisplay(string $at): string
    {
        return EpisodioDateTimePresenter::display($at);
    }

    /**
     * @return array<string, array{label: string, unit: string, intent: string, format: string}>
     */
    private function metricMeta(): array
    {
        return [
            self::METRIC_TA_SYS => ['label' => 'TA sistólica', 'unit' => 'mmHg', 'intent' => 'danger', 'format' => 'integer'],
            self::METRIC_TA_DIA => ['label' => 'TA diastólica', 'unit' => 'mmHg', 'intent' => 'warning', 'format' => 'integer'],
            self::METRIC_FC => ['label' => 'Frecuencia cardíaca', 'unit' => 'lpm', 'intent' => 'primary', 'format' => 'integer'],
            self::METRIC_FR => ['label' => 'Frecuencia respiratoria', 'unit' => 'rpm', 'intent' => 'info', 'format' => 'integer'],
            self::METRIC_SAT => ['label' => 'Saturación O₂', 'unit' => '%', 'intent' => 'info', 'format' => 'integer'],
            self::METRIC_TEMP => ['label' => 'Temperatura', 'unit' => '°C', 'intent' => 'warning', 'format' => 'decimal'],
            self::METRIC_GLUC => ['label' => 'Glucemia capilar', 'unit' => 'mg/dL', 'intent' => 'success', 'format' => 'decimal'],
            self::METRIC_GLASGOW => ['label' => 'Glasgow', 'unit' => 'pts', 'intent' => 'secondary', 'format' => 'integer'],
        ];
    }

    /**
     * @param mixed $v
     */
    private function toFloat($v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return (float) $v;
        }

        return null;
    }
}
