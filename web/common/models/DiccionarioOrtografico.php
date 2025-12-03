<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class DiccionarioOrtografico extends ActiveRecord
{
    const TIPO_TERMINO = 'termino';
    const TIPO_ERROR = 'error';
    const TIPO_STOPWORD = 'stopword';

    public static function tableName()
    {
        return 'diccionario_ortografico';
    }

    public function behaviors()
    {
        return [TimestampBehavior::class];
    }

    public function rules()
    {
        return [
            [['termino', 'tipo'], 'required'],
            [['termino', 'correccion'], 'string', 'max' => 150],
            [['tipo'], 'string', 'max' => 20],
            [['categoria', 'especialidad'], 'string', 'max' => 100],
            [['frecuencia'], 'integer'],
            [['peso'], 'number'],
            [['metadata'], 'safe'],
            [['activo'], 'boolean'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'termino' => 'Término',
            'correccion' => 'Corrección',
            'tipo' => 'Tipo',
            'categoria' => 'Categoría',
            'especialidad' => 'Especialidad',
            'frecuencia' => 'Frecuencia',
            'peso' => 'Peso',
            'activo' => 'Activo',
        ];
    }
}


