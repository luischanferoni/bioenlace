<?php
/**
 * Vista parcial para el formulario de consulta / evolución.
 *
 * @var array $form {@see \frontend\components\Clinical\EncounterCaptureFormViewBuilder}
 */
use yii\helpers\Html;
use yii\helpers\Json;

$prefill = (string) $form['motivoPacientePrefill'];
?>
<form id="form-consulta-chat" method="POST" action="<?= Html::encode($form['urlGuardar']) ?>"
      data-stt-config="<?= Json::htmlEncode($form['sttClientConfig']) ?>"
      data-url-inicio="<?= Html::encode($form['urlInicio']) ?>"
      data-modo-captura="<?= Html::encode($form['modoCaptura']) ?>">
    <?= Html::hiddenInput('id_persona', $form['personaId']) ?>
    <?php if (!empty($form['idConfiguracion'])): ?>
        <?= Html::hiddenInput('id_configuracion', (int) $form['idConfiguracion']) ?>
    <?php endif; ?>
    <?php if (!empty($form['idConsulta'])): ?>
        <?= Html::hiddenInput('id_consulta', (int) $form['idConsulta']) ?>
    <?php endif; ?>
    <?php if (!empty($form['parent'])): ?>
        <?= Html::hiddenInput('parent', (string) $form['parent']) ?>
    <?php endif; ?>
    <?php if (!empty($form['parentId'])): ?>
        <?= Html::hiddenInput('parent_id', (int) $form['parentId']) ?>
    <?php endif; ?>
    <?php if ($prefill !== ''): ?>
    <div class="alert alert-info mb-3" id="motivos-paciente-resumen" role="status">
        <strong>Motivos informados por el paciente</strong>
        <p class="mb-0 mt-2 small"><?= nl2br(Html::encode($prefill)) ?></p>
    </div>
    <?php endif; ?>

    <div class="form-group mb-3" id="chat-form">
        <label for="chat-input" class="form-label">
            <strong><?= Html::encode($form['formLabel']) ?></strong>
        </label>
        <textarea
            class="form-control"
            id="chat-input"
            name="consulta_texto"
            lang="es-AR"
            rows="4"
            placeholder="<?= Html::encode($form['placeholder']) ?>"
            style="border-width: 2px; resize: vertical;"><?= $prefill !== '' ? Html::encode($prefill) : '' ?></textarea>
        <div class="d-flex flex-wrap gap-2 mt-2 align-items-center">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="encounter-dictate-btn" title="Dictar">
                <i class="bi bi-mic"></i> Dictar
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="capture-cancel-edit-btn" style="display: none;" title="Volver a la revisión sin cambios">
                <i class="bi bi-arrow-counterclockwise"></i> Cancelar edición
            </button>
        </div>
        <div id="encounter-stt-status" class="small mt-1 text-muted" role="status" aria-live="polite"></div>
    </div>

    <div class="float-end mb-3" id="analyze-btn">
        <button class="btn btn-outline-primary" type="button" id="analyze-consultation" title="<?= Html::encode($form['analyzeTitle']) ?>">
            <i class="bi bi-clipboard2-check"></i>&nbsp;&nbsp;<?= Html::encode($form['analyzeLabel']) ?>
        </button>
    </div>

    <div id="agent-response" class="mt-3" style="display: none;">
        <div id="capture-review-root"></div>
        <div id="response-content" class="d-none" aria-hidden="true"></div>
        <div id="capture-save-alert" class="alert alert-danger d-none mb-3" role="alert" aria-live="assertive"></div>
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

    <div id="context-buttons" class="mt-2" style="display: none;">
    </div>
</form>
<?= $this->render('_capture_review_templates') ?>
