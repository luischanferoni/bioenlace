<?php

namespace common\components;

use Yii;

/**
 * Deduplicador de requests para evitar llamadas duplicadas
 */
class RequestDeduplicator
{
    private static $requestCache = [];
    private const CACHE_TTL = 300;
    // Umbral reducido de 0.95 a 0.85 para mayor agresividad en deduplicación (reduce costos)
    private const SIMILITUD_MINIMA = 0.85;
    
    public static function buscarSimilar($prompt, $tipo = 'general')
    {
        $cacheKey = self::generarCacheKey($prompt, $tipo);
        
        if (isset(self::$requestCache[$cacheKey])) {
            $cached = self::$requestCache[$cacheKey];
            if (time() - $cached['timestamp'] < self::CACHE_TTL) {
                return $cached['response'];
            } else {
                unset(self::$requestCache[$cacheKey]);
            }
        }
        
        foreach (self::$requestCache as $key => $cached) {
            if ($cached['tipo'] !== $tipo) {
                continue;
            }
            
            if (time() - $cached['timestamp'] >= self::CACHE_TTL) {
                unset(self::$requestCache[$key]);
                continue;
            }
            
            $similitud = self::calcularSimilitud($prompt, $cached['prompt']);
            if ($similitud >= self::SIMILITUD_MINIMA) {
                return $cached['response'];
            }
        }
        
        return null;
    }
    
    public static function guardar($prompt, $response, $tipo = 'general')
    {
        $cacheKey = self::generarCacheKey($prompt, $tipo);
        
        self::$requestCache[$cacheKey] = [
            'prompt' => $prompt,
            'response' => $response,
            'tipo' => $tipo,
            'timestamp' => time()
        ];
        
        if (count(self::$requestCache) > 1000) {
            self::limpiarExpirados();
        }
    }
    
    private static function generarCacheKey($prompt, $tipo)
    {
        return $tipo . '_' . md5($prompt);
    }
    
    private static function calcularSimilitud($prompt1, $prompt2)
    {
        $p1 = strtolower(trim($prompt1));
        $p2 = strtolower(trim($prompt2));
        
        if ($p1 === $p2) {
            return 1.0;
        }
        
        $maxLen = max(strlen($p1), strlen($p2));
        if ($maxLen === 0) {
            return 1.0;
        }
        
        $distancia = levenshtein($p1, $p2);
        $similitudLevenshtein = 1 - ($distancia / $maxLen);
        
        $palabras1 = array_filter(explode(' ', $p1));
        $palabras2 = array_filter(explode(' ', $p2));
        
        $palabrasComunes = count(array_intersect($palabras1, $palabras2));
        $totalPalabras = count(array_unique(array_merge($palabras1, $palabras2)));
        
        $similitudPalabras = $totalPalabras > 0 ? ($palabrasComunes / $totalPalabras) : 0;
        
        return ($similitudLevenshtein * 0.3) + ($similitudPalabras * 0.7);
    }
    
    private static function limpiarExpirados()
    {
        $ahora = time();
        foreach (self::$requestCache as $key => $cached) {
            if ($ahora - $cached['timestamp'] >= self::CACHE_TTL) {
                unset(self::$requestCache[$key]);
            }
        }
    }
}

/**
 * Gestor de modelos para carga/descarga dinámica
 * Optimiza memoria descargando modelos no utilizados
 */
class ModelManager
{
    private static $modelosCargados = [];
    private static $ultimoUso = [];
    private static $maxModelosMemoria = 3; // Máximo de modelos en memoria simultáneamente
    
    /**
     * Registrar uso de un modelo
     * @param string $modeloId Identificador del modelo
     * @param string $tipo Tipo de modelo (stt, text-generation, embedding, etc.)
     */
    public static function registrarUso($modeloId, $tipo = 'general')
    {
        $key = $tipo . '_' . $modeloId;
        self::$ultimoUso[$key] = time();
        
        if (!isset(self::$modelosCargados[$key])) {
            self::$modelosCargados[$key] = [
                'modelo_id' => $modeloId,
                'tipo' => $tipo,
                'cargado_en' => time(),
                'usos' => 0
            ];
        }
        
        self::$modelosCargados[$key]['usos']++;
        
        // Limpiar modelos no utilizados si excedemos el límite
        self::limpiarModelosNoUtilizados();
    }
    
    /**
     * Verificar si un modelo debe estar cargado
     * @param string $modeloId
     * @param string $tipo
     * @return bool
     */
    public static function debeEstarCargado($modeloId, $tipo = 'general')
    {
        $key = $tipo . '_' . $modeloId;
        
        // Si ya está cargado, mantenerlo
        if (isset(self::$modelosCargados[$key])) {
            return true;
        }
        
        // Si tenemos espacio, cargarlo
        if (count(self::$modelosCargados) < self::$maxModelosMemoria) {
            return true;
        }
        
        // Si no hay espacio, verificar si es prioritario
        return self::esPrioritario($modeloId, $tipo);
    }
    
