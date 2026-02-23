<?php

namespace backend\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use common\components\ConversacionLoader;
use common\components\ConsultaIntentRouter;
use common\components\AICostTracker;

/**
 * Pruebas de costos de IA: listar y ejecutar conversaciones (siempre simula IA).
 * Restringido a usuarios autenticados; en producción conviene asignar rol admin o permiso "costos".
 */
class CostosController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Listar conversaciones disponibles (solo metadatos).
     */
    public function actionListarConversaciones()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        return ConversacionLoader::listar();
    }

    /**
     * Ejecutar una conversación por id (tipo/archivo). Siempre simula IA.
     * GET o POST: conversacion=pre_turno/sacar_turno_completo
     */
    public function actionEjecutarConversacion()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $conversacionId = Yii::$app->request->get('conversacion') ?? Yii::$app->request->post('conversacion');
        if (empty($conversacionId)) {
            Yii::$app->response->statusCode = 400;
            return ['error' => 'Parámetro "conversacion" requerido (ej. pre_turno/sacar_turno_completo).'];
        }

        $data = ConversacionLoader::cargar($conversacionId);
        if ($data === null) {
            Yii::$app->response->statusCode = 404;
            return ['error' => "No se encontró la conversación: {$conversacionId}."];
        }

        $userId = $data['userId'] ?? 'test-costos';
        $mensajes = $data['mensajes'];

        AICostTracker::iniciarEjecucionPrueba();
        AICostTracker::reset();

        $respuestas = [];
        foreach ($mensajes as $i => $mensaje) {
            try {
                $result = ConsultaIntentRouter::process($mensaje, $userId, 'BOT');
                $texto = $result['response']['text'] ?? ($result['error'] ?? '');
                $respuestas[] = [
                    'mensaje' => $mensaje,
                    'respuesta' => $texto,
                    'success' => ($result['success'] ?? false),
                ];
            } catch (\Throwable $e) {
                $respuestas[] = [
                    'mensaje' => $mensaje,
                    'error' => $e->getMessage(),
                    'success' => false,
                ];
            }
        }

        AICostTracker::finalizarEjecucionPrueba();
        $resumen = AICostTracker::getResumen();

        return [
            'conversacion' => $conversacionId,
            'nombre' => $data['nombre'] ?? $conversacionId,
            'respuestas' => $respuestas,
            'resumen_costos' => $resumen,
        ];
    }
}
