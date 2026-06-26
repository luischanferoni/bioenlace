<?php

namespace common\components\Domain\Clinical\Laboratory\Service;

use common\components\Platform\Agent\AgentRunRecorder;
use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\components\Platform\Core\Service\Push\PushNotificationSender;
use common\components\Platform\Core\Service\Push\PushNotificationTypes;
use common\models\Clinical\DiagnosticReport;
use common\models\Clinical\Encounter;
use common\models\Clinical\Observation;
use common\models\Person\Persona;
use common\models\ProfesionalEfectorServicio;
use Yii;

/**
 * Agente B03: clasifica informe de laboratorio (LOINC + umbrales) y notifica paciente/staff.
 */
final class PostLabClassificationAgent
{
    public const AGENT_ID = 'post-lab-classification';

    public const TRIGGER_TYPE = 'laboratory_diagnostic_report';

    public function runAfterIngest(DiagnosticReport $report): void
    {
        if (!(Yii::$app->params['autonomous_agent_post_lab_enabled'] ?? true)) {
            return;
        }

        if (!$this->isFinalStatus((string) $report->status)) {
            return;
        }

        if ($this->alreadyProcessed((int) $report->id)) {
            return;
        }

        $config = AutonomousAgentMetadata::loadAgent(self::AGENT_ID);
        if ($config === null) {
            return;
        }

        $observations = $this->loadObservationFacts((int) $report->id);
        $classification = PostLabClassificationRuleEngine::classify($observations, $config);
        $severity = (string) $classification['severity'];
        $outcome = PostLabClassificationRuleEngine::outcomeConfig($config, $severity);
        if ($outcome === null) {
            return;
        }

        $context = $this->buildTemplateContext($report, $classification['triggering_observation']);

        $action = (string) ($outcome['action'] ?? 'notify_patient');
        switch ($action) {
            case 'notify_patient_and_staff':
                $this->notifyPatient($report, $outcome, $context, $severity, true);
                $this->notifyStaff($report, $outcome, $context, $severity, $classification);
                break;
            case 'notify_patient':
            default:
                $this->notifyPatient($report, $outcome, $context, $severity, false);
        }

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_TYPE,
            $severity,
            (int) $report->id,
            $report->encounter_id !== null ? (int) $report->encounter_id : null,
            (int) $report->subject_persona_id,
            $classification['matched_rules'][0]['rule_id'] ?? null,
            ['observations' => $observations],
            $classification
        );
    }

    private function isFinalStatus(string $status): bool
    {
        return in_array(strtolower($status), ['final', 'amended', 'corrected'], true);
    }

    private function alreadyProcessed(int $reportId): bool
    {
        return \common\models\Platform\AgentRun::find()
            ->where([
                'agent_id' => self::AGENT_ID,
                'trigger_type' => self::TRIGGER_TYPE,
                'trigger_id' => $reportId,
            ])
            ->exists();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadObservationFacts(int $reportId): array
    {
        $rows = Observation::find()
            ->where(['diagnostic_report_id' => $reportId])
            ->andWhere(['deleted_at' => null])
            ->all();

        $facts = [];
        foreach ($rows as $obs) {
            if (!$obs instanceof Observation) {
                continue;
            }
            $facts[] = [
                'loinc' => (string) $obs->code,
                'display' => (string) ($obs->value_string ?: $obs->code),
                'value' => $obs->value_quantity,
                'unit' => (string) ($obs->value_unit ?? ''),
                'interpretation' => (string) ($obs->interpretation_code ?? ''),
            ];
        }

        return $facts;
    }

    /**
     * @param array<string, mixed>|null $triggering
     * @return array<string, string>
     */
    private function buildTemplateContext(DiagnosticReport $report, ?array $triggering): array
    {
        $ctx = [
            'report_display' => (string) ($report->display ?? 'Informe de laboratorio'),
            'analyte_display' => '',
            'value' => '',
            'unit' => '',
        ];
        if (is_array($triggering)) {
            $ctx['analyte_display'] = (string) ($triggering['display'] ?? $triggering['loinc'] ?? '');
            $ctx['value'] = is_numeric($triggering['value'] ?? null) ? (string) $triggering['value'] : '';
            $ctx['unit'] = (string) ($triggering['unit'] ?? '');
        }

        return $ctx;
    }

    /**
     * @param array<string, mixed> $outcome
     * @param array<string, string> $context
     */
    private function notifyPatient(
        DiagnosticReport $report,
        array $outcome,
        array $context,
        string $severity,
        bool $critical
    ): void {
        $patient = is_array($outcome['patient'] ?? null) ? $outcome['patient'] : [];
        $title = (string) ($patient['title'] ?? 'Resultado de laboratorio');
        $body = (string) ($patient['body'] ?? '');
        if ($body === '') {
            return;
        }

        $type = $critical
            ? PushNotificationTypes::LAB_RESULT_CRITICAL_PATIENT
            : PushNotificationTypes::LAB_RESULT_AVAILABLE;

        (new PushNotificationSender())->sendToPersona(
            (int) $report->subject_persona_id,
            [
                'type' => $type,
                'diagnostic_report_id' => (string) $report->id,
                'severity' => $severity,
            ],
            $title,
            $body,
            $critical
        );
    }

    /**
     * @param array<string, mixed> $outcome
     * @param array<string, string> $context
     * @param array<string, mixed> $classification
     */
    private function notifyStaff(
        DiagnosticReport $report,
        array $outcome,
        array $context,
        string $severity,
        array $classification
    ): void {
        $staff = is_array($outcome['staff'] ?? null) ? $outcome['staff'] : [];
        $title = (string) ($staff['title'] ?? 'Laboratorio');
        $bodyTemplate = (string) ($staff['body_template'] ?? 'Revisá el informe de laboratorio del paciente.');
        $body = $this->interpolate($bodyTemplate, $context);

        $pesId = 0;
        if ($report->encounter_id) {
            $encounter = Encounter::findOne(['id' => (int) $report->encounter_id, 'deleted_at' => null]);
            if ($encounter !== null) {
                $pesId = (int) ($encounter->id_profesional_efector_servicio ?? 0);
            }
        }

        $staffPersonaId = $this->personaIdFromPes($pesId);
        if ($staffPersonaId <= 0) {
            Yii::info(
                'PostLabClassificationAgent: sin PES para alerta staff report=' . (int) $report->id,
                'autonomous-agent'
            );

            return;
        }

        $patientName = 'Paciente';
        if ($report->subjectPersona !== null) {
            $patientName = $report->subjectPersona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N);
        }

        (new PushNotificationSender())->sendToPersona(
            $staffPersonaId,
            [
                'type' => PushNotificationTypes::LAB_RESULT_CRITICAL_STAFF,
                'diagnostic_report_id' => (string) $report->id,
                'encounter_id' => $report->encounter_id !== null ? (string) $report->encounter_id : '',
                'severity' => $severity,
                'subject_persona_id' => (string) $report->subject_persona_id,
            ],
            $title,
            $patientName . ' — ' . $body,
            true
        );
    }

    /**
     * @param array<string, string> $context
     */
    private function interpolate(string $template, array $context): string
    {
        $out = $template;
        foreach ($context as $key => $value) {
            $out = str_replace('{' . $key . '}', $value, $out);
        }

        return $out;
    }

    private function personaIdFromPes(int $idPes): int
    {
        if ($idPes <= 0) {
            return 0;
        }
        $pes = ProfesionalEfectorServicio::findOne($idPes);

        return $pes ? (int) $pes->id_persona : 0;
    }
}
