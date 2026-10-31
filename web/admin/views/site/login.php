<?php

/**
 * @var \common\models\Platform\LoginForm|null $model
 * @var bool $passwordLoginEnabled
 * @var bool $enableAutoLogin
 */

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

$optionsUrl = Json::htmlEncode(Url::to(['/auth/passkey-login-options']));
$verifyUrl = Json::htmlEncode(Url::to(['/auth/passkey-login-verify']));
$qrStartUrl = Json::htmlEncode(Url::to(['/auth/qr-login-start']));
$qrStatusUrl = Json::htmlEncode(Url::to(['/auth/qr-login-status']));
$showPasswordForm = isset($model) && ($passwordLoginEnabled ?? true);
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
                        <div class="card d-flex justify-content-center mb-0 auth-card iq-auth-form">
                            <div class="card-body text-center">
                                <h1 class="h2 mb-4">Ingresá a Bioenlace</h1>
                                <p class="mb-4">Usá la passkey guardada en este dispositivo o en tu administrador de contraseñas.</p>

                                <button type="button" class="btn btn-primary mb-3" id="passkey-login-button">
                                    Ingresar con passkey
                                </button>
                                <p id="passkey-login-message" class="text-danger" role="status" aria-live="polite"></p>

                                <button type="button" class="btn btn-outline-primary mb-3" id="qr-login-button" data-bs-toggle="modal" data-bs-target="#qr-login-modal">
                                    Ingresar con la app
                                </button>

                                <?php if ($showPasswordForm): ?>
                                    <hr class="my-4">
                                    <p class="text-muted small mb-3">
                                        ¿Todavía no registraste una passkey? Podés ingresar con tu contraseña y luego
                                        registrarla desde tu perfil.
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

<?php
$this->registerJs(<<<JS
(() => {
    const button = document.getElementById('passkey-login-button');
    const message = document.getElementById('passkey-login-message');
    const optionsUrl = {$optionsUrl};
    const verifyUrl = {$verifyUrl};
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    const decodeBase64Url = (value) => {
        const base64 = value.replace(/-/g, '+').replace(/_/g, '/');
        const binary = atob(base64 + '='.repeat((4 - base64.length % 4) % 4));
        return Uint8Array.from(binary, (character) => character.charCodeAt(0)).buffer;
    };

    const encodeBase64Url = (buffer) => {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        bytes.forEach((byte) => { binary += String.fromCharCode(byte); });
        return btoa(binary).replace(/\\+/g, '-').replace(/\\//g, '_').replace(/=+$/g, '');
    };

    const prepareOptions = (options) => {
        options.challenge = decodeBase64Url(options.challenge);
        if (options.user?.id) {
            options.user.id = decodeBase64Url(options.user.id);
        }
        (options.allowCredentials ?? []).forEach((item) => { item.id = decodeBase64Url(item.id); });
        (options.excludeCredentials ?? []).forEach((item) => { item.id = decodeBase64Url(item.id); });
        return options;
    };

    const serializeCredential = (credential) => {
        const response = credential.response;
        const data = {
            id: credential.id,
            rawId: encodeBase64Url(credential.rawId),
            type: credential.type,
            response: {
                clientDataJSON: encodeBase64Url(response.clientDataJSON),
            },
            clientExtensionResults: credential.getClientExtensionResults(),
        };
        ['attestationObject', 'authenticatorData', 'signature', 'userHandle'].forEach((key) => {
            if (response[key] instanceof ArrayBuffer) {
                data.response[key] = encodeBase64Url(response[key]);
            }
        });
        if (typeof response.getTransports === 'function') {
            data.response.transports = response.getTransports();
        }
        return data;
    };

    const post = async (url, body) => {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify(body),
        });
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.message ?? 'No se pudo completar el inicio de sesión.');
        }
        return data;
    };

    button.addEventListener('click', async () => {
        message.textContent = '';
        if (!window.PublicKeyCredential || !navigator.credentials) {
            message.textContent = 'Este navegador no admite passkeys. Probá desde un dispositivo compatible o recuperá el acceso.';
            return;
        }

        button.disabled = true;
        try {
            const optionsResponse = await post(optionsUrl, {});
            const credential = await navigator.credentials.get({
                publicKey: prepareOptions(optionsResponse.publicKey),
            });
            if (!credential) {
                throw new Error('No se seleccionó una passkey.');
            }

            const result = await post(verifyUrl, {
                challengeId: optionsResponse.challengeId,
                credential: serializeCredential(credential),
            });
            window.location.assign(result.redirectUrl);
        } catch (error) {
            message.textContent = error.message || 'No se pudo iniciar sesión. Volvé a intentarlo.';
        } finally {
            button.disabled = false;
        }
    });

    const qrModal = document.getElementById('qr-login-modal');
    const qrCanvas = document.getElementById('qr-login-canvas');
    const qrMessage = document.getElementById('qr-login-message');
    let qrPollTimer = null;

    const stopQrPolling = () => {
        if (qrPollTimer) {
            clearInterval(qrPollTimer);
            qrPollTimer = null;
        }
    };

    qrModal?.addEventListener('shown.bs.modal', async () => {
        qrCanvas.innerHTML = '';
        qrMessage.textContent = '';
        stopQrPolling();

        if (typeof QRCode === 'undefined') {
            qrMessage.textContent = 'No se pudo cargar el código QR. Probá con una passkey.';
            return;
        }

        try {
            const start = await post({$qrStartUrl}, {});
            new QRCode(qrCanvas, {
                text: start.qrPayload,
                width: 220,
                height: 220,
                colorDark: '#000000',
                colorLight: '#ffffff',
            });

            qrPollTimer = setInterval(async () => {
                try {
                    const status = await post({$qrStatusUrl}, { challengeId: start.challengeId });
                    if (status.status === 'approved' && status.redirectUrl) {
                        stopQrPolling();
                        window.location.assign(status.redirectUrl);
                    } else if (status.status === 'expired' || status.status === 'not_found') {
                        stopQrPolling();
                        qrMessage.textContent = 'El código expiró. Cerrá y volvé a intentarlo.';
                    }
                } catch (error) {
                    stopQrPolling();
                    qrMessage.textContent = error.message || 'No se pudo verificar el acceso.';
                }
            }, 1500);
        } catch (error) {
            qrMessage.textContent = error.message || 'No se pudo iniciar el acceso con la app.';
        }
    });

    qrModal?.addEventListener('hidden.bs.modal', stopQrPolling);
})();
JS);
?>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js" defer></script>

