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

            <div class="col align-self-center justify-content-end">
                <?php if ($model->parent_class == '\common\models\Turno' || $model->parent_class == '\common\models\ServiciosEfector') {
                    $fecha_consulta = strtotime($model->parent->fecha . ' ' . $model->parent->hora);
                } else {
                    $fecha_consulta = strtotime($model['created_at']);
                } ?>
                <h3 class="text-end"><?= Yii::$app->formatter->asDate($fecha_consulta, 'php:d F Y - g:iA') ?></h3>
            </div>


            <?php if ($show_button_bar) : ?>
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
<?php if ($show_header) : ?>
    <div class="card">
        <div class="card-header bg-soft-primary">
            <h3 class="text-dark">Detalle de la Consulta</h3>
        </div>

    </div>
<?php endif; ?>

</div>


<?= $this->render('./v2/_detalle_consulta_amb', [
    'model' => $model,
    'model_diagnosticos_consulta' => $model_diagnosticos_consulta,
    'atencion_enfermeria' => $atencion_enfermeria,
    'model_valoracion_nutricional' => $model_valoracion_nutricional,
    'model_embarazo' => $model_embarazo,
    'model_tension_arterial' => $model_tension_arterial,
    'model_medicamentos_consulta' => $model_medicamentos_consulta,
    'model_consulta_practicas' => $model_consulta_practicas,
    'model_consulta_evaluaciones' => $model_consulta_evaluaciones,
    'model_consulta_alergias' => $model_consulta_alergias,
    'model_consulta_derivaciones' => $model_consulta_derivaciones,
    'model_personas_antecedente' => $model_personas_antecedente,
    'model_personas_antecedente_familiar' => $model_personas_antecedente_familiar,
    'model_consulta_evolucion' => $model_consulta_evolucion,
    'model_consulta_oftalmologia' => $model_consulta_oftalmologia,
    'model_consulta_oftalmologia_estudio' => $model_consulta_oftalmologia_estudio,
    'model_consulta_receta_lente' => $model_consulta_receta_lente,

]) ?>