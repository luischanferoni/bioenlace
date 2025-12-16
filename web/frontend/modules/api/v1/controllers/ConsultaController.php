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
use phpDocumentor\Reflection\DocBlock\Tags\Var_;

class ConsultaController extends BaseController
{
    public $modelClass = 'common\models\Consulta';

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        // Permitir acceso sin autenticación Bearer si hay sesión activa (para web)
        // El método actionAnalizar verificará manualmente la autenticación
        $behaviors['authenticator']['except'] = ['options', 'analizar', 'guardar'];
        
        return $behaviors;
    }

    public function actionAnalizar()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        try {
            // Verificar autenticación usando método centralizado
            $errorAuth = $this->requerirAutenticacion();
            if ($errorAuth !== null) {
                return $errorAuth;
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
                            Las palabras subrayadas han sido corregidas automáticamente
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
                'datos_con_snomed' => $datosConSnomed ? true : false,
                // Incluir categorías con mapeo título->modelo para el frontend
                'categorias' => $categorias
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
        
        // Obtener logger para registrar validación
        $logger = ConsultaLogger::obtenerInstancia();
        
        // Definimos una bandera que indica si hay datos faltantes, sirve para no dejar guardar la consulta
        if (!empty($categorias)) {
            $categoriasFaltantes = [];
            $camposFaltantes = [];
            
            foreach ($categorias as $categoria) {
                $esRequerida = $categoria['requerido'] ?? false;
                
                // si la categoria es requerida y no tiene datos break
                if ($esRequerida) {
                    if (!isset($datos[$categoria['titulo']]) || empty($datos[$categoria['titulo']])) {
                        $tieneDatosFaltantes = true;
                        $categoriasFaltantes[] = $categoria['titulo'];
                        
                        if ($logger) {
                            $logger->registrar(
                                'VALIDACIÓN',
                                null,
                                "Categoría requerida faltante: {$categoria['titulo']}",
                                [
                                    'metodo' => 'ConsultaController::generateAnalysisHtml',
                                    'tipo' => 'categoria_requerida_faltante',
                                    'categoria' => $categoria['titulo']
                                ]
                            );
                        }
                        break;
                    }
                }

                continue;

                $camposRequeridos = $categoria['campos_requeridos'] ?? [];
                if (!empty($camposRequeridos)) {
                    foreach ($camposRequeridos as $campo) {
                        if (!isset($datos[$categoria['titulo']][$campo]) || empty($datos[$categoria['titulo']][$campo])) {
                            $tieneDatosFaltantes = true;
                            $camposFaltantes[] = "{$categoria['titulo']}::{$campo}";
                            
                            if ($logger) {
                                $logger->registrar(
                                    'VALIDACIÓN',
                                    null,
                                    "Campo requerido faltante: {$categoria['titulo']}::{$campo}",
                                    [
                                        'metodo' => 'ConsultaController::generateAnalysisHtml',
                                        'tipo' => 'campo_requerido_faltante',
                                        'categoria' => $categoria['titulo'],
                                        'campo' => $campo
                                    ]
                                );
                            }
                            break;
                        }
                    }
                }

                if ($tieneDatosFaltantes) {
                    break;
                }
            }
            
            // Registrar resultado final de la validación
            if ($logger) {
                $logger->registrar(
                    'VALIDACIÓN',
                    null,
                    $tieneDatosFaltantes ? 'Se detectaron datos faltantes' : 'Validación completada sin datos faltantes',
                    [
                        'metodo' => 'ConsultaController::generateAnalysisHtml',
                        'tiene_datos_faltantes' => $tieneDatosFaltantes,
                        'categorias_faltantes' => $categoriasFaltantes,
                        'campos_faltantes' => $camposFaltantes,
                        'total_categorias' => count($categorias)
                    ]
                );
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

        // Prompt optimizado con instrucciones claras para generar JSON completo
        $prompt = "Extrae datos en JSON. Categorías: " . $categoriasTexto . ". Sin datos: [].

IMPORTANTE: Genera un JSON completo y válido. Asegúrate de cerrar todas las llaves, corchetes y comillas.

Formato:
{\"datosExtraidos\":{\"categoria\":[\"valor\"]}}

Texto: \"" . $texto . "\"

Responde SOLO con el JSON, sin texto adicional antes o después.";
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
     * Guardar consulta completa con todos sus datos relacionados
     * Recibe todos los datos en un solo POST en lugar de paso a paso
     */
    public function actionGuardar()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        try {
            // Verificar autenticación usando método centralizado
            $errorAuth = $this->requerirAutenticacion();
            if ($errorAuth !== null) {
                return $errorAuth;
            }

            // Obtener datos del POST - intentar múltiples fuentes
            $body = Yii::$app->request->getBodyParams();
            $post = Yii::$app->request->post();
            
            // Si getBodyParams está vacío, intentar con post
            if (empty($body)) {
                $body = $post;
            }
            
            // Si aún está vacío, intentar leer el raw body como JSON
            if (empty($body)) {
                $rawBody = Yii::$app->request->getRawBody();
                if (!empty($rawBody)) {
                    $decoded = json_decode($rawBody, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $body = $decoded;
                    }
                }
            }
            
            // Log para debugging (solo en modo debug)
            if (YII_DEBUG) {
                Yii::info('Datos recibidos en actionGuardar: ' . json_encode([
                    'bodyParams' => Yii::$app->request->getBodyParams(),
                    'post' => $post,
                    'rawBody' => substr(Yii::$app->request->getRawBody(), 0, 500),
                    'mergedBody' => $body
                ]), 'consulta-guardar');
            }
            
            // Validar datos básicos requeridos
            $idConfiguracion = $body['id_configuracion'] ?? null;
            $idPersona = $body['id_persona'] ?? null;
            $datosExtraidos = $body['datosExtraidos'] ?? [];
            $idConsulta = $body['id_consulta'] ?? null;
            
            // Si no viene id_persona en el body, intentar obtenerlo de la sesión
            if (!$idPersona) {
                $session = Yii::$app->session;
                if ($session->isActive && $session->has('idPersona')) {
                    $idPersona = $session->get('idPersona');
                }
            }
            
            // Si no viene id_configuracion, intentar obtenerlo desde servicio y encounter class
            if (!$idConfiguracion) {
                $idServicio = Yii::$app->user->getServicioActual();
                $encounterClass = Yii::$app->user->getEncounterClass();
                
                if ($idServicio && $encounterClass) {
                    list($urlAnterior, $urlActual, $urlSiguiente, $idConfiguracionObtenido) = 
                        \common\models\ConsultasConfiguracion::getUrlPorServicioYEncounterClass($idServicio, $encounterClass);
                    
                    if ($idConfiguracionObtenido) {
                        $idConfiguracion = $idConfiguracionObtenido;
                    }
                }
            }
            
            if (!$idConfiguracion || !$idPersona) {
                Yii::$app->response->statusCode = 400;
                return [
                    'success' => false,
                    'message' => 'Faltan datos obligatorios: id_configuracion e id_persona son requeridos.',
                    'errors' => [
                        'id_configuracion' => $idConfiguracion ? 'presente' : 'faltante',
                        'id_persona' => $idPersona ? 'presente' : 'faltante',
                        'body_keys' => array_keys($body ?? []),
                    ],
                ];
            }

            // Obtener configuración
            $configuracion = \common\models\ConsultasConfiguracion::findOne($idConfiguracion);
            if (!$configuracion) {
                Yii::$app->response->statusCode = 400;
                return [
                    'success' => false,
                    'message' => 'Configuración de consulta no encontrada.',
                    'errors' => null,
                ];
            }

            // Obtener categorías de la configuración
            $categorias = \common\models\ConsultasConfiguracion::getCategoriasParaPrompt($configuracion);
            
            // Obtener o crear consulta
            $paciente = \common\models\Persona::findOne($idPersona);
            if (!$paciente) {
                Yii::$app->response->statusCode = 400;
                return [
                    'success' => false,
                    'message' => 'Paciente no encontrado.',
                    'errors' => null,
                ];
            }

            // Obtener userId de la autenticación para asignar created_by
            $auth = $this->verificarAutenticacion();
            $userId = $auth['userId'] ?? null;
            //var_dump($userId); exit;
            
            $transaction = \Yii::$app->db->beginTransaction();
            
            try {
                // Obtener o crear la consulta
                if ($idConsulta) {
                    $modelConsulta = \common\models\Consulta::findOne($idConsulta);
                    if (!$modelConsulta) {
                        throw new \Exception('Consulta no encontrada');
                    }
                    
                    // Si es actualización, también guardar textos si vienen
                    if (isset($body['texto_original']) && !isset($body['consulta_inicial'])) {
                        $modelConsulta->consulta_inicial = $body['texto_original'];
                    }
                    
                    if (isset($body['texto_procesado']) && !isset($body['observacion'])) {
                        $modelConsulta->observacion = $body['texto_procesado'];
                    }
                } else {
                    // Crear nueva consulta
                    $parent = $body['parent'] ?? null;
                    $parentId = $body['parent_id'] ?? null;
                    
                    // Validar permiso de atención si hay parent
                    if ($parent && $parentId) {
                        $resultadoValidacion = \common\models\ConsultasConfiguracion::validarPermisoAtencion($parent, $parentId, $paciente);
                        if (!$resultadoValidacion['success']) {
                            throw new \Exception($resultadoValidacion['msg']);
                        }
                        
                        list($urlAnterior, $urlActual, $urlSiguiente, $idConfiguracionValidado) = 
                            \common\models\ConsultasConfiguracion::getUrlPorServicioYEncounterClass(
                                $resultadoValidacion['idServicio'], 
                                $resultadoValidacion['encounterClass']
                            );
                        
                        if ($idConfiguracionValidado && $idConfiguracionValidado != $idConfiguracion) {
                            $idConfiguracion = $idConfiguracionValidado;
                        }
                    } else {
                        // Sin parent, usar configuración del usuario actual
                        $idServicio = Yii::$app->user->getServicioActual();
                        $encounterClass = Yii::$app->user->getEncounterClass();
                        list($urlAnterior, $urlActual, $urlSiguiente, $idConfiguracionValidado) = 
                            \common\models\ConsultasConfiguracion::getUrlPorServicioYEncounterClass($idServicio, $encounterClass);
                        
                        if ($idConfiguracionValidado) {
                            $idConfiguracion = $idConfiguracionValidado;
                        }
                    }
                    
                    $modelConsulta = new \common\models\Consulta();
                    $modelConsulta->id_configuracion = $idConfiguracion;
                    $modelConsulta->id_persona = $idPersona;
                    $modelConsulta->id_rr_hh = Yii::$app->user->getIdRecursoHumano();
                    $modelConsulta->id_servicio = Yii::$app->user->getServicioActual();
                    $modelConsulta->id_efector = Yii::$app->user->getIdEfector();
                    $modelConsulta->estado = \common\models\Consulta::ESTADO_EN_PROGRESO;
                    $modelConsulta->paso_completado = 0;
                    $modelConsulta->editando = 0;
                    
                    // Asignar parent_class y parent_id
                    if ($parent && $parentId) {
                        $modelConsulta->parent_class = \common\models\Consulta::PARENT_CLASSES[$parent] ?? '';
                        $modelConsulta->parent_id = $parentId;
                    } else {
                        // Si no hay parent, usar GENERICO_AMB o GENERICO_EMER según encounter class
                        $encounterClass = Yii::$app->user->getEncounterClass();
                        if ($encounterClass == \common\models\Consulta::ENCOUNTER_CLASS_AMB) {
                            $parent = \common\models\Consulta::PARENT_GENERICO_AMB;
                        } else {
                            // Por defecto usar GENERICO_EMER para emergencias
                            $parent = \common\models\Consulta::PARENT_GENERICO_EMER;
                        }
                        
                        // Para GENERICO_AMB y GENERICO_EMER, usar parent_id = 0
                        $parentId = 0;
                        
                        $modelConsulta->parent_class = \common\models\Consulta::PARENT_CLASSES[$parent] ?? '';
                        $modelConsulta->parent_id = $parentId;
                    }
                    
                    // Campos adicionales de la consulta
                    // Guardar texto original en consulta_inicial (lo que el usuario escribió)
                    if (isset($body['consulta_inicial'])) {
                        $modelConsulta->consulta_inicial = $body['consulta_inicial'];
                    } elseif (isset($body['texto_original'])) {
                        // Si viene texto_original, guardarlo en consulta_inicial
                        $modelConsulta->consulta_inicial = $body['texto_original'];
                    }
                    
                    // Guardar texto procesado en observacion
                    if (isset($body['texto_procesado'])) {
                        $modelConsulta->observacion = $body['texto_procesado'];
                    } elseif (isset($body['observacion'])) {
                        $modelConsulta->observacion = $body['observacion'];
                    }
                    
                    if (isset($body['motivo_consulta'])) {
                        $modelConsulta->motivo_consulta = $body['motivo_consulta'];
                    }
                    
                    // Asignar created_by explícitamente si tenemos userId
                    if ($userId !== null) {
                        $modelConsulta->created_by = $userId;
                    } elseif (Yii::$app->user && !Yii::$app->user->isGuest) {
                        // Fallback: usar Yii::$app->user->id si está disponible
                        $modelConsulta->created_by = Yii::$app->user->id;
                    }
                    
                    if (!$modelConsulta->save()) {
                        throw new \Exception('Error al crear la consulta: ' . json_encode($modelConsulta->getErrors()));
                    }
                    
                    // Guardar ambos textos en ConsultaIa para tener un registro completo
                    if (isset($body['texto_original']) || isset($body['texto_procesado'])) {
                        $consultaIA = new \common\models\ConsultaIa();
                        $consultaIA->id_consulta = $modelConsulta->id_consulta;
                        $consultaIA->detalle = json_encode([
                            'texto_original' => $body['texto_original'] ?? $body['consulta_inicial'] ?? '',
                            'texto_procesado' => $body['texto_procesado'] ?? '',
                            'fecha_procesamiento' => date('Y-m-d H:i:s')
                        ]);
                        $consultaIA->save(false); // Guardar sin validar para no bloquear si hay errores
                    }
                }

                // Obtener configuración completa de pasos para mapeo de datos
                $jsonPasos = json_decode($configuracion->pasos_json, true);
                $configuracionPasos = $jsonPasos['conf'] ?? [];
                
                // Crear mapa de título a configuración completa
                $mapaConfiguracion = [];
                foreach ($configuracionPasos as $pasoConfig) {
                    $titulo = $pasoConfig['titulo'] ?? null;
                    if ($titulo) {
                        $mapaConfiguracion[$titulo] = $pasoConfig;
                    }
                }
                
                // Procesar cada categoría de la configuración
                $errores = [];
                foreach ($categorias as $categoria) {
                    $titulo = $categoria['titulo'];
                    $nombreModelo = $categoria['modelo'];
                    $esRequerido = $categoria['requerido'] ?? false;
                    
                    // Buscar datos para esta categoría en datosExtraidos
                    // Primero intentar por nombre del modelo (más fiable), luego por título (fallback)
                    $datosCategoria = $datosExtraidos[$nombreModelo] ?? $datosExtraidos[$titulo] ?? null;
                    
                    // Si es requerido y no hay datos, registrar error
                    if ($esRequerido && (empty($datosCategoria) || (is_array($datosCategoria) && count($datosCategoria) == 0))) {
                        $errores[] = "La categoría '{$titulo}' (modelo: {$nombreModelo}) es requerida pero no tiene datos";
                        continue;
                    }
                    
                    // Si no hay datos, continuar con la siguiente categoría
                    if (empty($datosCategoria)) {
                        continue;
                    }
                    
                    // Obtener configuración completa del paso
                    $pasoConfigCompleto = $mapaConfiguracion[$titulo] ?? null;
                    
                    // Mapear y guardar datos según el modelo usando la configuración
                    try {
                        $this->guardarDatosCategoria($modelConsulta, $nombreModelo, $datosCategoria, $titulo, $pasoConfigCompleto);
                    } catch (\Exception $e) {
                        $errores[] = "Error guardando {$titulo} (modelo: {$nombreModelo}): " . $e->getMessage();
                        Yii::error("Error guardando categoría {$titulo} (modelo: {$nombreModelo}): " . $e->getMessage(), 'consulta-guardar');
                    }
                }
                
                // Si hay errores en categorías requeridas, hacer rollback
                if (!empty($errores)) {
                    $transaction->rollBack();
                    Yii::$app->response->statusCode = 400;
                    return [
                        'success' => false,
                        'message' => 'Error al guardar algunos datos de la consulta.',
                        'errors' => $errores,
                    ];
                }
                
                // Marcar consulta como finalizada
                $modelConsulta->paso_completado = \common\models\Consulta::PASO_FINALIZADA;
                $modelConsulta->estado = \common\models\Consulta::ESTADO_FINALIZADA;
                
                // Si tiene parent de tipo Turno, actualizar estado
                if ($modelConsulta->parent_class == \common\models\Consulta::PARENT_CLASSES[\common\models\Consulta::PARENT_TURNO]) {
                    \common\models\Turno::cambiarCampoAtendido($modelConsulta->parent_id, \common\models\Turno::ATENDIDO_SI);
                    $turno = \common\models\Turno::findOne($modelConsulta->parent_id);
                    if ($turno) {
                        \common\models\Turno::cargarRrhhServicioAsignado($modelConsulta->parent_id, $turno->id_servicio_asignado);
                        $consultaPS = \common\models\ConsultaDerivaciones::getPracticaSolicitadasPorIdConsultaSolicitada($turno->id_consulta_referencia);
                        if ($consultaPS) {
                            $consultaPS->id_consulta_responde = $modelConsulta->id_consulta;
                            $consultaPS->save();
                        }
                    }
                }
                
                if (!$modelConsulta->save()) {
                    throw new \Exception('Error al finalizar la consulta: ' . json_encode($modelConsulta->getErrors()));
                }
                
                $transaction->commit();
                
                return [
                    'success' => true,
                    'message' => 'Consulta guardada exitosamente.',
                    'data' => [
                        'id_consulta' => $modelConsulta->id_consulta,
                        'estado' => $modelConsulta->estado,
                    ],
                ];
                
            } catch (\Exception $e) {
                $transaction->rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            Yii::error("Error en actionGuardar: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'consulta-guardar');
            
            Yii::$app->response->statusCode = 500;
            return [
                'success' => false,
                'message' => 'Ocurrió un error al guardar la consulta. Por favor, intente nuevamente.',
                'errors' => YII_DEBUG ? [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ];
        }
    }

    /**
     * Guardar datos de una categoría específica en su modelo correspondiente
     * @param \common\models\Consulta $modelConsulta
     * @param string $nombreModelo Nombre del modelo (ej: ConsultaMedicamentos, ConsultaSintomas)
     * @param mixed $datosCategoria Datos de la categoría (puede ser array simple o array de objetos)
     * @param string $tituloCategoria Título de la categoría para logging
     * @param array|null $pasoConfig Configuración completa del paso desde pasos_json
     */
    private function guardarDatosCategoria($modelConsulta, $nombreModelo, $datosCategoria, $tituloCategoria, $pasoConfig = null)
    {
        $claseModelo = "\\common\\models\\{$nombreModelo}";
        
        if (!class_exists($claseModelo)) {
            throw new \Exception("Modelo {$nombreModelo} no existe");
        }
        
        // Obtener modelos existentes de esta categoría
        $relacion = $this->obtenerRelacionConsulta($nombreModelo);
        $modelosExistentes = [];
        if ($relacion && method_exists($modelConsulta, $relacion)) {
            $modelosExistentes = $modelConsulta->$relacion;
            if (!is_array($modelosExistentes)) {
                $modelosExistentes = $modelosExistentes ? [$modelosExistentes] : [];
            }
        }
        
        $idsGuardados = [];
        foreach ($modelosExistentes as $modelo) {
            if (isset($modelo->id)) {
                $idsGuardados[] = $modelo->id;
            }
        }
        
        $nuevosIds = [];
        
        // Procesar según el tipo de modelo, pasando la configuración
        switch ($nombreModelo) {
            case 'ConsultaMedicamentos':
                $nuevosIds = $this->guardarMedicamentos($modelConsulta, $datosCategoria, $modelosExistentes, $pasoConfig);
                break;
                
            case 'ConsultaSintomas':
            case 'ConsultaMotivos':
                $nuevosIds = $this->guardarSintomasOMotivos($modelConsulta, $datosCategoria, $nombreModelo, $modelosExistentes, $pasoConfig);
                break;
                
            case 'ConsultaPracticas':
            case 'ConsultaPracticasOftalmologia':
                $nuevosIds = $this->guardarPracticas($modelConsulta, $datosCategoria, $nombreModelo, $modelosExistentes, $pasoConfig);
                break;
                
            case 'ConsultaDiagnosticos':
                $nuevosIds = $this->guardarDiagnosticos($modelConsulta, $datosCategoria, $modelosExistentes, $pasoConfig);
                break;
                
            default:
                // Intentar guardado genérico usando configuración
                $nuevosIds = $this->guardarGenerico($modelConsulta, $claseModelo, $datosCategoria, $modelosExistentes, $pasoConfig);
                break;
        }
        
        // Eliminar modelos que estaban en BD pero no vienen en los nuevos datos
        $idsAEliminar = array_diff($idsGuardados, $nuevosIds);
        if (!empty($idsAEliminar) && method_exists($claseModelo, 'hardDeleteGrupo')) {
            $claseModelo::hardDeleteGrupo($modelConsulta->id_consulta, $idsAEliminar);
        }
    }

    /**
     * Obtener el nombre de la relación en Consulta para un modelo dado
     */
    private function obtenerRelacionConsulta($nombreModelo)
    {
        $mapa = [
            'ConsultaMedicamentos' => 'consultaMedicamentos',
            'ConsultaSintomas' => 'consultaSintomas',
            'ConsultaMotivos' => 'motivoConsulta',
            'ConsultaPracticas' => 'practicasPersonaConsultas',
            'ConsultaPracticasOftalmologia' => 'oftalmologiasDP',
            'ConsultaDiagnosticos' => 'diagnosticoConsultas',
        ];
        
        return $mapa[$nombreModelo] ?? null;
    }

    /**
     * Guardar medicamentos usando configuración para mapear datos
     */
    private function guardarMedicamentos($modelConsulta, $datosCategoria, $modelosExistentes, $pasoConfig = null)
    {
        $nuevosIds = [];
        
        if (!is_array($datosCategoria)) {
            return $nuevosIds;
        }
        
        foreach ($datosCategoria as $medicamentoData) {
            $modelo = new \common\models\ConsultaMedicamentos();
            
            // Mapear campos usando configuración o mapeo por defecto
            if (is_array($medicamentoData)) {
                // Mapear usando configuración si está disponible
                $this->mapearDatosAModelo($modelo, $medicamentoData, $pasoConfig, [
                    'id_snomed_medicamento' => ['id_snomed_medicamento', 'snomed_code', 'codigo_snomed', 'conceptId'],
                    'cantidad' => ['Cantidad del medicamento', 'cantidad', 'quantity'],
                    'frecuencia' => ['Frecuencia de administracion', 'frecuencia', 'frequency'],
                    'durante' => ['Duracion del tratamiento', 'durante', 'duration', 'duracion'],
                    'indicaciones' => ['indicaciones', 'indicacion', 'instructions'],
                ]);
                
                // Si hay término del medicamento y código SNOMED, crear/actualizar SNOMED
                $termino = $medicamentoData['Nombre del medicamento'] ?? $medicamentoData['termino'] ?? $medicamentoData['medicamento'] ?? null;
                $codigoSnomed = $modelo->id_snomed_medicamento;
                
                if ($termino && $codigoSnomed) {
                    \common\models\snomed\SnomedMedicamentos::crearSiNoExiste($codigoSnomed, $termino);
                }
            } else {
                // Si viene como string simple, solo el nombre
                $termino = $medicamentoData;
            }
            
            $modelo->id_consulta = $modelConsulta->id_consulta;
            $modelo->estado = \common\models\ConsultaMedicamentos::ESTADO_ACTIVO;
            
            if ($modelo->save()) {
                $nuevosIds[] = $modelo->id;
            }
        }
        
        return $nuevosIds;
    }

    /**
     * Guardar síntomas o motivos de consulta usando configuración
     */
    private function guardarSintomasOMotivos($modelConsulta, $datosCategoria, $nombreModelo, $modelosExistentes, $pasoConfig = null)
    {
        $nuevosIds = [];
        
        if (!is_array($datosCategoria)) {
            return $nuevosIds;
        }
        
        $claseModelo = "\\common\\models\\{$nombreModelo}";
        
        foreach ($datosCategoria as $item) {
            $modelo = new $claseModelo();
            
            if (is_string($item)) {
                // Si viene como string simple, el código SNOMED debería venir en datosExtraidos con estructura
                $termino = $item;
                $modelo->codigo = null;
            } elseif (is_array($item)) {
                // Mapear usando configuración
                $this->mapearDatosAModelo($modelo, $item, $pasoConfig, [
                    'codigo' => ['codigo', 'id_snomed', 'snomed_code', 'conceptId', 'codigo_snomed'],
                ]);
                
                // Si hay término y código SNOMED, crear/actualizar SNOMED
                $termino = $item['termino'] ?? $item['texto'] ?? $item['nombre'] ?? null;
                $codigoSnomed = $modelo->codigo;
                
                if ($termino && $codigoSnomed && $nombreModelo === 'ConsultaSintomas') {
                    \common\models\snomed\SnomedProblemas::crearSiNoExiste($codigoSnomed, $termino);
                }
            }
            
            $modelo->id_consulta = $modelConsulta->id_consulta;
            
            if ($modelo->save()) {
                $nuevosIds[] = $modelo->id;
            }
        }
        
        return $nuevosIds;
    }

    /**
     * Guardar prácticas usando configuración
     */
    private function guardarPracticas($modelConsulta, $datosCategoria, $nombreModelo, $modelosExistentes, $pasoConfig = null)
    {
        $nuevosIds = [];
        
        if (!is_array($datosCategoria)) {
            return $nuevosIds;
        }
        
        $claseModelo = "\\common\\models\\{$nombreModelo}";
        
        foreach ($datosCategoria as $practicaData) {
            $modelo = new $claseModelo();
            
            if (is_string($practicaData)) {
                $modelo->codigo = null;
            } elseif (is_array($practicaData)) {
                // Mapear usando configuración
                $this->mapearDatosAModelo($modelo, $practicaData, $pasoConfig, [
                    'codigo' => ['codigo', 'id_snomed', 'snomed_code', 'conceptId', 'codigo_snomed'],
                ]);
            }
            
            $modelo->id_consulta = $modelConsulta->id_consulta;
            
            if ($modelo->save()) {
                $nuevosIds[] = $modelo->id;
            }
        }
        
        return $nuevosIds;
    }

    /**
     * Guardar diagnósticos usando configuración
     */
    private function guardarDiagnosticos($modelConsulta, $datosCategoria, $modelosExistentes, $pasoConfig = null)
    {
        $nuevosIds = [];
        
        if (!is_array($datosCategoria)) {
            return $nuevosIds;
        }
        
        foreach ($datosCategoria as $diagnosticoData) {
            $modelo = new \common\models\DiagnosticoConsulta();
            
            if (is_string($diagnosticoData)) {
                $modelo->codigo = null;
            } elseif (is_array($diagnosticoData)) {
                // Mapear usando configuración
                $this->mapearDatosAModelo($modelo, $diagnosticoData, $pasoConfig, [
                    'codigo' => ['codigo', 'codigo_cie10', 'cie10', 'id_cie10'],
                ]);
            }
            
            $modelo->id_consulta = $modelConsulta->id_consulta;
            
            if ($modelo->save()) {
                $nuevosIds[] = $modelo->id;
            }
        }
        
        return $nuevosIds;
    }

    /**
     * Guardado genérico para modelos no específicos usando configuración
     */
    private function guardarGenerico($modelConsulta, $claseModelo, $datosCategoria, $modelosExistentes, $pasoConfig = null)
    {
        $nuevosIds = [];
        
        if (!is_array($datosCategoria)) {
            return $nuevosIds;
        }
        
        foreach ($datosCategoria as $item) {
            $modelo = new $claseModelo();
            
            if (is_array($item)) {
                // Mapear usando configuración si está disponible, sino mapeo directo
                if ($pasoConfig) {
                    $this->mapearDatosAModelo($modelo, $item, $pasoConfig);
                } else {
                    // Mapeo directo por nombre de atributo
                    foreach ($item as $key => $value) {
                        if ($modelo->hasAttribute($key)) {
                            $modelo->$key = $value;
                        }
                    }
                }
            }
            
            // Asignar id_consulta si el modelo tiene ese atributo
            if ($modelo->hasAttribute('id_consulta')) {
                $modelo->id_consulta = $modelConsulta->id_consulta;
            }
            
            if ($modelo->save()) {
                $nuevosIds[] = $modelo->id ?? $modelo->primaryKey;
            }
        }
        
        return $nuevosIds;
    }

    /**
     * Mapear datos de la IA al modelo usando configuración y mapeo de campos
     * @param \yii\db\ActiveRecord $modelo
     * @param array $datos Datos de la IA (pueden incluir SNOMED)
     * @param array|null $pasoConfig Configuración del paso desde pasos_json
     * @param array|null $mapaCampos Mapa de campos del modelo a posibles nombres en datos de IA
     */
    private function mapearDatosAModelo($modelo, $datos, $pasoConfig = null, $mapaCampos = null)
    {
        // Si hay configuración, intentar usarla para mapear
        if ($pasoConfig && isset($pasoConfig['campos'])) {
            foreach ($pasoConfig['campos'] as $campoConfig) {
                $nombreCampo = $campoConfig['nombre'] ?? null;
                $fuentesDatos = $campoConfig['fuentes'] ?? [];
                
                if ($nombreCampo && $modelo->hasAttribute($nombreCampo)) {
                    // Buscar valor en las fuentes especificadas
                    foreach ($fuentesDatos as $fuente) {
                        if (isset($datos[$fuente])) {
                            $modelo->$nombreCampo = $datos[$fuente];
                            break;
                        }
                    }
                }
            }
        }
        
        // Si hay mapa de campos, usarlo para mapear
        if ($mapaCampos) {
            foreach ($mapaCampos as $campoModelo => $fuentesPosibles) {
                if ($modelo->hasAttribute($campoModelo)) {
                    foreach ($fuentesPosibles as $fuente) {
                        if (isset($datos[$fuente])) {
                            $modelo->$campoModelo = $datos[$fuente];
                            break;
                        }
                    }
                }
            }
        }
        
        // Mapeo directo por nombre de atributo (fallback)
        foreach ($datos as $key => $value) {
            if ($modelo->hasAttribute($key) && !isset($modelo->$key)) {
                $modelo->$key = $value;
            }
        }
    }

}