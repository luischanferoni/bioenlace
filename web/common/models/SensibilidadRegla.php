<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * Regla de sensibilidad por categoría: acción (generalizar|ocultar) aplicada a una lista de servicios.
 * Los servicios en sensibilidad_regla_servicio reciben esa acción; el resto ve el dato completo.
 * Lista vacía = nadie restringido = todos ven completo.
 *
 * @property int $id
 * @property int $id_categoria
 * @property string $accion generalizar|ocultar
 * @property string|null $codigo_generalizacion
 * @property string|null $etiqueta_generalizacion
 * @property SensibilidadCategoria $categoria
 * @property SensibilidadReglaServicio[] $reglaServicios
 * @property Servicio[] $servicios
 */
class SensibilidadRegla extends ActiveRecord
{
    const ACCION_GENERALIZAR = 'generalizar';
    const ACCION_OCULTAR = 'ocultar';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'sensibilidad_regla';
    }

    /**
     * Acciones permitidas para la regla (no se usa "ver_completo": el resto ve completo por defecto).
     */
    public static function accionesDisponibles()
    {
        return [
            self::ACCION_GENERALIZAR => 'Generalizar',
            self::ACCION_OCULTAR => 'Ocultar',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_categoria', 'accion'], 'required'],
            [['id_categoria'], 'integer'],
            [['accion'], 'in', 'range' => array_keys(self::accionesDisponibles())],
            [['codigo_generalizacion'], 'string', 'max' => 50],
            [['etiqueta_generalizacion'], 'string', 'max' => 255],
            [['id_categoria'], 'unique', 'targetAttribute' => ['id_categoria'], 'message' => 'Ya existe una regla para esta categoría.'],
            [['id_categoria'], 'exist', 'skipOnError' => true, 'targetClass' => SensibilidadCategoria::class, 'targetAttribute' => ['id_categoria' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_categoria' => 'Categoría',
            'accion' => 'Acción',
            'codigo_generalizacion' => 'Código generalización',
            'etiqueta_generalizacion' => 'Etiqueta generalización',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCategoria()
    {
        return $this->hasOne(SensibilidadCategoria::class, ['id' => 'id_categoria']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getReglaServicios()
    {
        return $this->hasMany(SensibilidadReglaServicio::class, ['id_regla' => 'id']);
    }

    /**
     * Servicios que reciben la acción (generalizar/ocultar). Lista vacía = todos ven completo.
     * @return \yii\db\ActiveQuery
     */
    public function getServicios()
    {
        return $this->hasMany(Servicio::class, ['id_servicio' => 'id_servicio'])
            ->viaTable('sensibilidad_regla_servicio', ['id_regla' => 'id']);
    }
}
