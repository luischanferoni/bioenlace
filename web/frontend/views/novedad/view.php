<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\Novedad */

$this->title = $model->titulo;
$this->params['breadcrumbs'][] = ['label' => 'Novedades', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="novedad-view">
<div class="card">
        <div class="card-header">
            <div class="header-title d-flex align-items-self justify-content-between">
                <h1><?= Html::encode($this->title) ?></h1>
                </div>
        </div>
    <div class="card-body">

        <p><?= $model->texto ?></p>
    </div>

</div>
