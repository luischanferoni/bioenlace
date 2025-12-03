<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\PersonaProgramaDiabetes */

$this->title = 'Create Persona Programa Diabetes';
$this->params['breadcrumbs'][] = ['label' => 'Persona Programa Diabetes', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="persona-programa-diabetes-create">

    <h2>Formulario de Reempadronamiento de Pacientes con Diabetes</h2>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
