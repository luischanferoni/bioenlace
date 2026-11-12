<?php

namespace common\models\Integrations;

use common\components\Platform\Assistant\WhatsApp\WhatsAppLinkToken;
use yii\db\ActiveRecord;

/**
 * Vínculo WhatsApp Cloud API ↔ usuario paciente.
 *
 * La verificación inicial y el step-up se registran acá; el historial de intentos
 * vive en {@see AsistenteWhatsappVerificacion}. Los secretos se guardan hasheados.
 *
 * @property int $id
 * @property string $wa_id
 * @property int|null $user_id
 * @property int|null $id_persona
 * @property string $estado
 * @property int|null $pending_user_id
 * @property int|null $pending_id_persona
 * @property string|null $flow_session
 * @property string|null $verification_method
 * @property string|null $verified_at
 * @property string|null $verification_expires_at
 * @property string|null $link_token_hash
 * @property string|null $link_token_expires_at
 * @property int $failed_attempts
 * @property string|null $last_failed_at
 * @property string|null $consent_at
 * @property string|null $consent_version
 * @property string|null $opt_out_at
 * @property string|null $last_activity_at
 * @property string $created_at
 * @property string|null $updated_at
 */
class AsistenteWhatsappVinculo extends ActiveRecord
{
    /** @deprecated Solo para que la migración histórica m260711 pueda ejecutarse en una BD nueva. */
    public const ESTADO_PENDIENTE_CONFIRMACION = 'PENDIENTE_CONFIRMACION';

    public const ESTADO_PENDIENTE_VERIFICACION = 'PENDIENTE_VERIFICACION';
    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_RECHAZADO = 'RECHAZADO';
    public const ESTADO_BLOQUEADO = 'BLOQUEADO';
    public const ESTADO_EXPIRADO = 'EXPIRADO';

    public const VERIFICATION_METHOD_APP_BIOMETRIC = 'APP_BIOMETRIC';
    public const VERIFICATION_METHOD_PASSKEY = 'PASSKEY';
    public const VERIFICATION_METHOD_DIDIT = 'DIDIT';

    public const MAX_FAILED_ATTEMPTS = 5;

    public static function tableName(): string
    {
        return '{{%asistente_whatsapp_vinculo}}';
    }

    /**
     * @return list<string>
     */
    public static function estadoValues(): array
    {
        return [
            self::ESTADO_PENDIENTE_VERIFICACION,
            self::ESTADO_ACTIVO,
            self::ESTADO_RECHAZADO,
            self::ESTADO_BLOQUEADO,
            self::ESTADO_EXPIRADO,
        ];
    }

    /**
     * @return list<string>
     */
    public static function verificationMethodValues(): array
    {
        return [
            self::VERIFICATION_METHOD_APP_BIOMETRIC,
            self::VERIFICATION_METHOD_PASSKEY,
            self::VERIFICATION_METHOD_DIDIT,
        ];
    }

    public function rules(): array
    {
        return [
            [['wa_id', 'estado'], 'required'],
            [['user_id', 'id_persona', 'pending_user_id', 'pending_id_persona', 'failed_attempts'], 'integer'],
            [['flow_session'], 'string'],
            [['wa_id'], 'string', 'max' => 64],
            [['estado'], 'in', 'range' => self::estadoValues()],
            [['verification_method'], 'in', 'range' => self::verificationMethodValues(), 'skipOnEmpty' => true],
            [['link_token_hash'], 'string', 'max' => 64],
            [['consent_version'], 'string', 'max' => 32],
            [[
                'verified_at',
                'verification_expires_at',
                'link_token_expires_at',
                'last_failed_at',
                'consent_at',
                'opt_out_at',
                'last_activity_at',
                'created_at',
                'updated_at',
            ], 'safe'],
            [['wa_id'], 'unique'],
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
     * @return array{intent_id?: string, subintent_id?: string, draft?: array<string, mixed>}
     */
    public function getFlowSessionArray(): array
    {
        $raw = trim((string) $this->flow_session);
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
     * @param array<string, mixed>|null $session
     */
    public function setFlowSessionArray(?array $session): void
    {
        if ($session === null || $session === []) {
            $this->flow_session = null;

            return;
        }
        $this->flow_session = json_encode($session, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Busca un vínculo por token de vinculación (el token viaja en la URL, no en la BD).
     */
    public static function findByLinkToken(string $token): ?self
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $vinculo = self::findOne(['link_token_hash' => WhatsAppLinkToken::hash($token)]);
        if ($vinculo === null) {
            return null;
        }

        if (WhatsAppLinkToken::isExpired($vinculo->link_token_expires_at)) {
            return null;
        }

        return $vinculo;
    }

    public function isLinked(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO
            && (int) $this->user_id > 0
            && (int) $this->id_persona > 0;
    }

    public function hasValidStepUp(): bool
    {
        $expiresAt = trim((string) $this->verification_expires_at);
        if ($expiresAt === '') {
            return false;
        }

        return strtotime($expiresAt) > time();
    }

    /**
     * Deja el número vinculado a la identidad verificada.
     */
    public function markLinkVerified(int $userId, int $idPersona, string $method): void
    {
        $this->user_id = $userId;
        $this->id_persona = $idPersona;
        $this->estado = self::ESTADO_ACTIVO;
        $this->verification_method = $method;
        $this->verified_at = date('Y-m-d H:i:s');
        $this->verification_expires_at = null;
        $this->link_token_hash = null;
        $this->link_token_expires_at = null;
        $this->failed_attempts = 0;
        $this->last_failed_at = null;
        $this->last_activity_at = date('Y-m-d H:i:s');
    }

    /**
     * Habilita una ventana de escritura. No cambia el vínculo: solo la assurance.
     */
    public function markStepUpVerified(string $method, int $ttlSeconds): void
    {
        $now = time();
        $this->verification_method = $method;
        $this->verified_at = date('Y-m-d H:i:s', $now);
        $this->verification_expires_at = date('Y-m-d H:i:s', $now + max(1, $ttlSeconds));
        $this->last_activity_at = date('Y-m-d H:i:s', $now);
    }

    public function clearStepUp(): void
    {
        $this->verification_expires_at = null;
    }

    public function registerFailure(int $maxAttempts = self::MAX_FAILED_ATTEMPTS): void
    {
        $this->failed_attempts = (int) $this->failed_attempts + 1;
        $this->last_failed_at = date('Y-m-d H:i:s');
        if ($this->failed_attempts >= max(1, $maxAttempts)) {
            $this->estado = self::ESTADO_BLOQUEADO;
        }
    }

    public function touchActivity(): void
    {
        $this->last_activity_at = date('Y-m-d H:i:s');
    }

    /**
     * Genera un token de vinculación de un solo uso y guarda solo su hash.
     *
     * @return string token en claro (para la URL)
     */
    public function issueLinkToken(int $ttlSeconds): string
    {
        $generated = WhatsAppLinkToken::generate($ttlSeconds);
        $this->link_token_hash = $generated['hash'];
        $this->link_token_expires_at = $generated['expires_at'];

        return $generated['token'];
    }

    public static function findByWaId(string $waId): ?self
    {
        $waId = trim($waId);
        if ($waId === '') {
            return null;
        }

        return self::findOne(['wa_id' => $waId]);
    }
}
