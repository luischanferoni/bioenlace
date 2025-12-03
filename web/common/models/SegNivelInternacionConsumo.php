<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "seg_nivel_internacion_consumo".
 *
 * @property int $id
 * @property string|null $conceptId
 * @property string|null $tipo
 * @property float|null $cantidad
 * @property int|null $unidad
 */
class SegNivelInternacionConsumo extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'seg_nivel_internacion_consumo';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id'], 'required'],
            [['id', 'unidad'], 'integer'],
            [['tipo'], 'string'],
            [['cantidad'], 'number'],
            [['conceptId'], 'string', 'max' => 25],
            [['id'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'conceptId' => 'Concept ID',
            'tipo' => 'Tipo',
            'cantidad' => 'Cantidad',
            'unidad' => 'Unidad',
        ];
    }
}
