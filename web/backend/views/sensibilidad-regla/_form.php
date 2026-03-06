<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\SensibilidadRegla */
/* @var $servicios common\models\Servicio[] */
/* @var $idsServicios int[] */

$form = ActiveForm::begin();
?>

<div class="sensibilidad-regla-form">

    <?= $form->field($model, 'accion')->dropDownList(\common\models\SensibilidadRegla::accionesDisponibles(), ['prompt' => 'Seleccione']) ?>
    <?= $form->field($model, 'codigo_generalizacion')->textInput(['maxlength' => true]) ?>
    <?= $form->field($model, 'etiqueta_generalizacion')->textInput(['maxlength' => true]) ?>

    <div class="form-group">
        <label class="control-label">Servicios que ven generalizado/oculto (lista vacía = todos ven completo)</label>
        <div class="form-control" style="height: auto; max-height: 200px; overflow-y: auto;">
            <?php
            $idsServicios = array_flip($idsServicios);
            foreach ($servicios as $s) {
                $id = (int) $s->id_servicio;
                $checked = isset($idsServicios[$id]) ? ' checked' : '';
                echo '<div class="checkbox"><label><input type="checkbox" name="ids_servicios[]" value="' . (int)$id . '"' . $checked . '> ' . Html::encode($s->nombre) . '</label></div>';
            }
            ?>
        </div>
        <p class="help-block">Solo los servicios marcados reciben la acción; el resto ve el dato completo.</p>
    </div>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Crear' : 'Actualizar', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
        <?= Html::a('Cancelar', $model->isNewRecord ? ['sensibilidad-categoria/index'] : ['view', 'id' => $model->id], ['class' => 'btn btn-default']) ?>
    </div>

</div>

<?php ActiveForm::end(); ?>
