<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "cat_profesiones".
 *
 * @property integer $id_profesion
 * @property string $nombre
 */
class Profesiones extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'cat_profesiones';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['nombre'],'unique'],
            ['nombre', 'required'],
   ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_profesion' => 'Id Profesion',
            'nombre' => 'Profesión',
        ];
    }

     /**
     * @return \yii\db\ActiveQuery
     */
    public function getEspecialidades()
    {
        return $this->hasMany(Especialidades::className(), ['id_profesion' => 'id_profesion']);
    }
}
