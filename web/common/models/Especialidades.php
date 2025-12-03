<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "especialidades".
 *
 * @property integer $id_especialidad
 * @property string $nombre
 */
class Especialidades extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'especialidades';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_profesion'], 'required'],
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
            'id_profesion' => 'Profesión',
            'id_especialidad' => 'Id Especialidad',
            'nombre' => 'Especialidad',
        ];
    }

    public function getProfesion() {
        return $this->hasOne(Profesiones::className(), ['id_profesion' => 'id_profesion']);
    }
     
}
