<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
use common\models\InfraestructuraSala;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\InfraestructuraCamaBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Camas';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="infraestructura-cama-index">

    <div class="card">
        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-2 mb-2 pe-2">
            <?= Html::a('Crear Cama', ['create'], ['class' => 'btn btn-primary rounded-pill']) ?>
        </div>
    </div>

    <?php // echo $this->render('_search', ['model' => $searchModel]); 
    ?>

    <div class="card">
        <div class="card-body">

            <?php            
            $elementos = $dataProvider->getTotalCount();            
            if ($elementos > 0) {
                echo GridView::widget([
                    'dataProvider' => $dataProvider,
                    'filterModel' => $searchModel,
                    'headerRowOptions' => ['class' => 'bg-soft-primary'],
                    'filterRowOptions' => ['class' => 'bg-light'],
                    'columns' => [
                        ['class' => 'yii\grid\SerialColumn'],

                        //'id',
                        'nro_cama',
                        'respirador',
                        'monitor',
                        //'id_sala',
                        [
                            'attribute' => 'sala.descripcion',
                            'label' => 'Sala',
                            'filter' => Html::activeDropDownList(
                                $searchModel,
                                'id_sala',
                                ArrayHelper::map(InfraestructuraSala::find()->all(), 'id_sala', 'descripcion'),
                                [
                                    'class' => 'form-control',
                                    'prompt' => '- Seleccione -'
                                ]
                            )
                        ],
                        'estado',

                        ['class' => 'yii\grid\ActionColumn'],
                    ],
                ]);
            } else {
                echo '<h4 class="text-center"> No existen camas cargadas en este efector.</h4>';
            }
            ?>
        </div>

    </div>

</div>