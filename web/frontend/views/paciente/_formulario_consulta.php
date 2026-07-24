<?php
/**
 * Vista parcial para el formulario de consulta
 * @var $paciente \common\models\Person\Persona
 * @var $idConfiguracion int|null
 * @var $idConsulta int|string|null
 * @var $parent string|null
 * @var $parentId int|string|null
 * @var $motivoPacientePrefill string Resumen IA de motivos cargados por el paciente (pre-atención)
 */
$idConsulta = $idConsulta ?? null;
$parent = $parent ?? null;
$parentId = $parentId ?? null;
$motivoPacientePrefill = trim((string) ($motivoPacientePrefill ?? ''));
$sttClientConfig = \common\components\Platform\Ai\SpeechToText\SttConfigService::clientSnapshot();
?>

<?php
use yii\helpers\Url;
use yii\helpers\Html;
?>
<form id="form-consulta-chat" method="POST" action="<?= Url::to(['/api/v1/clinical/encounter/guardar']) ?>"
      data-stt-config="<?= Html::encode(json_encode($sttClientConfig, JSON_UNESCAPED_UNICODE)) ?>">
    <?= Html::hiddenInput('id_persona', $paciente->id_persona) ?>
    <?php if (!empty($idConfiguracion)): ?>
        <?= Html::hiddenInput('id_configuracion', (int) $idConfiguracion) ?>
    <?php endif; ?>
    <?php if ($idConsulta !== null && $idConsulta !== '' && (int) $idConsulta > 0): ?>
        <?= Html::hiddenInput('id_consulta', (int) $idConsulta) ?>
    <?php endif; ?>
    <?php if ($parent !== null && $parent !== ''): ?>
        <?= Html::hiddenInput('parent', (string) $parent) ?>
    <?php endif; ?>
    <?php if ($parentId !== null && $parentId !== ''): ?>
        <?= Html::hiddenInput('parent_id', (int) $parentId) ?>
    <?php endif; ?>
    <?php if ($motivoPacientePrefill !== ''): ?>
    <div class="alert alert-info mb-3" id="motivos-paciente-resumen" role="status">
        <strong>Motivos informados por el paciente</strong>
        <p class="mb-0 mt-2 small"><?= nl2br(Html::encode($motivoPacientePrefill)) ?></p>
    </div>
    <?php endif; ?>

    <!-- Formulario de entrada -->
    <div class="form-group mb-3" id="chat-form">
        <label for="chat-input" class="form-label">
            <strong>Formulario de consulta</strong>
        </label>
        <textarea 
            class="form-control" 
            id="chat-input" 
            name="consulta_texto"
            lang="es-AR"
            rows="4" 
            placeholder="Escriba o dicte los detalles de la consulta. El asistente verificará motivos, evolución, diagnóstico, prácticas, etc."
            style="border-width: 2px; resize: vertical;"><?= $motivoPacientePrefill !== '' ? Html::encode($motivoPacientePrefill) : '' ?></textarea>
        <div class="d-flex flex-wrap gap-2 mt-2 align-items-center">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="encounter-dictate-btn" title="Dictado por voz">
                <i class="bi bi-mic"></i> Dictar
            </button>
            <button type="button" class="btn btn-sm btn-outline-warning" id="encounter-stt-server-btn" title="Transcribir audio con servidor">
                <i class="bi bi-cloud-arrow-up"></i> Transcribir en servidor
            </button>
        </div>
        <div id="encounter-stt-status" class="small mt-1 text-muted" role="status" aria-live="polite"></div>
    </div>

    <!-- Boton analysis de consulta -->
    <div class="float-end mb-3" id="analyze-btn">
        <button class="btn btn-outline-primary" type="button" id="analyze-consultation" title="Analizar la consulta con IA">
            <i class="bi bi-clipboard2-check"></i>&nbsp;&nbsp;Analizar consulta
        </button>
    </div>

    <!-- Área de revisión tras analizar -->
    <div id="agent-response" class="mt-3" style="display: none;">
        <div id="capture-review-root"></div>
        <div id="response-content" class="d-none" aria-hidden="true"></div>
        <div class="d-flex flex-wrap gap-2 justify-content-end mb-3" id="capture-review-actions" style="display: none;">
            <button class="btn btn-outline-secondary" type="button" id="capture-edit-btn">
                <i class="bi bi-pencil"></i>&nbsp;Editar texto
            </button>
            <button class="btn btn-outline-danger" type="button" id="capture-discard-btn">
                <i class="bi bi-x-circle"></i>&nbsp;Eliminar
            </button>
            <button class="btn btn-primary" type="button" id="send-message" disabled title="Guardar en la historia clínica">
                <i class="bi bi-check-circle"></i>&nbsp;Guardar
            </button>
        </div>
    </div>

    <!-- Botones de contexto (se mostrarán dinámicamente) -->
    <div id="context-buttons" class="mt-2" style="display: none;">
        <!-- Los botones se generarán dinámicamente -->
    </div>
</form>
