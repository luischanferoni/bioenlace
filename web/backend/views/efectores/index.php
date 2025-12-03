<?php

/**
 * @autor: María de los Ángeles Valdez
 * @versión: 1.2.
 * @creación: 15/10/2015
 * @modificación: 05/11/2015
 **/

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
use common\models\Departamento;


/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\EfectorBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Listado de Efectores';
$this->params['breadcrumbs'][] = $this->title;
?>


<div class="card">
    <div class="card-header">
        <h3><?= Html::encode($this->title) ?></h3>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <?= GridView::widget([
                'dataProvider' => $dataProvider,        
                'filterModel' => $searchModel,
                'tableOptions' => ['class' => 'table'],
                
                'columns' => [
                    ['class' => 'yii\grid\SerialColumn'],
                    [
                        'attribute' => 'nombre',
                        'value' => function ($data) {
                            return Html::a($data->nombre, ['efectores/view', 'id' => $data->id_efector]);
                        },
                        'format' => 'raw',
                        'contentOptions' => ['class' => 'text-wrap'],
                    ],
                    [//este arreglo es usado para obtener el nombre de la clave foranea id_localidad
                        'attribute' => 'localidad.nombre',
                        'label' =>'Localidad',
                        'value' => 'localidad.nombre',  /*uso la relacion getLocalidad (del modelo efectores) que me relaciona 
                                                            la tabla efectores con la de localidad */ 
                            
                        'filter' => Html::activeTextInput($searchModel, 'localidadNombre', ['class' => 'form-control'])
                        /*'filter' => Html::activeDropDownList($searchModel, 'id_localidad', 
                                        ArrayHelper::map(\common\models\Localidad::find()->all(),'id_localidad', 'nombre'), 
                                        ['class' => 'form-control', 
                                        'prompt' => '- Seleccione una -']) */ 
                    ],
                    [
                        'attribute' => 'localidad.departamento.nombre',
                        'label' => 'Departamento',               
                        'filter' => Html::activeDropDownList($searchModel, 'departamentoId', 
                                    ArrayHelper::map(Departamento::find()->all(),'id_departamento', 'nombre'), 
                                    ['class' => 'form-control', 
                                    'prompt' => '- Seleccione uno -'])
                    ],
                [
                    'class' => 'yii\grid\ActionColumn',
                    'template'=>'{update}'
                ], 
                ],
            ]);
            ?>
        </div>
    </div>
</div>
