<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\components\SpeechToTextManager;
use common\components\ProcesadorTextoMedico;

class AudioController extends BaseController
{
    public $modelClass = null;

    /**
     * Endpoint para recibir audio y transcribirlo
     * POST /api/v1/audio/transcribir
     */
    public function actionTranscribir()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        try {
            $request = Yii::$app->request;
            $audioData = $request->post('audio') ?? $request->post('audio_data');
            $modelo = $request->post('modelo', 'economico');
            $procesarInmediatamente = $request->post('procesar', false);

            if (empty($audioData)) {
                return [
                    'success' => false,
                    'error' => 'No se proporcionó audio'
                ];
            }

            // Transcribir audio a texto
            $resultado = SpeechToTextManager::transcribir($audioData, $modelo);

            if (empty($resultado['texto'])) {
                return [
                    'success' => false,
                    'error' => $resultado['error'] ?? 'No se pudo transcribir el audio',
                    'detalles' => $resultado
                ];
            }

            $textoTranscrito = $resultado['texto'];

            // Si se solicita procesamiento inmediato, pasar por el pipeline de análisis
            if ($procesarInmediatamente) {
                $userPerTabConfig = $request->post('userPerTabConfig', []);
                $idRrHhServicio = $userPerTabConfig['id_rrhh_servicio'] ?? null;
                $idServicio = $userPerTabConfig['servicio_actual'] ?? null;
                $idConfiguracion = $request->post('id_configuracion');

                if ($idRrHhServicio && $idServicio) {
                    $servicio = \common\models\Servicio::findOne($idServicio);
                    $tabId = $request->post('tab_id') ?? 'tab_' . uniqid() . '_' . time();

                    // Procesar el texto transcrito
                    $resultadoProcesamiento = ProcesadorTextoMedico::prepararParaIA(
                        $textoTranscrito,
                        $servicio ? $servicio->nombre : null,
                        $tabId
                    );

                    $textoProcesado = is_array($resultadoProcesamiento) 
                        ? $resultadoProcesamiento['texto_procesado'] 
                        : $resultadoProcesamiento;

                    // Llamar al análisis de consulta
                    $categorias = $this->getModelosPorConfiguracion($idConfiguracion);
                    $consultaController = new \frontend\modules\api\v1\controllers\ConsultaController('consulta', 'frontend\modules\api\v1');
                    $resultadoIA = $consultaController->analizarConsultaConIA(
                        $textoProcesado,
                        $servicio ? $servicio->nombre : null,
                        $categorias
                    );

                    return [
                        'success' => true,
                        'texto_transcrito' => $textoTranscrito,
                        'texto_procesado' => $textoProcesado,
                        'analisis' => $resultadoIA,
                        'confidence' => $resultado['confidence'] ?? 0.8,
                        'modelo_usado' => $resultado['modelo_usado'] ?? $modelo,
                        'tiempo_transcripcion' => $resultado['tiempo_procesamiento'] ?? 0
                    ];
                }
            }

            return [
                'success' => true,
                'texto_transcrito' => $textoTranscrito,
                'confidence' => $resultado['confidence'] ?? 0.8,
                'modelo_usado' => $resultado['modelo_usado'] ?? $modelo,
                'tiempo_transcripcion' => $resultado['tiempo_procesamiento'] ?? 0
            ];

        } catch (\Exception $e) {
            Yii::error("Error en transcripción de audio: " . $e->getMessage(), 'audio-controller');
            return [
                'success' => false,
                'error' => 'Error procesando audio: ' . $e->getMessage()
            ];
        }
    }

    private function getModelosPorConfiguracion($idConfiguracion)
    {
        if (!$idConfiguracion) {
            return [];
        }

        try {
            $configuracion = \common\models\ConsultasConfiguracion::findOne($idConfiguracion);
            if ($configuracion && $configuracion->modelos) {
                return json_decode($configuracion->modelos, true) ?? [];
            }
        } catch (\Exception $e) {
            Yii::error("Error obteniendo configuración: " . $e->getMessage(), 'audio-controller');
        }

        return [];
    }
}
