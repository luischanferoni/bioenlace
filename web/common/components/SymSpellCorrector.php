<?php

namespace common\components;

use Yii;
use common\helpers\TextoMedicoHelper;
use common\models\DiccionarioOrtografico;
use common\components\ConsultaLogger;

/**
 * Implementación de SymSpell para corrección ortográfica rápida
 * Basado en el algoritmo SymSpell para corrección de distancia de edición
 */
class SymSpellCorrector
{
    private $dictionary = [];
    private $maxEditDistance = 2;
    private $prefixLength = 7;
    private $countThreshold = 1;
    private const CONFIANZA_MINIMA_APLICAR = 1.0; // Solo aplicar correcciones con 100% de confianza
    
    /**
     * Constructor
     * @param int $maxEditDistance Distancia máxima de edición (default: 2)
     * @param int $prefixLength Longitud del prefijo (default: 7)
     * @param int $countThreshold Umbral mínimo de frecuencia (default: 1)
     */
    public function __construct($maxEditDistance = 2, $prefixLength = 7, $countThreshold = 1)
    {
        $this->maxEditDistance = $maxEditDistance;
        $this->prefixLength = $prefixLength;
        $this->countThreshold = $countThreshold;
        
        // Cargar diccionario médico al inicializar
        $this->loadMedicalDictionary();
    }
    
    /**
     * Cargar diccionario médico desde base de datos y archivos
     */
    private function loadMedicalDictionary()
    {
        $inicio = microtime(true);
        
        // 1. Cargar abreviaturas médicas de la BD
        $this->loadAbreviaturasFromDB();
        
        // 2. Cargar términos médicos y errores desde catálogos
        $this->loadMedicalTerms();
        $this->loadCommonErrors();
        
        $tiempo = microtime(true) - $inicio;
        
        $logger = ConsultaLogger::obtenerInstancia();
        if ($logger) {
            $logger->registrar(
                'PROCESAMIENTO',
                'Carga de diccionario',
                'Diccionario cargado exitosamente',
                [
                    'metodo' => 'SymSpellCorrector::loadMedicalDictionary',
                    'terminos_cargados' => count($this->dictionary),
                    'tiempo_carga' => round($tiempo, 3) . 's'
                ]
            );
        } else {
            \Yii::info("Diccionario médico cargado en {$tiempo}s. Términos: " . count($this->dictionary), 'symspell');
        }
    }
    
    /**
     * Cargar abreviaturas desde la base de datos
     * NOTA: Las abreviaturas NO se cargan en el diccionario de SymSpell
     * porque deben expandirse ANTES de la corrección ortográfica.
     * SymSpell solo debe trabajar con errores ortográficos, no con abreviaturas.
     */
    private function loadAbreviaturasFromDB()
    {
        // Las abreviaturas ya se expanden en ProcesadorTextoMedico antes de llegar a SymSpell
        // No necesitamos cargarlas aquí
        // Este método se mantiene por compatibilidad pero no hace nada
        return;
    }
    
    /**
     * Cargar términos médicos comunes
     */
    private function loadMedicalTerms()
    {
        $terminos = DiccionarioOrtografico::find()
            ->where(['activo' => 1, 'tipo' => DiccionarioOrtografico::TIPO_TERMINO])
            ->all();

        foreach ($terminos as $termino) {
            $this->dictionary[$termino->termino] = [
                'frequency' => $termino->frecuencia ?? 500,
                'expansion' => $termino->termino,
                'type' => $termino->categoria,
                'category' => $termino->categoria,
                'specialty' => $termino->especialidad,
            ];
        }
    }

    /**
     * Cargar errores ortográficos comunes
     */
    private function loadCommonErrors()
    {
        $errores = DiccionarioOrtografico::find()
            ->where(['activo' => 1, 'tipo' => DiccionarioOrtografico::TIPO_ERROR])
            ->all();

        foreach ($errores as $error) {
            $this->dictionary[$error->termino] = [
                'frequency' => $error->frecuencia ?? 300,
                'expansion' => $error->correccion ?? $error->termino,
                'type' => 'error_ortografico',
                'category' => $error->categoria,
                'specialty' => $error->especialidad,
            ];
        }
    }
    
