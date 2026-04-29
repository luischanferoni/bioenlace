<?php

use yii\helpers\Html;
use yii\helpers\Url;
use frontend\assets\BioenlaceApiClientAsset;

$this->title = 'Asistente';
$this->registerJsFile('@web/js/spa-navigation.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('@web/js/spa-home.js', ['depends' => [BioenlaceApiClientAsset::class, \yii\web\JqueryAsset::class]]);
$this->registerCssFile('@web/css/spa.css', ['depends' => [\yii\web\JqueryAsset::class]]);
?>

<div class="container-fluid py-3" style="min-height: 100vh;">
    <div class="row g-3 align-items-stretch">
        <div class="col-12">
            <div class="card shadow-sm d-flex flex-column position-relative" style="height: calc(100vh - 2rem);">
                <div class="card-header bg-white d-flex align-items-center justify-content-between">
                    <div class="fw-semibold"></div>
                    <div class="dropdown">
                        <button
                            type="button"
                            id="spa-shortcuts-toggle-btn"
                            class="btn btn-outline-secondary btn-sm dropdown-toggle"
                            data-bs-toggle="dropdown"
                            data-bs-auto-close="outside"
                            aria-expanded="false"
                        >
                            Atajos
                        </button>
                        <div class="dropdown-menu dropdown-menu-end p-3 shadow-sm" style="width: min(720px, 92vw);">
                            <div class="fw-semibold mb-1">Atajos</div>
                            <div class="text-muted small mb-2">Acciones disponibles según tus permisos.</div>
                            <div id="spa-shortcuts-content" class="overflow-auto" style="max-height: 50vh;">
                                <!-- Se llena dinámicamente con JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body flex-grow-1 overflow-auto bg-light" id="spa-chat-messages">
                    <!-- Respuesta del asistente (se muestra/oculta desde JS) -->
                    <div id="spa-response-section" class="d-none">
                        <div class="d-flex justify-content-start mb-3">
                            <div class="bg-white border rounded-4 p-3 w-100">
                                <div id="spa-explanation" class="mb-3"></div>
                                <div id="spa-actions" class="row g-3"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Estado inicial -->
                    <div class="d-flex justify-content-center" id="spa-chat-empty-hint">
                        <div class="text-muted small text-center">
                            Escribí una consulta para comenzar. Ejemplo: “Necesito buscar una persona” o “Quiero ver los reportes disponibles”.
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-white">
                    <div class="spa-chat-input-wrap">
                        <textarea
                            id="spa-query-input"
                            class="form-control spa-chat-input"
                            rows="2"
                            placeholder="Escribe tu mensaje…"
                        ></textarea>
                        <button type="button" id="spa-send-btn" class="btn btn-primary spa-chat-send-btn d-flex align-items-center justify-content-center">
                            <span class="spa-send-text">→</span>
                            <span class="spa-spinner d-none">
                                <span class="spinner-border spinner-border-sm" role="status"></span>
                            </span>
                        </button>
                    </div>
                    <div class="text-muted small mt-2">Enter para enviar, Shift+Enter para salto de línea.</div>
                </div>
            </div>
    </div>
</div>

<!-- Contenedor de páginas del stack -->
<div id="spa-pages-container" class="spa-pages-container">
    <!-- Las páginas se agregarán dinámicamente aquí -->
</div>

