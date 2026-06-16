<?php
/*
 * Autor: Guillermo Ponce
 * Creado: 16/10/2015
 * Modificado: 
*/

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model common\models\Localidad */

$this->title = 'Nueva Localidad';
$this->params['breadcrumbs'][] = ['label' => 'Localidades', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="localidad-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
