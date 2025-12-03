<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\bootstrap5\Modal;

use common\models\Persona;
use common\helpers\TimelineHelper;
use webvimark\modules\UserManagement\models\User;
use yii\web\View;



$tieneRolEnfermeria = User::hasRole(['enfermeria']) ? true : false;
$this->title = $persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N);

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
                    <!-- Columna izquierda: Datos del paciente -->
                    <div class="col-12 mb-3 border-bottom border-2">
                        
                        <!-- Desktop: Horizontal layout -->
                        <div class="d-flex flex-row d-none d-md-flex">
                            <b>Nombre Completo:</b>
                            <div class="ms-2 me-2"><?= $persona->nombre . ' ' . $persona->otro_nombre . ', ' . $persona->apellido ?></div>

                            <b class="ps-2 border-start border-2 pl-2">F. Nacimiento:</b>
                            <div class="ms-2 me-2"><?= Yii::$app->formatter->asDate($persona->fecha_nacimiento, 'dd LLLL yyyy').' ('.$persona->edad.' años)' ?></div>
                            <b class="ps-2 border-start border-2 pl-2">Nro. Documento:</b>
                            <div class="ms-2"><?= $persona->documento ?></div>
                        </div>
                        
                        <!-- Mobile: Vertical layout -->
                        <div class="d-flex flex-column d-md-none">
                            <div class="mb-2">
                                <b>Nombre Completo:</b>
                                <div class="ms-2"><?= $persona->nombre . ' ' . $persona->otro_nombre . ', ' . $persona->apellido ?></div>
                            </div>
                            
                            <div class="mb-2">
                                <b>F. Nacimiento:</b>
                                <div class="ms-2"><?= Yii::$app->formatter->asDate($persona->fecha_nacimiento, 'dd LLLL yyyy').' ('.$persona->edad.' años)' ?></div>
                            </div>
                            
                            <div class="mb-2">
                                <b>Nro. Documento:</b>
                                <div class="ms-2"><?= $persona->documento ?></div>
                            </div>
                        </div>
                        
                    </div>
                    
                    <!-- Columna derecha: Información médica -->
                    <div class="col-12 ms-3">
                        <h6 class="mb-2 text-primary"><b>INFORMACIÓN MÉDICA</b></h6>
                        
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
                                <p class="mb-2">
                                    <?php if (count($condicionesActivas) == 0) {
                                        echo '<span class="ms-2">Sin datos</span>';
                                    } else {
                                        foreach ($condicionesActivas as $condicionActiva) {
                                            if (!isset($condicionActiva->codigoSnomed)) continue;
                                            echo '<span class="badge border border-info text-info me-1">' . strtoupper($condicionActiva->codigoSnomed->term) . '</span>';
                                        }
                                    } ?>
                                </p>
                            </div>
                            <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
                                <h6 class="mb-1 text-decoration-underline">DIAGNÓSTICOS CRÓNICOS</h6>
                                <p class="mb-2">
                                    <?php if (!isset($condicionesCronicas) || count($condicionesCronicas) == 0) {
                                        echo '<span class="ms-2">Sin datos</span>';
                                    } else {
                                        foreach ($condicionesCronicas as $condicionCronica) {
                                            if (!isset($condicionCronica->codigoSnomed)) continue;
                                            echo '<span class="badge border border-warning text-warning me-1">' . strtoupper($condicionCronica->codigoSnomed->term) . '</span>';
                                        }
                                    } ?>
                                </p>
                            </div>
                            <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
                                <h6 class="mb-1 text-decoration-underline">ALERGIAS</h6>
                                <p class="mb-2">
                                    <?php if (count($hallazgos) == 0) {
                                        echo '<span class="ms-2">Sin datos</span>';
                                    } else {
                                        foreach ($hallazgos as $hallazgo) {
                                            echo '<span class="badge border border-warning text-warning me-1">' . strtoupper($hallazgo->codigoSnomed->term) . '</span>';
                                        }
                                    } ?>
                                </p>
                            </div>
                            <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
                                <h6 class="mb-1 text-decoration-underline">ANTECEDENTES</h6>
                                <p class="mb-2">
                                    <?php if (count($antecedentes_personales) == 0 && count($antecedentes_familiares) == 0) {
                                        echo '<span class="ms-2">Sin datos</span>';
                                    } else {
                                        foreach ($antecedentes_personales as $antecedente) {
                                            echo '<span class="badge border border-gray text-gray me-1">' . strtoupper($antecedente->snomedSituacion->term) . '</span>';
                                        }
                                        foreach ($antecedentes_familiares as $antecedente) {
                                            echo '<span class="badge border border-gray text-gray me-1">' . strtoupper($antecedente->snomedSituacion->term) . '</span>';
                                        }
                                    } ?>
                                </p>
                            </div>
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


