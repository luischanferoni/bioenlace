<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

$this->title = $model->nombre;

$this->params['breadcrumbs'][] = ['label' => 'Efectores', 'url' => ['indexuserefector']];
$this->params['breadcrumbs'][] = $this->title; 

?>

<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between">
            <div class="d-flex align-items-center">
                <h4 class="px-3"><?= Html::encode($this->title) ?></h4>
            </div> 
            <div class="d-flex align-items-center">
                <?= Html::a('Actualizar', ['update', 'id' => $model->id_efector], ['class' => 'btn btn-sm btn-primary']) ?>
                <?= Html::a('Eliminar', ['delete', 'id' => $model->id_efector], [
                    'class' => 'btn btn-sm btn-danger ms-2',
                    'data' => [
                        'confirm' => 'Esta seguro que desea eliminar este item?',
                        'method' => 'post',
                    ],
                ]) ?>
            </div>
        </div>
    </div>
    
    <div class="card-body">
        
        <?=$this->render("_view_tabs", ['model' => $model, 'tab' => 'view']);?>

        <?= DetailView::widget([
            'model' => $model,
            'attributes' => [
                'id_efector',
                'codigo_sisa',
                'nombre',
                'dependencia',
                'tipologia',
                'domicilio',
                'grupo',
                'formas_acceso',
                'telefono',
                'telefono2',
                'telefono3',
                'mail1',
                'mail2',
                'mail3',
                'dias_horario',
                'origen_financiamiento',
                'id_localidad',
                'estado',
            ],
        ]) ?>
    </div>
</div>
