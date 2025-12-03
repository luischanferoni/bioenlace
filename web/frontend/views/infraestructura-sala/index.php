<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
use common\models\InfraestructuraPiso;
use common\models\Rrhh_efector;
use common\models\Servicio;
use common\models\Persona;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\InfraestructuraSalaBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Salas';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="infraestructura-sala-index">

    <div class="card">
        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-2 mb-2 pe-2">
            <?= Html::a('Crear Sala', ['create'], ['class' => 'btn btn-primary rounded-pill']) ?>

        </div>
    </div>

    <?php // echo $this->render('_search', ['model' => $searchModel]); 
    ?>

    <div class="card">
        <div class="card-body">

            <?php

            $elementos = $dataProvider->getTotalCount();
            if ($elementos > 0) { ?>

            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'filterModel' => $searchModel,
                'headerRowOptions' => ['class' => 'bg-soft-primary'],
                'filterRowOptions' => ['class' => 'bg-light'],
                'columns' => [
                    ['class' => 'yii\grid\SerialColumn'],

                    //'id',
                    'nro_sala',
                    'descripcion',
                    'covid',
                    //'id_responsable',
                    [
                        //'attribute' => 'responsable.rrhh.idPersona.nombreCompleto',
                        'label' => 'Responsable',
                        'value' =>  function($data) {

                           if (is_object($data->responsable)) 
                            { 
                                return $data->responsable->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON); 
                            } else {
                                return 'Sin Responsable';
                            }
                            
                        },
                        'filter' => Html::activeDropDownList(
                            $searchModel,
                            'id_responsable',
                            ArrayHelper::map(Rrhh_efector::obtenerProfesionalesPorEfector(yii::$app->user->getIdEfector()), 'id_rr_hh', 'datos'),
                            [
                                'class' => 'form-control',
                                'prompt' => '- Seleccione -'
                            ]
                        )
                    ],
                    //'id_piso',
                    [
                        'attribute' => 'piso.descripcion',
                        'label' => 'Piso',
                        'filter' => Html::activeDropDownList(
                            $searchModel,
                            'id_piso',
                            ArrayHelper::map(InfraestructuraPiso::find()->all(), 'id_piso', 'descripcion'),
                            [
                                'class' => 'form-control',
                                'prompt' => '- Seleccione -'
                            ]
                        )
                    ],
                    //'id_servicio',
                    [
                        'attribute' => 'servicio.nombre',
                        'label' => 'Servicio',
                        'filter' => Html::activeDropDownList(
                            $searchModel,
                            'id_servicio',
                            ArrayHelper::map(Servicio::find()->all(), 'id_servicio', 'nombre'),
                            [
                                'class' => 'form-control',
                                'prompt' => '- Seleccione -'
                            ]
                        )
                    ],
                    //'tipo_sala',

                    ['class' => 'yii\grid\ActionColumn'],
                ],
            ]); 
         } else {
                echo '<h4 class="text-center"> No existen salas cargadas en este efector.</h4>';
            }?>

        </div>
    </div>
</div>