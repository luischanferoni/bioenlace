<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class DiccionarioOrtografico extends ActiveRecord
{
    const TIPO_TERMINO = 'termino';
    const TIPO_ERROR = 'error';
    const TIPO_STOPWORD = 'stopword';
    const TIPO_REGEX_PRESERVAR = 'regex_preservar';

    public static function tableName()
    {
        // Usar la nueva tabla unificada si existe, sino la antigua (para compatibilidad)
        $tableExists = \Yii::$app->db->schema->getTableSchema('{{%diccionario_medico}}') !== null;
        return $tableExists ? 'diccionario_medico' : 'diccionario_ortografico';
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
            [['tipo'], 'string', 'max' => 30], // Aumentado para regex_preservar
            [['categoria', 'especialidad'], 'string', 'max' => 100],
            [['frecuencia'], 'integer'],
            [['peso'], 'number'],
            [['fuente'], 'string', 'max' => 50],
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
            'fuente' => 'Fuente',
            'metadata' => 'Metadata',
            'activo' => 'Activo',
        ];
    }
}


