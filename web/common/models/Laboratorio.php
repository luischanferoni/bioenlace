<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "laboratorio".
 *
 * @property int $id
 * @property int $id_persona
 * @property string $id_muestra
 * @property string $fecha_recepcion
 * @property string $fecha_procesamiento
 * @property string $resultado
 * @property int $id_efector
 * @property string $observaciones
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $deleted_at
 * @property int $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 */
class Laboratorio extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'laboratorio';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_persona', 'id_muestra', 'fecha_recepcion', 'fecha_procesamiento', 'resultado', 'id_efector', 'observaciones', 'created_by'], 'required'],
            [['id_persona', 'id_efector', 'created_by', 'updated_by', 'deleted_by'], 'integer'],
            [['fecha_recepcion', 'fecha_procesamiento', 'created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['resultado', 'observaciones'], 'string'],
            [['id_muestra'], 'string', 'max' => 256],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_persona' => 'Id Persona',
            'id_muestra' => 'Id Muestra',
            'fecha_recepcion' => 'Fecha Recepcion',
            'fecha_procesamiento' => 'Fecha Procesamiento',
            'resultado' => 'Resultado',
            'id_efector' => 'Id Efector',
            'observaciones' => 'Observaciones',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'deleted_at' => 'Deleted At',
            'created_by' => 'Created By',
            'updated_by' => 'Updated By',
            'deleted_by' => 'Deleted By',
        ];
    }

    /**
     * {@inheritdoc}
     * @return LaboratorioQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new LaboratorioQuery(get_called_class());
    }
}
