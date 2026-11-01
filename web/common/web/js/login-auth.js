(() => {
    const login = document.querySelector('.login-content[data-passkey-options-url]');
    if (!login) {
        return;
    }

    const passkeyButton = document.getElementById('passkey-login-button');
    const passkeyMessage = document.getElementById('passkey-login-message');
    const qrModal = document.getElementById('qr-login-modal');
    const qrCanvas = document.getElementById('qr-login-canvas');
    const qrMessage = document.getElementById('qr-login-message');
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
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
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
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify(body),
        });
        const responseText = await response.text();
        let data;
        try {
            data = JSON.parse(responseText);
        } catch {
            if (response.redirected) {
                throw new Error(`El servidor redirigió la solicitud en lugar de responder JSON (HTTP ${response.status}).`);
            }
            const contentType = response.headers.get('content-type') ?? '';
            if (!contentType.includes('application/json')) {
                throw new Error(`El servidor respondió algo distinto de JSON (HTTP ${response.status}). Revisá la ruta, la sesión y los errores del servidor.`);
            }
            throw new Error(`El servidor devolvió un JSON inválido (HTTP ${response.status}).`);
        }
        if (!response.ok) {
            throw new Error(
                typeof data?.message === 'string'
                    ? data.message
                    : `No se pudo completar el inicio de sesión (HTTP ${response.status}).`
            );
        }
        return data;
    };

    passkeyButton?.addEventListener('click', async () => {
        passkeyMessage.textContent = '';
        if (!window.PublicKeyCredential || !navigator.credentials) {
            passkeyMessage.textContent = 'Este navegador no admite passkeys. Probá desde un dispositivo compatible o recuperá el acceso.';
            return;
        }

        passkeyButton.disabled = true;
        try {
            const optionsResponse = await post(login.dataset.passkeyOptionsUrl, {});
            const credential = await navigator.credentials.get({
                publicKey: prepareOptions(optionsResponse.publicKey),
            });
            if (!credential) {
                throw new Error('No se seleccionó una passkey.');
            }

            const result = await post(login.dataset.passkeyVerifyUrl, {
                challengeId: optionsResponse.challengeId,
                credential: serializeCredential(credential),
            });
            window.location.assign(result.redirectUrl);
        } catch (error) {
            passkeyMessage.textContent = error instanceof Error
                ? error.message
                : 'No se pudo iniciar sesión. Volvé a intentarlo.';
        } finally {
            passkeyButton.disabled = false;
        }
    });

    let qrPollTimer = null;
    const stopQrPolling = () => {
        if (qrPollTimer) {
            clearInterval(qrPollTimer);
            qrPollTimer = null;
        }
    };

    qrModal?.addEventListener('shown.bs.modal', async () => {
        qrCanvas.replaceChildren();
        qrMessage.textContent = '';
        stopQrPolling();

        if (typeof QRCode === 'undefined' || typeof QRCode.toCanvas !== 'function') {
            qrMessage.textContent = 'No se pudo cargar el código QR. Probá con una passkey.';
            return;
        }

        try {
            const start = await post(login.dataset.qrStartUrl, {});
            const canvas = document.createElement('canvas');
            await QRCode.toCanvas(canvas, start.qrPayload, {
                width: 220,
                color: {
                    dark: '#000000',
                    light: '#ffffff',
                },
            });
            qrCanvas.appendChild(canvas);

            qrPollTimer = setInterval(async () => {
                try {
                    const status = await post(login.dataset.qrStatusUrl, { challengeId: start.challengeId });
                    if (status.status === 'approved' && status.redirectUrl) {
                        stopQrPolling();
                        window.location.assign(status.redirectUrl);
                    } else if (status.status === 'expired' || status.status === 'not_found') {
                        stopQrPolling();
                        qrMessage.textContent = 'El código expiró. Cerrá y volvé a intentarlo.';
                    }
                } catch (error) {
                    stopQrPolling();
                    qrMessage.textContent = error instanceof Error
                        ? error.message
                        : 'No se pudo verificar el acceso.';
                }
            }, 1500);
        } catch (error) {
            qrMessage.textContent = error instanceof Error
                ? error.message
                : 'No se pudo iniciar el acceso con la app.';
        }
    });

    qrModal?.addEventListener('hidden.bs.modal', stopQrPolling);
})();
