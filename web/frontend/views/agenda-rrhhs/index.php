<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\bootstrap5\Modal;
use yii\helpers\Url;
use yii\widgets\Pjax;
use common\models\Persona;
use common\models\Rrhh;
use yii\helpers\ArrayHelper;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\Agenda_rrhhBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Agenda Laboral';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="agenda-rrhh-index">

    <h1><?= Html::encode($this->title) ?></h1>
<?php // echo $this->render('_search', ['model' => $searchModel]);  ?>

    <p>
<?= Html::a('Nueva Agenda', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?=
    GridView::widget([
        'id' => 'agenda-grid',
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            //['class' => 'yii\grid\SerialColumn'],
//            [
//                'attribute' => 'id_rr_hh',
//                'value' => 'persona.nombre',
//            ],
            [
                'attribute' => 'rrhh',
                'label' => 'Persona',
                'value' => function ($data) {
                    return $data->rrhh ? $data->rrhh->persona->apellido . ', ' . $data->rrhh->persona->nombre : '';
                },
                'filter' => Html::activeTextInput($searchModel, 'rrhh', ['class' => 'form-control'])
            ],
            'fecha_inicio',
            'hora_inicio',
            'fecha_fin',
            'hora_fin',
            //'lunes',
            //'martes',
            //'miercoles',
            //'jueves',
            //'viernes',
            //'sabado',
            //'domingo',
            // 'id_tipo_dia',
            // 'id_efector',
            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]);
    ?>
</div>