    /**
     * Corregir una palabra usando SymSpell
     * @param string $word Palabra a corregir
     * @param string $context Contexto opcional
     * @return array Resultado de la corrección
     */
    public function correct($word, $context = '')
    {
        $inicio = microtime(true);
        $logger = ConsultaLogger::obtenerInstancia();
        
        // Limpiar la palabra
        $cleanWord = $this->cleanWord($word);
        
        if ($logger) {
            $logger->registrar(
                'PROCESAMIENTO',
                $word,
                $cleanWord,
                [
                    'metodo' => 'SymSpellCorrector::correct - Limpieza',
                    'cambios' => $word !== $cleanWord ? $word . ' → ' . $cleanWord : 'Sin cambios'
                ]
            );
        }
        
        if (empty($cleanWord)) {
            if ($logger) {
                $logger->registrar(
                    'PROCESAMIENTO',
                    'Palabra vacía',
                    'Palabra vacía después de limpieza',
                    ['metodo' => 'SymSpellCorrector::correct - Palabra vacía']
                );
            }
            return [
                'original' => $word,
                'corrected' => $word,
                'confidence' => 1.0,
                'method' => 'no_change',
                'suggestions' => [],
                'processing_time' => microtime(true) - $inicio
            ];
        }
        
        // Si es una palabra de parada, no corregir
        if ($this->isStopWord($cleanWord)) {
            if ($logger) {
                $logger->registrar(
                    'PROCESAMIENTO',
                    $cleanWord,
                    'Palabra de parada detectada',
                    [
                        'metodo' => 'SymSpellCorrector::correct - Stop Word',
                        'cambios' => 'No se corregirá (stop word)'
                    ]
                );
            }
            return [
                'original' => $word,
                'corrected' => $word,
                'confidence' => 1.0,
                'method' => 'stop_word',
                'suggestions' => [],
                'processing_time' => microtime(true) - $inicio
            ];
        }
        
        // 1. Verificar si la palabra ya está en el diccionario (exacta)
        if (isset($this->dictionary[$cleanWord])) {
            $entry = $this->dictionary[$cleanWord];
            
            if ($logger) {
                $logger->registrar(
                    'PROCESAMIENTO',
                    $cleanWord,
                    $entry['expansion'],
                    [
                        'metodo' => 'SymSpellCorrector::correct - Coincidencia exacta',
                        'cambios' => $cleanWord . ' → ' . $entry['expansion'],
                        'tipo' => $entry['type'] ?? 'desconocido',
                        'categoria' => $entry['category'] ?? 'desconocida'
                    ]
                );
            }
            
            return [
                'original' => $word,
                'corrected' => $entry['expansion'],
                'confidence' => 1.0,
                'method' => 'exact_match',
                'suggestions' => [],
                'processing_time' => microtime(true) - $inicio,
                'metadata' => $entry
            ];
        }
        
        // 2. Verificar si es una posible abreviatura (mayúsculas, corta)
        // Las abreviaturas SOLO deben coincidir exactamente, NO por distancia de edición
        $esPosibleAbreviatura = $this->esPosibleAbreviatura($cleanWord);
        
        if ($esPosibleAbreviatura) {
            // Para abreviaturas, NO buscar por distancia de edición
            // Si no hay coincidencia exacta, no es una abreviatura válida
            if ($logger) {
                $logger->registrar(
                    'PROCESAMIENTO',
                    $cleanWord,
                    'Posible abreviatura sin coincidencia exacta',
                    [
                        'metodo' => 'SymSpellCorrector::correct - Abreviatura no encontrada',
                        'cambios' => 'No se corregirá (solo coincidencias exactas para abreviaturas)'
                    ]
                );
            }
            
            return [
                'original' => $word,
                'corrected' => $word,
                'confidence' => 0.0,
                'method' => 'no_suggestions',
                'suggestions' => [],
                'processing_time' => microtime(true) - $inicio
            ];
        }
        
        // 3. Buscar correcciones por distancia de edición (solo para palabras normales, NO abreviaturas)
        $suggestions = $this->lookup($cleanWord);
        
        if (empty($suggestions)) {
            if ($logger) {
                $logger->registrar(
                    'PROCESAMIENTO',
                    $cleanWord,
                    'No se encontraron sugerencias',
                    ['metodo' => 'SymSpellCorrector::correct - Sin sugerencias']
                );
            }
            return [
                'original' => $word,
                'corrected' => $word,
                'confidence' => 0.0,
                'method' => 'no_suggestions',
                'suggestions' => [],
                'processing_time' => microtime(true) - $inicio
            ];
        }
        
        // 3. Seleccionar la mejor sugerencia
        $bestSuggestion = $suggestions[0];
        $confidence = $this->calculateConfidence($bestSuggestion, $context);
        
        if ($logger) {
            $cambiosDetallados = [];
            $requiereValidacion = false;
            $razonesValidacion = [];
            
            // Construir lista de sugerencias encontradas para el log
            $sugerenciasTexto = [];
            foreach (array_slice($suggestions, 0, 5) as $i => $sug) {
                $sugerenciasTexto[] = ($i + 1) . '. ' . $sug['term'] . ' (dist: ' . $sug['distance'] . ', freq: ' . $sug['count'] . ')';
                $cambiosDetallados[] = ($i + 1) . '. ' . $cleanWord . ' → ' . $sug['term'] . ' (distancia: ' . $sug['distance'] . ', frecuencia: ' . $sug['count'] . ')';
                
                // Detectar casos problemáticos
                if ($sug['distance'] > 2) {
                    $requiereValidacion = true;
                    $razonesValidacion[] = 'Distancia alta (' . $sug['distance'] . ')';
                }
                if ($sug['count'] < 50) {
                    $requiereValidacion = true;
                    $razonesValidacion[] = 'Frecuencia baja (' . $sug['count'] . ')';
                }
            }
            
            // Verificar confianza general
            if ($confidence < 0.7) {
                $requiereValidacion = true;
                $razonesValidacion[] = 'Confianza baja (' . round($confidence, 2) . ')';
            }
            
            // Verificar palabras muy cortas
            if (strlen($cleanWord) < 4) {
                $requiereValidacion = true;
                $razonesValidacion[] = 'Palabra muy corta (' . strlen($cleanWord) . ' caracteres)';
            }
            
            // Mostrar las sugerencias encontradas en el log
            $salidaLog = "Sugerencias encontradas (" . count($suggestions) . "):\n" . implode("\n", $sugerenciasTexto);
            
            $logger->registrar(
                'PROCESAMIENTO',
                $cleanWord,
                $salidaLog,
                [
                    'metodo' => 'SymSpellCorrector::correct - Búsqueda SymSpell',
                    'sugerencias_encontradas' => count($suggestions),
                    'mejor_sugerencia' => $bestSuggestion['term']
                ]
            );
            
            $logger->registrar(
                'PROCESAMIENTO',
                $cleanWord,
                $bestSuggestion['term'],
                [
                    'metodo' => 'SymSpellCorrector::correct - Sugerencia SymSpell',
                    'cambios' => $cleanWord . ' → ' . $bestSuggestion['term'],
                    'confianza' => $confidence,
                    'requiere_validacion' => $requiereValidacion,
                    'razones_validacion' => $razonesValidacion,
                    'cambios_detallados' => $cambiosDetallados
                ]
            );
        }
        
        return [
            'original' => $word,
            'corrected' => $bestSuggestion['term'],
            'confidence' => $confidence,
            'method' => 'symspell_suggestion',
            'suggestions' => array_slice($suggestions, 0, 3), // Top 3
            'processing_time' => microtime(true) - $inicio,
            'metadata' => $bestSuggestion
        ];
    }
    
