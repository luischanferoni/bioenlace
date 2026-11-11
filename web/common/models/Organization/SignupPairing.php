<?php

namespace common\models\Organization;

use yii\db\ActiveRecord;

/**
 * Vínculo de un solo uso entre el navegador que inicia el alta web y el teléfono
 * que verifica la identidad (QR + código de emparejamiento).
 *
 * Los secretos (token y código) se guardan hasheados; el `id` no es secreto.
 */
final class SignupPairing extends ActiveRecord
{
    public const STATE_PENDING = 'PENDING';
    public const STATE_CLAIMED = 'CLAIMED';
    public const STATE_AUTHORIZED = 'AUTHORIZED';
    public const STATE_VERIFICATION_STARTED = 'VERIFICATION_STARTED';
    public const STATE_VERIFIED = 'VERIFIED';
    public const STATE_ACCOUNT_CREATED = 'ACCOUNT_CREATED';
    public const STATE_CANCELLED = 'CANCELLED';
    public const STATE_EXPIRED = 'EXPIRED';
    public const STATE_FAILED = 'FAILED';

    public static function tableName(): string
    {
        return 'signup_pairing';
    }

    /**
     * @return list<string>
     */
    public static function stateValues(): array
    {
        return [
            self::STATE_PENDING,
            self::STATE_CLAIMED,
            self::STATE_AUTHORIZED,
            self::STATE_VERIFICATION_STARTED,
            self::STATE_VERIFIED,
            self::STATE_ACCOUNT_CREATED,
            self::STATE_CANCELLED,
            self::STATE_EXPIRED,
            self::STATE_FAILED,
        ];
    }

    /**
     * Estados donde el vínculo ya no avanza.
     *
     * @return list<string>
     */
    public static function terminalStateValues(): array
    {
        return [
            self::STATE_ACCOUNT_CREATED,
            self::STATE_CANCELLED,
            self::STATE_EXPIRED,
            self::STATE_FAILED,
        ];
    }

    public function rules(): array
    {
        return [
            [[
                'id',
                'token_hash',
                'code_hash',
                'web_session_hash',
                'state',
                'expires_at',
                'created_at',
            ], 'required'],
            [['code_attempts', 'id_efector', 'id_persona', 'id_user'], 'integer'],
            [['expires_at', 'claimed_at', 'authorized_at', 'verified_at', 'completed_at', 'created_at'], 'integer'],
            [['state'], 'in', 'range' => self::stateValues()],
            [['didit_session_id', 'verification_id'], 'string', 'max' => 128],
        ];
    }
}
