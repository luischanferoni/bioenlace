<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

/**
 * @property int $encounter_id
 * @property int $subject_persona_id
 * @property int $pack_id
 * @property string $answers_json
 * @property bool $delta_requested
 * @property string $submitted_at
 * @property string $created_at
 * @property string $updated_at
 */
class CareAssistanceResponse extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%care_assistance_response}}';
    }

    public static function primaryKey(): array
    {
        return ['encounter_id'];
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
