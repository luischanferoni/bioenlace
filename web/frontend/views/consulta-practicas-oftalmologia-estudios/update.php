<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\ConsultaPracticasOftalmologia */

$this->title = 'Update Consulta Practicas Oftalmologia: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Consulta Practicas Oftalmologias', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="consulta-practicas-oftalmologia-update">
    <div class="card">
        <div class="card-header">
            <div class="header-title">
                <div class="col-lg-8 col-xs-12">
                    <h1>Actualizar Consulta Oftalmológica</h1>
                </div>
            </div>
        </div>
        <div class="card-body">
            <dl class="row">
                <?= $this->render('_form', [
                    'model' => $model,
                ]) ?>
            </dl>
        </div>
    </div>
</div>