    /**
     * Verificar si un modelo es prioritario
     * @param string $modeloId
     * @param string $tipo
     * @return bool
     */
    private static function esPrioritario($modeloId, $tipo)
    {
        // Modelos prioritarios que siempre deben estar cargados
        $prioritarios = [
            'stt_economico' => true, // STT económico es el más usado
            'text-generation_llama3.1:8b' => true, // Modelo principal de texto
        ];
        
        $key = $tipo . '_' . $modeloId;
        return isset($prioritarios[$key]);
    }
    
    /**
     * Limpiar modelos no utilizados
     */
    private static function limpiarModelosNoUtilizados()
    {
        $maxModelos = Yii::$app->params['max_modelos_memoria'] ?? self::$maxModelosMemoria;
        
        if (count(self::$modelosCargados) <= $maxModelos) {
            return;
        }
        
        // Ordenar por último uso (menos recientes primero)
        uasort(self::$ultimoUso, function($a, $b) {
            return $a - $b;
        });
        
        // Eliminar los menos recientes hasta estar bajo el límite
        $eliminados = 0;
        $necesitaEliminar = count(self::$modelosCargados) - $maxModelos;
        
        foreach (self::$ultimoUso as $key => $timestamp) {
            if ($eliminados >= $necesitaEliminar) {
                break;
            }
            
            // No eliminar modelos prioritarios
            if (self::esPrioritario(self::$modelosCargados[$key]['modelo_id'], self::$modelosCargados[$key]['tipo'])) {
                continue;
            }
            
            // Eliminar modelo
            unset(self::$modelosCargados[$key]);
            unset(self::$ultimoUso[$key]);
            $eliminados++;
            
            \Yii::info("Modelo descargado de memoria: {$key}", 'model-manager');
        }
    }
    
    /**
     * Obtener estadísticas de modelos
     * @return array
     */
    public static function obtenerEstadisticas()
    {
        return [
            'modelos_cargados' => count(self::$modelosCargados),
            'max_modelos' => self::$maxModelosMemoria,
            'modelos' => self::$modelosCargados
        ];
    }
    
    /**
     * Forzar descarga de un modelo específico
     * @param string $modeloId
     * @param string $tipo
     */
    public static function forzarDescarga($modeloId, $tipo = 'general')
    {
        $key = $tipo . '_' . $modeloId;
        if (isset(self::$modelosCargados[$key])) {
            unset(self::$modelosCargados[$key]);
            unset(self::$ultimoUso[$key]);
            \Yii::info("Modelo forzado a descargar: {$key}", 'model-manager');
        }
    }
}

/**
 * Procesador CPU para tareas simples que no requieren GPU
 * Optimiza costos procesando tareas básicas localmente
 */
class CPUProcessor
{
    /**
     * Determinar si una tarea puede procesarse con CPU
     * @param string $tipo Tipo de tarea
     * @param array $parametros Parámetros de la tarea
     * @return bool
     */
    public static function puedeProcesarConCPU($tipo, $parametros = [])
    {
        $tareasCPU = [
            'limpieza_texto',
            'normalizacion',
            'tokenizacion',
            'deteccion_idioma',
            'expansion_abreviaturas_simple',
            'correccion_ortografica_basica',
            'extraccion_palabras_clave',
            'filtrado_stopwords'
        ];
        
        return in_array($tipo, $tareasCPU);
    }
    
    /**
     * Procesar tarea con CPU
     * @param string $tipo Tipo de tarea
     * @param mixed $input Entrada a procesar
     * @param array $opciones Opciones adicionales
     * @return mixed Resultado del procesamiento
     */
    public static function procesar($tipo, $input, $opciones = [])
    {
        if (!self::puedeProcesarConCPU($tipo)) {
            throw new \Exception("Tarea '{$tipo}' no puede procesarse con CPU");
        }
        
        switch ($tipo) {
            case 'limpieza_texto':
                return self::limpiarTexto($input);
            case 'normalizacion':
                return self::normalizarTexto($input);
            case 'tokenizacion':
                return self::tokenizar($input);
            case 'deteccion_idioma':
                return self::detectarIdioma($input);
            case 'expansion_abreviaturas_simple':
                return self::expandirAbreviaturasSimple($input, $opciones);
            case 'correccion_ortografica_basica':
                return self::corregirOrtografiaBasica($input);
            case 'extraccion_palabras_clave':
                return self::extraerPalabrasClave($input);
            case 'filtrado_stopwords':
                return self::filtrarStopwords($input);
            default:
                throw new \Exception("Tipo de tarea desconocido: {$tipo}");
        }
    }
    
    /**
     * Limpiar texto (CPU) - Usa TextoMedicoHelper optimizado
     */
    private static function limpiarTexto($texto)
    {
        if (class_exists('\common\helpers\TextoMedicoHelper')) {
            return \common\helpers\TextoMedicoHelper::limpiarTexto($texto);
        }
        
        // Fallback básico
        $texto = trim($texto);
        $texto = preg_replace('/\s+/', ' ', $texto);
        return $texto;
    }
    
