<?php

use yii\helpers\Html;
use yii\grid\GridView;
use kartik\export\ExportMenu;
use kartik\daterange\DateRangePicker;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Resultados de Laboratorio para Dengue';
$this->params['breadcrumbs'][] = $this->title;
?>

<?php
$gridColumns = [
    [
        'label' => 'Codigo',
        'attribute' => 'codigo',
        'value' => function ($data) {
            return isset($data->codigo) ? $data->codigo : '';
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
        'label' => 'Fecha de recepción',
        'attribute' => 'fecha_recepcion',
        'filter' => DateRangePicker::widget([
            'model' => $searchModel,
            'attribute' => 'rango_fechas_recepcion',
            'convertFormat' => true,
            'pluginOptions' => [
                'locale' => [
                    'format' => 'Y-m-d'
                ],
            ],                
        ]),
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
        'attribute' => 'resultado_laboratorio',
    ]
];

$todasColumns = [
    'codigo',
    'apellido',
    'nombre',
    'dni',
    'edad',
    'establecimiento_notificador',

    'centro_derivador',
    'localidad',
    'departamento',
    'fecha_recepcion',
    'fecha_procesamiento',
    'fecha_inicio_fiebre',
    'dias_evolucion',
    'ns1_elisa',
    'ns1_test_rapido',
    'ig_m_dengue_elisa',
    'ig_m_test_rapido',
    'igg_test_rapido',
    'rt_pcr_tiempo_real_dengue',
    'serotipo_virus_dengue',
    'rt_pcr_chik',
    'igm_chik',
    'rt_pcr_tiempo_real_chik',
    'rt_pcr_tiempo_real_zika',
    'rt_pcr_tiempo_real_yf',
    'resultado_laboratorio',
    'observaciones',
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
