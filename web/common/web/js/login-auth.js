(() => {
    const login = document.querySelector('.login-content[data-qr-start-url]');
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

    const passkeyErrorMessage = (error) => {
        switch (error?.name) {
            case 'NotAllowedError':
                return 'No se pudo completar el acceso. Puede que hayas cancelado la solicitud, que no haya una passkey disponible para Bioenlace o que haya vencido. Intentá nuevamente o ingresá con la app.';
            case 'AbortError':
                return 'La solicitud de passkey se interrumpió. Intentá nuevamente.';
            case 'SecurityError':
                return 'No se pudo validar la passkey desde este sitio. Revisá que estés en la dirección habitual de Bioenlace.';
            case 'NotSupportedError':
                return 'Este dispositivo no ofrece una passkey compatible. Probá con otro método de acceso.';
            default:
                return 'No se pudo iniciar sesión con la passkey. Intentá nuevamente o ingresá con la app.';
        }
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
            let credential;
            try {
                credential = await navigator.credentials.get({
                    publicKey: prepareOptions(optionsResponse.publicKey),
                });
            } catch (error) {
                throw new Error(passkeyErrorMessage(error));
            }
            if (!credential) {
                throw new Error('No se seleccionó una passkey. Intentá nuevamente o ingresá con la app.');
            }

            const result = await post(login.dataset.passkeyVerifyUrl, {
                challengeId: optionsResponse.challengeId,
                credential: serializeCredential(credential),
            });
            window.location.assign(result.redirectUrl);
        } catch (error) {
            passkeyMessage.textContent = error instanceof Error
                ? error.message
                : passkeyErrorMessage(error);
        } finally {
            passkeyButton.disabled = false;
        }
    });

    let qrPollTimer = null;
    let qrAttempt = 0;
    const stopQrPolling = () => {
        if (qrPollTimer) {
            clearInterval(qrPollTimer);
            qrPollTimer = null;
        }
    };

    qrModal?.addEventListener('shown.bs.modal', async () => {
        const currentAttempt = ++qrAttempt;
        qrCanvas.replaceChildren();
        qrMessage.textContent = '';
        stopQrPolling();

        try {
            const start = await post(login.dataset.qrStartUrl, {});
            if (currentAttempt !== qrAttempt) {
                return;
            }
            const qrDocument = new DOMParser().parseFromString(start.qrSvg, 'image/svg+xml');
            const svg = qrDocument.documentElement;
            if (svg.localName !== 'svg' || qrDocument.querySelector('parsererror')) {
                throw new Error('El servidor devolvió un código QR inválido.');
            }
            svg.setAttribute('width', '200');
            svg.setAttribute('height', '200');
            svg.style.padding = '20px';
            svg.style.backgroundColor = '#ffffff';
            qrCanvas.replaceChildren(document.importNode(svg, true));

            qrPollTimer = setInterval(async () => {
                try {
                    const status = await post(login.dataset.qrStatusUrl, { challengeId: start.challengeId });
                    if (currentAttempt !== qrAttempt) {
                        stopQrPolling();
                        return;
                    }
                    if (status.status === 'approved' && status.redirectUrl) {
                        stopQrPolling();
                        window.location.assign(status.redirectUrl);
                    } else if (status.status === 'expired' || status.status === 'not_found') {
                        stopQrPolling();
                        qrMessage.textContent = 'El código expiró. Cerrá y volvé a intentarlo.';
                    }
                } catch (error) {
                    stopQrPolling();
                    if (currentAttempt !== qrAttempt) {
                        return;
                    }
                    qrMessage.textContent = error instanceof Error
                        ? error.message
                        : 'No se pudo verificar el acceso.';
                }
            }, 1500);
        } catch (error) {
            if (currentAttempt !== qrAttempt) {
                return;
            }
            qrMessage.textContent = error instanceof Error
                ? error.message
                : 'No se pudo iniciar el acceso con la app.';
        }
    });

    qrModal?.addEventListener('hidden.bs.modal', () => {
        qrAttempt++;
        stopQrPolling();
        qrCanvas.replaceChildren();
    });
})();
