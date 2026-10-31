<?php

/**
 * @var string $identifier
 * @var string $note
 * @var bool $requested
 */

use yii\helpers\Html;
?>

<div class="container-fluid">
    <section class="login-content overflow-hidden">
        <div class="row no-gutters align-items-center bg-white">
            <div class="col-md-12 col-lg-6 align-self-center">
                <img
                    src="<?= Yii::getAlias('@web') ?>/images/logo.svg"
                    alt="Bioenlace"
                    class="login-brand-logo mb-4 mt-5"
                    width="240"
                    height="51"
                >
                <div class="row justify-content-center pt-3">
                    <div class="col-md-9">
                        <div class="card auth-card iq-auth-form">
                            <div class="card-body">
                                <h1 class="h2 mb-3">Recuperar acceso</h1>

                                <?php if ($requested): ?>
                                    <div class="alert alert-success" role="alert">
                                        Recibimos tu solicitud. Un operador la revisará y te contactará para
                                        completar la verificación de identidad.
                                    </div>
                                    <?= Html::a('Volver al inicio de sesión', ['/auth/login'], ['class' => 'btn btn-primary']) ?>
                                <?php else: ?>
                                    <p class="text-muted">
                                        Indicá tu usuario o e-mail. La recuperación es asistida: verificaremos tu
                                        identidad antes de restablecer el acceso.
                                    </p>
                                    <form method="post">
                                        <input type="hidden" name="<?= Yii::$app->request->csrfParam ?>" value="<?= Yii::$app->request->csrfToken ?>">
                                        <div class="mb-3">
                                            <label class="form-label" for="recovery-identifier">Usuario o e-mail</label>
                                            <input
                                                type="text"
                                                class="form-control"
                                                id="recovery-identifier"
                                                name="identifier"
                                                value="<?= Html::encode($identifier) ?>"
                                                required
                                                autocomplete="off"
                                            >
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label" for="recovery-note">Nota opcional</label>
                                            <textarea
                                                class="form-control"
                                                id="recovery-note"
                                                name="note"
                                                rows="3"
                                                maxlength="500"
                                            ><?= Html::encode($note) ?></textarea>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <?= Html::submitButton('Enviar solicitud', ['class' => 'btn btn-primary']) ?>
                                            <?= Html::a('Volver', ['/auth/login']) ?>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 pe-0">
                <div class="login-side-pattern" aria-hidden="true"></div>
            </div>
        </div>
    </section>
</div>
