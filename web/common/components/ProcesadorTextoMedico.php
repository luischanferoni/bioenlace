<?php

namespace common\components;

use Yii;
use common\helpers\TextoMedicoHelper;
use common\models\AbreviaturasMedicas;
use common\models\TerminoContextoMedico;
use common\components\ConsultaLogger;

/**
 * Componente para procesar texto médico expandiendo abreviaturas
 * y preparando el texto para análisis con IA
 */
class ProcesadorTextoMedico
{
    private const CONTEXTO_MEDICO_UMBRAL = 2.0;

    private static array $contextTermsCache = [];
    private static array $contextBigramsCache = [];

    /**
     * Expandir abreviaturas médicas en el texto desde la base de datos
     * @param string $textoOriginal
     * @param string $especialidad
     * @param int $idRrHh Opcional: ID del médico
     * @return array
     */
    public static function expandirAbreviaturas($textoOriginal, $especialidad = null, $idRrHh = null)
    {
        $inicio = microtime(true);
        
        // Paso 1: Limpiar y normalizar el texto (usar CPUProcessor si está disponible)
        $usarCPU = Yii::$app->params['usar_cpu_tareas_simples'] ?? true;
        if ($usarCPU && CPUProcessor::puedeProcesarConCPU('limpieza_texto')) {
            $textoLimpio = CPUProcessor::procesar('limpieza_texto', $textoOriginal);
        } else {
            $textoLimpio = TextoMedicoHelper::limpiarTexto($textoOriginal);
        }
        
        $logger = ConsultaLogger::obtenerInstancia();
        if ($logger) {
            $cambiosDetallados = [];
            if ($textoOriginal !== $textoLimpio) {
                $cambiosDetallados[] = "Texto limpiado y normalizado";
            }
            
            $logger->registrar(
                'PROCESAMIENTO',
                $textoOriginal,
                $textoLimpio,
                [
                    'metodo' => 'ProcesadorTextoMedico::expandirAbreviaturas - Limpieza',
                    'cambios_detallados' => $cambiosDetallados
                ]
            );
        }
        
        // Paso 2: Expandir abreviaturas médicas (intentar CPU primero para abreviaturas simples)
        $usarCPU = Yii::$app->params['usar_cpu_tareas_simples'] ?? true;
        $textoExpandido = $textoLimpio;
        $abreviaturasEncontradas = [];
        
        if ($usarCPU && CPUProcessor::puedeProcesarConCPU('expansion_abreviaturas_simple')) {
            $textoExpandido = CPUProcessor::procesar('expansion_abreviaturas_simple', $textoLimpio, ['especialidad' => $especialidad]);
            if ($textoExpandido !== $textoLimpio) {
                \Yii::info("Abreviaturas expandidas con CPU", 'procesador-texto');
            }
        }
        
        // Si hay médico, usar lógica específica del médico (puede requerir BD)
        if ($idRrHh) {
            $resultadoExpansion = AbreviaturasMedicas::expandirAbreviaturasConMedico($textoExpandido, $especialidad, $idRrHh);
            $textoExpandido = $resultadoExpansion['texto_procesado'];
            $abreviaturasEncontradas = $resultadoExpansion['abreviaturas_encontradas'];
            
            if ($logger) {
                $abreviaturasDetalladas = [];
                foreach ($abreviaturasEncontradas as $abreviatura) {
                    $abreviaturasDetalladas[] = $abreviatura['abreviatura'] . ' → ' . $abreviatura['expansion'];
                }
                
                $logger->registrar(
                    'PROCESAMIENTO',
                    $textoLimpio,
                    $textoExpandido,
                    [
                        'metodo' => 'ProcesadorTextoMedico::expandirAbreviaturas - Con Médico',
                        'abreviaturas_encontradas' => $abreviaturasEncontradas,
                        'abreviaturas_detalladas' => $abreviaturasDetalladas
                    ]
                );
            }
        } else {
            $textoExpandido = AbreviaturasMedicas::expandirAbreviaturas($textoLimpio, $especialidad);
            $abreviaturasEncontradas = self::detectarAbreviaturasUsadas($textoLimpio, $especialidad);
            
            if ($logger) {
                $abreviaturasDetalladas = [];
                if (is_array($abreviaturasEncontradas)) {
                    foreach ($abreviaturasEncontradas as $abreviatura) {
                        $abreviaturasDetalladas[] = $abreviatura['abreviatura'] . ' → ' . $abreviatura['expansion'];
                    }
                }
                
                $logger->registrar(
                    'PROCESAMIENTO',
                    $textoLimpio,
                    $textoExpandido,
                    [
                        'metodo' => 'ProcesadorTextoMedico::expandirAbreviaturas - General',
                        'abreviaturas_encontradas' => $abreviaturasEncontradas,
                        'abreviaturas_detalladas' => $abreviaturasDetalladas
                    ]
                );
            }
        }
        
        // Paso 3: Detectar abreviaturas no reconocidas
        $abreviaturasNoReconocidas = self::detectarAbreviaturasNoReconocidas($textoLimpio, $especialidad);
        
        if ($logger && !empty($abreviaturasNoReconocidas)) {
            $abreviaturasDetalladas = [];
            foreach ($abreviaturasNoReconocidas as $abreviatura) {
                $abreviaturasDetalladas[] = $abreviatura['abreviatura'] . ' (tipo: ' . $abreviatura['tipo'] . ')';
            }
            
            $logger->registrar(
                'PROCESAMIENTO',
                $textoLimpio,
                'Abreviaturas no reconocidas detectadas',
                [
                    'metodo' => 'ProcesadorTextoMedico::detectarAbreviaturasNoReconocidas',
                    'abreviaturas_no_reconocidas' => count($abreviaturasNoReconocidas),
                    'abreviaturas_detalladas' => $abreviaturasDetalladas
                ]
            );
        }
        
        // Paso 4: Reportar abreviaturas no reconocidas
        self::reportarAbreviaturasNoReconocidas($abreviaturasNoReconocidas, $textoOriginal, $especialidad);
        
        // Paso 5: Incrementar frecuencia de uso
        self::actualizarFrecuenciaUso($abreviaturasEncontradas, $idRrHh);
        
        // Paso 6: Generar metadatos del procesamiento
        $metadatos = self::generarMetadatos($textoOriginal, $textoExpandido, $abreviaturasEncontradas, $abreviaturasNoReconocidas);
        
        $tiempoProcesamiento = microtime(true) - $inicio;
        
        
        return [
            'texto_original' => $textoOriginal,
            'texto_procesado' => $textoExpandido,
            'abreviaturas_encontradas' => $abreviaturasEncontradas,
            'metadatos' => $metadatos,
            'tiempo_procesamiento' => $tiempoProcesamiento,
            'mejora_legibilidad' => self::calcularMejoraLegibilidad($textoOriginal, $textoExpandido)
        ];
    }

    /**
     * Limpiar y normalizar texto médico
     * @param string $texto
     * @return string
     */
    /**
     * Detectar qué abreviaturas se usaron en el texto
     * @param string $texto
     * @param string $especialidad
     * @return array
     */
    private static function detectarAbreviaturasUsadas($texto, $especialidad = null)
    {
        $abreviaturas = AbreviaturasMedicas::getAbreviaturasPorEspecialidad($especialidad);
        $encontradas = [];
        
        foreach ($abreviaturas as $abreviatura) {
            $patron = '/\b' . preg_quote($abreviatura->abreviatura, '/') . '\b/i';
            if (preg_match($patron, $texto)) {
                $encontradas[] = [
                    'abreviatura' => $abreviatura->abreviatura,
                    'expansion' => $abreviatura->expansion_completa,
                    'categoria' => $abreviatura->categoria,
                    'contexto' => $abreviatura->contexto
                ];
            }
        }
        
        return $encontradas;
    }

