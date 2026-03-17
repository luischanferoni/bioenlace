<?php

use yii\helpers\Html;
use yii\helpers\Url;
use common\models\Turno;
use common\models\Persona;
use common\helpers\TimelineHelper;

$fechaAnterior = date('Y-m-d', strtotime($fecha . ' -1 day'));
$fechaSiguiente = date('Y-m-d', strtotime($fecha . ' +1 day'));
$tituloFecha = TimelineHelper::formatearFechaAmigable($fecha);
$esHoy = ($fecha == date('Y-m-d'));
$urlInicioDatos = Url::to(['site/inicio-datos'], true);

$this->title = 'Inicio - Turnos';
?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><?= Html::encode($tituloFecha) ?></h2>
        <div class="btn-group" role="group">
            <a href="<?= Url::to(['site/index', 'fecha' => $fechaAnterior]) ?>" class="btn btn-outline-secondary me-3">
                <i class="bi bi-chevron-left"></i> Anterior
            </a>
            <a href="<?= Url::to(['site/index', 'fecha' => date('Y-m-d')]) ?>" class="btn btn-outline-secondary">
                Hoy
            </a>
            <a href="<?= Url::to(['site/index', 'fecha' => $fechaSiguiente]) ?>" class="btn btn-outline-secondary ms-3">
                Siguiente <i class="bi bi-chevron-right"></i>
            </a>
        </div>
    </div>

    <?php if ($esHoy): ?>
        <!-- Card de prueba: Siguiente turno -->
        <div class="row mb-4">
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card h-100 spa-card shadow-sm"
                     data-card-id="next-appointment-card"
                     data-expandable="false"
                     data-full-page="true"
                     data-action-type="appointment"
                     data-action-url="">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-calendar-check text-primary me-2" style="font-size: 1.5rem;"></i>
                            <h6 class="card-title text-primary fw-semibold mb-0">Siguiente Turno</h6>
                        </div>
                        <p class="card-text text-muted small mb-2">
                            <strong>Paciente:</strong> [Nombre del Paciente]<br>
                            <strong>Fecha:</strong> [Fecha del turno]<br>
                            <strong>Hora:</strong> [Hora del turno]
                        </p>
                        <small class="text-muted">Haz clic para ver la historia clínica</small>
                        <div class="spa-card-expand-content d-none mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Contenedor: se llena por AJAX según encounter (turnos, internados o guardias) -->
    <div id="inicio-datos-container">
        <div id="inicio-datos-loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="mt-2 text-muted">Cargando datos...</p>
        </div>
        <div id="inicio-datos-content" class="d-none"></div>
        <div id="inicio-datos-error" class="d-none alert alert-warning"></div>
    </div>

<?php if ($esHoy): ?>
<!-- Contenedor de páginas del stack -->
<div id="spa-pages-container" class="spa-pages-container">
    <!-- Las páginas se agregarán dinámicamente aquí -->
</div>

