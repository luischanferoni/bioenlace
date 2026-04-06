<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\bootstrap5\Modal;

use common\models\Persona;
use common\helpers\TimelineHelper;
use webvimark\modules\UserManagement\models\User;
use yii\web\View;



$tieneRolEnfermeria = User::hasRole(['enfermeria']) ? true : false;
// Barrio del paciente (domicilio activo)
$barrioNombre = null;
if (is_object($persona) && is_object($persona->domicilioActivo)) {
    if (is_object($persona->domicilioActivo->modelBarrio)) {
        $barrioNombre = $persona->domicilioActivo->modelBarrio->nombre;
    } elseif (!empty($persona->domicilioActivo->barrio)) {
        // Fallback: el campo guarda el id del barrio; si no hay relación, mostramos el valor crudo.
        $barrioNombre = $persona->domicilioActivo->barrio;
    }
}

$barrioTexto = !empty($barrioNombre) ? $barrioNombre : 'Sin datos';
$this->title = $persona->nombre . ' ' . $persona->otro_nombre . ', ' . $persona->apellido . ' | ' . $persona->edad . ' años - Barrio: ' . $barrioTexto;

// Los archivos JS (turnos.js, chat-inteligente.js, timeline.js) se cargan automáticamente desde AppAsset
// Solo registrar Plotly si es necesario para gráficos
$this->registerJsFile(
    "https://cdn.plot.ly/plotly-2.27.1.min.js",
    [
        'position' => View::POS_HEAD,
        'charset' => 'utf-8'
    ]
);

?>

<!-- Primera fila: Datos del paciente (compacta) -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card border-2 mb-1">
            <div class="card-body p-4 pb-1">
                <div class="row">
                    
                    <!-- Columna derecha: Información médica -->
                    <div class="col-12 ms-3">
                        <h6 class="mb-2 text-primary"><b>ESTADO ACTUAL DEL PACIENTE</b></h6>
                        
                        <!-- Última Vacuna -->
                        <!--<div class="mb-2">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <h6 class="mb-0">ÚLTIMA VACUNA</h6>
                                <span id="vacunas-link" style="display: none;">
                                    <a href="#" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal-vacunas">
                                        <i class="bi bi-eye"></i> Ver todas
                                    </a>
                                </span>
                            </div>
                            <div class="border-bottom border-2"></div>
                            <div id="ultima-vacuna-content">
                                <div class="text-center py-2">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Cargando...</span>
                                    </div>
                                    <span class="ms-2 text-muted">Cargando vacunas...</span>
                                </div>
                            </div>
                        </div>-->
                        
                        <div class="row">
                            <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
                                <h6 class="mb-1 text-decoration-underline">DIAGNÓSTICOS RECIENTES</h6>
                                <p class="mb-2" id="tl_condiciones_activas"><span class="text-muted">Cargando...</span></p>
                            </div>
                            <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
                                <h6 class="mb-1 text-decoration-underline">DIAGNÓSTICOS CRÓNICOS</h6>
                                <p class="mb-2" id="tl_condiciones_cronicas"><span class="text-muted">Cargando...</span></p>
                            </div>
                            <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
                                <h6 class="mb-1 text-decoration-underline">ALERGIAS</h6>
                                <p class="mb-2" id="tl_hallazgos"><span class="text-muted">Cargando...</span></p>
                            </div>
                            <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
                                <h6 class="mb-1 text-decoration-underline">ANTECEDENTES</h6>
                                <p class="mb-2" id="tl_antecedentes"><span class="text-muted">Cargando...</span></p>
                            </div>
                        </div>

                        <div class="mb-3 pb-2 border-bottom border-2">
                            <h6 class="mb-2 text-primary"><b>MOTIVOS DE ESTA CONSULTA</b></h6>
                            <p class="mb-0 text-muted" id="tl_motivos_consulta">Cargando...</p>
                        </div>

                        <!-- Signos Vitales Actuales -->
                        <div class="mb-2">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <h6 class="mb-0" id="signos-vitales-titulo">SIGNOS VITALES ACTUALES</h6>
                                <span id="signos-vitales-link" style="display: none;">
                                    <a href="#" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal-signos-vitales">
                                        <i class="bi bi-eye"></i> Ver todos
                                    </a>
                                </span>
                            </div>
                            <div class="border-bottom border-2 mb-1"></div>
                            <div id="signos-vitales-actuales-content">
                                <div class="text-center py-2">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Cargando...</span>
                                    </div>
                                    <span class="ms-2 text-muted">Cargando signos vitales...</span>
                                </div>
                            </div>
                        </div>                        
                    </div>

                    <!-- Contenido automático con loading -->
                    <div class="col-12 ms-3 border-bottom border-2">
                        <!-- Loading inicial -->
                        <div id="loading-container" class="d-flex justify-content-center align-items-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <span class="ms-2">Cargando información...</span>
                        </div>
                        
                        <!-- Contenedores para el contenido -->
                        <?php if ($persona->edad < 14) : ?>
                            <div id="curvas-crecimiento-content" class="mb-3" style="display: none;"></div>
                        <?php endif; ?>
                    </div>

                    <!-- Formulario de chat inteligente -->
                    <div class="col-12">
                        <div class="card border-0">
                            <div class="card-body p-3">

                                <!-- Contenedor para mensajes y formulario (se carga dinámicamente) -->
                                <div id="formulario-container">
                                    <!-- Los mensajes y formulario se cargarán aquí via AJAX -->
                                </div>
                            </div>
                        </div>
                    </div>                    
                </div>
            </div>
        </div>
    </div>
