<?php

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Asistente';
$this->registerJsFile('@web/js/spa-navigation.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('@web/js/spa-home.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerCssFile('@web/css/spa.css', ['depends' => [\yii\web\JqueryAsset::class]]);
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10">

            <!-- Chat del asistente -->
            <div class="mb-4">
                <textarea 
                    id="spa-query-input" 
                    class="form-control form-control-lg" 
                    rows="3" 
                    placeholder="Escribe lo que necesitas... Ejemplo: 'Necesito buscar una persona' o 'Quiero ver los reportes disponibles'"
                    style="resize: none;"
                ></textarea>
                <div class="d-flex justify-content-end flex-wrap gap-2 mt-3">
                    <button type="button" id="spa-what-can-i-do-btn" class="btn btn-outline-secondary btn-lg">
                        ¿Qué puedo hacer?
                    </button>
                    <button type="button" id="spa-send-btn" class="btn btn-primary btn-lg d-flex align-items-center gap-2">
                        <span class="spa-send-icon">→</span>
                        <span class="spa-send-text">Enviar</span>
                        <span class="spa-spinner d-none">
                            <span class="spinner-border spinner-border-sm" role="status"></span>
                        </span>
                    </button>
                </div>
            </div>

            <!-- Respuesta del asistente -->
            <div id="spa-response-section" class="mb-4 d-none">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div id="spa-explanation" class="mb-3"></div>
                        <div id="spa-actions" class="row g-3"></div>
                    </div>
                </div>
            </div>

            <!-- Sugerencias iniciales -->
            <div id="spa-common-actions" class="mt-5">
                <h3 class="text-center mb-4 fw-semibold text-dark">Sugerencias del asistente</h3>
                <div id="spa-common-actions-grid" class="row g-3">
                    <!-- Se llenará dinámicamente con JavaScript -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Contenedor de páginas del stack -->
<div id="spa-pages-container" class="spa-pages-container">
    <!-- Las páginas se agregarán dinámicamente aquí -->
</div>

