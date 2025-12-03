<?php
/**
 * Vista parcial para el formulario de consulta
 * @var $paciente \common\models\Persona
 */
?>

<!-- Formulario de entrada -->
<div class="form-group mb-3" id="chat-form">
    <label for="chat-input" class="form-label">
        <strong>Formulario de consulta</strong>
    </label>
    <textarea 
        class="form-control" 
        id="chat-input" 
        rows="4" 
        placeholder="Escriba aquí los detalles de la consulta. El asistente verificará que tenga todos los datos necesarios: motivos de consulta, evolución, diagnóstico, prácticas, etc."
        style="border-width: 2px; resize: vertical;"
    ></textarea>
</div>

<!-- Contenedor para mostrar el texto procesado -->
<div id="texto-procesado-container" class="mt-2 mb-3" style="display: none;">
    <div class="card border-info">
        <div class="card-header bg-info text-white py-2">
            <small class="mb-0">
                <i class="bi bi-check-circle me-2"></i>
                <strong>Texto Procesado</strong>
            </small>
        </div>
        <div class="card-body p-2">
            <p id="texto-procesado-content" class="mb-0 small text-muted" style="white-space: pre-wrap;"></p>
        </div>
    </div>
</div>

<!-- Boton analysis de consulta -->
<div class="float-end mb-3" id="analyze-btn">
    <button class="btn btn-outline-primary" type="button" id="analyze-consultation">
        <i class="bi bi-search"></i>&nbsp;&nbsp;Analizar Consulta
    </button>
</div>

<!-- Área de respuesta del agente -->
<div id="agent-response" class="mt-3" style="display: none;">
    <div class="card border-0">
        <div class="card-body m-1">
            <!-- Contenido de la respuesta -->
            <div id="response-content"></div>
        </div>
    </div>
    <!-- Confirmacion de consulta -->
    <div class="float-end mb-3" id="confirm-section" style="display: none;">
        <button class="btn btn-primary" type="button" id="send-message" disabled>
            <i class="bi bi-check-circle"></i>&nbsp;&nbsp;Confirmar Consulta
        </button>
    </div>
</div>

<!-- Botones de contexto (se mostrarán dinámicamente) -->
<div id="context-buttons" class="mt-2" style="display: none;">
    <!-- Los botones se generarán dinámicamente -->
</div>
