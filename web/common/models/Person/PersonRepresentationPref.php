<?php

namespace common\models\Person;

use yii\db\ActiveRecord;

/**
 * Preferencias del paciente sobre representación delegada.
 *
 * @property int $id_persona
 * @property bool $notify_on_representative_action
 * @property string $updated_at
 */
class PersonRepresentationPref extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%person_representation_pref}}';
    }

    public function rules(): array
    {
        return [
            [['id_persona', 'updated_at'], 'required'],
            [['id_persona'], 'integer'],
            [['notify_on_representative_action'], 'boolean'],
            [['updated_at'], 'safe'],
        ];
    }
}
