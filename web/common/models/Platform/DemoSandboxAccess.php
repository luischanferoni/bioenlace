<?php

namespace common\models\Platform;

use yii\db\ActiveRecord;

/**
 * Código de un solo uso para entrar al sandbox demo (institucional → app).
 *
 * @property int $id
 * @property string $code_hash
 * @property string $role
 * @property string|null $mode
 * @property string|null $username
 * @property int|null $id_user
 * @property string|null $email
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string $expires_at
 * @property string|null $used_at
 * @property string $created_at
 */
class DemoSandboxAccess extends ActiveRecord
{
    public const ROLE_STAFF = 'staff';
    public const ROLE_ENFERMERIA = 'enfermeria';
    public const ROLE_PACIENTE = 'paciente';

    public static function tableName(): string
    {
        return '{{%demo_sandbox_access}}';
    }

    /**
     * @return list<string>
     */
    public static function roleValues(): array
    {
        return [self::ROLE_STAFF, self::ROLE_ENFERMERIA, self::ROLE_PACIENTE];
    }

    public static function isEphemeralStaffRole(string $role): bool
    {
        return $role === self::ROLE_STAFF || $role === self::ROLE_ENFERMERIA;
    }

    public function rules(): array
    {
        return [
            [['code_hash', 'role', 'expires_at', 'created_at'], 'required'],
            [['id_user'], 'integer'],
            [['expires_at', 'used_at', 'created_at'], 'safe'],
            [['code_hash'], 'string', 'max' => 64],
            [['role'], 'string', 'max' => 32],
            [['mode'], 'string', 'max' => 32],
            [['username'], 'string', 'max' => 64],
            [['email'], 'string', 'max' => 255],
            [['ip'], 'string', 'max' => 45],
            [['user_agent'], 'string', 'max' => 512],
            [['role'], 'in', 'range' => self::roleValues()],
        ];
    }

    public function isExpired(?string $now = null): bool
    {
        $now = $now ?? date('Y-m-d H:i:s');

        return $this->expires_at <= $now;
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null && $this->used_at !== '';
    }
}
