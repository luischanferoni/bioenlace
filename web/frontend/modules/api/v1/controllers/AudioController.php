<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\components\Ai\SpeechToText\EncounterSpeechInputResolver;
use common\components\Ai\SpeechToText\SpeechToTextManager;
use common\components\Text\ProcesadorTextoMedico;
use common\components\Clinical\Legacy\ConsultaProcesamientoService;

class AudioController extends BaseController
{
    /**
     * Endpoint para recibir audio y transcribirlo
     * POST /api/v1/audio/transcribir
     */
    public function actionTranscribir()
    {
        try {
            $request = Yii::$app->request;
            $body = array_merge(
                $request->post(),
                is_array($request->getBodyParams()) ? $request->getBodyParams() : []
            );
            $audioData = $body['audio'] ?? $body['audio_data'] ?? null;
            $modelo = $body['modelo'] ?? 'economico';
            $procesarInmediatamente = !empty($body['procesar']);

            $speech = EncounterSpeechInputResolver::resolveFromBody($body, 'captura_clinica');
            if (!empty($speech['ok'])) {
                $textoTranscrito = (string) $speech['text'];
                $resultado = [
                    'texto' => $textoTranscrito,
                    'confidence' => 0.9,
                    'modelo_usado' => !empty($speech['used_server_stt']) ? $modelo : 'device',
                ];
            } else {
                if (empty($audioData)) {
                    return [
                        'success' => false,
                        'error' => $speech['message'] ?? 'No se proporcionó audio',
                    ];
                }

                $resultado = SpeechToTextManager::transcribir($audioData, $modelo);
                if (empty($resultado['texto'])) {
                    return [
                        'success' => false,
                        'error' => $resultado['error'] ?? 'No se pudo transcribir el audio',
                        'detalles' => $resultado,
                    ];
                }
                $textoTranscrito = $resultado['texto'];
                $speech = [
                    'ok' => true,
                    'provenance' => EncounterSpeechInputResolver::PROVENANCE_SERVER,
                    'used_server_stt' => true,
                ];
            }

            // Si se solicita procesamiento inmediato, pasar por el pipeline de análisis
            if ($procesarInmediatamente) {
                $userPerTabConfig = $request->post('userPerTabConfig', []);
                $idPesCtx = $userPerTabConfig['idProfesionalEfectorServicio']
                    ?? $userPerTabConfig['id_profesional_efector_servicio'] ?? null;
                $idServicio = $userPerTabConfig['servicio_actual'] ?? null;
                $idConfiguracion = $request->post('id_configuracion');

                $idProfCtx = (int) ($idPesCtx ?? 0);
                if ($idServicio && $idProfCtx > 0) {
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

                    $consultaSvc = new ConsultaProcesamientoService();
                    $categorias = $consultaSvc->getModelosPorConfiguracion($idConfiguracion);
                    $resultadoIA = $consultaSvc->analizarConsultaConIA(
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
                'stt_provenance' => $speech['provenance'] ?? EncounterSpeechInputResolver::PROVENANCE_SERVER,
                'stt_used_server' => !empty($speech['used_server_stt']) || empty($speech['ok']),
                'confidence' => $resultado['confidence'] ?? 0.8,
                'modelo_usado' => $resultado['modelo_usado'] ?? $modelo,
                'tiempo_transcripcion' => $resultado['tiempo_procesamiento'] ?? 0,
            ];

        } catch (\Exception $e) {
            Yii::error("Error en transcripción de audio: " . $e->getMessage(), 'audio-controller');
            return [
                'success' => false,
                'error' => 'Error procesando audio: ' . $e->getMessage()
            ];
        }
    }
}
