<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\web\JsExpression;

use webvimark\modules\UserManagement\models\User;
use kartik\select2\Select2;

use common\models\Efector;
use common\models\Servicio;
use common\models\Persona;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\RrhhEfectorBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = $searchModel->efector->nombre;

$idEfector = $searchModel->efector->id_efector;
$this->params['breadcrumbs'][] = $this->title;

?>

<div class="card">
    <div class="card-header">
        <h4 class="px-3"><?= Html::encode($this->title) ?></h4>
    </div>

    <div class="card-body">

        <?= $this->render("_view_tabs", ['model' => $searchModel, 'tab' => 'rrhh']); ?>

        <div class="table-responsive">
            <?php 
                $mapServicios = ArrayHelper::map(Servicio::find()->orderBy('nombre')->all(), 'id_servicio', 'nombre');
                //var_dump($searchModel->id_persona);die;
            ?>

            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'filterModel' => $searchModel,
                'columns' => [
                    'id_rr_hh',

                    [
                        'attribute' => 'nombrePersona',
                        'value' => function ($data) {
                            if (isset($data->persona)) {
                                if ($data->persona->id_user == 0) {
                                    return $data->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N);
                                } else {
                                    $url = isset($data->persona->id_user) ? ['user-management/user/view', 'id' => $data->persona->id_user] : ['user-management/user/create'];
                                    return Html::a($data->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N), $url);
                                }
                            } else {
                                return '<span class="badge text-bg-warning">SIN PERSONA</span>';
                            }
                        },
                        'format' => 'raw',
                        /*'filter' => Select2::widget(
                            [
                                'model' => $searchModel,
                                'attribute' => 'id_persona',
                                'data' => isset($searchModel->persona)?[$searchModel->id_persona => $searchModel->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N)]:[],
                                'theme' => Select2::THEME_DEFAULT,
                                'options' => ['placeholder' => '- TODAS -'],
                                'pluginOptions' => [
                                    'width' => '100%',
                                    'minimumInputLength' => 3,
                                    'allowClear' => true,
                                    'ajax' => [
                                        'url' => Url::to(['rrhh-efector/personas-live-search', 'idEfector' => $idEfector]),
                                        'dataType' => 'json',
                                        'data' => new JsExpression('function(params) { return {q:params.term}; }')
                                    ],
                                ],
                            ]
                        ),*/
                    ],
                    [
                        'attribute' => 'idServicio',
                        'label' => 'Servicio',
                        'value' => function ($data) {
                            $servicios = [];
                            foreach ($data->rrhhServicio as $rrhhServicio) {
                                $servicios[] = $rrhhServicio->servicio->nombre;
                            }

                            return count($servicios) == 0 ? 'Sin servicios' : implode(" - ", $servicios);
                        },
                        'filter' => Html::activeDropDownList($searchModel, 'idServicio', $mapServicios, ['class' => 'form-control', 'prompt' => '- TODOS -'])
                    ],
                    [
                        'value' => function ($data) {
                            if (isset($data->persona->id_user) && $data->persona->id_user !== 0) {
                                $servicios = [];
                                foreach ($data->rrhhServicio as $rrhhServicio) {
                                    $servicios[] = $rrhhServicio->servicio->nombre;
                                }

                                $botonAdminEfector = '<li class="list-group-item">' . Html::a(
                                    'Establecer AdminEfector',
                                    ['/rrhh-efector/create-admin-efector', 'id_rr_hh' => $data->id_rr_hh],
                                    [
                                        'class' => 'btn btn-sm btn-warning ajax_adminefector', 
                                        'alert_title' => 'Confirme la asignacion de "'.
                                            $data->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N).
                                            '" como administrador de este efector', 
                                    ]
                                ) . '</li>';

                                if (in_array('ADMINISTRAR EFECTOR', $servicios)) {
                                    $botonAdminEfector = '<li class="list-group-item">' . Html::a(
                                        'Quitar AdminEfector',
                                        ['/rrhh-efector/remove-admin-efector', 'id_rr_hh' => $data->id_rr_hh],
                                        ['class' => 'btn btn-sm btn-warning ajax_adminefector',
                                            'alert_title' => 'Seguro desea quitar a "'.
                                            $data->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N).
                                            '" como administrador de este efector?',                                         
                                        ]
                                    ) . '</li>';
                                }
                                return '<ul class="list-group">
                                        <li class="list-group-item">' . Html::a(
                                    'Ver usuario',
                                    ['user-management/user/view', 'id' => $data->persona->id_user],
                                    ['class' => 'btn btn-sm btn-primary', 'data-pjax' => 0]
                                ) . $botonAdminEfector
                                . '</li>
                                        <li class="list-group-item">' . Html::a(
                                    'Logearse como este usuario',
                                    ['/user/impersonate', 'id' => $data->persona->id_user],
                                    ['class' => 'btn btn-sm btn-success', 'target' => '_blank']
                                ) . '</li>
                                    </ul>';
                            }
                            else{
                                return '<span class="badge text-bg-warning">SIN USUARIO</span>';
                            }
                        },
                        'format' => 'raw',
                        'visible' => User::canRoute('/user-management/user-permission/set'),
                        'options' => [
                            'width' => '10px',
                        ],
                    ],
                ],
            ]); ?>
        </div>
    </div>
</div>

<?php
//var_dump($withOffcanvas);die;
$this->registerJs(    
    "
    $(document).ready(function() {
        $('.ajax_adminefector').click(function(e) {
            e.preventDefault();
            let parent = $(this).parent();
            sweetAlertConfirm($(this).attr('alert_title'))
                .then((result) => {
                    if (result.isConfirmed) {
                        let url = yii.getBaseCurrentUrl() + $(this).attr('href');

                        $.ajax({
                            url: url,
                            type: 'POST',                            
                            success: function (data) {                                
                                alertaFlotante('Listo', 'success');
                                parent.html(data);
                            },
                            error: function () {
                                alertaFlotante('Ocurrió un error', 'danger');
                            }
                        });
                    }
                });
        });        
    });
    "
);