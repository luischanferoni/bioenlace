<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

$this->title = 'Importar resultados de '.$title;
$this->params['breadcrumbs'][] = ['label' => 'Laboratorio', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <div class="header-title">
            <h4 class="card-title"><?=$this->title;?></h4>
        </div>
    </div>

    <div class="card-body" id="dynamic-servicio-form">
        <div class="row">
            <?php $form = ActiveForm::begin(['options' => ['enctype'=>'multipart/form-data']]); ?>                
                <?= $form->errorSummary($model, ['class' => 'alert alert-danger', 'showAllErrors' => true]);  ?>
                <?php if (!is_null($procesados)) { ?>            
                    <p>
                        Procesados correctamente: <span class="label label-success"><?= $procesados['exitosos']?$procesados['exitosos']:0?></span><br>
                        Ya procesados previamente: <span class="label label-warning"><?= count($procesados['ya_procesados']) > 0 ? count($procesados['ya_procesados']):0?></span><br>
                        Con errores: <span class="label label-danger"><?= count($procesados['errores']) > 0 ? count($procesados['errores']):0?></span>
                    </p>
                    <ul >
                            <?php foreach ($procesados['errores'] as $error) { ?>
                                <li><b>fila: </b><?= $error['row']?> (<?=strtoupper($error['model']::UNIQUE)?>: <?=$error['model']->{$error['model']::UNIQUE}?>) - <b>errores:</b> <mark><?= implode(" | ", $error['model']->getErrorSummary(false))?></mark></li>
                            <?php } ?>
                    </ul>            
                <hr>
                <?php } ?>

                <div class="col-12">
                    <?= $form->field($model, 'archivo')->fileInput() ?>
                </div>
                
                <?= Html::submitButton('Importar Archivo CSV', ['class' => 'btn btn-success']) ?>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>