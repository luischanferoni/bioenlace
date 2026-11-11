<?php

/**
 * @var \common\models\Platform\LoginForm|null $model
 * @var bool $passwordLoginEnabled
 * @var bool $enableAutoLogin
 */

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use yii\helpers\Url;

$showPasswordForm = isset($model) && ($passwordLoginEnabled ?? true);
?>

<div class="container-fluid">
    <section
        class="login-content overflow-hidden"
        data-qr-start-url="<?= Html::encode(Url::to(['/auth/qr-login-start'])) ?>"
        data-qr-status-url="<?= Html::encode(Url::to(['/auth/qr-login-status'])) ?>"
    >
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
                        <div class="card d-flex justify-content-center mb-0 auth-card iq-auth-form">
                            <div class="card-body text-center">
                                <h1 class="h2 mb-4">Ingresá a Bioenlace</h1>
                                <p class="mb-4">Ingresá con tu usuario y contraseña o confirmá el acceso desde la app.</p>

                                <button type="button" class="btn btn-outline-primary mb-3" id="qr-login-button" data-bs-toggle="modal" data-bs-target="#qr-login-modal">
                                    Ingresar con la app
                                </button>

                                <?php if ($showPasswordForm): ?>
                                    <hr class="my-4">
                                    <p class="text-muted small mb-3">
                                        Ingresá con tu usuario y contraseña.
                                    </p>

                                    <?php $form = ActiveForm::begin([
                                        'id' => 'login-form',
                                        'options' => ['autocomplete' => 'off', 'class' => 'text-start'],
                                        'validateOnBlur' => false,
                                        'fieldConfig' => ['template' => "{input}\n{error}"],
                                    ]) ?>

                                    <?= $form->field($model, 'username')
                                        ->textInput(['placeholder' => $model->getAttributeLabel('username'), 'autocomplete' => 'off']) ?>

                                    <?= $form->field($model, 'password')
                                        ->passwordInput(['placeholder' => $model->getAttributeLabel('password'), 'autocomplete' => 'off']) ?>

                                    <?= ($enableAutoLogin ?? false) ? $form->field($model, 'rememberMe')->checkbox(['value' => true]) : '' ?>

                                    <div class="d-flex justify-content-center">
                                        <?= Html::submitButton('Ingresar', ['class' => 'btn btn-outline-primary mb-4']) ?>
                                    </div>

                                    <?php ActiveForm::end() ?>
                                <?php endif; ?>

                                <div class="registration-block">
                                    <?= Html::a('¿No podés ingresar? Recuperar acceso', ['/auth/recovery-request']) ?>
                                </div>
                                <div class="registration-block mt-2">
                                    <?= Html::a('Crear cuenta', ['/alta']) ?>
                                </div>
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

<div class="modal fade" id="qr-login-modal" tabindex="-1" aria-labelledby="qr-login-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="qr-login-title">Ingresá con la app</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body text-center">
                <p class="text-muted small">Abrí Bioenlace en tu teléfono y escaneá este código para confirmar tu acceso.</p>
                <div id="qr-login-canvas" class="d-flex justify-content-center my-3"></div>
                <p id="qr-login-message" class="text-danger" role="status" aria-live="polite"></p>
            </div>
        </div>
    </div>
</div>
