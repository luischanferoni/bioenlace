<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\PersonaPrograma */

$this->title = 'Empadronamiento';
$this->params['breadcrumbs'][] = ['label' => 'Persona Programas', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="persona-programa-create">

    <h2>Formulario de Reempadronamiento de Pacientes con Diabetes</h2>

    <?= $this->render('_form', [
        'model' => $model,
        'personaEmpadronada' => $personaEmpadronada
    ]) ?>

</div>
