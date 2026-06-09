<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $touchpoint_queue_id
 * @property int $encounter_id
 * @property int $subject_persona_id
 * @property string $touchpoint_key
 * @property string $answers_json
 * @property string $submitted_at
 * @property string $created_at
 */
class CareFollowupResponse extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%care_followup_response}}';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getAnswersArray(): ?array
    {
        $decoded = json_decode((string) $this->answers_json, true);

        return is_array($decoded) ? $decoded : null;
    }
}
