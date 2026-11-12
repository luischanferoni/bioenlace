<?php

/**
 * Página de verificación de identidad del canal WhatsApp.
 *
 * @var array $page view model armado por el controlador
 */

use yii\helpers\Html;

$state = (string) ($page['state'] ?? '');
$notice = $page['notice'] ?? null;
$diditUrl = $page['didit_url'] ?? null;
$retryUrl = $page['retry_url'] ?? null;
?>

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-12 col-sm-10 col-md-7 col-lg-5">
            <div class="card auth-card">
                <div class="card-body text-center">

                    <?php if ($notice !== null && $notice !== ''): ?>
                        <div class="alert alert-warning" role="alert"><?= Html::encode($notice) ?></div>
                    <?php endif; ?>

                    <?php if ($state === 'READY' || $state === 'RETRY'): ?>
                        <h1 class="h4 mb-3">Verificá tu identidad</h1>
                        <p class="text-muted">
                            Para usar Bioenlace por WhatsApp necesitamos confirmar que este número
                            es tuyo. Se abre una pantalla de Didit para escanear tu documento y
                            tomar una selfie.
                        </p>
                        <?php if ($diditUrl !== null): ?>
                            <?= Html::beginForm($diditUrl, 'post', ['class' => 'd-grid']) ?>
                                <button type="submit" class="btn btn-primary">Verificar mi identidad</button>
                            <?= Html::endForm() ?>
                        <?php else: ?>
                            <p class="text-muted small">El enlace venció. Escribinos de nuevo por WhatsApp.</p>
                        <?php endif; ?>

                    <?php elseif ($state === 'LINKED' || $state === 'ALREADY_LINKED'): ?>
                        <h1 class="h4 mb-3">Listo</h1>
                        <p class="text-muted">
                            Tu WhatsApp quedó vinculado a Bioenlace. Volvé a la conversación y
                            seguí escribiendo por ahí.
                        </p>
                        <p class="text-muted small">Ya podés cerrar esta página.</p>

                    <?php elseif ($state === 'NO_ACCOUNT'): ?>
                        <h1 class="h4 mb-3">No encontramos tu cuenta</h1>
                        <p class="text-muted">
                            No hay una cuenta Bioenlace con los datos que verificaste. Instalá la
                            app Bioenlace, cargá este teléfono en tu perfil y volvé a escribirnos.
                        </p>

                    <?php elseif ($state === 'FAILED'): ?>
                        <h1 class="h4 mb-3">No pudimos verificar tu identidad</h1>
                        <p class="text-muted">
                            Didit no aprobó la verificación o los datos no coinciden. Podés
                            intentarlo de nuevo o usar la app Bioenlace.
                        </p>
                        <?php if ($retryUrl !== null): ?>
                            <?= Html::a('Intentar de nuevo', $retryUrl, ['class' => 'btn btn-outline-primary']) ?>
                        <?php endif; ?>

                    <?php elseif ($state === 'NOT_FOUND'): ?>
                        <h1 class="h4 mb-3">El enlace venció</h1>
                        <p class="text-muted">
                            Este enlace ya no es válido o pasó demasiado tiempo. Escribinos de
                            nuevo por WhatsApp para recibir uno nuevo.
                        </p>

                    <?php else: ?>
                        <h1 class="h4 mb-3">No pudimos continuar</h1>
                        <p class="text-muted">
                            Ocurrió un problema al confirmar la verificación. Escribinos de nuevo
                            por WhatsApp o usá la app Bioenlace.
                        </p>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>