    /**
     * Actualizar frecuencia de uso de abreviaturas
     * @param array $abreviaturasEncontradas
     * @param int $idRrHh Opcional: ID del médico
     */
    private static function actualizarFrecuenciaUso($abreviaturasEncontradas, $idRrHh = null)
    {
        foreach ($abreviaturasEncontradas as $abreviatura) {
            // Incrementar frecuencia general
            AbreviaturasMedicas::incrementarFrecuencia($abreviatura['abreviatura']);
            
            // Si hay médico, registrar uso específico
            if ($idRrHh) {
                $abreviaturaModel = AbreviaturasMedicas::buscarAbreviatura($abreviatura['abreviatura']);
                if ($abreviaturaModel) {
                    \common\models\AbreviaturasMedicos::registrarUso($abreviaturaModel->id, $idRrHh);
                }
            }
        }
    }


    /**
     * Calcular mejora en legibilidad del texto
     * @param string $textoOriginal
     * @param string $textoExpandido
     * @return array
     */
    private static function calcularMejoraLegibilidad($textoOriginal, $textoExpandido)
    {
        $palabrasOriginales = str_word_count($textoOriginal);
        $palabrasExpandidas = str_word_count($textoExpandido);
        
        $incrementoPalabras = $palabrasExpandidas - $palabrasOriginales;
        $porcentajeIncremento = $palabrasOriginales > 0 ? ($incrementoPalabras / $palabrasOriginales) * 100 : 0;
        
        return [
            'palabras_originales' => $palabrasOriginales,
            'palabras_expandidas' => $palabrasExpandidas,
            'incremento_palabras' => $incrementoPalabras,
            'porcentaje_incremento' => round($porcentajeIncremento, 2)
        ];
    }

    /**
     * Procesar texto para análisis con IA (método principal)
     * Sistema simplificado: Corrección completa con IA local (Llama 3.1 70B Instruct) -> Expansión de abreviaturas
     * @param string $textoConsulta
     * @param string $especialidad
     * @param string $tabId ID único de la pestaña
     * @param int $idRrHh Opcional: ID del médico
     * @return string|array
     */
    public static function prepararParaIA($textoConsulta, $especialidad = null, $tabId = null, $idRrHh = null)
    {
        $inicio = microtime(true);
        $logger = ConsultaLogger::obtenerInstancia();
        
        // ============================================
        // CORRECCIÓN COMPLETA CON IA LOCAL (Llama 3.1 70B Instruct)
        // ============================================
        if ($logger) {
            $logger->registrar(
                'PROCESAMIENTO',
                $textoConsulta,
                null,
                [
                    'metodo' => 'ProcesadorTextoMedico::prepararParaIA',
                    'paso' => 'Iniciando corrección con IA local (Llama 3.1 70B Instruct)'
                ]
            );
        }
        
        $iam = Yii::$app->iamanager;
        $resultadoIA = $iam->corregirTextoCompletoConIA($textoConsulta, $especialidad);
        
        $textoCorregido = $resultadoIA['texto_corregido'];
        
        if ($logger) {
            $cambiosDetallados = [];
            foreach ($resultadoIA['cambios'] as $cambio) {
                $cambiosDetallados[] = $cambio['original'] . ' → ' . $cambio['corrected'];
            }
            
            $logger->registrar(
                'PROCESAMIENTO',
                $textoConsulta,
                $textoCorregido,
                [
                    'metodo' => 'ProcesadorTextoMedico::prepararParaIA - Corrección IA',
                    'confianza' => $resultadoIA['confidence'],
                    'total_cambios' => $resultadoIA['total_changes'],
                    'cambios_detallados' => $cambiosDetallados,
                    'tiempo_procesamiento' => round($resultadoIA['processing_time'], 3),
                    'modelo' => 'llama3.1:70b-instruct'
                ]
            );
        }
        
        // ============================================
        // EXPANSIÓN DE ABREVIATURAS
        // ============================================
        // IMPLEMENTACIÓN LOCAL COMENTADA - La IA ahora hace todo (corrección + expansión)
        /*
        if ($logger) {
            $logger->registrar(
                'PROCESAMIENTO',
                $textoCorregido,
                null,
                ['metodo' => 'ProcesadorTextoMedico::expandirAbreviaturas']
            );
        }
        
        $resultado = self::expandirAbreviaturas($textoCorregido, $especialidad, $idRrHh);
        $resultado['ia_changes'] = $resultadoIA['cambios'];
        $resultado['metodo_principal'] = 'ia_local';
        $resultado['confidence_ia'] = $resultadoIA['confidence'];
        $resultado['modelo_ia'] = 'llama3.1:70b-instruct';
        
        if ($logger) {
            $logger->registrar(
                'PROCESAMIENTO',
                null,
                $resultado['texto_procesado'],
                [
                    'metodo' => 'ProcesadorTextoMedico::expandirAbreviaturas',
                    'abreviaturas_encontradas' => $resultado['abreviaturas_encontradas']
                ]
            );
        }
        
        // Guardar información de correcciones con tabId
        self::guardarInfoCorrecciones($resultado, $tabId);
        
        return $resultado['texto_procesado'];
        */
        
        // La IA ya hizo corrección y expansión, solo necesitamos preparar el resultado
        $resultado = [
            'texto_original' => $textoConsulta,
            'texto_procesado' => $textoCorregido,
            'abreviaturas_encontradas' => [], // Ya expandidas por la IA
            'ia_changes' => $resultadoIA['cambios'],
            'metodo_principal' => 'ia_local',
            'confidence_ia' => $resultadoIA['confidence'],
            'modelo_ia' => 'llama3.1:70b-instruct',
            'metadatos' => [
                'longitud_original' => strlen($textoConsulta),
                'longitud_procesado' => strlen($textoCorregido),
                'incremento_longitud' => strlen($textoCorregido) - strlen($textoConsulta),
                'numero_abreviaturas' => 0, // Ya expandidas por la IA
                'numero_no_reconocidas' => 0,
                'fecha_procesamiento' => date('Y-m-d H:i:s')
            ],
            'tiempo_procesamiento' => microtime(true) - $inicio,
            'mejora_legibilidad' => self::calcularMejoraLegibilidad($textoConsulta, $textoCorregido)
        ];
        
        if ($logger) {
            $logger->registrar(
                'PROCESAMIENTO',
                $textoConsulta,
                $textoCorregido,
                [
                    'metodo' => 'ProcesadorTextoMedico::prepararParaIA - IA completa',
                    'total_cambios' => $resultadoIA['total_changes'],
                    'confidence' => $resultadoIA['confidence'],
                    'nota' => 'Corrección y expansión realizadas por IA'
                ]
            );
        }
        
        // Guardar información de correcciones con tabId
        self::guardarInfoCorrecciones($resultado, $tabId);
        
        return $textoCorregido;
        
        // ============================================
        // CÓDIGO COMENTADO - SYMSPELL Y LLM ANTIGUO
        // ============================================
        /*
        // CAPA 1: SymSpell - Corrección rápida con diccionario médico
        $resultadoSymSpell = self::procesarConSymSpell($textoConsulta, $especialidad);
        // ... resto del código antiguo comentado ...
        */
    }


