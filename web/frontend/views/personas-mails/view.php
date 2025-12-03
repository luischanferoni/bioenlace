<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model app\models\persona_mails */
$persona = app\models\Persona::findOne($model->id_persona);
$this->title = 'Mail Actualizado  ' . 'de : ' .$persona->apellido.', '.$persona->nombre;
//$this->title = $model->id_persona_mail;
//$this->params['breadcrumbs'][] = ['label' => 'Persona Mails', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => 'Personas', 'url' => ['personas/index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="persona-mails-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Actualizar', ['update', 'id' => $model->id_persona_mail, 'idp' => $model->id_persona], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Borrar', ['delete', 'id' => $model->id_persona_mail], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Are you sure you want to delete this item?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id_persona_mail',
            'id_persona',
            'mail',
        ],
    ]) ?>

</div>
