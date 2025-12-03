<?php

use yii\helpers\Html;
use yii\grid\GridView;
use webvimark\modules\UserManagement\models\User;
use common\models\Persona;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\ReferenciaBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Referencias';
$this->params['breadcrumbs'][] = $this->title;
$esAdministrativo = User::hasRole(['Administrativo'], $superAdminAllowed = true);
$esMedico = User::hasRole(['Medico'], $superAdminAllowed = true);
$id_efector = Yii::$app->user->idEfector;
?>
<style>
    div .alert {
        position: relative !important;
    }
</style>
<div class="referencia-index">
    <div class="card">
        <div class="card-body">
            <div class="custom-table-effect">
                <h1><?= Html::encode($this->title) ?></h1>

                <?php #echo $this->render('_search', ['model' => $searchModel]); 
                ?>


                <?= GridView::widget([
                    'dataProvider' => $dataProvider,
                    'filterModel' => $searchModel,
                    'columns' => [
                        ['class' => 'yii\grid\SerialColumn'],

                        [
                            #'attribute'=>'id_consulta_solicitante.consulta.paciente',
                            'label' => 'Paciente',
                            'value' => function ($data) {
                                return $data->id_consulta_solicitante ? $data->consulta->paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON) : 'No definida';
                            }
                        ],

                        [
                            #'attribute'=>'if_efector',
                            'label' => 'Efector que lo Solicita',
                            'contentOptions' => [
                                'class' => 'text-wrap'
                            ],
                            'value' => function ($data) {
                                return common\models\Consulta::getEfectorByIdConsulta($data->id_consulta_solicitante);
                            }
                        ],

                        [
                            'attribute' => 'indicaciones',
                            'contentOptions' => [
                                'class' => 'text-wrap'
                            ],
                            'filter' => false,
                            'label' => 'Indicaciones',
                        ],

                        [
                            'attribute' => 'id_servicio',
                            'filter' => false,
                            'label' => 'Servicio',
                            'value' => function ($data) {
                                $servicio = common\models\Servicio::findOne(['id_servicio' => $data->id_servicio]);
                                return $servicio->nombre;
                            }
                        ],
                        [
                            'format' => 'raw',
                            'value' => function ($data) {
                                $consulta = \common\models\Consulta::findOne(['id_consulta' => $data->id_consulta_solicitante]);

                                if ($consulta->parent_class == '\common\models\SegNivelInternacion'):
                                    $url    = ['internacion/' . $consulta->parent_id];
                                    $button = 'Internacion';
                                    $class  = 'btn btn-outline-success me-2';
                                else:
                                    $url    = ['turnos/index', 'id' => $data->consulta->paciente->id_persona, 'id_servicio' => $data->id_servicio];
                                    $button = 'Asignar Turno';
                                    $class  = 'btn btn-outline-info me-2';
                                endif;

                                if (Yii::$app->user->getServicioActual() != 62):
                                    return Html::a(
                                        $button,
                                        $url,
                                        ['class' => $class]
                                    );
                                else:
                                    return false;
                                endif;
                            }
                        ],
                    ],
                ]); ?>
            </div>
        </div>
    </div>
</div>