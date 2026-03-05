<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\SensibilidadMapeoSnomed */
/* @var $categorias common\models\SensibilidadCategoria[] */

$this->title = 'Nuevo mapeo SNOMED → sensibilidad';
$this->params['breadcrumbs'][] = ['label' => 'Mapeo SNOMED', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="sensibilidad-mapeo-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'categorias' => $categorias,
    ]) ?>

</div>
