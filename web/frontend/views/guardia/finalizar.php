<?php

use yii\helpers\Html;
use common\models\Persona;

/* @var $this yii\web\View */
/* @var $model common\models\Guardia */

$this->title = 'Finalizar Guardia: ' . Persona::findOne(["id_persona" => $model->id_persona])->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D);
$this->params['breadcrumbs'][] = ['label' => 'Guardias', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="guardia-update">

<div class="card">
        <div class="card-header bg-soft-info">
            <h3><?= Html::encode($this->title) ?></h3>        
        </div>
        <div class="card-body">
            <?= $this->render('_form_finalizar', [
                'model' => $model,
            ]) ?>
        </div>
    </div>
</div>
