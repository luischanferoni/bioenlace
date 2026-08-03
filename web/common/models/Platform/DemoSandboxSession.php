<?php

namespace common\models\Platform;

use yii\db\ActiveRecord;

/**
 * Sesión efímera del sandbox demo (médico temporal + seed clínico).
 *
 * @property int $id
 * @property int|null $id_access
 * @property string $role
 * @property int $id_efector
 * @property int $id_user
 * @property int $id_persona
 * @property int $id_pes
 * @property int $id_servicio
 * @property string $username
 * @property string|null $seed_payload_json
 * @property string $expires_at
 * @property string|null $purged_at
 * @property string $created_at
 */
class DemoSandboxSession extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%demo_sandbox_session}}';
    }

    public function rules(): array
    {
        return [
            [['role', 'id_efector', 'id_user', 'id_persona', 'id_pes', 'id_servicio', 'username', 'expires_at', 'created_at'], 'required'],
            [['id_access', 'id_efector', 'id_user', 'id_persona', 'id_pes', 'id_servicio'], 'integer'],
            [['seed_payload_json', 'expires_at', 'purged_at', 'created_at'], 'safe'],
            [['role'], 'string', 'max' => 32],
            [['username'], 'string', 'max' => 64],
        ];
    }

    public function isPurged(): bool
    {
        return $this->purged_at !== null && $this->purged_at !== '';
    }

    public function isExpired(?string $now = null): bool
    {
        $now = $now ?? date('Y-m-d H:i:s');

        return $this->expires_at <= $now;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSeedPayload(): array
    {
        if ($this->seed_payload_json === null || $this->seed_payload_json === '') {
            return [];
        }
        $decoded = json_decode($this->seed_payload_json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setSeedPayload(array $payload): void
    {
        $this->seed_payload_json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
