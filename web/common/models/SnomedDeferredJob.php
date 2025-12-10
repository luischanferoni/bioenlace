<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Modelo para trabajos SNOMED diferidos
 * Almacena trabajos de codificación SNOMED para procesamiento en segundo plano
 */
class SnomedDeferredJob extends ActiveRecord
{
    public static function tableName()
    {
        return 'snomed_deferred_jobs';
    }

    public function behaviors()
    {
        return [TimestampBehavior::class];
    }

    public function rules()
    {
        return [
            [['datos_extraidos', 'categorias', 'status'], 'required'],
            [['consulta_id'], 'integer'],
            [['datos_extraidos', 'categorias', 'resultado'], 'string'],
            [['status'], 'string', 'max' => 20],
            [['processed_at'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'consulta_id' => 'ID Consulta',
            'datos_extraidos' => 'Datos Extraídos',
            'categorias' => 'Categorías',
            'status' => 'Estado',
            'resultado' => 'Resultado',
            'processed_at' => 'Procesado En',
        ];
    }
}

