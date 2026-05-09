<?php

use common\models\Persona;
use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\DispensaProgramaDiabetes */

$this->title = $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Dispensa Programa Diabetes', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="dispensa-programa-diabetes-view">

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
            'id_persona_programa_diabetes',
            'id_persona_retira',
            'fecha_retiro',
            'ins_lenta_nph',
            'ins_lenta_lantus',
            'ins_rapida_novorapid',
            'metformina_500',
            'metformina_850',
            'glibenclamida',
            'tiras',
            'monitor',
            'lanceta',
            [
                'attribute' => 'id_profesional_efector_servicio',
                'label' => 'Profesional que entrega',
                'value' => static function ($model) {
                    $pes = $model->profesionalEfectorServicio;

                    return $pes && $pes->persona
                        ? $pes->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON)
                        : ($model->id_profesional_efector_servicio !== null ? (string) $model->id_profesional_efector_servicio : '');
                },
            ],
        ],
    ]) ?>

</div>
