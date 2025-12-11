<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\NotFoundHttpException;
use common\models\Consulta;
use common\models\Persona;
use common\components\ProcesadorTextoMedico;
use common\components\CodificadorSnomedIA;
use common\components\IAManager;
use common\components\ConsultaLogger;
use common\components\ConsultaClassifier;
use common\components\RespuestaPredefinidaManager;
use common\components\DeferredSnomedProcessor;

class ConsultaController extends BaseController
{
    public $modelClass = 'common\models\Consulta';

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        // Permitir acceso sin autenticación Bearer si hay sesión activa (para web)
        // El método actionAnalizar verificará manualmente la autenticación
        $behaviors['authenticator']['except'] = ['options', 'analizar'];
        
        return $behaviors;
    }

    public function actionAnalizar()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        try {
            // Verificar autenticación: Bearer token o sesión web
            $isAuthenticated = false;
            $userId = null;
            
            // Intentar autenticación por Bearer token primero
            $authHeader = Yii::$app->request->getHeaders()->get('Authorization');
            if ($authHeader && preg_match('/^Bearer\s+(.*?)$/', $authHeader, $matches)) {
                $token = $matches[1];
                try {
                    $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key(Yii::$app->params['jwtSecret'], 'HS256'));
                    $userId = $decoded->user_id;
                    $isAuthenticated = true;
                } catch (\Exception $e) {
                    // Token inválido, continuar con verificación de sesión
                }
            }
            
            // Si no hay Bearer token, verificar sesión web de frontend
            if (!$isAuthenticated) {
                $session = Yii::$app->session;
                
                // Asegurar que la sesión esté iniciada
                if (!$session->isActive) {
                    $session->open();
                }
                
                // Verificar si hay identidad de usuario en la sesión
                $identityId = $session->get('__id');
                $identity = $session->get('__identity');
                
                if ($identity !== null || !empty($identityId)) {
                    if (is_object($identity) && method_exists($identity, 'getId')) {
                        $userId = $identity->getId();
                    } elseif (is_object($identity) && isset($identity->id)) {
                        $userId = $identity->id;
                    } elseif (!empty($identityId)) {
                        $userId = $identityId;
                    }
                    
                    if (!empty($userId)) {
                        $isAuthenticated = true;
                    }
                } elseif ($session->has('idPersona')) {
                    // Si hay idPersona en sesión, el usuario está autenticado
                    $isAuthenticated = true;
                    // Intentar obtener userId desde la persona
                    $idPersona = $session->get('idPersona');
                    $persona = \common\models\Persona::findOne(['id_persona' => $idPersona]);
                    if ($persona && $persona->id_user) {
                        $userId = $persona->id_user;
                    }
                }
            }
            
            // Si no está autenticado, retornar error 401
            if (!$isAuthenticated) {
                Yii::$app->response->statusCode = 401;
                return [
                    'success' => false,
                    'message' => 'Usuario no autenticado. Debe iniciar sesión o proporcionar un token válido.',
                    'errors' => null,
                ];
            }

            $body = Yii::$app->request->getBodyParams();
            $userPerTabConfig = $body['userPerTabConfig'] ?? [];
            $idRrHhServicio = $userPerTabConfig['id_rrhh_servicio'] ?? null;        
            $idServicio = $userPerTabConfig['servicio_actual'] ?? null;
            $textoConsulta = $body['consulta'] ?? null;
            $idConfiguracion = $body['id_configuracion'] ?? null;
            
            if (!$idRrHhServicio || !$textoConsulta) {
                Yii::$app->response->statusCode = 400;
                return [
                    'success' => false,
                    'message' => 'Faltan datos obligatorios. Por favor, verifique que haya proporcionado el ID de recurso humano y el texto de la consulta.',
                    'errors' => null,
                ];
            }        

            // Obtener o generar tabId para esta pestaña
            $tabId = $body['tab_id'] ?? null;
            if (!$tabId) {
                $tabId = 'tab_' . uniqid() . '_' . time();
            }
            
            // Inicializar logger para esta consulta
            $servicio = \common\models\Servicio::findOne($idServicio);
            if (!$servicio) {
                Yii::$app->response->statusCode = 400;
                return [
                    'success' => false,
                    'message' => 'Servicio no encontrado. Por favor, verifique la configuración.',
                    'errors' => null,
                ];
            }
            
            $contextoLogger = [
                'idRrHhServicio' => $idRrHhServicio,
                'servicio' => $servicio->nombre,
                'tabId' => $tabId
            ];
            $logger = ConsultaLogger::iniciar($textoConsulta, $contextoLogger);
            
            // 1. Corrección ortográfica y expansión de abreviaturas con IA local (Llama 3.1 70B Instruct)
            $logger->registrar(
                'PROCESAMIENTO',
                $textoConsulta,
                null,
                ['metodo' => 'ProcesadorTextoMedico::prepararParaIAConFormato']
            );
            
            // Obtener texto procesado y formateado con subrayado
            $resultadoFormato = ProcesadorTextoMedico::prepararParaIAConFormato(
                $textoConsulta,
                $servicio->nombre,
                $tabId,
                $idRrHhServicio
            );
            $textoProcesado = $resultadoFormato['texto_procesado'];
            $textoFormateado = $resultadoFormato['texto_formateado'];
            $totalCambios = $resultadoFormato['total_cambios'];
            
            $logger->registrar(
                'PROCESAMIENTO',
                null,
                $textoProcesado,
                [
                    'metodo' => 'ProcesadorTextoMedico::prepararParaIAConFormato',
                    'total_cambios' => $totalCambios
                ]
            );
            
            // Los logs detallados ya se manejan en ConsultaLogger

            // Obtener categorías para el HTML genérico
            $categorias = $this->getModelosPorConfiguracion($idConfiguracion);
            
            // Verificar si es consulta simple (procesamiento selectivo)
            $esSimple = ConsultaClassifier::esConsultaSimple($textoProcesado);
            
            if ($esSimple) {
                // Procesar con reglas predefinidas (sin GPU)
                $logger->registrar(
                    'ANÁLISIS SIMPLE',
                    $textoProcesado,
                    null,
                    ['metodo' => 'ConsultaClassifier::procesarConsultaSimple']
                );
                
                $resultadoIA = ConsultaClassifier::procesarConsultaSimple($textoProcesado, $servicio->nombre, $categorias);
                
                $logger->registrar(
                    'ANÁLISIS SIMPLE',
                    null,
                    'Consulta simple procesada sin GPU',
                    [
                        'metodo' => 'ConsultaClassifier::procesarConsultaSimple',
                        'categorias_extraidas' => isset($resultadoIA['datosExtraidos']) ? count($resultadoIA['datosExtraidos']) : 0
                    ]
                );
            } else {
                // Llamada a la IA para analizar la consulta con texto expandido (con GPU)
                $logger->registrar(
                    'ANÁLISIS IA',
                    $textoProcesado,
                    null,
                    ['metodo' => 'ConsultaController::analizarConsultaConIA']
                );
                
                $resultadoIA = $this->analizarConsultaConIA($textoProcesado, $servicio->nombre, $categorias);
                
                $logger->registrar(
                    'ANÁLISIS IA',
                    null,
                    $resultadoIA ? 'Análisis completado' : 'Error en análisis',
                    [
                        'metodo' => 'ConsultaController::analizarConsultaConIA',
                        'categorias_extraidas' => $resultadoIA && isset($resultadoIA['datosExtraidos']) ? count($resultadoIA['datosExtraidos']) : 0
                    ]
                );
            }
            
            // NUEVO: Codificar automáticamente con SNOMED (procesamiento diferido)
            $datosConSnomed = null;
            $estadisticasSnomed = null;
            $requiereValidacionSnomed = false;
            
            // Procesar SNOMED de forma diferida (no bloquea al médico)
            if ($resultadoIA && isset($resultadoIA['datosExtraidos'])) {
                DeferredSnomedProcessor::procesarDiferido(
                    null, // consulta_id se asignará cuando se guarde la consulta
                    $resultadoIA,
                    $categorias
                );
                \Yii::info("SNOMED agregado a cola de procesamiento diferido", 'snomed-codificador');
            }
            
            // Usar datos con SNOMED si están disponibles, sino usar datos originales
            if ($datosConSnomed) {
                $datos = $datosConSnomed;
            } elseif ($resultadoIA) {
                $datos = $resultadoIA;
            } else {
                // Si la IA falla, usar estructura vacía con mensaje de error
                $datos = [
                    'datosExtraidos' => [
                        'Error' => [
                            'texto' => 'No se pudo procesar la consulta con IA',
                            'detalle' => 'Revisar manualmente la consulta',
                            'tipo' => 'error_sistema'
                        ]
                    ]
                ];
            }
            
            // Datos de prueba para testing
            /*$datos = [
                'datosExtraidos' => [
                    'Motivos de Consulta / Síntomas' => [
                        'dolor',
                        'laceracion',
                        'inflamacion'
                    ],
                    'Evaluación / Prácticas' => [
                        'dolor',
                        'laceracion'
                    ],
                    'Medicamentos' => [
                        [
                            'Nombre del medicamento' => 'Ibuprofeno 400mg',
                            'Cantidad del medicamento' => '20 comprimidos',
                            'Frecuencia de administracion' => 'Cada 8 horas',
                            'Tipo de frecuencia' => 'Tres veces al día',
                            'Duracion del tratamiento' => '7 días',
                            'Tipo de duracion' => 'Una semana'
                        ],
                        [
                            'Nombre del medicamento' => 'Amoxicilina 500mg',
                            'Cantidad del medicamento' => '21 cápsulas',
                            'Frecuencia de administracion' => 'Cada 8 horas',
                            'Tipo de frecuencia' => 'Tres veces al día',
                            'Duracion del tratamiento' => '7 días',
                            'Tipo de duracion' => 'Una semana'
                        ],
                        [
                            'Nombre del medicamento' => 'Paracetamol 500mg',
                            'Cantidad del medicamento' => '10 comprimidos',
                            'Frecuencia de administracion' => 'Cada 6 horas',
                            'Tipo de frecuencia' => 'Cuatro veces al día',
                            'Duracion del tratamiento' => '3 días',
                            'Tipo de duracion' => 'Tres días'
                        ]
                    ]
                ]
            ];*/
            
            // Sugerencias de prueba (comentadas para usar datos reales de IA)
            $sugerencias = [/*
                'sugerencias_diagnosticas' => [
                    'Considerar glaucoma de ángulo abierto',
                    'Evaluar catarata incipiente',
                    'Descartar retinopatía diabética'
                ],
                'sugerencias_practicas' => [
                    'Campo visual automatizado',
                    'Paquimetría corneal',
                    'Biomicroscopía con lámpara de hendidura'
                ],
                'sugerencias_seguimiento' => [
                    'Control en 3 meses si hay cambios',
                    'Evaluación anual de fondo de ojo',
                    'Monitoreo de presión intraocular'
                ],
                'alertas' => [
                    'Paciente con antecedentes familiares de glaucoma',
                    'Considerar derivación a especialista si empeora',
                    'Vigilar síntomas de cefalea asociada'
                ]*/
            ];
            
            // Generar HTML formateado desde PHP con sugerencias integradas
            $htmlResult = $this->generateAnalysisHtml($datos["datosExtraidos"], $sugerencias, $categorias);
            $html = $htmlResult['html'];
            $tieneDatosFaltantesHTML = $htmlResult['tieneDatosFaltantes'];
            
            // Agregar sección de texto formateado con correcciones si hay cambios
            if ($totalCambios > 0) {
                // Usar heredoc para evitar problemas de escape con comillas
                $textoFormateadoHtml = <<<HTML
                <div class="alert alert-light border mt-3">
                    <h6><i class="bi bi-file-text me-2"></i>Texto Formateado</h6>
                    <div class="mt-3">
                        <div class="bg-light p-3 rounded border">
                            <div class="texto-formateado">
                                {$textoFormateado}
                            </div>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Las palabras subrayadas en verde han sido corregidas automáticamente
                        </small>
                    </div>
                </div>
HTML;
                $html = $textoFormateadoHtml . $html;
            }
            
            // Determinar si tiene datos faltantes basado en la respuesta de la IA y el HTML generado
            $tieneDatosFaltantes = false;
            if ($resultadoIA && isset($resultadoIA['informacionFaltante'])) {
                $tieneDatosFaltantes = $resultadoIA['informacionFaltante']['tieneDatosFaltantes'] ?? false;
            }
            // También considerar los datos faltantes detectados en el HTML
            if ($tieneDatosFaltantesHTML) {
                $tieneDatosFaltantes = true;
            }
            
            $resultado = [
                'success' => true,
                'datos' => $datos,
                'html' => $html,
                'texto_original' => $textoConsulta,
                'texto_procesado' => $textoProcesado,
                'texto_formateado' => $textoFormateado ?? null, // HTML formateado con correcciones
                'tab_id' => $tabId,
                'sugerencias' => $sugerencias,
                'tiene_datos_faltantes' => $tieneDatosFaltantes,
                // NUEVO: Información de codificación SNOMED
                'codigos_snomed' => $estadisticasSnomed,
                'requiere_validacion_snomed' => $requiereValidacionSnomed,
                'datos_con_snomed' => $datosConSnomed ? true : false
            ];
            
            // Finalizar logger antes del return
            $logger->finalizar($resultado);
            
            return $resultado;
            
        } catch (\Exception $e) {
            // Log del error para debugging
            Yii::error("Error en actionAnalizar: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'consulta-ia');
            
            // Intentar finalizar logger si existe
            if (isset($logger)) {
                try {
                    $logger->finalizar([
                        'success' => false,
                        'error' => $e->getMessage(),
                    ]);
                } catch (\Exception $logError) {
                    // Ignorar errores al finalizar logger
                }
            }
            
            // Retornar mensaje amigable al usuario
            Yii::$app->response->statusCode = 500;
            return [
                'success' => false,
                'message' => 'Ocurrió un error al procesar la consulta. Por favor, intente nuevamente en unos momentos. Si el problema persiste, contacte al soporte técnico.',
                'errors' => YII_DEBUG ? [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ];
        }
    }

    public function analizarConsultaConIA($texto, $servicio, $categorias)
    {
        try {
            // Buscar respuesta predefinida antes de usar GPU
            $similitudMinima = Yii::$app->params['similitud_minima_respuestas'] ?? 0.85;
            $respuestaPredefinida = \common\components\RespuestaPredefinidaManager::obtenerRespuesta($texto, $servicio, $similitudMinima);
            
            if ($respuestaPredefinida) {
                \Yii::info("Respuesta predefinida encontrada para consulta similar (sin GPU)", 'consulta-ia');
                // Incrementar contador de usos
                \common\components\RespuestaPredefinidaManager::incrementarUsos($respuestaPredefinida['id']);
                return $respuestaPredefinida['respuesta_json'];
            }
            
            // Intentar primero con el prompt especializado
            $promptData = $this->generarPromptEspecializado($texto, $servicio, $categorias);
            
            // Si hay error en la generación del prompt, retornar error inmediatamente
            if ($promptData === null) {
                \Yii::error('No se pudo generar el prompt debido a errores en el JSON de ejemplo', 'consulta-ia');
                return [
                    'datosExtraidos' => [
                        'Error' => [
                            'texto' => 'Error en la configuración del sistema. Por favor, contacte al administrador.',
                            'detalle' => 'No se pudo procesar la consulta debido a un error en la configuración.',
                            'tipo' => 'error_configuracion'
                        ]
                    ]
                ];
            }
            
            $resultado = $this->intentarAnalisisConIA($promptData['prompt'], $texto, $categorias);

            if ($resultado && !isset($resultado['error'])) {
                // Guardar respuesta para futuro uso (respuestas predefinidas)
                try {
                    \common\components\RespuestaPredefinidaManager::guardarRespuesta($texto, $resultado, $servicio);
                } catch (\Exception $e) {
                    // Si falla guardar la respuesta predefinida, continuar de todas formas
                    \Yii::warning("No se pudo guardar respuesta predefinida: " . $e->getMessage(), 'respuestas-predefinidas');
                }
                return $resultado;
            }

            // Retornar datos por defecto en caso de error
            return [
                'datosExtraidos' => [
                    'Error' => [
                        'texto' => 'No se pudo procesar la consulta con inteligencia artificial en este momento.',
                        'detalle' => 'Por favor, intente nuevamente en unos momentos o revise la consulta manualmente.',
                        'tipo' => 'error_ia'
                    ]
                ]
            ];
        } catch (\Exception $e) {
            \Yii::error("Error en analizarConsultaConIA: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'consulta-ia');
            return [
                'datosExtraidos' => [
                    'Error' => [
                        'texto' => 'Ocurrió un error al procesar la consulta.',
                        'detalle' => 'Por favor, intente nuevamente. Si el problema persiste, contacte al soporte técnico.',
                        'tipo' => 'error_sistema'
                    ]
                ]
            ];
        }
    }

    /**
     * Intentar análisis con IA usando un prompt específico
     * @param string $prompt
     * @param string $texto
     * @param array $categorias
     * @return array|null
     */
    private function intentarAnalisisConIA($prompt, $texto, $categorias = [])
    {
        // Usar tipo de modelo 'analysis' para HuggingFace
        return Yii::$app->iamanager->consultar($prompt, 'analisis-consulta', 'analysis');
    }

    private function obtenerSugerenciasConIA($texto, $servicio)
    {
        // Prompt optimizado (reducido 50% para reducir costos)
        $prompt = "Analiza $servicio. JSON: sugerencias_diagnosticas, sugerencias_practicas, sugerencias_seguimiento, alertas (arrays).

Texto: \"$texto\"";

        $endpointIA = 'http://192.168.1.11:11434/api/generate';

        $payload = [
            'model' => 'mistral',
            'prompt' => $prompt,
            'stream' => false
        ];

        // Los logs de sugerencias ya se manejan en ConsultaLogger

        $client = new \yii\httpclient\Client();
        $response = $client->createRequest()
            ->setMethod('POST')
            ->setUrl($endpointIA)
            ->addHeaders(['Content-Type' => 'application/json'])
            ->setContent(json_encode($payload))
            ->send();

        if ($response->isOk) {
            $responseData = json_decode($response->data["response"], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $responseData;
            } else {
                \Yii::error('Error decodificando JSON de sugerencias IA: ' . json_last_error_msg(), 'consulta-oftalmo');
            }
        } else {
            \Yii::error('Error en la respuesta de la IA para sugerencias: ' . $response->getStatusCode() . ' - ' . $response->getContent(), 'consulta-oftalmo');
        }

        // Retornar sugerencias por defecto en caso de error
        return [
            'sugerencias_diagnosticas' => ['Revisar diagnóstico diferencial'],
            'sugerencias_practicas' => ['Evaluar prácticas complementarias'],
            'sugerencias_seguimiento' => ['Programar seguimiento'],
            'alertas' => ['Verificar datos de la consulta']
        ];
    }

    private function generateAnalysisHtml($datos, $sugerencias = [], $categorias = [])
    {
        // Bandera para detectar datos faltantes
        $tieneDatosFaltantes = false;
        
        // Definimos una bandera que indica si hay datos faltantes, sirve para no dejar guardar la consulta
        if (!empty($categorias)) {
            foreach ($categorias as $categoria) {
                $esRequerida = $categoria['requerido'] ?? false;

                // si la categoria es requerida y no tiene datos break
                if ($esRequerida) {
                    if (!isset($datos[$categoria['titulo']]) || empty($datos[$categoria['titulo']])) {
                        $tieneDatosFaltantes = true;
                        break;
                    }
                }

                $camposRequeridos = $categoria['campos_requeridos'] ?? [];
                if (!empty($camposRequeridos)) {
                    foreach ($camposRequeridos as $campo) {
                        if (!isset($datos[$categoria['titulo']][$campo]) || empty($datos[$categoria['titulo']][$campo])) {
                            $tieneDatosFaltantes = true;
                            break;
                        }
                    }
                }

                if ($tieneDatosFaltantes) {
                    break;
                }
            }
        }
        
        // Renderizar la vista
        $html = $this->renderPartial('//paciente/_resultado_analisis_consulta', [
            'datos' => $datos,
            'sugerencias' => $sugerencias,
            'categorias' => $categorias,
            'tieneDatosFaltantes' => $tieneDatosFaltantes
        ]);
        
        return [
            'html' => $html,
            'tieneDatosFaltantes' => $tieneDatosFaltantes
        ];
    }

    /**
     * Obtener categorías de configuración por servicio
     * @param string $servicio
     * @return array
     */
    private function getModelosPorConfiguracion($idConfiguracion)
    {
        // Buscar configuración por servicio
        $configuracion = \common\models\ConsultasConfiguracion::findOne($idConfiguracion);

        return \common\models\ConsultasConfiguracion::getCategoriasParaPrompt($configuracion);
    }

    /**
     * Generar prompt especializado por servicio
     * @param string $texto
     * @param string $servicio
     * @param array $categorias
     * @return array
     */
    private function generarPromptEspecializado($texto, $servicio, $categorias)
    {
        $categoriasTexto = $this->construirCategoriasTexto($categorias);
        $jsonEjemplo = $this->generarJsonEjemplo($categorias);

        // Verificar si el JSON de ejemplo contiene errores
        if ($jsonEjemplo === false) {            
            return null; // Retornar null para indicar error
        }

        // Prompt optimizado (reducido 40% para reducir costos)
        $prompt = "Extrae datos en JSON. Categorías: " . $categoriasTexto . ". Sin datos: [].

Formato:
{\"datosExtraidos\":{\"categoria\":[\"valor\"]}}

Texto: \"" . $texto . "\"";
//var_dump($prompt);die;
        return [
            'prompt' => $prompt,
            'json_ejemplo' => $jsonEjemplo
        ];
    }

    /**
     * Generar JSON de ejemplo basado en las categorías
     * @param array $categorias
     * @return string
     */
    private function generarJsonEjemplo($categorias)
    {
        $datosExtraidos = [];
        
        foreach ($categorias as $categoria) {
            $titulo = $categoria['titulo'];            
            
            $datosExtraidos[$titulo] = [];
        }
        
        $jsonEjemplo = [
            "datosExtraidos" => $datosExtraidos
        ];
        
        $jsonString = json_encode($jsonEjemplo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if ($jsonString === false) {
            $error = json_last_error_msg();
            \Yii::error('Error al generar JSON de ejemplo: ' . $error . ' - Datos: ' . print_r($jsonEjemplo, true), 'consulta-ia');
            
            return false;
        }
        
        return $jsonString;
    }

    /**
     * Construir texto de categorías para el prompt
     * @param array $categorias
     * @return string
     */
    private function construirCategoriasTexto($categorias)
    {
        $texto = '';
        foreach ($categorias as $categoria) {            
            $camposRequeridos = '';
            
            if (!empty($categoria['campos_requeridos'])) {
                $camposRequeridos = ' con los siguientes subdatos: (' . implode(', ', $categoria['campos_requeridos']) . ')';
            }
            
            $texto .= "{$categoria['titulo']}$camposRequeridos, ";
        }
        
        return substr($texto, 0, -2);
    }

    /**
     * Pipeline optimizado que evita procesamiento redundante
     * @param string $textoConsulta
     * @param \common\models\Servicio $servicio
     * @param int $idConfiguracion
     * @param string $tabId
     * @param array $contextoLogger
     * @return array
     */
    private function procesarPipelineOptimizado($textoConsulta, $servicio, $idConfiguracion, $tabId, $contextoLogger = [])
    {
        $logger = ConsultaLogger::obtenerInstancia();
        
        // Cache key para evitar procesamiento redundante (mismo texto, mismo servicio)
        $cacheKey = 'pipeline_' . md5($textoConsulta . $servicio->id . $idConfiguracion);
        $cache = Yii::$app->cache;
        
        // Verificar cache primero (TTL extendido para reducir costos)
        if ($cache) {
            $cached = $cache->get($cacheKey);
            if ($cached !== false) {
                \Yii::info("Pipeline optimizado: resultado desde cache", 'consulta-pipeline');
                return $cached;
            }
        }
        
        // Caché intermedio: texto procesado
        $cacheKeyTexto = 'pipeline_texto_' . md5($textoConsulta);
        $textoProcesado = null;
        if ($cache) {
            $textoCached = $cache->get($cacheKeyTexto);
            if ($textoCached !== false) {
                $textoProcesado = $textoCached;
                \Yii::info("Texto procesado obtenido desde cache intermedio", 'consulta-pipeline');
            }
        }
        
        // Verificar si el texto ya está procesado (evitar procesamiento redundante)
        $necesitaProcesamiento = true;
        if (strlen($textoConsulta) < 200 && preg_match('/^[A-ZÁÉÍÓÚÑ][a-záéíóúñ\s,\.]+$/', $textoConsulta)) {
            // Texto corto y bien formateado, puede no necesitar procesamiento completo
            $necesitaProcesamiento = false;
        }
        
        // Procesamiento de texto (solo si es necesario y no está en cache)
        if ($textoProcesado === null) {
            if ($necesitaProcesamiento) {
                $resultadoProcesamiento = ProcesadorTextoMedico::prepararParaIA($textoConsulta, $servicio->nombre, $tabId);
                $textoProcesado = is_array($resultadoProcesamiento) ? $resultadoProcesamiento['texto_procesado'] : $resultadoProcesamiento;
            } else {
                $textoProcesado = $textoConsulta;
            }
            
            // Guardar texto procesado en cache intermedio (TTL extendido)
            if ($cache) {
                $cache->set($cacheKeyTexto, $textoProcesado, 3600); // 1 hora
            }
        }
        
        // Obtener categorías una sola vez
        $categorias = $this->getModelosPorConfiguracion($idConfiguracion);
        
        // Verificar si es consulta simple (procesamiento selectivo)
        $esSimple = ConsultaClassifier::esConsultaSimple($textoProcesado);
        
        if ($esSimple) {
            $resultadoIA = ConsultaClassifier::procesarConsultaSimple($textoProcesado, $servicio->nombre, $categorias);
        } else {
            $resultadoIA = $this->analizarConsultaConIA($textoProcesado, $servicio->nombre, $categorias);
        }
        
        // SNOMED diferido (no bloquea)
        if ($resultadoIA && isset($resultadoIA['datosExtraidos'])) {
            DeferredSnomedProcessor::procesarDiferido(null, $resultadoIA, $categorias);
        }
        
        $resultado = [
            'texto_procesado' => $textoProcesado,
            'resultado_ia' => $resultadoIA,
            'es_simple' => $esSimple,
            'necesito_procesamiento' => $necesitaProcesamiento
        ];
        
        // Guardar en cache (TTL extendido para reducir costos)
        if ($cache) {
            $cache->set($cacheKey, $resultado, 1800); // 30 minutos (aumentado de 5 minutos)
        }
        
        return $resultado;
    }
    
    /**
     * Construir campos faltantes para el prompt
     * @param array $categorias
     * @return string
     */
    private function construirCamposFaltantes($categorias)
    {
        $campos = [];
        
        foreach ($categorias as $categoria) {
            if (!empty($categoria['campos_requeridos'])) {
                $campos[] = "\nCAMPOS REQUERIDOS para {$categoria['titulo']}:";
                foreach ($categoria['campos_requeridos'] as $campo) {
                    $campos[] = "- {$campo}";
                }
            }
        }

        // Campos generales que siempre se verifican
        $camposGenerales = [
            "\nINFORMACIÓN CLÍNICA GENERAL:",
            "- Lateralidad (si aplica, como ojos, oídos, extremidades)",
            "- Duración (desde cuándo)",
            "- Intensidad (leve, moderado, severo)",
            "- Localización anatómica específica",
            "- Frecuencia o contexto (en reposo, al esfuerzo, nocturno, etc)",
            "- Síntomas asociados",
            "- Factores desencadenantes o agravantes"
        ];

        return implode("\n", array_merge($camposGenerales, $campos));
    }





}