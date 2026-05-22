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
