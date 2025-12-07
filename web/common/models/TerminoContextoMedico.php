<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use common\components\EmbeddingsManager;

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

/**
 * Modelo para respuestas predefinidas de IA
 * Reutiliza respuestas cuando hay alta similitud (sin GPU)
 */
class RespuestaPredefinida extends ActiveRecord
{
    public static function tableName()
    {
        return 'respuestas_predefinidas';
    }

    public function behaviors()
    {
        return [TimestampBehavior::class];
    }

    public function rules()
    {
        return [
            [['texto_original', 'texto_hash', 'respuesta_json'], 'required'],
            [['texto_original', 'respuesta_json'], 'string'],
            [['texto_hash'], 'string', 'length' => 32],
            [['categoria', 'servicio'], 'string', 'max' => 100],
            [['similitud_promedio'], 'number'],
            [['usos'], 'integer'],
        ];
    }

    /**
     * Buscar respuesta similar
     * @param string $texto Texto de la consulta
     * @param string $servicio Nombre del servicio
     * @param float $similitudMinima Umbral mínimo de similitud
     * @return RespuestaPredefinida|null
     */
    public static function buscarSimilar($texto, $servicio, $similitudMinima = 0.85)
    {
        $textoHash = md5($texto);
        
        // Buscar por hash exacto primero
        $exacta = self::find()
            ->where(['texto_hash' => $textoHash, 'servicio' => $servicio])
            ->one();
        
        if ($exacta) {
            return $exacta;
        }
        
        // Buscar por similitud usando embeddings
        $embeddingTexto = EmbeddingsManager::generarEmbedding($texto);
        if (!$embeddingTexto) {
            return null;
        }
        
        $respuestas = self::find()
            ->where(['servicio' => $servicio])
            ->orderBy(['usos' => SORT_DESC])
            ->limit(100) // Limitar búsqueda a las más usadas
            ->all();
        
        $mejorMatch = null;
        $mejorSimilitud = 0;
        
        foreach ($respuestas as $respuesta) {
            $embeddingRespuesta = EmbeddingsManager::generarEmbedding($respuesta->texto_original);
            if (!$embeddingRespuesta) {
                continue;
            }
            
            $similitud = EmbeddingsManager::calcularSimilitudCoseno($embeddingTexto, $embeddingRespuesta);
            
            if ($similitud > $mejorSimilitud && $similitud >= $similitudMinima) {
                $mejorSimilitud = $similitud;
                $mejorMatch = $respuesta;
            }
        }
        
        return $mejorMatch;
    }

    /**
     * Guardar nueva respuesta
     * @param string $texto Texto original
     * @param array $respuesta Respuesta de IA
     * @param string $servicio Servicio médico
     * @return RespuestaPredefinida
     */
    public static function guardar($texto, $respuesta, $servicio)
    {
        $model = new self();
        $model->texto_original = $texto;
        $model->texto_hash = md5($texto);
        $model->respuesta_json = is_array($respuesta) ? json_encode($respuesta) : $respuesta;
        $model->servicio = $servicio;
        $model->similitud_promedio = 0.000;
        $model->usos = 0;
        $model->save();
        
        return $model;
    }
}

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