</div>

<?php

Modal::begin([
    'title' => '',
    'id' => 'modal-consulta',
    'size' => 'modal-xl',
    'clientOptions' => ['backdrop' => 'static', 'keyboard' => false]
]);
echo "<div id='modal-content-consulta'></div>";
Modal::end();


Modal::begin([
    'title' => '<h4 id="modal-title"></h4>',
    'id' => 'modal-general',
    'size' => 'modal-xl',
]);
echo "<div id='modal-content'></div>";
Modal::end();
/*$modal = '';

if($referencia['tipo_solicitud'] == 'INTERCONSULTA'):
    Modal::begin([
        'title' => '<h4 id="modal-title">Referencia</h4>',
        'id' => 'modal-referencia',
        'size' => 'modal-lg'
        #'centerVertical' => true
    ]);
    echo "<div id='modal-content-referencia' style='border: 2px solid #1c8b37; background-color:aliceblue;padding:25px'>".$referencia['dato']."</div>";
    echo "<div class='modal-footer'>
            <button type='button' class='btn btn-warning' data-bs-dismiss='modal'>Cerrar</button>
          </div>";
    Modal::end();
    $modal = '$("#modal-referencia").modal("show"); $("#modla-referencia  .modal-dialog .modal-content").css({ "--bs-modal-border-color": "blue" });';
endif;*/
?>



<template id="loader_template">
    <div class="iq-loader-box">
        <div class="iq-loader-8"></div>
    </div>
</template>

<?php
Modal::begin([
    'title' => 'Ver detalle: ',
    'id' => 'modal_detail_consulta',
    'size' => Modal::SIZE_EXTRA_LARGE,
]);
Modal::end();

Modal::begin([
    'title' => 'Historial de Vacunas',
    'id' => 'modal-vacunas',
    'size' => Modal::SIZE_EXTRA_LARGE,
]);
echo "<div id='modal-vacunas-content'></div>";
Modal::end();

Modal::begin([
    'title' => 'Historial de Signos Vitales',
    'id' => 'modal-signos-vitales',
    'size' => Modal::SIZE_EXTRA_LARGE,
]);
echo "<div id='modal-signos-vitales-content'></div>";
Modal::end();

// El manejo del modal de detalle de consulta ahora está en modal-consulta.js
?>

