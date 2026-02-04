<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\RrhhEfector */
/* @var $model_persona common\models\Persona|null */

$this->title = 'Crear RRHH' . ($model_persona ? ' para: ' . $model_persona->apellido . ', ' . $model_persona->nombre : '');
$this->params['breadcrumbs'][] = ['label' => 'RRHH', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="rrhh-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'model_persona' => $model_persona,
    ]) ?>

</div>
