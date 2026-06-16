<?php
/*
 * Autor: Guillermo Ponce
 * Creado: 16/10/2015
 * Modificado: 
*/

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\Localidad */

$this->title = 'Actualizar Localidad';//'Actualizar Localidad: ' . ' ' . $model->id_localidad;
$this->params['breadcrumbs'][] = ['label' => 'Localidades', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => 'Id localidad = ' . $model->id_localidad, 
                                    'url' => ['view', 'id' => $model->id_localidad]];
$this->params['breadcrumbs'][] = 'Actualizar';

?>
<div class="localidad-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