    /**
     * Detectar abreviaturas no reconocidas en el texto
     * @param string $texto
     * @param string $especialidad
     * @return array
     */
    private static function detectarAbreviaturasNoReconocidas($texto, $especialidad = null)
    {
        $abreviaturasNoReconocidas = [];
        $palabras = preg_split('/\s+/', $texto);
        
        foreach ($palabras as $palabra) {
            $palabraLimpia = preg_replace('/[^a-zA-Z]/', '', $palabra);
            
            // Patrones de abreviaturas comunes
            $patrones = [
                // Abreviaturas médicas comunes (2-4 letras mayúsculas)
                '/^[A-Z]{2,4}$/' => 'abreviatura_medica',
                // Abreviaturas con punto
                '/^[A-Z]{1,3}\.$/' => 'abreviatura_con_punto',
                // Abreviaturas con números
                '/^[A-Z]{1,3}\d+$/' => 'abreviatura_con_numero',
                // Abreviaturas mixtas
                '/^[A-Z][a-z]{1,2}$/' => 'abreviatura_mixta',
            ];
            
            foreach ($patrones as $patron => $tipo) {
                if (preg_match($patron, $palabraLimpia) && strlen($palabraLimpia) >= 2) {
                    // Verificar si ya existe en la base de datos
                    $existe = AbreviaturasMedicas::buscarAbreviatura($palabraLimpia);
                    
                    if (!$existe) {
                        $contextoMejorado = self::obtenerContextoMejorado($palabra, $texto, $especialidad);
                        
                        $abreviaturasNoReconocidas[] = [
                            'abreviatura' => $palabraLimpia,
                            'tipo' => $tipo,
                            'contexto' => $contextoMejorado,
                            'palabra_original' => $palabra,
                            'posicion' => strpos($texto, $palabra)
                        ];
                    }
                }
            }
        }
        
        return $abreviaturasNoReconocidas;
    }

    /**
     * Obtener contexto mejorado para una abreviatura
     * @param string $palabra
     * @param string $texto
     * @return array
     */
    private static function obtenerContextoMejorado($palabra, $texto, $especialidad = null)
    {
        $posicion = strpos($texto, $palabra);
        $palabrasTexto = preg_split('/\s+/', $texto);
        $indicePalabra = array_search($palabra, $palabrasTexto);
        
        // Window de palabras (3 antes, 3 después)
        $windowAntes = [];
        $windowDespues = [];
        
        for ($i = max(0, $indicePalabra - 3); $i < $indicePalabra; $i++) {
            $windowAntes[] = strtolower(preg_replace('/[^a-zA-Z]/', '', $palabrasTexto[$i]));
        }
        
        for ($i = $indicePalabra + 1; $i < min(count($palabrasTexto), $indicePalabra + 4); $i++) {
            $windowDespues[] = strtolower(preg_replace('/[^a-zA-Z]/', '', $palabrasTexto[$i]));
        }
        
        // Bigrams (pares de palabras)
        $bigramsAntes = [];
        $bigramsDespues = [];
        
        if (count($windowAntes) >= 2) {
            for ($i = 0; $i < count($windowAntes) - 1; $i++) {
                $bigramsAntes[] = $windowAntes[$i] . ' ' . $windowAntes[$i + 1];
            }
        }
        
        if (count($windowDespues) >= 2) {
            for ($i = 0; $i < count($windowDespues) - 1; $i++) {
                $bigramsDespues[] = $windowDespues[$i] . ' ' . $windowDespues[$i + 1];
            }
        }
        
        // Oración completa
        $oracionCompleta = self::extraerOracionCompleta($palabra, $texto);
        
        // Análisis de contexto médico
        $contextoMedico = self::analizarContextoMedico($windowAntes, $windowDespues, $bigramsAntes, $bigramsDespues, $especialidad);
        
        return [
            'window_antes' => $windowAntes,
            'window_despues' => $windowDespues,
            'bigrams_antes' => $bigramsAntes,
            'bigrams_despues' => $bigramsDespues,
            'oracion_completa' => $oracionCompleta,
            'contexto_medico' => $contextoMedico,
            'posicion_relativa' => $indicePalabra / count($palabrasTexto)
        ];
    }
    