<!-- Segunda fila: Historia (ocupa todo el ancho) -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="header-title">
                    <h4 class="card-title">Historia</h4>
                </div>
            </div>
            <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if (count($historial) == 0) {
                            echo '<li><p class="ms-2">Sin historial</p></li>';
                        } ?>

                        <?php foreach ($historial as $key => $historia) { 
                            $fechaFormateada = TimelineHelper::formatTimelineDate($historia['fecha']);
                            $tiempoRelativo = TimelineHelper::getRelativeTime($historia['fecha']);
                        ?>
                            <li class="list-group-item p-0">
                                <div class="row g-0">
                                    <!-- Columna de fecha (10% del ancho) -->
                                    <div class="col-1 d-flex flex-column justify-content-center align-items-center bg-light border-end border-2 py-3" style="min-width: 80px;">
                                        <div class="fw-bold fs-5 text-dark"><?= $fechaFormateada['day'] ?></div>
                                        <div class="text-muted small text-uppercase"><?= $fechaFormateada['month'] ?></div>
                                        <div class="text-muted small"><?= $fechaFormateada['year'] ?></div>
                                        <div class="text-primary small fw-bold mt-1"><?= $tiempoRelativo ?></div>
                                    </div>
                                    
                                    <!-- Contenido del timeline (80% del ancho) -->
                                    <div class="col-10 p-3" style="min-width: 200px;">
                                        <?php //yii\widgets\Pjax::begin(['id' => 'pjax-' . $key, 'enablePushState' => false, 'enableReplaceState' => false]) 
                                        ?>
                                        <?php if ($historia['tipo_historia'] == 'Turno') { ?>
                                            <?= $this->render('_timeline_turno', ['historia' => $historia, 'pase_previo' => $pase_previo, 'persona' => $persona]); ?>
                                        <?php } ?>
                                        
                                        <?php if ($historia['tipo_historia'] == 'Consulta') { ?>
                                            <?= $this->render('_timeline_consulta_ambulatoria', ['historia' => $historia, 'persona' => $persona]); ?>
                                        <?php } ?>

                                        <?php if ($historia['tipo_historia'] == 'Guardia') { ?>
                                            <?= $this->render('_timeline_guardia', ['historia' => $historia, 'persona' => $persona]); ?>
                                        <?php } ?>

                                        <?php if ($historia['tipo_historia'] == 'Internacion') { ?>
                                            <?= $this->render('_timeline_internacion', ['historia' => $historia]); ?>
                                        <?php } ?>

                                        <?php if ($historia['tipo_historia'] == 'EncuestaParchesMamarios') { ?>
                                            <?= $this->render('_timeline_encuesta_parches_mamarios', ['historia' => $historia]); ?>
                                        <?php } ?>

                                        <?php if ($historia['tipo_historia'] == 'DocumentoExterno') { ?>
                                            <?= $this->render('_timeline_documento_externo', ['historia' => $historia]); ?>
                                        <?php } ?>

                                        <?php if ($historia['tipo_historia'] == 'EstudiosImagenes') { ?>
                                            <?= $this->render('_timeline_pentalogic', ['historia' => $historia, 'persona' => $persona]); ?>
                                        <?php } ?>

                                        <?php if ($historia['tipo_historia'] == 'EstudiosLab') { ?>
                                            <?= $this->render('_timeline_sianlabs', ['historia' => $historia]); ?>
                                        <?php } ?>

                                        <?php if ($historia['tipo_historia'] == 'Forms') { ?>
                                            <?= $this->render('_timeline_forms', ['historia' => $historia]); ?>
                                        <?php } ?>

                                        <?php //yii\widgets\Pjax::end() 
                                        ?>
                                    </div>
                                    
                                    <!-- Columna de botones (10% del ancho) -->
                                    <div class="col-1 d-flex flex-column justify-content-center align-items-center bg-light border-start border-2 py-3" style="min-width: 60px;">
                                        <?php
                                        // Determinar qué botón mostrar según el tipo de historia
                                        $boton = '';
                                        switch ($historia['tipo_historia']) {
                                            case 'Turno':
                                                $boton = '<button class="btn btn-sm btn-outline-primary" title="Ver detalle del turno">
                                                    <i class="bi bi-eye"></i>
                                                </button>';
                                                break;
                                                
                                            case 'Consulta':
                                                // Verificar si es del usuario actual y reciente
                                                if ($historia['id_rr_hh'] == Yii::$app->user->getIdRecursoHumano()) {
                                                    $fechaConsulta = new DateTime($historia['fecha']);
                                                    $fechaHoy = new DateTime();
                                                    $interval = $fechaConsulta->diff($fechaHoy);
                                                    
                                                    if ($interval->days <= 7) {
                                                        if (strpos($historia['tipo'], '999') !== false) {
                                                            $boton = '<a href="' . \yii\helpers\Url::to(['consultas/update', 'id_consulta' => $historia['parent_id'], 'id_persona' => $persona->id_persona]) . '" class="btn btn-sm btn-outline-warning" title="Editar Consulta">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>';
                                                        } else {
                                                            $boton = '<a href="' . \yii\helpers\Url::to(['consultas/continuar-consulta', 'id_consulta' => $historia['id'], 'continuar' => $historia['tipo'], 'id_persona' => $persona->id_persona]) . '" class="btn btn-sm btn-outline-primary" title="Continuar Consulta">
                                                                <i class="bi bi-arrow-right"></i>
                                                            </a>';
                                                        }
                                                    } else {
                                                        // Determinar URL según el servicio
                                                        $url_view = ($historia['servicio'] == 'ODONTOLOGIA') ? 
                                                            \yii\helpers\Url::toRoute('consulta-odontologia/detalle') : 
                                                            \yii\helpers\Url::toRoute('consultas/view');
                                                        
                                                        $boton = '<button class="btn btn-sm btn-outline-info" title="Ver detalle de la consulta" 
                                                            data-bs-toggle="modal" data-bs-target="#modal_detail_consulta" 
                                                            data-bs-consulta_id="' . $historia['parent_id'] . '" 
                                                            data-bs-consulta_detalle_url="' . $url_view . '">
                                                            <i class="bi bi-eye"></i>
                                                        </button>';
                                                    }
                                                } else {
                                                    // Determinar URL según el servicio
                                                    $url_view = ($historia['servicio'] == 'ODONTOLOGIA') ? 
                                                        \yii\helpers\Url::toRoute('consulta-odontologia/detalle') : 
                                                        \yii\helpers\Url::toRoute('consultas/view');
                                                    
                                                    $boton = '<button class="btn btn-sm btn-outline-info" title="Ver detalle de la consulta" 
                                                        data-bs-toggle="modal" data-bs-target="#modal_detail_consulta" 
                                                        data-bs-consulta_id="' . $historia['parent_id'] . '" 
                                                        data-bs-consulta_detalle_url="' . $url_view . '">
                                                        <i class="bi bi-eye"></i>
                                                    </button>';
                                                }
                                                break;
                                                
                                            case 'Guardia':
                                                $boton = '<button class="btn btn-sm btn-outline-danger" title="Ver detalle de guardia">
                                                    <i class="bi bi-eye"></i>
                                                </button>';
                                                break;
                                                
                                            case 'Internacion':
                                                $boton = '<button class="btn btn-sm btn-outline-secondary" title="Ver detalle de internación">
                                                    <i class="bi bi-eye"></i>
                                                </button>';
                                                break;
                                                
                                            case 'Forms':
                                                $boton = '<a href="' . $historia['resumen'] . '" class="btn btn-sm btn-outline-success" title="Ver formulario" target="_blank">
                                                    <i class="bi bi-file-text"></i>
                                                </a>';
                                                break;
                                                
                                            case 'EstudiosImagenes':
                                                $boton = '<a href="' . $historia['resumen'] . '" class="btn btn-sm btn-outline-info" title="Ver imagen" target="_blank">
                                                    <i class="bi bi-image"></i>
                                                </a>';
                                                break;
                                                
                                            case 'EstudiosLab':
                                                $boton = '<button class="btn btn-sm btn-outline-success" title="Ver resultados de laboratorio">
                                                    <i class="bi bi-flask"></i>
                                                </button>';
                                                break;
                                                
                                            case 'DocumentoExterno':
                                                $boton = '<button class="btn btn-sm btn-outline-warning" title="Ver documento externo">
                                                    <i class="bi bi-file-earmark"></i>
                                                </button>';
                                                break;
                                                
                                            case 'EncuestaParchesMamarios':
                                                $boton = '<button class="btn btn-sm btn-outline-primary" title="Ver encuesta">
                                                    <i class="bi bi-clipboard-data"></i>
                                                </button>';
                                                break;
                                                
                                            default:
                                                $boton = '<button class="btn btn-sm btn-outline-secondary" title="Ver detalle">
                                                    <i class="bi bi-eye"></i>
                                                </button>';
                                                break;
                                        }
                                        echo $boton;
                                        ?>
                                    </div>
                                </div>
                            </li>
                        <?php } ?>
                    </ul>
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
            signosVitales: '<?= \yii\helpers\Url::to(['personas/signos-vitales', 'id' => $persona->id_persona]) ?>',
            formularioConsulta: '<?= Url::to(['paciente/formulario-consulta', 'id' => $persona->id_persona]) ?>'
        }
    };

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