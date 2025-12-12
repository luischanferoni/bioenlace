<?php

namespace common\components;

use Yii;
use common\helpers\TextoMedicoHelper;
use common\models\AbreviaturasMedicas;
use common\models\TerminoContextoMedico;
use common\models\DiccionarioOrtografico;
use common\components\ConsultaLogger;
use common\components\SymSpellCorrector;

/**
 * Componente para procesar texto médico con flujo optimizado:
 * 1. Preservar notación médica válida
 * 2. Expandir abreviaturas conocidas
 * 3. Corrección ortográfica rápida (SymSpell)
 * 4. Corrección con IA solo si es necesario
 * 5. Guardar correcciones/expansiones de IA (solo 100% confianza)
 */
class ProcesadorTextoMedico
{
    private const CONFIDENCE_MINIMA_APROBACION = 1.0; // Solo 100% de confianza

    /**
     * Procesar texto para análisis con IA (método principal)
     * Flujo optimizado: Preservar → Expandir → Corregir rápido → IA si necesario → Aprender
     * 
     * @param string $textoConsulta
     * @param string $especialidad
     * @param string $tabId ID único de la pestaña
     * @param int $idRrHh Opcional: ID del médico
     * @return string
     */
    public static function prepararParaIA($textoConsulta, $especialidad = null, $tabId = null, $idRrHh = null)
    {
        $inicio = microtime(true);
        $logger = ConsultaLogger::obtenerInstancia();
        
        if ($logger) {
            $logger->registrar(
                'PROCESAMIENTO',
                $textoConsulta,
                null,
                [
                    'metodo' => 'ProcesadorTextoMedico::prepararParaIA',
                    'paso' => 'Iniciando procesamiento completo'
                ]
            );
        }
        
        // ============================================
        // PASO 1: Identificar y preservar notación médica válida
        // ============================================
        $elementosPreservar = self::identificarElementosPreservar($textoConsulta, $especialidad);
        
        // Reemplazar temporalmente con marcadores
        $textoMarcado = $textoConsulta;
        foreach ($elementosPreservar as $elemento) {
            $textoMarcado = str_replace(
                $elemento['texto'], 
                $elemento['marcador'], 
                $textoMarcado
            );
        }
        
        if ($logger && !empty($elementosPreservar)) {
            $logger->registrar(
                'PROCESAMIENTO',
                'Preservación de notación médica',
                'Elementos preservados: ' . count($elementosPreservar),
                [
                    'metodo' => 'ProcesadorTextoMedico::identificarElementosPreservar',
                    'elementos_preservados' => count($elementosPreservar)
                ]
            );
        }
        
        // ============================================
        // PASO 2: Expandir abreviaturas conocidas desde BD
        // ============================================
        $textoConAbreviaturas = self::expandirAbreviaturasConocidas($textoMarcado, $especialidad, $idRrHh);
        
        if ($logger) {
            $logger->registrar(
                'PROCESAMIENTO',
                'Expansión de abreviaturas',
                'Abreviaturas expandidas desde BD',
                ['metodo' => 'ProcesadorTextoMedico::expandirAbreviaturasConocidas']
            );
        }
        
        // ============================================
        // PASO 3: Corrección ortográfica rápida (SymSpell)
        // ============================================
        $resultadoSymSpell = self::procesarConSymSpell($textoConAbreviaturas, $especialidad);
        $textoCorregidoRapido = $resultadoSymSpell['corrected_text'] ?? $textoConAbreviaturas;
        
        if ($logger) {
            $corrections = $resultadoSymSpell['corrections'] ?? [];
            $cambiosSymSpell = is_countable($corrections) ? count($corrections) : 0;
            $logger->registrar(
                'PROCESAMIENTO',
                'Corrección SymSpell',
                "Correcciones rápidas: {$cambiosSymSpell}",
                [
                    'metodo' => 'ProcesadorTextoMedico::procesarConSymSpell',
                    'total_correcciones' => $cambiosSymSpell
                ]
            );
        }
        
        // ============================================
        // PASO 4: Evaluar si necesita IA
        // ============================================
        $necesitaIA = self::evaluarSiNecesitaIA($resultadoSymSpell);
        
        if ($logger) {
            $logger->registrar(
                'PROCESAMIENTO',
                'Evaluación de necesidad de IA',
                $necesitaIA ? 'Requiere corrección con IA' : 'No requiere IA',
                [
                    'metodo' => 'ProcesadorTextoMedico::evaluarSiNecesitaIA',
                    'necesita_ia' => $necesitaIA
                ]
            );
        }
        
        // ============================================
        // PASO 5: Corrección con IA (solo si es necesario)
        // Si hay al menos una palabra no encontrada en el diccionario, 
        // enviar TODO el texto a la IA para que tenga contexto completo
        // ============================================
        if ($necesitaIA) {
            $iam = Yii::$app->iamanager;
            
            // Verificar si hay palabras sin sugerencias (no encontradas en diccionario)
            $palabrasSinSugerencias = $resultadoSymSpell['words_without_suggestions'] ?? [];
            
            // Si hay al menos una palabra no encontrada, usar corrección de texto completo
            // para que la IA tenga contexto completo
            if (!empty($palabrasSinSugerencias)) {
                $resultadoIA = $iam->corregirTextoCompletoConIA($textoCorregidoRapido, $especialidad);
                $textoCorregido = $resultadoIA['texto_corregido'];
                
                // VALIDACIÓN CRÍTICA: Verificar que no se haya agregado texto nuevo
                $textoCorregido = self::validarCorreccionIA($textoCorregido, $textoCorregidoRapido);
                
                if ($logger) {
                    $cambiosIAArray = $resultadoIA['cambios'] ?? [];
                    $cambiosIA = is_countable($cambiosIAArray) ? count($cambiosIAArray) : 0;
                    $palabrasSinSugerenciasCount = is_countable($palabrasSinSugerencias) ? count($palabrasSinSugerencias) : 0;
                    
                    $logger->registrar(
                        'PROCESAMIENTO',
                        'Corrección IA texto completo',
                        "Correcciones IA: {$cambiosIA}",
                        [
                            'metodo' => 'IAManager::corregirTextoCompletoConIA',
                            'total_cambios' => $cambiosIA,
                            'palabras_no_encontradas' => $palabrasSinSugerenciasCount
                        ]
                    );
                }
            } else {
                // Solo hay cambios problemáticos pero todas las palabras están en el diccionario
                // Usar resultado de SymSpell (ya es suficientemente bueno)
                $textoCorregido = $textoCorregidoRapido;
                $resultadoIA = [
                    'texto_corregido' => $textoCorregido,
                    'cambios' => [],
                    'confidence' => 1.0,
                    'total_changes' => 0
                ];
            }
        } else {
            // No necesita IA, usar resultado de SymSpell
            $textoCorregido = $textoCorregidoRapido;
            $resultadoIA = [
                'texto_corregido' => $textoCorregido,
                'cambios' => [],
                'confidence' => 1.0,
                'total_changes' => 0
            ];
        }
        
        // ============================================
        // PASO 6: Restaurar elementos preservados
        // ============================================
        foreach ($elementosPreservar as $elemento) {
            $textoCorregido = str_replace(
                $elemento['marcador'],
                $elemento['texto'], // Mantener original preservado
                $textoCorregido
            );
        }
        
        // ============================================
        // PASO 7: Guardar correcciones ortográficas de IA en diccionario (solo 100%)
        // ============================================
        if (!empty($resultadoIA['cambios'])) {
            self::guardarCorreccionesIAEnDiccionario($resultadoIA['cambios'], $especialidad);
        }
        
        // ============================================
        // PASO 8: Extraer y guardar expansiones de abreviaturas detectadas por IA (solo 100%)
        // ============================================
        $expansionesIA = self::extraerExpansionesDeTextoIA($textoConsulta, $textoCorregido);
        if (!empty($expansionesIA)) {
            self::guardarExpansionesAbreviaturasIA($expansionesIA, $especialidad, $textoConsulta);
        }
        
        // Guardar información de correcciones con tabId
        $resultado = [
            'texto_original' => $textoConsulta,
            'texto_procesado' => $textoCorregido,
            'ia_changes' => $resultadoIA['cambios'] ?? [],
            'symspell_changes' => $resultadoSymSpell['corrections'] ?? [],
            'metodo_principal' => $necesitaIA ? 'ia_local' : 'symspell',
            'confidence_ia' => $resultadoIA['confidence'] ?? 1.0,
            'tiempo_procesamiento' => microtime(true) - $inicio
        ];
        
        self::guardarInfoCorrecciones($resultado, $tabId);
        
        if ($logger) {
            $logger->registrar(
                'PROCESAMIENTO',
                'Procesamiento completado',
                'Texto procesado exitosamente',
                [
                    'metodo' => 'ProcesadorTextoMedico::prepararParaIA',
                    'total_cambios' => is_countable($resultadoIA['cambios'] ?? []) ? count($resultadoIA['cambios'] ?? []) : 0,
                    'tiempo_total' => round(microtime(true) - $inicio, 3)
                ]
            );
        }
        
        return $textoCorregido;
    }

