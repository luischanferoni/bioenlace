<?php

namespace admin\controllers;

use common\components\Platform\Ai\Cost\AICostEstimationService;
use common\components\Platform\Ai\Cost\AICostReferenceMetadata;
use common\components\Platform\Ai\Cost\AICostTracker;
use common\components\Platform\Ai\Cost\ConversacionCostosService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Análisis de costos de uso de IA (pruebas simuladas y telemetría).
 *
 * @see web/docs/costos/pruebas-costos-ia.md
 */
class CostosController extends Controller
{
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (Yii::$app->user->isGuest || !Yii::$app->user->isSuperadmin) {
            throw new ForbiddenHttpException('Solo superadmin puede acceder al análisis de costos de IA.');
        }

        return true;
    }

    public function behaviors()
    {
        return [
            'ghost-access' => [
                'class' => \frontend\components\BioenlaceAdminAccessControl::class,
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'ejecutar' => ['POST'],
                    'ejecutar-todas' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Catálogo de conversaciones de prueba y estado del tracking.
     */
    public function actionIndex()
    {
        return $this->render('index', [
            'conversaciones' => ConversacionCostosService::listarConversaciones(),
            'estado' => $this->estadoTracking(),
            'referencia' => AICostReferenceMetadata::load(),
        ]);
    }

    /**
     * Ejecuta una conversación simulada y muestra métricas.
     *
     * @throws NotFoundHttpException
     */
    public function actionEjecutar()
    {
        $ruta = trim((string) Yii::$app->request->post('conversacion', ''));
        if ($ruta === '') {
            Yii::$app->session->setFlash('error', 'Debe indicar una conversación.');

            return $this->redirect(['index']);
        }

        try {
            $resultado = ConversacionCostosService::ejecutar($ruta, (int) Yii::$app->user->id);
        } catch (\InvalidArgumentException $e) {
            throw new NotFoundHttpException($e->getMessage());
        }

        return $this->render('resultado', [
            'titulo' => 'Resultado: ' . ($resultado['conversacion']['nombre'] ?? $ruta),
            'resultado' => $resultado,
            'estado' => $this->estadoTracking(),
        ]);
    }

    /**
     * Ejecuta todas las conversaciones y muestra resumen agregado.
     */
    public function actionEjecutarTodas()
    {
        $batch = ConversacionCostosService::ejecutarTodas((int) Yii::$app->user->id);

        return $this->render('resultado-todas', [
            'batch' => $batch,
            'estado' => $this->estadoTracking(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function estadoTracking(): array
    {
        $params = Yii::$app->params;

        return [
            'ia_usage_tracking_habilitado' => (bool) ($params['ia_usage_tracking_habilitado'] ?? false),
            'vertex_context_cache_simulado' => (bool) ($params['vertex_context_cache_simulado'] ?? false),
            'vertex_ai_model' => (string) ($params['vertex_ai_model'] ?? ''),
            'modelo_referencia' => AICostReferenceMetadata::modeloReferencia(),
            'tracker_activo_ahora' => AICostTracker::trackingHabilitado(),
            'estimacion_ejemplo' => AICostEstimationService::estimarDesdeResumen([
                'llamada_simulada' => 1,
                'por_contexto' => [
                    'asistente-preprocess' => ['llamadas' => 1],
                ],
                'tokens' => [
                    'prompt_token_count' => 0,
                    'cached_content_token_count' => 0,
                    'candidates_token_count' => 0,
                ],
            ]),
        ];
    }
}
