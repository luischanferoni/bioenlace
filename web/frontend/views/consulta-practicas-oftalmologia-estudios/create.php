<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\ConsultaPracticasOftalmologia */

$this->title = 'Create Consulta Practicas Oftalmologia';
$this->params['breadcrumbs'][] = ['label' => 'Consulta Practicas Oftalmologias', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="consulta-practicas-oftalmologia-create">
    <div class="card">
        <div class="card-header">
            <div class="header-title">
                    <div class="col-lg-8 col-xs-12">
                        <h1>Consulta Oftalmológica</h1>
                    </div>
            </div>
        </div>
        <div class="card-body">
            <dl class="row">
            <?= $this->render('_form', [
                'oftalmologias' => $oftalmologias,
            ]) ?>
            </dl>
        </div>
    </div>
</div>
