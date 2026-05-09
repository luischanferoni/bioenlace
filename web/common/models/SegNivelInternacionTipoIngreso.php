<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "seg_nivel_internacion_tipo_ingreso".
 *
 * @property int $id
 * @property string $tipo_ingreso
 *
 * @property-read SegNivelInternacion[] $internaciones
 */
class SegNivelInternacionTipoIngreso extends \yii\db\ActiveRecord
{

    

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'seg_nivel_internacion_tipo_ingreso';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['tipo_ingreso'], 'required'],
            [['tipo_ingreso'], 'string', 'max' => 80],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'tipo_ingreso' => 'Tipo Ingreso',
        ];
    }

    /**
     * Internaciones con este tipo de ingreso.
     */
    public function getInternaciones()
    {
        return $this->hasMany(SegNivelInternacion::className(), ['id_tipo_ingreso' => 'id']);
    }

    /**
     * Alias histórico (`segNivelInternacions`).
     */
    public function getSegNivelInternacions()
    {
        return $this->getInternaciones();
    }
}
