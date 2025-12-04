<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "seg_nivel_internacion_suministro_medicamento".
 *
 * @property int $id
 * @property int $id_internacion
 * @property int $id_internacion_medicamento
 * @property int $id_rrhh
 * @property string $fecha
 * @property string $hora
 * @property string|null $observacion
 *
 * @property SegNivelInternacion $internacion
 * @property SegNivelInternacionMedicamento $medicamento
 */
class SegNivelInternacionSuministroMedicamento extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'seg_nivel_internacion_suministro_medicamento';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_internacion', 'id_internacion_medicamento', 'id_rrhh', 'fecha', 'hora'], 'required'],
            [['id', 'id_internacion', 'id_internacion_medicamento', 'id_rrhh'], 'integer'],
            [['fecha', 'hora'], 'safe'],
            [['observacion'], 'string'],
            [['id_internacion'], 'exist', 'skipOnError' => true, 'targetClass' => SegNivelInternacion::className(), 'targetAttribute' => ['id_internacion' => 'id']],
            [['id_internacion_medicamento'], 'exist', 'skipOnError' => true, 'targetClass' => SegNivelInternacionMedicamento::className(), 'targetAttribute' => ['id_internacion_medicamento' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_internacion' => 'Internación',
            'id_internacion_medicamento' => 'Medicamento',
            'id_rrhh' => 'Recurso Humano',
            'fecha' => 'Fecha',
            'hora' => 'Hora',
            'observacion' => 'Observación',
        ];
    }

    /**
     * Gets query for [[SegNivelInternacion]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getInternacion()
    {
        return $this->hasOne(SegNivelInternacion::className(), ['id' => 'id_internacion']);
    }

    /**
     * Gets query for [[SegNivelInternacionMedicamento]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMedicamento()
    {
        return $this->hasOne(SegNivelInternacionMedicamento::className(), ['id' => 'id_internacion_medicamento']);
    }
}

