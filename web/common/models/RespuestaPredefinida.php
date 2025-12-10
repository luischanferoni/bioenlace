<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use common\components\EmbeddingsManager;

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

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'texto_original' => 'Texto Original',
            'texto_hash' => 'Hash del Texto',
            'respuesta_json' => 'Respuesta JSON',
            'categoria' => 'Categoría',
            'servicio' => 'Servicio',
            'similitud_promedio' => 'Similitud Promedio',
            'usos' => 'Usos',
            'created_at' => 'Creado En',
            'updated_at' => 'Actualizado En',
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
        try {
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
        } catch (\Exception $e) {
            \Yii::error("Error buscando respuesta similar: " . $e->getMessage(), 'respuestas-predefinidas');
            return null;
        }
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

