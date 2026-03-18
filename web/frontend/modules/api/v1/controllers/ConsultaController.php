<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\Response;
use common\models\Consulta;
use common\\components\\Text\\ProcesadorTextoMedico;
use common\components\ConsultaLogger;
use common\components\Chatbot\Classification\ConsultaClassifier;
use common\components\DeferredSnomedProcessor;

/**
 * API Consulta: lógica de análisis y guardado de consultas.
 *
 * La lógica se implementa directamente aquí, sin usar el controlador del frontend.
 */
class ConsultaController extends BaseController
{
    /**
     * Acciones sin auth para API (si se necesitara configurar).
     */
    public static $authenticatorExcept = [];

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update'], $actions['delete']);
        return $actions;
    }

    public function actionAnalizar()
    {
        try {
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

            $tabId = $body['tab_id'] ?? null;
            if (!$tabId) {
                $tabId = 'tab_' . uniqid() . '_' . time();
            }

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
                'tabId' => $tabId,
            ];
            $logger = ConsultaLogger::iniciar($textoConsulta, $contextoLogger);

            $logger->registrar(
                'PROCESAMIENTO',
                $textoConsulta,
                null,
                ['metodo' => 'ProcesadorTextoMedico::prepararParaIAConFormato']
            );

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
                    'total_cambios' => $totalCambios,
                ]
            );

            $categorias = $this->getModelosPorConfiguracion($idConfiguracion);

            $esSimple = ConsultaClassifier::esConsultaSimple($textoProcesado);

            if ($esSimple) {
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
                        'categorias_extraidas' => isset($resultadoIA['datosExtraidos']) ? count($resultadoIA['datosExtraidos']) : 0,
                    ]
                );
            } else {
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
                        'categorias_extraidas' => $resultadoIA && isset($resultadoIA['datosExtraidos']) ? count($resultadoIA['datosExtraidos']) : 0,
                    ]
                );
            }

            $datosConSnomed = null;
            $estadisticasSnomed = null;
            $requiereValidacionSnomed = false;

            if ($resultadoIA && isset($resultadoIA['datosExtraidos'])) {
                DeferredSnomedProcessor::procesarDiferido(
                    null,
                    $resultadoIA,
                    $categorias
                );
                Yii::info('SNOMED agregado a cola de procesamiento diferido', 'snomed-codificador');
            }

            if ($datosConSnomed) {
                $datos = $datosConSnomed;
            } elseif ($resultadoIA) {
                $datos = $resultadoIA;
            } else {
                $datos = [
                    'datosExtraidos' => [
                        'Error' => [
                            'texto' => 'No se pudo procesar la consulta con IA',
                            'detalle' => 'Revisar manualmente la consulta',
                            'tipo' => 'error_sistema',
                        ],
                    ],
                ];
            }

            $sugerencias = [];

            $htmlResult = $this->generateAnalysisHtml($datos['datosExtraidos'], $sugerencias, $categorias);
            $html = $htmlResult['html'];
            $tieneDatosFaltantesHTML = $htmlResult['tieneDatosFaltantes'];

            if ($totalCambios > 0) {
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

            $tieneDatosFaltantes = false;
            if ($resultadoIA && isset($resultadoIA['informacionFaltante'])) {
                $tieneDatosFaltantes = $resultadoIA['informacionFaltante']['tieneDatosFaltantes'] ?? false;
            }
            if ($tieneDatosFaltantesHTML) {
                $tieneDatosFaltantes = true;
            }

            $resultado = [
                'success' => true,
                'datos' => $datos,
                'html' => $html,
                'texto_original' => $textoConsulta,
                'texto_procesado' => $textoProcesado,
                'texto_formateado' => $textoFormateado ?? null,
                'tab_id' => $tabId,
                'sugerencias' => $sugerencias,
                'tiene_datos_faltantes' => $tieneDatosFaltantes,
                'codigos_snomed' => $estadisticasSnomed,
                'requiere_validacion_snomed' => $requiereValidacionSnomed,
                'datos_con_snomed' => $datosConSnomed ? true : false,
                'categorias' => $categorias,
            ];

            $logger->finalizar($resultado);

            return $resultado;
        } catch (\Exception $e) {
            Yii::error('Error en actionAnalizar: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'consulta-ia');

            if (isset($logger)) {
                try {
                    $logger->finalizar([
                        'success' => false,
                        'error' => $e->getMessage(),
                    ]);
                } catch (\Exception $logError) {
                }
            }

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

    public function actionGuardar()
    {
        try {
            $body = Yii::$app->request->getBodyParams();
            $post = Yii::$app->request->post();

            if (empty($body)) {
                $body = $post;
            }

            if (empty($body)) {
                $rawBody = Yii::$app->request->getRawBody();
                if (!empty($rawBody)) {
                    $decoded = json_decode($rawBody, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $body = $decoded;
                    }
                }
            }

            if (YII_DEBUG) {
                Yii::info('Datos recibidos en actionGuardar (api): ' . json_encode([
                    'bodyParams' => Yii::$app->request->getBodyParams(),
                    'post' => $post,
                    'rawBody' => substr(Yii::$app->request->getRawBody(), 0, 500),
                    'mergedBody' => $body,
                ]), 'consulta-guardar');
            }

            $idConfiguracion = $body['id_configuracion'] ?? null;
            $idPersona = $body['id_persona'] ?? null;
            $datosExtraidos = $body['datosExtraidos'] ?? [];
            $idConsulta = $body['id_consulta'] ?? null;

            if (!$idPersona) {
                $session = Yii::$app->session;
                if ($session->isActive && $session->has('idPersona')) {
                    $idPersona = $session->get('idPersona');
                }
            }

            if (!$idConfiguracion) {
                $idServicio = Yii::$app->user->getServicioActual();
                $encounterClass = Yii::$app->user->getEncounterClass();

                if ($idServicio && $encounterClass) {
                    [
                        $urlAnterior,
                        $urlActual,
                        $urlSiguiente,
                        $idConfiguracionObtenido,
                    ] = \common\models\ConsultasConfiguracion::getUrlPorServicioYEncounterClass($idServicio, $encounterClass);

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

            $configuracion = \common\models\ConsultasConfiguracion::findOne($idConfiguracion);
            if (!$configuracion) {
                Yii::$app->response->statusCode = 400;
                return [
                    'success' => false,
                    'message' => 'Configuración de consulta no encontrada.',
                    'errors' => null,
                ];
            }

            $categorias = \common\models\ConsultasConfiguracion::getCategoriasParaPrompt($configuracion);

            $paciente = \common\models\Persona::findOne($idPersona);
            if (!$paciente) {
                Yii::$app->response->statusCode = 400;
                return [
                    'success' => false,
                    'message' => 'Paciente no encontrado.',
                    'errors' => null,
                ];
            }

            $userId = Yii::$app->user->id;

            $transaction = Yii::$app->db->beginTransaction();

            try {
                if ($idConsulta) {
                    $modelConsulta = Consulta::findOne($idConsulta);
                    if (!$modelConsulta) {
                        throw new \Exception('Consulta no encontrada');
                    }

                    if (isset($body['texto_original']) && !isset($body['consulta_inicial'])) {
                        $modelConsulta->consulta_inicial = $body['texto_original'];
                    }

                    if (isset($body['texto_procesado']) && !isset($body['observacion'])) {
                        $modelConsulta->observacion = $body['texto_procesado'];
                    }
                } else {
                    $parent = $body['parent'] ?? null;
                    $parentId = $body['parent_id'] ?? null;

                    if ($parent && $parentId) {
                        $resultadoValidacion = \common\models\ConsultasConfiguracion::validarPermisoAtencion($parent, $parentId, $paciente);
                        if (!$resultadoValidacion['success']) {
                            throw new \Exception($resultadoValidacion['msg']);
                        }

                        [
                            $urlAnterior,
                            $urlActual,
                            $urlSiguiente,
                            $idConfiguracionValidado,
                        ] = \common\models\ConsultasConfiguracion::getUrlPorServicioYEncounterClass(
                            $resultadoValidacion['idServicio'],
                            $resultadoValidacion['encounterClass']
                        );

                        if ($idConfiguracionValidado && $idConfiguracionValidado != $idConfiguracion) {
                            $idConfiguracion = $idConfiguracionValidado;
                        }
                    } else {
                        $idServicio = Yii::$app->user->getServicioActual();
                        $encounterClass = Yii::$app->user->getEncounterClass();
                        [
                            $urlAnterior,
                            $urlActual,
                            $urlSiguiente,
                            $idConfiguracionValidado,
                        ] = \common\models\ConsultasConfiguracion::getUrlPorServicioYEncounterClass($idServicio, $encounterClass);

                        if ($idConfiguracionValidado) {
                            $idConfiguracion = $idConfiguracionValidado;
                        }
                    }

                    $modelConsulta = new Consulta();
                    $modelConsulta->id_configuracion = $idConfiguracion;
                    $modelConsulta->id_persona = $idPersona;
                    $modelConsulta->id_rr_hh = Yii::$app->user->getIdRecursoHumano();
                    $modelConsulta->id_servicio = Yii::$app->user->getServicioActual();
                    $modelConsulta->id_efector = Yii::$app->user->getIdEfector();
                    $modelConsulta->estado = Consulta::ESTADO_EN_PROGRESO;
                    $modelConsulta->paso_completado = 0;
                    $modelConsulta->editando = 0;

                    if ($parent && $parentId) {
                        $modelConsulta->parent_class = Consulta::PARENT_CLASSES[$parent] ?? '';
                        $modelConsulta->parent_id = $parentId;
                    } else {
                        $encounterClass = Yii::$app->user->getEncounterClass();
                        if ($encounterClass == Consulta::ENCOUNTER_CLASS_AMB) {
                            $parent = Consulta::PARENT_GENERICO_AMB;
                        } else {
                            $parent = Consulta::PARENT_GENERICO_EMER;
                        }

                        $parentId = 0;

                        $modelConsulta->parent_class = Consulta::PARENT_CLASSES[$parent] ?? '';
                        $modelConsulta->parent_id = $parentId;
                    }

                    if (isset($body['consulta_inicial'])) {
                        $modelConsulta->consulta_inicial = $body['consulta_inicial'];
                    } elseif (isset($body['texto_original'])) {
                        $modelConsulta->consulta_inicial = $body['texto_original'];
                    }

                    if (isset($body['texto_procesado'])) {
                        $modelConsulta->observacion = $body['texto_procesado'];
                    } elseif (isset($body['observacion'])) {
                        $modelConsulta->observacion = $body['observacion'];
                    }

                    if (isset($body['motivo_consulta'])) {
                        $modelConsulta->motivo_consulta = $body['motivo_consulta'];
                    }

                    $modelConsulta->created_by = $userId;

                    if (!$modelConsulta->save()) {
                        throw new \Exception('Error al crear la consulta: ' . json_encode($modelConsulta->getErrors()));
                    }

                    if (isset($body['texto_original']) || isset($body['texto_procesado'])) {
                        $consultaIA = new \common\models\ConsultaIa();
                        $consultaIA->id_consulta = $modelConsulta->id_consulta;
                        $consultaIA->detalle = json_encode([
                            'texto_original' => $body['texto_original'] ?? $body['consulta_inicial'] ?? '',
                            'texto_procesado' => $body['texto_procesado'] ?? '',
                            'fecha_procesamiento' => date('Y-m-d H:i:s'),
                        ]);
                        $consultaIA->save(false);
                    }
                }

                $jsonPasos = json_decode($configuracion->pasos_json, true);
                $configuracionPasos = $jsonPasos['conf'] ?? [];

                $mapaConfiguracion = [];
                foreach ($configuracionPasos as $pasoConfig) {
                    $titulo = $pasoConfig['titulo'] ?? null;
                    if ($titulo) {
                        $mapaConfiguracion[$titulo] = $pasoConfig;
                    }
                }

                $errores = [];
                foreach ($categorias as $categoria) {
                    $titulo = $categoria['titulo'];
                    $nombreModelo = $categoria['modelo'];
                    $esRequerido = $categoria['requerido'] ?? false;

                    $datosCategoria = $datosExtraidos[$nombreModelo] ?? $datosExtraidos[$titulo] ?? null;

                    if ($esRequerido && (empty($datosCategoria) || (is_array($datosCategoria) && count($datosCategoria) == 0))) {
                        $errores[] = "La categoría '{$titulo}' (modelo: {$nombreModelo}) es requerida pero no tiene datos";
                        continue;
                    }

                    if (empty($datosCategoria)) {
                        continue;
                    }

                    $pasoConfigCompleto = $mapaConfiguracion[$titulo] ?? null;

                    try {
                        $this->guardarDatosCategoria($modelConsulta, $nombreModelo, $datosCategoria, $titulo, $pasoConfigCompleto);
                    } catch (\Exception $e) {
                        $errores[] = "Error guardando {$titulo} (modelo: {$nombreModelo}): " . $e->getMessage();
                        Yii::error("Error guardando categoría {$titulo} (modelo: {$nombreModelo}): " . $e->getMessage(), 'consulta-guardar');
                    }
                }

                if (!empty($errores)) {
                    $transaction->rollBack();
                    Yii::$app->response->statusCode = 400;
                    return [
                        'success' => false,
                        'message' => 'Error al guardar algunos datos de la consulta.',
                        'errors' => $errores,
                    ];
                }

                $modelConsulta->paso_completado = Consulta::PASO_FINALIZADA;
                $modelConsulta->estado = Consulta::ESTADO_FINALIZADA;

                if ($modelConsulta->parent_class == Consulta::PARENT_CLASSES[Consulta::PARENT_TURNO]) {
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
            Yii::error('Error en actionGuardar (api): ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'consulta-guardar');

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

    private function analizarConsultaConIA($texto, $servicio, $categorias)
    {
        try {
            $similitudMinima = Yii::$app->params['similitud_minima_respuestas'] ?? 0.85;
            $respuestaPredefinida = \common\components\RespuestaPredefinidaManager::obtenerRespuesta($texto, $servicio, $similitudMinima);

            if ($respuestaPredefinida) {
                Yii::info('Respuesta predefinida encontrada para consulta similar (sin GPU)', 'consulta-ia');
                \common\components\RespuestaPredefinidaManager::incrementarUsos($respuestaPredefinida['id']);
                return $respuestaPredefinida['respuesta_json'];
            }

            $promptData = $this->generarPromptEspecializado($texto, $servicio, $categorias);

            if ($promptData === null) {
                Yii::error('No se pudo generar el prompt debido a errores en el JSON de ejemplo', 'consulta-ia');
                return [
                    'datosExtraidos' => [
                        'Error' => [
                            'texto' => 'Error en la configuración del sistema. Por favor, contacte al administrador.',
                            'detalle' => 'No se pudo procesar la consulta debido a un error en la configuración.',
                            'tipo' => 'error_configuracion',
                        ],
                    ],
                ];
            }

            $resultado = $this->intentarAnalisisConIA($promptData['prompt'], $texto, $categorias);

            if ($resultado && !isset($resultado['error'])) {
                try {
                    \common\components\RespuestaPredefinidaManager::guardarRespuesta($texto, $resultado, $servicio);
                } catch (\Exception $e) {
                    Yii::warning('No se pudo guardar respuesta predefinida: ' . $e->getMessage(), 'respuestas-predefinidas');
                }
                return $resultado;
            }

            return [
                'datosExtraidos' => [
                    'Error' => [
                        'texto' => 'No se pudo procesar la consulta con inteligencia artificial en este momento.',
                        'detalle' => 'Por favor, intente nuevamente en unos momentos o revise la consulta manualmente.',
                        'tipo' => 'error_ia',
                    ],
                ],
            ];
        } catch (\Exception $e) {
            Yii::error('Error en analizarConsultaConIA: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'consulta-ia');
            return [
                'datosExtraidos' => [
                    'Error' => [
                        'texto' => 'Ocurrió un error al procesar la consulta.',
                        'detalle' => 'Por favor, intente nuevamente. Si el problema persiste, contacte al soporte técnico.',
                        'tipo' => 'error_sistema',
                    ],
                ],
            ];
        }
    }

    private function intentarAnalisisConIA($prompt, $texto, $categorias = [])
    {
        return Yii::$app->iamanager->consultar($prompt, 'analisis-consulta', 'analysis');
    }

    private function generateAnalysisHtml($datos, $sugerencias = [], $categorias = [])
    {
        $tieneDatosFaltantes = false;
        $logger = ConsultaLogger::obtenerInstancia();

        if (!empty($categorias)) {
            $categoriasFaltantes = [];
            $camposFaltantes = [];

            foreach ($categorias as $categoria) {
                $esRequerida = $categoria['requerido'] ?? false;

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
                                    'categoria' => $categoria['titulo'],
                                ]
                            );
                        }
                        break;
                    }
                }

                continue;
            }

            if ($logger) {
                $logger->registrar(
                    'VALIDACIÓN',
                    null,
                    $tieneDatosFaltantes ? 'Se detectaron datos faltantes' : 'Validación completada sin datos faltantes',
                    [
                        'metodo' => 'ConsultaController::generateAnalysisHtml',
                        'tiene_datos_faltantes' => $tieneDatosFaltantes,
                        'categorias_faltantes' => $categoriasFaltantes,
                        'campos_faltantes' => $camposFaltantes ?? [],
                        'total_categorias' => count($categorias),
                    ]
                );
            }
        }

        $html = Yii::$app->view->render('//paciente/_resultado_analisis_consulta', [
            'datos' => $datos,
            'sugerencias' => $sugerencias,
            'categorias' => $categorias,
            'tieneDatosFaltantes' => $tieneDatosFaltantes,
        ]);

        return [
            'html' => $html,
            'tieneDatosFaltantes' => $tieneDatosFaltantes,
        ];
    }

    private function getModelosPorConfiguracion($idConfiguracion)
    {
        $configuracion = \common\models\ConsultasConfiguracion::findOne($idConfiguracion);
        return \common\models\ConsultasConfiguracion::getCategoriasParaPrompt($configuracion);
    }

    private function generarPromptEspecializado($texto, $servicio, $categorias)
    {
        $categoriasTexto = $this->construirCategoriasTexto($categorias);
        $jsonEjemplo = $this->generarJsonEjemplo($categorias);

        if ($jsonEjemplo === false) {
            return null;
        }

        $prompt = "Extrae datos en JSON. Categorías: " . $categoriasTexto . ". Sin datos: [].

IMPORTANTE: Genera un JSON completo y válido. Asegúrate de cerrar todas las llaves, corchetes y comillas.

Formato:
{\"datosExtraidos\":{\"categoria\":[\"valor\"]}}

Texto: \"" . $texto . "\"

Responde SOLO con el JSON, sin texto adicional antes o después.";

        return [
            'prompt' => $prompt,
            'json_ejemplo' => $jsonEjemplo,
        ];
    }

    private function generarJsonEjemplo($categorias)
    {
        $datosExtraidos = [];

        foreach ($categorias as $categoria) {
            $titulo = $categoria['titulo'];
            $datosExtraidos[$titulo] = [];
        }

        $jsonEjemplo = [
            'datosExtraidos' => $datosExtraidos,
        ];

        $jsonString = json_encode($jsonEjemplo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($jsonString === false) {
            $error = json_last_error_msg();
            Yii::error('Error al generar JSON de ejemplo: ' . $error . ' - Datos: ' . print_r($jsonEjemplo, true), 'consulta-ia');
            return false;
        }

        return $jsonString;
    }

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

    private function guardarDatosCategoria($modelConsulta, $nombreModelo, $datosCategoria, $tituloCategoria, $pasoConfig = null)
    {
        $claseModelo = "\\common\\models\\{$nombreModelo}";

        if (!class_exists($claseModelo)) {
            throw new \Exception("Modelo {$nombreModelo} no existe");
        }

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
                $nuevosIds = $this->guardarGenerico($modelConsulta, $claseModelo, $datosCategoria, $modelosExistentes, $pasoConfig);
                break;
        }

        $idsAEliminar = array_diff($idsGuardados, $nuevosIds);
        if (!empty($idsAEliminar) && method_exists($claseModelo, 'hardDeleteGrupo')) {
            $claseModelo::hardDeleteGrupo($modelConsulta->id_consulta, $idsAEliminar);
        }
    }

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

    private function guardarMedicamentos($modelConsulta, $datosCategoria, $modelosExistentes, $pasoConfig = null)
    {
        $nuevosIds = [];

        if (!is_array($datosCategoria)) {
            return $nuevosIds;
        }

        foreach ($datosCategoria as $medicamentoData) {
            $modelo = new \common\models\ConsultaMedicamentos();

            if (is_array($medicamentoData)) {
                $this->mapearDatosAModelo($modelo, $medicamentoData, $pasoConfig, [
                    'id_snomed_medicamento' => ['id_snomed_medicamento', 'snomed_code', 'codigo_snomed', 'conceptId'],
                    'cantidad' => ['Cantidad del medicamento', 'cantidad', 'quantity'],
                    'frecuencia' => ['Frecuencia de administracion', 'frecuencia', 'frequency'],
                    'durante' => ['Duracion del tratamiento', 'durante', 'duration', 'duracion'],
                    'indicaciones' => ['indicaciones', 'indicacion', 'instructions'],
                ]);

                $termino = $medicamentoData['Nombre del medicamento'] ?? $medicamentoData['termino'] ?? $medicamentoData['medicamento'] ?? null;
                $codigoSnomed = $modelo->id_snomed_medicamento;

                if ($termino && $codigoSnomed) {
                    \common\models\snomed\SnomedMedicamentos::crearSiNoExiste($codigoSnomed, $termino);
                }
            }

            $modelo->id_consulta = $modelConsulta->id_consulta;
            $modelo->estado = \common\models\ConsultaMedicamentos::ESTADO_ACTIVO;

            if ($modelo->save()) {
                $nuevosIds[] = $modelo->id;
            }
        }

        return $nuevosIds;
    }

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
                $modelo->codigo = null;
            } elseif (is_array($item)) {
                $this->mapearDatosAModelo($modelo, $item, $pasoConfig, [
                    'codigo' => ['codigo', 'id_snomed', 'snomed_code', 'conceptId', 'codigo_snomed'],
                ]);

                $termino = $item['termino'] ?? $item['texto'] ?? $item['nombre'] ?? null;
                $codigoSnomed = $modelo->codigo;

                if ($termino && $codigoSnomed && $nombreModelo === 'ConsultaSintomas') {
                    \common\models\snomed\SnomedProblemas::crearSiNoExiste($codigoSnomed, $termino);
                }
            }

            $modelo->id_consulta = $modelConsulta->id_consulta;
            if ($nombreModelo === 'ConsultaMotivos') {
                $modelo->origen = \common\models\ConsultaMotivos::ORIGEN_MEDICO;
            }

            if ($modelo->save()) {
                $nuevosIds[] = $modelo->id;
            }
        }

        return $nuevosIds;
    }

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

    private function guardarGenerico($modelConsulta, $claseModelo, $datosCategoria, $modelosExistentes, $pasoConfig = null)
    {
        $nuevosIds = [];

        if (!is_array($datosCategoria)) {
            return $nuevosIds;
        }

        foreach ($datosCategoria as $item) {
            $modelo = new $claseModelo();

            if (is_array($item)) {
                if ($pasoConfig) {
                    $this->mapearDatosAModelo($modelo, $item, $pasoConfig);
                } else {
                    foreach ($item as $key => $value) {
                        if ($modelo->hasAttribute($key)) {
                            $modelo->$key = $value;
                        }
                    }
                }
            }

            if ($modelo->hasAttribute('id_consulta')) {
                $modelo->id_consulta = $modelConsulta->id_consulta;
            }

            if ($modelo->save()) {
                $nuevosIds[] = $modelo->id ?? $modelo->primaryKey;
            }
        }

        return $nuevosIds;
    }

    private function mapearDatosAModelo($modelo, $datos, $pasoConfig = null, $mapaCampos = null)
    {
        if ($pasoConfig && isset($pasoConfig['campos'])) {
            foreach ($pasoConfig['campos'] as $campoConfig) {
                $nombreCampo = $campoConfig['nombre'] ?? null;
                $fuentesDatos = $campoConfig['fuentes'] ?? [];

                if ($nombreCampo && $modelo->hasAttribute($nombreCampo)) {
                    foreach ($fuentesDatos as $fuente) {
                        if (isset($datos[$fuente])) {
                            $modelo->$nombreCampo = $datos[$fuente];
                            break;
                        }
                    }
                }
            }
        }

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

        foreach ($datos as $key => $value) {
            if ($modelo->hasAttribute($key) && !isset($modelo->$key)) {
                $modelo->$key = $value;
            }
        }
    }
}

