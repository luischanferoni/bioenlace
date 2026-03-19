<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Configuración de turnos y comunicación médica por efector.
 *
 * @property int $id
 * @property int $id_efector
 * @property int $cancel_suave_umbral
 * @property int $cancel_moderada_umbral
 * @property int $cancel_ventana_dias
 * @property int $autogestion_liberacion_vigencia_dias
 * @property bool $confirmacion_requerida
 * @property bool $permitir_cambio_modalidad
 * @property bool $recordatorios_habilitados
 * @property string $modo_comunicacion_medicos
 * @property bool $sobreturno_notificar_retraso
 * @property int $sobreturno_minutos_retraso_estimado
 * @property bool $cancelacion_masiva
 * @property string $created_at
 * @property string $updated_at
 */
class EfectorTurnosConfig extends ActiveRecord
{
    const MODO_MEDICOS_DESHABILITADO = 'deshabilitado';
    const MODO_MEDICOS_DIRECTO = 'directo';
    const MODO_MEDICOS_INTERMEDIARIO = 'intermediario';
    const MODO_MEDICOS_AUTO_ASIGNACION = 'auto_asignacion';

    public static function tableName()
    {
        return '{{%efector_turnos_config}}';
    }

    public function rules()
    {
        return [
            [['id_efector'], 'required'],
            [['id_efector', 'cancel_suave_umbral', 'cancel_moderada_umbral', 'cancel_ventana_dias',
                'autogestion_liberacion_vigencia_dias', 'sobreturno_minutos_retraso_estimado'], 'integer'],
            [['confirmacion_requerida', 'permitir_cambio_modalidad', 'recordatorios_habilitados',
                'sobreturno_notificar_retraso', 'cancelacion_masiva'], 'boolean'],
            [['modo_comunicacion_medicos'], 'string', 'max' => 32],
            [['modo_comunicacion_medicos'], 'in', 'range' => [
                self::MODO_MEDICOS_DESHABILITADO,
                self::MODO_MEDICOS_DIRECTO,
                self::MODO_MEDICOS_INTERMEDIARIO,
                self::MODO_MEDICOS_AUTO_ASIGNACION,
            ]],
            [['id_efector'], 'unique'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'cancel_suave_umbral' => 'Umbral cancelaciones (mensaje suave)',
            'cancel_moderada_umbral' => 'Umbral cancelaciones (moderada)',
            'cancel_ventana_dias' => 'Ventana días para contar cancelaciones',
            'autogestion_liberacion_vigencia_dias' => 'Días de vigencia liberación autogestión',
            'confirmacion_requerida' => 'Pedir confirmación de asistencia',
            'permitir_cambio_modalidad' => 'Permitir cambio presencial/teleconsulta',
            'recordatorios_habilitados' => 'Recordatorios push',
            'modo_comunicacion_medicos' => 'Comunicación entre médicos',
            'sobreturno_notificar_retraso' => 'Notificar retraso por sobreturno',
            'sobreturno_minutos_retraso_estimado' => 'Minutos retraso estimado (sobreturno)',
            'cancelacion_masiva' => 'Permitir cancelación masiva por día',
        ];
    }

    public function getEfector()
    {
        return $this->hasOne(Efector::className(), ['id_efector' => 'id_efector']);
    }

    /**
     * Obtiene o crea fila por defecto para el efector.
     * @param int $idEfector
     * @return static
     */
    public static function getOrCreateForEfector($idEfector)
    {
        $row = static::findOne(['id_efector' => (int) $idEfector]);
        if ($row) {
            return $row;
        }
        $row = new static();
        $row->id_efector = (int) $idEfector;
        $row->save(false);
        return $row;
    }
}
