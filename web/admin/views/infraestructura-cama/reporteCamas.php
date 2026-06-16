<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
use common\models\InfraestructuraSala;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\InfraestructuraCamaBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Reporte de Camas';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="infraestructura-cama-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php if ($type !== 'print') {
                echo Html::a('<span class="glyphicon glyphicon-print" aria-hidden="true"></span> Imprimir', ['infraestructura-cama/reportecamas', 'type' => 'print'], ['class' => 'btn btn-info', 'target' => '_blank']);
          } 
    ?>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            //'id',
            
            //'id_efector',                      
            [
                'attribute'=> 'id_efector',
                'label'=> 'Efector',
                'value'=>function($data){
                    $consulta = \common\models\Efector::findOne(['id_efector'=>$data['id_efector']]);
                    
                    return $consulta->nombre;
                }
            ],
            //'id_servicio',
            [
                'attribute'=> 'id_servicio',
                'label'=> 'Servicio',
                'value'=>function($data){
                    $consulta = \common\models\Servicio::findOne(['id_servicio'=>$data['id_servicio']]);
                    
                    return $consulta? $consulta->nombre: 'No definido' ;
                }
            ],
            'totalcamas',
            'ocupadas',
            'desocupadas',           

           
        ],
    ]); ?>


</div>
<?php if ($type== 'print') { ?>
    <script type="text/javascript">
        document.getElementsByClassName('breadcrumb')[0].style.display = 'none';
        window.print();
    </script>
<?php } ?>