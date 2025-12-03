<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "condiciones_laborales".
 *
 * @property string $id_condicion_laboral
 * @property string $nombre
 *
 * @property RrHhEfector[] $rrHhEfectors
 */
class Condiciones_laborales extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'condiciones_laborales';
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
     * @return \yii\db\ActiveQuery
     */
    public function getRrHhEfectors()
    {
        return $this->hasMany(RrHhEfector::className(), ['id_condicion_laboral' => 'id_condicion_laboral']);
    }
}
