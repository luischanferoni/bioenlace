<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\Guardia */

$this->title = 'Guardia: Ingreso de Paciente';
$this->params['breadcrumbs'][] = ['label' => 'Guardias', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="guardia-create">

    <div class="card">
        <div class="card-header bg-soft-info">
            <h3><?= Html::encode($this->title) ?></h3>        
        </div>
        <div class="card-body">
            <?= $this->render('_form', [
                'model' => $model,
                'persona' => $persona,
                'telefono' => $telefono,
                'coberturas'=> $coberturas,
                //'efectores' => $efectores
            ]) ?>
        
        </div>
    </div>    
</div>
