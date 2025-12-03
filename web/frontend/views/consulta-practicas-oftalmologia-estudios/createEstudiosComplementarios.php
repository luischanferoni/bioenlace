<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\ConsultaPracticasOftalmologia */

$this->title = 'Create Estudios Oftalmologicos Complementarios';
$this->params['breadcrumbs'][] = ['label' => 'Estudios Oftalmologicos Complementarios', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="consulta-practicas-oftalmologia-create">
    <div class="card">
        <div class="card-body">
            <dl class="row">
            <?= $this->render('_formEstudiosComplementarios', [
                'oftalmologias' => $oftalmologias,
                'arrayC' => $arrayC,
                'modelConsulta' => $modelConsulta,
                'arrayO' => $arrayO,
                'form_steps' => $form_steps,
                'form_childs_min' => $form_childs_min,
            ]) ?>
            </dl>
        </div>
    </div>
</div>
