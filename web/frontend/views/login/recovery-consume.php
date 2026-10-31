<?php

/**
 * @var string $code
 * @var string|null $error
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
                                <h1 class="h2 mb-3">Ingresar código de recuperación</h1>
                                <p class="text-muted">
                                    Ingresá el código de un solo uso que te brindó el operador. Después deberás
                                    registrar una passkey nueva.
                                </p>

                                <?php if ($error !== null): ?>
                                    <div class="alert alert-danger" role="alert"><?= Html::encode($error) ?></div>
                                <?php endif; ?>

                                <form method="post">
                                    <input type="hidden" name="<?= Yii::$app->request->csrfParam ?>" value="<?= Yii::$app->request->csrfToken ?>">
                                    <div class="mb-3">
                                        <label class="form-label" for="recovery-code">Código</label>
                                        <input
                                            type="text"
                                            class="form-control text-uppercase"
                                            id="recovery-code"
                                            name="code"
                                            value="<?= Html::encode($code) ?>"
                                            required
                                            autocomplete="off"
                                            style="letter-spacing: 0.2em;"
                                        >
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <?= Html::submitButton('Continuar', ['class' => 'btn btn-primary']) ?>
                                        <?= Html::a('Volver', ['/auth/login']) ?>
                                    </div>
                                </form>
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
