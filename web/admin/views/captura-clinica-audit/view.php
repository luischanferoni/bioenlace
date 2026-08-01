<?php

use common\models\Clinical\EncounterCapture;
use common\models\Clinical\EncounterCaptureAudit;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model EncounterCapture */
/* @var $events list<EncounterCaptureAudit> */
/* @var $savedMeta array<string, mixed>|null */

$this->title = 'Captura #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Auditoría de captura clínica', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$sttMeta = $model->getSttMeta();
$acceptance = is_array($savedMeta) ? $savedMeta : null;
?>
<div class="captura-clinica-audit-view">
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
                    'client_capture_id',
                    'stage',
                    'subject_persona_id',
                    'created_by_user_id',
                    [
                        'label' => 'Parent',
                        'value' => $model->parent_type
                            ? ($model->parent_type . ' #' . (int) $model->parent_id)
                            : '—',
                    ],
                    'encounter_id',
                    'created_at:datetime',
                    'updated_at:datetime',
                    'attempts_stt',
                    'attempts_analysis',
                    'attempts_save',
                    'analysis_cache_token',
                    'last_error:ntext',
                    [
                        'label' => 'STT provenance',
                        'value' => $sttMeta['provenance'] ?? '—',
                    ],
                    [
                        'label' => 'Tiene audio',
                        'value' => $model->hasAudio() ? 'Sí' : 'No',
                    ],
                ],
            ]) ?>
        </div>
    </div>

    <?php if ($acceptance !== null): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h2 class="h5 mb-0">Aceptación IA (evento SAVED)</h2>
            </div>
            <div class="card-body">
                <?php $summary = $acceptance['summary'] ?? []; ?>
                <ul class="mb-2">
                    <li>IA aceptadas: <?= (int) ($summary['ai_accepted'] ?? 0) ?></li>
                    <li>IA rechazadas: <?= (int) ($summary['ai_rejected'] ?? 0) ?></li>
                    <li>Clinical desmarcados: <?= (int) ($summary['clinical_deselected'] ?? 0) ?></li>
                </ul>
                <pre class="small bg-light p-2 border rounded" style="max-height: 280px; overflow: auto;"><?= Html::encode(
                    Json::encode($acceptance, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                ) ?></pre>
            </div>
        </div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-header">
            <h2 class="h5 mb-0">Timeline de auditoría</h2>
        </div>
        <div class="card-body">
            <?php if ($events === []): ?>
                <p class="text-muted mb-0">Sin eventos registrados (capturas anteriores a la instrumentación).</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Evento</th>
                                <th>Actor</th>
                                <th>Encounter</th>
                                <th>Meta</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $event): ?>
                                <tr>
                                    <td><?= (int) $event->id ?></td>
                                    <td><?= Html::encode($event->created_at) ?></td>
                                    <td><code><?= Html::encode($event->event_type) ?></code></td>
                                    <td><?= $event->actor_user_id !== null ? (int) $event->actor_user_id : '—' ?></td>
                                    <td><?= $event->encounter_id !== null ? (int) $event->encounter_id : '—' ?></td>
                                    <td>
                                        <pre class="small mb-0" style="max-width: 420px; max-height: 120px; overflow: auto;"><?= Html::encode(
                                            Json::encode($event->getMeta(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                                        ) ?></pre>
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
            <h2 class="h5 mb-0">Transcript</h2>
        </div>
        <div class="card-body">
            <pre class="small mb-0" style="white-space: pre-wrap;"><?= Html::encode((string) ($model->transcript ?? '')) ?></pre>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h2 class="h5 mb-0">Staged item IDs</h2>
        </div>
        <div class="card-body">
            <pre class="small mb-0"><?= Html::encode(
                Json::encode($model->getStagedItemIds(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ) ?></pre>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h2 class="h5 mb-0">Datos extraídos</h2>
        </div>
        <div class="card-body">
            <pre class="small mb-0" style="max-height: 360px; overflow: auto;"><?= Html::encode(
                Json::encode($model->getDatosExtraidos(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ) ?></pre>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h2 class="h5 mb-0">Analysis response (snapshot)</h2>
        </div>
        <div class="card-body">
            <pre class="small mb-0" style="max-height: 360px; overflow: auto;"><?= Html::encode(
                Json::encode($model->getAnalysisResponse(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ) ?></pre>
        </div>
    </div>
</div>
