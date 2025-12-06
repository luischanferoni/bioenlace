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

class ConsultaController extends BaseController
{
    public $modelClass = 'common\models\Consulta';

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        // Ajustar autenticación según necesidades específicas
        // Por defecto BaseController requiere autenticación excepto para 'options'
        return $behaviors;
    }


    public function actionAnalizar()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $body = Yii::$app->request->getBodyParams();
        $userPerTabConfig = $body['userPerTabConfig'] ?? [];
        $idRrHhServicio = $userPerTabConfig['id_rrhh_servicio'] ?? null;        
        $idServicio = $userPerTabConfig['servicio_actual'] ?? null;
        $textoConsulta = $body['consulta'] ?? null;
        $idConfiguracion = $body['id_configuracion'] ?? null;
        
        if (!$idRrHhServicio || !$textoConsulta) {
            return ['success' => false, 'msj' => 'Faltan datos obligatorios: idRrHh y consulta.'];
        }        

        // Obtener o generar tabId para esta pestaña
        $tabId = $body['tab_id'] ?? null;
        if (!$tabId) {
            $tabId = 'tab_' . uniqid() . '_' . time();
        }
        
        // Inicializar logger para esta consulta
        $servicio = \common\models\Servicio::findOne($idServicio);
        $contextoLogger = [
            'idRrHhServicio' => $idRrHhServicio,
            'servicio' => $servicio ? $servicio->nombre : 'Desconocido',
            'tabId' => $tabId
        ];
        $logger = ConsultaLogger::iniciar($textoConsulta, $contextoLogger);
        
        // 1. Corrección ortográfica y expansión de abreviaturas con IA local (Llama 3.1 70B Instruct)
        $logger->registrar(
            'PROCESAMIENTO',
            $textoConsulta,
            null,
            ['metodo' => 'ProcesadorTextoMedico::prepararParaIA']
        );
        
        $resultadoProcesamiento = ProcesadorTextoMedico::prepararParaIA($textoConsulta, $servicio->nombre, $tabId);
        
        // Extraer el texto procesado
        $textoProcesado = is_array($resultadoProcesamiento) ? $resultadoProcesamiento['texto_procesado'] : $resultadoProcesamiento;
        
        $logger->registrar(
            'PROCESAMIENTO',
            null,
            $textoProcesado,
            [
                'metodo' => 'ProcesadorTextoMedico::prepararParaIA'
            ]
        );
        
        // Los logs detallados ya se manejan en ConsultaLogger
        
        // Obtener información de correcciones por tabId
        $correccionesInfo = ProcesadorTextoMedico::obtenerInfoCorrecciones($tabId);
        
        // Los logs de correcciones ya se manejan en ConsultaLogger

        // Obtener categorías para el HTML genérico
        $categorias = $this->getModelosPorConfiguracion($idConfiguracion);
        
        // Llamada a la IA para analizar la consulta con texto expandido
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
        
        // NUEVO: Codificar automáticamente con SNOMED
        $datosConSnomed = null;
        $estadisticasSnomed = null;
        $requiereValidacionSnomed = false;
        
        /*if ($resultadoIA && isset($resultadoIA['datosExtraidos'])) {
            $codificador = new CodificadorSnomedIA();
            $datosConSnomed = $codificador->codificarDatos($resultadoIA, $categorias);
            $estadisticasSnomed = $codificador->getEstadisticasCodificacion();
            $requiereValidacionSnomed = $codificador->hayBajaConfianza();
            
            // Los logs de SNOMED ya se manejan en ConsultaLogger
        }*/
        
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
        
        // Usar información de correcciones desde sesión
        if (!$correccionesInfo) {
            $correccionesInfo = [
                'total_cambios' => 0,
                'cambios_automaticos' => [],
            ];
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
            'correcciones' => $correccionesInfo,
            'texto_original' => $textoConsulta,
            'texto_procesado' => $textoProcesado,
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
    }

    private function analizarConsultaConIA($texto, $servicio, $categorias)
    {
        // Intentar primero con el prompt especializado
        $promptData = $this->generarPromptEspecializado($texto, $servicio, $categorias);
        //var_dump($promptData['prompt']);die;
        // Si hay error en la generación del prompt, retornar error inmediatamente
        if ($promptData === null) {
            \Yii::error('No se pudo generar el prompt debido a errores en el JSON de ejemplo', 'consulta-ia');
            return [
                'error' => 'Contactar administrador',
            ];
        }
        
        $resultado = $this->intentarAnalisisConIA($promptData['prompt'], $texto, $categorias);

        if ($resultado) {
            return $resultado;
        }

        // Retornar datos por defecto en caso de error
        return [
            'diagnostico' => 'Error al analizar con IA',
            'practicas' => ['Revisar manualmente'],
            'prescripciones' => ['Consultar con especialista']
        ];
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
        // Prompt optimizado (más corto)
        $prompt = "Analiza consulta de $servicio y devuelve JSON con: sugerencias_diagnosticas, sugerencias_practicas, sugerencias_seguimiento, alertas (arrays).

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

        // Prompt optimizado (más corto para reducir costos)
        $prompt = "Analiza el texto clínico y extrae información estructurada en JSON. Categorías: " . $categoriasTexto . ". Si no hay información para una categoría, usa [].

Formato JSON:
{
    \"datosExtraidos\": {
        \"categoria\": [\"valor\"]
    }
}

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