<?php
/**
 * Vista parcial para el formulario de consulta
 * @var $paciente \common\models\Persona
 */
?>

<?php
use yii\helpers\Url;
use yii\helpers\Html;
?>
<form id="form-consulta-chat" method="POST" action="<?= Url::to(['/api/v1/consulta/guardar']) ?>">
    <?= Html::hiddenInput('id_persona', $paciente->id_persona) ?>
    <!-- Formulario de entrada -->
    <div class="form-group mb-3" id="chat-form">
        <label for="chat-input" class="form-label">
            <strong>Formulario de consulta</strong>
        </label>
        <textarea 
            class="form-control" 
            id="chat-input" 
            name="consulta_texto"
            rows="4" 
            placeholder="Escriba aquí los detalles de la consulta. El asistente verificará que tenga todos los datos necesarios: motivos de consulta, evolución, diagnóstico, prácticas, etc."
            style="border-width: 2px; resize: vertical;"></textarea>
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
</form>
