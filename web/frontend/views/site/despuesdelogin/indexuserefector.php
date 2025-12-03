<?php

use yii\helpers\Html;
use yii\grid\GridView;

?>
<div class="card">
    <div class="card-body">
        <div class="card-header">
            <h3>Seleccione el Efector en el cual desea trabajar para poder continuar</h3>
        </div>

        <div class="custom-table-effect">

            <?= GridView::widget([
                'dataProvider' => $dataProviderEfectores,
                'id' => 'grid_efectores',
                'columns' => [
                    [
                        'attribute'=> 'nombre',
                        'label'=> 'Efector',
                        'format'=> 'raw',
                        'value' => function ($model, $key, $index) {
                            
                            return Html::radio('nombre_efector', false, 
                                        ['label' => $model->nombre, 'value' => $model->id_efector]);
                        },

                    ],
            
                ],
            ]); ?>

        </div>
    </div>
</div>