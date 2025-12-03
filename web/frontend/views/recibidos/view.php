<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use common\models\Mensajes;
use \webvimark\modules\UserManagement\models\User;

/* @var $this yii\web\View */
/* @var $model common\models\Recibidos 
$mensajes = Mensajes::findAll(['id_receptor' =>Yii::$app->user->id]);
foreach ($mensajes as $mensajes) {
$mensajes->estado = 'Leído';
$mensajes->save();    
}*/

$this->title = $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Recibidos', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="recibidos-view">

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
                'value'=> User::findOne(["id" => $model->id_receptor])->username,
            ],
            'texto:ntext',
            'fecha',
        ],
    ]) ?>
<p><td>
        <?= Html::a('Volver', ['/recibidos'], ['class' => 'btn btn-success']) ?>
       </td> </p>
</div>
