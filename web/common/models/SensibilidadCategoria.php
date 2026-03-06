<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * Categoría de sensibilidad para el módulo de resumen con IA.
 * Cada código SNOMED se mapea a una categoría; la regla de la categoría define acción (generalizar|ocultar) y para qué servicios aplica; el resto ve completo.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property SensibilidadRegla|null $regla
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
     * Regla de sensibilidad para esta categoría (una por categoría). Lista de servicios en regla->reglaServicios; el resto ve completo.
     * @return \yii\db\ActiveQuery
     */
    public function getRegla()
    {
        return $this->hasOne(SensibilidadRegla::class, ['id_categoria' => 'id']);
    }
}