    /**
     * Buscar sugerencias para una palabra
     * @param string $word
     * @return array
     */
    private function lookup($word)
    {
        $suggestions = [];
        $wordLength = strlen($word);
        
        // Si la palabra es muy corta, no buscar
        if ($wordLength < 2) {
            return $suggestions;
        }
        
        // Si es una palabra de parada, no buscar
        if ($this->isStopWord($word)) {
            \Yii::info("Palabra de parada detectada: '{$word}' - no se buscarán sugerencias", 'symspell');
            return $suggestions;
        }
        
        // Buscar en el diccionario
        // EXCLUIR abreviaturas de la búsqueda por distancia de edición
        // Las abreviaturas solo deben coincidir exactamente
        foreach ($this->dictionary as $dictWord => $entry) {
            // NO buscar sugerencias para abreviaturas por distancia de edición
            // Las abreviaturas deben coincidir exactamente (ya se verificó arriba)
            if (isset($entry['type']) && $entry['type'] === 'abreviatura') {
                continue; // Saltar abreviaturas en búsqueda por distancia
            }
            
            $distance = $this->levenshteinDistance($word, $dictWord);
            
            if ($distance <= $this->maxEditDistance) {
                $suggestions[] = [
                    'term' => $entry['expansion'],
                    'distance' => $distance,
                    'frequency' => $entry['frequency'],
                    'count' => $entry['frequency'], // Alias para compatibilidad
                    'type' => $entry['type'] ?? 'unknown',
                    'category' => $entry['category'] ?? 'unknown',
                    'original_length' => strlen($word),
                    'term_length' => strlen($entry['expansion'])
                ];
            }
        }
        
        // Ordenar por distancia y frecuencia
        usort($suggestions, function($a, $b) {
            if ($a['distance'] == $b['distance']) {
                return $b['frequency'] - $a['frequency'];
            }
            return $a['distance'] - $b['distance'];
        });
        
        return $suggestions;
    }
    
