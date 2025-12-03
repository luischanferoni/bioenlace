<?php

/**
* @autor: Ivana Beltrán y María de los A. Valdez
* @versión: 1.1 
* @creacion: 27/11/2015
* @modificacion: 
**/

namespace common\models;

use Yii;

/**
 * This is the model class for table "tipo_ingreso".
 *
 * @property string $id_tipo_ingreso
 * @property string $nombre
 * @property integer $id_tipo_consulta
 *
 * @property Consultas[] $consultas
 * @property TipoConsulta $idTipoConsulta
 */
class TipoIngreso extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'tipo_ingreso';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_tipo_consulta'], 'integer'],
            [['nombre'], 'string', 'max' => 60]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_tipo_ingreso' => 'Id Tipo Ingreso',
            'nombre' => 'Nombre',
            'id_tipo_consulta' => 'Id Tipo Consulta',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getConsultas()
    {
        return $this->hasMany(Consultas::className(), ['id_tipo_ingreso' => 'id_tipo_ingreso']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getIdTipoConsulta()
    {
        return $this->hasOne(TipoConsulta::className(), ['id_tipo_consulta' => 'id_tipo_consulta']);
    }
    
    //Busca los tipos de ingresos correspondientes al tipo de consulta seleccionado
    public function getListaTipoIngresoPorConsulta($id_tipo_cons)
    {
       
       $tipo_ingresos = TipoIngreso::findAll(['id_tipo_consulta'=>$id_tipo_cons]);
       return \yii\helpers\ArrayHelper::map($tipo_ingresos,'id_tipo_ingreso', 'nombre');
               
    }
}
