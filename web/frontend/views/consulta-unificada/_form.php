<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\Url;
use yii\helpers\Json;

/* @var $this yii\web\View */
/* @var $configuracionPasos array */
/* @var $modelConsulta common\models\Consulta */
/* @var $paciente common\models\Persona */
?>

<style>
    .form-wizard-container {
        display: flex;
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .progress-container {
        margin-bottom: 30px;
    }
    
    .step-indicator {
        display: flex;
        flex-direction: column;
        flex: 0 0 250px;
        position: relative;
        width: 250px;
        padding-right: 20px;
        border-right: 2px solid #e9ecef;
    }

    .step-item {
        position: relative;
        display: flex;
        align-items: center;
        margin-bottom: 30px;
        cursor: pointer;
    }

    .step-item::before {
        content: '';
        position: absolute;
        left: 15px;
        top: 0;
        bottom: -30px;
        width: 2px;
        background: #e9ecef;
        z-index: -1;
    }

    .step-item:last-child::before {
        display: none;
    }

    .step-circle {
        flex-shrink: 0;
        border-radius: 50%;
        width: 35px;
        height: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #e9ecef;
        background: white;
        margin-right: 10px;
        transition: all 0.3s ease;
    }

    .step-item.active .step-circle {
        background: #007bff;
        border-color: #007bff;
        color: white;
    }

    .step-item.completed .step-circle {
        background: #28a745;
        border-color: #28a745;
        color: white;
    }

    .step-label {
        font-size: 14px;
        font-weight: 500;
    }
    
    .step-content {
        display: none;
        padding: 20px;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        background: white;
        margin-bottom: 20px;
        min-height: 300px;
    }
    
    .step-content.active {
        display: block;
    }
    
    .step-content.loading {
        text-align: center;
        padding: 40px;
    }
    
    .navigation-buttons {
        display: flex;
        justify-content: space-between;
        margin-top: 20px;
        padding: 20px 0;
        border-top: 1px solid #dee2e6;
    }
    
    .save-progress-btn {
        background: #17a2b8;
        border-color: #17a2b8;
    }
    
    .final-submit-btn {
        background: #28a745;
        border-color: #28a745;
    }
    
    .alert-save-status {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
    }
    
    .step-header {
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 15px;
        margin-bottom: 20px;
    }
    
    .step-header h5 {
        color: #495057;
        margin-bottom: 5px;
    }
    
    .step-body {
        min-height: 200px;
    }
    
    #steps-container {
        flex: 1;
        padding-left: 30px;
    }

    #form-wizard {	
        flex: 1;
    }

    .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid #f3f3f3;
        border-top: 3px solid #007bff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

