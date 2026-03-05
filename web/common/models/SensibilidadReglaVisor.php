<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * Regla de sensibilidad por visor (servicio o rol): para una categoría, define ver_completo, generalizar u ocultar.
 *
 * @property int $id
 * @property string $tipo_visor servicio|rol
 * @property int $id_visor id_servicio o id del rol
 * @property int $id_categoria
 * @property string $accion ver_completo|generalizar|ocultar
 * @property string|null $codigo_generalizacion
 * @property string|null $etiqueta_generalizacion
 * @property SensibilidadCategoria $categoria
 */
class SensibilidadReglaVisor extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'sensibilidad_regla_visor';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['tipo_visor', 'id_visor', 'id_categoria', 'accion'], 'required'],
            [['id_visor', 'id_categoria'], 'integer'],
            [['tipo_visor'], 'in', 'range' => ['servicio', 'rol']],
            [['accion'], 'in', 'range' => array_keys(SensibilidadCategoria::accionesDisponibles())],
            [['codigo_generalizacion'], 'string', 'max' => 50],
            [['etiqueta_generalizacion'], 'string', 'max' => 255],
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
            'tipo_visor' => 'Tipo visor',
            'id_visor' => 'ID visor',
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
}
