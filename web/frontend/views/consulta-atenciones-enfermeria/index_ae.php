<?php

use yii\helpers\Html;
use yii\grid\GridView;

$this->title = 'Historial de Controles';
$this->params['breadcrumbs'][] = $this->title;

$nombre_paciente = common\models\Consulta::getPersona(Yii::$app->getRequest()->getQueryParam('id_persona'));
?>
<div class="consulta-index">

<!--    <h1><?php //= Html::encode($this->title) ?></h1>-->
    <h3>Paciente: <span style="font-style: italic"><?= $nombre_paciente?></span></h3>
    

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
//        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            [
                'attribute'=> 'datos',
                'label'=> 'Datos',
               
            ],
        ],
    ]); ?>

</div>