    /**
     * Detectar si una palabra es una posible abreviatura médica
     * Las abreviaturas típicamente son cortas (2-5 caracteres) y en mayúsculas
     * 
     * @param string $word
     * @return bool
     */
    private function esPosibleAbreviatura($word)
    {
        $length = strlen($word);
        
        // Abreviaturas típicamente tienen 2-5 caracteres
        if ($length < 2 || $length > 5) {
            return false;
        }
        
        // Si la palabra está completamente en mayúsculas, es probablemente una abreviatura
        if (strtoupper($word) === $word && $word !== strtolower($word)) {
            return true;
        }
        
        // Si tiene mayoría de mayúsculas, también podría ser abreviatura
        $uppercaseCount = 0;
        for ($i = 0; $i < $length; $i++) {
            if (ctype_upper($word[$i])) {
                $uppercaseCount++;
            }
        }
        
        // Si más del 50% son mayúsculas, probablemente es abreviatura
        if ($uppercaseCount > ($length / 2)) {
            return true;
        }
        
        return false;
    }

    /**
     * Lista de palabras de parada que nunca deben ser expandidas
     * Carga desde la base de datos, con cache estático para mejor rendimiento
     * @return array
     */
    private static function getStopWords()
    {
        // Cache estático para evitar múltiples consultas a la BD
        static $stopWordsCache = null;
        
        if ($stopWordsCache !== null) {
            return $stopWordsCache;
        }
        
        try {
            // Cargar stop words desde la base de datos
            $stopWords = DiccionarioOrtografico::find()
                ->select('termino')
                ->where([
                    'tipo' => DiccionarioOrtografico::TIPO_STOPWORD,
                    'activo' => 1
                ])
                ->asArray()
                ->column();
            
            // Si no hay stop words en la BD, usar lista por defecto como fallback
            if (empty($stopWords)) {
                \Yii::warning("No se encontraron stop words en la BD, usando lista por defecto", 'symspell');
                $stopWords = [
                    // Artículos
                    'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas',
                    // Preposiciones
                    'de', 'del', 'a', 'al', 'en', 'con', 'por', 'para', 'sin', 'sobre', 'bajo', 'entre',
                    // Conjunciones
                    'y', 'o', 'pero', 'aunque', 'mientras', 'cuando', 'donde', 'como',
                    // Pronombres
                    'que', 'quien', 'cual', 'cuyo', 'cuya', 'cuyos', 'cuyas',
                    // Adverbios comunes
                    'muy', 'más', 'menos', 'bien', 'mal', 'siempre', 'nunca',
                    // Números
                    'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez',
                    // Palabras médicas muy comunes que no son abreviaturas
                    'paciente', 'doctor', 'medico', 'médico', 'consulta', 'tratamiento',
                    // Palabras muy cortas que pueden causar conflictos
                    'es', 'se', 'le', 'te', 'me', 'nos', 'os', 'lo', 'la', 'le', 'les'
                ];
            }
            
            // Convertir a array indexado numéricamente y normalizar a minúsculas
            $stopWordsCache = array_map('strtolower', array_values($stopWords));
            
            return $stopWordsCache;
            
        } catch (\Exception $e) {
            \Yii::error("Error cargando stop words desde BD: " . $e->getMessage(), 'symspell');
            
            // Fallback a lista por defecto en caso de error
            $stopWordsCache = [
                'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas',
                'de', 'del', 'a', 'al', 'en', 'con', 'por', 'para', 'sin', 'sobre', 'bajo', 'entre',
                'y', 'o', 'pero', 'aunque', 'mientras', 'cuando', 'donde', 'como',
                'que', 'quien', 'cual', 'cuyo', 'cuya', 'cuyos', 'cuyas',
                'muy', 'más', 'menos', 'bien', 'mal', 'siempre', 'nunca',
                'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez',
                'paciente', 'doctor', 'medico', 'médico', 'consulta', 'tratamiento',
                'es', 'se', 'le', 'te', 'me', 'nos', 'os', 'lo', 'la', 'le', 'les'
            ];
            
            return $stopWordsCache;
        }
    }

