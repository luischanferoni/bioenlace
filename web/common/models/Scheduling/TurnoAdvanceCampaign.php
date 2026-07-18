<?php

namespace common\models\Scheduling;

use yii\db\ActiveRecord;

/**
 * Campaña de ofertas secuenciales para reocupar un slot cancelado.
 *
 * @property int $id
 * @property int $id_cancelled_turno
 * @property int $id_efector
 * @property int $id_servicio
 * @property int $id_profesional_efector_servicio
 * @property string $fecha
 * @property string $hora
 * @property string $modalidad
 * @property string $estado
 * @property int $current_sequence
 * @property string|null $next_run_at
 * @property int|null $id_turno_filled
 * @property string|null $stop_reason
 * @property string $created_at
 * @property string|null $updated_at
 */
class TurnoAdvanceCampaign extends ActiveRecord
{
    public const ESTADO_ACTIVE = 'ACTIVE';
    public const ESTADO_FILLED = 'FILLED';
    public const ESTADO_EXHAUSTED = 'EXHAUSTED';
    public const ESTADO_STOPPED = 'STOPPED';

    public static function tableName()
    {
        return '{{%turno_advance_campaign}}';
    }

    /**
     * @return list<string>
     */
    public static function estadoValues(): array
    {
        return [
            self::ESTADO_ACTIVE,
            self::ESTADO_FILLED,
            self::ESTADO_EXHAUSTED,
            self::ESTADO_STOPPED,
        ];
    }

    public function rules()
    {
        return [
            [[
                'id_cancelled_turno',
                'id_efector',
                'id_servicio',
                'id_profesional_efector_servicio',
                'fecha',
                'hora',
                'estado',
                'created_at',
            ], 'required'],
            [[
                'id_cancelled_turno',
                'id_efector',
                'id_servicio',
                'id_profesional_efector_servicio',
                'current_sequence',
                'id_turno_filled',
            ], 'integer'],
            [['fecha', 'hora', 'next_run_at', 'created_at', 'updated_at'], 'safe'],
            [['modalidad'], 'string', 'max' => 32],
            [['estado'], 'string', 'max' => 16],
            [['stop_reason'], 'string', 'max' => 64],
            [['estado'], 'in', 'range' => self::estadoValues()],
        ];
    }
}
