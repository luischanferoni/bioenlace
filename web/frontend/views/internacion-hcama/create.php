<?php

use yii\helpers\Html;
use yii\helpers\ArrayHelper;

/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacionHcama */

$this->title = sprintf(
        'Internación %s - Cambio de cama', 
        ArrayHelper::getValue($context, 'id_internacion')
        );
$this->params['breadcrumbs'][] = [
    'label' => 'Internación Historial Camas',
    'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="seg-nivel-internacion-hcama-create">

    <div class="card">
        <div class="card-header bg-soft-info">
            <h1><?= Html::encode($this->title) ?></h1>
        </div>
        <div class="card-body">
            <?= $this->render('_form', [
                'model' => $model,
                'context' => $context
                ]) ?>
        </div>
    </div>
</div>
