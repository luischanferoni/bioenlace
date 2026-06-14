<?php

namespace common\models\rbac;

use yii\db\ActiveRecord;
use yii\rbac\Item;

/**
 * Rol RBAC (`auth_item.type` = rol).
 *
 * @property string $name
 * @property int $type
 * @property string|null $description
 */
class AuthRole extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'auth_item';
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public static function find()
    {
        return parent::find()->andWhere(['type' => Item::TYPE_ROLE]);
    }
}
