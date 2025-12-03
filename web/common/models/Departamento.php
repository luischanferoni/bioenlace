<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "departamentos".
 *
 * @property integer $id_departamento
 * @property string $nombre
 * @property string $cod_indec
 * @property integer $id_provincia
 *
 * @property Provincias $idProvincia
 * @property Localidades[] $localidades
 */
class Departamento extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'departamentos';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_departamento', 'nombre', 'cod_indec', 'id_provincia'], 'required'],
            [['id_departamento', 'id_provincia'], 'integer'],
            [['nombre'], 'string', 'max' => 40],
            [['cod_indec'], 'string', 'max' => 3]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_departamento' => 'Departamento',
            'nombre' => 'Nombre del departamento',
            'cod_indec' => 'Codigo del INDEC',
            'id_provincia' => 'Codigo de Provincia',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getProvincia()
    {
        return $this->hasOne(Provincia::className(), ['id_provincia' => 'id_provincia']);
    }
    
  
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getLocalidades()
    {
        return $this->hasMany(Localidades::className(), ['id_departamento' => 'id_departamento']);
    }
}
