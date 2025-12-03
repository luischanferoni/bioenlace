<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel app\models\busquedas\Persona_mailsBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Persona Mails';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="persona-mails-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <p>
        <?= Html::a('Create Persona Mails', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id_persona_mail',
            'id_persona',
            'mail',

            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>

</div>
