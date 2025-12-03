<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use yii\helpers\ArrayHelper;
use common\models\Mensajes; 
use common\models\Personas; 

/* @var $this yii\web\View */
/* @var $model common\models\Enviados */

$this->title = $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Enviados', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="enviados-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
       
        <?= Html::a('Borrar', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Realmente desea borrar este registro?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

   <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
             [   
                'attribute'=>'id_receptor',
                'value'=>common\models\Usuarios::findOne(["id" => $model->id_receptor])->username,
            ],
            'texto:ntext',
            'fecha',
                     ],
    ]) ?>
<p><td>
        <?= Html::a('Volver', ['/enviados'], ['class' => 'btn btn-success']) ?>
       </td> </p>
</div>
