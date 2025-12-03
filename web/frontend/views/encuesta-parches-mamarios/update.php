<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\EncuestaParchesMamarios */

$this->title = 'Actualizar Encuesta Parches Mamarios: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Encuesta Parches Mamarios', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Actualizar';
?>
<div class="encuesta-parches-mamarios-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
