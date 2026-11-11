/**
 * Alta de cuenta desde la web: elegir centro, mostrar el QR y el código, consultar estado.
 *
 * El markup vive en `frontend/views/alta/index.php` (incluida la plantilla de resultados).
 * Acá sólo se completa texto, se alternan paneles y se habla con el servidor.
 */
(function () {
    'use strict';

    var root = document.getElementById('alta-root');
    if (!root) {
        return;
    }

    var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var pollMs = Math.max(1000, (parseInt(root.dataset.pollSeconds, 10) || 2) * 1000);
    var urls = {
        iniciar: root.dataset.iniciarUrl || '',
        estado: root.dataset.estadoUrl || '',
        cancelar: root.dataset.cancelarUrl || '',
        completar: root.dataset.completarUrl || '',
        centros: root.dataset.centrosUrl || '',
        provincias: root.dataset.provinciasUrl || ''
    };

    var panels = {};
    root.querySelectorAll('[data-panel]').forEach(function (element) {
        panels[element.dataset.panel] = element;
    });

    var startButton = root.querySelector('[data-action="start"]');
    if (!startButton) {
        return;
    }

    var els = {
        sector: root.querySelector('[data-field="sector"]'),
        provincia: root.querySelector('[data-field="id_provincia"]'),
        q: root.querySelector('[data-field="q"]'),
        results: root.querySelector('[data-results]'),
        resultsHint: root.querySelector('[data-results-hint]'),
        formError: root.querySelector('[data-form-error]'),
        qr: root.querySelector('[data-qr]'),
        qrCenter: root.querySelector('[data-qr-center]'),
        qrStatus: root.querySelector('[data-qr-status]'),
        code: root.querySelector('[data-code]'),
        codeStatus: root.querySelector('[data-code-status]'),
        errorMessage: root.querySelector('[data-error-message]'),
        resultMessage: root.querySelector('[data-result-message]'),
        template: root.querySelector('[data-template="center-result"]')
    };

    var state = {
        centerId: 0,
        centerName: '',
        pairingId: '',
        code: '',
        pollTimer: null,
        searchTimer: null,
        searchAttempt: 0,
        pollAttempt: 0,
        completing: false
    };

    function showPanel(name) {
        Object.keys(panels).forEach(function (key) {
            panels[key].classList.toggle('d-none', key !== name);
        });
    }

    function setFormError(message) {
        if (!els.formError) {
            return;
        }
        els.formError.textContent = message || '';
        els.formError.classList.toggle('d-none', !message);
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(body || {})
        }).then(function (response) {
            return response.json().catch(function () {
                return null;
            }).then(function (data) {
                if (!response.ok) {
                    var message = data && data.error && data.error.message
                        ? data.error.message
                        : 'No pudimos completar la operación.';
                    var error = new Error(message);
                    error.status = response.status;
                    throw error;
                }

                return data || {};
            });
        });
    }

    function loadProvinces() {
        if (!urls.provincias || !els.provincia) {
            return;
        }

        fetch(urls.provincias, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            var items = payload && payload.data && payload.data.items ? payload.data.items : [];
            items.forEach(function (item) {
                var option = document.createElement('option');
                option.value = String(item.id);
                option.textContent = String(item.nombre);
                els.provincia.appendChild(option);
            });
        }).catch(function () {
            /* Sin provincias el filtro queda en "Todas": no bloquea el alta. */
        });
    }

    function renderCenters(items) {
        els.results.querySelectorAll('[data-center]').forEach(function (node) {
            node.remove();
        });

        if (!items.length) {
            if (els.resultsHint) {
                els.resultsHint.textContent = 'No encontramos centros con esos filtros.';
                els.resultsHint.classList.remove('d-none');
            }
            return;
        }

        if (els.resultsHint) {
            els.resultsHint.classList.add('d-none');
        }

        items.forEach(function (item) {
            if (!els.template) {
                return;
            }
            var node = els.template.content.firstElementChild.cloneNode(true);
            node.dataset.centerId = String(item.id_efector);
            node.querySelector('[data-center-name]').textContent = String(item.nombre || '');
            node.querySelector('[data-center-meta]').textContent = [
                item.sector === 'PRIVADO' ? 'Privado' : 'Público',
                item.localidad || '',
                item.provincia || ''
            ].filter(Boolean).join(' · ');
            node.addEventListener('click', function () {
                selectCenter(Number(item.id_efector), String(item.nombre || ''));
            });
            els.results.appendChild(node);
        });
    }

    function selectCenter(id, name) {
        state.centerId = id;
        state.centerName = name;
        startButton.disabled = !id;
        els.results.querySelectorAll('[data-center]').forEach(function (node) {
            var selected = Number(node.dataset.centerId) === id;
            node.classList.toggle('active', selected);
            node.setAttribute('aria-selected', selected ? 'true' : 'false');
        });
    }

    function searchCenters() {
        if (!urls.centros) {
            return;
        }

        var params = new URLSearchParams();
        var query = (els.q && els.q.value ? els.q.value : '').trim();
        if (query) {
            params.set('q', query);
        }
        if (els.sector && els.sector.value) {
            params.set('sector', els.sector.value);
        }
        if (els.provincia && els.provincia.value) {
            params.set('id_provincia', els.provincia.value);
        }

        var attempt = ++state.searchAttempt;

        fetch(urls.centros + (params.toString() ? '?' + params.toString() : ''), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            if (attempt !== state.searchAttempt) {
                return;
            }
            var items = payload && payload.data && payload.data.items ? payload.data.items : [];
            renderCenters(items);
        }).catch(function () {
            if (attempt !== state.searchAttempt) {
                return;
            }
            setFormError('No pudimos cargar los centros. Reintentá en unos segundos.');
        });
    }

    function scheduleSearch() {
        window.clearTimeout(state.searchTimer);
        state.searchTimer = window.setTimeout(searchCenters, 300);
    }

    function stopPolling() {
        if (state.pollTimer) {
            window.clearInterval(state.pollTimer);
            state.pollTimer = null;
        }
    }

    function resetToForm() {
        stopPolling();
        state.pairingId = '';
        state.code = '';
        state.completing = false;
        state.pollAttempt++;
        if (els.qr) {
            els.qr.replaceChildren();
        }
        if (els.qrStatus) {
            els.qrStatus.textContent = '';
        }
        if (els.codeStatus) {
            els.codeStatus.textContent = '';
        }
        if (els.code) {
            els.code.textContent = '';
        }
        setFormError('');
        startButton.disabled = !state.centerId;
        showPanel('form');
    }

    function showError(message) {
        stopPolling();
        if (els.errorMessage) {
            els.errorMessage.textContent = message || 'No pudimos continuar.';
        }
        showPanel('error');
    }

    function startPairing() {
        if (!state.centerId) {
            setFormError('Elegí el centro de salud al que pertenecés.');
            return;
        }

        setFormError('');
        startButton.disabled = true;

        postJson(urls.iniciar, { id_efector: state.centerId })
            .then(function (data) {
                state.pairingId = String(data.pairing_id || '');
                state.code = String(data.code || '');
                state.pollAttempt++;

                if (els.qr) {
                    els.qr.replaceChildren();
                    var qrDocument = new DOMParser().parseFromString(String(data.qr_svg || ''), 'image/svg+xml');
                    var svg = qrDocument.documentElement;
                    if (svg.localName === 'svg' && !qrDocument.querySelector('parsererror')) {
                        els.qr.appendChild(document.importNode(svg, true));
                    }
                }
                if (els.qrCenter) {
                    els.qrCenter.textContent = state.centerName;
                }
                if (els.qrStatus) {
                    els.qrStatus.textContent = '';
                }

                showPanel('qr');
                pollStatus();
                state.pollTimer = window.setInterval(pollStatus, pollMs);
            })
            .catch(function (error) {
                startButton.disabled = false;
                setFormError(error.message || 'No pudimos iniciar el alta.');
            });
    }

    function pollStatus() {
        if (!state.pairingId) {
            return;
        }

        var attempt = ++state.pollAttempt;

        postJson(urls.estado, { pairing_id: state.pairingId })
            .then(function (data) {
                if (attempt !== state.pollAttempt) {
                    return;
                }
                handleState(String(data.state || ''));
            })
            .catch(function (error) {
                if (attempt !== state.pollAttempt) {
                    return;
                }
                showError(error.message);
            });
    }

    function handleState(nextState) {
        switch (nextState) {
            case 'PENDING':
                /* El teléfono todavía no abrió el enlace: se sigue esperando en el QR. */
                break;
            case 'CLAIMED':
                if (els.code && els.code.textContent !== state.code) {
                    els.code.textContent = state.code;
                }
                showPanel('code');
                break;
            case 'AUTHORIZED':
            case 'VERIFICATION_STARTED':
                showPanel('waiting');
                break;
            case 'VERIFIED':
                completeAccount();
                break;
            case 'ACCOUNT_CREATED':
                stopPolling();
                showResult({ already_done: true });
                break;
            case 'EXPIRED':
                stopPolling();
                showPanel('expired');
                break;
            case 'CANCELLED':
            case 'FAILED':
                stopPolling();
                showPanel('expired');
                break;
            case 'NOT_FOUND':
                showError('El vínculo del alta ya no existe. Volvé a empezar.');
                break;
            default:
                break;
        }
    }

    function completeAccount() {
        if (state.completing) {
            return;
        }

        state.completing = true;
        stopPolling();
        showPanel('ready');

        postJson(urls.completar, { pairing_id: state.pairingId })
            .then(function (data) {
                state.completing = false;
                showResult(data || {});
            })
            .catch(function (error) {
                state.completing = false;
                showError(error.message);
            });
    }

    function showResult(data) {
        var message = '';

        if (data && data.account) {
            message = data.account.es_nueva_persona
                ? 'Creamos tu cuenta con los datos que verificaste.'
                : 'Ya tenías cuenta con esa identidad: la reutilizamos, sin crear otra.';

            if (data.affiliation && data.affiliation.nombre_efector) {
                message += ' Tu solicitud para ' + data.affiliation.nombre_efector + ' quedó registrada.';
            }

            if (data.draft_pending) {
                message += ' Además tenés un alta de espacio de atención sin terminar:'
                    + ' podés retomarla desde la app Personal de Salud.';
            }
        } else {
            message = 'El alta ya estaba terminada.';
        }

        if (els.resultMessage) {
            els.resultMessage.textContent = message;
        }
        showPanel('result');
    }

    function cancelPairing() {
        var pairingId = state.pairingId;
        if (!pairingId) {
            resetToForm();
            return;
        }

        postJson(urls.cancelar, { pairing_id: pairingId })
            .catch(function () {
                /* Cancelar es best-effort: el vínculo expira solo. */
            })
            .then(function () {
                resetToForm();
            });
    }

    startButton.addEventListener('click', startPairing);

    root.querySelectorAll('[data-action="cancel"]').forEach(function (button) {
        button.addEventListener('click', cancelPairing);
    });

    root.querySelectorAll('[data-action="restart"]').forEach(function (button) {
        button.addEventListener('click', resetToForm);
    });

    [els.sector, els.provincia].forEach(function (field) {
        if (field) {
            field.addEventListener('change', searchCenters);
        }
    });

    if (els.q) {
        els.q.addEventListener('input', scheduleSearch);
    }

    loadProvinces();
    searchCenters();
})();
