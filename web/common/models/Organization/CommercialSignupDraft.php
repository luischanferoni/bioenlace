<?php

namespace common\models\Organization;

use yii\db\ActiveRecord;

/**
 * Mutable, versioned onboarding state for one Didit-verified identity.
 */
final class CommercialSignupDraft extends ActiveRecord
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_CHECKOUT_PENDING = 'CHECKOUT_PENDING';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_EXPIRED = 'EXPIRED';

    public static function tableName(): string
    {
        return 'commercial_signup_draft';
    }

    public function rules(): array
    {
        return [
            [['id_persona', 'status', 'current_step', 'schema_version', 'last_activity_at', 'expires_at'], 'required'],
            [['id_persona', 'id_billing_account_ministerio', 'current_step', 'schema_version'], 'integer'],
            [['efector_json', 'plan_json'], 'string'],
            [['status'], 'in', 'range' => [
                self::STATUS_DRAFT,
                self::STATUS_CHECKOUT_PENDING,
                self::STATUS_ACTIVE,
                self::STATUS_EXPIRED,
            ]],
            [['sector'], 'in', 'range' => ['PUBLICO', 'PRIVADO']],
            [['payer_email'], 'email'],
            [['payer_email'], 'string', 'max' => 255],
            [['contact_phone'], 'string', 'max' => 40],
            [['checkout_request_key'], 'string', 'length' => 48],
            [['checkout_key'], 'string', 'max' => 64],
            [['last_activity_at', 'expires_at', 'created_at', 'updated_at'], 'safe'],
            [['id_persona'], 'unique'],
        ];
    }
}
