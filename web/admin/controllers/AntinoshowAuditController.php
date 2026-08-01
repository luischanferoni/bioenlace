<?php

namespace admin\controllers;

use common\components\Domain\Scheduling\Service\TurnoAntinoshowAuditQueryService;
use common\models\TurnoNotificacionProgramada;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Auditoría del agente A04 anti no-show (solo superadmin).
 *
 * @see web/docs/plans/auditoria-agentes-autonomos/design.md
 */
class AntinoshowAuditController extends Controller
{
    private TurnoAntinoshowAuditQueryService $queries;

    public function __construct($id, $module, $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->queries = new TurnoAntinoshowAuditQueryService();
    }

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        if (Yii::$app->user->isGuest || !Yii::$app->user->isSuperadmin) {
            throw new ForbiddenHttpException('Solo superadmin puede auditar anti no-show.');
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
            'dataProvider' => $this->queries->buildNotifListProvider($filters),
            'filters' => $filters,
            'tipos' => TurnoAntinoshowAuditQueryService::notifTipos(),
            'title' => 'Auditoría anti no-show (A04)',
            'failedOnly' => false,
        ]);
    }

    public function actionFallos()
    {
        $filters = $this->readFilters();

        return $this->render('index', [
            'dataProvider' => $this->queries->buildFailedNotifListProvider($filters),
            'filters' => $filters,
            'tipos' => TurnoAntinoshowAuditQueryService::notifTipos(),
            'title' => 'Anti no-show — fallos / pendientes vencidos',
            'failedOnly' => true,
        ]);
    }

    /**
     * @param int $id id de turno_notificacion_programada
     */
    public function actionView($id)
    {
        $model = $this->findModel((int) $id);
        $idTurno = (int) $model->id_turno;

        return $this->render('view', [
            'model' => $model,
            'siblingNotifs' => $this->queries->listSiblingNotifsForTurno($idTurno),
            'agentRuns' => $this->queries->listAgentRunsForTurno($idTurno),
            'events' => $this->queries->listRelatedEventsForTurno($idTurno),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function readFilters(): array
    {
        $q = Yii::$app->request->get();

        return [
            'tipo' => isset($q['tipo']) ? (string) $q['tipo'] : '',
            'estado' => isset($q['estado']) ? (string) $q['estado'] : '',
            'id_turno' => isset($q['id_turno']) ? (int) $q['id_turno'] : 0,
            'date_from' => isset($q['date_from']) ? (string) $q['date_from'] : '',
            'date_to' => isset($q['date_to']) ? (string) $q['date_to'] : '',
        ];
    }

    protected function findModel(int $id): TurnoNotificacionProgramada
    {
        $model = TurnoNotificacionProgramada::find()
            ->where([
                'id' => $id,
                'tipo' => TurnoAntinoshowAuditQueryService::notifTipos(),
            ])
            ->one();
        if ($model !== null) {
            return $model;
        }

        throw new NotFoundHttpException('La notificación anti no-show no existe.');
    }
}
