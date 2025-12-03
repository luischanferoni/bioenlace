<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Url;

use common\assets\SisseDynamicFormAsset;

//SisseDynamicFormAsset::register($this);
?>

<?php
    $form = ActiveForm::begin(
        [
            'id' => 'form-motivoconsulta',
            'options' => []
        ]
    );
?>

    <div class="form-group" id="item">
        <!-- Input de búsqueda -->
        <div class="mb-3">
            <label class="form-label">Buscar Motivos de Consulta</label>
            <input type="text" 
                   id="search-motivos" 
                   class="form-control" 
                   placeholder="Escriba al menos 4 caracteres para buscar..."
                   autocomplete="off">
        </div>
        
        <!-- Lista de resultados -->
        <div id="motivos-results" class="list-group" style="max-height: 300px; overflow-y: auto; display: none;">
            <!-- Los resultados se cargarán aquí dinámicamente -->
        </div>
        
        <!-- Motivos seleccionados -->
        <div class="mt-3">
            <label class="form-label">Motivos Seleccionados</label>
            <div id="motivos-seleccionados" class="border rounded p-2" style="min-height: 50px;">
                <!-- Los motivos seleccionados aparecerán aquí -->
            </div>
        </div>

        <!-- Botones para prácticas fijas -->
        <div class="mt-3">
            <label class="form-label">Motivos Frecuentes</label>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($motivos as $ind => $practica): ?>
                    <button type="button" 
                            class="btn btn-outline-info btn-sm fixed_values" 
                            data-value="<?= $ind ?>" 
                            data-text="<?= Html::encode($practica) ?>">
                        <?= Html::encode($practica) ?>
                    </button>
                <?php endforeach ?>
            </div>
        </div>
        
        <?= Html::hiddenInput("terminos_motivos", implode(",", array_values($dataProblemas)), ['id' => "terminos_motivos"]) ?>

        <?php 
        /*
           echo $form->field($modelConsultaMotivos, 'detalle')
            ->textarea(['rows' => 5,]);
        */
        ?>

    </div>

<?php ActiveForm::end(); ?>

<?php
//$headerMenu = $modelConsulta->getHeader();
$searchUrl = Url::to(["snowstorm/motivos-de-consulta"]);

$script = <<<JS

// Configuración
const MOTIVOS_CONFIG = {
    searchUrl: '$searchUrl',
    minLength: 4,
    delay: 500
};

// Variables globales
let motivosSeleccionados = [];
let searchTimeout = null;

// Inicializar directamente (no usar document.ready en modal)


// Pequeño delay para asegurar que el DOM del modal esté listo
setTimeout(function() {
    inicializarMotivos();
}, 100);

function inicializarMotivos() {
    console.log('Inicializando motivos...');
    console.log('Elemento search-motivos:', $('#search-motivos').length);
    
    // Búsqueda en tiempo real
    $('#search-motivos').on('input keyup', function() {
        const query = $(this).val().trim();
        console.log('Input detectado, query:', query);
        if (query.length < MOTIVOS_CONFIG.minLength) {
            $('#motivos-results').hide();
            return;
        }
        
        // Debounce
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            buscarMotivos(query);
        }, MOTIVOS_CONFIG.delay);
    });
    
    // Botones de motivos fijos
    $('.fixed_values').on('click', function() {
        const value = $(this).data('value');
        const text = $(this).data('text');
        agregarMotivo(value, text);
    });
    
    // Evento de delegación como alternativa
    $(document).on('input keyup', '#search-motivos', function() {
        const query = $(this).val().trim();
        console.log('Delegación detectada, query:', query);
        if (query.length < MOTIVOS_CONFIG.minLength) {
            $('#motivos-results').hide();
            return;
        }
        
        // Debounce
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            buscarMotivos(query);
        }, MOTIVOS_CONFIG.delay);
    });
}

function buscarMotivos(query) {
    console.log("buscarMotivos");
    $.ajax({
        url: MOTIVOS_CONFIG.searchUrl,
        method: 'GET',
        data: { q: query },
        dataType: 'json',
        beforeSend: function() {
            $('#motivos-results').html('<div class="text-center p-3"><div class="spinner-border spinner-border-sm"></div> Buscando...</div>').show();
        },
        success: function(data) {
            mostrarResultados(data);
        },
        error: function() {
            $('#motivos-results').html('<div class="alert alert-danger">Error al buscar motivos</div>').show();
        }
    });
}

function mostrarResultados(data) {
    let html = '';
    
    if (data && data.length > 0) {
        data.forEach(function(item) {
            html += '<div class="list-group-item list-group-item-action" ' +
                   'data-value="' + item.id + '" ' +
                   'data-text="' + item.text + '" ' +
                   'style="cursor: pointer;">' +
                   '<div class="d-flex justify-content-between">' +
                   '<span>' + item.text + '</span>' +
                   '<small class="text-muted">' + item.id + '</small>' +
                   '</div></div>';
        });
    } else {
        html = '<div class="list-group-item text-muted">No se encontraron resultados</div>';
    }
    
    $('#motivos-results').html(html).show();
    
    // Eventos para seleccionar
    $('#motivos-results .list-group-item').on('click', function() {
        const value = $(this).data('value');
        const text = $(this).data('text');
        agregarMotivo(value, text);
        $('#motivos-results').hide();
        $('#search-motivos').val('');
    });
}

function agregarMotivo(value, text) {
    // Verificar si ya está seleccionado
    if (motivosSeleccionados.find(m => m.value === value)) {
        return;
    }
    
    // Agregar a la lista
    motivosSeleccionados.push({ value: value, text: text });
    
    // Actualizar UI
    actualizarMotivosSeleccionados();
    actualizarHiddenInput();
}

function removerMotivo(value) {
    motivosSeleccionados = motivosSeleccionados.filter(m => m.value !== value);
    actualizarMotivosSeleccionados();
    actualizarHiddenInput();
}

function actualizarMotivosSeleccionados() {
    let html = '';
    
    if (motivosSeleccionados.length === 0) {
        html = '<div class="text-muted">No hay motivos seleccionados</div>';
    } else {
        motivosSeleccionados.forEach(function(motivo) {
            html += '<span class="badge bg-primary me-2 mb-2" style="font-size: 0.9em;">' +
                   motivo.text +
                   '<button type="button" ' +
                   'class="btn-close btn-close-white ms-1" ' +
                   'style="font-size: 0.7em;" ' +
                   'onclick="removerMotivo(\'' + motivo.value + '\')"></button>' +
                   '</span>';
        });
    }
    
    $('#motivos-seleccionados').html(html);
}

function actualizarHiddenInput() {
    const valores = motivosSeleccionados.map(m => m.value).join(',');
    $('#terminos_motivos').val(valores);
}

JS;

$this->registerJs($script);
?>