    /**
     * Formatear texto procesado con HTML subrayado para palabras corregidas
     * 
     * @param string $textoProcesado Texto ya corregido
     * @param array $iaChanges Cambios realizados por IA
     * @param array $symspellChanges Cambios realizados por SymSpell
     * @return string Texto formateado con HTML
     */
    public static function formatearTextoConSubrayado($textoProcesado, $iaChanges = [], $symspellChanges = [])
    {
        // Escapar HTML primero
        $textoFormateado = htmlspecialchars($textoProcesado, ENT_QUOTES, 'UTF-8');
        
        // Preservar saltos de línea
        $textoFormateado = nl2br($textoFormateado);
        
        // Combinar todos los cambios
        $todosLosCambios = [];
        
        // Agregar cambios de IA
        foreach ($iaChanges as $cambio) {
            if (isset($cambio['original']) && isset($cambio['corrected']) && 
                $cambio['original'] !== $cambio['corrected']) {
                // Guardar valores originales (sin escapar) para el título
                $originalRaw = $cambio['original'];
                $correctedRaw = $cambio['corrected'];
                // Limpiar cualquier HTML que pueda haber en los valores originales
                $originalClean = strip_tags($originalRaw);
                $correctedClean = strip_tags($correctedRaw);
                
                $todosLosCambios[] = [
                    'original' => htmlspecialchars($originalRaw, ENT_QUOTES, 'UTF-8'), // Para reemplazo en texto
                    'corrected' => htmlspecialchars($correctedRaw, ENT_QUOTES, 'UTF-8'), // Para reemplazo en texto
                    'original_clean' => $originalClean, // Para título (sin HTML)
                    'corrected_clean' => $correctedClean // Para título (sin HTML)
                ];
            }
        }
        
        // Agregar cambios de SymSpell
        foreach ($symspellChanges as $cambio) {
            if (isset($cambio['original']) && isset($cambio['corrected']) && 
                $cambio['original'] !== $cambio['corrected']) {
                // Guardar valores originales (sin escapar) para el título
                $originalRaw = $cambio['original'];
                $correctedRaw = $cambio['corrected'];
                // Limpiar cualquier HTML que pueda haber en los valores originales
                $originalClean = strip_tags($originalRaw);
                $correctedClean = strip_tags($correctedRaw);
                
                // Evitar duplicados
                $existe = false;
                foreach ($todosLosCambios as $existente) {
                    if ($existente['original'] === htmlspecialchars($originalRaw, ENT_QUOTES, 'UTF-8') && 
                        $existente['corrected'] === htmlspecialchars($correctedRaw, ENT_QUOTES, 'UTF-8')) {
                        $existe = true;
                        break;
                    }
                }
                if (!$existe) {
                    $todosLosCambios[] = [
                        'original' => htmlspecialchars($originalRaw, ENT_QUOTES, 'UTF-8'), // Para reemplazo en texto
                        'corrected' => htmlspecialchars($correctedRaw, ENT_QUOTES, 'UTF-8'), // Para reemplazo en texto
                        'original_clean' => $originalClean, // Para título (sin HTML)
                        'corrected_clean' => $correctedClean // Para título (sin HTML)
                    ];
                }
            }
        }
        
        // Aplicar subrayado a las palabras corregidas
        // Ordenar por longitud descendente para evitar reemplazos parciales
        usort($todosLosCambios, function($a, $b) {
            return strlen($b['corrected']) - strlen($a['corrected']);
        });
        
        // Dividir el texto en partes: texto normal y tags HTML
        // Esto evita reemplazar dentro de tags HTML
        $partes = preg_split('/(<[^>]+>)/', $textoFormateado, -1, PREG_SPLIT_DELIM_CAPTURE);
        
        foreach ($todosLosCambios as $cambio) {
            // Escapar caracteres especiales para regex
            $palabraEscapada = preg_quote($cambio['corrected'], '/');
            
            // Buscar solo palabras completas
            $patron = '/\b' . $palabraEscapada . '\b/iu';
            
            // Usar solo clases de Bootstrap: text-decoration-underline para subrayado
            // Estilo inline mínimo solo para el color del subrayado (limitación de Bootstrap, no tiene clase para esto)
            $reemplazo = '<span class="text-decoration-underline" style="text-decoration-color: var(--bs-success);">' . $cambio['corrected'] . '</span>';
            
            // Aplicar reemplazo solo en partes que no son tags HTML
            for ($i = 0; $i < count($partes); $i++) {
                // Si la parte no empieza con <, es texto normal
                if (!preg_match('/^</', $partes[$i])) {
                    $partes[$i] = preg_replace($patron, $reemplazo, $partes[$i]);
                }
            }
        }
        
        // Reconstruir el texto
        $textoFormateado = implode('', $partes);
        
        return $textoFormateado;
    }

