<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Membresía efector ↔ cuenta de licencia.
 *
 * @property int $id
 * @property int $id_billing_account
 * @property int $id_efector
 */
class BillingAccountEfector extends ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    public static function tableName()
    {
        return 'billing_account_efector';
    }

    public function behaviors()
    {
        return [
            'blames' => [
                'class' => 'yii\behaviors\AttributeBehavior',
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['created_by'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_by'],
                ],
                'value' => static function () {
                    return Yii::$app->has('user', true) && Yii::$app->user->id
                        ? (int) Yii::$app->user->id
                        : null;
                },
            ],
            'timestamp' => [
                'class' => 'yii\behaviors\TimestampBehavior',
                'value' => static function () {
                    return date('Y-m-d H:i:s');
                },
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => 'updated_at',
            ],
        ];
    }

    public function rules()
    {
        return [
            [['id_billing_account', 'id_efector'], 'required'],
            [['id_billing_account', 'id_efector'], 'integer'],
            [['created_at', 'updated_at', 'deleted_at'], 'safe'],
        ];
    }

    public function getAccount()
    {
        return $this->hasOne(BillingAccount::class, ['id' => 'id_billing_account']);
    }

    public function getEfector()
    {
        return $this->hasOne(Efector::class, ['id_efector' => 'id_efector']);
    }
}
