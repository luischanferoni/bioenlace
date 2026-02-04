<?php

namespace common\components;

use Yii;

/**
 * Extracción de parámetros específicos del mensaje según el intent
 * 
 * Extrae parámetros estructurados del mensaje del usuario
 * según la configuración del intent.
 */
class ParameterExtractor
{
    /**
     * Extraer parámetros del mensaje según el intent
     * @param string $message Mensaje del usuario
     * @param string $intent Intent detectado
     * @param array|null $context Contexto de conversación (opcional)
     * @return array Parámetros extraídos
     */
    public static function extract($message, $intent, $context = null)
    {
        $intentConfig = self::getIntentConfig($intent);
        
        if (!$intentConfig) {
            return [];
        }
        
        $parameters = [];
        $messageLower = mb_strtolower($message, 'UTF-8');
        
        // Extraer cada parámetro según el intent
        foreach ($intentConfig['required_params'] as $param) {
            $value = self::extractParameter($message, $messageLower, $param, $intent);
            if ($value !== null) {
                $parameters[$param] = $value;
            }
        }
        
        foreach ($intentConfig['optional_params'] as $param) {
            $value = self::extractParameter($message, $messageLower, $param, $intent);
            if ($value !== null) {
                $parameters[$param] = $value;
            }
        }
        
        // Resolver referencias del paciente si está habilitado
        if (isset($intentConfig['patient_profile']['resolve_references']) && 
            $intentConfig['patient_profile']['resolve_references']) {
            $parameters = self::resolvePatientReferences($message, $messageLower, $parameters, $intent, $context);
        }
        
        return $parameters;
    }
    
    /**
     * Extraer un parámetro específico del mensaje
     * @param string $message Mensaje original
     * @param string $messageLower Mensaje en minúsculas
     * @param string $param Nombre del parámetro
     * @param string $intent Intent actual
     * @return mixed Valor del parámetro o null
     */
    private static function extractParameter($message, $messageLower, $param, $intent)
    {
        switch ($param) {
            case 'servicio':
                return self::extractServicio($messageLower);
            
            case 'fecha':
                return self::extractFecha($messageLower);
            
            case 'hora':
            case 'horario':
                return self::extractHora($messageLower);
            
            case 'profesional':
            case 'id_rr_hh':
                return self::extractProfesional($message, $messageLower);
            
            case 'efector':
            case 'id_efector':
                return self::extractEfector($message, $messageLower);
            
            case 'medicamento':
                return self::extractMedicamento($message, $messageLower);
            
            case 'sintoma':
                return self::extractSintoma($message);
            
            case 'turno_id':
                return self::extractTurnoId($messageLower);
            
            case 'tipo_practica':
                return self::extractTipoPractica($messageLower);
            
            case 'ubicacion':
                return self::extractUbicacion($message);
            
            default:
                // Para otros parámetros, intentar extracción genérica
                return self::extractGeneric($message, $messageLower, $param);
        }
    }
    
    /**
     * Extraer servicio del mensaje.
     * Usa Servicio::extractFromQuery (dinámico desde BD) y devuelve el nombre del servicio.
     */
    private static function extractServicio($messageLower)
    {
        $idServicio = \common\models\Servicio::extractFromQuery($messageLower);
        if ($idServicio === null) {
            return null;
        }
        $servicio = \common\models\Servicio::findOne($idServicio);
        return $servicio ? $servicio->nombre : null;
    }
    
    /**
     * Extraer fecha del mensaje
     */
    private static function extractFecha($messageLower)
    {
        // Patrones de fecha
        $patterns = [
            '/\b(hoy|mañana|pasado\s+mañana)\b/i',
            '/\b(lunes|martes|miércoles|miercoles|jueves|viernes|sábado|sabado|domingo)\b/i',
            '/(\d{1,2})\s*[\/\-]\s*(\d{1,2})(\s*[\/\-]\s*(\d{2,4}))?/',
            '/(\d{1,2})\s+(de\s+)?(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $messageLower, $matches)) {
                return self::normalizeFecha($matches[0]);
            }
        }
        
