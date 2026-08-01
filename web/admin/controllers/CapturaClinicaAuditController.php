<?php

namespace admin\controllers;

use common\components\Domain\Clinical\Service\EncounterCaptureAuditQueryService;
use common\models\Clinical\EncounterCapture;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Auditoría del pipeline de captura clínica (solo superadmin).
 *
 * @see web/docs/plans/auditoria-captura-clinica/design.md
 */
class CapturaClinicaAuditController extends Controller
{
    private EncounterCaptureAuditQueryService $queries;

    public function __construct($id, $module, $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->queries = new EncounterCaptureAuditQueryService();
    }

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (Yii::$app->user->isGuest || !Yii::$app->user->isSuperadmin) {
            throw new ForbiddenHttpException('Solo superadmin puede auditar la captura clínica.');
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
            ],
        ];
    }

    /**
     * Listado filtrable de drafts de captura.
     */
    public function actionIndex()
    {
        $filters = $this->readFilters();

        return $this->render('index', [
            'dataProvider' => $this->queries->buildCaptureListProvider($filters),
            'filters' => $filters,
            'stages' => EncounterCapture::stageValues(),
            'title' => 'Auditoría de captura clínica',
            'failedOnly' => false,
        ]);
    }

    /**
     * Capturas en stages de fallo.
     */
    public function actionFallos()
    {
        $filters = $this->readFilters();

        return $this->render('index', [
            'dataProvider' => $this->queries->buildFailedCaptureListProvider($filters),
            'filters' => $filters,
            'stages' => [
                EncounterCapture::STAGE_STT_FAILED,
                EncounterCapture::STAGE_ANALYSIS_FAILED,
                EncounterCapture::STAGE_SAVE_FAILED,
            ],
            'title' => 'Capturas con fallo',
            'failedOnly' => true,
        ]);
    }

    /**
     * Detalle de un draft + timeline de eventos.
     *
     * @param int $id
     */
    public function actionView($id)
    {
        $model = $this->findModel((int) $id);
        $events = $this->queries->listEventsForCapture((int) $model->id);
        $savedMeta = $this->queries->findLatestSavedMeta((int) $model->id);

        return $this->render('view', [
            'model' => $model,
            'events' => $events,
            'savedMeta' => $savedMeta,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function readFilters(): array
    {
        $q = Yii::$app->request->get();

        return [
            'stage' => isset($q['stage']) ? (string) $q['stage'] : '',
            'subject_persona_id' => isset($q['subject_persona_id']) ? (int) $q['subject_persona_id'] : 0,
            'created_by_user_id' => isset($q['created_by_user_id']) ? (int) $q['created_by_user_id'] : 0,
            'parent_type' => isset($q['parent_type']) ? (string) $q['parent_type'] : '',
            'parent_id' => isset($q['parent_id']) ? (int) $q['parent_id'] : 0,
            'client_capture_id' => isset($q['client_capture_id']) ? (string) $q['client_capture_id'] : '',
            'date_from' => isset($q['date_from']) ? (string) $q['date_from'] : '',
            'date_to' => isset($q['date_to']) ? (string) $q['date_to'] : '',
        ];
    }

    protected function findModel(int $id): EncounterCapture
    {
        $model = EncounterCapture::findOne($id);
        if ($model !== null) {
            return $model;
        }

        throw new NotFoundHttpException('La captura solicitada no existe.');
    }
}
