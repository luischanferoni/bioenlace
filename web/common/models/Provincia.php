<?php

namespace common\models;

/**
 * This is the model class for table "geo_provincias".
 *
 * @property integer $id_provincia
 * @property integer $id_pais
 * @property string $nombre
 * @property string $region_pais
 * @property integer $superficie
 * @property string $cod_indec Código de subdivisión local (INDEC en AR; scoped por id_pais)
 *
 * @property Pais $pais
 * @property Departamento[] $departamentos
 */
class Provincia extends \yii\db\ActiveRecord
{
    public static function tableName()
    {
        return 'geo_provincias';
    }

    public function rules()
    {
        return [
            [['id_provincia', 'id_pais', 'nombre', 'region_pais', 'superficie', 'cod_indec'], 'required'],
            [['id_provincia', 'id_pais', 'superficie'], 'integer'],
            [['nombre', 'region_pais'], 'string', 'max' => 20],
            [['cod_indec'], 'string', 'max' => 16],
            [['id_pais', 'cod_indec'], 'unique', 'targetAttribute' => ['id_pais', 'cod_indec']],
            [['id_pais'], 'exist', 'skipOnError' => true, 'targetClass' => Pais::class, 'targetAttribute' => ['id_pais' => 'id_pais']],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id_provincia' => 'Provincia',
            'id_pais' => 'País',
            'nombre' => 'Nombre',
            'region_pais' => 'Region Pais',
            'superficie' => 'Superficie',
            'cod_indec' => 'Codigo subdivision',
        ];
    }

    public function getPais()
    {
        return $this->hasOne(Pais::class, ['id_pais' => 'id_pais']);
    }

    public function getDepartamentos()
    {
        return $this->hasMany(Departamento::class, ['id_provincia' => 'id_provincia']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getVecinos()
    {
        return $this->hasMany(self::class, ['id_provincia' => 'id_provincia_vecina'])
            ->viaTable('{{%geo_provincia_vecinos}}', ['id_provincia' => 'id_provincia']);
    }
}
