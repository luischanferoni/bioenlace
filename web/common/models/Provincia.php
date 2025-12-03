<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "provincias".
 *
 * @property integer $id_provincia
 * @property string $nombre
 * @property string $region_pais
 * @property integer $superficie
 * @property string $cod_indec
 *
 * @property Departamentos[] $departamentos
 */
class Provincia extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'provincias';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_provincia', 'nombre', 'region_pais', 'superficie', 'cod_indec'], 'required'],
            [['id_provincia', 'superficie'], 'integer'],
            [['nombre', 'region_pais'], 'string', 'max' => 20],
            [['cod_indec'], 'string', 'max' => 2]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id_provincia' => 'Provincia',
            'nombre' => 'Nombre',
            'region_pais' => 'Region Pais',
            'superficie' => 'Superficie',
            'cod_indec' => 'Cod Indec',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDepartamentos()
    {
        return $this->hasMany(Departamentos::className(), ['id_provincia' => 'id_provincia']);
    }
}