<div class="form-wizard-container">
    <div class="progress-container">
        <h4 class="text-center mb-4">Progreso de la Consulta</h4>
        
        <!-- Indicador de pasos -->
        <div class="step-indicator">
            <?php foreach ($configuracionPasos as $index => $paso): ?>
                <div class="step-item <?= $index === 0 ? 'active' : '' ?>" data-step="<?= $index ?>">
                    <div class="step-circle"><?= $index + 1 ?></div>                    
                    <div class="step-label"><?= Html::encode($paso['titulo']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Contenedor del formulario principal -->
    <div id="form-wizard">
        <?php $form = ActiveForm::begin([
            'id' => 'form-wizard-unified',
            'options' => ['class' => 'form-wizard-form']
        ]); ?>
        
        <!-- Campos ocultos para el estado -->
        <?= Html::hiddenInput('id_consulta', $modelConsulta->id_consulta ?? '') ?>
        <?= Html::hiddenInput('id_persona', $paciente->id_persona) ?>
        <?= Html::hiddenInput('paso_actual', 0) ?>
        <?= Html::hiddenInput('pasos_completados', '[]') ?>
        <?= Html::hiddenInput('parent', $modelConsulta->parent_class ?? '') ?>
        <?= Html::hiddenInput('parent_id', $modelConsulta->parent_id ?? '') ?>
        
        <!-- Contenedor dinámico para los pasos -->
        <div id="steps-container">
            <?php foreach ($configuracionPasos as $index => $paso): ?>
                <div class="step-content <?= $index === 0 ? 'active' : '' ?>" id="step-<?= $index ?>">
                    <div class="step-header">
                        <h5><?= Html::encode($paso['titulo']) ?></h5>                        
                    </div>
                    
                    <div class="step-body" id="step-body-<?= $index ?>">
                        <?php if ($index === 0): ?>
                            <!-- El primer paso se carga inmediatamente -->
                            <div class="loading">
                                <div class="loading-spinner"></div>
                                <p class="mt-2">Cargando formulario...</p>
                            </div>
                        <?php else: ?>
                            <!-- Los demás pasos se cargarán dinámicamente -->
                            <div class="text-center text-muted">
                                <i class="bi bi-arrow-right-circle fs-1"></i>
                                <p>Este paso se cargará cuando llegues aquí</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Botones de navegación -->
        <button type="button" class="btn btn-secondary float-start" id="btn-prev" style="display: none;">
            <i class="bi bi-arrow-left"></i> Anterior
        </button>
        <button type="button" class="btn btn-primary float-end" id="btn-next">
            Guardar y Continuar <i class="bi bi-arrow-right"></i>
        </button>
        <button type="submit" class="btn btn-success float-end" id="btn-final-submit" style="display: none;">
            <i class="bi bi-check-circle"></i> Finalizar Consulta
        </button>
        
        <?php ActiveForm::end(); ?>
    </div>
</div>

<!-- Alertas para el estado de guardado -->
<div id="alert-container" class="alert-save-status"></div>

<?php
// Configuración para el JavaScript externo
$configuracionJson = Json::encode($configuracionPasos);
$urls = [];
foreach ($configuracionPasos as $paso) {
    $urls[] = Url::toRoute(trim($paso['url']));
}
$urlsJson = Json::encode($urls);
$totalPasos = count($configuracionPasos);

// Registrar el archivo JavaScript externo
$this->registerJsFile('@web/js/consulta-unificada.js', ['depends' => [\yii\web\JqueryAsset::class]]);

// Configuración global para el JavaScript
$js = <<<JS
// Configuración global
window.FormWizardConfig = {
    pasos: {$configuracionJson},
    urls: {$urlsJson},
    totalPasos: {$totalPasos},
    pasoActual: 0,
    pasosCompletados: [],
    estadoGuardado: {},
    cargando: false
};

// Debugging
console.log('FormWizardConfig creado:', window.FormWizardConfig);

// Inicializar inmediatamente (para modales)
setTimeout(function() {
    if (typeof inicializarWizard === 'function') {
        inicializarWizard();
    }
}, 200);
JS;

$this->registerJs($js);

// Registrar el componente de chat
$this->registerJsFile('@web/js/chat-component.js', ['depends' => [\yii\web\JqueryAsset::class]]);
?>

<!-- React Chat Container -->
<div id="react-chat-container" 
     data-react-chat="true"
     data-consulta-id="<?= $model->id_consulta ?? 0 ?>"
     data-user-id="<?= Yii::$app->user->id ?>"
     data-user-role="<?= Yii::$app->user->identity->role ?>"
     data-api-base-url="/api"
     style="margin-top: 20px;">
</div>

<!-- Cargar React solo si está disponible -->
<?php if (file_exists(Yii::getAlias('@web/dist/assets/main.js'))): ?>
    <script type="module" src="<?= Yii::getAlias('@web/dist/assets/main.js') ?>"></script>
<?php else: ?>
    <!-- Fallback al chat JavaScript vanilla -->
    <div id="chat-container" 
         data-consulta-id="<?= $model->id_consulta ?? 0 ?>"
         data-user-id="<?= Yii::$app->user->id ?>"
         data-user-role="<?= Yii::$app->user->identity->role ?>"
         style="margin-top: 20px;">
    </div>
    <script>
        // Inicializar chat vanilla como fallback
        if (typeof window.initChat === 'function') {
            window.initChat('chat-container', <?= $model->id_consulta ?? 0 ?>, <?= Yii::$app->user->id ?>, '<?= Yii::$app->user->identity->role ?>');
        }
    </script>
<?php endif; ?>
