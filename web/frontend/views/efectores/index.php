<?php

/**
 * @autor: María de los Ángeles Valdez
 * @versión: 1.2.
 * @creación: 15/10/2015
 * @modificación: 05/11/2015
 **/

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\ArrayHelper; //Agrego esta librería
use common\models\Departamento; //Agrego el modelo Departamento


/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\EfectorBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Listado de Efectores para BIOENLACE';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="efector-index">

      
    <h1><?= Html::encode($this->title) ?></h1>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <p>
        <?= Html::a('Actualizar Tabla', ['subir_archivo'], ['class' => 'btn btn-success']) ?>
    </p>

 <?=    GridView::widget([
        'dataProvider' => $dataProvider,        
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
            //'id_efector',
             // 'codigo_sisa',
              'nombre',  
             
            // 'dependencia',
            // 'tipologia',
            //'domicilio',
            // 'telefono',
            // 'origen_financiamiento',
             // 'id_localidad',
            
            
            [//este arreglo es usado para obtener el nombre de la clave foranea id_localidad
              'attribute' => 'localidadNombre',
              'label' =>'Localidad',
              'value' => 'idLocalidad.nombre',  /*uso la relacion getIdLocalidad (del modelo efectores) que me relaciona 
                                                  la tabla efectores con la de localidad */ 
                
              'filter' => Html::activeTextInput($searchModel, 'localidadNombre', ['class' => 'form-control'])
             /*'filter' => Html::activeDropDownList($searchModel, 'id_localidad', 
                            ArrayHelper::map(\common\models\Localidad::find()->all(),'id_localidad', 'nombre'), 
                            ['class' => 'form-control', 
                            'prompt' => '- Seleccione una -']) */ 
                
               
              
            ],           
            
            [
                'attribute' => 'idLocalidad.departamentoNombre',
                'label' => 'Departamento',               
                'filter' => Html::activeDropDownList($searchModel, 'departamentoId', 
                            ArrayHelper::map(Departamento::find()->all(),'id_departamento', 'nombre'), 
                            ['class' => 'form-control', 
                            'prompt' => '- Seleccione uno -'])
            ],
                       
            
           //  'estado',
           ['class' => 'yii\grid\ActionColumn',
           'template'=>'{view}{update}'], 
        ],
    ]);
    ?>
    
 

</div>
