<?php

namespace common\components\Platform\Agent;

use common\models\Platform\AgentRun;
use Yii;

/**
 * Persiste auditoría de un paso decisorio de agente autónomo.
 */
final class AgentRunRecorder
{
    /**
     * @param array<string, mixed> $facts
     * @param array<string, mixed>|null $decision
     */
    public static function record(
        string $agentId,
        string $triggerType,
        string $outcome,
        ?int $triggerId = null,
        ?int $encounterId = null,
        ?int $subjectPersonaId = null,
        ?string $ruleId = null,
        array $facts = [],
        ?array $decision = null,
        array $auditContext = []
    ): ?AgentRun {
        if (!(Yii::$app->params['autonomous_agent_audit_enabled'] ?? true)) {
            return null;
        }

        $row = new AgentRun();
        $row->agent_id = $agentId;
        $row->trigger_type = $triggerType;
        $row->trigger_id = $triggerId;
        $row->encounter_id = $encounterId;
        $row->subject_persona_id = $subjectPersonaId;
        $row->rule_id = $ruleId;
        $row->outcome = $outcome;
        $row->facts_json = $facts !== [] ? json_encode($facts, JSON_UNESCAPED_UNICODE) : null;
        $row->decision_json = $decision !== null ? json_encode($decision, JSON_UNESCAPED_UNICODE) : null;
        foreach ([
            'profile_id',
            'profile_contract_version',
            'policy_id',
            'policy_version',
            'policy_hash',
            'execution_mode',
        ] as $attribute) {
            if ($row->hasAttribute($attribute) && array_key_exists($attribute, $auditContext)) {
                $row->{$attribute} = $auditContext[$attribute];
            }
        }
        foreach (['evidence_json', 'action_json', 'result_json'] as $attribute) {
            $source = substr($attribute, 0, -5);
            if ($row->hasAttribute($attribute) && array_key_exists($source, $auditContext)) {
                $row->{$attribute} = json_encode($auditContext[$source], JSON_UNESCAPED_UNICODE);
            }
        }
        $row->created_at = date('Y-m-d H:i:s');

        if (!$row->save(false)) {
            Yii::warning(
                'AgentRunRecorder: no se pudo persistir agent_run: ' . json_encode($row->errors),
                'autonomous-agent'
            );

            return null;
        }

        return $row;
    }
}
