<?php

namespace common\models\Integrations;

use yii\db\ActiveRecord;

/**
 * Operación de escritura esperando el step-up de identidad.
 *
 * El cuerpo se guarda acá para no confiar en lo que el paciente reescriba en el chat.
 * El token de step-up se guarda hasheado.
 *
 * @property int $id
 * @property string $wa_id
 * @property int|null $user_id
 * @property int|null $id_persona
 * @property string|null $intent_id
 * @property string|null $subintent_id
 * @property string $route
 * @property string $method
 * @property string|null $body_json
 * @property string|null $draft_hash
 * @property string $token_hash
 * @property string $status
 * @property string $expires_at
 * @property string|null $consumed_at
 * @property string $created_at
 * @property string|null $updated_at
 */
class AsistenteWhatsappAccionPendiente extends ActiveRecord
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_EXECUTED = 'EXECUTED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_EXPIRED = 'EXPIRED';

    public static function tableName(): string
    {
        return '{{%asistente_whatsapp_accion_pendiente}}';
    }

    /**
     * @return list<string>
     */
    public static function statusValues(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_EXECUTED,
            self::STATUS_REJECTED,
            self::STATUS_EXPIRED,
        ];
    }

    public function rules(): array
    {
        return [
            [['wa_id', 'route', 'token_hash', 'status', 'expires_at'], 'required'],
            [['user_id', 'id_persona'], 'integer'],
            [['wa_id'], 'string', 'max' => 64],
            [['intent_id', 'subintent_id'], 'string', 'max' => 128],
            [['route'], 'string', 'max' => 255],
            [['method'], 'string', 'max' => 8],
            [['body_json'], 'string'],
            [['draft_hash', 'token_hash'], 'string', 'max' => 64],
            [['status'], 'in', 'range' => self::statusValues()],
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

    /**
     * @return array<string, mixed>
     */
    public function getBodyArray(): array
    {
        $raw = trim((string) $this->body_json);
        if ($raw === '') {
            return [];
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $body
     */
    public function setBodyArray(array $body): void
    {
        $this->body_json = $body === [] ? null : json_encode($body, JSON_UNESCAPED_UNICODE);
    }

    public static function findByToken(string $token): ?self
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $row = self::findOne(['token_hash' => hash('sha256', $token)]);
        if ($row === null) {
            return null;
        }
        if (strtotime((string) $row->expires_at) < time()) {
            return null;
        }

        return $row->status === self::STATUS_PENDING ? $row : null;
    }
}