    /**
     * Procesar texto y devolver tanto el texto corregido como el formateado con HTML
     * 
     * @param string $textoConsulta
     * @param string $especialidad
     * @param string $tabId
     * @param int $idRrHh
     * @return array ['texto_procesado' => string, 'texto_formateado' => string, 'total_cambios' => int]
     */
    public static function prepararParaIAConFormato($textoConsulta, $especialidad = null, $tabId = null, $idRrHh = null)
    {
        $inicio = microtime(true);
        $logger = ConsultaLogger::obtenerInstancia();
        
        // Obtener el texto procesado usando el método existente
        $textoProcesado = self::prepararParaIA($textoConsulta, $especialidad, $tabId, $idRrHh);
        
        // Limpiar cualquier "Corregido:" que la IA pueda haber agregado al final
        $textoProcesado = preg_replace('/\s*(Corregido|Texto corregido|Corrección):?\s*$/i', '', $textoProcesado);
        $textoProcesado = trim($textoProcesado);
        
        // Limpiar líneas que solo contengan "Corregido:" o variaciones
        $lineas = explode("\n", $textoProcesado);
        $lineasLimpias = [];
        foreach ($lineas as $linea) {
            $lineaLimpia = trim($linea);
            // Si la línea es solo "Corregido:" o variaciones, omitirla
            if (!preg_match('/^(Corregido|Texto corregido|Corrección):?\s*$/i', $lineaLimpia)) {
                $lineasLimpias[] = $linea;
            }
        }
        $textoProcesado = implode("\n", $lineasLimpias);
        $textoProcesado = trim($textoProcesado);
        
        // Si después de limpiar el texto está vacío o solo tiene "Corregido:", usar el texto original
        if (empty($textoProcesado) || preg_match('/^(Corregido|Texto corregido|Corrección):?\s*$/i', $textoProcesado)) {
            if ($logger) {
                $logger->registrar(
                    'ADVERTENCIA',
                    'Texto procesado vacío después de limpieza',
                    'Usando texto original',
                    [
                        'metodo' => 'ProcesadorTextoMedico::prepararParaIAConFormato',
                        'texto_original_length' => strlen($textoConsulta)
                    ]
                );
            }
            $textoProcesado = $textoConsulta;
        }
        
        // Obtener información de correcciones para formatear
        $correccionesInfo = self::obtenerInfoCorrecciones($tabId);
        
        $iaChanges = [];
        $symspellChanges = [];
        $totalCambios = 0;
        
        if ($correccionesInfo && isset($correccionesInfo['cambios_automaticos'])) {
            $totalCambios = $correccionesInfo['total_cambios'] ?? 0;
            foreach ($correccionesInfo['cambios_automaticos'] as $cambio) {
                if (isset($cambio['original']) && isset($cambio['corregido'])) {
                    if (($cambio['metodo'] ?? '') === 'symspell') {
                        $symspellChanges[] = [
                            'original' => $cambio['original'],
                            'corrected' => $cambio['corregido']
                        ];
                    } else {
                        $iaChanges[] = [
                            'original' => $cambio['original'],
                            'corrected' => $cambio['corregido']
                        ];
                    }
                }
            }
        }
        
        // Formatear texto con subrayado
        $textoFormateado = self::formatearTextoConSubrayado($textoProcesado, $iaChanges, $symspellChanges);
        
        if ($logger) {
            $logger->registrar(
                'PROCESAMIENTO',
                'Formateo de texto completado',
                'Texto formateado generado',
                [
                    'metodo' => 'ProcesadorTextoMedico::prepararParaIAConFormato',
                    'texto_procesado_length' => strlen($textoProcesado),
                    'texto_formateado_length' => strlen($textoFormateado),
                    'total_cambios' => $totalCambios,
                    'ia_changes' => count($iaChanges),
                    'symspell_changes' => count($symspellChanges),
                    'texto_formateado_preview' => substr($textoFormateado, 0, 200)
                ]
            );
        }
        
        return [
            'texto_procesado' => $textoProcesado,
            'texto_formateado' => $textoFormateado,
            'total_cambios' => $totalCambios
        ];
    }

