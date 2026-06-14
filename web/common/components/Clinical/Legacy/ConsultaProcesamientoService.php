<?php

namespace common\components\Clinical\Legacy;

use Yii;
use yii\base\Component;
use common\components\Clinical\SpeechToText\ClinicalSpeechInputResolver;
use common\components\Clinical\AiContext\PatientAiContextBuilder;
use common\components\Clinical\Workflow\EncounterDocumentationService;
use common\components\Text\ProcesadorTextoMedico;
use common\components\Logging\ConsultaLogger;
use common\components\Terminology\Snomed\DeferredSnomedProcessor;

/**
 * AnÃ¡lisis IA y persistencia de consultas (agnÃ³stico de capa HTTP).
 * El controller API arma el body, llama aquÃ­ y aplica statusCode segÃºn __statusCode en la respuesta.
 */
class ConsultaProcesamientoService extends Component
{
    public function analizar(array $body): array
    {
        try {
            $userPerTabConfig = $body['userPerTabConfig'] ?? [];
            $idPesTab = $userPerTabConfig['id_profesional_efector_servicio']
                ?? $userPerTabConfig['idProfesionalEfectorServicio'] ?? null;
            $idProfesionalEfectorServicio = (int) ($idPesTab ?: 0);
            $idServicio = $userPerTabConfig['servicio_actual'] ?? null;
            $idConfiguracion = $body['id_configuracion'] ?? null;

            $speech = ClinicalSpeechInputResolver::resolveFromBody($body, 'captura_clinica');
            if (empty($speech['ok'])) {
                return [
                    '__statusCode' => 400,
                    'success' => false,
                    'message' => $speech['message'] ?? 'No se pudo obtener texto para analizar la consulta.',
                    'errors' => isset($speech['quality']) ? ['stt_quality' => $speech['quality']] : null,
                ];
            }
            $textoConsulta = (string) $speech['text'];

            if ($idProfesionalEfectorServicio <= 0 || $textoConsulta === '') {
                return [
                    '__statusCode' => 400,
                    'success' => false,
                    'message' => 'Faltan datos obligatorios. Verifique id_profesional_efector_servicio y el texto de la consulta.',
                    'errors' => null,
                ];
            }

            $tabId = $body['tab_id'] ?? null;
            if (!$tabId) {
                $tabId = 'tab_' . uniqid() . '_' . time();
            }

            $servicio = \common\models\Servicio::findOne($idServicio);
            if (!$servicio) {
                return [
                    '__statusCode' => 400,
                    'success' => false,
                    'message' => 'Servicio no encontrado. Por favor, verifique la configuraciÃ³n.',
                    'errors' => null,
                ];
            }

            $contextoLogger = [
                'id_profesional_efector_servicio' => $idProfesionalEfectorServicio,
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
                $idProfesionalEfectorServicio
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

            // Camino "consulta simple" (CPU, sin GPU) desactivado: siempre modelo IA. Revisar ConsultaClassifier::esConsultaSimple antes de reactivar.
            // $esSimple = ConsultaClassifier::esConsultaSimple($textoProcesado);
            // if ($esSimple) {
            //     $logger->registrar(
            //         'ANÃLISIS SIMPLE',
            //         $textoProcesado,
            //         null,
            //         ['metodo' => 'ConsultaClassifier::procesarConsultaSimple']
            //     );
            //     $resultadoIA = ConsultaClassifier::procesarConsultaSimple($textoProcesado, $servicio->nombre, $categorias);
            //     $logger->registrar(
            //         'ANÃLISIS SIMPLE',
            //         null,
            //         'Consulta simple procesada sin GPU',
            //         [
            //             'metodo' => 'ConsultaClassifier::procesarConsultaSimple',
            //             'categorias_extraidas' => isset($resultadoIA['datosExtraidos']) ? count($resultadoIA['datosExtraidos']) : 0,
            //         ]
            //     );
            // } else {

            $logger->registrar(
                'ANÃLISIS IA',
                $textoProcesado,
                null,
                ['metodo' => 'ConsultaProcesamientoService::analizarConsultaConIA']
            );

            $resultadoIA = $this->analizarConsultaConIA(
                $textoProcesado,
                $servicio->nombre,
                $categorias,
                PatientAiContextBuilder::resolveSubjectPersonaIdFromBody($body)
            );

            $logger->registrar(
                'ANÃLISIS IA',
                null,
                $resultadoIA ? 'AnÃ¡lisis completado' : 'Error en anÃ¡lisis',
                [
                    'metodo' => 'ConsultaProcesamientoService::analizarConsultaConIA',
                    'categorias_extraidas' => $resultadoIA && isset($resultadoIA['datosExtraidos']) ? count($resultadoIA['datosExtraidos']) : 0,
                ]
            );

            // } // al reactivar if ($esSimple) â€¦ else { â€¦ }, descomentar este cierre antes de $datosConSnomed

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
                            Las palabras subrayadas han sido corregidas automÃ¡ticamente
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
                'stt_provenance' => $speech['provenance'] ?? ClinicalSpeechInputResolver::PROVENANCE_TEXT_ONLY,
                'stt_used_server' => !empty($speech['used_server_stt']),
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
            Yii::error('Error en ConsultaProcesamientoService::analizar: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'consulta-ia');

            if (isset($logger)) {
                try {
                    $logger->finalizar([
                        'success' => false,
                        'error' => $e->getMessage(),
                    ]);
                } catch (\Exception $logError) {
                }
            }

            return [
                '__statusCode' => 500,
                'success' => false,
                'message' => 'OcurriÃ³ un error al procesar la consulta. Por favor, intente nuevamente en unos momentos. Si el problema persiste, contacte al soporte tÃ©cnico.',
                'errors' => YII_DEBUG ? [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ];
        }
    }

    public function guardar(array $body): array
    {
        // clean-legacy (Fase 03c): no persistir en tablas legacy `consultas` desde este pipeline.
        // Se mantiene el entrypoint para compatibilidad interna, delegando a EncounterDocumentationService (FHIR).
        try {
            return (new EncounterDocumentationService())->guardar($body);
        } catch (\Throwable $e) {
            Yii::error('Error delegando a EncounterDocumentationService::guardar: ' . $e->getMessage(), 'consulta-guardar');

            return [
                '__statusCode' => 500,
                'success' => false,
                'message' => 'OcurriÃ³ un error al guardar el encounter. Por favor, intente nuevamente.',
                'errors' => YII_DEBUG ? [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ];
        }
    }

    public function analizarConsultaConIA($texto, $servicio, $categorias, ?int $subjectPersonaId = null)
    {
        try {
            $promptData = $this->generarPromptEspecializado($texto, $servicio, $categorias, $subjectPersonaId);

            if ($promptData === null) {
                Yii::error('No se pudo generar el prompt debido a errores en el JSON de ejemplo', 'consulta-ia');
                return [
                    'datosExtraidos' => [
                        'Error' => [
                            'texto' => 'Error en la configuraciÃ³n del sistema. Por favor, contacte al administrador.',
                            'detalle' => 'No se pudo procesar la consulta debido a un error en la configuraciÃ³n.',
                            'tipo' => 'error_configuracion',
                        ],
                    ],
                ];
            }

            $resultado = $this->intentarAnalisisConIA($promptData['prompt'], $texto, $categorias);

            if ($resultado && !isset($resultado['error'])) {
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
                        'texto' => 'OcurriÃ³ un error al procesar la consulta.',
                        'detalle' => 'Por favor, intente nuevamente. Si el problema persiste, contacte al soporte tÃ©cnico.',
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
                                'VALIDACIÃ“N',
                                null,
                                "CategorÃ­a requerida faltante: {$categoria['titulo']}",
                                [
                                    'metodo' => 'ConsultaProcesamientoService::generateAnalysisHtml',
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
                    'VALIDACIÃ“N',
                    null,
                    $tieneDatosFaltantes ? 'Se detectaron datos faltantes' : 'ValidaciÃ³n completada sin datos faltantes',
                    [
                        'metodo' => 'ConsultaProcesamientoService::generateAnalysisHtml',
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

    public function getModelosPorConfiguracion($idConfiguracion)
    {
        if ($idConfiguracion === null || $idConfiguracion === '') {
            return [];
        }
        $configuracion = \common\models\Clinical\EncounterDefinition::findOne($idConfiguracion);
        if (!$configuracion) {
            return [];
        }

        return \common\models\Clinical\EncounterDefinition::getCategoriasParaPrompt($configuracion);
    }

    private function generarPromptEspecializado($texto, $servicio, $categorias, ?int $subjectPersonaId = null)
    {
        $categoriasTexto = $this->construirCategoriasTexto($categorias);
        $jsonEjemplo = $this->generarJsonEjemplo($categorias);

        if ($jsonEjemplo === false) {
            return null;
        }

        $patientBlock = '';
        if ($subjectPersonaId !== null && $subjectPersonaId > 0) {
            $patientBlock = (new PatientAiContextBuilder())->build(
                $subjectPersonaId,
                PatientAiContextBuilder::PROFILE_ENCOUNTER
            );
        }

        $prompt = "Extrae datos en JSON. CategorÃ­as: " . $categoriasTexto . ". Sin datos: [].

IMPORTANTE: Genera un JSON completo y vÃ¡lido. AsegÃºrate de cerrar todas las llaves, corchetes y comillas.

Formato:
{\"datosExtraidos\":{\"categoria\":[\"valor\"]}}";

        if ($patientBlock !== '') {
            $prompt .= "\n\n" . $patientBlock;
        }

        $prompt .= "\n\nTexto: \"" . $texto . "\"

Responde SOLO con el JSON, sin texto adicional antes o despuÃ©s.";

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
}
