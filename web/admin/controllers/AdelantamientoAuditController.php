<?php

namespace admin\controllers;

use common\components\Domain\Scheduling\Service\TurnoAdvanceOfferAuditQueryService;
use common\models\Scheduling\TurnoAdvanceCampaign;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Auditoría del agente A03 de adelantamiento por cancelación (solo superadmin).
 *
 * @see web/docs/plans/auditoria-adelantamiento-turnos/design.md
 */
class AdelantamientoAuditController extends Controller
{
    private TurnoAdvanceOfferAuditQueryService $queries;

    public function __construct($id, $module, $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->queries = new TurnoAdvanceOfferAuditQueryService();
    }

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (Yii::$app->user->isGuest || !Yii::$app->user->isSuperadmin) {
            throw new ForbiddenHttpException('Solo superadmin puede auditar adelantamientos de turnos.');
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
     * Listado filtrable de campañas de adelantamiento.
     */
    public function actionIndex()
    {
        $filters = $this->readFilters();

        return $this->render('index', [
            'dataProvider' => $this->queries->buildCampaignListProvider($filters),
            'filters' => $filters,
            'estados' => TurnoAdvanceCampaign::estadoValues(),
            'title' => 'Auditoría de adelantamiento (A03)',
            'failedOnly' => false,
        ]);
    }

    /**
     * Campañas detenidas, agotadas o activas con next_run vencido.
     */
    public function actionFallos()
    {
        $filters = $this->readFilters();

        return $this->render('index', [
            'dataProvider' => $this->queries->buildFailedCampaignListProvider($filters),
            'filters' => $filters,
            'estados' => [
                TurnoAdvanceCampaign::ESTADO_STOPPED,
                TurnoAdvanceCampaign::ESTADO_EXHAUSTED,
                TurnoAdvanceCampaign::ESTADO_ACTIVE,
            ],
            'title' => 'Adelantamiento — fallos / atascadas',
            'failedOnly' => true,
        ]);
    }

    /**
     * Detalle de campaña: ofertas, eventos canónicos y agent_run.
     *
     * @param int $id
     */
    public function actionView($id)
    {
        $model = $this->findModel((int) $id);
        $offers = $this->queries->listOffersForCampaign((int) $model->id);
        $events = $this->queries->listAdvanceEventsForCampaign($model);
        $agentRuns = $this->queries->listAgentRunsForCampaign((int) $model->id);
        $offerCounts = $this->queries->offerCountsByEstado((int) $model->id);

        return $this->render('view', [
            'model' => $model,
            'offers' => $offers,
            'events' => $events,
            'agentRuns' => $agentRuns,
            'offerCounts' => $offerCounts,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function readFilters(): array
    {
        $q = Yii::$app->request->get();

        return [
            'estado' => isset($q['estado']) ? (string) $q['estado'] : '',
            'id_efector' => isset($q['id_efector']) ? (int) $q['id_efector'] : 0,
            'id_servicio' => isset($q['id_servicio']) ? (int) $q['id_servicio'] : 0,
            'id_profesional_efector_servicio' => isset($q['id_profesional_efector_servicio'])
                ? (int) $q['id_profesional_efector_servicio']
                : 0,
            'id_cancelled_turno' => isset($q['id_cancelled_turno']) ? (int) $q['id_cancelled_turno'] : 0,
            'slot_fecha' => isset($q['slot_fecha']) ? (string) $q['slot_fecha'] : '',
            'date_from' => isset($q['date_from']) ? (string) $q['date_from'] : '',
            'date_to' => isset($q['date_to']) ? (string) $q['date_to'] : '',
        ];
    }

    protected function findModel(int $id): TurnoAdvanceCampaign
    {
        $model = TurnoAdvanceCampaign::findOne($id);
        if ($model !== null) {
            return $model;
        }

        throw new NotFoundHttpException('La campaña de adelantamiento no existe.');
    }
}