    /**
     * Extraer oración completa donde aparece la palabra
     * @param string $palabra
     * @param string $texto
     * @return string
     */
    private static function extraerOracionCompleta($palabra, $texto)
    {
        // Buscar la palabra como palabra completa (con límites de palabra)
        $pattern = '/\b' . preg_quote($palabra, '/') . '\b/';
        if (!preg_match($pattern, $texto, $matches, PREG_OFFSET_CAPTURE)) {
            return $texto; // Si no se encuentra, devolver todo el texto
        }

        $pos = $matches[0][1]; // Posición de la palabra encontrada

        // Buscar inicio de oración hacia atrás
        $inicio = $pos;
        while ($inicio > 0 && !in_array($texto[$inicio], ['.', '!', '?', "\n"])) {
            $inicio--;
        }
        if ($inicio > 0) $inicio++; // Incluir el delimitador

        // Buscar fin de oración hacia adelante
        $fin = $pos + strlen($palabra);
        while ($fin < strlen($texto) && !in_array($texto[$fin], ['.', '!', '?', "\n"])) {
            $fin++;
        }

        $sentence = trim(substr($texto, $inicio, $fin - $inicio));

        // Si la oración es muy corta, expandir con palabras adyacentes
        if (strlen($sentence) < 50) {
            // Agregar 3 palabras antes y después
            $words = preg_split('/\s+/', $texto);
            $wordIndex = false;
            $cleanWord = preg_replace('/[^a-zA-ZáéíóúüñÁÉÍÓÚÜÑ]/', '', $palabra);

            foreach ($words as $idx => $w) {
                $cleanW = preg_replace('/[^a-zA-ZáéíóúüñÁÉÍÓÚÜÑ]/', '', $w);
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
     * Analizar contexto médico basado en palabras y bigrams
     * @param array $windowAntes
     * @param array $windowDespues
     * @param array $bigramsAntes
     * @param array $bigramsDespues
     * @return array
     */
    private static function analizarContextoMedico($windowAntes, $windowDespues, $bigramsAntes, $bigramsDespues, $especialidad = null)
    {
        $terminos = self::getContextTerms($especialidad);
        $bigrams = self::getContextBigrams($especialidad);

        $score = 0;
        $terminosEncontrados = [];
        $bigramsEncontrados = [];

        $todasLasPalabras = array_merge($windowAntes, $windowDespues);
        foreach ($todasLasPalabras as $palabra) {
            $palabraNormalizada = TextoMedicoHelper::limpiarPalabra($palabra);
            if (isset($terminos[$palabraNormalizada])) {
                $score += $terminos[$palabraNormalizada]['peso'] ?? 1;
                $terminosEncontrados[] = [
                    'termino' => $palabraNormalizada,
                    'categoria' => $terminos[$palabraNormalizada]['categoria'] ?? null,
                    'especialidad' => $terminos[$palabraNormalizada]['especialidad'] ?? null,
                ];
            }
        }

        $todosLosBigrams = array_merge($bigramsAntes, $bigramsDespues);
        foreach ($todosLosBigrams as $bigram) {
            $bigramNormalizado = TextoMedicoHelper::limpiarTexto($bigram);
            if (isset($bigrams[$bigramNormalizado])) {
                $score += $bigrams[$bigramNormalizado]['peso'] ?? 2;
                $bigramsEncontrados[] = [
                    'termino' => $bigramNormalizado,
                    'categoria' => $bigrams[$bigramNormalizado]['categoria'] ?? null,
                    'especialidad' => $bigrams[$bigramNormalizado]['especialidad'] ?? null,
                ];
            }
        }

        return [
            'score' => $score,
            'umbral' => self::CONTEXTO_MEDICO_UMBRAL,
            'es_contexto_medico' => $score >= self::CONTEXTO_MEDICO_UMBRAL,
            'terminos_encontrados' => $terminosEncontrados,
            'bigrams_encontrados' => $bigramsEncontrados
        ];
    }

    /**
     * Reportar abreviaturas no reconocidas
     * @param array $abreviaturasNoReconocidas
     * @param string $textoOriginal
     * @param string $especialidad
     */
    private static function reportarAbreviaturasNoReconocidas($abreviaturasNoReconocidas, $textoOriginal, $especialidad = null)
    {
        foreach ($abreviaturasNoReconocidas as $abreviatura) {
            $expansionPropuesta = $abreviatura['expansion_propuesta'] ?? '';

            // Registrar sugerencia en la tabla principal con origen USUARIO
            $nuevaAbreviatura = new AbreviaturasMedicas();
            $nuevaAbreviatura->abreviatura = $abreviatura['abreviatura'];
            $nuevaAbreviatura->expansion_completa = $expansionPropuesta;
            $nuevaAbreviatura->categoria = $abreviatura['categoria'] ?? null;
            $nuevaAbreviatura->especialidad = $especialidad;
            $nuevaAbreviatura->contexto = $abreviatura['contexto'] ?? null;
            $nuevaAbreviatura->sinonimos = isset($abreviatura['sinonimos']) ? json_encode($abreviatura['sinonimos']) : null;
            $nuevaAbreviatura->frecuencia_uso = 1;
            $nuevaAbreviatura->origen = AbreviaturasMedicas::ORIGEN_USUARIO;
            $nuevaAbreviatura->activo = 0; // Pendiente de aprobación manual

            try {
                if (!$nuevaAbreviatura->save()) {
                    \Yii::error('Error guardando sugerencia de abreviatura: ' . json_encode($nuevaAbreviatura->getErrors()), 'procesador-texto');
                }
            } catch (\Exception $e) {
                \Yii::error('Excepción guardando sugerencia de abreviatura: ' . $e->getMessage(), 'procesador-texto');
            }
        }
    }

    /**
     * Generar metadatos del procesamiento (versión mejorada)
     * @param string $textoOriginal
     * @param string $textoExpandido
     * @param array $abreviaturasEncontradas
     * @param array $abreviaturasNoReconocidas
     * @return array
     */
    private static function generarMetadatos($textoOriginal, $textoExpandido, $abreviaturasEncontradas, $abreviaturasNoReconocidas = [])
    {
        return [
            'longitud_original' => strlen($textoOriginal),
            'longitud_procesado' => strlen($textoExpandido),
            'incremento_longitud' => strlen($textoExpandido) - strlen($textoOriginal),
            'numero_abreviaturas' => count($abreviaturasEncontradas),
            'numero_no_reconocidas' => count($abreviaturasNoReconocidas),
            'categorias_encontradas' => array_unique(array_column($abreviaturasEncontradas, 'categoria')),
            'tipos_no_reconocidas' => array_unique(array_column($abreviaturasNoReconocidas, 'tipo')),
            'fecha_procesamiento' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Obtener abreviaturas más reportadas
     * @param int $limite
     * @return array
     */
    public static function getAbreviaturasMasReportadas($limite = 20)
    {
        return AbreviaturasMedicas::find()
            ->where(['activo' => 0])
            ->orderBy(['frecuencia_uso' => SORT_DESC])
            ->limit($limite)
            ->all();
    }

    /**
     * Obtener estadísticas de sugerencias
     * @return array
     */
    public static function getEstadisticasSugerencias()
    {
        $total = AbreviaturasMedicas::find()->count();
        $pendientes = AbreviaturasMedicas::find()->where(['activo' => 0])->count();
        $aprobadas = AbreviaturasMedicas::find()->where(['activo' => 1])->count();
        $llm = AbreviaturasMedicas::find()->where(['origen' => AbreviaturasMedicas::ORIGEN_LLM])->count();
        $usuario = AbreviaturasMedicas::find()->where(['origen' => AbreviaturasMedicas::ORIGEN_USUARIO])->count();
        
        return [
            'total' => $total,
            'pendientes' => $pendientes,
            'aprobadas' => $aprobadas,
            'llm' => $llm,
            'usuario' => $usuario
        ];
    }

    /**
     * Obtener sugerencias pendientes
     * @param int $limite
     * @return array
     */
    public static function getSugerenciasPendientes($limite = 50)
    {
        return AbreviaturasMedicas::find()
            ->where(['activo' => 0])
            ->orderBy(['fecha_creacion' => SORT_DESC])
            ->limit($limite)
            ->all();
    }

    /**
     * Corregir errores de tipeo usando modelo clínico (wrapper para ConsultaController)
     * @param string $textoOriginal
     * @param string $especialidad
     * @return array
     */
    public static function corregirErroresTipeo($textoOriginal, $especialidad = null)
    {
        // Este método actúa como wrapper para el método del ConsultaController
        // Se puede usar desde otros componentes del sistema
        
        $inicio = microtime(true);
        
        try {
            // Verificar si la corrección está activada
            if (!(\Yii::$app->params['hf_activar_correccion'] ?? false)) {
                $logger = ConsultaLogger::obtenerInstancia();
                if ($logger) {
                    $logger->registrar(
                        'PROCESAMIENTO',
                        'Corrección de tipeo',
                        'Corrección de tipeo desactivada',
                        ['metodo' => 'ProcesadorTextoMedico::corregirErroresTipeo']
                    );
                }
                return [
                    'texto_corregido' => $textoOriginal,
                    'cambios_realizados' => [],
                    'errores_detectados' => [],
                    'tiempo_procesamiento' => 0
                ];
            }

            // Detectar posibles errores de tipeo usando patrones simples
            $erroresDetectados = self::detectarErroresTipeoSimple($textoOriginal, $especialidad);
            
            if (empty($erroresDetectados)) {
                $logger = ConsultaLogger::obtenerInstancia();
                if ($logger) {
                    $logger->registrar(
                        'PROCESAMIENTO',
                        'Detección de errores',
                        'No se detectaron errores de tipeo',
                        ['metodo' => 'ProcesadorTextoMedico::corregirErroresTipeo']
                    );
                }
                return [
                    'texto_corregido' => $textoOriginal,
                    'cambios_realizados' => [],
                    'errores_detectados' => [],
                    'tiempo_procesamiento' => microtime(true) - $inicio
                ];
            }

            // Aplicar correcciones básicas
            $textoCorregido = $textoOriginal;
            $cambiosRealizados = [];
            
            foreach ($erroresDetectados as $error) {
                $correccion = self::obtenerCorreccionBasica($error, $especialidad);
                
                if ($correccion && $correccion !== $error['palabra']) {
                    $textoCorregido = str_replace($error['palabra'], $correccion, $textoCorregido);
                    $cambiosRealizados[] = [
                        'original' => $error['palabra'],
                        'corregido' => $correccion,
                        'tipo' => 'correccion_basica',
                        'contexto' => $error['contexto'] ?? ''
                    ];
                    
                    $logger = ConsultaLogger::obtenerInstancia();
                    if ($logger) {
                        $logger->registrar(
                            'PROCESAMIENTO',
                            $error['palabra'],
                            $correccion,
                            [
                                'metodo' => 'ProcesadorTextoMedico::corregirErroresTipeo',
                                'cambios' => $error['palabra'] . ' → ' . $correccion
                            ]
                        );
                    }
                }
            }

            $tiempoProcesamiento = microtime(true) - $inicio;
            
            $logger = ConsultaLogger::obtenerInstancia();
            if ($logger) {
                $logger->registrar(
                    'PROCESAMIENTO',
                    'Corrección básica',
                    'Corrección básica completada',
                    [
                        'metodo' => 'ProcesadorTextoMedico::corregirErroresTipeo',
                        'total_cambios' => count($cambiosRealizados),
                        'tiempo_procesamiento' => round($tiempoProcesamiento, 3) . 's'
                    ]
                );
            }

            return [
                'texto_corregido' => $textoCorregido,
                'cambios_realizados' => $cambiosRealizados,
                'errores_detectados' => $erroresDetectados,
                'tiempo_procesamiento' => $tiempoProcesamiento
            ];

        } catch (\Exception $e) {
            \Yii::error('Error en corrección básica de tipeo: ' . $e->getMessage(), 'procesador-texto');
            
            return [
                'texto_corregido' => $textoOriginal,
                'cambios_realizados' => [],
                'errores_detectados' => [],
                'tiempo_procesamiento' => microtime(true) - $inicio,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Detectar errores de tipeo usando patrones simples (sin modelo de IA)
     * @param string $texto
     * @param string $especialidad
     * @return array
     */
    private static function detectarErroresTipeoSimple($texto, $especialidad = null)
    {
        $errores = [];
        $palabras = preg_split('/\s+/', $texto);
        
        // Diccionario de errores comunes en medicina
        $erroresComunes = [
            'laseracion' => 'laceración',
            'diabetis' => 'diabetes',
            'hipertencion' => 'hipertensión',
            'prescrivir' => 'prescribir',
            'medicamento' => 'medicamento',
            'sintomas' => 'síntomas',
            'diagnostico' => 'diagnóstico',
            'tratamiento' => 'tratamiento',
            'paciente' => 'paciente',
            'consulta' => 'consulta',
            'enfermedad' => 'enfermedad',
            'examen' => 'examen',
            'resultado' => 'resultado',
            'prescripcion' => 'prescripción',
            'dosis' => 'dosis',
            'frecuencia' => 'frecuencia',
            'control' => 'control',
            'seguimiento' => 'seguimiento',
            'historia' => 'historia',
            'clinica' => 'clínica',
            'medico' => 'médico',
            'hospital' => 'hospital'
        ];
        
        foreach ($palabras as $palabra) {
            $palabraLimpia = preg_replace('/[^a-zA-ZáéíóúüñÁÉÍÓÚÜÑ]/', '', $palabra);
            $palabraLower = strtolower($palabraLimpia);
            
            // Verificar si es un error conocido
            if (isset($erroresComunes[$palabraLower])) {
                $contexto = self::obtenerContextoSimple($palabra, $texto);
                
                $errores[] = [
                    'palabra' => $palabra,
                    'palabra_limpia' => $palabraLimpia,
                    'correccion_sugerida' => $erroresComunes[$palabraLower],
                    'contexto' => $contexto,
                    'posicion' => strpos($texto, $palabra)
                ];
            }
        }
        
        return $errores;
    }

    /**
     * Obtener contexto simple de una palabra
     * @param string $palabra
     * @param string $texto
     * @return string
     */
    private static function obtenerContextoSimple($palabra, $texto)
    {
        $posicion = strpos($texto, $palabra);
        $inicio = max(0, $posicion - 30);
        $longitud = 60;
        
        return substr($texto, $inicio, $longitud);
    }

    /**
     * Obtener corrección básica para un error detectado
     * @param array $error
     * @param string $especialidad
     * @return string|null
     */
    private static function obtenerCorreccionBasica($error, $especialidad = null)
    {
        return $error['correccion_sugerida'] ?? null;
    }

    /**
     * CAPA 1: Procesar con SymSpell (corrección rápida)
     * MÉTODO COMENTADO - Ya no se usa, reemplazado por corrección completa con IA
     * @param string $texto
     * @param string $especialidad
     * @return array
     */
    /*
    private static function procesarConSymSpell($texto, $especialidad = null)
    {
        try {
            $corrector = new SymSpellCorrector();
            $resultado = $corrector->correctText($texto, $especialidad);
            
            return $resultado;
            
        } catch (\Exception $e) {
            \Yii::error("Error en SymSpell: " . $e->getMessage(), 'procesador-texto');
            
            return [
                'original_text' => $texto,
                'corrected_text' => $texto,
                'corrections' => [],
                'changes' => [],
                'total_changes' => 0,
                'processing_time' => 0,
                'confidence_avg' => 0
            ];
        }
    }
    */

    /**
     * CAPA 2: Procesar con LLM (PlanTL-GOB-ES)
     * MÉTODO COMENTADO - Ya no se usa, reemplazado por corrección completa con IA
     * @param string $texto
     * @param string $especialidad
     * @param array|null $resultadoSymSpell Resultado de SymSpell para incluir palabras problemáticas
     * @return array
     */
    /*
    private static function procesarConLLM($texto, $especialidad = null, $resultadoSymSpell = null)
    {
        try {
            // Verificar si la corrección con LLM está activada
            if (!(\Yii::$app->params['hf_activar_correccion'] ?? false)) {
                $logger = ConsultaLogger::obtenerInstancia();
                if ($logger) {
                    $logger->registrar(
                        'PROCESAMIENTO',
                        'Corrección LLM desactivada',
                        'hf_activar_correccion está en false - No se procesará con LLM',
                        [
                            'metodo' => 'ProcesadorTextoMedico::procesarConLLM',
                            'parametro' => 'hf_activar_correccion',
                            'valor' => false
                        ]
                    );
                }
                
                return [
                    'corrected_text' => $texto,
                    'confidence' => 0,
                    'changes' => [],
                    'processing_time' => 0
                ];
            }

            $inicio = microtime(true);
            
            // Combinar todas las palabras problemáticas:
            // 1. Palabras sin sugerencias de SymSpell
            // 2. Palabras con baja confianza de SymSpell
            // 3. Palabras sospechosas detectadas por patrones
            $palabrasACorregir = [];
            
            // Si tenemos resultado de SymSpell, incluir sus palabras problemáticas
            if ($resultadoSymSpell) {
                // Agregar palabras sin sugerencias
                $palabrasSinSugerencias = $resultadoSymSpell['words_without_suggestions'] ?? [];
                foreach ($palabrasSinSugerencias as $palabraInfo) {
                    $palabra = $palabraInfo['word'];
                    $palabraLimpia = preg_replace('/[^a-zA-ZáéíóúüñÁÉÍÓÚÜÑ]/', '', $palabra);
                    if (strlen($palabraLimpia) >= 3 && !in_array($palabra, $palabrasACorregir)) {
                        $palabrasACorregir[] = $palabra;
                    }
                }
                
                // Agregar palabras con baja confianza
                $cambiosProblematicos = $resultadoSymSpell['cambios_problematicos'] ?? [];
                foreach ($cambiosProblematicos as $cambioProblematico) {
                    $palabra = $cambioProblematico['original'] ?? null;
                    if ($palabra) {
                        $palabraLimpia = preg_replace('/[^a-zA-ZáéíóúüñÁÉÍÓÚÜÑ]/', '', $palabra);
                        if (strlen($palabraLimpia) >= 3 && !in_array($palabra, $palabrasACorregir)) {
                            $palabrasACorregir[] = $palabra;
                        }
                    }
                }
            }
            
            // Agregar palabras sospechosas detectadas por patrones
            $palabrasSospechosas = self::detectarPalabrasSospechosas($texto);
            foreach ($palabrasSospechosas as $palabra) {
                if (!in_array($palabra, $palabrasACorregir)) {
                    $palabrasACorregir[] = $palabra;
                }
            }
            
            if (empty($palabrasACorregir)) {
                $logger = ConsultaLogger::obtenerInstancia();
                if ($logger) {
                    $logger->registrar(
                        'PROCESAMIENTO',
                        'No se encontraron palabras sospechosas',
                        'No se detectaron palabras con errores ortográficos - Texto sin cambios',
                        [
                            'metodo' => 'ProcesadorTextoMedico::procesarConLLM',
                            'total_palabras_sospechosas' => 0,
                            'texto_procesado' => substr($texto, 0, 100) . (strlen($texto) > 100 ? '...' : '')
                        ]
                    );
                }
                
                return [
                    'corrected_text' => $texto,
                    'confidence' => 1.0,
                    'changes' => [],
                    'processing_time' => microtime(true) - $inicio
                ];
            }

            // Usar el modelo clínico para corrección contextual
            // Procesar todas las palabras problemáticas en un solo prompt
            $logger = ConsultaLogger::obtenerInstancia();
            if ($logger) {
                $logger->registrar(
                    'PROCESAMIENTO',
                    'Procesando palabras problemáticas con LLM',
                    'Enviando ' . count($palabrasACorregir) . ' palabras (sin sugerencias + baja confianza + sospechosas) en un solo prompt',
                    [
                        'metodo' => 'ProcesadorTextoMedico::procesarConLLM',
                        'total_palabras' => count($palabrasACorregir),
                        'palabras' => $palabrasACorregir,
                        'tiene_symspell' => $resultadoSymSpell !== null
                    ]
                );
            }
            
            $iam = Yii::$app->iamanager;
            $correcciones = $iam->corregirPalabrasConLLM($palabrasACorregir, $texto, $especialidad);
            
            $textoCorregido = $texto;
            $cambios = [];
            $confianzaTotal = 0;
            $cambiosRealizados = 0;

            foreach ($correcciones as $palabra => $correccion) {
                if ($correccion && $correccion['confidence'] > 0.7) {
                    // Reemplazar todas las ocurrencias de la palabra
                    $textoCorregido = str_replace($palabra, $correccion['suggestion'], $textoCorregido);
                    $cambios[] = [
                        'original' => $palabra,
                        'corrected' => $correccion['suggestion'],
                        'confidence' => $correccion['confidence'],
                        'method' => 'llm_clinical'
                    ];
                    $confianzaTotal += $correccion['confidence'];
                    $cambiosRealizados++;
                }
            }

            $confianzaPromedio = $cambiosRealizados > 0 ? $confianzaTotal / $cambiosRealizados : 0;

            $logger = ConsultaLogger::obtenerInstancia();
            if ($logger) {
                $logger->registrar(
                    'PROCESAMIENTO',
                    'LLM',
                    'LLM procesó texto exitosamente',
                    [
                        'metodo' => 'ProcesadorTextoMedico::procesarConLLM',
                        'total_cambios' => $cambiosRealizados,
                        'confianza' => $confianzaPromedio
                    ]
                );
            }

            return [
                'corrected_text' => $textoCorregido,
                'confidence' => $confianzaPromedio,
                'changes' => $cambios,
                'total_changes' => $cambiosRealizados,
                'processing_time' => microtime(true) - $inicio
            ];

        } catch (\Exception $e) {
            \Yii::error("Error en procesamiento LLM: " . $e->getMessage(), 'procesador-texto');
            
            return [
                'corrected_text' => $texto,
                'confidence' => 0,
                'changes' => [],
                'processing_time' => 0
            ];
        }
    }
    */

    /**
     * Detectar palabras sospechosas de tener errores
     * @param string $texto
     * @return array
     */
    private static function detectarPalabrasSospechosas($texto)
    {
        $palabras = preg_split('/\s+/', $texto);
        $sospechosas = [];
        
        foreach ($palabras as $palabra) {
            $palabraLimpia = preg_replace('/[^a-zA-ZáéíóúüñÁÉÍÓÚÜÑ]/', '', $palabra);
            
            if (strlen($palabraLimpia) < 3) continue;
            
            // Patrones de errores comunes
            $patrones = [
                '/[aeiou]{3,}/', // Vocales repetidas
                '/[bcdfghjklmnpqrstvwxyz]{4,}/', // Consonantes repetidas
                '/^[a-z]+[A-Z]/', // Mezcla de mayúsculas/minúsculas
                '/[^a-zA-ZáéíóúüñÁÉÍÓÚÜÑ\s]/', // Caracteres extraños
            ];
            
            foreach ($patrones as $patron) {
                if (preg_match($patron, $palabraLimpia)) {
                    $sospechosas[] = $palabra;
                    break;
                }
            }
        }
        
        return array_unique($sospechosas);
    }

    /**
     * Corregir una palabra específica usando LLM clínico
     * @param string $palabra
     * @param string $contexto
     * @param string $especialidad
     * @return array|null
     */
    private static function corregirPalabraConLLM($palabra, $contexto, $especialidad = null)
    {
        try {
            // Crear prompt específico para corrección
            $prompt = "Eres un especialista médico en {$especialidad}. Corrige la ortografía de la siguiente palabra en el contexto médico dado. Responde SOLO con la palabra corregida, sin explicaciones.\n\n";
            $prompt .= "Palabra: {$palabra}\n";
            $prompt .= "Contexto: {$contexto}\n";
            $prompt .= "Corrección:";

            // Usar el endpoint de HuggingFace configurado
            $endpoint = \Yii::$app->params['hf_endpoint'] ?? 'https://api-inference.huggingface.co/models/PlanTL-GOB-ES/roberta-base-biomedical-clinical-es';
            $apiKey = \Yii::$app->params['hf_api_key'] ?? '';
            
            $payload = [
                'inputs' => $prompt,
                'parameters' => [
                    'max_length' => 50,
                    'temperature' => 0.1,
                    'return_full_text' => false
                ]
            ];

            $client = new \yii\httpclient\Client();
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl($endpoint)
                ->addHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json'
                ])
                ->setContent(json_encode($payload))
                ->send();

            if ($response->isOk) {
                $data = json_decode($response->content, true);
                $suggestion = trim($data[0]['generated_text'] ?? '');
                
                if (!empty($suggestion) && $suggestion !== $palabra) {
                    // Calcular confianza basada en la respuesta delegando a IAManager
                    $confianza = Yii::$app->iamanager->calcularConfianzaLLM($palabra, $suggestion, $contexto);
                    
                    return [
                        'suggestion' => $suggestion,
                        'confidence' => $confianza
                    ];
                }
            }

            return null;

        } catch (\Exception $e) {
            \Yii::error("Error corrigiendo palabra con LLM: " . $e->getMessage(), 'procesador-texto');
            return null;
        }
    }

    /**
     * Calcular confianza de la corrección del LLM
     * @param string $original
     * @param string $suggestion
     * @param string $contexto
     * @return float
     */
    private static function calcularConfianzaLLM($original, $suggestion, $contexto)
    {
        $confianza = 0.5; // Base
        
        // Ajustar por similitud
        $similitud = 1 - (levenshtein($original, $suggestion) / max(strlen($original), strlen($suggestion)));
        $confianza += $similitud * 0.3;
        
        // Ajustar por contexto médico
        $terminosMedicos = ['paciente', 'consulta', 'diagnóstico', 'tratamiento', 'medicamento', 'síntoma', 'enfermedad'];
        foreach ($terminosMedicos as $termino) {
            if (stripos($contexto, $termino) !== false) {
                $confianza += 0.1;
                break;
            }
        }
        
        // Ajustar por longitud (palabras muy cortas son menos confiables)
        if (strlen($suggestion) < 3) {
            $confianza -= 0.2;
        }
        
        return min(1.0, max(0.0, $confianza));
    }

    /**
     * Guardar información de correcciones con ID único por pestaña
     * @param array $resultado
     * @param string $tabId ID único de la pestaña
     * @return string ID de la corrección guardada
     */
    private static function guardarInfoCorrecciones($resultado, $tabId = null)
    {
        try {
            // Generar ID único si no se proporciona
            if (!$tabId) {
                $tabId = self::generarTabId();
            }

            $correcciones = [
                'tab_id' => $tabId,
                'timestamp' => time(),
                'total_cambios' => 0,
                'cambios_automaticos' => [],
                'requiere_validacion' => false,
                'correcciones_pendientes' => [],
                'estadisticas' => [
                    'ia_local' => 0,
                    'symspell' => 0, // Mantener para compatibilidad
                    'llm' => 0 // Mantener para compatibilidad
                ]
            ];

            // Procesar cambios de IA local (nuevo método principal)
            if (isset($resultado['ia_changes'])) {
                foreach ($resultado['ia_changes'] as $cambio) {
                    $correcciones['cambios_automaticos'][] = [
                        'original' => $cambio['original'],
                        'corregido' => $cambio['corrected'],
                        'confianza' => $cambio['confidence'] ?? 0.95,
                        'metodo' => $cambio['method'] ?? 'ia_local'
                    ];
                    $correcciones['estadisticas']['ia_local']++;
                    $correcciones['total_cambios']++;
                }
            }

            // Procesar cambios de SymSpell (compatibilidad con código antiguo)
            if (isset($resultado['symspell_changes'])) {
                foreach ($resultado['symspell_changes'] as $cambio) {
                    $correcciones['cambios_automaticos'][] = [
                        'original' => $cambio['original'],
                        'corregido' => $cambio['corrected'],
                        'confianza' => $cambio['confidence']
                    ];
                    $correcciones['estadisticas']['symspell']++;
                    $correcciones['total_cambios']++;
                }
            }

            // Procesar cambios de LLM (compatibilidad con código antiguo)
            if (isset($resultado['llm_changes'])) {
                foreach ($resultado['llm_changes'] as $cambio) {
                    $correcciones['cambios_automaticos'][] = [
                        'original' => $cambio['original'],
                        'corregido' => $cambio['corrected']
                    ];
                    $correcciones['estadisticas']['llm']++;
                    $correcciones['total_cambios']++;
                }
            }

            // Procesar casos que requieren validación
            if (isset($resultado['requires_validation']) && $resultado['requires_validation']) {
                $correcciones['requiere_validacion'] = true;

                // Agregar sugerencias de SymSpell (excluyendo confianza 1.0)
                if (isset($resultado['symspell_suggestions'])) {
                    foreach ($resultado['symspell_suggestions'] as $sugerencia) {
                        $confianza = $sugerencia['confidence'] ?? 0;
                        // Solo agregar si la confianza es menor a 1.0 (100%)
                        if ($confianza < 1.0) {
                            $correcciones['correcciones_pendientes'][] = [
                                'original' => $sugerencia['original'],
                                'sugerencia' => $sugerencia['corrected'],
                                'confianza' => $confianza,
                                'metodo' => $sugerencia['method'] ?? 'symspell'
                            ];
                        }
                    }
                }

                // Agregar sugerencias de LLM (excluyendo confianza 1.0)
                if (isset($resultado['llm_suggestions'])) {
                    foreach ($resultado['llm_suggestions'] as $sugerencia) {
                        $confianza = $sugerencia['confidence'] ?? 0;
                        // Solo agregar si la confianza es menor a 1.0 (100%)
                        if ($confianza < 1.0) {
                            $correcciones['correcciones_pendientes'][] = [
                                'original' => $sugerencia['original'],
                                'sugerencia' => $sugerencia['corrected'],
                                'confianza' => $confianza,
                                'metodo' => $sugerencia['method'] ?? 'llm'
                            ];
                        }
                    }
                }
                
                // Si después de filtrar no quedan correcciones pendientes, no requiere validación
                if (empty($correcciones['correcciones_pendientes'])) {
                    $correcciones['requiere_validacion'] = false;
                }
            }

            // Guardar en cache con TTL de 1 hora
            $cacheKey = "correcciones_texto_{$tabId}";
            \Yii::$app->cache->set($cacheKey, $correcciones, 3600); // 1 hora

            $logger = ConsultaLogger::obtenerInstancia();
            if ($logger) {
                $logger->registrar(
                    'PROCESAMIENTO',
                    'Guardar correcciones',
                    'Información de correcciones guardada',
                    [
                        'metodo' => 'ProcesadorTextoMedico::guardarInfoCorrecciones',
                        'tab_id' => $tabId
                    ]
                );
            }

            return $tabId;

        } catch (\Exception $e) {
            \Yii::error("Error guardando información de correcciones: " . $e->getMessage(), 'procesador-texto');
            return null;
        }
    }

    /**
     * Obtener información de correcciones por ID de pestaña (sin estadísticas para frontend)
     * @param string $tabId ID de la pestaña
     * @return array|null
     */
    public static function obtenerInfoCorrecciones($tabId = null)
    {
        if (!$tabId) {
            return null;
        }

        $cacheKey = "correcciones_texto_{$tabId}";
        $correcciones = \Yii::$app->cache->get($cacheKey);
        
        if ($correcciones && (time() - $correcciones['timestamp']) < 3600) {
            // Devolver solo los campos necesarios para el frontend (sin estadísticas)
            return [
                'tab_id' => $correcciones['tab_id'],
                'total_cambios' => $correcciones['total_cambios'],
                'cambios_automaticos' => $correcciones['cambios_automaticos'],
                'requiere_validacion' => $correcciones['requiere_validacion'],
                'correcciones_pendientes' => $correcciones['correcciones_pendientes']
            ];
        }
        
        return null;
    }

    /**
     * Generar ID único para pestaña
     * @return string
     */
    private static function generarTabId()
    {
        return 'tab_' . uniqid() . '_' . time();
    }

    /**
     * Limpiar correcciones expiradas
     */
    public static function limpiarCorreccionesExpiradas()
    {
        try {
            // Esta función se puede llamar desde un cron job
            // Para limpiar correcciones que tienen más de 1 hora
            $pattern = "correcciones_texto_*";
            // Implementar limpieza según el sistema de cache usado
            $logger = ConsultaLogger::obtenerInstancia();
            if ($logger) {
                $logger->registrar(
                    'PROCESAMIENTO',
                    'Limpieza',
                    'Limpieza de correcciones expiradas ejecutada',
                    ['metodo' => 'ProcesadorTextoMedico::limpiarCorreccionesExpiradas']
                );
            }
        } catch (\Exception $e) {
            \Yii::error("Error limpiando correcciones expiradas: " . $e->getMessage(), 'procesador-texto');
        }
    }

    /**
     * Obtener términos de contexto médico por especialidad
     * @param string $especialidad
     * @return array
     */
    private static function getContextTerms($especialidad = null)
    {
        // Verificar cache primero
        $cacheKey = "context_terms_" . ($especialidad ?? 'general');
        if (isset(self::$contextTermsCache[$cacheKey])) {
            return self::$contextTermsCache[$cacheKey];
        }

        try {
            $query = TerminoContextoMedico::find()
                ->where(['activo' => 1])
                ->orderBy(['peso' => SORT_DESC]);

            if ($especialidad) {
                $query->andWhere(['or', 
                    ['especialidad' => $especialidad],
                    ['especialidad' => null] // Términos generales
                ]);
            } else {
                $query->andWhere(['especialidad' => null]);
            }

            $terminos = $query->all();
            $terminosArray = [];

            foreach ($terminos as $termino) {
                $terminosArray[strtolower($termino->termino)] = [
                    'peso' => $termino->peso ?? 1,
                    'categoria' => $termino->categoria,
                    'especialidad' => $termino->especialidad
                ];
            }

            // Cachear resultado
            self::$contextTermsCache[$cacheKey] = $terminosArray;

            $logger = ConsultaLogger::obtenerInstancia();
            if ($logger) {
                $logger->registrar(
                    'PROCESAMIENTO',
                    'Carga de términos',
                    'Términos de contexto médico cargados',
                    [
                        'metodo' => 'ProcesadorTextoMedico::getContextTerms',
                        'terminos_cargados' => count($terminosArray)
                    ]
                );
            }

            return $terminosArray;

        } catch (\Exception $e) {
            \Yii::error("Error obteniendo términos de contexto: " . $e->getMessage(), 'procesador-texto');
            
            // Devolver términos básicos como fallback
            return self::getTerminosBasicos();
        }
    }

    /**
     * Obtener bigrams de contexto médico por especialidad
     * @param string $especialidad
     * @return array
     */
    private static function getContextBigrams($especialidad = null)
    {
        // Verificar cache primero
        $cacheKey = "context_bigrams_" . ($especialidad ?? 'general');
        if (isset(self::$contextBigramsCache[$cacheKey])) {
            return self::$contextBigramsCache[$cacheKey];
        }

        try {
            $query = TerminoContextoMedico::find()
                ->where(['activo' => 1, 'tipo' => 'bigram'])
                ->orderBy(['peso' => SORT_DESC]);

            if ($especialidad) {
                $query->andWhere(['or', 
                    ['especialidad' => $especialidad],
                    ['especialidad' => null] // Bigrams generales
                ]);
            } else {
                $query->andWhere(['especialidad' => null]);
            }

            $bigrams = $query->all();
            $bigramsArray = [];

            foreach ($bigrams as $bigram) {
                $bigramsArray[strtolower($bigram->termino)] = [
                    'peso' => $bigram->peso ?? 2,
                    'categoria' => $bigram->categoria,
                    'especialidad' => $bigram->especialidad
                ];
            }

            // Cachear resultado
            self::$contextBigramsCache[$cacheKey] = $bigramsArray;

            $logger = ConsultaLogger::obtenerInstancia();
            if ($logger) {
                $logger->registrar(
                    'PROCESAMIENTO',
                    'Carga de bigrams',
                    'Bigrams de contexto médico cargados',
                    [
                        'metodo' => 'ProcesadorTextoMedico::getContextBigrams',
                        'bigrams_cargados' => count($bigramsArray)
                    ]
                );
            }

            return $bigramsArray;

        } catch (\Exception $e) {
            \Yii::error("Error obteniendo bigrams de contexto: " . $e->getMessage(), 'procesador-texto');
            
            // Devolver bigrams básicos como fallback
            return self::getBigramsBasicos();
        }
    }

    /**
     * Obtener términos básicos como fallback
     * @return array
     */
    private static function getTerminosBasicos()
    {
        return [
            'paciente' => ['peso' => 2, 'categoria' => 'general', 'especialidad' => null],
            'consulta' => ['peso' => 2, 'categoria' => 'general', 'especialidad' => null],
            'diagnóstico' => ['peso' => 3, 'categoria' => 'general', 'especialidad' => null],
            'tratamiento' => ['peso' => 3, 'categoria' => 'general', 'especialidad' => null],
            'medicamento' => ['peso' => 2, 'categoria' => 'general', 'especialidad' => null],
            'síntoma' => ['peso' => 2, 'categoria' => 'general', 'especialidad' => null],
            'enfermedad' => ['peso' => 2, 'categoria' => 'general', 'especialidad' => null],
            'historia' => ['peso' => 1, 'categoria' => 'general', 'especialidad' => null],
            'clínica' => ['peso' => 1, 'categoria' => 'general', 'especialidad' => null],
            'médico' => ['peso' => 1, 'categoria' => 'general', 'especialidad' => null],
            'hospital' => ['peso' => 1, 'categoria' => 'general', 'especialidad' => null],
            'examen' => ['peso' => 2, 'categoria' => 'general', 'especialidad' => null],
            'resultado' => ['peso' => 2, 'categoria' => 'general', 'especialidad' => null],
            'prescripción' => ['peso' => 2, 'categoria' => 'general', 'especialidad' => null],
            'dosis' => ['peso' => 2, 'categoria' => 'general', 'especialidad' => null],
            'frecuencia' => ['peso' => 1, 'categoria' => 'general', 'especialidad' => null],
            'control' => ['peso' => 1, 'categoria' => 'general', 'especialidad' => null],
            'seguimiento' => ['peso' => 1, 'categoria' => 'general', 'especialidad' => null]
        ];
    }

    /**
     * Obtener bigrams básicos como fallback
     * @return array
     */
    private static function getBigramsBasicos()
    {
        return [
            'historia clínica' => ['peso' => 3, 'categoria' => 'general', 'especialidad' => null],
            'consulta médica' => ['peso' => 3, 'categoria' => 'general', 'especialidad' => null],
            'diagnóstico diferencial' => ['peso' => 4, 'categoria' => 'general', 'especialidad' => null],
            'tratamiento médico' => ['peso' => 3, 'categoria' => 'general', 'especialidad' => null],
            'medicamento prescrito' => ['peso' => 3, 'categoria' => 'general', 'especialidad' => null],
            'síntomas principales' => ['peso' => 3, 'categoria' => 'general', 'especialidad' => null],
            'enfermedad crónica' => ['peso' => 3, 'categoria' => 'general', 'especialidad' => null],
            'examen físico' => ['peso' => 3, 'categoria' => 'general', 'especialidad' => null],
            'resultado normal' => ['peso' => 2, 'categoria' => 'general', 'especialidad' => null],
            'prescripción médica' => ['peso' => 3, 'categoria' => 'general', 'especialidad' => null],
            'dosis diaria' => ['peso' => 2, 'categoria' => 'general', 'especialidad' => null],
            'control médico' => ['peso' => 2, 'categoria' => 'general', 'especialidad' => null],
            'seguimiento médico' => ['peso' => 2, 'categoria' => 'general', 'especialidad' => null]
        ];
    }
}
