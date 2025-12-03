<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\ConsultasRecetaLentes */

$this->title = 'Create Consultas Receta Lentes';
$this->params['breadcrumbs'][] = ['label' => 'Consultas Receta Lentes', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="consultas-receta-lentes-create">
    <div class="card">
        <div class="card-header">
            <div class="header-title">
                <div class="col-lg-8 col-xs-12">
                    <h1>Receta Lentes</h1>
                </div>
            </div>
        </div>
        <div class="card-body">
            <dl class="row">
                <?= $this->render('_form', [
                    'model' => $model,
                ]) ?>
            </dl>
        </div>
    </div>
</div>
