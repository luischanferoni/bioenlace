<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

use common\models\Efector;
use common\models\ServiciosEfector;

$this->title = 'Servicios por Efector';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="card">
    <div class="card-header">
        <h4 class="px-3"><?= Html::encode($this->title) ?></h4>
    </div>

    <div class="card-body">

        <div class="table-responsive">
            <?php \yii\widgets\Pjax::begin(['id' => 'serviciosEfector']); ?>
            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'filterModel' => $searchModel,
                'columns' => [
                    ['class' => 'yii\grid\SerialColumn'],

                    'nombreServicio',            
                    [
                        'attribute' => 'formas_atencion',

                        'contentOptions' => ['class' => 'text-wrap'], // For TD
                        
                        'value' => function($data) {
                            return ServiciosEfector::FORMAS_ATENCION[$data->formas_atencion];
                        }
                    ],
                    [
                        'attribute' => 'pase_previo',
                        'headerOptions' => ['class' => 'text-wrap'], // For TH
                        'value' => function($data) {
                            return $data->pase_previo == 0 ? '--' : $data->pasePrevio->nombre;
                        }
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
                        'template' => '{editar} {delete} {activar_servicio}',
                        'buttons'=> [
                            'editar'  => function ($url, $model) {                               
                                if (is_null($model->deleted_at)) {
                                    return Html::a('Editar', 
                                        ['servicios-efectores/update', 'id_servicio' => $model->id_servicio, 'id_efector' => $model->id_efector],
                                        ['class' => 'btn btn-outline-info me-2']
                                );
                                } else {
                                    return;
                                }
                            },                            
                            
                            'activar_servicio'  => function ($url, $model) {
                                if (!is_null($model->deleted_at)) {
                                    return Html::button('Activar',
                                        [
                                            'class' => 'btn btn-outline-info me-2 ajax-sweet-pjax',
                                            'data-url' => Url::to(['servicios-efectores/reactivar', 'id_servicio' => $model->id_servicio, 'id_efector' => $model->id_efector]),                                            
                                            'data-sweet_title' => 'Reactivar '.$model->nombreServicio.'?',
                                            'data-container' => '#serviciosEfector'
                                        ]
                                    );
                                }
                            },                            
                            'delete'  => function ($url, $model) {
                                if (!is_null($model->deleted_at)) { return;}
                                else {
                                    return Html::button('Eliminar',
                                        [
                                            'class' => 'btn btn-outline-danger ajax-sweet-pjax',
                                            'data-url' => Url::to(['servicios-efectores/delete', 'id_servicio' => $model->id_servicio, 'id_efector' => $model->id_efector]),                                            
                                            'data-sweet_title' => 'Quitar el servicio "'.$model->nombreServicio.'"'.
                                                    ' de "'.Yii::$app->user->getNombreEfector().'"?',
                                            'data-container' => '#serviciosEfector'
                                        ]
                                    );                                   
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

        $('.ajax-activar_servicio, .ajax-delete').click(function(e) {
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
                                    $.pjax.reload({container:'#serviciosEfector'});
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