<?php

use yii\helpers\Url;
use yii\helpers\Html;

use frontend\assets\SesionOperativaPostloginWizardAsset;

SesionOperativaPostloginWizardAsset::register($this);

$this->title = 'Configurar sesión';

$urlEstablecerSesionOperativa = Url::to(['/api/v1/sesion-operativa/establecer'], true);
?>

<div class="py-3 py-md-4">
    <header class="mb-4">
        <h1 class="h3 mb-2"><?= Html::encode($this->title) ?></h1>
        <p class="text-body-secondary mb-0">Elegí efector, área y servicio para continuar.</p>
    </header>

    <div id="dynamic-form" class="form-wizard">

        <ul id="top-tab-list" class="nav nav-pills nav-fill gap-2 p-0 list-unstyled mb-0" role="tablist">
            <li id="paso1" class="nav-item active" role="presentation">
                <a href="#" class="wizard_tab_link text-center" tabindex="-1" aria-disabled="true">
                    <span class="visually-hidden">Paso 1:</span> Efector
                </a>
            </li>
            <li id="paso2" class="nav-item" role="presentation">
                <a href="#" class="wizard_tab_link text-center" tabindex="-1" aria-disabled="true">
                    <span class="visually-hidden">Paso 2:</span> Área
                </a>
            </li>
            <li id="paso3" class="nav-item" role="presentation">
                <a href="#" class="wizard_tab_link text-center" tabindex="-1" aria-disabled="true">
                    <span class="visually-hidden">Paso 3:</span> Servicio
                </a>
            </li>
        </ul>

        <fieldset class="formwizard_fieldset border-0 p-0 m-0" id="formwizard_efectores">
            <?= $this->render('indexuserefector'); ?>
            <div class="d-flex justify-content-end gap-2 mt-3 pt-2 border-top">
                <button type="button" name="next" class="btn btn-primary next action-button" value="Siguiente" disabled>Siguiente</button>
            </div>
        </fieldset>

        <fieldset class="formwizard_fieldset border-0 p-0 m-0" id="formwizard_encounter">
            <?= $this->render('inicio_encounter_class'); ?>
            <div class="d-flex justify-content-end gap-2 mt-3 pt-2 border-top">
                <button type="button" name="previous" class="btn btn-outline-secondary previous action-button-previous" value="Anterior">Anterior</button>
                <button type="button" name="next" class="btn btn-primary next a-servicio action-button" value="Siguiente" disabled>Siguiente</button>
            </div>
        </fieldset>

        <fieldset class="formwizard_fieldset border-0 p-0 m-0" id="formwizard_servicios">
            <?= $this->render('inicio_servicio'); ?>
            <div class="d-flex justify-content-end gap-2 mt-3 pt-2 border-top">
                <button type="button" name="previous" class="btn btn-outline-secondary previous action-button-previous" value="Previous">Anterior</button>
                <button type="button" name="next" class="btn btn-primary next action-button" value="Siguiente" disabled>Finalizar</button>
            </div>
        </fieldset>

    </div>
</div>

<?= Html::tag('div', '', [
    'id' => 'sesion-operativa-wizard-config',
    'class' => 'd-none',
    'data-establecer-url' => $urlEstablecerSesionOperativa,
]) ?>
