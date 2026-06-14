<?php

use frontend\assets\InternacionIngresoAsset;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\Person\Persona $persona */
/** @var array<string, mixed> $ctx */

$this->title = 'Ingreso a internación';
$this->params['breadcrumbs'][] = ['label' => 'Internaciones', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

InternacionIngresoAsset::register($this);
?>
<div class="seg-nivel-internacion-ingreso">
    <div class="card mb-3">
        <div class="card-header bg-soft-info">
            <h1 class="h4 mb-0"><?= Html::encode($this->title) ?></h1>
        </div>
    </div>

    <?= $this->render('_ingreso_api', [
        'persona' => $persona,
        'ctx' => $ctx,
    ]) ?>
</div>
