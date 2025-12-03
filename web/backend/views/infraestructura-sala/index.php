<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
use common\models\InfraestructuraPiso;
use common\models\Rrhh_efector;
use common\models\Servicio;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\InfraestructuraSalaBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Salas';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="infraestructura-sala-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Crear Sala', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            //'id',
            'nro_sala',
            'descripcion',
            'covid',
            //'id_responsable',
            [
                'attribute' => 'responsable.rrhh.idPersona.nombreCompleto',
                'label' => 'Responsable',                
                'filter' => Html::activeDropDownList($searchModel, 'id_responsable', 
                            ArrayHelper::map(Rrhh_efector::obtenerProfesionalesPorEfector(yii::$app->user->getIdEfector()),'id_rr_hh', 'datos'), 
                            ['class' => 'form-control', 
                            'prompt' => '- Seleccione -'])
            ],
            //'id_piso',
            [
                'attribute' => 'piso.descripcion',
                'label' => 'Piso',                
                'filter' => Html::activeDropDownList($searchModel, 'id_piso', 
                            ArrayHelper::map(InfraestructuraPiso::find()->all(),'id_piso', 'descripcion'), 
                            ['class' => 'form-control', 
                            'prompt' => '- Seleccione -'])
            ],
            //'id_servicio',
            [
                'attribute' => 'servicio.nombre',
                'label' => 'Servicio',                
                'filter' => Html::activeDropDownList($searchModel, 'id_servicio', 
                            ArrayHelper::map(Servicio::find()->all(),'id_servicio', 'nombre'), 
                            ['class' => 'form-control', 
                            'prompt' => '- Seleccione -'])
            ],            
            //'tipo_sala',

            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>


</div>
