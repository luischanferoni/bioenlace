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
            <?= $this->render('_formMedico', [
                'oftalmologias' => $oftalmologias,
                'arrayC' => $arrayC, 
                'modelConsulta' => $modelConsulta, 
                'arrayO' => $arrayO,
                'form_steps' => $form_steps,
                'form_childs_min' => $form_childs_min,
            ]) ?>
        </div>
    </div>
</div>
