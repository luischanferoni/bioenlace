<?php

namespace common\components\Domain\Clinical\Laboratory\Service;

use common\components\Platform\Agent\AgentRunRecorder;
use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\models\Clinical\DiagnosticReport;
use common\models\Clinical\DiagnosticReportEncounterLinkPending;
use common\models\Clinical\Encounter;
use common\models\Clinical\Observation;
use Yii;

/**
 * Agente E01 v1: vincula informe de laboratorio al encounter más probable.
 */
final class LaboratoryEncounterLinkAgent
{
    public const AGENT_ID = LaboratoryEncounterLinkScoringService::AGENT_ID;

    public const TRIGGER_TYPE = 'laboratory_diagnostic_report_ingest';

    private LaboratoryEncounterLinkScoringService $scoring;

    public function __construct(?LaboratoryEncounterLinkScoringService $scoring = null)
    {
        $this->scoring = $scoring ?? new LaboratoryEncounterLinkScoringService();
    }

    public function isEnabled(): bool
    {
        return (bool) (Yii::$app->params['autonomous_agent_lab_encounter_link_enabled'] ?? true);
    }

    /**
     * @param array<string, mixed> $fhirReport
     */
    public function resolveEncounterIdForIngest(int $subjectPersonaId, array $fhirReport, array $reportMeta): ?int
    {
        if (!$this->isEnabled()) {
            return (new LaboratoryEncounterLinkService())->resolveEncounterIdLegacy($subjectPersonaId, $fhirReport);
        }

        $config = AutonomousAgentMetadata::loadAgent(self::AGENT_ID);
        if ($config === null) {
            return (new LaboratoryEncounterLinkService())->resolveEncounterIdLegacy($subjectPersonaId, $fhirReport);
        }

        $result = $this->scoring->resolve($subjectPersonaId, $fhirReport, $reportMeta, $config);
        $outcome = (string) ($result['outcome'] ?? 'orphan');
        $encounterId = isset($result['encounter_id']) ? (int) $result['encounter_id'] : null;

        if ($outcome === 'pending_staff') {
            $this->persistPending($reportMeta, $result['candidates']);
        } else {
            $this->clearPending((int) ($reportMeta['diagnostic_report_id'] ?? 0));
        }

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_TYPE,
            $outcome,
            (int) ($reportMeta['diagnostic_report_id'] ?? 0) ?: null,
            $encounterId,
            $subjectPersonaId,
            null,
            [
                'report_code' => $reportMeta['code'] ?? null,
                'issued_at' => $reportMeta['issued_at'] ?? null,
            ],
            [
                'encounter_id' => $encounterId,
                'candidates' => $result['candidates'] ?? [],
            ]
        );

        return $encounterId > 0 ? $encounterId : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function confirmLinkAsStaff(int $reportId, int $encounterId, int $staffPersonaId): array
    {
        $report = DiagnosticReport::findOne(['id' => $reportId, 'deleted_at' => null]);
        if ($report === null) {
            throw new \InvalidArgumentException('Informe no encontrado.');
        }

        $encounter = Encounter::findOne([
            'id' => $encounterId,
            'subject_persona_id' => (int) $report->subject_persona_id,
            'deleted_at' => null,
        ]);
        if ($encounter === null) {
            throw new \InvalidArgumentException('Encounter inválido para este paciente.');
        }

        $report->encounter_id = $encounterId;
        if (!$report->save(false, ['encounter_id', 'updated_at'])) {
            throw new \RuntimeException('No se pudo vincular el informe.');
        }

        Observation::updateAll(
            ['encounter_id' => $encounterId],
            ['diagnostic_report_id' => $reportId, 'deleted_at' => null]
        );

        $this->clearPending($reportId);

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_TYPE,
            'staff_confirmed',
            $reportId,
            $encounterId,
            (int) $report->subject_persona_id,
            null,
            ['staff_persona_id' => $staffPersonaId],
            ['encounter_id' => $encounterId]
        );

        return [
            'report_id' => $reportId,
            'encounter_id' => $encounterId,
        ];
    }

    /**
     * @param list<array<string, mixed>> $candidates
     * @param array<string, mixed> $reportMeta
     */
    private function persistPending(array $reportMeta, array $candidates): void
    {
        $reportId = (int) ($reportMeta['diagnostic_report_id'] ?? 0);
        if ($reportId <= 0) {
            return;
        }

        $row = DiagnosticReportEncounterLinkPending::findOne(['diagnostic_report_id' => $reportId])
            ?? new DiagnosticReportEncounterLinkPending();
        $row->diagnostic_report_id = $reportId;
        $row->candidates_json = json_encode($candidates, JSON_UNESCAPED_UNICODE);
        $row->created_at = date('Y-m-d H:i:s');
        $row->save(false);
    }

    private function clearPending(int $reportId): void
    {
        if ($reportId <= 0) {
            return;
        }

        DiagnosticReportEncounterLinkPending::deleteAll(['diagnostic_report_id' => $reportId]);
    }
}
