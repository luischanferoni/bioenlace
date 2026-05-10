<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\web\JsExpression;

use webvimark\modules\UserManagement\models\User;
use kartik\select2\Select2;

use common\models\ProfesionalEfectorServicio;
use common\models\Servicio;
use common\models\Persona;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\ProfesionalEfectorServicioBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$efCtx = $searchModel->efector;
$this->title = $efCtx !== null ? $efCtx->nombre : ('Efector #' . (int) ($searchModel->id_efector ?? 0));

$idEfector = (int) ($searchModel->id_efector ?? ($efCtx !== null ? $efCtx->id_efector : 0));
$this->params['breadcrumbs'][] = $this->title;

?>

<div class="card">
    <div class="card-header">
        <h4 class="px-3"><?= Html::encode($this->title) ?></h4>
    </div>

    <div class="card-body">

        <?= $this->render("_view_tabs", ['model' => $searchModel, 'tab' => 'profesionales']); ?>

        <div class="table-responsive">
            <?php
                $mapServicios = ArrayHelper::map(Servicio::find()->orderBy('nombre')->all(), 'id_servicio', 'nombre');
            ?>

            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'filterModel' => $searchModel,
                'columns' => [
                    [
                        'attribute' => 'id',
                        'label' => 'PES (id)',
                    ],

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
                    ],
                    [
                        'attribute' => 'idServicio',
                        'label' => 'Servicio',
                        'value' => static function ($data) {
                            return $data->servicio !== null ? $data->servicio->nombre : 'Sin servicios';
                        },
                        'filter' => Html::activeDropDownList($searchModel, 'idServicio', $mapServicios, ['class' => 'form-control', 'prompt' => '- TODOS -'])
                    ],
                    [
                        'value' => function ($data) {
                            if (isset($data->persona->id_user) && $data->persona->id_user !== 0) {
                                $idPes = (int) $data->id;
                                $nombreServicio = $data->servicio !== null ? $data->servicio->nombre : '';

                                $botonAdminEfector = '<li class="list-group-item">' . Html::a(
                                    'Establecer AdminEfector',
                                    ['/profesional-efector-servicio/create-admin-efector', 'id_pes' => $idPes],
                                    [
                                        'class' => 'btn btn-sm btn-warning ajax_adminefector',
                                        'alert_title' => 'Confirme la asignacion de "'.
                                            $data->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N).
                                            '" como administrador de este efector',
                                    ]
                                ) . '</li>';

                                if ($nombreServicio === 'ADMINISTRAR EFECTOR') {
                                    $botonAdminEfector = '<li class="list-group-item">' . Html::a(
                                        'Quitar AdminEfector',
                                        ['/profesional-efector-servicio/remove-admin-efector', 'id_pes' => $idPes],
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
