<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use common\models\Persona;

/* @var $this yii\web\View */
/* @var $model common\models\Guardia */

$this->title = Persona::findOne(["id_persona" => $model->id_persona])->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D);
$this->params['breadcrumbs'][] = ['label' => 'Guardias', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="guardia-view">

<div class="card">
        <div class="card-header bg-soft-info">
            <h3><?= Html::encode($this->title) ?></h3>        
        </div>
        <div class="card-body">

            <p>
                <?php if($model->estado !== 'finalizada') Html::a('Finalizar', ['finalizar', 'id' => $model->id], ['class' => 'btn btn-warning']) ?>
                <?= Html::a('Pacientes en espera', ['index'], ['class' => 'btn btn-success']) ?>
                <?php /*echo Html::a('Delete', ['delete', 'id' => $model->id], [
                    'class' => 'btn btn-danger',
                    'data' => [
                        'confirm' => 'Are you sure you want to delete this item?',
                        'method' => 'post',
                    ],
                ]) */ ?>
            </p>

            <?= DetailView::widget([
                'model' => $model,
                'attributes' => [
                    'id',
                    'estado',
                    'fecha',
                    'hora',
                    'id_rrhh_asignado',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                    'created_by',
                    'updated_by',
                    'deleted_by',
                    'cobertura',
                    'situacion_al_ingresar:ntext',
                    'id_efector_derivacion',
                    'condiciones_derivacion:ntext',
                    'notificar_internacion_id_efector',
                ],
            ]) ?>
        </div>
    </div>
</div>
