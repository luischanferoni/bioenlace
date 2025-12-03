<?php

use common\models\ConsultaMedicamentos;
use yii\widgets\DetailView;
use frontend\components\PanelWidget;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\GridView;

?>

<div class="card">
    <div class="card-body p-0">

        <?php //model->atencionEnfermeria ver como se muestra en internación. 
        ?>
        <?php if ($model->atencionEnfermeria) {
            $model_a_e = $model->atencionEnfermeria;
            echo PanelWidget::widget([
                'model' => $model_a_e,
                'title' => '<div class ="card-header bg-soft-primary p-3"><h5>Controles de Enfermería</h5></div>',
                'attributes' => [
                    /*[
                    'label' => 'Fecha control/atención',
                    'value' => $model?Yii::$app->formatter->asDate($model->fecha_creacion, 'dd/MM/yyyy'):'--',
                ],*/
                    [
                        'label' => 'Control/Atención',
                        'value' => $model_a_e ? $model_a_e->formatearDatos() : '--',
                        'format' => 'raw',
                    ],
                    'observaciones',
                    [
                        'label' => 'Profesional',
                        'value' => $model_a_e ? (is_object($model_a_e->user) ? $model_a_e->user->nombre . ' ' . $model_a_e->user->apellido : '') : '--',
                    ],
                ],
            ]);
        } ?>

        <?php if (isset($model_valoracion_nutricional)) { ?>
            <div class="col-lg-4">
                <div>
                    <h3 class="mb-5">Valoracion Nutricional</h3>
                </div>
                <?= DetailView::widget([
                    'model' => $model_valoracion_nutricional,
                    'attributes' => [
                        'peso',
                        'talla',
                        'perim_cefalico',
                        'per_perim_cefalico',

                    ],
                ]) ?>
            </div>
        <?php } ?>

        <?php if (isset($model_tension_arterial)) { ?>
            <div class="col-lg-4">
                <div>
                    <h3 class="mb-5">Tension Arterial</h3>
                </div>
                <?= DetailView::widget([
                    'model' => $model_tension_arterial,
                    'attributes' => [
                        'fecha',
                        'diastolica',
                        'sistolica',

                    ],
                ]) ?>
            </div>
        <?php } ?>

        <?php // Motivos 
        ?>

        <div class="row">
            <div class="card-group mb-3">

                <?php if (isset($model->motivoConsulta) && count($model->motivoConsulta) > 0) { ?>

                    <div class="card me-5">
                        <div class="card-header bg-soft-primary p-3">
                            <h5>Motivos</h5>
                        </div>
                        <div class="card-body">

                            <ul class="list-group">
                                <?php foreach ($model->motivoConsulta as $mc) :
                                    $motivo = common\models\snomed\SnomedProblemas::findOne(['conceptId' => $mc['codigo']]);
                                    $motivo_term = '';
                                    if ($motivo) {
                                        $motivo_term = $motivo->term;
                                    }
                                ?>
                                    <li class="list-group-item"><?= $motivo_term ?></li>
                                <?php endforeach; ?>
                            </ul><br>
                            <?php if ($model->motivoConsulta[0]->detalle !== "") { ?>
                                <p><b>Detalles:</b><br><?= $model->motivoConsulta[0]->detalle ?></p><br>
                            <?php } ?>

                        </div>
                    </div>

                <?php } else { ?>

                    <?php
                    if (isset($model->motivo_consulta) && $model->motivo_consulta !== "") { ?>

                        <div class="card me-5">
                            <div class="card-header bg-soft-primary p-3">
                                <h5>Motivos</h5>
                            </div>
                            <div class="card-body">
                                <p><b>Detalles:</b><br><?= $model->motivo_consulta ?></p><br>
                            </div>
                        <?php } ?>

                    <?php } ?>

                    <?php if (count($model_diagnosticos_consulta) !== 0) { ?>
                        <div class="card">
                            <div class="card-header bg-soft-primary p-3">
                                <h5>Diagnosticos</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                foreach ($model_diagnosticos_consulta as $row) {
                                    $diagnostico = common\models\Cie10::findOne(['codigo' => $row['codigo']]);
                                    if (is_object($diagnostico)) {
                                        $diagnostico_mostrar = $diagnostico->diagnostico;
                                    } else {
                                        $diagnostico = common\models\snomed\SnomedHallazgos::findOne(['conceptId' => $row['codigo']]);
                                        if (is_object($diagnostico)) {
                                            $diagnostico_mostrar = $diagnostico->term;
                                        } else {
                                            $diagnostico_mostrar = 'Sin Especificar';
                                        }
                                    }
                                    echo "<span class='text-dark'>" . $diagnostico_mostrar . "</span><br>";
                                }

                                ?>
                            </div>
                        </div>
                    <?php } ?>
                        </div>
            </div>

            <?php // Observaciones (de la version vieja) si las hubiese 
            ?>
            <?php if (isset($model->observacion) && $model->observacion != "") { ?>
                <div class="row">
                    <div class="card-group mb-3">
                        <div class="card me-5">
                            <div class="card-header bg-soft-primary p-3">
                                <h5>Observaciones</h5>
                            </div>
                            <div class="card-body">
                                <p><b>Observaciones:</b><br><?= $model->observacion ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <!--  Medicamentos -->

            <?php if (isset($model_medicamentos_consulta) && count($model_medicamentos_consulta) > 0) { ?>
                <div class="card">
                    <div class="card-header bg-soft-primary p-3">
                        <h5 class="text-black">Medicamentos</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        foreach ($model_medicamentos_consulta as $row) {
                            if ($row['id_medicamento'] !== 0) {
                                $medicamento_mostrar = common\models\Medicamento::findOne(['id_medicamento' => $row['id_medicamento']])->generico;
                                $codigo = $row['id_medicamento'];
                            } else {
                                $medicamento = common\models\snomed\SnomedMedicamentos::findOne(['conceptId' => $row['id_snomed_medicamento']]);
                                if (is_object($medicamento)) {
                                    $medicamento_mostrar = $medicamento->term;
                                    $codigo = $row['id_snomed_medicamento'];
                                }
                            }

                            $frecuenciaPlural = ($row['frecuencia'] > 1) ? 's' : '';

                            $durantePlural = '';

                            if ($row['durante'] > 1) {
                                switch ($row['durante_tipo']) {
                                    case ConsultaMedicamentos::DURANTE_TIPO_MES:
                                        $durantePlural = 'ES';
                                        break;

                                    case ConsultaMedicamentos::DURANTE_TIPO_DIA || ConsultaMedicamentos::DURANTE_TIPO_SEMANA:
                                        $durantePlural = 'S';
                                        break;
                                }
                            }

                            $durante = $row['durante_tipo'] != ConsultaMedicamentos::DURANTE_TIPO_CRONICO ? $row['durante'] . ' ' . $row['durante_tipo'] . $durantePlural : $row['durante_tipo'];

                            echo '<div style="margin:3px; padding:3px;">';
                            echo '<br><b>Nombre:</b> ' . $medicamento_mostrar;
                            echo '<br><b>Cantidad:</b> ' . $row['cantidad'] . ' cada: ' . $row['frecuencia'] . ' ' . ConsultaMedicamentos::FRECUENCIAS[$row['frecuencia_tipo']] . $frecuenciaPlural;
                            echo '<br><b>Durante: </b>' . $durante;
                            echo '</div>';
                        }

                        ?>
                    </div>
                </div>
            <?php } ?>

            <!--  Evaluaciones -->

            <?php if (isset($model_consulta_evaluaciones) && count($model_consulta_evaluaciones) > 0) { ?>
                <div class="card">
                    <div class="card-header bg-soft-primary p-3">
                        <h5 class="text-black">Evaluaciones</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($model_consulta_evaluaciones as $row) :

                            $codigo = "";
                            $practica_mostrar = "";

                            $practica = common\models\snomed\SnomedProcedimientos::findOne(['conceptId' => $row->codigo]);
                            if (is_object($practica)) {
                                $codigo = $row->codigo;
                                $practica_mostrar = $practica->term;
                            } ?>

                            <?= ucfirst($practica_mostrar) ?><br>
                            <b>Detalle:</b> <?= $row->informe; ?><br>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php } ?>

            <!--  Practicas -->

            <?php if (isset($model_consulta_practicas) && count($model_consulta_practicas) > 0) { ?>
                <div class="card">
                    <div class="card-header bg-soft-primary p-3">
                        <h5 class="text-black">Practicas</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($model_consulta_practicas as $row) :
                            $has_adjuntos = false;
                            $codigo = "";
                            $practica_mostrar = "";
                            $detalle_practica = common\models\DetallePractica::findOne(['id_detalle' => $row->id_detalle_practicas]);
                            if (is_object($detalle_practica)) {
                                $codigo = $row->id_detalle_practicas;
                                $practica_mostrar = $detalle_practica->nombre;
                            } else {
                                $practica = common\models\snomed\SnomedProcedimientos::findOne(['conceptId' => $row->codigo]);
                                if (is_object($practica)) {
                                    $codigo = $row->codigo;
                                    $practica_mostrar = $practica->term;
                                }
                            }
                            $adjuntos = $row->adjuntos;
                            // var_dump($adjunto);die;
                            if ($adjuntos) {
                                $has_adjuntos = true;
                            } ?>

                            <?= ucfirst($practica_mostrar) ?><br>
                            <b>Informe:</b> <?= $row->informe; ?><br>
                            <?php if ($has_adjuntos) {
                                $i = 1;
                                foreach ($adjuntos as $adjunto) {
                            ?>
                                    <br>
                                    <b>Adjunto <?= $i ?>:
                                        <?= Html::a(
                                            'Ver archivo',
                                            ['adjunto/ver', 'id' => $adjunto->id],
                                            ['target' => '_blank']
                                        ); ?></b>
                            <?php }
                            } ?>
                            <br>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php } ?>

            <!--  Solicitud de Derivaciones -->

            <?php if (isset($model_consulta_derivaciones) && count($model_consulta_derivaciones) > 0) { ?>
                <div class="card">
                    <div class="card-header bg-soft-primary p-3">
                        <h5 class="text-black">Solicitudes de Practicas/Derivaciones</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($model_consulta_derivaciones as $row) {

                            $tipo_derivacion = $row->tipo_solicitud;
                            $servicio = $row->servicio->nombre;
                            $efector = $row->efector->nombre;
                            $practica = $row->codigoSnomed->term;

                            echo '<strong>Tipo: </strong>' . $tipo_derivacion . '<br>';
                            echo '<strong>Servicio: </strong>' . $servicio . '<br>';
                            echo '<strong>Efector: </strong>' . $efector . '<br>';
                            echo '<strong>Practica: </strong>' . $practica . '<br>';
                        }
                        ?>
                    </div>
                </div>
            <?php } ?>

            <!--  Evolucion -->

            <?php if (isset($model_consulta_evolucion) && $model_consulta_evolucion != "") { ?>
                <div class="card">
                    <div class="card-header bg-soft-primary p-3">
                        <h5 class="text-black">Evolucion</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        echo '<p>' . $model_consulta_evolucion->evolucion . '</p><br>';
                        ?>
                    </div>
                </div>
            <?php } ?>

            <!--  Alergias -->

            <?php
            if (isset($model_consulta_alergias) && count($model_consulta_alergias) > 0) { ?>
                <div class="card">
                    <div class="card-header bg-soft-primary p-3">
                        <h5 class="text-black">Alergias</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($model_consulta_alergias as $ca) :
                            $codigo = $ca['id_snomed_hallazgo'];
                            $alergia = common\models\snomed\SnomedHallazgos::findOne(['conceptId' => $codigo]);
                            $alergia_term = '';
                            if ($alergia) {
                                $alergia_term = $alergia->term;
                            }
                        ?>
                            <?= $alergia_term ?><br>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php } ?>

            <!--  Antecedentes Personales -->
            <?php if (isset($model_personas_antecedente) && count($model_personas_antecedente) > 0) { ?>
                <div class="card">
                    <div class="card-header bg-soft-primary p-3">
                        <h5 class="text-black">Antecedentes Personales</h5>
                    </div>
                    <div class="card-body">

                        <?php foreach ($model_personas_antecedente as $row) {
                            #print_r($row); die();
                            //$antecedente_personal = common\models\Antecedente::findOne(['id_antecedente'=>$row['id_antecedente']]);
                            $antecedente_personal = common\models\snomed\SnomedSituacion::findOne(['conceptId' => $row['codigo']]);
                            echo '<div style="margin:3px; padding:3px">';
                            if (is_object($antecedente_personal)) {

                                echo $antecedente_personal->term;
                            }
                            echo '</div>';
                        } ?>

                    </div>
                </div>

            <?php } ?>

            <!--  Antecedentes Familiares -->
            <?php if (isset($model_personas_antecedente_familiar) && count($model_personas_antecedente_familiar) > 0) { ?>
                <div class="card">
                    <div class="card-header bg-soft-primary p-3">
                        <h5 class="text-black">Antecedentes Familiares</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        foreach ($model_personas_antecedente_familiar as $row) {
                            //$antecedente_familiar = common\models\Antecedente::findOne(['id_antecedente'=>$row['id_antecedente']]);
                            $antecedente_familiar = common\models\snomed\SnomedSituacion::findOne(['conceptId' => $row['codigo']]);
                            echo '<div style="margin:3px; padding:3px">';
                            if (is_object($antecedente_familiar)) {

                                echo  $antecedente_familiar->term;
                            }
                            echo '</div>';
                        } ?>
                    </div>
                </div>
            <?php } ?>


            <?php
            //controla si el tipo de consulta corresponde a Embarazada        
            if ($model->id_tipo_consulta == 4 || $model->id_tipo_consulta == 5) {
            ?>
                <div class="card me-5">
                    <div class="card-header bg-soft-primary">
                        <h5>Control de Embarazo</h5>
                    </div>
                    <div class="card-body">
                        <?= DetailView::widget([
                            'model' => $model_embarazo,
                            'attributes' => [
                                'fum',
                                'fpp',
                                'fecha_diagnostico',
                                'fecha_parto',

                                [
                                    'label' => 'Método Anticonceptivo',
                                    'value' => $model_embarazo->metodo_anticonceptivo == 1 ? "Si" : "No",
                                ],

                            ],
                        ]); ?>
                    </div>
                </div>
            <?php } ?>


            <?php if (isset($model_consulta_oftalmologia) && $model_consulta_oftalmologia->getTotalCount() > 0) { ?>
                <div class="card me-5">
                    <div class="card-header bg-soft-primary">
                        <h5>Oftalmologia</h5>
                    </div>
                    <div class="card-body">
                        <?= GridView::widget([
                            'dataProvider' => $model_consulta_oftalmologia,
                            'columns' => [
                                [
                                    'label' => 'Practica',
                                    'value' => function ($data) {
                                        return $data->getTerm();
                                    },
                                    'contentOptions' => ['class' => 'text-wrap']
                                ],
                                'ojo',
                                [
                                    'attribute' => 'resultado',
                                    'label' => 'Resultado',
                                    'contentOptions' => ['class' => 'text-wrap']
                                ],
                                [
                                    'attribute' => 'informe',
                                    'label' => 'informe',
                                    'contentOptions' => ['class' => 'text-wrap']
                                ],
                            ],
                        ]) ?>
                    </div>
                </div>
            <?php } ?>

            <?php if (isset($model_consulta_oftalmologia_estudio) && $model_consulta_oftalmologia_estudio->getTotalCount() > 0) { ?>
                <div class="card me-5">
                    <div class="card-header bg-soft-primary">
                        <h5>Oftalmologia Estudios Complementarios</h5>
                    </div>
                    <div class="card-body">
                        <?= GridView::widget([
                            'dataProvider' => $model_consulta_oftalmologia_estudio,
                            'columns' => [
                                [
                                    'label' => 'Practica',
                                    'value' => function ($data) {
                                        return $data->getTerm();
                                    }
                                ],
                                'ojo',
                                [
                                    'attribute' => 'informe',
                                    'label' => 'informe',
                                    'contentOptions' => ['class' => 'text-wrap']
                                ],
                            ],
                        ]) ?>
                    </div>
                </div>
            <?php } ?>
            <?php if (isset($model_consulta_receta_lente)) { ?>
                <div class="card me-5">
                    <div class="card-header bg-soft-primary">
                        <h5>Prescripción de Lentes</h5>
                    </div>
                    <div class="card-body">

                        <div class="row justify-content-start">
                            <div class="col-sm-2"></div>
                            <div class="col-sm-8">
                                <h5>De lejos</h5>
                                <table class="table table-responsive table-sm">
                                    <tr>
                                        <th>Ojo</th>
                                        <th>Esfera</th>
                                        <th>Cilindro</th>
                                        <th>Eje</th>
                                    </tr>
                                    <tr>
                                        <td>OD</td>
                                        <td><?= $model_consulta_receta_lente->od_esfera > 0 ?  "+" . $model_consulta_receta_lente->od_esfera : $model_consulta_receta_lente->od_esfera ?></td>
                                        <td><?= $model_consulta_receta_lente->od_cilindro > 0 ?  "+" . $model_consulta_receta_lente->od_cilindro : $model_consulta_receta_lente->od_cilindro ?></td>
                                        <td><?php echo $model_consulta_receta_lente->od_eje ?></td>
                                    </tr>
                                    <tr>
                                        <td>OI</td>
                                        <td><?= $model_consulta_receta_lente->oi_esfera > 0 ?  "+" . $model_consulta_receta_lente->oi_esfera : $model_consulta_receta_lente->oi_esfera ?></td>
                                        <td><?= $model_consulta_receta_lente->oi_cilindro > 0 ?  "+" . $model_consulta_receta_lente->oi_cilindro : $model_consulta_receta_lente->oi_cilindro ?></td>
                                        <td><?php echo $model_consulta_receta_lente->oi_eje ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-sm-2"></div>
                        </div>

                        <div class="row justify-content-start mt-2 mb-2">
                            <div class="col-sm-2"></div>
                            <div class="col-sm-8">
                                <h5>De cerca</h5>
                                <table class="table table-responsive table-sm">
                                    <tr>
                                        <th>Ojo</th>
                                        <th>ADD</th>
                                    </tr>
                                    <tr>
                                        <td>OD</td>
                                        <td><?= $model_consulta_receta_lente->od_add ? "+" . $model_consulta_receta_lente->od_add  : ""?></td>
                                    </tr>
                                    <tr>
                                        <td>OI</td>
                                        <td><?= $model_consulta_receta_lente->oi_add ? "+" . $model_consulta_receta_lente->oi_add  : ""?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-sm-2"></div>
                        </div>

                    </div>
                </div>
            <?php } ?>
        </div>
    </div>