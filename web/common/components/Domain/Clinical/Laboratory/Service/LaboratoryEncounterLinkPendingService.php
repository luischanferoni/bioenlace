<?php

namespace common\components\Domain\Clinical\Laboratory\Service;

use common\models\Clinical\DiagnosticReport;
use common\models\Clinical\DiagnosticReportEncounterLinkPending;
use common\models\Clinical\Encounter;
use common\models\Person\Persona;

/**
 * Bandeja staff: informes de lab pendientes de vincular a encounter (agente E01).
 */
final class LaboratoryEncounterLinkPendingService
{
    /**
     * @return array<string, mixed>
     */
    public function listPending(int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));
        $rows = DiagnosticReportEncounterLinkPending::find()
            ->orderBy(['created_at' => SORT_ASC])
            ->limit($limit)
            ->all();

        $items = [];
        foreach ($rows as $row) {
            $item = $this->buildItem($row);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return [
            'items' => $items,
            'total' => count($items),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildItem(DiagnosticReportEncounterLinkPending $row): ?array
    {
        $report = $row->diagnosticReport;
        if ($report === null || $report->deleted_at !== null) {
            return null;
        }
        if ($report->encounter_id !== null && (int) $report->encounter_id > 0) {
            return null;
        }

        $persona = Persona::findOne((int) $report->subject_persona_id);
        $candidates = [];
        foreach ($row->candidatesList() as $cand) {
            if (!is_array($cand)) {
                continue;
            }
            $encId = (int) ($cand['encounter_id'] ?? 0);
            if ($encId <= 0) {
                continue;
            }
            $enc = Encounter::findOne(['id' => $encId, 'deleted_at' => null]);
            $candidates[] = [
                'encounter_id' => $encId,
                'score' => (int) ($cand['score'] ?? 0),
                'period_start' => $enc ? (string) ($enc->period_start ?? '') : (string) ($cand['period_start'] ?? ''),
            ];
        }

        return [
            'report_id' => (int) $report->id,
            'display' => (string) ($report->display ?? 'Informe de laboratorio'),
            'issued_at' => (string) ($report->issued_at ?? ''),
            'paciente' => [
                'id_persona' => (int) $report->subject_persona_id,
                'nombre_completo' => $persona ? $persona->getNombreCompleto() : 'Paciente',
            ],
            'candidates' => $candidates,
            'pending_since' => (string) $row->created_at,
        ];
    }
}
