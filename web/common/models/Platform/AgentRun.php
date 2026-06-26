<?php

namespace common\models\Platform;

use yii\db\ActiveRecord;

/**
 * Auditoría de un paso decisorio de agente autónomo — tabla `agent_run`.
 */
class AgentRun extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'agent_run';
    }

    public function rules(): array
    {
        return [
            [['agent_id', 'trigger_type', 'outcome', 'created_at'], 'required'],
            [['trigger_id', 'encounter_id', 'subject_persona_id'], 'integer'],
            [['facts_json', 'decision_json'], 'string'],
            [['agent_id', 'rule_id'], 'string', 'max' => 64],
            [['trigger_type', 'outcome'], 'string', 'max' => 48],
        ];
    }
}
