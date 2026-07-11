<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Vínculo WhatsApp Cloud API ↔ usuario paciente.
 *
 * @property int $id
 * @property string $wa_id
 * @property int|null $user_id
 * @property int|null $id_persona
 * @property string $estado
 * @property int|null $pending_user_id
 * @property int|null $pending_id_persona
 * @property string|null $flow_session
 * @property string $created_at
 * @property string|null $updated_at
 */
class AsistenteWhatsappVinculo extends ActiveRecord
{
    public const ESTADO_PENDIENTE_CONFIRMACION = 'PENDIENTE_CONFIRMACION';
    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_RECHAZADO = 'RECHAZADO';

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
            self::ESTADO_PENDIENTE_CONFIRMACION,
            self::ESTADO_ACTIVO,
            self::ESTADO_RECHAZADO,
        ];
    }

    public function rules(): array
    {
        return [
            [['wa_id', 'estado'], 'required'],
            [['user_id', 'id_persona', 'pending_user_id', 'pending_id_persona'], 'integer'],
            [['flow_session'], 'string'],
            [['wa_id'], 'string', 'max' => 64],
            [['estado'], 'in', 'range' => self::estadoValues()],
            [['wa_id'], 'unique'],
            [['created_at', 'updated_at'], 'safe'],
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

    public static function findByWaId(string $waId): ?self
    {
        $waId = trim($waId);
        if ($waId === '') {
            return null;
        }

        return self::findOne(['wa_id' => $waId]);
    }
}
