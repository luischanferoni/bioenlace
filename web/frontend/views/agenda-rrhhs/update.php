<?php

use yii\helpers\Html;
use common\models\Persona;

/* @var $this yii\web\View */
/* @var $model common\models\Agenda_rrhh */

$this->title = Persona::findOne(["id_persona" => $model->rrhh->id_persona])->nombreCompleto(Persona::FORMATO_NOMBRE_A_N_D);

$this->params['breadcrumbs'][] = ['label' => 'Agenda Rrhhs', 'url' => ['index']];
//$this->params['breadcrumbs'][] = ['label' => $model->id_agenda_rrhh, 'url' => ['view', 'id' => $model->id_agenda_rrhh]];
$this->params['breadcrumbs'][] = ['label' => $this->title, 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Actualizar';
?>
<div class="agenda-rrhh-update">

    <div class="h3"><?= Html::encode('Actualizar Agenda de '.$this->title) ?></div>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
