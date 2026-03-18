<?php

namespace common\components\Text;

use Yii;
use common\helpers\TextoMedicoHelper;
use common\models\DiccionarioOrtografico;
use common\components\Logging\ConsultaLogger;

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

    public function __construct($maxEditDistance = 2, $prefixLength = 7, $countThreshold = 1)
    {
        $this->maxEditDistance = $maxEditDistance;
        $this->prefixLength = $prefixLength;
        $this->countThreshold = $countThreshold;
        $this->loadMedicalDictionary();
    }

    private function loadMedicalDictionary()
    {
        $inicio = microtime(true);

        $this->loadAbreviaturasFromDB();
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

    private function loadAbreviaturasFromDB()
    {
        return;
    }

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

    public function correct($word, $context = '')
    {
        $inicio = microtime(true);
        $logger = ConsultaLogger::obtenerInstancia();

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

        $esPosibleAbreviatura = $this->esPosibleAbreviatura($cleanWord);

        if ($esPosibleAbreviatura) {
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

        $bestSuggestion = $suggestions[0];
        $confidence = $this->calculateConfidence($bestSuggestion, $context);

        if ($logger) {
            $cambiosDetallados = [];
            $requiereValidacion = false;
            $razonesValidacion = [];

            $sugerenciasTexto = [];
            foreach (array_slice($suggestions, 0, 5) as $i => $sug) {
                $sugerenciasTexto[] = ($i + 1) . '. ' . $sug['term'] . ' (dist: ' . $sug['distance'] . ', freq: ' . $sug['count'] . ')';
                $cambiosDetallados[] = ($i + 1) . '. ' . $cleanWord . ' → ' . $sug['term'] . ' (distancia: ' . $sug['distance'] . ', frecuencia: ' . $sug['count'] . ')';

                if ($sug['distance'] > 2) {
                    $requiereValidacion = true;
                    $razonesValidacion[] = 'Distancia alta (' . $sug['distance'] . ')';
                }
                if ($sug['count'] < 50) {
                    $requiereValidacion = true;
                    $razonesValidacion[] = 'Frecuencia baja (' . $sug['count'] . ')';
                }
            }

            if ($confidence < 0.7) {
                $requiereValidacion = true;
                $razonesValidacion[] = 'Confianza baja (' . round($confidence, 2) . ')';
            }

            if (strlen($cleanWord) < 4) {
                $requiereValidacion = true;
                $razonesValidacion[] = 'Palabra muy corta (' . strlen($cleanWord) . ' caracteres)';
            }

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
            'suggestions' => array_slice($suggestions, 0, 3),
            'processing_time' => microtime(true) - $inicio,
            'metadata' => $bestSuggestion
        ];
    }

    private function lookup($word)
    {
        $suggestions = [];
        $wordLength = strlen($word);

        if ($wordLength < 2) {
            return $suggestions;
        }

        if ($this->isStopWord($word)) {
            \Yii::info("Palabra de parada detectada: '{$word}' - no se buscarán sugerencias", 'symspell');
            return $suggestions;
        }

        foreach ($this->dictionary as $dictWord => $entry) {
            if (isset($entry['type']) && $entry['type'] === 'abreviatura') {
                continue;
            }

            $distance = $this->levenshteinDistance($word, $dictWord);

            if ($distance <= $this->maxEditDistance) {
                $suggestions[] = [
                    'term' => $entry['expansion'],
                    'distance' => $distance,
                    'frequency' => $entry['frequency'],
                    'count' => $entry['frequency'],
                    'type' => $entry['type'] ?? 'unknown',
                    'category' => $entry['category'] ?? 'unknown',
                    'original_length' => strlen($word),
                    'term_length' => strlen($entry['expansion'])
                ];
            }
        }

        usort($suggestions, function ($a, $b) {
            if ($a['distance'] == $b['distance']) {
                return $b['frequency'] - $a['frequency'];
            }
            return $a['distance'] - $b['distance'];
        });

        return $suggestions;
    }

    private function esPosibleAbreviatura($word)
    {
        $length = strlen($word);

        if ($length < 2 || $length > 5) {
            return false;
        }

        if (strtoupper($word) === $word && $word !== strtolower($word)) {
            return true;
        }

        $uppercaseCount = 0;
        for ($i = 0; $i < $length; $i++) {
            if (ctype_upper($word[$i])) {
                $uppercaseCount++;
            }
        }

        if ($uppercaseCount > ($length / 2)) {
            return true;
        }

        return false;
    }

    private static function getStopWords()
    {
        static $stopWordsCache = null;

        if ($stopWordsCache !== null) {
            return $stopWordsCache;
        }

        try {
            $stopWords = DiccionarioOrtografico::find()
                ->select('termino')
                ->where([
                    'tipo' => DiccionarioOrtografico::TIPO_STOPWORD,
                    'activo' => 1
                ])
                ->asArray()
                ->column();

            if (empty($stopWords)) {
                \Yii::warning("No se encontraron stop words en la BD, usando lista por defecto", 'symspell');
                $stopWords = [
                    'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas',
                    'de', 'del', 'a', 'al', 'en', 'con', 'por', 'para', 'sin', 'sobre', 'bajo', 'entre',
                    'y', 'o', 'pero', 'aunque', 'mientras', 'cuando', 'donde', 'como',
                    'que', 'quien', 'cual', 'cuyo', 'cuya', 'cuyos', 'cuyas',
                    'muy', 'más', 'menos', 'bien', 'mal', 'siempre', 'nunca',
                    'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez',
                    'paciente', 'doctor', 'medico', 'médico', 'consulta', 'tratamiento',
                    'es', 'se', 'le', 'te', 'me', 'nos', 'os', 'lo', 'la', 'le', 'les'
                ];
            }

            $stopWordsCache = array_map('strtolower', array_values($stopWords));

            return $stopWordsCache;
        } catch (\Exception $e) {
            \Yii::error("Error cargando stop words desde BD: " . $e->getMessage(), 'symspell');

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

    private function isStopWord($word)
    {
        $stopWords = self::getStopWords();
        $cleanWord = strtolower(trim($word));

        return in_array($cleanWord, $stopWords, true);
    }

    private function levenshteinDistance($str1, $str2)
    {
        if ($this->isStopWord($str1)) {
            return 999;
        }

        $str1 = strtolower($str1);
        $str2 = strtolower($str2);

        $len1 = strlen($str1);
        $len2 = strlen($str2);

        if (abs($len1 - $len2) > 5) {
            return 999;
        }

        if ($len1 < 3) {
            return 999;
        }

        if ($len1 == 0) {
            return $len2;
        }
        if ($len2 == 0) {
            return $len1;
        }

        $matrix = [];

        for ($i = 0; $i <= $len1; $i++) {
            $matrix[$i][0] = $i;
        }

        for ($j = 0; $j <= $len2; $j++) {
            $matrix[0][$j] = $j;
        }

        for ($i = 1; $i <= $len1; $i++) {
            for ($j = 1; $j <= $len2; $j++) {
                $cost = ($str1[$i - 1] == $str2[$j - 1]) ? 0 : 1;
                $matrix[$i][$j] = min(
                    $matrix[$i - 1][$j] + 1,
                    $matrix[$i][$j - 1] + 1,
                    $matrix[$i - 1][$j - 1] + $cost
                );
            }
        }

        return $matrix[$len1][$len2];
    }

    private function calculateConfidence($suggestion, $context = '')
    {
        if (
            isset($suggestion['type']) && $suggestion['type'] === 'error_ortografico' &&
            $suggestion['distance'] <= 1 && $suggestion['frequency'] >= 100
        ) {
            return 1.0;
        }

        $baseConfidence = 0.5;

        if ($suggestion['distance'] == 0) {
            $distanceBonus = 0.5;
        } elseif ($suggestion['distance'] == 1 && $suggestion['frequency'] >= 500) {
            $distanceBonus = 0.4;
        } elseif ($suggestion['distance'] == 1) {
            $distanceBonus = 0.3;
        } else {
            $distanceBonus = max(0, (3 - $suggestion['distance']) / 3) * 0.2;
        }

        if ($suggestion['frequency'] >= 1000) {
            $frequencyBonus = 0.3;
        } elseif ($suggestion['frequency'] >= 500) {
            $frequencyBonus = 0.2;
        } elseif ($suggestion['frequency'] >= 100) {
            $frequencyBonus = 0.1;
        } else {
            $frequencyBonus = min(0.05, $suggestion['frequency'] / 2000);
        }

        $contextBonus = 0;
        if (!empty($context)) {
            static $terminosContextoCache = null;

            if ($terminosContextoCache === null) {
                $terminosContextoCache = DiccionarioOrtografico::find()
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

        $lengthPenalty = 0;
        if (strlen($suggestion['term']) < 4) {
            $lengthPenalty = -0.3;
        }

        $lengthDifferencePenalty = 0;
        if (isset($suggestion['original_length']) && isset($suggestion['term_length'])) {
            $lengthDiff = abs($suggestion['original_length'] - $suggestion['term_length']);
            if ($lengthDiff > 5) {
                $lengthDifferencePenalty = -0.3;
            } elseif ($lengthDiff > 3) {
                $lengthDifferencePenalty = -0.1;
            }
        }

        $confidence = $baseConfidence + $distanceBonus + $frequencyBonus + $contextBonus + $lengthPenalty + $lengthDifferencePenalty;

        return min(1.0, max(0.0, $confidence));
    }

    private function cleanWord($word)
    {
        return TextoMedicoHelper::limpiarPalabra($word);
    }

    public function correctText($text, $context = '')
    {
        $inicio = microtime(true);
        $textoNormalizado = TextoMedicoHelper::limpiarTexto($text);
        $words = preg_split('/\s+/', $textoNormalizado);
        $corrections = [];
        $correctedText = $text;
        $changes = [];
        $cambiosProblematicos = [];
        $wordsWithoutSuggestions = [];
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
                    $confianzaSuficiente = $result['confidence'] >= self::CONFIANZA_MINIMA_APLICAR;

                    $esProblematico = $result['confidence'] < 0.7 ||
                        (isset($result['metadata']['distance']) && $result['metadata']['distance'] > 2) ||
                        (isset($result['metadata']['count']) && $result['metadata']['count'] < 50) ||
                        strlen($result['original']) < 4;

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
                    if ($result['method'] === 'no_suggestions' && $result['confidence'] == 0.0) {
                        $sentence = $this->extractSentence($word, $text);

                        $wordsWithoutSuggestions[] = [
                            'word' => $result['original'],
                            'clean_word' => $this->cleanWord($result['original']),
                            'sentence' => $sentence,
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
                continue;
            }
        }

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
            'words_without_suggestions' => $wordsWithoutSuggestions,
            'total_changes' => count($changes),
            'total_words_without_suggestions' => count($wordsWithoutSuggestions),
            'processing_time' => microtime(true) - $inicio,
            'confidence_avg' => count($corrections) > 0 ? array_sum(array_column($corrections, 'confidence')) / count($corrections) : 0,
            'requiere_validacion' => $requiereValidacion,
            'cambios_problematicos' => $cambiosProblematicos,
            'total_problematicos' => count($cambiosProblematicos)
        ];
    }

    private function extractSentence($word, $text)
    {
        $pattern = '/\b' . preg_quote($word, '/') . '\b/';
        if (!preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return $text;
        }

        $pos = $matches[0][1];

        $start = $pos;
        while ($start > 0 && !in_array($text[$start], ['.', '!', '?', "\n"], true)) {
            $start--;
        }
        if ($start > 0) {
            $start++;
        }

        $end = $pos + strlen($word);
        while ($end < strlen($text) && !in_array($text[$end], ['.', '!', '?', "\n"], true)) {
            $end++;
        }

        $sentence = trim(substr($text, $start, $end - $start));

        if (strlen($sentence) < 50) {
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

