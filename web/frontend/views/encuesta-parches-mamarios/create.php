<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\EncuestaParchesMamarios */

$this->title = 'Encuesta Parches Mamarios';
$this->params['breadcrumbs'][] = ['label' => 'Personas', 'url' => ['personas/buscar-persona']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="encuesta-parches-mamarios-create">

    <div class="card">
        <div class="card-header bg-soft-info">

            <h1><?= Html::encode($this->title) ?></h1>
        </div>
    </div>

    <?= $this->render('_form', [
        'model' => $model,
        'modelAtencionEnfermeria' => $modelAtencionEnfermeria 
    ]) ?>



</div>