    /**
     * Verificar si una palabra es una palabra de parada
     * @param string $word
     * @return bool
     */
    private function isStopWord($word)
    {
        $stopWords = self::getStopWords();
        $cleanWord = strtolower(trim($word));
        
        return in_array($cleanWord, $stopWords);
    }

    /**
     * Calcular distancia de Levenshtein entre dos palabras
     * @param string $str1
     * @param string $str2
     * @return int
     */
    private function levenshteinDistance($str1, $str2)
    {
        // Si es una palabra de parada, no calcular distancia
        if ($this->isStopWord($str1)) {
            return 999; // Distancia muy alta para evitar expansión
        }
        
        $str1 = strtolower($str1);
        $str2 = strtolower($str2);
        
        $len1 = strlen($str1);
        $len2 = strlen($str2);
        
        // Si la diferencia de longitud es muy grande, no es una buena coincidencia
        if (abs($len1 - $len2) > 5) {
            return 999; // Distancia muy alta
        }
        
        // Si la palabra es muy corta (menos de 3 caracteres), ser más estricto
        if ($len1 < 3) {
            return 999; // No expandir palabras muy cortas
        }
        
        if ($len1 == 0) return $len2;
        if ($len2 == 0) return $len1;
        
        $matrix = [];
        
        for ($i = 0; $i <= $len1; $i++) {
            $matrix[$i][0] = $i;
        }
        
        for ($j = 0; $j <= $len2; $j++) {
            $matrix[0][$j] = $j;
        }
        
        for ($i = 1; $i <= $len1; $i++) {
            for ($j = 1; $j <= $len2; $j++) {
                $cost = ($str1[$i-1] == $str2[$j-1]) ? 0 : 1;
                $matrix[$i][$j] = min(
                    $matrix[$i-1][$j] + 1,
                    $matrix[$i][$j-1] + 1,
                    $matrix[$i-1][$j-1] + $cost
                );
            }
        }
        
        return $matrix[$len1][$len2];
    }
    
