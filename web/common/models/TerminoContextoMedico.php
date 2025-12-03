<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class TerminoContextoMedico extends ActiveRecord
{
    public static function tableName()
    {
        return 'terminos_contexto_medico';
    }

    public function behaviors()
    {
        return [TimestampBehavior::class];
    }

    public function rules()
    {
        return [
            [['termino'], 'required'],
            [['termino'], 'string', 'max' => 150],
            [['tipo'], 'string', 'max' => 20],
            [['categoria', 'especialidad', 'fuente'], 'string', 'max' => 100],
            [['peso'], 'number'],
            [['frecuencia_uso'], 'integer'],
            [['metadata'], 'safe'],
            [['activo'], 'boolean'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'termino' => 'Término',
            'tipo' => 'Tipo',
            'categoria' => 'Categoría',
            'especialidad' => 'Especialidad',
            'peso' => 'Peso',
            'frecuencia_uso' => 'Frecuencia de Uso',
            'fuente' => 'Fuente',
            'activo' => 'Activo',
        ];
    }
}


