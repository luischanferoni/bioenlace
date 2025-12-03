<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\PersonaProgramaDiabetes */

$this->title = $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Persona Programa Diabetes', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="persona-programa-diabetes-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Update', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Delete', ['delete', 'id' => $model->id], [
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
            'id',
            'id_persona_programa',
            'tipo_diabetes',
            'incluir_salud',
            'id_persona_autorizada',
            'parentesco_persona_autorizada',
            'ins_lenta_nph',
            'ins_lenta_lantus',
            'ins_rapida_novorapid',
            'metformina_500',
            'metformina_850',
            'glibenclamida',
            'tiras',
            'monitor',
            'lanceta',
            'id_rrhh_efector',
            'hba1c',
            'glucemia',
        ],
    ]) ?>

</div>
