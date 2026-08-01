<?php

namespace admin\controllers;

use common\components\Platform\Agent\AgentRunAuditQueryService;
use common\models\Platform\AgentRun;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Auditoría genérica de agent_run (solo superadmin).
 *
 * @see web/docs/plans/auditoria-agentes-autonomos/design.md
 */
class AgentRunAuditController extends Controller
{
    private AgentRunAuditQueryService $queries;

    public function __construct($id, $module, $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->queries = new AgentRunAuditQueryService();
    }

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        if (Yii::$app->user->isGuest || !Yii::$app->user->isSuperadmin) {
            throw new ForbiddenHttpException('Solo superadmin puede auditar agent_run.');
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
        $agentIds = $this->queries->listAgentIds();
        $agentOptions = [];
        foreach ($agentIds as $aid) {
            $agentOptions[$aid] = AgentRunAuditQueryService::agentLabel($aid);
        }

        return $this->render('index', [
            'dataProvider' => $this->queries->buildListProvider($filters),
            'filters' => $filters,
            'agentOptions' => $agentOptions,
        ]);
    }

    /**
     * @param int $id
     */
    public function actionView($id)
    {
        $model = $this->findModel((int) $id);
        $siblings = $this->queries->listSiblingRuns($model);

        return $this->render('view', [
            'model' => $model,
            'siblings' => $siblings,
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
            'trigger_type' => isset($q['trigger_type']) ? (string) $q['trigger_type'] : '',
            'trigger_id' => isset($q['trigger_id']) ? (int) $q['trigger_id'] : 0,
            'subject_persona_id' => isset($q['subject_persona_id']) ? (int) $q['subject_persona_id'] : 0,
            'encounter_id' => isset($q['encounter_id']) ? (int) $q['encounter_id'] : 0,
            'date_from' => isset($q['date_from']) ? (string) $q['date_from'] : '',
            'date_to' => isset($q['date_to']) ? (string) $q['date_to'] : '',
        ];
    }

    protected function findModel(int $id): AgentRun
    {
        $model = AgentRun::findOne($id);
        if ($model !== null) {
            return $model;
        }

        throw new NotFoundHttpException('El agent_run no existe.');
    }
}
