<?php

namespace common\models\Platform;

use yii\db\ActiveRecord;

/**
 * Etiqueta y orden de un grupo de atajos (prefijo de intent_id).
 *
 * @property string $group_id
 * @property string $label
 * @property int $sort_order
 * @property string|null $updated_at
 */
class AssistantShortcutGroup extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%assistant_shortcut_group}}';
    }

    public function rules(): array
    {
        return [
            [['group_id', 'label'], 'required'],
            [['sort_order'], 'integer'],
            [['updated_at'], 'safe'],
            [['group_id'], 'string', 'max' => 64],
            [['group_id'], 'match', 'pattern' => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            [['label'], 'string', 'max' => 255],
            [['group_id'], 'unique'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'group_id' => 'Prefijo (familia intent)',
            'label' => 'Título visible',
            'sort_order' => 'Orden',
            'updated_at' => 'Actualizado',
        ];
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $this->updated_at = date('Y-m-d H:i:s');

        return true;
    }
}