    /**
     * Normalizar texto (CPU) - Usa TextoMedicoHelper optimizado
     */
    private static function normalizarTexto($texto)
    {
        if (class_exists('\common\helpers\TextoMedicoHelper')) {
            return \common\helpers\TextoMedicoHelper::normalizarTexto($texto);
        }
        
        // Fallback básico
        $texto = mb_strtolower($texto, 'UTF-8');
        $texto = preg_replace('/[^\p{L}\p{N}\s]/u', '', $texto);
        return $texto;
    }
    
    /**
     * Tokenizar texto (CPU) - Usa TextoMedicoHelper optimizado
     */
    private static function tokenizar($texto)
    {
        if (class_exists('\common\helpers\TextoMedicoHelper')) {
            return \common\helpers\TextoMedicoHelper::tokenizar($texto, false);
        }
        
        // Fallback básico
        $tokens = preg_split('/\s+/', trim($texto));
        return array_filter($tokens, function($token) {
            return strlen($token) > 0;
        });
    }
    
    /**
     * Detectar idioma (CPU) - Usa TextoMedicoHelper optimizado
     */
    private static function detectarIdioma($texto)
    {
        if (class_exists('\common\helpers\TextoMedicoHelper')) {
            return \common\helpers\TextoMedicoHelper::detectarIdioma($texto);
        }
        
        // Fallback básico
        $textoLower = mb_strtolower($texto, 'UTF-8');
        
        $palabrasEspanol = ['el', 'la', 'de', 'que', 'y', 'a', 'en', 'un', 'es', 'se', 'no', 'te', 'lo', 'le', 'da', 'su', 'por', 'son', 'con', 'para', 'al', 'del', 'los', 'las'];
        $palabrasIngles = ['the', 'be', 'to', 'of', 'and', 'a', 'in', 'that', 'have', 'i', 'it', 'for', 'not', 'on', 'with', 'he', 'as', 'you', 'do', 'at'];
        
        $countEspanol = 0;
        $countIngles = 0;
        
        foreach ($palabrasEspanol as $palabra) {
            if (stripos($textoLower, $palabra) !== false) {
                $countEspanol++;
            }
        }
        
        foreach ($palabrasIngles as $palabra) {
            if (stripos($textoLower, $palabra) !== false) {
                $countIngles++;
            }
        }
        
        return $countEspanol > $countIngles ? 'es' : 'en';
    }
    
    /**
     * Expandir abreviaturas simples (CPU, sin IA)
     */
    private static function expandirAbreviaturasSimple($texto, $opciones = [])
    {
        // Usar diccionario de abreviaturas de BD si está disponible
        if (class_exists('\common\models\AbreviaturasMedicas')) {
            try {
                $abreviaturas = \common\models\AbreviaturasMedicas::find()
                    ->where(['activo' => 1])
                    ->all();
                
                foreach ($abreviaturas as $abrev) {
                    $patron = '/\b' . preg_quote($abrev->abreviatura, '/') . '\b/i';
                    $texto = preg_replace($patron, $abrev->expansion, $texto);
                }
            } catch (\Exception $e) {
                \Yii::warning("Error expandiendo abreviaturas: " . $e->getMessage(), 'cpu-processor');
            }
        }
        
        return $texto;
    }
    
    /**
     * Corrección ortográfica básica (CPU, sin IA)
     */
    private static function corregirOrtografiaBasica($texto)
    {
        // Correcciones básicas comunes
        $correcciones = [
            '/\bpa\b/i' => 'para',
            '/\bq\b/i' => 'que',
            '/\bxa\b/i' => 'para',
            '/\bdx\b/i' => 'diagnóstico',
        ];
        
        foreach ($correcciones as $patron => $reemplazo) {
            $texto = preg_replace($patron, $reemplazo, $texto);
        }
        
        return $texto;
    }
    
    /**
     * Extraer palabras clave (CPU) - Usa TextoMedicoHelper optimizado
     */
    private static function extraerPalabrasClave($texto, $limite = 10)
    {
        if (class_exists('\common\helpers\TextoMedicoHelper')) {
            return \common\helpers\TextoMedicoHelper::extraerPalabrasClave($texto, $limite);
        }
        
        // Fallback básico
        $tokens = self::tokenizar($texto);
        $frecuencias = array_count_values($tokens);
        arsort($frecuencias);
        
        return array_slice(array_keys($frecuencias), 0, $limite);
    }
    
    /**
     * Filtrar stopwords (CPU)
     */
    private static function filtrarStopwords($texto)
    {
        $stopwords = ['el', 'la', 'de', 'que', 'y', 'a', 'en', 'un', 'es', 'se', 'no', 'te', 'lo', 'le', 'da', 'su', 'por', 'son', 'con', 'para', 'al', 'del', 'los', 'las', 'le', 'les'];
        $tokens = self::tokenizar($texto);
        
        $filtrados = array_filter($tokens, function($token) use ($stopwords) {
            return !in_array(mb_strtolower($token, 'UTF-8'), $stopwords);
        });
        
        return implode(' ', $filtrados);
    }
}

