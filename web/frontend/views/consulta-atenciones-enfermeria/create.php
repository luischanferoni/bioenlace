<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;


/* @var $this yii\web\View */
/* @var $model common\models\Consulta */

$this->title = 'Cargar control';
$this->params['breadcrumbs'][] = ['label' => 'Controles', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="atenciones-enfermeria-create">

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