<script>
(function() {
    'use strict';
    
    // Configuración para el timeline (usar var para permitir redeclaración en SPA)
    var timelineConfig = {
        pacienteId: <?= $persona->id_persona ?>,
        endpoints: {
            curvasCrecimiento: <?= $persona->edad < 14 ? "'" . \yii\helpers\Url::to(['personas/curvas-crecimiento', 'id' => $persona->id_persona]) . "'" : 'null' ?>,
            //vacunas: '<?= \yii\helpers\Url::to(['personas/vacunas', 'dni' => $persona->documento, 'sexo' => $persona->sexo_biologico]) ?>',
            signosVitales: <?= json_encode(\yii\helpers\Url::to(['/v1/persona/signos-vitales', 'id' => (int) $persona->id_persona], true), JSON_UNESCAPED_SLASHES) ?>,
            formularioConsulta: '<?= Url::to(['paciente/formulario-consulta', 'id' => $persona->id_persona]) ?>',
            timeline: '/api/v1/personas/<?= (int) $persona->id_persona ?>/timeline'
        }
    };

    function bioHeaders() {
        if (typeof window.getBioenlaceApiClientHeaders === "function") {
            return window.getBioenlaceApiClientHeaders();
        }
        return {};
    }

    function renderBadges(containerId, items, badgeClass) {
        var el = document.getElementById(containerId);
        if (!el) return;
        if (!items || !items.length) {
            el.innerHTML = '<span class="ms-2">Sin datos</span>';
            return;
        }
        el.innerHTML = items
            .filter(function (x) { return x && x.termino; })
            .map(function (x) { return '<span class="badge ' + badgeClass + ' me-1">' + String(x.termino).toUpperCase() + '</span>'; })
            .join('');
    }

    function renderMotivos(texto) {
        var el = document.getElementById('tl_motivos_consulta');
        if (!el) return;
        if (texto && String(texto).trim() !== '') {
            el.classList.remove('text-muted');
            el.classList.add('text-body');
            el.style.whiteSpace = 'pre-wrap';
            el.textContent = String(texto);
        } else {
            el.classList.add('text-muted');
            el.textContent = 'Sin motivos registrados para esta consulta.';
        }
    }

    async function loadTimelineSummary() {
        try {
            var endpoints = timelineConfig.endpoints || {};
            if (!endpoints.timeline) return;
            var resp = await fetch(endpoints.timeline, { headers: bioHeaders() });
            var payload = await resp.json();
            if (!payload || payload.success !== true || !payload.data) {
                throw new Error((payload && payload.message) ? payload.message : 'Error al cargar timeline');
            }
            var info = payload.data.informacion_medica || {};
            renderBadges('tl_condiciones_activas', info.condiciones_activas || [], 'border border-info text-info');
            renderBadges('tl_condiciones_cronicas', info.condiciones_cronicas || [], 'border border-warning text-warning');
            renderBadges('tl_hallazgos', info.hallazgos || [], 'border border-warning text-warning');
            renderBadges('tl_antecedentes', [].concat(info.antecedentes_personales || [], info.antecedentes_familiares || []), 'border border-gray text-gray');
            renderMotivos(info.motivos_consulta || null);
        } catch (e) {
            renderBadges('tl_condiciones_activas', [], 'border border-info text-info');
            renderBadges('tl_condiciones_cronicas', [], 'border border-warning text-warning');
            renderBadges('tl_hallazgos', [], 'border border-warning text-warning');
            renderBadges('tl_antecedentes', [], 'border border-gray text-gray');
            renderMotivos(null);
        }
    }

    // Función para inicializar el timeline
    function inicializarTimeline() {
        console.log('Intentando inicializar timeline...');
        console.log('TimelineJS disponible:', !!window.TimelineJS);
        console.log('TimelineJS.init disponible:', !!(window.TimelineJS && window.TimelineJS.init));
        console.log('Config:', timelineConfig);
        
        // Verificar que los elementos del DOM estén presentes
        const signosVitalesContent = document.getElementById('signos-vitales-actuales-content');
        const formularioContainer = document.getElementById('formulario-container');
        
        if (!signosVitalesContent && !formularioContainer) {
            console.warn('Elementos del timeline no encontrados en el DOM');
            return false;
        }
        
        if (window.TimelineJS && window.TimelineJS.init) {
            console.log('Inicializando timeline con config:', timelineConfig);
            try {
                // Siempre inicializar, incluso si ya se inicializó antes (para SPA)
                window.TimelineJS.init(timelineConfig);
                loadTimelineSummary();
                console.log('Timeline inicializado correctamente');
                return true;
            } catch (error) {
                console.error('Error al inicializar timeline:', error);
                return false;
            }
        } else {
            console.warn('TimelineJS no está disponible aún');
            return false;
        }
    }

    // Función para intentar inicializar con múltiples reintentos
    function intentarInicializarConReintentos(intentosMaximos, delay) {
        var intentos = 0;
        var intervalo = setInterval(function() {
            intentos++;
            console.log('Intento de inicialización:', intentos, 'de', intentosMaximos);
            
            if (inicializarTimeline()) {
                clearInterval(intervalo);
                return;
            }
            
            if (intentos >= intentosMaximos) {
                console.error('No se pudo inicializar el timeline después de', intentosMaximos, 'intentos');
                clearInterval(intervalo);
            }
        }, delay);
    }

    // Intentar inicializar inmediatamente y luego con reintentos
    if (!inicializarTimeline()) {
        // Si el DOM está cargando, esperar a que esté listo
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                console.log('DOM cargado, intentando inicializar timeline...');
                if (!inicializarTimeline()) {
                    intentarInicializarConReintentos(5, 300);
                }
            });
        } else {
            // DOM ya está listo, intentar con reintentos
            console.log('DOM ya está listo, intentando inicializar timeline...');
            intentarInicializarConReintentos(5, 300);
        }
    }
})();
</script>