    /**
     * Calcular confianza de la corrección
     * @param array $suggestion
     * @param string $context
     * @return float
     */
    private function calculateConfidence($suggestion, $context = '')
    {
        // Si es una corrección de tipo 'error' del diccionario con alta frecuencia, confianza máxima
        if (isset($suggestion['type']) && $suggestion['type'] === 'error_ortografico' && 
            $suggestion['distance'] <= 1 && $suggestion['frequency'] >= 100) {
            return 1.0;
        }
        
        $baseConfidence = 0.5;
        
        // Ajustar por distancia (distance 0 = exacta, distance 1 = muy cercana, distance 2 = aceptable)
        // Para distance 1 con alta frecuencia, dar confianza alta
        if ($suggestion['distance'] == 0) {
            $distanceBonus = 0.5; // Coincidencia exacta
        } elseif ($suggestion['distance'] == 1 && $suggestion['frequency'] >= 500) {
            $distanceBonus = 0.4; // Muy cercana con alta frecuencia
        } elseif ($suggestion['distance'] == 1) {
            $distanceBonus = 0.3; // Muy cercana
        } else {
            $distanceBonus = max(0, (3 - $suggestion['distance']) / 3) * 0.2; // Aceptable
        }
        
        // Ajustar por frecuencia (más peso para frecuencias altas)
        if ($suggestion['frequency'] >= 1000) {
            $frequencyBonus = 0.3; // Frecuencia muy alta
        } elseif ($suggestion['frequency'] >= 500) {
            $frequencyBonus = 0.2; // Frecuencia alta
        } elseif ($suggestion['frequency'] >= 100) {
            $frequencyBonus = 0.1; // Frecuencia media
        } else {
            $frequencyBonus = min(0.05, $suggestion['frequency'] / 2000); // Frecuencia baja
        }
        
        // Ajustar por contexto médico
        $contextBonus = 0;
        if (!empty($context)) {
            // Usar cache estático para evitar consultas repetidas a la BD
            static $terminosContextoCache = null;
            
            if ($terminosContextoCache === null) {
                $terminosContextoCache = \common\models\DiccionarioOrtografico::find()
                    ->select(['termino'])
                    ->where(['activo' => 1])
                    ->asArray()
                    ->column();
            }
            
            $terminoEncontrado = null;
            foreach ($terminosContextoCache as $term) {
                if (stripos($context, $term) !== false) {
                    $contextBonus += 0.1;
                    $terminoEncontrado = $term;
                    break;
                }
            }
            
            // Log del uso de contexto médico si se aplicó bonus
            if ($contextBonus > 0) {
                $logger = ConsultaLogger::obtenerInstancia();
                if ($logger) {
                    $logger->registrar(
                        'PROCESAMIENTO',
                        'Contexto médico detectado',
                        "Bonus de contexto aplicado: +{$contextBonus}",
                        [
                            'metodo' => 'SymSpellCorrector::calculateConfidence',
                            'termino_encontrado' => $terminoEncontrado,
                            'contexto' => substr($context, 0, 50) . '...'
                        ]
                    );
                }
            }
        }
        
        // Penalizar palabras muy cortas
        $lengthPenalty = 0;
        if (strlen($suggestion['term']) < 4) {
            $lengthPenalty = -0.3;
        }
        
        // Penalizar si la diferencia de longitud es muy grande (indica posible error)
        $lengthDifferencePenalty = 0;
        if (isset($suggestion['original_length']) && isset($suggestion['term_length'])) {
            $lengthDiff = abs($suggestion['original_length'] - $suggestion['term_length']);
            if ($lengthDiff > 5) {
                $lengthDifferencePenalty = -0.3; // Penalización mayor para diferencias grandes
            } elseif ($lengthDiff > 3) {
                $lengthDifferencePenalty = -0.1; // Penalización menor
            }
        }
        
        $confidence = $baseConfidence + $distanceBonus + $frequencyBonus + $contextBonus + $lengthPenalty + $lengthDifferencePenalty;
        
        return min(1.0, max(0.0, $confidence));
    }
    
    /**
     * Limpiar palabra para procesamiento
     * @param string $word
     * @return string
     */
    private function cleanWord($word)
    {
        return TextoMedicoHelper::limpiarPalabra($word);
    }
    
