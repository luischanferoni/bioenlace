<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\InfraestructuraSala */

$this->title = 'Registrar Suministro de Medicamento';
$this->params['breadcrumbs'][] = ['label' => 'Suministro de medicamento', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="internacion-suministro-medicamento-create">

    <div class="row">
        <div class="col-sm-2"></div>
        <div class="col-sm">

            <div class="card">
                <div class="card-header bg-soft-info">
                    <h2><?= Html::encode($this->title) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-sm-2"></div>
    </div>

    <?= $this->render('_form', [
        'model' => $model,
        'medicamentosInternacion' => $medicamentosInternacion,
        'error' => $error
    ]) ?>

</div>