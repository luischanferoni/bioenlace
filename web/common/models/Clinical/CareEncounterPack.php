<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

/**
 * @property int $encounter_id
 * @property int $subject_persona_id
 * @property string $cohort_key
 * @property string|null $cohort_profile_json
 * @property int|null $assistance_pack_id
 * @property int|null $followup_pack_id
 * @property int|null $education_pack_id
 * @property string $created_at
 * @property string $updated_at
 */
class CareEncounterPack extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%care_encounter_pack}}';
    }

    public static function primaryKey(): array
    {
        return ['encounter_id'];
    }
}
