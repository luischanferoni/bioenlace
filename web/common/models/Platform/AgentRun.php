<?php

namespace common\models\Platform;

use yii\db\ActiveRecord;

/**
 * Auditoría de un paso decisorio de agente autónomo — tabla `agent_run`.
 */
class AgentRun extends ActiveRecord
{
    public const EXECUTION_SHADOW = 'SHADOW';
    public const EXECUTION_LOW_IMPACT = 'LOW_IMPACT';
    public const EXECUTION_ENFORCE = 'ENFORCE';

    /** @return list<string> */
    public static function executionModeValues(): array
    {
        return [self::EXECUTION_SHADOW, self::EXECUTION_LOW_IMPACT, self::EXECUTION_ENFORCE];
    }

    public static function tableName(): string
    {
        return 'agent_run';
    }

    public function rules(): array
    {
        return [
            [['agent_id', 'trigger_type', 'outcome', 'created_at'], 'required'],
            [['trigger_id', 'encounter_id', 'subject_persona_id', 'profile_id'], 'integer'],
            [['facts_json', 'decision_json', 'evidence_json', 'action_json', 'result_json'], 'string'],
            [['agent_id', 'rule_id', 'policy_id', 'profile_contract_version', 'policy_version'], 'string', 'max' => 64],
            [['trigger_type', 'outcome'], 'string', 'max' => 48],
            [['policy_hash'], 'string', 'max' => 64],
            [['execution_mode'], 'in', 'range' => self::executionModeValues(), 'skipOnEmpty' => true],
        ];
    }
}
