<?php

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Acciones';
$this->registerJsFile('@web/js/spa-navigation.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('@web/js/spa-home.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerCssFile('@web/css/spa.css', ['depends' => [\yii\web\JqueryAsset::class]]);
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10">
            <!-- Header -->
            <div class="text-center mb-5">
                <h1 class="display-5 fw-light text-dark mb-2">VitaMind</h1>
                <p class="lead text-muted">¿En qué puedo ayudarte?</p>
            </div>

            <!-- Textarea estilo ChatGPT -->
            <div class="mb-4">
                <textarea 
                    id="spa-query-input" 
                    class="form-control form-control-lg" 
                    rows="4" 
                    placeholder="Escribe tu consulta aquí... Ejemplo: 'Necesito buscar una persona' o 'Quiero ver los reportes disponibles'"
                    style="resize: none;"
                ></textarea>
                <div class="d-flex justify-content-end mt-3">
                    <button id="spa-send-btn" class="btn btn-primary btn-lg d-flex align-items-center gap-2">
                        <span class="spa-send-icon">→</span>
                        <span class="spa-send-text">Enviar</span>
                        <span class="spa-spinner d-none">
                            <span class="spinner-border spinner-border-sm" role="status"></span>
                        </span>
                    </button>
                </div>
            </div>

            <!-- Área de respuesta de IA -->
            <div id="spa-response-section" class="mb-4 d-none">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div id="spa-explanation" class="mb-3"></div>
                        <div id="spa-actions" class="row g-3"></div>
                    </div>
                </div>
            </div>

            <!-- Acciones comunes iniciales -->
            <div id="spa-common-actions" class="mt-5">
                <h3 class="text-center mb-4 fw-semibold text-dark">Acciones Comunes</h3>
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

<script>
// Inicializar variables globales para la SPA
window.spaConfig = {
    baseUrl: '<?= rtrim(Yii::$app->urlManager->createAbsoluteUrl(['/']), '/') ?>',
    csrfToken: '<?= Yii::$app->request->csrfToken ?>'
};
</script>

