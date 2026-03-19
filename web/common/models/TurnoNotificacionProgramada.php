<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $id_turno
 * @property string $tipo
 * @property string $run_at
 * @property string $estado
 * @property string|null $payload_json
 * @property int $intentos
 * @property string|null $ultimo_error
 * @property string $created_at
 * @property string $updated_at
 */
class TurnoNotificacionProgramada extends ActiveRecord
{
    const TIPO_REMINDER = 'REMINDER';
    const TIPO_CONFIRM_REQUEST = 'CONFIRM_REQUEST';
    const TIPO_TRANSPORT_HINT = 'TRANSPORT_HINT';
    const TIPO_RETRASO_SOBRETURNO = 'RETRASO_SOBRETURNO';

    const ESTADO_PENDIENTE = 'PENDIENTE';
    const ESTADO_ENVIADA = 'ENVIADA';
    const ESTADO_CANCELADA = 'CANCELADA';
    const ESTADO_FALLIDA = 'FALLIDA';

    public static function tableName()
    {
        return '{{%turno_notificacion_programada}}';
    }

    public function rules()
    {
        return [
            [['id_turno', 'tipo', 'run_at'], 'required'],
            [['id_turno', 'intentos'], 'integer'],
            [['run_at'], 'safe'],
            [['payload_json', 'ultimo_error'], 'string'],
            [['tipo'], 'string', 'max' => 40],
            [['estado'], 'string', 'max' => 20],
        ];
    }

    public function getTurno()
    {
        return $this->hasOne(Turno::className(), ['id_turnos' => 'id_turno']);
    }

    public static function cancelarPendientesPorTurno($idTurno)
    {
        return static::updateAll(
            ['estado' => self::ESTADO_CANCELADA],
            ['id_turno' => $idTurno, 'estado' => self::ESTADO_PENDIENTE]
        );
    }
}
