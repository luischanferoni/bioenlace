<?php

use yii\helpers\Html;
use yii\grid\GridView;
use common\models\User;
use common\models\Clinical\Encounter;
use common\models\Person\Persona;
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
                                if (!$data->id_consulta_solicitante) {
                                    return 'No definida';
                                }
                                $subject = $data->encounter->subject ?? null;

                                return $subject
                                    ? $subject->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON)
                                    : 'No definida';
                            }
                        ],

                        [
                            #'attribute'=>'if_efector',
                            'label' => 'Efector que lo Solicita',
                            'contentOptions' => [
                                'class' => 'text-wrap'
                            ],
                            'value' => function ($data) {
                                return Encounter::getEfectorNombreById((int) $data->id_consulta_solicitante);
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
                                $encounterId = (int) $data->id_consulta_solicitante;
                                $encounter = \common\models\Clinical\Encounter::findOne($encounterId);
                                if ($encounter === null) {
                                    return false;
                                }

                                if ($encounter->parent_type === \common\models\Clinical\Encounter::PARENT_INTERNACION) {
                                    $url = ['internacion/' . $encounter->parent_id];
                                    $button = 'Internacion';
                                    $class = 'btn btn-outline-success me-2';
                                } else {
                                    $paciente = $data->encounter ? $data->encounter->subject : null;
                                    $url = ['turnos/index', 'id' => $paciente ? $paciente->id_persona : 0, 'id_servicio' => $data->id_servicio];
                                    $button = 'Asignar Turno';
                                    $class = 'btn btn-outline-info me-2';
                                }

                                if (Yii::$app->user->getServicioActual() != 62) {
                                    return Html::a($button, $url, ['class' => $class]);
                                }

                                return false;
                            }
                        ],
                    ],
                ]); ?>
            </div>
        </div>
    </div>
</div>