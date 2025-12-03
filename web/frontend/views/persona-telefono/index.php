<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $searchModel app\models\busquedas\Persona_telefonoBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Persona Telefonos';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="persona-telefono-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <p>
        <?php // Html::a('Create Persona Telefono', ['create'], ['class' => 'btn btn-success']) ?>
         <div role="alert" class="alert alert-success">
             Para agregar un nuevo Telefono, vaya a la <a href="<?= Url::toRoute('personas')?>"><strong>Sección Personas </strong></a>
      </div>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id_persona_telefono',
            'id_persona',
            'id_tipo_telefono',
            'numero',
            'comentario:ntext',

            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>

</div>
