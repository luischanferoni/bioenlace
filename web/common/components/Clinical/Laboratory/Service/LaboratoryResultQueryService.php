<?php

namespace common\components\Clinical\Laboratory\Service;

use common\components\Clinical\Dto\DiagnosticReportDto;
use common\models\Clinical\DiagnosticReport;
use common\models\Clinical\Observation;

final class LaboratoryResultQueryService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForPersona(int $idPersona, int $limit = 50): array
    {
        $reports = DiagnosticReport::find()
            ->andWhere(['subject_persona_id' => $idPersona, 'deleted_at' => null])
            ->orderBy(['issued_at' => SORT_DESC, 'id' => SORT_DESC])
            ->limit($limit)
            ->all();

        return array_map(fn (DiagnosticReport $r) => $this->serializeReport($r), $reports);
    }

    /**
     * Informe del paciente o null si no existe / no pertenece.
     *
     * @return array<string, mixed>|null
     */
    public function getReportForPersona(int $idPersona, int $reportId): ?array
    {
        if ($reportId <= 0) {
            return null;
        }
        $report = DiagnosticReport::findOne([
            'id' => $reportId,
            'subject_persona_id' => $idPersona,
            'deleted_at' => null,
        ]);
        if ($report === null) {
            return null;
        }

        return $this->serializeReport($report);
    }

    /**
     * Texto multilínea para UI readonly (detalle de analitos).
     *
     * @param array<string, mixed> $serialized
     */
    public function formatAnalitosText(array $serialized): string
    {
        $lines = [];
        foreach ($serialized['observations'] ?? [] as $obs) {
            if (!is_array($obs)) {
                continue;
            }
            $label = (string) ($obs['display'] ?? $obs['code'] ?? 'Analito');
            $val = trim((string) ($obs['valueQuantity'] ?? $obs['display'] ?? '—'));
            $unit = trim((string) ($obs['valueUnit'] ?? ''));
            $piece = $val;
            if ($unit !== '') {
                $piece .= ' ' . $unit;
            }
            $lines[] = $label . ': ' . $piece;
        }

        return $lines === [] ? 'Sin analitos en este informe.' : implode("\n", $lines);
    }

    /**
     * Texto plano para bloque ui_json `message` (saltos de línea y viñetas con asterisco).
     *
     * @param array<string, mixed> $serialized
     */
    public function formatReportDetailMessage(array $serialized): string
    {
        $lines = [];
        $title = trim((string) ($serialized['display'] ?? 'Informe de laboratorio'));
        $lines[] = $title !== '' ? $title : 'Informe de laboratorio';

        $fecha = trim((string) ($serialized['issuedAt'] ?? ''));
        if ($fecha !== '') {
            $lines[] = '';
            $lines[] = 'Fecha: ' . $fecha;
        }

        $lines[] = '';
        $lines[] = 'Analitos:';
        $hasObs = false;
        foreach ($serialized['observations'] ?? [] as $obs) {
            if (!is_array($obs)) {
                continue;
            }
            $hasObs = true;
            $label = trim((string) ($obs['display'] ?? $obs['code'] ?? 'Analito'));
            $val = trim((string) ($obs['valueQuantity'] ?? $obs['display'] ?? '—'));
            $unit = trim((string) ($obs['valueUnit'] ?? ''));
            $piece = $val;
            if ($unit !== '') {
                $piece .= ' ' . $unit;
            }
            $lines[] = '* ' . ($label !== '' ? $label : 'Analito') . ': ' . $piece;
        }
        if (!$hasObs) {
            $lines[] = '* Sin analitos en este informe.';
        }

        $conclusion = trim((string) ($serialized['conclusion'] ?? ''));
        if ($conclusion !== '') {
            $lines[] = '';
            $lines[] = 'Conclusión:';
            $lines[] = $conclusion;
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForEncounter(int $encounterId): array
    {
        $reports = DiagnosticReport::find()
            ->andWhere(['encounter_id' => $encounterId, 'deleted_at' => null])
            ->orderBy(['issued_at' => SORT_DESC])
            ->all();

        return array_map(fn (DiagnosticReport $r) => $this->serializeReport($r), $reports);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReport(DiagnosticReport $report): array
    {
        $dto = DiagnosticReportDto::fromModel($report);
        $observations = Observation::find()
            ->andWhere(['diagnostic_report_id' => $report->id, 'deleted_at' => null])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $data = $dto->toArray();
        $data['observations'] = array_map(static function (Observation $o) {
            return [
                'id' => (int) $o->id,
                'code' => $o->code,
                'codeSystem' => $o->code_system,
                'display' => $o->value_string,
                'valueQuantity' => $o->value_quantity,
                'valueUnit' => $o->value_unit,
                'effectiveDatetime' => $o->effective_datetime,
            ];
        }, $observations);

        return $data;
    }
}
