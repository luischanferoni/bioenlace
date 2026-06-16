<?php
/*
 * Autor: Guillermo Ponce
 * Creado: 16/10/2015
 * Modificado: 
*/

use yii\helpers\Html;
use yii\widgets\DetailView;
use common\models\Provincia;
/* @var $this yii\web\View */
/* @var $model common\models\Localidad */

$this->title = 'Id localidad =' . ' ' . $model-> id_localidad;
$this->params['breadcrumbs'][] = ['label' => 'Localidades', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this-> title;
?>
<div class="localidad-view">

    <!--<h1><?= Html::encode($this->title) ?></h1>-->

    <p>
        <?= Html::a('Actualizar', ['update', 'id' => $model->id_localidad], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Eliminar', ['delete', 'id' => $model->id_localidad], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => '¿Está seguro de elimir este elemento?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([  //aqui muestra el registro 
        'model' => $model,
        'attributes' => [
            //'id_localidad',
            //'cod_sisa',
            //'cod_bahra',
            'nombre',
            'cod_postal',
            //'id_departamento',
            
            [   //muestra el nombre del departamento seleccionado en el listado
                'attribute'=>'id_departamento',
                'value'=>$model->idDepartamento->nombre,
            ],
            [   //muestra el nombre de la provincia seleccionado en el listado
                'attribute'=>'id_provincia',
                'value'=>Provincia::findOne(["id_provincia" => $model->idDepartamento->id_provincia])->nombre,
            ],
        ],
    ]) ?>
    
    <p>
        <?= Html::a('Volver', ['index'], ['class' => 'btn btn-success']) ?>
    </p>

</div>
