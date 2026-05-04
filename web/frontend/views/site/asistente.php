<?php

use yii\helpers\Html;
use yii\helpers\Url;
use frontend\assets\BioenlaceApiClientAsset;

$this->title = 'Asistente';
$this->registerJsFile('@web/js/spa-navigation.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('@web/js/spa-home.js', ['depends' => [BioenlaceApiClientAsset::class, \yii\web\JqueryAsset::class]]);
$this->registerCssFile('@web/css/spa.css', ['depends' => [\yii\web\JqueryAsset::class]]);
?>

<div class="container-fluid px-0 spa-asistente-root">
    <div class="row g-0 flex-grow-1">
        <div class="col-12 d-flex flex-column spa-asistente-col">
            <div id="spa-chat-root" class="spa-chat-root d-flex flex-column">
                <div id="spa-chat-toolbar" class="spa-chat-toolbar w-100">
                    <!-- Botón y .dropdown-menu como hermanos directos (Bootstrap y bootstrap-custom.js esperan nextElementSibling). -->
                    <div class="dropdown position-static w-100 d-flex flex-column align-items-end">
                        <button
                            type="button"
                            id="spa-shortcuts-toggle-btn"
                            class="btn btn-outline-secondary btn-sm dropdown-toggle spa-chat-shortcuts-btn"
                            data-bs-toggle="dropdown"
                            data-bs-auto-close="outside"
                            data-bs-display="static"
                            aria-expanded="false"
                        >
                            Atajos
                        </button>
                        <div class="dropdown-menu w-100 p-3 shadow-sm mt-1 border-0 spa-chat-shortcuts-menu">
                            <div id="spa-shortcuts-content" class="overflow-auto" style="max-height: 50vh;">
                                <!-- Se llena dinámicamente con JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>

                <div id="spa-chat-messages" class="spa-chat-messages flex-grow-1 overflow-auto px-0">
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
            </div>

            <div id="spa-chat-composer" class="spa-chat-composer" aria-label="Escribir mensaje al asistente">
                <div class="spa-chat-input-wrap">
                    <textarea
                        id="spa-query-input"
                        class="form-control spa-chat-input spa-chat-input--bare border-2"
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
                <div class="text-muted small mt-2 mb-0 ms-3">Enter para enviar, Shift+Enter para salto de línea.</div>
            </div>
        </div>
    </div>
</div>

<!-- Contenedor de páginas del stack -->
<div id="spa-pages-container" class="spa-pages-container">
    <!-- Las páginas se agregarán dinámicamente aquí -->
</div>

