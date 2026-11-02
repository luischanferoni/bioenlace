<?php

namespace common\models\Organization;

use yii\db\ActiveRecord;

/**
 * Persisted monthly subscription checkout for an efector signup.
 *
 * @property int $id
 * @property string $checkout_key
 * @property int $id_user
 * @property int $id_persona
 * @property string $perfil
 * @property string $sector
 * @property string $status
 * @property int|null $id_billing_account
 * @property int|null $id_efector
 * @property string|null $reserved_sisa_code
 * @property int|null $id_billing_account_ministerio
 * @property string|null $provider_subscription_id
 * @property string|null $provider_payment_id
 * @property string|null $checkout_url
 * @property string $payer_email
 * @property string $plan_json
 * @property string $efector_json
 * @property float $amount_usd
 * @property float $fx_rate
 * @property string $fx_rate_effective_date
 * @property float $amount_net_ars
 * @property float $vat_rate
 * @property float $vat_ars
 * @property float $amount_total_ars
 * @property string|null $paid_through_at
 * @property string|null $provider_status
 * @property string|null $provider_subscription_status
 * @property string|null $last_provider_payload
 * @property string|null $created_at
 * @property string|null $updated_at
 */
final class BillingCheckoutAttempt extends ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    public const STATUS_CREATING = 'CREATING';
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_EXPIRED = 'EXPIRED';

    public static function tableName(): string
    {
        return 'billing_checkout_attempt';
    }

    public function behaviors(): array
    {
        return [
            'timestamp' => [
                'class' => 'yii\behaviors\TimestampBehavior',
                'value' => static fn() => date('Y-m-d H:i:s'),
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => 'updated_at',
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['checkout_key', 'id_user', 'id_persona', 'perfil', 'sector', 'status', 'payer_email', 'plan_json', 'efector_json'], 'required'],
            [['id_user', 'id_persona', 'id_billing_account', 'id_efector', 'id_billing_account_ministerio'], 'integer'],
            [['plan_json', 'efector_json', 'last_provider_payload'], 'string'],
            [['amount_usd', 'fx_rate', 'amount_net_ars', 'vat_rate', 'vat_ars', 'amount_total_ars'], 'number'],
            [['checkout_key'], 'string', 'max' => 64],
            [['perfil', 'status', 'provider_status', 'provider_subscription_status'], 'string', 'max' => 24],
            [['sector'], 'string', 'max' => 12],
            [['provider_subscription_id', 'provider_payment_id'], 'string', 'max' => 100],
            [['checkout_url'], 'string', 'max' => 2048],
            [['reserved_sisa_code'], 'string', 'max' => 15],
            [['paid_through_at'], 'safe'],
            [['payer_email'], 'email'],
            [['fx_rate_effective_date', 'created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['checkout_key'], 'unique'],
            [['provider_subscription_id'], 'unique'],
            [['provider_payment_id'], 'unique'],
            [['reserved_sisa_code'], 'unique'],
        ];
    }

    public function getBillingAccount()
    {
        return $this->hasOne(BillingAccount::class, ['id' => 'id_billing_account']);
    }
}
