<?php

namespace common\components\Domain\Clinical\CareCohort\Service;

use common\components\Platform\Agent\AgentRunRecorder;
use common\components\Platform\Agent\AutonomousAgentRuleEngine;
use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\components\Platform\Core\Service\Push\PushNotificationSender;
use common\components\Platform\Core\Service\Push\PushNotificationTypes;
use common\models\Clinical\CareFollowupTouchpointQueue;
use common\models\Clinical\Encounter;
use common\models\Person\Persona;
use common\models\ProfesionalEfectorServicio;
use Yii;

/**
 * Agente B01: evalúa respuestas de touchpoint y ejecuta rama (staff / educativo).
 */
final class CareFollowupBranchingAgent
{
    public const AGENT_ID = 'care-followup-branching';

    public const TRIGGER_TYPE = 'care_followup_touchpoint';

    /**
     * @param array<string, mixed> $answers
     */
    public function runAfterSubmit(CareFollowupTouchpointQueue $queue, array $answers): void
    {
        if (!(Yii::$app->params['autonomous_agent_care_followup_branching_enabled'] ?? true)) {
            return;
        }

        $rules = AutonomousAgentMetadata::rulesForAgent(self::AGENT_ID);
        if ($rules === []) {
            return;
        }

        $matched = AutonomousAgentRuleEngine::matchAll(
            $rules,
            $answers,
            (string) $queue->form_kind
        );
        if ($matched === []) {
            return;
        }

        $encounter = Encounter::findOne(['id' => (int) $queue->encounter_id, 'deleted_at' => null]);
        $context = $this->buildTemplateContext($queue, $answers, $encounter);

        foreach ($matched as $rule) {
            $action = (string) ($rule['action'] ?? '');
            $ruleId = (string) ($rule['id'] ?? '');

            switch ($action) {
                case 'notify_staff':
                    $this->notifyStaff($queue, $encounter, $rule, $context, $ruleId, $answers);
                    break;
                case 'educational_push':
                    $this->educationalPush($queue, $rule, $ruleId, $answers);
                    break;
                default:
                    Yii::warning(
                        'CareFollowupBranchingAgent: acción desconocida ' . $action . ' en regla ' . $ruleId,
                        'autonomous-agent'
                    );
            }
        }
    }

    /**
     * @param array<string, mixed> $answers
     * @return array<string, string>
     */
    private function buildTemplateContext(
        CareFollowupTouchpointQueue $queue,
        array $answers,
        ?Encounter $encounter
    ): array {
        $patientName = 'Paciente';
        if ($encounter !== null && $encounter->subject !== null) {
            $patientName = $encounter->subject->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N);
        }

        $ctx = [
            'touchpoint_title' => (string) $queue->title,
            'touchpoint_key' => (string) $queue->touchpoint_key,
            'patient_name' => $patientName,
        ];
        foreach ($answers as $key => $value) {
            if (is_scalar($value)) {
                $ctx[(string) $key] = (string) $value;
            }
        }

        return $ctx;
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, string> $context
     * @param array<string, mixed> $answers
     */
    private function notifyStaff(
        CareFollowupTouchpointQueue $queue,
        ?Encounter $encounter,
        array $rule,
        array $context,
        string $ruleId,
        array $answers
    ): void {
        $staff = is_array($rule['staff'] ?? null) ? $rule['staff'] : [];
        $title = (string) ($staff['title'] ?? 'Seguimiento post-consulta');
        $bodyTemplate = (string) ($staff['body_template'] ?? 'Revisá el seguimiento del paciente.');
        $body = $this->interpolate($bodyTemplate, $context);

        $pesId = $encounter !== null ? (int) ($encounter->id_profesional_efector_servicio ?? 0) : 0;
        $staffPersonaId = $this->personaIdFromPes($pesId);
        if ($staffPersonaId <= 0) {
            Yii::info(
                'CareFollowupBranchingAgent: sin PES para alerta staff encounter=' . (int) $queue->encounter_id,
                'autonomous-agent'
            );
        } else {
            (new PushNotificationSender())->sendToPersona(
                $staffPersonaId,
                [
                    'type' => PushNotificationTypes::CARE_FOLLOWUP_STAFF_ALERT,
                    'encounter_id' => (string) $queue->encounter_id,
                    'touchpoint_id' => (string) $queue->id,
                    'rule_id' => $ruleId,
                ],
                $title,
                $body,
                true
            );
        }

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_TYPE,
            'notify_staff',
            (int) $queue->id,
            (int) $queue->encounter_id,
            (int) $queue->subject_persona_id,
            $ruleId,
            $answers,
            ['staff_persona_id' => $staffPersonaId > 0 ? $staffPersonaId : null]
        );
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $answers
     */
    private function educationalPush(
        CareFollowupTouchpointQueue $queue,
        array $rule,
        string $ruleId,
        array $answers
    ): void {
        $patient = is_array($rule['patient'] ?? null) ? $rule['patient'] : [];
        $title = (string) ($patient['title'] ?? 'Seguimiento de tu salud');
        $body = (string) ($patient['body'] ?? '');

        if ($body === '') {
            return;
        }

        (new PushNotificationSender())->sendToPersona(
            (int) $queue->subject_persona_id,
            [
                'type' => PushNotificationTypes::CARE_FOLLOWUP_TOUCHPOINT,
                'encounter_id' => (string) $queue->encounter_id,
                'touchpoint_id' => (string) $queue->id,
                'rule_id' => $ruleId,
                'educational' => '1',
            ],
            $title,
            $body,
            false
        );

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_TYPE,
            'educational_push',
            (int) $queue->id,
            (int) $queue->encounter_id,
            (int) $queue->subject_persona_id,
            $ruleId,
            $answers
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
