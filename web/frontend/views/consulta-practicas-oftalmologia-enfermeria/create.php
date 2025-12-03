<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\ConsultaPracticasOftalmologia */

$this->title = sprintf('Consulta %s - Practica Oftalmologica - Crear', $modelConsulta->id_consulta);
$this->params['breadcrumbs'][] = ['label' => 'Consulta Practicas Oftalmologias', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="consulta-practicas-oftalmologia-create">
    <div class="card">
        <div class="card-header bg-soft-info">
            <h5><?= Html::encode($this->title) ?></h5>
        </div>
        <div class="card-body">
            <dl class="row">
                <?= $this->render('_form', [
                    'modelConsulta' => $modelConsulta,
                    'oftalmologias' => $oftalmologias,
                    'form_steps' => $form_steps,
                ]) ?>
            </dl>
        </div>
    </div>
</div>