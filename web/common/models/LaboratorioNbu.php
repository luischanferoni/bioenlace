<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "laboratorio_nbu".
 *
 * @property int $id
 * @property int $codigo
 * @property string $nombre
 * @property int|null $snomed_codigo
 * @property string|null $snomed_nombre
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property string|null $deleted_at
 */
class LaboratorioNbu extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'laboratorio_nbu';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['codigo', 'nombre'], 'required'],
            [['codigo', 'snomed_codigo'], 'integer'],
            [['created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['nombre', 'snomed_nombre'], 'string', 'max' => 255],
            [['codigo'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'codigo' => 'Codigo',
            'nombre' => 'Nombre',
            'snomed_codigo' => 'Snomed Codigo',
            'snomed_nombre' => 'Snomed Nombre',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'deleted_at' => 'Deleted At',
        ];
    }

    public function getlaboratorioNbuSnomed()
    {
        return $this->hasOne(LaboratorioNbuSnomed::className(), ['codigo' => 'codigo']);
    }
    
}
