<?php

use common\components\Platform\Agent\AgentRunAuditQueryService;
use common\models\Platform\AgentRun;
use common\models\TurnoNotificacionProgramada;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model AgentRun */
/* @var $familyRuns list<AgentRun> */
/* @var $notifs list<TurnoNotificacionProgramada> */

$this->title = 'Resolución run #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Auditoría resolución', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$facts = AgentRunAuditQueryService::decodeJson($model->facts_json ?? null);
$decision = AgentRunAuditQueryService::decodeJson($model->decision_json ?? null);
?>
<div class="resolucion-audit-view">
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h1 class="card-title mt-1 mb-0"><?= Html::encode($this->title) ?></h1>
            <div>
                <?= Html::a('Alto impacto', ['fallos'], ['class' => 'btn btn-outline-danger btn-sm']) ?>
                <?= Html::a('Listado', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                <?= Html::a('agent_run genérico', ['/agent-run-audit/view', 'id' => $model->id], [
                    'class' => 'btn btn-outline-secondary btn-sm',
                ]) ?>
            </div>
        </div>
        <div class="card-body">
            <?= DetailView::widget([
                'model' => $model,
                'attributes' => [
                    'id',
                    'created_at:datetime',
                    [
                        'attribute' => 'agent_id',
                        'value' => AgentRunAuditQueryService::agentLabel((string) $model->agent_id)
                            . ' (' . $model->agent_id . ')',
                    ],
                    'outcome',
                    'trigger_type',
                    [
                        'attribute' => 'trigger_id',
                        'label' => 'Turno',
                    ],
                    'subject_persona_id',
                    'rule_id',
                ],
            ]) ?>
            <h3 class="h6">facts</h3>
            <pre class="small bg-light p-2 border rounded" style="max-height: 240px; overflow: auto;"><?= Html::encode(
                Json::encode($facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ) ?></pre>
            <h3 class="h6">decision</h3>
            <pre class="small bg-light p-2 border rounded" style="max-height: 240px; overflow: auto;"><?= Html::encode(
                Json::encode($decision, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ) ?></pre>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h2 class="h5 mb-0">Familia de runs del mismo turno</h2></div>
        <div class="card-body">
            <?php if ($familyRuns === []): ?>
                <p class="text-muted mb-0">Sin otros runs.</p>
            <?php else: ?>
                <ul class="mb-0">
                    <?php foreach ($familyRuns as $run): ?>
                        <li>
                            <?= Html::a(
                                '#' . $run->id . ' [' . AgentRunAuditQueryService::agentLabel((string) $run->agent_id)
                                    . '] ' . $run->outcome . ' @ ' . $run->created_at,
                                ['view', 'id' => $run->id]
                            ) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h2 class="h5 mb-0">Notifs multicanal / loop-close</h2></div>
        <div class="card-body">
            <?php if ($notifs === []): ?>
                <p class="text-muted mb-0">Sin notificaciones programadas de resolución para este turno.</p>
            <?php else: ?>
                <table class="table table-sm">
                    <thead><tr><th>ID</th><th>Tipo</th><th>Estado</th><th>run_at</th><th>Error</th></tr></thead>
                    <tbody>
                        <?php foreach ($notifs as $n): ?>
                            <tr>
                                <td><?= (int) $n->id ?></td>
                                <td><?= Html::encode($n->tipo) ?></td>
                                <td><?= Html::encode($n->estado) ?></td>
                                <td><?= Html::encode($n->run_at) ?></td>
                                <td class="small"><?= Html::encode($n->ultimo_error ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
