<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
//use yii\widgets\Pjax;
use yii\helpers\Url;

use common\models\Persona;
use common\models\Servicio;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\RrhhEfectorBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Recursos Humanos de su Efector';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="card">
    <div class="card-header">
        <div class="row">
            <div class="col-10">
                <h4 class="px-3"><?= Html::encode($this->title) ?></h4>
            </div>
            <div class="col-2 text-end">
                <?= Html::a('Nuevo RRHH', 
                    ['personas/index'],
                    ['class' => 'btn btn-info text-white',]
                );
                ?>
            </div>
        </div>
    </div>

    <div class="card-body">

        <div class="table-responsive">
            <?php 
                $mapServicios = ArrayHelper::map(Servicio::find()->orderBy('nombre')->all(), 'id_servicio', 'nombre');
            ?>

            <?php \yii\widgets\Pjax::begin(['id' => 'rrhh']); ?>

            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'filterModel' => $searchModel,
                'columns' => [
                    [
                        'attribute' => 'nombrePersona',
                        'value' => function($data) {
                            if (isset($data->persona)) {
                                //$url =  ['rrhh-efector/view', 'id_rr_hh' => $data->id_rr_hh];
                                //return Html::a($data->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N), $url);
                                return $data->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N);
                            } else {
                                return  'Id RRHH: '.$data->id_rr_hh.' Id Persona: '.$data->id_persona. ' <span class="badge text-bg-warning">SIN PERSONA</span>';
                            }
                        },
                        'format' => 'raw',
                    ],

                    [
                        'attribute' => 'nombreEfector',
                        'value' => function($data) {
                            return isset($data->efector) ? $data->efector->nombre : '--';
                        },
                        'visible' => Yii::$app->user->getIdEfector() ? false : true,
                    ],

                    [
                        'attribute' => 'idServicio',
                        'label' => 'Servicio',
                        'value' => function($data) {
                            $servicios = [];
                            foreach($data->rrhhServicio as $rrhh_servicio) {
                                $servicios[] = $rrhh_servicio->servicio->nombre;
                            }
                            
                            return implode(", ", $servicios);                            
                        },
                        'filter' => Html::activeDropDownList($searchModel, 'idServicio', $mapServicios, ['class' => 'form-control', 'prompt' => '- TODOS -']),
                        'format' => 'raw',
                    ],

                    [
                        'attribute' => 'deleted_at',
                        'label' => 'Activos',
                        'value' => function($data) {
                            return is_null($data->deleted_at) || $data->deleted_at == 'null' ? 'activo' : 'eliminado';                            
                        },
                        'filter' => Html::activeDropDownList($searchModel, 'deleted_at',
                                                        ['null' => 'Activos', 'not_null' => 'Eliminados'], 
                                                        ['class' => 'form-control']),
                    ],

                    [
                        'class' => 'yii\grid\ActionColumn',
                        'template' => '{editar_usuario} {editar_agenda} {delete} {activar_rrhh}',
                        'buttons'=> [
                            'editar_usuario'  => function ($url, $model) {
                                if (!is_null($model->deleted_at)) { return;}
                                if (isset($model->persona->id_user) && $model->persona->id_user !== 0) {
                                    return Html::a('Editar usuario', 
                                        ['user/update', 'id' => $model->persona->id_user],
                                        ['class' => 'btn btn-outline-info me-2']
                                );
                                } else {
                                    return '<span class="badge text-bg-warning">SIN USUARIO</span>';
                                }
                            },                            
                            'editar_agenda'  => function ($url, $model) {
                                if (!is_null($model->deleted_at)) { return;}
                                if (isset($model->persona->id_user) && $model->persona->id_user !== 0) {
                                    return Html::a('Editar agenda / Servicios', 
                                        ['rrhh-efector/create', 'id_persona' => $model->persona->id_persona],
                                        ['class' => 'btn btn-outline-warning me-2',]
                                    );
                                }
                            },
                            'activar_rrhh'  => function ($url, $model) {
                                if (!is_null($model->deleted_at)) {
                                    return Html::button('Activar RRHH',
                                        [
                                            'class' => 'btn btn-outline-info me-2 ajax-sweet-pjax',
                                            'data-url' => Url::to(['rrhh-efector/reactivar', 'id_rr_hh' =>$model->id_rr_hh]),                                            
                                            'data-sweet_title' => 'Reactivar a '.$model->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N).'?',
                                            'data-container' => '#rrhh'
                                        ]
                                    );
                                }
                            },                            
                            'delete'  => function ($url, $model) {
                                if (!is_null($model->deleted_at)) { return;}
                                if (isset($model->persona->id_user) && $model->persona->id_user !== 0) {
                                    return Html::button('Eliminar',
                                        [
                                            'class' => 'btn btn-outline-danger ajax-sweet-pjax',
                                            'data-url' => Url::to(['rrhh-efector/delete', 'id_rr_hh' => $model->id_rr_hh]),                                            
                                            'data-sweet_title' => 'Quitar a "'.$model->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N).'"'.
                                                    ' como RRHH de "'.Yii::$app->user->getNombreEfector().'"?',
                                            'data-container' => '#rrhh'
                                        ]
                                    );
                                    /*                                    
                                    return Html::a('Eliminar', 
                                    ['delete', 'id_rr_hh' => $model->id_rr_hh], 
                                    [
                                        'class' => 'btn btn-outline-danger ajax-delete',
                                        'data' => [
                                        'data-confirm' => 'Quitar a "'.$model->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N).'"'.
                                                     ' como RRHH de "'.Yii::$app->user->getNombreEfector().'"?',
                                         'method' => 'post',
                                        // 'data-pjax' => 1,
                                            //'data-method' => 'post',
                                            //'pjax-container' => 'pjax-rrhh-delete',
                                        ],
                                    ]);*/
                                }
                            },
                        ]
                    ],
                ],
            ]); ?>

            <?php \yii\widgets\Pjax::end(); ?>

        </div>

    </div>

</div>

<?php

$this->registerJs(

   "$('document').ready(function(){ 

        $('.ajax-activar_rrhh, .ajax-delete').click(function(e) {
            e.preventDefault();
            
            sweetAlertConfirm($(this).attr('alert_title'))
                .then((result) => {
                    if (result.isConfirmed) {
                        let url = yii.getBaseCurrentUrl() + $(this).data('url');

                        $.ajax({
                            url: url,
                            type: 'POST',                            
                            success: function (data) {                                
                                if(typeof(data.success) !== 'undefined' || data.error == true) {
                                    alertaFlotante(data.msg, 'danger');
                                } else {
                                    alertaFlotante(data.msg, 'success');
                                    $.pjax.reload({container:'#rrhh'});
                                }
                            },
                            error: function () {
                                alertaFlotante('Ocurrió un error', 'danger');
                            }
                        });
                    }
                });
            });           
    });"
);
?>