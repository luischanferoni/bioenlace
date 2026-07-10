<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Solicitud de alta comercial (ministerio asistido / log efector self-service).
 *
 * @property int $id
 * @property string $tipo
 * @property string $status
 * @property string $nombre_organizacion
 * @property string|null $sector
 * @property int|null $id_billing_account_ministerio
 * @property string $contacto_nombre
 * @property string $contacto_apellido
 * @property string $contacto_email
 * @property string|null $contacto_telefono
 * @property string|null $contacto_documento
 * @property string|null $notas
 * @property int|null $id_user
 * @property int|null $id_billing_account
 * @property int|null $id_efector
 * @property int|null $reviewed_by
 * @property string|null $reviewed_at
 */
class BillingSignupRequest extends ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    public const TIPO_MINISTERIO = 'MINISTERIO';

    public const TIPO_EFECTOR = 'EFECTOR';

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    public const SECTOR_PUBLICO = 'PUBLICO';

    public const SECTOR_PRIVADO = 'PRIVADO';

    public static function tableName()
    {
        return 'billing_signup_request';
    }

    /** @return list<string> */
    public static function tipoValues(): array
    {
        return [self::TIPO_MINISTERIO, self::TIPO_EFECTOR];
    }

    /** @return list<string> */
    public static function statusValues(): array
    {
        return [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED];
    }

    /** @return list<string> */
    public static function sectorValues(): array
    {
        return [self::SECTOR_PUBLICO, self::SECTOR_PRIVADO];
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
            [['tipo', 'status', 'nombre_organizacion', 'contacto_nombre', 'contacto_apellido', 'contacto_email'], 'required'],
            [['tipo'], 'in', 'range' => self::tipoValues()],
            [['status'], 'in', 'range' => self::statusValues()],
            [['sector'], 'in', 'range' => self::sectorValues()],
            [['id_billing_account_ministerio', 'id_user', 'id_billing_account', 'id_efector', 'reviewed_by'], 'integer'],
            [['nombre_organizacion', 'contacto_email'], 'string', 'max' => 255],
            [['contacto_nombre', 'contacto_apellido'], 'string', 'max' => 120],
            [['contacto_telefono'], 'string', 'max' => 40],
            [['contacto_documento'], 'string', 'max' => 20],
            [['contacto_email'], 'email'],
            [['notas'], 'string'],
            [['reviewed_at', 'created_at', 'updated_at', 'deleted_at'], 'safe'],
        ];
    }

    public function getMinisterioAccount()
    {
        return $this->hasOne(BillingAccount::class, ['id' => 'id_billing_account_ministerio']);
    }

    public function getAccount()
    {
        return $this->hasOne(BillingAccount::class, ['id' => 'id_billing_account']);
    }
}
