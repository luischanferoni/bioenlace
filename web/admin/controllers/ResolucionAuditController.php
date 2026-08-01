<?php

namespace admin\controllers;

use common\components\Domain\Scheduling\Service\TurnoResolucionAuditQueryService;
use common\models\Platform\AgentRun;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Auditoría de agentes de resolución A01/A02/A06 (solo superadmin).
 *
 * @see web/docs/plans/auditoria-agentes-autonomos/design.md
 */
class ResolucionAuditController extends Controller
{
    private TurnoResolucionAuditQueryService $queries;

    public function __construct($id, $module, $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->queries = new TurnoResolucionAuditQueryService();
    }

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        if (Yii::$app->user->isGuest || !Yii::$app->user->isSuperadmin) {
            throw new ForbiddenHttpException('Solo superadmin puede auditar resolución de turnos.');
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

    public function actionIndex()
    {
        $filters = $this->readFilters();

        return $this->render('index', [
            'dataProvider' => $this->queries->buildRunListProvider($filters),
            'filters' => $filters,
            'agentOptions' => TurnoResolucionAuditQueryService::agentLabels(),
            'title' => 'Auditoría resolución turnos (A01/A02/A06)',
            'failedOnly' => false,
        ]);
    }

    public function actionFallos()
    {
        $filters = $this->readFilters();

        return $this->render('index', [
            'dataProvider' => $this->queries->buildFailedRunListProvider($filters),
            'filters' => $filters,
            'agentOptions' => TurnoResolucionAuditQueryService::agentLabels(),
            'title' => 'Resolución — fallos / alto impacto',
            'failedOnly' => true,
        ]);
    }

    /**
     * @param int $id agent_run id
     */
    public function actionView($id)
    {
        $model = $this->findModel((int) $id);
        $triggerId = (int) ($model->trigger_id ?? 0);

        return $this->render('view', [
            'model' => $model,
            'familyRuns' => $this->queries->listFamilyRunsForTrigger($triggerId),
            'notifs' => $this->queries->listResolucionNotifsForTurno($triggerId),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function readFilters(): array
    {
        $q = Yii::$app->request->get();

        return [
            'agent_id' => isset($q['agent_id']) ? (string) $q['agent_id'] : '',
            'outcome' => isset($q['outcome']) ? (string) $q['outcome'] : '',
            'trigger_id' => isset($q['trigger_id']) ? (int) $q['trigger_id'] : 0,
            'subject_persona_id' => isset($q['subject_persona_id']) ? (int) $q['subject_persona_id'] : 0,
            'date_from' => isset($q['date_from']) ? (string) $q['date_from'] : '',
            'date_to' => isset($q['date_to']) ? (string) $q['date_to'] : '',
        ];
    }

    protected function findModel(int $id): AgentRun
    {
        $model = AgentRun::find()
            ->where([
                'id' => $id,
                'agent_id' => TurnoResolucionAuditQueryService::AGENT_IDS,
            ])
            ->one();
        if ($model !== null) {
            return $model;
        }

        throw new NotFoundHttpException('El agent_run de resolución no existe.');
    }
}
