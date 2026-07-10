<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Cobro de licencia (pasarela real o simulada).
 *
 * @property int $id
 * @property int $id_billing_account
 * @property string $provider
 * @property string $status
 * @property float $amount_usd
 * @property string $currency
 * @property string|null $external_reference
 * @property string|null $card_last4
 * @property string|null $payload_json
 * @property string|null $paid_at
 */
class BillingPayment extends ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    public const PROVIDER_SIMULATED = 'SIMULATED';

    public const PROVIDER_MERCADOPAGO = 'MERCADOPAGO';

    public const PROVIDER_STRIPE = 'STRIPE';

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_REFUNDED = 'REFUNDED';

    public static function tableName()
    {
        return 'billing_payment';
    }

    /** @return list<string> */
    public static function providerValues(): array
    {
        return [self::PROVIDER_SIMULATED, self::PROVIDER_MERCADOPAGO, self::PROVIDER_STRIPE];
    }

    /** @return list<string> */
    public static function statusValues(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_REFUNDED,
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
            [['id_billing_account', 'provider', 'status', 'amount_usd'], 'required'],
            [['id_billing_account'], 'integer'],
            [['amount_usd'], 'number'],
            [['provider'], 'in', 'range' => self::providerValues()],
            [['status'], 'in', 'range' => self::statusValues()],
            [['currency'], 'string', 'max' => 3],
            [['external_reference'], 'string', 'max' => 64],
            [['card_last4'], 'string', 'max' => 4],
            [['payload_json'], 'string'],
            [['paid_at', 'created_at', 'updated_at', 'deleted_at'], 'safe'],
        ];
    }

    public function getAccount()
    {
        return $this->hasOne(BillingAccount::class, ['id' => 'id_billing_account']);
    }
}
