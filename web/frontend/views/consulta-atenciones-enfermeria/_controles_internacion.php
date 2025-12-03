<?php

use kartik\date\DatePicker;
use kartik\time\TimePicker;
use yii\helpers\Html;

?>


<div class="row">


    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card">
            <div class="card-header bg-soft-info">
                <h3>Otras Atenciones/Controles</h3>
            </div>
            <div class="card-body">

                <div class="row">
                    <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12 mb-3">
                        <div class="form-check">
                            <h5 class="mb-2">Cama</h5>
                            <label class="form-check-label ps-2 text-dark">
                                <input type="checkbox" name="225965002" value="cambio_ropa_cama"> Cambio de Ropa de Cama
                            </label><br>
                            <label class="form-check-label ps-2 text-dark">
                                <input type="checkbox" name="24530001" value="tendido_cama"> Tendido de Cama
                            </label><br>
                            <label class="form-check-label ps-2 text-dark">
                                <input type="checkbox" name="orden_unidad" value="orden_unidad"> Orden de Unidad del Paciente
                            </label><br>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12 mb-3">
                        <div class="form-check">
                            <h5 class="mb-2">Medidas de Higiene</h5>
                            <label class="form-check-label ps-2 text-dark">
                                <input type="checkbox" name="444681008" value="higiene_perineal"> Higiene Perineal
                            </label><br>
                            <label class="form-check-label ps-2 text-dark">
                                <input type="checkbox" name="717778001" value="higiene_bucal"> Higiene Bucal
                            </label><br>
                            <label class="form-check-label ps-2 text-dark">
                                <input type="checkbox" name="higiene_matutina" value="higiene_matutina"> Higiene Matutina
                            </label><br>
                            <label class="form-check-label ps-2 text-dark">
                                <input type="checkbox" name="20565007" value="baño_ducha"> Baño en Ducha
                            </label><br>
                            <label class="form-check-label ps-2 text-dark">
                                <input type="checkbox" name="58530006" value="baño_cama"> Baño en Cama
                            </label><br>
                            <label class="form-check-label ps-2 text-dark">
                                <input type="checkbox" name="13749006" value="baño_inmersion"> Baño de Inmersión
                            </label><br>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12 mb-3">
                        <div class="form-check">
                            <h5 class="mb-2">Movilización</h5>
                            <label class="form-check-label ps-2 text-dark">
                                <input type="checkbox" name="359962006" value="rotacion"> Rotacion
                            </label><br>
                            <label class="form-check-label ps-2 text-dark">
                                <input type="checkbox" name="56469005" value="cambios_posicion"> Cambios de Posición
                            </label><br>
                            <label class="form-check-label ps-2 text-dark">
                                <input type="checkbox" name="ejercicios" value="ejercicios"> Ejercicios Pasivos y/o Activos
                            </label><br>
                        </div>
                    </div>

                    <?php //name=conceptId de SNOMED
                    ?>


                    <div class="col-lg-4 col-md-3 col-sm-12 col-xs-12 mb-3">
                        <div class="form-check">
                            <h5 class="mb-2">Curaciones</h5>
                            <label class="form-check-label ps-2 text-dark">
                                <input type="checkbox" name="herida_quirurgica" value="herida_quirurgica"> Herida Quirúrgica
                            </label><br>
                            <label class="form-check-label ps-2 text-dark">
                                <input type="checkbox" name="133901003" value="quemaduras"> Quemaduras
                            </label><br>
                            <label class="form-check-label ps-2 text-dark">
                                <input type="checkbox" name="30549001" value="extraccion_punto"> Extracción de Puntos
                            </label><br>
                            <label class="form-check-label ps-2 text-dark">
                                <input type="checkbox" name="241031001" value="extraccion_drenaje"> Extracción de Drenajes
                            </label><br>
                            <label class="form-check-label ps-2 text-dark">
                                <input type="checkbox" name="otras" value="otras"> Otras
                            </label><br>
                        </div>
                    </div>



                    <div class="col-lg-4 col-md-3 col-sm-12 col-xs-12 mb-3">
                        <div class="form-check">
                            <h5 class="mb-2">Otras</h5>
                            <label class="form-check-label ps-2 text-dark">
                                <input type="checkbox" name="87750000" value="colocacion_sonda_nasogastrica"> Colocación de Sonda Nasogástrica
                            </label><br>
                            <label class="form-check-label ps-2 text-dark">
                                <input type="checkbox" name="173830003" value="lavaje_gastrico"> Lavaje Gástrico
                            </label><br>
                            <label class="form-check-label ps-2 text-dark">
                                <input type="checkbox" name="274441001" value="drenaje_aspirativo"> Drenaje Aspirativo
                            </label><br>
                            <label class="form-check-label ps-2 text-dark">
                                <input type="checkbox" name="225285007" value="administracion_oral_liquidos"> Administración Oral de Liquidos
                            </label><br>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-3 col-sm-12 col-xs-12 mt-5 mb-3">
                        <div class="form-check">
                            <label class="form-check-label ps-2 text-dark">
                                <input type="checkbox" name="410256001" value="colocacion_sonda_vesical"> Colocación de Sonda Vesical
                            </label><br>
                            <label class="form-check-label ps-2 text-dark">
                                <input type="checkbox" name="bolsa_orina" value="bolsa_orina"> Bolsa colectora de Orina
                            </label><br>
                            <label class="form-check-label ps-2 text-dark">
                                <input type="checkbox" name="78533007" value="lavaje_vesical"> Lavaje Vesical
                            </label><br>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-3 col-sm-12 col-xs-12 mb-3">
                        <h5 class="mb-2">Alimentación</h5>
                        <select class="form-select form-select-sm mb-5" aria-label=".form-select-sm alimentacion">
                            <option hidden selected>Seleccione la forma de alimentación</option>
                            <option name="53370001" value="alimentacion_oral">Oral</option>
                            <option name="229914003" value="alimentacion_nasogastrica">Nasogástrica</option>
                            <option name="229917005" value="alimentacion_gastrostomia">Gastrostomía</option>
                        </select>
                    </div>

                    <div class="col-lg-4 col-md-3 col-sm-12 col-xs-12 mt-5 mb-3">
                        <select class="form-select form-select-sm ps-2" aria-label=".form-select-sm alimentacion">
                            <option hidden selected>Seleccione el horario de alimentación</option>
                            <option value="alimentacion_desayuno">Desayuno</option>
                            <option value="alimentacion_almuerzo">Almuerzo</option>
                            <option value="alimentacion_merienda">Merienda</option>
                            <option value="alimentacion_cena">Cena</option>
                        </select>
                    </div>

                    <div class="col-lg-4 col-md-3 col-sm-12 col-xs-12 mb-3">
                        <h5 class="mb-2">Enema</h5>
                        <select class="form-select form-select-sm ps-2" aria-label=".form-select-sm enema">
                            <option hidden selected>Seleccione el tipo de enema</option>
                            <option value="enema_evacuante">Evacuante</option>
                            <option value="enema_murphy">Murphy</option>
                            <option value="enema_micro">Micro Enema</option>
                            <option value="enema_otro">otro</option>
                        </select>
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
                <?= Html::input('text', 'observaciones', '', ['class' => 'form-control', 'placeHolder' => 'Aqui puede agregar detalles sobre la atención realizada.']) ?>
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