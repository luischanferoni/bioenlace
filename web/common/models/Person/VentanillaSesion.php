<?php

namespace common\models\Person;

use yii\db\ActiveRecord;

/**
 * Sesión temporal de mostrador (staff actúa por un paciente con TTL).
 *
 * @property int $id
 * @property int $staff_user_id
 * @property int $staff_persona_id
 * @property int $subject_persona_id
 * @property int $id_efector
 * @property string $identity_method
 * @property string $started_at
 * @property string $expires_at
 * @property string|null $closed_at
 * @property string $created_at
 */
class VentanillaSesion extends ActiveRecord
{
    public const METHOD_DNI_LECTOR = 'DNI_LECTOR';
    public const METHOD_DIDIT = 'DIDIT';
    public const METHOD_ID_PERSONA = 'ID_PERSONA';

    public static function tableName(): string
    {
        return '{{%ventanilla_sesion}}';
    }

    public function rules(): array
    {
        return [
            [['staff_user_id', 'staff_persona_id', 'subject_persona_id', 'id_efector', 'identity_method', 'started_at', 'expires_at'], 'required'],
            [['staff_user_id', 'staff_persona_id', 'subject_persona_id', 'id_efector'], 'integer'],
            [['started_at', 'expires_at', 'closed_at', 'created_at'], 'safe'],
            [['identity_method'], 'string', 'max' => 16],
            [['identity_method'], 'in', 'range' => [
                self::METHOD_DNI_LECTOR,
                self::METHOD_DIDIT,
                self::METHOD_ID_PERSONA,
            ]],
        ];
    }

    public function isOpen(): bool
    {
        if ($this->closed_at !== null && trim((string) $this->closed_at) !== '') {
            return false;
        }

        return strtotime((string) $this->expires_at) > time();
    }
}
