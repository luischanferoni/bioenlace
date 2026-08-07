<?php

namespace common\components\Domain\Clinical\Service;

use common\components\Domain\Clinical\Presentation\EpisodioDateTimeFormatter;
use common\models\ConsultaAtencionesEnfermeria;
use common\models\Emergency\GuardiaTriage;
use common\models\Guardia;
use common\models\SegNivelInternacion;

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
     *   series: list<array<string, mixed>>,
     *   ultimos: array<string, mixed>,
     *   total_points: int
     * }|null
     */
    public function buildForGuardia(int $personaId, int $guardiaId, int $idEfector): ?array
    {
        $guardia = Guardia::findOne(['id' => $guardiaId, 'id_efector' => $idEfector]);
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
     *   series: list<array<string, mixed>>,
     *   ultimos: array<string, mixed>,
     *   total_points: int
     * }|null
     */
    public function buildForInternacion(int $personaId, int $internacionId): ?array
    {
        $internacion = SegNivelInternacion::findOne($internacionId);
        if (
            !$internacion instanceof SegNivelInternacion
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
        $triage = GuardiaTriage::findOne(['guardia_id' => $guardiaId]);
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
        $rows = ConsultaAtencionesEnfermeria::find()
            ->where(['encounter_id' => $ids])
            ->orderBy(['fecha_creacion' => SORT_ASC, 'id' => SORT_ASC])
            ->limit(80)
            ->all();

        foreach ($rows as $row) {
            if (!$row instanceof ConsultaAtencionesEnfermeria) {
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

    private function atencionTimestamp(ConsultaAtencionesEnfermeria $row): string
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
                'points' => $pts,
            ];
            $last = $pts[count($pts) - 1];
            $ultimos[$metric] = [
                'value' => $last['value'],
                'at' => $last['at'],
                'unit' => $info['unit'],
                'label' => $info['label'],
                'source' => $last['source'],
            ];
        }

        // Compat visual TA agrupada.
        if (isset($ultimos[self::METRIC_TA_SYS]) || isset($ultimos[self::METRIC_TA_DIA])) {
            $ultimos['ta'] = [
                'sistolica' => $ultimos[self::METRIC_TA_SYS]['value'] ?? null,
                'diastolica' => $ultimos[self::METRIC_TA_DIA]['value'] ?? null,
                'at' => $ultimos[self::METRIC_TA_SYS]['at']
                    ?? $ultimos[self::METRIC_TA_DIA]['at']
                    ?? null,
            ];
        }

        return [
            'parent_type' => $parentType,
            'episodio_id' => $episodioId,
            'series' => $series,
            'ultimos' => $ultimos,
            'total_points' => count($points),
        ];
    }

    /**
     * Fecha legible es-AR: dd/MM/yyyy HH:mm (sin segundos).
     */
    private function formatAtDisplay(string $at): string
    {
        return EpisodioDateTimeFormatter::display($at);
    }

    /**
     * @return array<string, array{label: string, unit: string}>
     */
    private function metricMeta(): array
    {
        return [
            self::METRIC_TA_SYS => ['label' => 'TA sistólica', 'unit' => 'mmHg'],
            self::METRIC_TA_DIA => ['label' => 'TA diastólica', 'unit' => 'mmHg'],
            self::METRIC_FC => ['label' => 'Frecuencia cardíaca', 'unit' => 'lpm'],
            self::METRIC_FR => ['label' => 'Frecuencia respiratoria', 'unit' => 'rpm'],
            self::METRIC_SAT => ['label' => 'Saturación O₂', 'unit' => '%'],
            self::METRIC_TEMP => ['label' => 'Temperatura', 'unit' => '°C'],
            self::METRIC_GLUC => ['label' => 'Glucemia capilar', 'unit' => 'mg/dL'],
            self::METRIC_GLASGOW => ['label' => 'Glasgow', 'unit' => 'pts'],
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
