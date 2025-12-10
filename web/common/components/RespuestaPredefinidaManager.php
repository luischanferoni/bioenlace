<?php

namespace common\components;

use Yii;

/**
 * Gestor de respuestas predefinidas para reutilizar respuestas de IA
 * Busca respuestas similares en base de datos para evitar usar GPU
 */
class RespuestaPredefinidaManager
{
    /**
     * Obtener respuesta predefinida si existe una similar
     * @param string $texto Texto de la consulta
     * @param string $servicio Nombre del servicio
     * @param float $similitudMinima Umbral mínimo de similitud
     * @return array|null Respuesta JSON o null si no se encuentra
     */
    public static function obtenerRespuesta($texto, $servicio, $similitudMinima = 0.85)
    {
        try {
            $respuesta = \common\models\RespuestaPredefinida::buscarSimilar($texto, $servicio, $similitudMinima);
            
            if ($respuesta) {
                $respuestaJson = is_string($respuesta->respuesta_json) ? 
                    json_decode($respuesta->respuesta_json, true) : 
                    $respuesta->respuesta_json;
                
                return [
                    'id' => $respuesta->id,
                    'respuesta_json' => $respuestaJson,
                ];
            }
            
            return null;
        } catch (\Exception $e) {
            Yii::error("Error buscando respuesta predefinida: " . $e->getMessage(), 'respuestas-predefinidas');
            return null;
        }
    }
    
    /**
     * Guardar respuesta para futuro uso
     * @param string $texto Texto original
     * @param array $respuesta Respuesta de IA
     * @param string $servicio Servicio médico
     */
    public static function guardarRespuesta($texto, $respuesta, $servicio)
    {
        try {
            \common\models\RespuestaPredefinida::guardar($texto, $respuesta, $servicio);
            Yii::info("Respuesta predefinida guardada para: " . substr($texto, 0, 50), 'respuestas-predefinidas');
        } catch (\Exception $e) {
            Yii::error("Error guardando respuesta predefinida: " . $e->getMessage(), 'respuestas-predefinidas');
        }
    }
    
    /**
     * Incrementar contador de usos
     * @param int $id ID de la respuesta predefinida
     */
    public static function incrementarUsos($id)
    {
        try {
            $respuesta = \common\models\RespuestaPredefinida::findOne($id);
            if ($respuesta) {
                $respuesta->usos = ($respuesta->usos ?? 0) + 1;
                $respuesta->save(false);
            }
        } catch (\Exception $e) {
            Yii::error("Error incrementando usos: " . $e->getMessage(), 'respuestas-predefinidas');
        }
    }
}

