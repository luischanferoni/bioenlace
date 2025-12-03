<?php

use yii\helpers\Html;
use kartik\date\DatePicker;
use kartik\time\TimePicker;

?>


<div class="row">

    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card">
            <div class="card-header bg-soft-info">
                <h5>Otras Atenciones/Controles</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 col-sm-12 col-xs-12">
                        <div class="form-check">
                            <?= Html::checkbox('nebulizacion', isset($datos['nebulizacion']) ? true : false, 
                                        ['id' => 'nebulizacion', 'class' => 'form-check-input']) ?>
                            <label class="form-check-label text-dark" for="nebulizacion">
                                Nebulización
                            </label><br>
                            <?= Html::checkbox('rescate_sbo', isset($datos['rescate_sbo']) ? true : false, 
                                        ['id' => 'rescate_sbo', 'class' => 'form-check-input']) ?>
                            <label class="form-check-label text-dark" for="rescate_sbo">
                                Rescate y SBO
                            </label><br>
                            <?= Html::checkbox('inyectable', isset($datos['inyectable']) ? true : false, 
                                        ['id' => 'inyectable', 'class' => 'form-check-input']) ?>
                            <label class="form-check-label text-dark" for="inyectable">
                                Inyectable
                            </label><br>
                            <?= Html::checkbox('campaña', isset($datos['campaña']) ? true : false, 
                                        ['id' => 'campaña', 'class' => 'form-check-input']) ?>
                            <label class="form-check-label text-dark" for="campaña">
                                <span class="label label-danger">Campaña</span>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12 col-xs-12">
                        <div class="form-check">
                            <?= Html::checkbox('inmunizacion', isset($datos['inmunizacion']) ? true : false, 
                                    ['id' => 'inmunizacion', 'class' => 'form-check-input']) ?>
                            <label class="form-check-label text-dark" for="inmunizacion">
                                Inmunización
                            </label><br>
                            <?= Html::checkbox('extraccion_puntos', isset($datos['extraccion_puntos']) ? true : false, 
                                    ['id' => 'extraccion_puntos', 'class' => 'form-check-input']) ?>
                            <label class="form-check-label text-dark" for="extraccion_puntos">
                                Extracción de puntos
                            </label><br>
                            <?= Html::checkbox('curacion', isset($datos['curacion']) ? true : false, 
                                    ['id' => 'curacion', 'class' => 'form-check-input']) ?>
                            <label class="form-check-label text-dark" for="curacion">
                                Curación
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12 col-xs-12">
                        <div class="form-check">
                            <?= Html::checkbox('internacion_abreviada', isset($datos['internacion_abreviada']) ? true : false, 
                                ['id' => 'internacion_abreviada', 'class' => 'form-check-input']) ?>
                            <label class="form-check-label text-dark" for="internacion_abreviada">
                                Internación abreviada
                            </label><br>
                            <?= Html::checkbox('visita_domiciliaria', isset($datos['visita_domiciliaria']) ? true : false, 
                                ['id' => 'visita_domiciliaria', 'class' => 'form-check-input']) ?>
                            <label class="form-check-label text-dark" for="visita_domiciliaria">
                                Visita domiciliaria
                            </label><br>
                            <?= Html::checkbox('electrocardiograma', isset($datos['electrocardiograma']) ? true : false, 
                                ['id' => 'electrocardiograma', 'class' => 'form-check-input']) ?>
                            <label class="form-check-label text-dark" for="electrocardiograma">
                                Electrocardiograma
                            </label>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
        <div class="card">
            <div class="card-header bg-soft-info">
                <h5 class="panel-title">Observaciones</h5>
            </div>
            <div class="card-body mb-3">
                <?= Html::input('text', 'observaciones', $model->observaciones, ['class' => 'form-control', 'placeHolder' => 'Aqui puede agregar detalles sobre la atención realizada.']) ?>
            </div>
        </div>
    </div>

    <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
        <div class="card">
            <div class="card-header bg-soft-info">
                <h5 class="panel-title">Fecha y Hora</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">

                        <?= $form->field($model, 'fecha_creacion')->label(false)->widget(DatePicker::className(), [
                            'type' => DatePicker::TYPE_COMPONENT_APPEND,
                            'pickerIcon' => '<i class="bi bi-calendar2-week"></i>',
                            'removeIcon' => '<i class="bi bi-trash"></i>',
                            'pluginOptions' => [
                                'autoclose' => true,
                            ]
                        ]) ?>

                    </div>

                    <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">

                        <?= $form->field($model, 'hora_creacion')->label(false)->widget(TimePicker::classname(), [
                            'pluginOptions' => [
                                'upArrowStyle' => 'bi bi-chevron-up',
                                'downArrowStyle' => 'bi bi-chevron-down',
                                'showMeridian' => false,
                            ],
                            'addon' => '<i class="bi bi-clock"></i>',
                        ]); ?>


                    </div>
                </div>


            </div>
        </div>

    </div>
</div>