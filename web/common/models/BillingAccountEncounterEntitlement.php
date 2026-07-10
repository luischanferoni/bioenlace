<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Entitlement por clase en una cuenta (pool compartido).
 *
 * @property int $id
 * @property int $id_billing_account
 * @property string $encounter_class
 * @property int|null $max_pes
 * @property int|null $pending_max_pes
 * @property string|null $pending_effective_on
 * @property int $dictado_incluido
 * @property int $videollamada_permitida
 * @property int $activo
 */
class BillingAccountEncounterEntitlement extends ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    public static function tableName()
    {
        return 'billing_account_encounter_entitlement';
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
            [['id_billing_account', 'encounter_class'], 'required'],
            [['id_billing_account', 'max_pes', 'pending_max_pes', 'dictado_incluido', 'videollamada_permitida', 'activo'], 'integer'],
            [['encounter_class'], 'string', 'max' => 10],
            [['pending_effective_on', 'created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['dictado_incluido', 'videollamada_permitida'], 'default', 'value' => 0],
            [['activo'], 'default', 'value' => 1],
        ];
    }

    public function attributeLabels()
    {
        return [
            'encounter_class' => 'Clase',
            'max_pes' => 'Máx. profesionales',
            'pending_max_pes' => 'Pending máx.',
            'pending_effective_on' => 'Pending desde',
            'dictado_incluido' => 'Dictado incluido',
            'videollamada_permitida' => 'Videollamada permitida',
            'activo' => 'Activo',
        ];
    }

    public function getAccount()
    {
        return $this->hasOne(BillingAccount::class, ['id' => 'id_billing_account']);
    }

    /**
     * @return list<self>
     */
    public static function findActivasPorAccount(int $idBillingAccount): array
    {
        if ($idBillingAccount <= 0) {
            return [];
        }

        return static::find()
            ->where([
                'id_billing_account' => $idBillingAccount,
                'activo' => 1,
                'deleted_at' => null,
            ])
            ->orderBy(['encounter_class' => SORT_ASC])
            ->all();
    }
}