<?php
$this->registerJsFile('@web/js/spa-navigation.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('@web/js/spa-home.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerCssFile('@web/css/spa.css', ['depends' => [\yii\web\JqueryAsset::class]]);
?>
<?php endif; ?>

<?php
$urlInternacionView = Url::to(['internacion/view'], true);
$urlGuardiaIndex = Url::to(['guardia/index'], true);
$urlInternacionRonda = Url::to(['internacion/ronda'], true);
$this->registerJs(<<<JS
(function() {
    var container = document.getElementById('inicio-datos-content');
    var loading = document.getElementById('inicio-datos-loading');
    var errorEl = document.getElementById('inicio-datos-error');
    var fecha = '{$fecha}';
    var urlInicioDatos = '{$urlInicioDatos}';
    var urlInternacionView = '{$urlInternacionView}';
    var urlGuardiaIndex = '{$urlGuardiaIndex}';
    var urlInternacionRonda = '{$urlInternacionRonda}';

    function escapeHtml(s) {
        if (s == null) return '';
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    function renderTurnos(data) {
        if (!data.length) {
            container.innerHTML = '<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No hay turnos programados para esta fecha.</div>';
            return;
        }
        var html = '<div class="row">';
        data.forEach(function(t) {
            var nombre = (t.paciente && t.paciente.nombre_completo) ? t.paciente.nombre_completo : 'Sin paciente';
            var servicio = t.servicio || 'Sin servicio';
            var estadoClass = (t.estado === 'PENDIENTE') ? 'warning' : 'secondary';
            var estadoLabel = t.estado_label || t.estado || '';
            var obs = (t.observaciones) ? '<div class="mb-2"><strong><i class="bi bi-chat-left-text me-2"></i>Observaciones:</strong> <small class="text-muted">' + escapeHtml(t.observaciones) + '</small></div>' : '';
            html += '<div class="col-md-6 col-lg-4 mb-3">' +
                '<div class="card h-100 shadow-sm">' +
                '<div class="card-body">' +
                '<h5 class="card-title"><i class="bi bi-person-circle text-primary me-2"></i>' + escapeHtml(nombre) + '</h5>' +
                '<div class="mb-2"><strong><i class="bi bi-clock me-2"></i>Hora:</strong> ' + escapeHtml(t.hora) + '</div>' +
                '<div class="mb-2"><strong><i class="bi bi-hospital me-2"></i>Servicio:</strong> ' + escapeHtml(servicio) + '</div>' + obs +
                '<div class="mt-3"><span class="badge bg-' + estadoClass + '">' + escapeHtml(estadoLabel) + '</span></div>' +
                '</div></div></div>';
        });
        html += '</div>';
        container.innerHTML = html;
    }

    function renderInternados(data) {
        if (!data.length) {
            container.innerHTML = '<div class="d-flex align-items-center p-3 mb-2 bg-soft-gray rounded"><div class="ms-3"><h5 class="mb-0">No se encontraron resultados.</h5></div></div>';
            return;
        }
        var html = '<div class="card"><div class="card-header"><h4 class="mb-0">Pacientes internados</h4></div><div class="card-body">';
        data.forEach(function(i) {
            var urlView = urlInternacionView + '?id=' + i.id;
            html += '<div class="d-flex align-items-center p-3 mb-2 bg-soft-gray rounded">' +
                '<div class="ms-3" style="flex:1;">' +
                '<h5 class="mb-0">' + escapeHtml(i.nombre) + '</h5>' +
                '<p class="mb-1"><strong>Piso:</strong> ' + escapeHtml(i.piso) + ' <strong>Sala:</strong> ' + escapeHtml(i.sala) + ' <strong>Cama:</strong> ' + escapeHtml(i.cama) + '</p>' +
                '<a href="' + urlView + '" class="p-2 btn btn-success btn-sm mt-2">Atender Paciente</a>' +
                '</div></div>';
        });
        html += '</div><div class="card-footer"><a href="' + urlInternacionRonda + '" class="btn btn-primary">Ver ronda de internación</a></div></div>';
        container.innerHTML = html;
    }

    function renderGuardias(data) {
        if (!data.length) {
            container.innerHTML = '<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Ningún ingreso registrado hasta el momento.</div>';
            return;
        }
        var html = '<div class="card"><div class="card-header bg-light"><h4 class="mb-0">Últimos ingresos en guardia</h4></div><div class="card-body">';
        data.forEach(function(g) {
            var docLabel = (g.tipo_documento) ? escapeHtml(g.tipo_documento) + ': ' : '';
            html += '<div class="d-flex align-items-center p-3 mb-2 bg-soft-gray rounded">' +
                '<div class="ms-3" style="flex:1;">' +
                '<h5 class="mb-0">' + escapeHtml(g.nombre_completo) + '</h5>' +
                '<p class="mb-1">' + docLabel + escapeHtml(g.documento || '') + '</p>' +
                '</div>' +
                '<span class="btn btn-dark btn-sm">Atender</span>' +
                '</div>';
        });
        html += '</div><div class="card-footer"><a href="' + urlGuardiaIndex + '" class="btn btn-success float-end">Ver todos los ingresos activos</a></div></div>';
        container.innerHTML = html;
    }

    function showError(msg) {
        errorEl.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>' + escapeHtml(msg);
        errorEl.classList.remove('d-none');
    }

    fetch(urlInicioDatos + '?fecha=' + encodeURIComponent(fecha), {
        method: 'GET',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        loading.classList.add('d-none');
        if (res.error) {
            showError(res.error);
            return;
        }
        var kind = res.kind;
        var data = res.data || [];
        if (kind === 'turnos') {
            renderTurnos(data);
        } else if (kind === 'internados') {
            renderInternados(data);
        } else if (kind === 'guardias') {
            renderGuardias(data);
        } else {
            showError(res.error || 'No hay datos configurados para este tipo de atención.');
            return;
        }
        container.classList.remove('d-none');
    })
    .catch(function(err) {
        loading.classList.add('d-none');
        showError('Error al cargar los datos. Intente de nuevo.');
    });
})();
JS
);
?>
