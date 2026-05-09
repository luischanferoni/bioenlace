<?php

use common\models\Persona;
use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\PersonaPrograma */

$this->title = $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Persona Programas', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="persona-programa-view">

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
            'id_persona',
            'id_programa',
            'clave_beneficiario',
            'activo',
            'fecha',
            'fecha_baja',
            'motivo_baja',
            'tipo_empadronamiento',
            [
                'attribute' => 'id_profesional_efector_servicio',
                'label' => 'Profesional',
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
