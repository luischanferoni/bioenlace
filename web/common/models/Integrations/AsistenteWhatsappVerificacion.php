<?php

namespace common\models\Integrations;

use yii\db\ActiveRecord;

/**
 * Intento de verificación de identidad del canal WhatsApp.
 *
 * Guarda el resultado de Didit o passkey para auditoría. No reemplaza al vínculo:
 * el estado vigente vive en {@see AsistenteWhatsappVinculo}.
 *
 * @property int $id
 * @property string $wa_id
 * @property int|null $user_id
 * @property int|null $id_persona
 * @property string $purpose
 * @property string $method
 * @property string $status
 * @property string|null $didit_session_id
 * @property string|null $didit_verification_id
 * @property string|null $didit_reference_id
 * @property string|null $linked_document
 * @property string|null $expires_at
 * @property string|null $consumed_at
 * @property string $created_at
 * @property string|null $updated_at
 */
class AsistenteWhatsappVerificacion extends ActiveRecord
{
    public const PURPOSE_LINK = 'LINK';
    public const PURPOSE_STEP_UP = 'STEP_UP';

    public const METHOD_APP_BIOMETRIC = 'APP_BIOMETRIC';
    public const METHOD_PASSKEY = 'PASSKEY';
    public const METHOD_DIDIT = 'DIDIT';

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_CONSUMED = 'CONSUMED';

    public static function tableName(): string
    {
        return '{{%asistente_whatsapp_verificacion}}';
    }

    /**
     * @return list<string>
     */
    public static function purposeValues(): array
    {
        return [self::PURPOSE_LINK, self::PURPOSE_STEP_UP];
    }

    /**
     * @return list<string>
     */
    public static function methodValues(): array
    {
        return [self::METHOD_APP_BIOMETRIC, self::METHOD_PASSKEY, self::METHOD_DIDIT];
    }

    /**
     * @return list<string>
     */
    public static function statusValues(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_EXPIRED,
            self::STATUS_CONSUMED,
        ];
    }

    public function rules(): array
    {
        return [
            [['wa_id', 'purpose', 'method', 'status'], 'required'],
            [['user_id', 'id_persona'], 'integer'],
            [['wa_id'], 'string', 'max' => 64],
            [['purpose'], 'in', 'range' => self::purposeValues()],
            [['method'], 'in', 'range' => self::methodValues()],
            [['status'], 'in', 'range' => self::statusValues()],
            [['didit_session_id', 'didit_verification_id', 'didit_reference_id'], 'string', 'max' => 128],
            [['linked_document'], 'string', 'max' => 32],
            [['expires_at', 'consumed_at', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        if ($insert && empty($this->created_at)) {
            $this->created_at = $now;
        }
        $this->updated_at = $now;

        return true;
    }

    public static function findByDiditSession(string $sessionId): ?self
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            return null;
        }

        return self::findOne(['didit_session_id' => $sessionId]);
    }
}
