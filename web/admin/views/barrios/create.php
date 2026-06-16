<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model common\models\Barrios */

$this->title = 'Nuevo Barrio';
$this->params['breadcrumbs'][] = ['label' => 'Barrios', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="barrios-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'provincia' => $provincia,
        'departamento' => $departamento,
        'localidad' => $localidad
    ]) ?>

</div>
