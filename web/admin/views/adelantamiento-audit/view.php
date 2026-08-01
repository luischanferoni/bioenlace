<?php

use common\models\Platform\AgentRun;
use common\models\Scheduling\TurnoAdvanceCampaign;
use common\models\Scheduling\TurnoAdvanceOffer;
use common\models\TurnoEventoAudit;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model TurnoAdvanceCampaign */
/* @var $offers list<TurnoAdvanceOffer> */
/* @var $events list<TurnoEventoAudit> */
/* @var $agentRuns list<AgentRun> */
/* @var $offerCounts array<string, int> */

$this->title = 'Campaña adelantamiento #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Auditoría de adelantamiento (A03)', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="adelantamiento-audit-view">
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
                    'estado',
                    'stop_reason',
                    [
                        'label' => 'Slot liberado',
                        'value' => $model->fecha . ' ' . $model->hora . ' (' . $model->modalidad . ')',
                    ],
                    'id_cancelled_turno',
                    'id_turno_filled',
                    'id_efector',
                    'id_servicio',
                    'id_profesional_efector_servicio',
                    'current_sequence',
                    'next_run_at',
                    'created_at:datetime',
                    'updated_at',
                ],
            ]) ?>

            <p class="mb-0 small">
                Ofertas:
                <?php foreach ($offerCounts as $estado => $n): ?>
                    <?php if ($n > 0): ?>
                        <span class="badge text-bg-secondary me-1"><?= Html::encode($estado) ?>: <?= (int) $n ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if (array_sum($offerCounts) === 0): ?>
                    <span class="text-muted">ninguna</span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h2 class="h5 mb-0">Ofertas (secuencia)</h2>
        </div>
        <div class="card-body">
            <?php if ($offers === []): ?>
                <p class="text-muted mb-0">Sin ofertas. La campaña no llegó a notificar candidatos (o aún no corrió el cron).</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Seq</th>
                                <th>Estado</th>
                                <th>Turno candidato</th>
                                <th>Persona</th>
                                <th>Ofrecida</th>
                                <th>Vence</th>
                                <th>Decidida</th>
                                <th>Detalle</th>
                                <th>Notif</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($offers as $offer): ?>
                                <tr>
                                    <td><?= (int) $offer->sequence ?></td>
                                    <td><code><?= Html::encode($offer->estado) ?></code></td>
                                    <td><?= (int) $offer->id_turno_candidate ?></td>
                                    <td><?= (int) $offer->subject_persona_id ?></td>
                                    <td><?= Html::encode($offer->offered_at) ?></td>
                                    <td><?= Html::encode($offer->expires_at) ?></td>
                                    <td><?= Html::encode($offer->decided_at ?: '—') ?></td>
                                    <td><?= Html::encode($offer->result_detail ?: '—') ?></td>
                                    <td class="small"><?= Html::encode($offer->notification_ref ?: '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h2 class="h5 mb-0">Eventos canónicos (ADVANCE_*)</h2>
        </div>
        <div class="card-body">
            <?php if ($events === []): ?>
                <p class="text-muted mb-0">Sin eventos ADVANCE_* asociados.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Occurred</th>
                                <th>Tipo</th>
                                <th>Turno</th>
                                <th>Persona</th>
                                <th>Meta</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $event): ?>
                                <tr>
                                    <td><?= (int) $event->id ?></td>
                                    <td><?= Html::encode($event->occurred_at ?: $event->created_at) ?></td>
                                    <td>
                                        <code><?= Html::encode($event->tipo_evento) ?></code>
                                        <div class="small text-muted"><?= Html::encode(TurnoEventoAudit::etiquetaTipoEvento((string) $event->tipo_evento)) ?></div>
                                    </td>
                                    <td><?= (int) $event->id_turno ?></td>
                                    <td><?= $event->id_persona !== null ? (int) $event->id_persona : '—' ?></td>
                                    <td>
                                        <pre class="small mb-0" style="max-width: 360px; max-height: 100px; overflow: auto;"><?php
                                            $meta = [];
                                            if (is_string($event->meta_json) && $event->meta_json !== '') {
                                                $decoded = json_decode($event->meta_json, true);
                                                $meta = is_array($decoded) ? $decoded : ['raw' => $event->meta_json];
                                            }
                                            echo Html::encode(Json::encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                        ?></pre>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h2 class="h5 mb-0">agent_run (turno-advance-offer)</h2>
        </div>
        <div class="card-body">
            <?php if ($agentRuns === []): ?>
                <p class="text-muted mb-0">Sin filas en agent_run para esta campaña (flag de auditoría o corridas anteriores).</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Trigger</th>
                                <th>Outcome</th>
                                <th>Persona</th>
                                <th>Facts</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($agentRuns as $run): ?>
                                <tr>
                                    <td><?= (int) $run->id ?></td>
                                    <td><?= Html::encode($run->created_at) ?></td>
                                    <td><?= Html::encode($run->trigger_type) ?></td>
                                    <td><code><?= Html::encode($run->outcome) ?></code></td>
                                    <td><?= $run->subject_persona_id !== null ? (int) $run->subject_persona_id : '—' ?></td>
                                    <td>
                                        <pre class="small mb-0" style="max-width: 360px; max-height: 100px; overflow: auto;"><?php
                                            $facts = [];
                                            if (is_string($run->facts_json) && $run->facts_json !== '') {
                                                $decoded = json_decode($run->facts_json, true);
                                                $facts = is_array($decoded) ? $decoded : ['raw' => $run->facts_json];
                                            }
                                            echo Html::encode(Json::encode($facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                        ?></pre>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