    /**
     * Procesar texto completo
     * @param string $text
     * @param string $context
     * @return array
     */
    public function correctText($text, $context = '')
    {
        $inicio = microtime(true);
        $textoNormalizado = TextoMedicoHelper::limpiarTexto($text);
        $words = preg_split('/\s+/', $textoNormalizado);
        $corrections = [];
        $correctedText = $text;
        $changes = [];
        $cambiosProblematicos = [];
        $wordsWithoutSuggestions = []; // NUEVO: Array para palabras sin sugerencias con contexto
        $requiereValidacion = false;
        $contador = 0;
        
        $logger = ConsultaLogger::obtenerInstancia();

        $logger->registrar(
            'PROCESAMIENTO',
            json_encode($words),
            'Iniciando procesamiento de ' . count($words) . ' palabras',
            [
                'metodo' => 'SymSpellCorrector::correctText',
                'total_palabras' => count($words)
            ]
        );

        foreach ($words as $word) {
            $contador++;
            try {
                $logger->registrar(
                    'PROCESAMIENTO',
                    $word,
                    "Palabra a procesar ({$contador}/" . count($words) . ")",
                    ['metodo' => 'SymSpellCorrector::correctText']
                );
                
                $result = $this->correct($word, $context);
                
                if ($result['corrected'] !== $result['original']) {
                    // Verificar confianza mínima para aplicar corrección
                    $confianzaSuficiente = $result['confidence'] >= self::CONFIANZA_MINIMA_APLICAR;
                    
                    // Verificar si requiere validación (confianza baja o otros factores)
                    $esProblematico = $result['confidence'] < 0.7 || 
                                     (isset($result['metadata']['distance']) && $result['metadata']['distance'] > 2) ||
                                     (isset($result['metadata']['count']) && $result['metadata']['count'] < 50) ||
                                     strlen($result['original']) < 4;

                    // Si la confianza no es suficiente, marcar como problemático y NO aplicar
                    if (!$confianzaSuficiente) {
                        $cambiosProblematicos[] = [
                            'original' => $result['original'],
                            'corrected' => $result['corrected'],
                            'confidence' => $result['confidence'],
                            'method' => $result['method'],
                            'metadata' => $result['metadata'] ?? [],
                            'razon_rechazo' => 'Confianza insuficiente (' . $result['confidence'] . ' < ' . self::CONFIANZA_MINIMA_APLICAR . ')'
                        ];
                        $requiereValidacion = true;
                        
                        $logger->registrar(
                            'PROCESAMIENTO',
                            $word,
                            'Corrección rechazada por confianza insuficiente',
                            [
                                'metodo' => 'SymSpellCorrector::correct',
                                'confianza' => $result['confidence'],
                                'umbral_minimo' => self::CONFIANZA_MINIMA_APLICAR,
                                'cambios_propuestos' => $result['original'] . ' → ' . $result['corrected'],
                                'aplicado' => false
                            ]
                        );
                    } else {
                        // Confianza suficiente, aplicar la corrección
                        if ($esProblematico) {
                            $cambiosProblematicos[] = [
                                'original' => $result['original'],
                                'corrected' => $result['corrected'],
                                'confidence' => $result['confidence'],
                                'method' => $result['method'],
                                'metadata' => $result['metadata'] ?? []
                            ];
                            $requiereValidacion = true;
                        }

                        $logger->registrar(
                            'PROCESAMIENTO',
                            $word,
                            $result['corrected'],
                            [
                                'metodo' => 'SymSpellCorrector::correct',
                                'confianza' => $result['confidence'],
                                'cambios' => $result['original'] . ' → ' . $result['corrected'],
                                'requiere_validacion' => $esProblematico,
                                'aplicado' => true
                            ]
                        );

                        $corrections[] = $result;
                        $correctedText = str_replace($result['original'], $result['corrected'], $correctedText);
                        $changes[] = [
                            'original' => $result['original'],
                            'corrected' => $result['corrected'],
                            'confidence' => $result['confidence'],
                            'method' => $result['method']
                        ];
                    }
                } else {
                    // NUEVO: Capturar palabras sin sugerencias con su contexto
                    if ($result['method'] === 'no_suggestions' && $result['confidence'] == 0.0) {
                        // Extraer la oración completa donde aparece la palabra
                        $sentence = $this->extractSentence($word, $text);
                        
                        $wordsWithoutSuggestions[] = [
                            'word' => $result['original'],
                            'clean_word' => $this->cleanWord($result['original']),
                            'sentence' => $sentence, // Contexto de la oración completa
                            'position' => strpos($text, $word)
                        ];
                        
                        $logger->registrar(
                            'PROCESAMIENTO',
                            $word,
                            'Palabra sin sugerencias detectada',
                            [
                                'metodo' => 'SymSpellCorrector::correct',
                                'confianza' => $result['confidence'],
                                'contexto_oracion' => $sentence
                            ]
                        );
                    } else {
                        // Registrar palabras que no cambiaron
                        $logger->registrar(
                            'PROCESAMIENTO',
                            $word,
                            'Sin cambios',
                            [
                                'metodo' => 'SymSpellCorrector::correct',
                                'confianza' => $result['confidence'],
                                'cambios' => 'Sin cambios'
                            ]
                        );
                    }
                }
            } catch (\Exception $e) {
                // Log del error pero continuar procesando
                $logger->registrar(
                    'ERROR',
                    $word,
                    'Error procesando palabra: ' . $e->getMessage(),
                    [
                        'metodo' => 'SymSpellCorrector::correctText',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]
                );
                
                // Continuar con la siguiente palabra sin interrumpir el proceso
                continue;
            }
        }
        
        // Log del resumen del procesamiento
        $logger->registrar(
            'PROCESAMIENTO',
            'Resumen',
            "Procesamiento completado: {$contador} palabras procesadas, " . count($corrections) . " correcciones aplicadas",
            [
                'metodo' => 'SymSpellCorrector::correctText',
                'palabras_procesadas' => $contador,
                'total_palabras' => count($words),
                'correcciones_aplicadas' => count($corrections),
                'cambios_problematicos' => count($cambiosProblematicos),
                'requiere_validacion' => $requiereValidacion
            ]
        );
        
        return [
            'original_text' => $text,
            'corrected_text' => $correctedText,
            'corrections' => $corrections,
            'changes' => $changes,
            'words_without_suggestions' => $wordsWithoutSuggestions, // NUEVO
            'total_changes' => count($changes),
            'total_words_without_suggestions' => count($wordsWithoutSuggestions), // NUEVO
            'processing_time' => microtime(true) - $inicio,
            'confidence_avg' => count($corrections) > 0 ? array_sum(array_column($corrections, 'confidence')) / count($corrections) : 0,
            'requiere_validacion' => $requiereValidacion,
            'cambios_problematicos' => $cambiosProblematicos,
            'total_problematicos' => count($cambiosProblematicos)
        ];
    }
    
