<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "seg_nivel_internacion_tipo_ingreso".
 *
 * @property int $id
 * @property string $tipo_ingreso
 *
 * @property SegNivelInternacion[] $segNivelInternacions
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
     * Gets query for [[SegNivelInternacions]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSegNivelInternacions()
    {
        return $this->hasMany(SegNivelInternacion::className(), ['id_tipo_ingreso' => 'id']);
    }
}
