<?php

use yii\helpers\Html;
use yii\grid\GridView;

use common\models\busquedas\LaboratorioBusqueda;

$this->title = 'Resultados de laboratorio';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="d-flex justify-content-between align-items-center flex-wrap mb-5 gap-3">

    <h2><?= Html::encode($this->title) ?></h2>

    <?php 
        $view_search = isset($accesolibre) ? '_search_publico' : '_search';
        echo $this->render($view_search, ['model' => $searchModel, 'accesolibre' => isset($accesolibre) ? true : false]);
    ?>

    <div class="col-12">
        <?php
        echo $dataProvider->getTotalCount() >= 0 ? GridView::widget([
            'id' => 'laboratorio-grid',
            'options' => ['class' => 'table-responsive'],
            'tableOptions' => ['class' => 'table table-hover'],
            'headerRowOptions' => [
                'class' => 'table-success ',
            ],            
            'summary'=> "",
            'dataProvider' => $dataProvider,
            'columns' => [
                [
                    'class' => 'yii\grid\ActionColumn', 
                    'template' => '{descargar}',
                    'buttons'=> [
                        'descargar' => function ($url, $model) {
                            return Html::a('<i class="bi bi-download"></i> Informe',
                                        ['laboratorio/descargar'], 
                                        [
                                            'data' => [
                                                'method' => 'post',
                                                'params' => [
                                                    'id' => $model->id,
                                                    'tipo' => get_class($model) == 'common\models\file\DengueImport'?LaboratorioBusqueda::TIPOS_ESTUDIOS_DENGUE:LaboratorioBusqueda::TIPOS_ESTUDIOS_VIRUS_RESPIRATORIO
                                                ],
                                            ],
                                            'target' => '_blank',
                                        ]
                                    );
                        },
                    ]                
                ],
                [
                    'attribute' => 'fecha_procesamiento',
                    'label' => 'F. proceso',
                    'value' => function($data) {
                         return Yii::$app->formatter->asDate($data->fecha_procesamiento);
                    }
                 ],                            
                'dni',
                'apellido',
                'nombre',
            ],
        ]) : null;
        ?>
    </div>
</div>