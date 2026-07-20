<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array<string, mixed> $model */
/** @var array<int|string, string> $provincias */

$this->title = 'Editar protocolo: ' . (string) ($model['protocol_key'] ?? '');
$this->params['breadcrumbs'][] = ['label' => 'Protocolos de cuidado', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Editar';
?>
<div class="care-protocol-update">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= Html::encode($this->title) ?></h2>
            </div>
            <div class="card-body">
                <?php if (Yii::$app->session->hasFlash('error')): ?>
                    <div class="alert alert-danger"><?= Html::encode((string) Yii::$app->session->getFlash('error')) ?></div>
                <?php endif; ?>
                <?= $this->render('_form', [
                    'model' => $model,
                    'provincias' => $provincias,
                    'submitLabel' => 'Guardar',
                    'lockKey' => true,
                ]) ?>
            </div>
        </div>
    </div>
</div>
