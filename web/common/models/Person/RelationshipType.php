<?php

namespace common\models\Person;

use yii\db\ActiveRecord;

/**
 * Catálogo de parentesco / vínculo para representación operativa.
 *
 * @property int $id
 * @property string $code
 * @property string $label
 * @property string|null $hl7_code
 * @property string $regime_allowed A|B|both
 * @property bool $requires_legal_document
 * @property int $sort_order
 * @property bool $active
 */
class RelationshipType extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%relationship_type}}';
    }

    public function rules(): array
    {
        return [
            [['code', 'label', 'regime_allowed'], 'required'],
            [['requires_legal_document', 'active'], 'boolean'],
            [['sort_order'], 'integer'],
            [['code'], 'string', 'max' => 32],
            [['label'], 'string', 'max' => 128],
            [['hl7_code'], 'string', 'max' => 64],
            [['regime_allowed'], 'string', 'max' => 16],
            [['code'], 'unique'],
        ];
    }

    public static function findByCode(string $code): ?self
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        return static::findOne(['code' => $code, 'active' => true]);
    }
}
