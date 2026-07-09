<?php

namespace common\components\Domain\Clinical\Legacy;

use Yii;
use yii\base\Component;
use common\components\Domain\Clinical\SpeechToText\ClinicalSpeechInputResolver;
use common\components\Domain\Clinical\AiContext\PatientAiContextBuilder;
use common\components\Domain\Clinical\Presentation\EncounterCaptureReviewPresenter;
use common\components\Domain\Clinical\Workflow\EncounterDocumentationService;
use common\components\Domain\Clinical\Text\ProcesadorTextoMedico;

/**
 * Análisis IA y persistencia de consultas (agnóstico de capa HTTP).
 * El controller API arma el body, llama aquí y aplica statusCode según __statusCode en la respuesta.
 */
class ConsultaProcesamientoService extends Component
{
    public function analizar(array $body): array
    {
        try {
            [$idProfesionalEfectorServicio, $idServicio] = self::resolveOperationalContext($body);
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
                    'message' => 'Servicio no encontrado. Por favor, verifique la configuración.',
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
                'ANÁLISIS IA',
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
                'ANÁLISIS IA',
                null,
                $resultadoIA ? 'Análisis completado' : 'Error en análisis',
                [
                    'metodo' => 'ConsultaProcesamientoService::analizarConsultaConIA',
                    'categorias_extraidas' => $resultadoIA && isset($resultadoIA['datosExtraidos']) ? count($resultadoIA['datosExtraidos']) : 0,
                ]
            );

            // } // al reactivar if ($esSimple) â€¦ else { â€¦ }, descomentar este cierre antes de $datosConSnomed

            // Codificación CIE-10/SNOMED: al guardar encounter vía EncounterAutomaticCodingService (IA + persistencia).

            if ($resultadoIA) {
                $datos = self::normalizeResultadoIa($resultadoIA);
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

            $extraidos = self::resolveDatosExtraidos($datos);

            $htmlResult = $this->generateAnalysisHtml($extraidos, $sugerencias, $categorias);
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

            $captureReview = (new EncounterCaptureReviewPresenter())->build(
                $datos,
                $categorias,
                $textoConsulta,
                $textoProcesado,
                $tieneDatosFaltantes
            );

            $resultado = [
                'success' => true,
                'datos' => $datos,
                'html' => $html,
                'capture_review' => $captureReview,
                'puede_confirmar' => $captureReview['puede_confirmar'] ?? false,
                'stt_provenance' => $speech['provenance'] ?? ClinicalSpeechInputResolver::PROVENANCE_TEXT_ONLY,
                'stt_used_server' => !empty($speech['used_server_stt']),
                'texto_original' => $textoConsulta,
                'texto_procesado' => $textoProcesado,
                'texto_formateado' => $textoFormateado ?? null,
                'tab_id' => $tabId,
                'sugerencias' => $sugerencias,
                'tiene_datos_faltantes' => $tieneDatosFaltantes,
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
                'message' => 'Ocurrió un error al procesar la consulta. Por favor, intente nuevamente en unos momentos. Si el problema persiste, contacte al soporte técnico.',
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
                'message' => 'Ocurrió un error al guardar el encounter. Por favor, intente nuevamente.',
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
                            'texto' => 'Error en la configuración del sistema. Por favor, contacte al administrador.',
                            'detalle' => 'No se pudo procesar la consulta debido a un error en la configuración.',
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
                        'texto' => 'Ocurrió un error al procesar la consulta.',
                        'detalle' => 'Por favor, intente nuevamente. Si el problema persiste, contacte al soporte técnico.',
                        'tipo' => 'error_sistema',
                    ],
                ],
            ];
        }
    }

    private static function normalizeResultadoIa(mixed $resultadoIA): array
    {
        if (is_string($resultadoIA)) {
            $decoded = json_decode(trim($resultadoIA), true);
            if (is_array($decoded)) {
                $resultadoIA = $decoded;
            } else {
                return [
                    'datosExtraidos' => [
                        'Error' => [
                            'texto' => 'La IA devolvió una respuesta no estructurada.',
                            'detalle' => 'Intente analizar nuevamente o revise el texto manualmente.',
                            'tipo' => 'error_ia',
                        ],
                    ],
                ];
            }
        }

        if (!is_array($resultadoIA)) {
            return [
                'datosExtraidos' => [
                    'Error' => [
                        'texto' => 'No se pudo procesar la consulta con IA',
                        'detalle' => 'Revisar manualmente la consulta',
                        'tipo' => 'error_sistema',
                    ],
                ],
            ];
        }

        if (isset($resultadoIA['datosExtraidos']) && is_array($resultadoIA['datosExtraidos'])) {
            return $resultadoIA;
        }

        if (self::looksLikeExtraidosMap($resultadoIA)) {
            return ['datosExtraidos' => $resultadoIA];
        }

        return $resultadoIA;
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private static function resolveDatosExtraidos(array $datos): array
    {
        $extraidos = $datos['datosExtraidos'] ?? null;
        if (is_array($extraidos)) {
            return $extraidos;
        }
        if (is_string($extraidos)) {
            $decoded = json_decode($extraidos, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return self::looksLikeExtraidosMap($datos) ? $datos : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function looksLikeExtraidosMap(array $data): bool
    {
        foreach (array_keys($data) as $key) {
            if ($key === 'informacionFaltante' || $key === 'error') {
                continue;
            }
            return true;
        }

        return false;
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
                    'VALIDACIÓN',
                    null,
                    $tieneDatosFaltantes ? 'Se detectaron datos faltantes' : 'Validación completada sin datos faltantes',
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

        $prompt = "Extrae datos en JSON. Categorías: " . $categoriasTexto . ". Sin datos: [].

IMPORTANTE: Genera un JSON completo y válido. Asegúrate de cerrar todas las llaves, corchetes y comillas.

Formato:
{\"datosExtraidos\":{\"categoria\":[\"valor\"]}}";

        if ($patientBlock !== '') {
            $prompt .= "\n\n" . $patientBlock;
        }

        $prompt .= "\n\nTexto: \"" . $texto . "\"

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

    /**
     * PES y servicio desde userPerTabConfig (web), campos planos del body (móvil) o sesión/JWT.
     *
     * @param array<string, mixed> $body
     * @return array{0: int, 1: int|null}
     */
    private static function resolveOperationalContext(array $body): array
    {
        $userPerTabConfig = $body['userPerTabConfig'] ?? [];
        if (!is_array($userPerTabConfig)) {
            $userPerTabConfig = [];
        }

        $idPesTab = $userPerTabConfig['id_profesional_efector_servicio']
            ?? $userPerTabConfig['idProfesionalEfectorServicio']
            ?? $body['id_profesional_efector_servicio']
            ?? null;
        $idPes = (int) ($idPesTab ?: 0);
        if ($idPes <= 0) {
            $sessionPes = Yii::$app->user->getIdProfesionalEfectorServicio();
            if ($sessionPes !== null && $sessionPes !== '') {
                $idPes = (int) $sessionPes;
            }
        }

        $idServicioRaw = $userPerTabConfig['servicio_actual']
            ?? $body['servicio_actual']
            ?? $body['servicio_id']
            ?? null;
        if ($idServicioRaw === null || $idServicioRaw === '') {
            $sessionSvc = Yii::$app->user->getServicioActual();
            $idServicio = ($sessionSvc !== null && $sessionSvc !== '') ? (int) $sessionSvc : null;
        } else {
            $idServicio = (int) $idServicioRaw;
        }

        return [$idPes, $idServicio];
    }
}
