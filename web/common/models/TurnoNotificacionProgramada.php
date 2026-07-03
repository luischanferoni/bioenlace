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
    /** Procesar motivos de consulta (IA en lote) ~1 min antes del turno. */
    const TIPO_MOTIVOS_IA_BATCH = 'MOTIVOS_IA_BATCH';

    /** Recordatorio journey: cargar motivos de consulta. */
    const TIPO_JOURNEY_MOTIVOS_RECORDATORIO = 'JOURNEY_MOTIVOS_RECORDATORIO';

    /** Último aviso journey: motivos de consulta. */
    const TIPO_JOURNEY_MOTIVOS_ULTIMO_AVISO = 'JOURNEY_MOTIVOS_ULTIMO_AVISO';

    /** Recordatorio journey: cuestionario pre-consulta. */
    const TIPO_JOURNEY_PRECONSULTA_RECORDATORIO = 'JOURNEY_PRECONSULTA_RECORDATORIO';

    /** Escalada multicanal tras push de reubicación (agente A02). */
    const TIPO_RESOLUCION_MULTICANAL = 'RESOLUCION_MULTICANAL';

    /** Cierre de loop sin respuesta (agente A06). */
    const TIPO_RESOLUCION_LOOP_CLOSE = 'RESOLUCION_LOOP_CLOSE';

    /** Anti no-show: checkpoint de riesgo (agente A04). */
    const TIPO_ANTINOSHOW_CHECKPOINT = 'ANTINOSHOW_CHECKPOINT';

    /** Anti no-show: liberar cupo si no confirmó (agente A04). */
    const TIPO_ANTINOSHOW_RELEASE = 'ANTINOSHOW_RELEASE';

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
