<?php

/* @var $this yii\web\View */

use yii\helpers\Html;
use yii\helpers\Url;
use common\models\Efector;
use yii\grid\GridView;
use yii\data\ActiveDataProvider;

$this->title = 'Centros de Salud';
$this->params['breadcrumbs'][] = ['label' => 'Guía de Servicios y Centros de Salud', 'url' => ['guia-servicios']];
$this->params['breadcrumbs'][] = $this->title;
?>
<style type="text/css">
    .grupo8,.grupo1 {
        background-color:  #B23437; 
        border-color: #B23437 ;
    }
    .grupo8:hover,.grupo1:hover {
      background-color: #EC3237;
        border-color: #EC3237;
    }
    .grupo2 {
        background-color: #067A3B;
        border-color: #067A3B;
    }
    .grupo2:hover {
      background-color: #00A85A;
        border-color: #00A85A;
    }
    .grupo9,.grupo3 {
        background-color: #075180;
        border-color: #075180;
    }
    .grupo3:hover, .grupo9:hover {
        background-color: #0060AA;
        border-color: #0060AA;
    }
    .grupo4 {
        background-color: #EDBE00; 
        border-color: #EDBE00;
    }
    .grupo4:hover {
        background-color: #FFEA6F; 
        border-color: #FFEA6F;
    }
    .grupo5 {
        background-color:  #881F5A;  
        border-color:  #881F5A;
    }
    .grupo5:hover {
        background-color: #A9518B;
        border-color: #A9518B; 
    }
    .grupo6 {
        background-color: #C65338;
        border-color: #C65338;
    }
    .grupo6:hover {
        background-color: #F58634;
        border-color: #F58634;
    }
    .grupo7 {
        background-color:  #35AEE5;
        border-color:  #35AEE5;
    }  
    .grupo7:hover {
        background-color: #91D8F6;
        border-color: #91D8F6;
    }   
    
</style>
<div class="site-about">
    <h1><?= Html::encode($this->title) ?></h1>
<?php

$dataProvider = new ActiveDataProvider([
    'query' => Efector::find()->select('efectores.*')
                  ->from('ServiciosEfector')
                  ->leftJoin('efectores', '`efectores`.`id_efector` = `ServiciosEfector`.`id_efector`')
                  ->where(['efectores.id_localidad' => $id])                  
                  ->andWhere(['<>','efectores.grupo',0])
                  ->groupBy('efectores.id_efector')
                  ->orderBy('efectores.grupo'),
    'pagination' => [
        'pageSize' => 16,
    ],
]);

echo GridView::widget([
    'dataProvider' => $dataProvider,
    'columns' => [

            ['class' => 'yii\grid\SerialColumn'],            
              'nombre',  
              'domicilio',
              'telefono',

            ['attribute' => 'grupo',
              'format'=>'raw',
              'value' => function ($data) {
               
              return Html::button($data->grupo,
                ['value'=> '#',
                  'class'=> 'btn btn-info grupo'.$data->grupo,
                  'title' => 'Grupo '.$data->grupo]);
                   
                }            
            ],
              
            ['class' => 'yii\grid\ActionColumn',
              'template'=>'{view}',
              'buttons' => [
              'view' => function ($url, $model) {
                       
                    return Html::a('<span class="glyphicon glyphicon-eye-open"></span> Ver Centro', ['site/ver-centro-salud', 'id' => $model->id_efector], [ 'title' =>'Ver Centro de Salud', 'class' => 'btn btn-info grupo'.$model->grupo,
                  ]);           
                },            
              ], 
            ],
      ],
]);

?>

   
</div>