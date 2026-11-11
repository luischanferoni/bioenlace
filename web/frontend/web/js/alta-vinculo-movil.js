/**
 * Página del teléfono del alta web.
 *
 * Dos comportamientos: enviar el código de emparejamiento sin recargar y reintentar
 * solo el resultado de Didit mientras el servidor lo siga confirmando.
 */
(function () {
    'use strict';

    var card = document.querySelector('[data-alta-telefono]');
    if (!card) {
        return;
    }

    var retryUrl = card.dataset.retryUrl || '';
    var retrySeconds = Math.max(2, parseInt(card.dataset.retrySeconds, 10) || 5);

    if (retryUrl) {
        window.setTimeout(function () {
            window.location.replace(retryUrl);
        }, retrySeconds * 1000);
    }

    var form = document.getElementById('alta-telefono-codigo');
    if (!form) {
        return;
    }

    var input = form.querySelector('#alta-codigo');
    var errorBox = card.querySelector('[data-code-error]');
    var submitButton = form.querySelector('button[type="submit"]');
    var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    function showError(message) {
        if (!errorBox) {
            return;
        }
        errorBox.textContent = message || '';
        errorBox.classList.toggle('d-none', !message);
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!input) {
            return;
        }

        var code = input.value.replace(/\s+/g, '').toUpperCase();
        if (code === '') {
            showError('Escribí el código que aparece en la computadora.');
            return;
        }

        showError('');
        if (submitButton) {
            submitButton.disabled = true;
        }

        fetch(card.dataset.codigoUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ code: code })
        }).then(function (response) {
            return response.json().catch(function () {
                return null;
            }).then(function (data) {
                if (response.ok) {
                    window.location.reload();
                    return;
                }

                var message = data && data.error && data.error.message
                    ? data.error.message
                    : 'No pudimos validar el código.';
                var remaining = data && data.error ? Number(data.error.remaining_attempts || 0) : 0;
                if (remaining > 0) {
                    message += ' Te quedan ' + remaining + ' intento' + (remaining === 1 ? '' : 's') + '.';
                }
                showError(message);
                if (submitButton) {
                    submitButton.disabled = false;
                }
                if (input) {
                    input.select();
                }
            });
        }).catch(function () {
            showError('No pudimos validar el código. Revisá tu conexión.');
            if (submitButton) {
                submitButton.disabled = false;
            }
        });
    });
})();
