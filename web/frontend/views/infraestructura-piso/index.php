<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
use common\models\Efector;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\InfraestructuraPisoBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Pisos';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="infraestructura-piso-index">

    <?php // echo $this->render('_search', ['model' => $searchModel]); 
    ?>

    <div class="card">
            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-2 mb-2 pe-2">
                <?= Html::a('Crear Piso', ['create'], ['class' => 'btn btn-primary rounded-pill']) ?>
            </div>
    </div>

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

                        // 'id',
                        'nro_piso',
                        'descripcion',

                        ['class' => 'yii\grid\ActionColumn'],
                    ],
                ]);
            } else {
                echo '<h4 class="text-center"> No existen pisos cargados en este efector.</h4>';
            }
            ?>
        </div>

    </div>



</div>