    /**
     * Identificar elementos médicos que deben preservarse (sin hard-coding)
     * Usa base de datos: terminos_contexto_medico con tipo='regex' y categoria='preservar'
     * 
     * @param string $texto
     * @param string|null $especialidad
     * @return array
     */
    private static function identificarElementosPreservar($texto, $especialidad = null)
    {
        $elementosPreservar = [];
        
        if (!class_exists('\common\models\DiccionarioOrtografico')) {
            return $elementosPreservar;
        }
        
        try {
            // Cargar patrones regex desde base de datos (nueva tabla unificada)
            // Usar DiccionarioOrtografico que ahora apunta a diccionario_medico
            $query = DiccionarioOrtografico::find()
                ->where(['tipo' => DiccionarioOrtografico::TIPO_REGEX_PRESERVAR, 'activo' => 1])
                ->andWhere(['or', 
                    ['categoria' => 'preservar'],
                    ['categoria' => 'notacion_medica']
                ]);
            
            if ($especialidad) {
                $query->andWhere(['or',
                    ['especialidad' => $especialidad],
                    ['especialidad' => null] // Patrones generales
                ]);
            } else {
                $query->andWhere(['especialidad' => null]);
            }
            
            $patrones = $query->orderBy(['peso' => SORT_DESC])->all();
            
            foreach ($patrones as $patron) {
                $regex = $patron->termino; // El término contiene el regex
                $metadata = $patron->metadata ?? [];
                
                if (preg_match_all($regex, $texto, $matches, PREG_OFFSET_CAPTURE) && !empty($matches[0])) {
                    foreach ($matches[0] as $match) {
                        $elementosPreservar[] = [
                            'texto' => $match[0],
                            'posicion' => $match[1],
                            'tipo' => $patron->categoria,
                            'especialidad' => $patron->especialidad,
                            'metadata' => $metadata,
                            'marcador' => '{{PRESERVAR_' . count($elementosPreservar) . '}}'
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            \Yii::error("Error identificando elementos a preservar: " . $e->getMessage(), 'procesador-texto');
        }
        
        return $elementosPreservar;
    }

    /**
     * Expandir abreviaturas conocidas desde base de datos
     * 
     * @param string $texto
     * @param string|null $especialidad
     * @param int|null $idRrHh
     * @return string
     */
    private static function expandirAbreviaturasConocidas($texto, $especialidad = null, $idRrHh = null)
    {
        $logger = ConsultaLogger::obtenerInstancia();
        
        if (!class_exists('\common\models\AbreviaturasMedicas')) {
            if ($logger) {
                $logger->registrar(
                    'ERROR',
                    'Clase AbreviaturasMedicas no existe',
                    'No se pueden expandir abreviaturas',
                    ['metodo' => 'ProcesadorTextoMedico::expandirAbreviaturasConocidas']
                );
            }
            return $texto;
        }
        
        try {
            if ($logger) {
                $logger->registrar(
                    'PROCESAMIENTO',
                    'Iniciando expansión de abreviaturas',
                    'Método: ' . ($idRrHh ? 'expandirAbreviaturasConMedico' : 'expandirAbreviaturas'),
                    [
                        'metodo' => 'ProcesadorTextoMedico::expandirAbreviaturasConocidas',
                        'id_rr_hh' => $idRrHh,
                        'especialidad' => $especialidad,
                        'texto_length' => strlen($texto)
                    ]
                );
            }
            
            if ($idRrHh) {
                $resultado = AbreviaturasMedicas::expandirAbreviaturasConMedico($texto, $especialidad, $idRrHh);
                
                $textoResultado = $resultado['texto_procesado'] ?? $texto;
                
                if ($logger) {
                    $abreviaturasEncontradas = $resultado['abreviaturas_encontradas'] ?? [];
                    // Asegurar que sea un array antes de contar
                    if (!is_array($abreviaturasEncontradas) && !($abreviaturasEncontradas instanceof \Countable)) {
                        $abreviaturasEncontradas = [];
                    }
                    $totalEncontradas = is_countable($abreviaturasEncontradas) ? count($abreviaturasEncontradas) : 0;
                        
                    $logger->registrar(
                        'PROCESAMIENTO',
                        'Expansión de abreviaturas completada (con médico)',
                        "Abreviaturas encontradas: {$totalEncontradas}",
                        [
                            'metodo' => 'ProcesadorTextoMedico::expandirAbreviaturasConocidas',
                            'abreviaturas_encontradas' => $abreviaturasEncontradas
                        ]
                    );
                }
                
                return $textoResultado;
            } else {
                $textoResultado = AbreviaturasMedicas::expandirAbreviaturas($texto, $especialidad);
                
                if ($logger) {
                    $logger->registrar(
                        'PROCESAMIENTO',
                        'Expansión de abreviaturas completada',
                        'Texto procesado',
                        [
                            'metodo' => 'ProcesadorTextoMedico::expandirAbreviaturasConocidas',
                            'texto_original_length' => strlen($texto),
                            'texto_procesado_length' => strlen($textoResultado)
                        ]
                    );
                }
                
                return $textoResultado;
            }
        } catch (\Exception $e) {
            \Yii::error("Error expandiendo abreviaturas: " . $e->getMessage(), 'procesador-texto');
            
            if ($logger) {
                $logger->registrar(
                    'ERROR',
                    'Error expandiendo abreviaturas',
                    $e->getMessage(),
                    [
                        'metodo' => 'ProcesadorTextoMedico::expandirAbreviaturasConocidas',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]
                );
            }
            
            return $texto;
        }
    }

    /**
     * Procesar con SymSpell (corrección rápida)
     * 
     * @param string $texto
     * @param string|null $especialidad
     * @return array
     */
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
                'confidence_avg' => 0,
                'words_without_suggestions' => [],
                'cambios_problematicos' => []
            ];
        }
    }

    /**
     * Evaluar si necesita corrección con IA
     * 
     * @param array $resultadoSymSpell
     * @return bool
     */
    private static function evaluarSiNecesitaIA($resultadoSymSpell)
    {
        // Necesita IA si:
        // 1. Hay palabras sin sugerencias de SymSpell
        // 2. Hay cambios problemáticos (baja confianza)
        
        $palabrasSinSugerencias = $resultadoSymSpell['words_without_suggestions'] ?? [];
        $cambiosProblematicos = $resultadoSymSpell['cambios_problematicos'] ?? [];
        
        return !empty($palabrasSinSugerencias) || !empty($cambiosProblematicos);
    }

    /**
     * Guardar correcciones ortográficas realizadas por IA en diccionario_ortografico
     * IMPORTANTE: Solo se guardan y activan automáticamente si confidence = 1.0 (100%)
     * 
     * @param array $cambiosIA
     * @param string|null $especialidad
     * @return void
     */
    private static function guardarCorreccionesIAEnDiccionario($cambiosIA, $especialidad = null)
    {
        if (empty($cambiosIA) || !class_exists('\common\models\DiccionarioOrtografico')) {
            return;
        }
        
        $logger = ConsultaLogger::obtenerInstancia();
        
        foreach ($cambiosIA as $cambio) {
            // Validar que $cambio sea un array
            if (!is_array($cambio)) {
                \Yii::warning("Cambio no es un array: " . print_r($cambio, true), 'procesador-texto');
                continue;
            }
            
            // Extraer valores y asegurar que sean strings
            $originalRaw = $cambio['original'] ?? '';
            $correctedRaw = $cambio['corrected'] ?? '';
            
            // Convertir a string si es array (no debería pasar, pero por seguridad)
            if (is_array($originalRaw)) {
                \Yii::warning("Original es un array: " . print_r($originalRaw, true), 'procesador-texto');
                $originalRaw = implode(' ', $originalRaw);
            }
            if (is_array($correctedRaw)) {
                \Yii::warning("Corrected es un array: " . print_r($correctedRaw, true), 'procesador-texto');
                $correctedRaw = implode(' ', $correctedRaw);
            }
            
            $original = trim((string)$originalRaw);
            $corrected = trim((string)$correctedRaw);
            $confidence = floatval($cambio['confidence'] ?? 0.0);
            
            // Validar que sean palabras válidas
            if (empty($original) || empty($corrected) || 
                strlen($original) < 3 || strlen($corrected) < 3 ||
                $original === $corrected) {
                continue;
            }
            
            // CRÍTICO: Solo guardar si confidence = 1.0 (100%)
            if ($confidence < self::CONFIDENCE_MINIMA_APROBACION) {
                if ($logger) {
                    $logger->registrar(
                        'APRENDIZAJE',
                        "Corrección descartada (confianza < 100%): {$original} → {$corrected}",
                        "Confianza: {$confidence} < 1.0, no se guardará",
                        [
                            'metodo' => 'guardarCorreccionesIAEnDiccionario',
                            'confianza' => $confidence
                        ]
                    );
                }
                continue;
            }
            
            try {
                // Buscar si ya existe esta corrección
                $diccionario = DiccionarioOrtografico::find()
                    ->where([
                        'termino' => $original,
                        'correccion' => $corrected,
                        'tipo' => DiccionarioOrtografico::TIPO_ERROR
                    ])
                    ->andWhere(['or',
                        ['especialidad' => $especialidad],
                        ['especialidad' => null]
                    ])
                    ->one();
                
                if ($diccionario) {
                    // Incrementar frecuencia
                    $diccionario->frecuencia = ($diccionario->frecuencia ?? 0) + 1;
                    
                    $metadata = $diccionario->metadata ?? [];
                    $metadata['fuente_ia'] = true;
                    $metadata['confianza_promedio'] = 1.0;
                    $metadata['ultima_correccion'] = date('Y-m-d H:i:s');
                    
                    // Asegurar que modelo_ia sea string
                    $modeloIA = $cambio['modelo'] ?? 'llama3.1:70b-instruct';
                    if (is_array($modeloIA)) {
                        $modeloIA = 'llama3.1:70b-instruct';
                    }
                    $metadata['modelo_ia'] = (string)$modeloIA;
                    
                    $diccionario->metadata = $metadata;
                    $diccionario->peso = min(10.0, ($diccionario->peso ?? 5.0) + 0.1);
                    $diccionario->activo = 1;
                    $diccionario->save(false);
                    
                    if ($logger) {
                        $logger->registrar(
                            'APRENDIZAJE',
                            "Corrección actualizada (100%): {$original} → {$corrected}",
                            "Frecuencia: {$diccionario->frecuencia}, ACTIVA",
                            [
                                'metodo' => 'guardarCorreccionesIAEnDiccionario',
                                'activo' => true,
                                'confianza' => 1.0
                            ]
                        );
                    }
                } else {
                    // Crear nueva entrada
                    $diccionario = new DiccionarioOrtografico();
                    $diccionario->termino = $original;
                    $diccionario->correccion = $corrected;
                    $diccionario->tipo = DiccionarioOrtografico::TIPO_ERROR;
                    
                    // Asegurar que categoria sea string
                    $categoria = self::detectarCategoriaMedica($original, $corrected);
                    $diccionario->categoria = is_array($categoria) ? null : (string)$categoria;
                    
                    // Asegurar que especialidad sea string o null
                    if (is_array($especialidad)) {
                        \Yii::warning("Especialidad es un array: " . print_r($especialidad, true), 'procesador-texto');
                        $especialidad = null;
                    }
                    $diccionario->especialidad = $especialidad ? (string)$especialidad : null;
                    
                    $diccionario->frecuencia = 1;
                    $diccionario->peso = 10.0;
                    
                    // Asegurar que modelo_ia sea string
                    $modeloIA = $cambio['modelo'] ?? 'llama3.1:70b-instruct';
                    if (is_array($modeloIA)) {
                        $modeloIA = 'llama3.1:70b-instruct';
                    }
                    
                    $diccionario->metadata = [
                        'fuente_ia' => true,
                        'confianza_promedio' => 1.0,
                        'primera_deteccion' => date('Y-m-d H:i:s'),
                        'ultima_correccion' => date('Y-m-d H:i:s'),
                        'modelo_ia' => (string)$modeloIA,
                        'aprobacion_automatica' => true
                    ];
                    $diccionario->activo = 1;
                    
                    if ($diccionario->save(false)) {
                        if ($logger) {
                            $logger->registrar(
                                'APRENDIZAJE',
                                "Nueva corrección guardada (100%): {$original} → {$corrected}",
                                "ACTIVA automáticamente",
                                [
                                    'metodo' => 'guardarCorreccionesIAEnDiccionario',
                                    'activo' => true,
                                    'confianza' => 1.0
                                ]
                            );
                        }
                    }
                }
            } catch (\Exception $e) {
                // Log detallado del error para debugging
                $errorDetails = [
                    'mensaje' => $e->getMessage(),
                    'archivo' => $e->getFile(),
                    'linea' => $e->getLine(),
                    'original' => is_array($original) ? 'ARRAY' : (string)$original,
                    'corrected' => is_array($corrected) ? 'ARRAY' : (string)$corrected,
                    'especialidad' => is_array($especialidad) ? 'ARRAY' : (string)($especialidad ?? 'null'),
                    'cambio_completo' => print_r($cambio, true)
                ];
                \Yii::error("Error guardando corrección IA en diccionario: " . json_encode($errorDetails, JSON_UNESCAPED_UNICODE), 'procesador-texto');
            }
        }
    }

    /**
     * Detectar categoría médica basada en el término corregido
     * 
     * @param string $original
     * @param string $corrected
     * @return string|null
     */
    private static function detectarCategoriaMedica($original, $corrected)
    {
        // Buscar en diccionario médico para inferir categoría
        if (class_exists('\common\models\DiccionarioOrtografico')) {
            $termino = DiccionarioOrtografico::find()
                ->where(['termino' => strtolower($corrected), 'activo' => 1])
                ->one();
            
            if ($termino) {
                $categoria = $termino->categoria;
                // Asegurar que categoria sea string o null, nunca array
                if (is_array($categoria)) {
                    return null;
                }
                return $categoria ? (string)$categoria : null;
            }
        }
        
        // Categorías comunes basadas en patrones (fallback)
        $patronesCategoria = [
            '/ción$/' => 'procedimiento',
            '/itis$/' => 'enfermedad',
            '/oma$/' => 'enfermedad',
            '/algia$/' => 'sintoma',
            '/patía$/' => 'enfermedad',
        ];
        
        foreach ($patronesCategoria as $patron => $categoria) {
            if (preg_match($patron, strtolower($corrected))) {
                return $categoria;
            }
        }
        
        return 'general';
    }

    /**
     * Extraer expansiones de abreviaturas comparando texto original y corregido
     * 
     * @param string $textoOriginal
     * @param string $textoCorregido
     * @return array
     */
    private static function extraerExpansionesDeTextoIA($textoOriginal, $textoCorregido)
    {
        $expansiones = [];
        
        // Detectar abreviaturas que fueron expandidas (texto original corto → texto corregido largo)
        $palabrasOriginales = preg_split('/\s+/', $textoOriginal);
        
        foreach ($palabrasOriginales as $palabraOriginal) {
            $palabraLimpia = preg_replace('/[^a-zA-Z]/', '', $palabraOriginal);
            
            // Si es abreviatura potencial (2-4 letras mayúsculas)
            if (preg_match('/^[A-Z]{2,4}$/', $palabraLimpia)) {
                // Si la palabra original no aparece en el texto corregido, fue expandida
                if (stripos($textoCorregido, $palabraOriginal) === false) {
                    // Buscar expansión alrededor de esta posición
                    $posicion = strpos($textoOriginal, $palabraOriginal);
                    if ($posicion !== false) {
                        $contexto = substr($textoCorregido, max(0, $posicion - 50), 100);
                        
                        $expansiones[] = [
                            'abreviatura' => $palabraLimpia,
                            'expansion' => self::inferirExpansion($palabraLimpia, $contexto),
                            'confidence' => 1.0, // Asumimos 100% si la IA lo expandió
                            'categoria' => null
                        ];
                    }
                }
            }
        }
        
        return $expansiones;
    }

    /**
     * Inferir expansión de abreviatura desde contexto
     * 
     * @param string $abreviatura
     * @param string $contexto
     * @return string
     */
    private static function inferirExpansion($abreviatura, $contexto)
    {
        // Buscar en BD primero
        $abreviaturaModel = AbreviaturasMedicas::find()
            ->where(['abreviatura' => $abreviatura, 'activo' => 1])
            ->one();
        
        if ($abreviaturaModel) {
            return $abreviaturaModel->expansion_completa;
        }
        
        // Si no está en BD, retornar la abreviatura misma
        return $abreviatura;
    }

    /**
     * Guardar expansiones de abreviaturas detectadas por IA en abreviaturas_medicas
     * IMPORTANTE: Solo se guardan y activan automáticamente si confidence = 1.0 (100%)
     * 
     * @param array $expansionesIA
     * @param string|null $especialidad
     * @param string|null $contexto
     * @return void
     */
    private static function guardarExpansionesAbreviaturasIA($expansionesIA, $especialidad = null, $contexto = null)
    {
        if (empty($expansionesIA) || !class_exists('\common\models\AbreviaturasMedicas')) {
            return;
        }
        
        $logger = ConsultaLogger::obtenerInstancia();
        
        foreach ($expansionesIA as $expansion) {
            $abreviatura = trim($expansion['abreviatura'] ?? '');
            $expansionCompleta = trim($expansion['expansion'] ?? '');
            $confidence = floatval($expansion['confidence'] ?? 0.0);
            
            // Validar datos
            if (empty($abreviatura) || empty($expansionCompleta) || 
                strlen($abreviatura) < 2 || strlen($expansionCompleta) < 3 ||
                $abreviatura === $expansionCompleta) {
                continue;
            }
            
            // CRÍTICO: Solo guardar si confidence = 1.0 (100%)
            if ($confidence < self::CONFIDENCE_MINIMA_APROBACION) {
                if ($logger) {
                    $logger->registrar(
                        'APRENDIZAJE',
                        "Expansión descartada (confianza < 100%): {$abreviatura} → {$expansionCompleta}",
                        "Confianza: {$confidence} < 1.0, no se guardará",
                        [
                            'metodo' => 'guardarExpansionesAbreviaturasIA',
                            'confianza' => $confidence
                        ]
                    );
                }
                continue;
            }
            
            try {
                // Buscar si ya existe esta abreviatura
                $abreviaturaModel = AbreviaturasMedicas::find()
                    ->where(['abreviatura' => $abreviatura])
                    ->one();
                
                if ($abreviaturaModel) {
                    // Si ya existe pero con origen diferente, crear variante si es diferente
                    if ($abreviaturaModel->origen !== AbreviaturasMedicas::ORIGEN_LLM) {
                        if ($abreviaturaModel->expansion_completa !== $expansionCompleta) {
                            // Crear nueva entrada con origen LLM
                            $nuevaAbreviatura = new AbreviaturasMedicas();
                            $nuevaAbreviatura->abreviatura = $abreviatura;
                            $nuevaAbreviatura->expansion_completa = $expansionCompleta;
                            $nuevaAbreviatura->categoria = $expansion['categoria'] ?? null;
                            $nuevaAbreviatura->especialidad = $especialidad;
                            $nuevaAbreviatura->contexto = $contexto;
                            $nuevaAbreviatura->origen = AbreviaturasMedicas::ORIGEN_LLM;
                            $nuevaAbreviatura->frecuencia_uso = 1;
                            $nuevaAbreviatura->activo = 1;
                            
                            if ($nuevaAbreviatura->save(false)) {
                                if ($logger) {
                                    $logger->registrar(
                                        'APRENDIZAJE',
                                        "Nueva expansión IA guardada (100%): {$abreviatura} → {$expansionCompleta}",
                                        "ACTIVA automáticamente",
                                        ['metodo' => 'guardarExpansionesAbreviaturasIA']
                                    );
                                }
                            }
                        }
                    } else {
                        // Ya existe con origen LLM, incrementar frecuencia
                        $abreviaturaModel->frecuencia_uso = ($abreviaturaModel->frecuencia_uso ?? 0) + 1;
                        
                        if ($especialidad && !$abreviaturaModel->especialidad) {
                            $abreviaturaModel->especialidad = $especialidad;
                        }
                        
                        $abreviaturaModel->activo = 1;
                        $abreviaturaModel->save(false);
                        
                        if ($logger) {
                            $logger->registrar(
                                'APRENDIZAJE',
                                "Expansión IA actualizada (100%): {$abreviatura}",
                                "Frecuencia: {$abreviaturaModel->frecuencia_uso}, ACTIVA",
                                [
                                    'metodo' => 'guardarExpansionesAbreviaturasIA',
                                    'activo' => true,
                                    'confianza' => 1.0
                                ]
                            );
                        }
                    }
                } else {
                    // Crear nueva abreviatura con origen LLM
                    $nuevaAbreviatura = new AbreviaturasMedicas();
                    $nuevaAbreviatura->abreviatura = $abreviatura;
                    $nuevaAbreviatura->expansion_completa = $expansionCompleta;
                    $nuevaAbreviatura->categoria = $expansion['categoria'] ?? self::detectarCategoriaAbreviatura($abreviatura, $expansionCompleta);
                    $nuevaAbreviatura->especialidad = $especialidad;
                    $nuevaAbreviatura->contexto = $contexto;
                    $nuevaAbreviatura->origen = AbreviaturasMedicas::ORIGEN_LLM;
                    $nuevaAbreviatura->frecuencia_uso = 1;
                    $nuevaAbreviatura->activo = 1;
                    
                    if ($nuevaAbreviatura->save(false)) {
                        if ($logger) {
                            $logger->registrar(
                                'APRENDIZAJE',
                                "Nueva abreviatura IA guardada (100%): {$abreviatura} → {$expansionCompleta}",
                                "ACTIVA automáticamente",
                                [
                                    'metodo' => 'guardarExpansionesAbreviaturasIA',
                                    'activo' => true,
                                    'confianza' => 1.0
                                ]
                            );
                        }
                    }
                }
            } catch (\Exception $e) {
                \Yii::error("Error guardando expansión IA: " . $e->getMessage(), 'procesador-texto');
            }
        }
    }

    /**
     * Detectar categoría de abreviatura basada en su expansión
     * 
     * @param string $abreviatura
     * @param string $expansion
     * @return string|null
     */
    private static function detectarCategoriaAbreviatura($abreviatura, $expansion)
    {
        $patronesCategoria = [
            '/medicamento|fármaco|droga/i' => 'medicamento',
            '/medición|medida|valor/i' => 'medicion',
            '/procedimiento|examen|prueba/i' => 'procedimiento',
            '/anatomía|estructura|órgano/i' => 'anatomia',
            '/síntoma|signo/i' => 'sintoma',
        ];
        
        foreach ($patronesCategoria as $patron => $categoria) {
            if (preg_match($patron, $expansion)) {
                return $categoria;
            }
        }
        
        return 'general';
    }

    /**
     * Guardar información de correcciones con ID único por pestaña
     * 
     * @param array $resultado
     * @param string $tabId
     * @return string|null
     */
    private static function guardarInfoCorrecciones($resultado, $tabId = null)
    {
        try {
            if (!$tabId) {
                $tabId = self::generarTabId();
            }

            $correcciones = [
                'tab_id' => $tabId,
                'timestamp' => time(),
                'total_cambios' => 0,
                'cambios_automaticos' => [],
                'estadisticas' => [
                    'ia_local' => 0,
                    'symspell' => 0
                ]
            ];

            // Procesar cambios de IA local
            if (isset($resultado['ia_changes'])) {
                foreach ($resultado['ia_changes'] as $cambio) {
                    $correcciones['cambios_automaticos'][] = [
                        'original' => $cambio['original'],
                        'corregido' => $cambio['corrected'],
                        'confianza' => $cambio['confidence'] ?? 1.0,
                        'metodo' => $cambio['method'] ?? 'ia_local'
                    ];
                    $correcciones['estadisticas']['ia_local']++;
                    $correcciones['total_cambios']++;
                }
            }

            // Procesar cambios de SymSpell
            if (isset($resultado['symspell_changes'])) {
                foreach ($resultado['symspell_changes'] as $cambio) {
                    $correcciones['cambios_automaticos'][] = [
                        'original' => $cambio['original'],
                        'corregido' => $cambio['corrected'],
                        'confianza' => $cambio['confidence'] ?? 1.0,
                        'metodo' => 'symspell'
                    ];
                    $correcciones['estadisticas']['symspell']++;
                    $correcciones['total_cambios']++;
                }
            }

            // Guardar en cache con TTL de 1 hora
            $cacheKey = "correcciones_texto_{$tabId}";
            \Yii::$app->cache->set($cacheKey, $correcciones, 3600);

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
     * Obtener información de correcciones por ID de pestaña
     * 
     * @param string $tabId
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
            return [
                'tab_id' => $correcciones['tab_id'],
                'total_cambios' => $correcciones['total_cambios'],
                'cambios_automaticos' => $correcciones['cambios_automaticos']
            ];
        }
        
        return null;
    }

    /**
     * Validar corrección de IA para evitar cambios no deseados
     * Rechaza correcciones que agreguen texto nuevo o cambien la estructura
     * 
     * @param string $textoCorregido Texto corregido por IA
     * @param string $textoOriginal Texto original antes de IA
     * @return string Texto validado (puede revertir a original si hay problemas)
     */
    private static function validarCorreccionIA($textoCorregido, $textoOriginal)
    {
        // Normalizar espacios y saltos de línea para comparación
        $normalizadoOriginal = preg_replace('/\s+/', ' ', trim($textoOriginal));
        $normalizadoCorregido = preg_replace('/\s+/', ' ', trim($textoCorregido));
        
        // Contar palabras en original y corregido
        $palabrasOriginal = str_word_count($normalizadoOriginal, 0, 'áéíóúüñÁÉÍÓÚÜÑ');
        $palabrasCorregido = str_word_count($normalizadoCorregido, 0, 'áéíóúüñÁÉÍÓÚÜÑ');
        
        // Permitir hasta 30% más palabras para permitir expansión de abreviaturas médicas
        // Ejemplo: "h 5" → "horizontal de 5" agrega palabras pero es válido
        if ($palabrasCorregido > $palabrasOriginal * 1.3) {
            \Yii::warning("Corrección IA rechazada: se agregaron demasiadas palabras nuevas ({$palabrasCorregido} vs {$palabrasOriginal})", 'procesador-texto');
            return $textoOriginal;
        }
        
        // Permitir hasta 40% de diferencia en longitud para permitir expansión de abreviaturas
        // Ejemplo: "aprox." → "aproximadamente" aumenta la longitud
        $diferenciaLongitud = abs(strlen($normalizadoCorregido) - strlen($normalizadoOriginal));
        $porcentajeDiferencia = strlen($normalizadoOriginal) > 0 
            ? ($diferenciaLongitud / strlen($normalizadoOriginal)) * 100 
            : 0;
        
        if ($porcentajeDiferencia > 40) {
            \Yii::warning("Corrección IA rechazada: diferencia de longitud muy grande ({$porcentajeDiferencia}%)", 'procesador-texto');
            return $textoOriginal;
        }
        
        // Verificar que no se hayan agregado frases comunes que indican interpretación
        $frasesProhibidas = [
            'cita médica',
            'control médico',
            'documento',
            'texto corregido',
            'corrección',
            'corregido:'
        ];
        
        foreach ($frasesProhibidas as $frase) {
            if (stripos($normalizadoCorregido, $frase) !== false && 
                stripos($normalizadoOriginal, $frase) === false) {
                \Yii::warning("Corrección IA rechazada: se agregó frase no deseada: '{$frase}'", 'procesador-texto');
                return $textoOriginal;
            }
        }
        
        // Verificar específicamente si termina con "Corregido:" o variaciones
        if (preg_match('/\s*(Corregido|Texto corregido|Corrección):?\s*$/i', $normalizadoCorregido) &&
            !preg_match('/\s*(Corregido|Texto corregido|Corrección):?\s*$/i', $normalizadoOriginal)) {
            \Yii::warning("Corrección IA rechazada: texto termina con 'Corregido:'", 'procesador-texto');
            // Limpiar el sufijo y retornar
            $textoCorregido = preg_replace('/\s*(Corregido|Texto corregido|Corrección):?\s*$/i', '', $normalizadoCorregido);
            return trim($textoCorregido);
        }
        
        return $textoCorregido;
    }

    /**
     * Generar ID único para pestaña
     * 
     * @return string
     */
    private static function generarTabId()
    {
        return 'tab_' . uniqid() . '_' . time();
    }
}

