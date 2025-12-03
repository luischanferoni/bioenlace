<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model app\models\persona_mails */

$persona = app\models\Persona::findOne($model->id_persona);

$this->title = 'Actualizar Mails de: ' . ' ' . $persona->apellido.' '.$persona->nombre;
$this->params['breadcrumbs'][] = ['label' => 'Persona Mails', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id_persona_mail, 'url' => ['view', 'id' => $model->id_persona_mail]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="persona-mails-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
