<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "tipo_consultas".
 *
 * @property string $id_tipo_consulta
 * @property string $nombre
 *
 * @property Consultas[] $consultas
 */
class TipoConsulta extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'tipo_consulta';
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

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getConsultas()
    {
        return $this->hasMany(Consultas::className(), ['id_tipo_consulta' => 'id_tipo_consulta']);
    }
    
    //Agregamos esta función para traer los nombres de los tipos de consultas
    public static function getListaTiposConsultas()
    {
       $tipos_consultas = static::find()->indexBy('id_tipo_consulta')->asArray()->all(); 
       return \yii\helpers\ArrayHelper::map($tipos_consultas, 'id_tipo_consulta', 'nombre');
    }
}
