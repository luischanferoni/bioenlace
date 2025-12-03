<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "abreviaturas_medicos".
 *
 * @property int $id
 * @property int $abreviatura_id
 * @property int $id_rr_hh
 * @property int $frecuencia_uso
 * @property string $fecha_primer_uso
 * @property string $fecha_ultimo_uso
 * @property int $activo
 */
class AbreviaturasMedicos extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'abreviaturas_rrhh';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['abreviatura_id', 'id_rr_hh'], 'required'],
            [['abreviatura_id', 'id_rr_hh', 'frecuencia_uso', 'activo'], 'integer'],
            [['fecha_primer_uso', 'fecha_ultimo_uso'], 'safe'],
            [['abreviatura_id', 'id_rr_hh'], 'unique', 'targetAttribute' => ['abreviatura_id', 'id_rr_hh']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'abreviatura_id' => 'Abreviatura ID',
            'id_rr_hh' => 'ID RRHH',
            'frecuencia_uso' => 'Frecuencia Uso',
            'fecha_primer_uso' => 'Fecha Primer Uso',
            'fecha_ultimo_uso' => 'Fecha Ultimo Uso',
            'activo' => 'Activo',
        ];
    }

    /**
     * Relación con AbreviaturasMedicas
     */
    public function getAbreviatura()
    {
        return $this->hasOne(AbreviaturasMedicas::class, ['id' => 'abreviatura_id']);
    }

    /**
     * Registrar uso de abreviatura por médico
     * @param int $abreviaturaId
     * @param int $idRrHh
     * @return bool
     */
    public static function registrarUso($abreviaturaId, $idRrHh)
    {
        $relacion = self::find()
            ->where(['abreviatura_id' => $abreviaturaId, 'id_rr_hh' => $idRrHh])
            ->one();

        if ($relacion) {
            // Incrementar frecuencia y actualizar fecha
            $relacion->frecuencia_uso++;
            $relacion->fecha_ultimo_uso = date('Y-m-d H:i:s');
            return $relacion->save();
        } else {
            // Crear nueva relación
            $relacion = new self();
            $relacion->abreviatura_id = $abreviaturaId;
            $relacion->id_rr_hh = $idRrHh;
            $relacion->frecuencia_uso = 1;
            $relacion->fecha_primer_uso = date('Y-m-d H:i:s');
            $relacion->fecha_ultimo_uso = date('Y-m-d H:i:s');
            $relacion->activo = 1;
            return $relacion->save();
        }
    }

    /**
     * Obtener abreviaturas más usadas por un médico específico
     * @param int $idRrHh
     * @param int $limit
     * @return array
     */
    public static function getAbreviaturasPorMedico($idRrHh, $limit = 10)
    {
        return self::find()
            ->joinWith('abreviatura')
            ->where(['id_rr_hh' => $idRrHh, 'activo' => 1])
            ->orderBy(['frecuencia_uso' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    /**
     * Obtener médicos que usan una abreviatura específica
     * @param int $abreviaturaId
     * @return array
     */
    public static function getMedicosPorAbreviatura($abreviaturaId)
    {
        return self::find()
            ->where(['abreviatura_id' => $abreviaturaId, 'activo' => 1])
            ->orderBy(['frecuencia_uso' => SORT_DESC])
            ->all();
    }

    /**
     * Obtener estadísticas de uso por médico
     * @param int $idRrHh
     * @return array
     */
    public static function getEstadisticasPorMedico($idRrHh)
    {
        $total = self::find()
            ->where(['id_rr_hh' => $idRrHh, 'activo' => 1])
            ->sum('frecuencia_uso');

        $abreviaturasUnicas = self::find()
            ->where(['id_rr_hh' => $idRrHh, 'activo' => 1])
            ->count();

        return [
            'total_usos' => $total ?: 0,
            'abreviaturas_unicas' => $abreviaturasUnicas,
            'promedio_uso' => $abreviaturasUnicas > 0 ? round($total / $abreviaturasUnicas, 2) : 0
        ];
    }
}
