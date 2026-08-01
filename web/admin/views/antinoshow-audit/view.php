<?php

use common\components\Platform\Agent\AgentRunAuditQueryService;
use common\models\Platform\AgentRun;
use common\models\TurnoEventoAudit;
use common\models\TurnoNotificacionProgramada;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model TurnoNotificacionProgramada */
/* @var $siblingNotifs list<TurnoNotificacionProgramada> */
/* @var $agentRuns list<AgentRun> */
/* @var $events list<TurnoEventoAudit> */

$this->title = 'Anti no-show notif #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Auditoría anti no-show (A04)', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$payload = AgentRunAuditQueryService::decodeJson($model->payload_json ?? null);
?>
<div class="antinoshow-audit-view">
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h1 class="card-title mt-1 mb-0"><?= Html::encode($this->title) ?></h1>
            <div>
                <?= Html::a('Fallos', ['fallos'], ['class' => 'btn btn-outline-danger btn-sm']) ?>
                <?= Html::a('Listado', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            </div>
        </div>
        <div class="card-body">
            <?= DetailView::widget([
                'model' => $model,
                'attributes' => [
                    'id',
                    'id_turno',
                    'tipo',
                    'estado',
                    'run_at:datetime',
                    'intentos',
                    'ultimo_error:ntext',
                    'created_at',
                    'updated_at',
                ],
            ]) ?>
            <h3 class="h6">payload_json</h3>
            <pre class="small bg-light p-2 border rounded"><?= Html::encode(
                Json::encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ) ?></pre>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h2 class="h5 mb-0">Notifs A04 del turno</h2></div>
        <div class="card-body">
            <ul class="mb-0">
                <?php foreach ($siblingNotifs as $n): ?>
                    <li>
                        <?= Html::a(
                            '#' . $n->id . ' ' . $n->tipo . ' ' . $n->estado . ' @ ' . $n->run_at,
                            ['view', 'id' => $n->id]
                        ) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h2 class="h5 mb-0">agent_run (turno-antinoshow)</h2></div>
        <div class="card-body">
            <?php if ($agentRuns === []): ?>
                <p class="text-muted mb-0">Sin agent_run para este turno.</p>
            <?php else: ?>
                <table class="table table-sm">
                    <thead><tr><th>ID</th><th>Fecha</th><th>Trigger</th><th>Outcome</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($agentRuns as $run): ?>
                            <tr>
                                <td><?= (int) $run->id ?></td>
                                <td><?= Html::encode($run->created_at) ?></td>
                                <td><?= Html::encode($run->trigger_type) ?></td>
                                <td><code><?= Html::encode($run->outcome) ?></code></td>
                                <td><?= Html::a('Ver', ['/agent-run-audit/view', 'id' => $run->id]) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h2 class="h5 mb-0">Eventos relacionados</h2></div>
        <div class="card-body">
            <?php if ($events === []): ?>
                <p class="text-muted mb-0">Sin eventos de confirmación / liberación.</p>
            <?php else: ?>
                <table class="table table-sm">
                    <thead><tr><th>ID</th><th>Occurred</th><th>Tipo</th></tr></thead>
                    <tbody>
                        <?php foreach ($events as $ev): ?>
                            <tr>
                                <td><?= (int) $ev->id ?></td>
                                <td><?= Html::encode($ev->occurred_at ?: $ev->created_at) ?></td>
                                <td>
                                    <code><?= Html::encode($ev->tipo_evento) ?></code>
                                    <div class="small text-muted"><?= Html::encode(TurnoEventoAudit::etiquetaTipoEvento((string) $ev->tipo_evento)) ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
