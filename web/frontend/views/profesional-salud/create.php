<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\ProfesionalSalud */

$this->title = 'Agregar profesiones a '.$persona->apellido.', '.$persona->nombre;
$this->params['breadcrumbs'][] = ['label' => 'Profesional Salud', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="card">
    <div class="card-header">
        <h4 class="px-3"><?= Html::encode($this->title) ?></h4>
    </div>

    <div class="card-body">
        <?= $this->render('_form', [
            'persona_profesiones' => $persona_profesiones,
            'persona_especialidades' => $persona_especialidades,        
        ]) ?>
    </div>

</div>
