<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "cat_condiciones_laborales".
 *
 * @property string $id_condicion_laboral
 * @property string $nombre
 *
 * @property ProfesionalEfectorServicioCondicionLaboral[] $profesionalEfectorServicioCondicionesLaborales
 */
class Condiciones_laborales extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'cat_condiciones_laborales';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['nombre'], 'string', 'max' => 60],
            ['fecha_inicio', 'date'],
            ['fecha_inicio', 'required'],            
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_condicion_laboral' => 'Id Condicion Laboral',
            'nombre' => 'Nombre',
        ];
    }

    /**
     * Filas puente PES–condición laboral para esta condición.
     *
     * @return \yii\db\ActiveQuery
     */
    public function getProfesionalEfectorServicioCondicionesLaborales()
    {
        return $this->hasMany(ProfesionalEfectorServicioCondicionLaboral::className(), ['id_condicion_laboral' => 'id_condicion_laboral']);
    }

}
