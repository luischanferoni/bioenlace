<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacionBalancehidrico */

$this->title = sprintf('Consulta %s - Balance Hídrico - Crear', $consulta->id_consulta);
$this->params['breadcrumbs'][] = ['label' => 'Consulta Balance Hídrico', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="consulta-balancehidrico-create">
    
    <div class="card">
        <div class="card-header bg-soft-info">
            <h1><?= Html::encode($this->title) ?></h1>
        </div>
        <div class="card-body">
            <?= $this->render('_form', [
                'balances' => $balances,
                'is_ajax' => $is_ajax,
                'consulta' => $consulta,
                ]) ?>
        </div>
    </div>

</div>
