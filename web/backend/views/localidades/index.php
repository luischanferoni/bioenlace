<?php
/*
 * Autor: Guillermo Ponce
 * Creado: 16/10/2015
 * Modificado: 
*/

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
//use common\models\Departamento;
use common\models\Provincia;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\LocalidadBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Localidades';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="localidad-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <p>
        <?= Html::a('Crear Nueva Localidad', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
       
            //'id_localidad',
            //'cod_sisa',
            //'cod_bahra',
            'nombre',
            'cod_postal',
            //'id_departamento',
            
            /*[//este arreglo es usado para obtener los valores de la clave foranea id_departamento
                'attribute' => 'id_departamento',
                'value' => function($data) {
                    return Departamento::findOne(
                    ["id_departamento" => $data->id_departamento])->nombre;
                },
                //'filter' => ArrayHelper::map(Departamento::find()->all(),'id_departamento', 'nombre'),
            ],*/
            [
                'attribute' => 'departamentoNombre',
                'label' => 'Departamento',
                'filter' => Html::activeTextInput($searchModel, 'departamentoNombre', ['class' => 'form-control'])
            ],
            
            /*[//este arreglo es usado para obtener los valores de la clave foranea id_provincia
                'attribute' => 'id_provincia',
                'value' => function($data) {
                    return Provincia::findOne(
                    ["id_provincia" => $data->idDepartamento->id_provincia])->nombre;
                },
                //'filter' => ArrayHelper::map(Provincia::find()->all(),'id_provincia', 'nombre'),
            ],*/
            [
                'attribute' => 'idDepartamento.provinciaName',
                'label' => 'Provincia',
                //'filter' => Html::activeTextInput($searchModel, 'provinciaName', ['class' => 'form-control'])
                'filter' => Html::activeDropDownList($searchModel, 'provinciaId', 
                            ArrayHelper::map(Provincia::find()->all(),'id_provincia', 'nombre'), 
                            ['class' => 'form-control', 
                            'prompt' => '- Seleccione -'])
            ],
            
            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?> 

</div>
