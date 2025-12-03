<?php

use yii\helpers\Html;
use yii\grid\GridView;
use kartik\export\ExportMenu;
use kartik\daterange\DateRangePicker;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Resultados de Laboratorio para Virus Respiratorios';
$this->params['breadcrumbs'][] = $this->title;
?>

<?php
$gridColumns = [
    [
        'label' => 'Caso',
        'attribute' => 'caso',
        'value' => function ($data) {
            return isset($data->caso) ? $data->caso : '';
        }
    ],
    'dni',
    [
        'label' => 'Apellido y Nombre',
        'attribute' => 'apellido',
        'value' => function ($data) {
            return $data->apellido.', '.$data->nombre;
        }
    ],
    [
        'label' => 'Fecha de procesamiento',
        'attribute' => 'fecha_procesamiento',
        'filter' => DateRangePicker::widget([
            'model' => $searchModel,
            'attribute' => 'rango_fechas_procesamiento',
            'convertFormat' => true,
            'pluginOptions' => [
                'locale' => [
                    'format' => 'Y-m-d'
                ],
            ],                
        ]),
    ], 
    [
        'label' => 'Resultados',
        'value' => function ($data) {
            return '<b>'.$data->getAttributeLabel('resultado_genoma_viral_sars_cov_2') .':</b> '. $data->resultado_genoma_viral_sars_cov_2.'<br>'.
            '<b>'.$data->getAttributeLabel('resultado_rt_pcr_virus_influenza_a') .':</b> '. $data->resultado_rt_pcr_virus_influenza_a.'<br>'.
            '<b>'.$data->getAttributeLabel('resultado_rt_pcr_virus_influenza_b') .':</b> '. $data->resultado_rt_pcr_virus_influenza_b.'<br>'.
            '<b>'.$data->getAttributeLabel('resultado_genoma_viral_rsv') .':</b> '. $data->resultado_genoma_viral_rsv;
        },
        'format' => 'raw'
    ]
];

$todasColumns = [
    'caso',
    'apellido', 
    'nombre',        
    'dni',
    'establecimiento_notificador',
    'localidad',
    'edad',
    'situacion_paciente',
    'fecha_procesamiento', 
    'observaciones',
    'resultado_genoma_viral_sars_cov_2',
    'resultado_rt_pcr_virus_influenza_a',
    'resultado_rt_pcr_virus_influenza_b',
    'resultado_genoma_viral_rsv',
    'tipo_muestra',
];    
?>

    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <div class="header-title">
                <h4 class="card-title"><?=$this->title;?></h4>
            </div>
        </div>

        <div class="card-body">
            <div class="row">
                <div class="col-lg-12">
                    <div class="float-right">
                    <?php
                /* echo ExportMenu::widget([
                        'dataProvider' => $dataProvider,
                        'columns' => $todasColumns,
                        'exportConfig' => [
                            ExportMenu::FORMAT_TEXT => false,
                            ExportMenu::FORMAT_HTML => false,
                            ExportMenu::FORMAT_EXCEL => true,
                            ExportMenu::FORMAT_PDF => false,
                            ExportMenu::FORMAT_CSV => false,
                        ],
                        'dropdownOptions' => [
                            'label' => 'Exportar',
                            'class' => 'btn btn-outline-secondary btn-default'
                        ]        
                    ]);    */
                    ?>
                    </div>
                </div>
            </div>
            <div class="row">
                <?= \kartik\grid\GridView::widget([
                    'dataProvider' => $dataProvider,
                    'filterModel' => $searchModel,
                    'columns' => $gridColumns,
                ]); ?>
            </div>
        </div>
    </div>
