<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\ConsultaRegimen */

$this->title = sprintf('Consulta %s - Regimen - Crear', $consulta->id_consulta);
$this->params['breadcrumbs'][] = ['label' => 'Consulta Regimen', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="consulta-regimen-create">
    
    <div class="card">
        <div class="card-header bg-soft-info">
            <h1><?= Html::encode($this->title) ?></h1>
        </div>
        <div class="card-body">
            <?= $this->render('_form', [
                'regimenes' => $regimenes,
                'is_ajax' => $is_ajax,
                'consulta' => $consulta,
                ]) ?>
        </div>
    </div>

</div>
