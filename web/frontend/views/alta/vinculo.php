<?php

/**
 * Página del teléfono: ingreso del código, lanzamiento de Didit y resultado.
 *
 * Toda la lógica de estado vive en `frontend/controllers/AltaController`; acá sólo se
 * pinta el paso que corresponde al estado del vínculo.
 *
 * @var array $page view model armado por el controlador
 */

use yii\helpers\Html;

$state = (string) ($page['state'] ?? '');
$urls = $page['urls'] ?? [];
$notice = $page['notice'] ?? null;
$retryUrl = $page['retry_url'] ?? null;
?>

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-12 col-sm-10 col-md-7 col-lg-5">
            <div
                class="card auth-card"
                data-alta-telefono
                data-codigo-url="<?= Html::encode($urls['codigo'] ?? '') ?>"
                data-retry-url="<?= Html::encode($retryUrl ?? '') ?>"
                data-retry-seconds="<?= (int) ($page['retry_seconds'] ?? 5) ?>"
            >
                <div class="card-body text-center">

                    <?php if ($notice !== null && $notice !== ''): ?>
                        <div class="alert alert-warning" role="alert"><?= Html::encode($notice) ?></div>
                    <?php endif; ?>

                    <?php if ($state === 'CLAIMED'): ?>
                        <h1 class="h4 mb-3">Ingresá el código</h1>
                        <p class="text-muted">
                            Escribí el código de 6 caracteres que aparece en la computadora
                            donde empezaste el alta.
                        </p>
                        <?php
                        $form = \yii\bootstrap5\ActiveForm::begin([
                            'id' => 'alta-telefono-codigo',
                            'action' => $urls['codigo'] ?? '',
                            'options' => ['class' => 'text-start', 'novalidate' => true],
                        ]);
                        ?>
                        <div class="mb-3">
                            <label class="form-label" for="alta-codigo">Código</label>
                            <input
                                type="text"
                                class="form-control form-control-lg text-center text-uppercase"
                                id="alta-codigo"
                                name="code"
                                inputmode="text"
                                autocomplete="one-time-code"
                                autocapitalize="characters"
                                spellcheck="false"
                                maxlength="7"
                                required
                                autofocus
                            >
                        </div>
                        <div class="alert alert-danger d-none" role="alert" data-code-error></div>
                        <button type="submit" class="btn btn-primary w-100">Continuar</button>
                        <?php \yii\bootstrap5\ActiveForm::end(); ?>

                    <?php elseif ($state === 'AUTHORIZED'): ?>
                        <h1 class="h4 mb-3">Código correcto</h1>
                        <p class="text-muted">
                            Ahora verificá tu identidad. Se abre una pantalla de Didit para
                            escanear tu documento y tomar una selfie.
                        </p>
                        <?= Html::beginForm($urls['didit'] ?? '', 'post', ['class' => 'd-grid']) ?>
                            <button type="submit" class="btn btn-primary">Verificar mi identidad</button>
                        <?= Html::endForm() ?>

                    <?php elseif ($state === 'VERIFICATION_STARTED'): ?>
                        <h1 class="h4 mb-3">Confirmando la verificación</h1>
                        <div class="spinner-border text-primary my-3" role="status">
                            <span class="visually-hidden">Confirmando</span>
                        </div>
                        <p class="text-muted">
                            Estamos confirmando el resultado con Didit. No cierres esta página.
                        </p>
                        <?php if ($retryUrl === null || $retryUrl === ''): ?>
                            <p class="text-muted small">
                                Si ya terminaste, volvé a comprobar el resultado.
                            </p>
                            <?= Html::a(
                                'Volver a comprobar',
                                ['/alta/telefono-retorno', 'id' => $page['pairing_id'] ?? ''],
                                ['class' => 'btn btn-outline-primary']
                            ) ?>
                        <?php endif; ?>

                    <?php elseif ($state === 'VERIFIED' || $state === 'ACCOUNT_CREATED'): ?>
                        <h1 class="h4 mb-3">Listo</h1>
                        <p class="text-muted">
                            Tu identidad quedó verificada. Volvé a la computadora: ahí
                            termina el alta.
                        </p>
                        <p class="text-muted small">Ya podés cerrar esta página.</p>

                    <?php elseif ($state === 'FAILED'): ?>
                        <h1 class="h4 mb-3">No pudimos verificar tu identidad</h1>
                        <p class="text-muted">
                            Didit no aprobó la verificación. Volvé a la computadora y empezá
                            el alta de nuevo.
                        </p>
                        <?= Html::a('Volver a empezar', ['/alta'], ['class' => 'btn btn-primary']) ?>

                    <?php elseif ($state === 'EXPIRED' || $state === 'CANCELLED'): ?>
                        <h1 class="h4 mb-3">El trámite venció</h1>
                        <p class="text-muted">
                            Pasó demasiado tiempo o se canceló desde la computadora.
                            Volvé a la computadora y empezá de nuevo.
                        </p>
                        <?= Html::a('Volver a empezar', ['/alta'], ['class' => 'btn btn-primary']) ?>

                    <?php else: ?>
                        <h1 class="h4 mb-3">Vínculo sin usar</h1>
                        <p class="text-muted">
                            Este enlace no está en un paso válido. Volvé a la computadora y
                            empezá el alta de nuevo.
                        </p>
                        <?= Html::a('Volver a empezar', ['/alta'], ['class' => 'btn btn-primary']) ?>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>
