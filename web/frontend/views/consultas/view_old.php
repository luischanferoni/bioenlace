<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use frontend\components\PanelWidget;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $model common\models\Consulta */

//$this->title = $model->id_consulta;
$this->title = 'consulta';
$this->params['breadcrumbs'][] = ['label' => 'Consultas', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="card">
    <div class="card-header">
        <div class="row">
            <div class="col align-self-center">
                <?= '<h3 class="text-uppercase">' . Html::label('Paciente:') . ' ' . $model_persona->apellido . ', ' . $model_persona->nombre . '<h3>' ?>
            </div>
            <?php if($show_button_bar): ?>
            <div class="col align-self-center">
                <p class="text-end pe-2">
                    <?php if (!isset($_GET['type'])) { ?>
                        <?= Html::a('Actualizar', ['update', 'id' => $model->id_consulta], ['class' => 'btn btn-primary rounded-pill']) ?>
                        <?= Html::a(
                            'Crear Referencia',
                            ['referencias/create', 'idc' => $model->id_consulta],
                            ['class' => 'btn btn-success rounded-pill', 'target' => 'blank']
                        ) ?>
                        <?= Html::a('Imprimir', ['view', 'id' => $model->id_consulta, 'type' => 'print'], ['class' => 'btn btn-info text-white rounded-pill', 'target' => '_blank']) ?>

                    <?php  } //= Html::a('Eliminar', ['delete', 'id' => $model->id_consulta], [
                    //            'class' => 'btn btn-danger',
                    //            'data' => [
                    //                'confirm' => 'Realmente desea borrar este registro?',
                    //                'method' => 'post',
                    //            ],
                    //        ]) 
                    ?>
                    <?php if (!empty($model_medicamentos_consulta)) { ?>
                        <?= Html::a('Receta', ['imprimirreceta', 'id' => $model->id_consulta, 'type' => 'print'], ['class' => 'btn btn-info text-white rounded-pill', 'target' => '_blank']) ?>
                    <?php  } ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!--    <h1><?= Html::encode($this->title) ?></h1>-->


<?php
//$tipo_consulta = \common\models\TipoIngreso::findOne($model->id_tipo_ingreso);
$id_tipo_consulta = $model->id_tipo_consulta;
?>

<div class="card">

    <div class="card-body">
        <?php if($show_header) :?>
        <div class="card-header bg-soft-primary">
            <h3 class="text-dark">Detalle de la Consulta</h3>
        </div>
        <?php endif; ?>
        <table class="table dataTable detail-view text-wrap">
            <thead>
                <tr>
                    <td colspan="2" class="text-dark"><span class="badge bg-primary pe-5">
                            <h5 class="text-white"><i class="bi bi-calendar2-date"></i> <?= $consulta_fecha ?></h5>
                        </span>
                        <span class=" badge bg-success pe-5">
                            <h5 class="text-white"><i class="bi bi-clock"></i> <?= '12:00' ?></h5>
                        </span>
                        <span class="pe-5 badge bg-info">
                            <h5 class="text-white">Tipo: <?= $model->consulta_inicial == 'SI' ? "Inicial" : "Ulterior" ?></h5>
                        </span>
                    </td>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th class="text-dark"> Motivo</th>
                    <td class="text-dark">
                    <?= chunk_split(
                            $model->motivo_consulta, 70, "<br>") ?>
                    </td>
                </tr>
                <tr>
                    <th class="text-dark">Observaciones</th>
                    <td class="text-dark">
                    <?= chunk_split(
                            $model->observacion, 70, "<br>") ?>
                    </td>
                </tr>
            </tbody>
        </table>

    </div>

</div>

<div class="card">
    <div class="card-body">

        <?php if ($atencion_enfermeria) {
            $model_a_e = $atencion_enfermeria;
            echo PanelWidget::widget([
                'model' => $model_a_e,
                'title' => '<div class ="card-header bg-soft-primary"><h4>Controles de Enfermería</h4></div>',
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

        <?php if ($model_valoracion_nutricional) { ?>
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

        <?php if ($model_tension_arterial) { ?>
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

        <?php // MOtivos ?>
        <div class="mt-5 mb-5">
          <table class="table table-striped table-bordered detail-view">
            <tbody>
              <tr>
                <th class="bg-soft-primary" style="width: 10%">Motivos</th>
                <td>
                <?php if(!empty($model->motivoConsulta)): ?>
                  <?php foreach ($model->motivoConsulta as $mc): 
                    $motivo = common\models\snomed\SnomedProblemas::findOne(['conceptId' => $mc['codigo']]);
                    $motivo_term = '';
                    if($motivo) {
                        $motivo_term = $motivo->term;
                    }
                  ?>
                    <b><?= $mc->codigo ?> - </b> <?= $motivo_term ?><br>
                  <?php endforeach; ?>
                <?php else: ?>
                    Sin especificar
                <?php endif; ?>
                </td>
              </tr>
            </tbody>
            </table>
        </div>
        
        <?php // Sintomas ?>
        <div class="mt-5 mb-5">
          <table class="table table-striped table-bordered detail-view">
            <tbody>
              <tr>
                <th class="bg-soft-primary" style="width: 10%">Síntomas</th>
                <td>
                <?php if(!empty($model->consultaSintomas)): ?>
                  <?php foreach ($model->consultaSintomas as $cs): 
                    $sintoma = common\models\snomed\SnomedProblemas::findOne(['conceptId' => $cs['codigo']]);
                    $sintoma_term = '';
                    if($sintoma) {
                        $sintoma_term = $sintoma->term;
                    }
                  ?>
                    <b><?= $cs->codigo ?> - </b> <?= $sintoma_term ?><br>
                  <?php endforeach; ?>
                <?php else: ?>
                    Sin especificar
                <?php endif; ?>
                </td>
              </tr>
            </tbody>
            </table>
        </div>
        
        <?php // Alergias ?>
        <div class="mt-5 mb-5">
          <table class="table table-striped table-bordered detail-view">
            <tbody>
              <tr>
                <th class="bg-soft-primary" style="width: 10%">Alergias</th>
                <td>
                <?php if(!empty($model->alergias)): ?>
                  <?php foreach ($model->alergias as $ca):
                    $codigo = $ca['id_snomed_hallazgo'];
                    $alergia = common\models\snomed\SnomedHallazgos::findOne(['conceptId' => $codigo]);
                    $alergia_term = '';
                    if($alergia) {
                        $alergia_term = $alergia->term;
                    }
                  ?>
                    <b><?= $codigo ?> - </b> <?= $alergia_term ?><br>
                  <?php endforeach; ?>
                <?php else: ?>
                    Sin especificar
                <?php endif; ?>
                </td>
              </tr>
            </tbody>
            </table>
        </div>

        <div class="mt-5 mb-5">
            <table class="table table-striped table-bordered detail-view">
                <tbody>
                    <tr>
                        <th class="bg-soft-primary" style="width: 10%">Diagnósticos</th>
                        <td>

                            <?php
                            foreach ($model_diagnosticos_consulta as $row) {
                                $diagnostico = common\models\Cie10::findOne(['codigo' => $row['codigo']]);
                                if (is_object($diagnostico)) {
                                    $diagnostico_mostrar = $diagnostico->diagnostico;
                                } else {
                                    $diagnostico = common\models\snomed\SnomedHallazgos::findOne(['conceptId' => $row['codigo']]);
                                    if (is_object($diagnostico)) {
                                        $diagnostico_mostrar = $diagnostico->term;
                                    }
                                }

                                echo "<b>" . $row['codigo'] . " - </b> ";
                                echo "<span class='text-capitalize'>" . $diagnostico_mostrar . "</span>";
                                if ($row['tipo_diagnostico'] === 'P') {
                                    echo ' (Primario) <br>';
                                } else if ($row['tipo_diagnostico'] === 'S') {
                                    echo ' (Secundario) <br>';
                                } else {
                                    echo ' (Sin Especificar) <br>';
                                }
                            }

                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php
        //controla si el tipo de consulta corresponde a Embarazada        
        if ($id_tipo_consulta == 4 or $id_tipo_consulta == 5) {
        ?>
            <div class="col-lg-4">
                <div>
                    <h3>Control de Embarazo</h3>
                </div>
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
        <?php } ?>


        <!--  Tabla Medicamentos-->
        <?php if (!empty($model_medicamentos_consulta)) { ?>

            <div class="mt-5 mb-5">
                <table class="table table-striped table-bordered detail-view">
                    <tbody>
                        <tr>
                            <th class="bg-soft-primary" style="width: 10%">Medicamentos</th>
                            <td>

                                <?php
                                if (!empty($model_medicamentos_consulta)) {
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
                                        echo '<div style="background-color: #E8E8E8 ; margin:3px; padding:3px">';
                                        echo '<b>Cód de Medicamento:</b> ' . $codigo;
                                        echo '<br><b>Nombre Genérico:</b> ' . $medicamento_mostrar;
                                        echo '<br><b>Cantidad:</b> ' . $row['cantidad'];
                                        echo '<br><b>Dosis Diaria:</b> ' . $row['frecuencia'];

                                        echo '</div>';
                                    }
                                } else {
                                    echo 'Sin especificar';
                                }

                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php } ?>

        <!--  Tabla Practicas Personas-->

        <div class="mt-5 mb-5">

            <table class="table table-striped table-bordered detail-view">
                <tbody>
                    <tr>
                        <th class="bg-soft-primary" style="width: 10%">Prácticas</th>
                        <td>
<?php if (!empty($model_consulta_practicas)): ?>
  <?php foreach ($model_consulta_practicas as $row):
    $has_adjunto = false;
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
    $adjunto = common\models\Adjunto::findOne($row['adjunto']);
    if($adjunto) {
        $has_adjunto = true;
        $adjunto_url = url::to('@web/'.$adjunto->path);
        $adjunto_label = basename($adjunto->path);
    }
  ?>
  <div class="card">
    <div class="card-body">
        <b><?= $codigo ?> - </b> <?= $practica_mostrar ?><br>
      <b>Informe:</b> <?= $row['informe'];?>
      <?php if($has_adjunto): ?>
      <br><b>Adjunto: 
      <?= Html::a($adjunto_label, $adjunto_url,
              ['target' => '_blank']); ?></b> 
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
<?php else: ?>
   Sin Especificar
<?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!--  Tabla Personas Antecedentes-->

        <?php
        //controla si el tipo de consulta corresponde a Embarazada

        if ($id_tipo_consulta != 4 and $id_tipo_consulta != 5) {

        ?>
            <div class="mt-5 mb-5">
                <table class="table dataTable table-bordered detail-view">
                    <tbody>
                        <tr>
                            <th class="text-dark bg-soft-secondary" colspan="2">ANTECEDENTES</th>
                        </tr>
                        <tr>
                            <th class="bg-soft-primary" style="width: 10%">Personales</th>
                            <th class="bg-soft-primary" style="width: 10%">Familiares</th>
                        </tr>
                        <tr>
                            <td>

                                <?php

                                if (!empty($model_personas_antecedente)) {
                                    foreach ($model_personas_antecedente as $row) {
                                        #print_r($row); die();
                                        //$antecedente_personal = common\models\Antecedente::findOne(['id_antecedente'=>$row['id_antecedente']]);
                                        $antecedente_personal = common\models\snomed\SnomedSituacion::findOne(['conceptId' => $row['codigo']]);
                                        echo '<div style="background-color: #E8E8E8 ; margin:3px; padding:3px">';
                                        if (is_object($antecedente_personal)) {

                                            echo $antecedente_personal->term;
                                        }
                                        echo '</div>';
                                    }
                                } else {
                                    echo 'Sin Especificar';
                                }

                                ?>
                            </td>
                            <td>

                                <?php

                                if (!empty($model_personas_antecedente_familiar)) {
                                    foreach ($model_personas_antecedente_familiar as $row) {
                                        //$antecedente_familiar = common\models\Antecedente::findOne(['id_antecedente'=>$row['id_antecedente']]);
                                        $antecedente_familiar = common\models\snomed\SnomedSituacion::findOne(['conceptId' => $row['codigo']]);
                                        echo '<div style="background-color: #E8E8E8 ; margin:3px; padding:3px">';
                                        if (is_object($antecedente_familiar)) {

                                            echo  $antecedente_familiar->term;
                                        }
                                        echo '</div>';
                                    }
                                } else {
                                    echo 'Sin Especificar';
                                }

                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php
        }
        ?>
    </div>
</div>