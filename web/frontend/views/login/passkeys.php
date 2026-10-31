<?php

use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

/** @var array $credentials */

$this->title = 'Passkeys';

$registrationOptionsUrl = Json::htmlEncode(Url::to(['/auth/passkey-registration-options']));
$registrationVerifyUrl = Json::htmlEncode(Url::to(['/auth/passkey-registration-verify']));
$revokeUrl = Json::htmlEncode(Url::to(['/auth/passkey-revoke']));
?>

<div class="container my-4">
    <h1>Passkeys</h1>
    <p class="text-muted">
        Ingresá a Bioenlace con tu huella, rostro o bloqueo de pantalla, sin contraseñas.
    </p>

    <div class="mb-4">
        <button type="button" class="btn btn-primary" id="passkey-register-button">
            Registrar una passkey nueva
        </button>
        <p id="passkey-register-message" class="text-danger mt-2" role="status" aria-live="polite"></p>
    </div>

    <?php if (empty($credentials)): ?>
        <p class="text-muted">Todavía no registraste ninguna passkey.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Creada</th>
                        <th>Último uso</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($credentials as $credential): ?>
                        <tr>
                            <td><?= Html::encode($credential['name']) ?></td>
                            <td><?= Yii::$app->formatter->asDatetime((int) $credential['created_at']) ?></td>
                            <td>
                                <?= $credential['last_used_at'] !== null
                                    ? Yii::$app->formatter->asDatetime((int) $credential['last_used_at'])
                                    : '—' ?>
                            </td>
                            <td class="text-end">
                                <button
                                    type="button"
                                    class="btn btn-outline-danger btn-sm passkey-revoke-button"
                                    data-credential-id-hash="<?= Html::encode($credential['credential_id_hash']) ?>"
                                    data-name="<?= Html::encode($credential['name']) ?>"
                                >
                                    Revocar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
$this->registerJs(<<<JS
(() => {
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
            throw new Error(data.message ?? 'No se pudo completar la operación.');
        }
        return data;
    };

    const registerButton = document.getElementById('passkey-register-button');
    const registerMessage = document.getElementById('passkey-register-message');

    registerButton?.addEventListener('click', async () => {
        registerMessage.textContent = '';
        if (!window.PublicKeyCredential || !navigator.credentials) {
            registerMessage.textContent = 'Este navegador no admite passkeys.';
            return;
        }

        registerButton.disabled = true;
        try {
            const optionsResponse = await post({$registrationOptionsUrl}, {});
            const credential = await navigator.credentials.create({
                publicKey: prepareOptions(optionsResponse.publicKey),
            });
            if (!credential) {
                throw new Error('No se creó la passkey.');
            }

            await post({$registrationVerifyUrl}, {
                challengeId: optionsResponse.challengeId,
                credential: serializeCredential(credential),
                name: 'Dispositivo nuevo',
            });
            window.location.reload();
        } catch (error) {
            registerMessage.textContent = error.message || 'No se pudo registrar la passkey.';
        } finally {
            registerButton.disabled = false;
        }
    });

    document.querySelectorAll('.passkey-revoke-button').forEach((button) => {
        button.addEventListener('click', async () => {
            const name = button.dataset.name;
            if (!window.confirm('¿Seguro que querés revocar la passkey "' + name + '"? Se cerrarán tus sesiones.')) {
                return;
            }

            button.disabled = true;
            try {
                const result = await post({$revokeUrl}, { credentialIdHash: button.dataset.credentialIdHash });
                window.location.assign(result.redirectUrl);
            } catch (error) {
                window.alert(error.message || 'No se pudo revocar la passkey.');
                button.disabled = false;
            }
        });
    });
})();
JS);
?>
