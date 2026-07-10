<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Cuenta comercial de licencia (Ministerio / Red / Efector).
 *
 * @property int $id
 * @property string $nombre
 * @property string $tipo
 * @property string|null $notas
 * @property int $activo
 */
class BillingAccount extends ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    public const TIPO_MINISTERIO = 'MINISTERIO';
    public const TIPO_RED = 'RED';
    public const TIPO_EFECTOR = 'EFECTOR';

    public static function tableName()
    {
        return 'billing_account';
    }

    public static function tipoOptions(): array
    {
        return [
            self::TIPO_MINISTERIO => 'Ministerio',
            self::TIPO_RED => 'Red',
            self::TIPO_EFECTOR => 'Efector',
        ];
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
            [['nombre', 'tipo'], 'required'],
            [['nombre'], 'string', 'max' => 255],
            [['tipo'], 'in', 'range' => array_keys(self::tipoOptions())],
            [['notas'], 'string'],
            [['activo'], 'integer'],
            [['activo'], 'default', 'value' => 1],
            [['created_at', 'updated_at', 'deleted_at'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'nombre' => 'Nombre',
            'tipo' => 'Tipo',
            'notas' => 'Notas',
            'activo' => 'Activo',
        ];
    }

    public function getMembers()
    {
        return $this->hasMany(BillingAccountEfector::class, ['id_billing_account' => 'id'])
            ->andOnCondition(['billing_account_efector.deleted_at' => null]);
    }

    public function getEntitlements()
    {
        return $this->hasMany(BillingAccountEncounterEntitlement::class, ['id_billing_account' => 'id'])
            ->andOnCondition(['billing_account_encounter_entitlement.deleted_at' => null]);
    }

    /**
     * @return list<self>
     */
    public static function findActivas(): array
    {
        return static::find()
            ->where(['activo' => 1, 'deleted_at' => null])
            ->orderBy(['nombre' => SORT_ASC])
            ->all();
    }
}