    /**
     * Extraer la oración completa donde aparece una palabra
     * @param string $word
     * @param string $text
     * @return string
     */
    private function extractSentence($word, $text)
    {
        // Buscar la palabra como palabra completa (con límites de palabra)
        $pattern = '/\b' . preg_quote($word, '/') . '\b/';
        if (!preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return $text; // Si no se encuentra, devolver todo el texto
        }
        
        $pos = $matches[0][1]; // Posición de la palabra encontrada
        
        // Buscar inicio de oración hacia atrás
        $start = $pos;
        while ($start > 0 && !in_array($text[$start], ['.', '!', '?', "\n"])) {
            $start--;
        }
        if ($start > 0) $start++; // Incluir el delimitador
        
        // Buscar fin de oración hacia adelante
        $end = $pos + strlen($word);
        while ($end < strlen($text) && !in_array($text[$end], ['.', '!', '?', "\n"])) {
            $end++;
        }
        
        $sentence = trim(substr($text, $start, $end - $start));
        
        // Si la oración es muy corta, expandir con palabras adyacentes
        if (strlen($sentence) < 50) {
            // Agregar 3 palabras antes y después
            $words = preg_split('/\s+/', $text);
            $wordIndex = false;
            $cleanWord = $this->cleanWord($word);
            
            foreach ($words as $idx => $w) {
                $cleanW = $this->cleanWord($w);
                if ($cleanW === $cleanWord) {
                    $wordIndex = $idx;
                    break;
                }
            }
            
            if ($wordIndex !== false) {
                $startIndex = max(0, $wordIndex - 3);
                $endIndex = min(count($words), $wordIndex + 4);
                $sentence = implode(' ', array_slice($words, $startIndex, $endIndex - $startIndex));
            }
        }
        
        return $sentence;
    }
    
    /**
     * Obtener estadísticas del diccionario
     * @return array
     */
    public function getDictionaryStats()
    {
        $stats = [
            'total_terms' => count($this->dictionary),
            'by_type' => [],
            'by_category' => [],
            'avg_frequency' => 0
        ];
        
        $frequencies = [];
        
        foreach ($this->dictionary as $entry) {
            $type = $entry['type'] ?? 'unknown';
            $category = $entry['category'] ?? 'unknown';
            
            $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;
            $stats['by_category'][$category] = ($stats['by_category'][$category] ?? 0) + 1;
            $frequencies[] = $entry['frequency'];
        }
        
        $stats['avg_frequency'] = count($frequencies) > 0 ? array_sum($frequencies) / count($frequencies) : 0;
        
        return $stats;
    }
}
