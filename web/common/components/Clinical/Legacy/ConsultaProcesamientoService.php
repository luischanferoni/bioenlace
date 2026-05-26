<?php

namespace common\components\Clinical\Legacy;

use Yii;
use yii\base\Component;
use common\components\Clinical\Workflow\EncounterDocumentationService;
use common\components\Text\ProcesadorTextoMedico;
use common\components\Logging\ConsultaLogger;
use common\components\Terminology\Snomed\DeferredSnomedProcessor;

/**
 * Análisis IA y persistencia de consultas (agnóstico de capa HTTP).
 * El controller API arma el body, llama aquí y aplica statusCode según __statusCode en la respuesta.
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
            $textoConsulta = $body['consulta'] ?? null;
            $idConfiguracion = $body['id_configuracion'] ?? null;

            if ($idProfesionalEfectorServicio <= 0 || !$textoConsulta) {
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
            //         'ANÁLISIS SIMPLE',
            //         $textoProcesado,
            //         null,
            //         ['metodo' => 'ConsultaClassifier::procesarConsultaSimple']
            //     );
            //     $resultadoIA = ConsultaClassifier::procesarConsultaSimple($textoProcesado, $servicio->nombre, $categorias);
            //     $logger->registrar(
            //         'ANÁLISIS SIMPLE',
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

            $resultadoIA = $this->analizarConsultaConIA($textoProcesado, $servicio->nombre, $categorias);

            $logger->registrar(
                'ANÁLISIS IA',
                null,
                $resultadoIA ? 'Análisis completado' : 'Error en análisis',
                [
                    'metodo' => 'ConsultaProcesamientoService::analizarConsultaConIA',
                    'categorias_extraidas' => $resultadoIA && isset($resultadoIA['datosExtraidos']) ? count($resultadoIA['datosExtraidos']) : 0,
                ]
            );

            // } // al reactivar if ($esSimple) … else { … }, descomentar este cierre antes de $datosConSnomed

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

    public function analizarConsultaConIA($texto, $servicio, $categorias)
    {
        try {
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
        $configuracion = \common\models\ConsultasConfiguracion::findOne($idConfiguracion);
        if (!$configuracion) {
            return [];
        }

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
            'ConsultaMedicamentos' => 'medicamentos',
            'ConsultaSintomas' => 'sintomas',
            'ConsultaMotivos' => 'motivoConsulta',
            'ConsultaPracticas' => 'practicasPostDiagnostico',
            'ConsultaPracticasOftalmologia' => 'oftalmologiasDP',
            'ConsultaDiagnosticos' => 'diagnosticos',
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

