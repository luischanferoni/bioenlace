<?php

use common\models\Clinical\EncounterCapture;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $filters array<string, mixed> */
/* @var $stages list<string> */
/* @var $title string */
/* @var $failedOnly bool */

$this->title = $title;
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="captura-clinica-audit-index">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h1 class="card-title mt-1 mb-0"><?= Html::encode($this->title) ?></h1>
            <div>
                <?php if ($failedOnly): ?>
                    <?= Html::a('Ver todas', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                <?php else: ?>
                    <?= Html::a('Solo fallos', ['fallos'], ['class' => 'btn btn-outline-danger btn-sm']) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted small">
                Trail del pipeline de captura clínica. Solo visible para superadmin. Contiene datos clínicos (PHI).
            </p>

            <form method="get" action="<?= Html::encode(Url::to($failedOnly ? ['fallos'] : ['index'])) ?>" class="mb-3">
                <div class="row g-2 align-items-end">
                    <?php if (!$failedOnly): ?>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">Stage</label>
                            <?= Html::dropDownList(
                                'stage',
                                $filters['stage'] ?? '',
                                array_combine($stages, $stages),
                                ['class' => 'form-select form-select-sm', 'prompt' => 'Todos']
                            ) ?>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Persona ID</label>
                        <?= Html::textInput('subject_persona_id', $filters['subject_persona_id'] ?: '', [
                            'class' => 'form-control form-control-sm',
                            'type' => 'number',
                        ]) ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">User ID</label>
                        <?= Html::textInput('created_by_user_id', $filters['created_by_user_id'] ?: '', [
                            'class' => 'form-control form-control-sm',
                            'type' => 'number',
                        ]) ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Parent</label>
                        <?= Html::textInput('parent_type', $filters['parent_type'] ?? '', [
                            'class' => 'form-control form-control-sm',
                            'placeholder' => 'TURNO / GUARDIA…',
                        ]) ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Client capture ID</label>
                        <?= Html::textInput('client_capture_id', $filters['client_capture_id'] ?? '', [
                            'class' => 'form-control form-control-sm',
                        ]) ?>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                    </div>
                </div>
            </form>

            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'columns' => [
                    'id',
                    [
                        'attribute' => 'updated_at',
                        'label' => 'Actualizado',
                        'format' => ['datetime', 'php:d/m/Y H:i'],
                    ],
                    'stage',
                    [
                        'attribute' => 'subject_persona_id',
                        'label' => 'Persona',
                    ],
                    [
                        'attribute' => 'created_by_user_id',
                        'label' => 'User',
                    ],
                    [
                        'label' => 'Parent',
                        'value' => static function (EncounterCapture $model) {
                            if ($model->parent_type === null || $model->parent_type === '') {
                                return '—';
                            }

                            return $model->parent_type . '#' . (int) $model->parent_id;
                        },
                    ],
                    [
                        'attribute' => 'encounter_id',
                        'label' => 'Encounter',
                        'value' => static function (EncounterCapture $model) {
                            return $model->encounter_id ?: '—';
                        },
                    ],
                    [
                        'attribute' => 'attempts_stt',
                        'label' => 'STT',
                    ],
                    [
                        'attribute' => 'attempts_analysis',
                        'label' => 'IA',
                    ],
                    [
                        'attribute' => 'attempts_save',
                        'label' => 'Save',
                    ],
                    [
                        'attribute' => 'last_error',
                        'label' => 'Error',
                        'format' => 'ntext',
                        'value' => static function (EncounterCapture $model) {
                            $err = trim((string) $model->last_error);
                            if ($err === '') {
                                return '';
                            }

                            return \yii\helpers\StringHelper::truncate($err, 60);
                        },
                    ],
                    [
                        'class' => 'yii\grid\ActionColumn',
                        'template' => '{view}',
                        'urlCreator' => static function ($action, EncounterCapture $model) {
                            return Url::to([$action, 'id' => $model->id]);
                        },
                    ],
                ],
            ]); ?>
        </div>
    </div>
</div>
