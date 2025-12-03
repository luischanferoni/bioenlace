<?php

use yii\helpers\Url;
use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

?>

<?php $form = ActiveForm::begin([
    'id' => 'form-ia',
    'options' => ['class' => 'form-wizard']
]);
?>

    <div class="row">
        <div class="col-lg-12">
            <div class="form-group">
                <?= $form->field($modelIA, "detalle")->textarea(['rows' => 4]); ?>
            </div>

            <hr class="border border-info border-1 opacity-50">
            <?php if ($modelConsulta->urlAnterior) { ?>
                <?= Html::a('Anterior', $modelConsulta->urlAnterior, ['class' => 'btn btn-primary atender rounded-pill float-start']) ?>
            <?php } ?>
            <!-- Botón oculto de submit -->
            <?= Html::submitButton(
                $modelConsulta->urlSiguiente == 'fin' ? 'Finalizar' : 'Guardar y/o Continuar',
                [
                    'class' => 'btn btn-primary rounded-pill float-end',
                    'style' => 'display:none;', // Oculto
                    'id' => 'btn-submit-oculto'
                ]
            ) ?>

            <!-- Botón Analizar visible -->
            <?= Html::button(
                'Analizar',
                [
                    'class' => 'btn btn-success rounded-pill float-end',
                    'id' => 'btn-analizar'
                ]
            ) ?>

        </div>
    </div>



<?php ActiveForm::end(); ?>

<?php
$headerMenu = $modelConsulta->getHeader();
$header = "$('#modal-consulta-label').html('".$headerMenu."')";
$this->registerJs($header);

// Obtener el ID del textarea de forma segura
$detalleInputId = Html::getInputId($modelIA, 'detalle');
$analizarUrl = Url::to(['/api/v1/consulta/analizar']);
?>

<div id="analisis-resultado" class="mt-4"></div>

<?php
$js = <<<JS
$('#btn-analizar').on('click', function(e) {
    e.preventDefault();
    var btn = $(this);
    var originalHtml = btn.html();
    btn.prop('disabled', true);
    btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Analizando...');
    $('#analisis-resultado').html('');
    var detalle = $('#{$detalleInputId}').val();
    $.ajax({
        url: '{$analizarUrl}',
        type: 'POST',
        data: { detalle: detalle },
        success: function(response) {
            // Si la respuesta es string, intentar parsear
            if (typeof response === 'string') {
                try { response = JSON.parse(response); } catch(e) { response = {}; }
            }
            var html = '';
            if(response.success) {
                html += '<div><b>Diagnóstico:</b> ' + (response.diagnostico ?? '-') + '</div>';
                html += '<div><b>Prácticas:</b><ul>';
                if(response.practicas && response.practicas.length > 0) {
                    response.practicas.forEach(function(item) {
                        html += '<li>' + (item.practica ?? '-') + ' - resultado: ' + (item.resultado ?? '-') + '</li>';
                    });
                } else {
                    html += '<li>-</li>';
                }
                html += '</ul></div>';
                html += '<div><b>Prescripciones:</b>';
                if(response.prescripciones && response.prescripciones.length > 0) {
                    html += '<ul>';
                    response.prescripciones.forEach(function(pres) {
                        html += '<li>';
                        // Medicamentos
                        if(pres.medicamentos && pres.medicamentos.length > 0) {
                            html += '<b>Medicamentos:</b> <ul>';
                            pres.medicamentos.forEach(function(med) {
                                html += '<li>' + med + '</li>';
                            });
                            html += '</ul>';
                        }
                        // Dosis
                        if(pres.dosis) {
                            html += '<b>Dosis:</b> ' + pres.dosis + '<br>';
                        }
                        // Lentes oftálmicos
                        if(pres.lentes_oftalmicos && pres.lentes_oftalmicos.length > 0) {
                            html += '<b>Lentes oftálmicos:</b> <ul>';
                            pres.lentes_oftalmicos.forEach(function(lente) {
                                for (var ojo in lente) {
                                    html += '<li><b>' + ojo.toUpperCase() + ':</b> ' + lente[ojo] + '</li>';
                                }
                            });
                            html += '</ul>';
                        }
                        // Uso
                        if(pres.uso) {
                            html += '<b>Uso:</b> ' + pres.uso + '<br>';
                        }
                        html += '</li>';
                    });
                    html += '</ul>';
                } else {
                    html += '<ul><li>-</li></ul>';
                }
                html += '</div>';
            } else {
                html = '<div class="alert alert-warning">No se pudo analizar la información.</div>';
            }
            $('#analisis-resultado').html(html);
        },
        error: function() {
            $('#analisis-resultado').html('<div class="alert alert-danger">Error al analizar</div>');
        },
        complete: function() {
            btn.prop('disabled', false);
            btn.html(originalHtml);
        }
    });
});
JS;
$this->registerJs($js);
?>