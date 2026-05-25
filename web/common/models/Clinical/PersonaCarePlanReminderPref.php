<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

/**
 * Preferencias de recordatorios care plan (sincronización multi-dispositivo).
 *
 * @property int $id
 * @property int $id_persona
 * @property int|null $care_plan_id
 * @property int|null $activity_id
 * @property bool $enabled
 * @property string|null $custom_times_json
 * @property string $updated_at
 */
class PersonaCarePlanReminderPref extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%persona_care_plan_reminder_pref}}';
    }

    public function rules(): array
    {
        return [
            [['id_persona', 'enabled', 'updated_at'], 'required'],
            [['id_persona', 'care_plan_id', 'activity_id'], 'integer'],
            [['enabled'], 'boolean'],
            [['custom_times_json'], 'string'],
            [['updated_at'], 'safe'],
        ];
    }
}
