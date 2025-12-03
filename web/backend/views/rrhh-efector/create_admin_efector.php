<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\RrhhEfector */

$this->title = '"'.$persona->apellido.' '.$persona->nombre.'" como Administrador de Efector';
$this->params['breadcrumbs'][] = ['label' => 'Rrhh -> Efectores', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="rrhh-efector-create">

    <h3><?= Html::encode($this->title) ?></h3>

    <?= $this->render('_form_admin_efector', [
        'persona_efectores' => $persona_efectores,
        'error' => $error,
    ]) ?>

</div>
