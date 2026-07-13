<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "cat_tipo_consulta".
 *
 * @property string $id_tipo_consulta
 * @property string $nombre
 *
 * @property-read Consulta[] $consultas
 */
class TipoConsulta extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'cat_tipo_consulta';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['nombre'], 'string', 'max' => 60]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_tipo_consulta' => 'Id Tipo Consulta',
            'nombre' => 'Nombre',
        ];
    }

    //Agregamos esta función para traer los nombres de los tipos de consultas
    public static function getListaTiposConsultas()
    {
       $tipos_consultas = static::find()->indexBy('id_tipo_consulta')->asArray()->all(); 
       return \yii\helpers\ArrayHelper::map($tipos_consultas, 'id_tipo_consulta', 'nombre');
    }
}
