<?php

namespace common\models\Scheduling;

use yii\db\ActiveRecord;

/**
 * Estado global de materialización del perfil (watermark por versión de contrato).
 *
 * @property int $id
 * @property int $profile_contract_version
 * @property int|null $last_watermark_event_id
 * @property string $last_status
 * @property string|null $last_run_at
 * @property string|null $last_error
 * @property string $updated_at
 */
class PersonaTurnosPerfilMaterializacion extends ActiveRecord
{
    public const STATUS_IDLE = 'IDLE';
    public const STATUS_RUNNING = 'RUNNING';
    public const STATUS_OK = 'OK';
    public const STATUS_FAILED = 'FAILED';

    public static function tableName()
    {
        return '{{%persona_turnos_perfil_materializacion}}';
    }

    /** @return list<string> */
    public static function statusValues(): array
    {
        return [
            self::STATUS_IDLE,
            self::STATUS_RUNNING,
            self::STATUS_OK,
            self::STATUS_FAILED,
        ];
    }

    public function rules()
    {
        return [
            [['profile_contract_version', 'last_status', 'updated_at'], 'required'],
            [['profile_contract_version', 'last_watermark_event_id'], 'integer'],
            [['last_run_at', 'updated_at'], 'safe'],
            [['last_error'], 'string'],
            [['last_status'], 'in', 'range' => self::statusValues()],
        ];
    }
}
