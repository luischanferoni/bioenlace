<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model common\models\referencia */

$this->title = 'Crear Referencia';
$this->params['breadcrumbs'][] = ['label' => 'Referencias', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="referencia-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
         'model_servicios' => $model_servicios,
        'persona' => $persona,
    ]) ?>

</div>
