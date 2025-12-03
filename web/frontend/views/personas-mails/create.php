<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model app\models\persona_mails */

$this->title = 'Nuevo Mail  para: '.$model_persona->apellido.', '.$model_persona->nombre;
$this->params['breadcrumbs'][] = ['label' => 'Persona Mails', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
//print_r($mje);
?>
<div class="persona-mails-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
         'model_persona' => $model_persona,
    ]) ?>

</div>