        return null;
    }
    
    /**
     * Extraer hora del mensaje
     */
    private static function extractHora($messageLower)
    {
        // Patrones de hora
        $patterns = [
            '/(\d{1,2})\s*:\s*(\d{2})\s*(am|pm)?/i',
            '/(\d{1,2})\s+(de\s+la\s+)?(mañana|tarde|noche)/i',
            '/\b(a\s+las?|a\s+la\s+hora\s+de)\s+(\d{1,2})/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $messageLower, $matches)) {
                return self::normalizeHora($matches[0]);
            }
        }
        
        return null;
    }
    
    /**
     * Extraer profesional del mensaje
     */
    private static function extractProfesional($message, $messageLower)
    {
        // Buscar nombres propios (Dr., Dra., Doctor, etc.)
        if (preg_match('/\b(Dr\.?|Dra\.?|Doctor|Doctora)\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)*)/u', $message, $matches)) {
            return $matches[2];
        }
        
        return null;
    }
    
    /**
     * Extraer efector del mensaje
     */
    private static function extractEfector($message, $messageLower)
    {
        // Buscar referencias a centros, hospitales, etc.
        if (preg_match('/\b(centro|hospital|cl[ií]nica)\s+(de\s+)?([A-ZÁÉÍÓÚÑ][a-záéíóúñ\s]+)/u', $message, $matches)) {
            return $matches[3];
        }
        
        return null;
    }
    
    /**
     * Extraer medicamento del mensaje
     */
    private static function extractMedicamento($message, $messageLower)
    {
        $medicamentos = [
            'ibuprofeno', 'paracetamol', 'aspirina', 'amoxicilina',
            'omeprazol', 'metformina', 'losartan', 'atenolol'
        ];
        
        foreach ($medicamentos as $med) {
            if (stripos($messageLower, $med) !== false) {
                return $med;
            }
        }
        
        return null;
    }
    
    /**
     * Extraer síntoma del mensaje
     */
    private static function extractSintoma($message)
    {
        // Retornar el mensaje completo como síntoma si no se puede extraer específicamente
        // La IA puede procesarlo mejor
        return $message;
    }
    
    /**
     * Extraer ID de turno
     */
    private static function extractTurnoId($messageLower)
    {
        if (preg_match('/turno\s+(n[úu]mero\s+)?(\d+)/i', $messageLower, $matches)) {
            return (int)$matches[2];
        }
        
        return null;
    }
    
    /**
     * Extraer tipo de práctica
     */
    private static function extractTipoPractica($messageLower)
    {
        $tipos = [
            'laboratorio' => ['laboratorio', 'análisis', 'analisis', 'sangre'],
            'imagenes' => ['imágenes', 'imagenes', 'rayos', 'ecografía', 'ecografia'],
            'nutricion' => ['nutrición', 'nutricion']
        ];
        
        foreach ($tipos as $tipo => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($messageLower, $keyword) !== false) {
                    return strtoupper($tipo);
                }
            }
        }
        
        return null;
    }
    
    /**
     * Extraer ubicación
     */
    private static function extractUbicacion($message)
    {
        // Buscar nombres de lugares, barrios, etc.
        if (preg_match('/\b(en|por|cerca\s+de)\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ\s]+)/u', $message, $matches)) {
            return $matches[2];
        }
        
        return null;
    }
    
    /**
     * Extracción genérica para parámetros no definidos
     */
    private static function extractGeneric($message, $messageLower, $param)
    {
        // Intentar buscar el parámetro mencionado explícitamente
        $pattern = '/\b' . preg_quote($param, '/') . '\s*[:=]?\s*([^\s,\.]+)/i';
        if (preg_match($pattern, $messageLower, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Resolver referencias del paciente
     */
    private static function resolvePatientReferences($message, $messageLower, $parameters, $intent, $context)
    {
        $references = require Yii::getAlias('@common/config/chatbot/patient-references.php');
        
        // Buscar referencias en el mensaje
        foreach ($references as $referenceText => $referenceConfig) {
            if (stripos($messageLower, $referenceText) !== false) {
                // Resolver referencia usando PatientProfile (se implementará después)
                // Por ahora retornar los parámetros sin modificar
                Yii::info("ParameterExtractor: Referencia detectada '{$referenceText}' para intent '{$intent}'", 'parameter-extractor');
                // TODO: Integrar con PatientProfile cuando esté implementado
                break;
            }
        }
        
        return $parameters;
    }
    
    /**
     * Normalizar nombre de servicio (fallback cuando ya se tiene un nombre extraído).
     * Si el valor coincide con un servicio en BD, devuelve su nombre oficial.
     */
    private static function normalizeServicio($servicio)
    {
        $id = \common\models\Servicio::findByName($servicio);
        if ($id !== null) {
            $s = \common\models\Servicio::findOne($id);
            return $s ? $s->nombre : strtoupper($servicio);
        }
        return strtoupper($servicio);
    }
    
    /**
     * Normalizar fecha
     */
    private static function normalizeFecha($fecha)
    {
        $fechaLower = mb_strtolower($fecha, 'UTF-8');
        
        if ($fechaLower === 'hoy') {
            return date('Y-m-d');
        }
        
        if ($fechaLower === 'mañana') {
            return date('Y-m-d', strtotime('+1 day'));
        }
        
        if ($fechaLower === 'pasado mañana') {
            return date('Y-m-d', strtotime('+2 days'));
        }
        
        // Intentar parsear fecha
        $parsed = strtotime($fecha);
        if ($parsed !== false) {
            return date('Y-m-d', $parsed);
        }
        
        return $fecha; // Retornar original si no se puede normalizar
    }
    
    /**
     * Normalizar hora
     */
    private static function normalizeHora($hora)
    {
        // Intentar parsear hora
        $parsed = strtotime($hora);
        if ($parsed !== false) {
            return date('H:i', $parsed);
        }
        
        return $hora; // Retornar original si no se puede normalizar
    }
    
    /**
     * Obtener configuración de intent
     */
    private static function getIntentConfig($intent)
    {
        $intentParams = require Yii::getAlias('@common/config/chatbot/intent-parameters.php');
        return $intentParams[$intent] ?? null;
    }
}
