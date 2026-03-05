<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * Categoría de sensibilidad para el módulo de resumen con IA.
 * Cada código SNOMED puede mapearse a una categoría; luego las reglas por visor definen si ocultar, generalizar o ver.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 */
class SensibilidadCategoria extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'sensibilidad_categoria';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['nombre'], 'required'],
            [['descripcion'], 'string'],
            [['nombre'], 'string', 'max' => 100],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'nombre' => 'Nombre',
            'descripcion' => 'Descripción',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getMapeosSnomed()
    {
        return $this->hasMany(SensibilidadMapeoSnomed::class, ['id_categoria' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getReglasVisor()
    {
        return $this->hasMany(SensibilidadReglaVisor::class, ['id_categoria' => 'id']);
    }

    /**
     * Valores permitidos para accion en reglas.
     */
    public static function accionesDisponibles()
    {
        return [
            'ver_completo' => 'Ver completo',
            'generalizar' => 'Generalizar',
            'ocultar' => 'Ocultar',
        ];
    }
}
