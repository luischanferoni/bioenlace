/**
 * Lógica de la Página Inicial de la SPA
 * Maneja el textarea, consultas a IA, y renderizado de cards
 */

(function() {
    'use strict';

    /** URL absoluta para fetch desde el shell (misma regla que loadPageContent). */
    function resolveSpaFetchUrl(url) {
        if (!url) return '';
        if (url.startsWith('http://') || url.startsWith('https://')) {
            return url;
        }
        if (url.startsWith('/api/')) {
            return window.location.origin + url;
        }
        if (url.startsWith('/')) {
            return window.spaConfig.baseUrl + url;
        }
        return window.spaConfig.baseUrl + '/' + url;
    }

    function mergeApiQueryIntoUrl(baseUrl, apiObj) {
        if (!apiObj || !apiObj.query || typeof apiObj.query !== 'object') {
            return baseUrl;
        }
        try {
            const u = new URL(baseUrl);
            Object.keys(apiObj.query).forEach(function (k) {
                const v = apiObj.query[k];
                if (v != null && String(v) !== '') {
                    u.searchParams.set(k, String(v));
                }
            });
            return u.toString();
        } catch (e) {
            return baseUrl;
        }
    }

    function buildUrlForFlowTab(tab) {
        if (!tab || !tab.route) {
            return '';
        }
        const base = resolveSpaFetchUrl(String(tab.route));
        try {
            const u = new URL(base);
            const params = tab.params && typeof tab.params === 'object' ? tab.params : {};
            Object.keys(params).forEach(function (k) {
                const spec = String(params[k] || '');
                if (spec.indexOf('draft.') === 0) {
                    const f = spec.slice(6);
                    const dv = draft[f];
                    if (dv != null && String(dv) !== '') {
                        u.searchParams.set(k, String(dv));
                    }
                }
            });
            return u.toString();
        } catch (e) {
            return base;
        }
    }

    function flowTabNeedsGeo(tab) {
        return tab && Array.isArray(tab.requires_client) && tab.requires_client.indexOf('geolocation') !== -1;
    }

    function fetchFlowUiDefinition(fullUrl, mountEl, responseSection) {
        mountEl.innerHTML = '<div class="d-flex align-items-center justify-content-center gap-2 py-3 text-muted"><div class="spinner-border spinner-border-sm"></div> Cargando...</div>';
        fetch(fullUrl, {
            method: 'GET',
            headers: window.BioenlaceApiClient.mergeHeaders({
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            })
        })
            .then(function (r) {
                if (!r.ok) {
                    return r.text().then(function (t) {
                        try {
                            const j = JSON.parse(t);
                            if (j && typeof j.message === 'string' && j.message.trim() !== '') {
                                throw new Error(j.message.trim());
                            }
                        } catch (e) {
                            // ignore parse error; use generic below
                        }
                        throw new Error('HTTP ' + r.status);
                    });
                }
                return r.json();
            })
            .then(function (json) {
                mountEl.innerHTML = '';
                if (json && json.kind === 'ui_definition') {
                    renderDynamicUi(json, mountEl, { url: fullUrl });
                } else {
                    mountEl.innerHTML = '<div class="alert alert-warning mb-0">La respuesta no es una definición de UI válida.</div>';
                }
            })
            .catch(function (err) {
                console.error('Error cargando UI JSON (flow):', err);
                const msg = (err && err.message) ? String(err.message) : 'Error al cargar la UI';
                mountEl.innerHTML = '<div class="alert alert-danger mb-0">' + escapeHtml(msg) + '</div>';
            })
            .finally(function () {
                responseSection.classList.remove('d-none');
                setTimeout(function () {
                    responseSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }, 100);
            });
    }

    // Referencias a elementos DOM
    const chatCard = document.getElementById('spa-chat-card');
    const queryInput = document.getElementById('spa-query-input');
    const sendBtn = document.getElementById('spa-send-btn');
    const shortcutsToggleBtn = document.getElementById('spa-shortcuts-toggle-btn');
    const shortcutsContent = document.getElementById('spa-shortcuts-content');
    const shortcutsPanel = document.getElementById('spa-shortcuts-panel');
    const responseSection = document.getElementById('spa-response-section');
    const explanationDiv = document.getElementById('spa-explanation');
    const actionsDiv = document.getElementById('spa-actions');
    const chatMessagesDiv = document.getElementById('spa-chat-messages');
    const chatEmptyHint = document.getElementById('spa-chat-empty-hint');

    // Estado de cards expandidos
    const expandedCards = new Map();

    // Estado conversacional (flow) — similar al cliente Flutter.
    let currentIntentId = null;
    let currentSubintentId = null;
    let draft = {};
    // Persistencia simple en memoria global para evitar perder estado en re-renders complejos.
    // (No es storage; solo evita depender de closures si se re-ejecuta parte del JS).
    const FLOW_STATE_KEY = '__bio_spa_flow_state__';

    function writeFlowState() {
        try {
            window[FLOW_STATE_KEY] = {
                intent_id: currentIntentId,
                subintent_id: currentSubintentId,
                draft: draft || {}
            };
        } catch (e) {
            // ignore
        }
    }

    function readFlowState() {
        try {
            const s = window[FLOW_STATE_KEY];
            if (s && typeof s === 'object') {
                if (s.intent_id) currentIntentId = String(s.intent_id);
                if (s.subintent_id) currentSubintentId = String(s.subintent_id);
                if (s.draft && typeof s.draft === 'object') draft = Object.assign({}, s.draft);
            }
        } catch (e) {
            // ignore
        }
    }

    /**
     * Inicialización
     */
    function init() {
        // Recuperar estado flow si existía.
        readFlowState();

        // Ajustar altura real del chat al viewport (considera navbar/layout Yii).
        // Evita hardcodear `100vh - X` en la vista.
        function applyChatHeight() {
            if (!chatCard) return;
            try {
                const rect = chatCard.getBoundingClientRect();
                const top = rect.top;
                const vh = window.innerHeight || document.documentElement.clientHeight || 0;
                const marginBottom = 12; // pequeño respiro para no pegar al borde
                const h = Math.max(320, Math.floor(vh - top - marginBottom));
                chatCard.style.height = h + 'px';
            } catch (e) { /* ignore */ }
        }
        applyChatHeight();
        window.addEventListener('resize', function () {
            applyChatHeight();
        });

        // Capturar el estado "idle" del botón enviar desde el DOM (evitar hardcode en JS).
        // Esto permite que el ícono/texto se defina en la vista (`asistente.php`) y el JS solo lo reutilice.
        try {
            if (sendBtn && !sendBtn.dataset.sendIdleText) {
                const st = sendBtn.querySelector('.spa-send-text');
                if (st && st.textContent != null) {
                    const idle = String(st.textContent);
                    if (idle.trim() !== '') {
                        sendBtn.dataset.sendIdleText = idle;
                    }
                }
            }
        } catch (e) {
            // ignore
        }

        // Cargar atajos (flows) al inicio solo si existe el panel
        if (shortcutsContent) {
            loadCommonActions();
        }

        // Event listeners solo si los elementos existen
        if (sendBtn && queryInput) {
            sendBtn.addEventListener('click', handleSendQuery);
            queryInput.addEventListener('keydown', handleKeyDown);
            queryInput.addEventListener('input', handleInput);
            queryInput.addEventListener('focus', function () {
                // Si el usuario empieza a interactuar con el input, priorizar el chat.
                collapseShortcutsPanel();
            });

            // Focus en textarea al cargar
            queryInput.focus();
        }

        // Panel Atajos: toggle manual (sin dropdown Bootstrap).
        if (shortcutsToggleBtn) {
            shortcutsToggleBtn.addEventListener('click', function () {
                toggleShortcutsPanel();
            });
        }
    }

    function setShortcutsPanelExpanded(expanded) {
        if (!chatCard) return;
        const ex = !!expanded;
        try {
            chatCard.classList.toggle('spa-shortcuts-collapsed', !ex);
        } catch (e) { /* ignore */ }
        if (shortcutsToggleBtn) {
            try { shortcutsToggleBtn.setAttribute('aria-expanded', ex ? 'true' : 'false'); } catch (e) { /* ignore */ }
        }
        if (shortcutsPanel) {
            try { shortcutsPanel.style.display = ex ? '' : 'none'; } catch (e) { /* ignore */ }
        }
    }

    function isShortcutsPanelExpanded() {
        if (!chatCard) return false;
        return !chatCard.classList.contains('spa-shortcuts-collapsed');
    }

    function collapseShortcutsPanel() {
        if (!chatCard || !shortcutsPanel) return;
        if (!isShortcutsPanelExpanded()) return;
        setShortcutsPanelExpanded(false);
    }

    function expandShortcutsPanel() {
        if (!chatCard || !shortcutsPanel) return;
        if (isShortcutsPanelExpanded()) return;
        setShortcutsPanelExpanded(true);
    }

    function toggleShortcutsPanel() {
        if (!chatCard || !shortcutsPanel) return;
        setShortcutsPanelExpanded(!isShortcutsPanelExpanded());
        // Si el usuario reabre el panel, no robamos el foco del textarea.
    }

    function scrollChatToBottom() {
        if (!chatMessagesDiv) return;
        try {
            chatMessagesDiv.scrollTop = chatMessagesDiv.scrollHeight;
        } catch (e) {
            // ignore
        }
    }

    /**
     * Append de burbujas al chat (no reemplazar historial).
     * @param {"user"|"bot"|"system"} role
     * @param {string} html
     * @returns {HTMLDivElement|null}
     */
    function appendChatBubble(role, html) {
        if (!chatMessagesDiv) {
            return null;
        }
        const row = document.createElement('div');
        row.className = 'd-flex mb-2 ' + (role === 'user' ? 'justify-content-end' : 'justify-content-start');

        const bubble = document.createElement('div');
        const base = 'p-2 rounded-3';
        const theme = role === 'user'
            ? ' bg-primary text-white'
            : (role === 'system' ? ' bg-light text-muted border' : ' bg-white border');
        bubble.className = base + theme;
        bubble.style.maxWidth = '95%';
        bubble.innerHTML = html;

        row.appendChild(bubble);
        chatMessagesDiv.appendChild(row);
        setTimeout(scrollChatToBottom, 10);
        return bubble;
    }

    /**
     * Turno de asistente en flow: ancho completo del chat (sin burbuja estrecha).
     * El título corresponde a `assistant_text` (viene en `text` del payload).
     *
     * @param {string} titleText
     * @returns {HTMLDivElement|null} Contenedor interno donde montar tabs/UI (después del h4)
     */
    function appendFlowAssistantTurn(titleText) {
        if (!chatMessagesDiv) {
            return null;
        }
        const row = document.createElement('div');
        row.className = 'w-100 mb-3 spa-chat-flow-row';
        const inner = document.createElement('div');
        inner.className = 'spa-chat-flow-turn w-100';
        const h = document.createElement('h4');
        h.className = 'spa-assistant-step-title';
        const t = String(titleText || '').trim();
        h.textContent = t !== '' ? t : 'Ok.';
        inner.appendChild(h);
        row.appendChild(inner);
        chatMessagesDiv.appendChild(row);
        setTimeout(scrollChatToBottom, 10);
        return inner;
    }

    /**
     * Manejar envío de consulta
     */
    function handleSendQuery(contentOverride) {
        // Si el usuario envía, priorizar chat y colapsar atajos.
        if (typeof contentOverride !== 'string') {
            collapseShortcutsPanel();
        }
        const raw = (typeof contentOverride === 'string')
            ? contentOverride
            : (queryInput ? queryInput.value : '');
        const query = String(raw || '').trim();
        
        // En flows, se permite avanzar con `content=''` (solo snapshot draft/intento).
        if (!query && !currentIntentId) {
            showError('Por favor, ingresa una consulta');
            return;
        }

        // Deshabilitar botón y mostrar loading
        setLoadingState(true);
        hideResponse();
        if (chatEmptyHint) {
            chatEmptyHint.classList.add('d-none');
        }

        // Limpiar el textarea inmediatamente (UX tipo chat) solo si el input existe
        // y el envío vino del textarea (no por override programático).
        if (typeof contentOverride !== 'string') {
            try {
                queryInput.value = '';
                handleInput();
            } catch (e) {
                // ignore
            }
        }

        // En modo chat, agregar burbuja de usuario antes de enviar (si hay texto).
        if (chatMessagesDiv && query !== '' && typeof contentOverride !== 'string') {
            appendChatBubble('user', '<div>' + escapeHtml(query) + '</div>');
        }

        // Usar endpoint de la API. Importante: en entornos donde el frontend vive bajo /api,
        // window.spaConfig.baseUrl puede ser https://host/api y concatenar "/api/..." duplica.
        const asistenteUrl = window.location.origin + '/api/v1/asistente/enviar';
        const body = {};

        // Modo flow: si hay intent activo, enviar snapshot.
        if (currentIntentId) {
            body.intent_id = currentIntentId;
            if (currentSubintentId) {
                body.subintent_id = currentSubintentId;
            }
            body.draft = draft || {};
            body.content = query;
        } else {
            body.content = query;
        }

        fetch(asistenteUrl, {
            method: 'POST',
            headers: window.BioenlaceApiClient.mergeHeaders({
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }),
            credentials: 'same-origin', // Incluir cookies de sesión
            body: JSON.stringify(body)
        })
        .then(response => {
            // Si la respuesta no es exitosa, manejar el error
            if (!response.ok) {
                return response.text().then(text => {
                    // Intentar parsear como JSON si es posible
                    try {
                        const jsonData = JSON.parse(text);
                        throw new Error(jsonData.message || jsonData.error || `Error ${response.status}: ${response.statusText}`);
                    } catch (e) {
                        // Si no es JSON, es probablemente un error de validación
                        if (response.status === 400) {
                            throw new Error('Error de validación. Por favor, verifica tu consulta e intenta nuevamente.');
                        } else if (response.status === 401) {
                            throw new Error('Debes estar autenticado para usar esta funcionalidad.');
                        }
                        throw new Error(`Error ${response.status}: ${response.statusText}`);
                    }
                });
            }
            
            // Verificar que la respuesta sea JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    console.warn('El servidor devolvió HTML en lugar de JSON:', text.substring(0, 200));
                    throw new Error('Respuesta no válida del servidor');
                });
            }
            return response.json();
        })
        .then(data => {
            // Compat: antes venía { success, message, data }, ahora el endpoint devuelve el payload directo.
            let result = data;
            if (data.data && typeof data.data === 'object' && (data.data.success !== undefined || data.data.explanation !== undefined || data.data.action !== undefined)) {
                result = data.data;
            }

            // Normalizar flow: si viene `kind=intent_flow` usamos `text` como respuesta principal.
            const kind = result && result.kind ? String(result.kind) : '';
            const flowText = result && typeof result.text === 'string' ? result.text : null;
            const explanationText = typeof result.explanation === 'string' ? result.explanation : null;
            const primaryText = (flowText && flowText.trim() !== '') ? flowText.trim() : (explanationText || '');

            // Si trae intent_id/subintent_id, sincronizar estado conversacional.
            if (result && result.intent_id) {
                currentIntentId = String(result.intent_id);
                writeFlowState();
            }
            if (result && result.subintent_id) {
                currentSubintentId = String(result.subintent_id);
                writeFlowState();
            }
            if (result && result.draft_delta && typeof result.draft_delta === 'object') {
                try {
                    draft = Object.assign({}, draft || {}, result.draft_delta || {});
                    writeFlowState();
                } catch (e) {
                    // ignore
                }
            }

            // Flow conversacional: no renderizar cards/botones; renderizar mini-UI inline si viene open_ui.
            if (kind === 'intent_flow' || (result && result.intent_id && flowText)) {
                // En web: turno a ancho completo + título (assistant_text) como h4; UI debajo sin burbuja angosta.
                let flowTurnInner = null;
                if (chatMessagesDiv) {
                    flowTurnInner = appendFlowAssistantTurn(primaryText || 'Ok.');
                } else {
                    // Fallback legacy (sin contenedor chat)
                    explanationDiv.innerHTML = '<h4 class="spa-assistant-step-title">' + escapeHtml(primaryText || 'Ok.') + '</h4>';
                    actionsDiv.innerHTML = '';
                }

                const openUi = result && result.open_ui && typeof result.open_ui === 'object' ? result.open_ui : null;
                const co = openUi && openUi.client_open && typeof openUi.client_open === 'object' ? openUi.client_open : null;
                const fm = result.flow_manifest && typeof result.flow_manifest === 'object' ? result.flow_manifest : null;
                const activeStep = fm && fm.active_step && typeof fm.active_step === 'object' ? fm.active_step : null;
                const nextId = activeStep && activeStep.next != null ? String(activeStep.next) : '';

                const uiMeta = activeStep && activeStep.ui && typeof activeStep.ui === 'object' ? activeStep.ui : null;
                const tabs = uiMeta && Array.isArray(uiMeta.tabs) ? uiMeta.tabs : [];
                const defaultTabId = uiMeta && uiMeta.default_tab != null ? String(uiMeta.default_tab) : '';

                // Fin del flow: sin más pasos / sin UI (motor suele devolver texto "Listo.").
                // Si el siguiente paso solo tiene submit, el motor ya devuelve `open_ui` del submit (sin pantalla intermedia).
                const hasOpenUi = !!(openUi && openUi.action_id);
                const okUiJson = co && String(co.kind || '') === 'ui_json' && co.api && co.api.route;
                const hasTabs = tabs.length >= 1;
                const isTerminalStep = nextId === '';
                const isDoneText = typeof primaryText === 'string' && /^listo\.?$/i.test(primaryText.trim());

                // Regla prioritaria: si el bot dice "Listo." y no hay `open_ui` explícito, NO abrir UI por tabs.
                // (El manifest puede seguir apuntando al último step, pero el flujo ya cerró.)
                if (isDoneText && !hasOpenUi && !okUiJson) {
                    currentIntentId = null;
                    currentSubintentId = null;
                    draft = {};
                    writeFlowState();
                    if (flowTurnInner) {
                        setTimeout(scrollChatToBottom, 20);
                    } else {
                        responseSection.classList.remove('d-none');
                    }
                    return;
                }

                // En flows, la fuente de verdad para abrir la UI es `flow_manifest.active_step.ui.tabs[*].route`.
                // `open_ui.client_open` puede venir null (p. ej. por permisos/catálogo), pero igual podemos intentar
                // abrir vía route y dejar que el server autorice (403) si corresponde.
                let fullUrl = '';
                if (okUiJson) {
                    const route = String(co.api.route || '');
                    fullUrl = mergeApiQueryIntoUrl(resolveSpaFetchUrl(route), co.api);
                } else if (tabs.length >= 1) {
                    let defIdx = 0;
                    for (let ti = 0; ti < tabs.length; ti++) {
                        if (defaultTabId !== '' && String(tabs[ti].id) === defaultTabId) {
                            defIdx = ti;
                            break;
                        }
                    }
                    fullUrl = buildUrlForFlowTab(tabs[defIdx]);
                }

                if (!fullUrl) {
                    const errHtml = openUi && openUi.action_id
                        ? ('<div class="alert alert-danger mb-0 mt-2">No puedo abrir la mini-UI requerida (' + escapeHtml(String(openUi.action_id)) + ').</div>')
                        : ('<div class="alert alert-danger mb-0 mt-2">No puedo determinar la UI a abrir para este paso.</div>');
                    if (flowTurnInner) {
                        const errWrap = document.createElement('div');
                        errWrap.innerHTML = errHtml;
                        while (errWrap.firstChild) {
                            flowTurnInner.appendChild(errWrap.firstChild);
                        }
                        setTimeout(scrollChatToBottom, 20);
                    } else {
                        explanationDiv.innerHTML =
                            '<h4 class="spa-assistant-step-title">' + escapeHtml(primaryText || 'Ok.') + '</h4>' + errHtml;
                        responseSection.classList.remove('d-none');
                        setTimeout(() => responseSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 100);
                    }
                    return;
                }

                // Host para renderizar la mini-UI del paso (debajo del h4, ancho completo).
                const mountHost = flowTurnInner ? flowTurnInner : actionsDiv;

                if (tabs.length >= 2) {
                    if (!flowTurnInner) {
                        actionsDiv.innerHTML = '';
                    }
                    const tabRow = document.createElement('div');
                    tabRow.className = 'd-flex flex-wrap gap-2 mb-2 mt-2 w-100';
                    const mountEl = document.createElement('div');
                    mountEl.className = 'mt-1 w-100 spa-chat-flow-ui';
                    mountHost.appendChild(tabRow);
                    mountHost.appendChild(mountEl);

                    let firstDefaultIdx = 0;
                    for (let ti = 0; ti < tabs.length; ti++) {
                        if (String(tabs[ti].id) === defaultTabId) {
                            firstDefaultIdx = ti;
                            break;
                        }
                    }

                    function activateTab(tab) {
                        const isDefault = defaultTabId !== '' && tab && String(tab.id) === defaultTabId;
                        let url;
                        if (isDefault) {
                            url = fullUrl;
                        } else if (flowTabNeedsGeo(tab)) {
                            if (!navigator.geolocation) {
                                mountEl.innerHTML = '<div class="alert alert-warning mb-0">Geolocalización no disponible en este navegador.</div>';
                                return;
                            }
                            // Loader genérico (no específico del dominio): mientras esperamos geolocalización.
                            mountEl.innerHTML = '<div class="d-flex align-items-center gap-2 py-2 text-muted"><div class="spinner-border spinner-border-sm"></div> Cargando...</div>';
                            navigator.geolocation.getCurrentPosition(function (pos) {
                                const u = new URL(buildUrlForFlowTab(tab));
                                u.searchParams.set('latitud', String(pos.coords.latitude));
                                u.searchParams.set('longitud', String(pos.coords.longitude));
                                fetchFlowUiDefinition(u.toString(), mountEl, responseSection);
                            }, function () {
                                mountEl.innerHTML = '<div class="alert alert-warning mb-0">No se pudo obtener la ubicación.</div>';
                            });
                            return;
                        } else {
                            url = buildUrlForFlowTab(tab);
                        }
                        if (!url) {
                            mountEl.innerHTML = '<div class="alert alert-warning mb-0">URL inválida para esta pestaña.</div>';
                            return;
                        }
                        fetchFlowUiDefinition(url, mountEl, responseSection);
                    }

                    tabs.forEach(function (tab, idx) {
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'btn btn-sm btn-outline-primary';
                        b.textContent = (tab && tab.label) ? String(tab.label) : ('Vista ' + (idx + 1));
                        b.addEventListener('click', function () {
                            tabRow.querySelectorAll('button').forEach(function (x) { x.classList.remove('active'); });
                            b.classList.add('active');
                            activateTab(tab);
                        });
                        tabRow.appendChild(b);
                    });

                    const firstBtn = tabRow.querySelectorAll('button')[firstDefaultIdx];
                    if (firstBtn) {
                        firstBtn.classList.add('active');
                        activateTab(tabs[firstDefaultIdx]);
                    }
                    return;
                }

                if (!flowTurnInner) {
                    actionsDiv.innerHTML = '<div class="d-flex align-items-center justify-content-center gap-2 py-3 text-muted"><div class="spinner-border spinner-border-sm"></div> Cargando...</div>';
                } else {
                    // Importante: no renderizar la UI sobre el root del turno porque renderDynamicUi()
                    // reemplaza innerHTML y borraría el h4. Siempre montar en un hijo marcado.
                    var flowUiMount = document.createElement('div');
                    flowUiMount.className = 'spa-chat-flow-ui w-100 mt-2';
                    flowUiMount.setAttribute('data-spa-flow-ui-mount', '1');
                    const loading = document.createElement('div');
                    loading.className = 'd-flex align-items-center justify-content-center gap-2 py-2 text-muted mt-2';
                    loading.innerHTML = '<div class="spinner-border spinner-border-sm"></div> Cargando...';
                    flowUiMount.appendChild(loading);
                    mountHost.appendChild(flowUiMount);
                }
                fetch(fullUrl, {
                    method: 'GET',
                    headers: window.BioenlaceApiClient.mergeHeaders({
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    })
                })
                .then(r => {
                    if (!r.ok) {
                        return r.text().then(function (t) {
                            try {
                                const j = JSON.parse(t);
                                if (j && typeof j.message === 'string' && j.message.trim() !== '') {
                                    throw new Error(j.message.trim());
                                }
                            } catch (e) {
                                // ignore
                            }
                            throw new Error('HTTP ' + r.status);
                        });
                    }
                    return r.json();
                })
                .then(json => {
                    if (!flowTurnInner) {
                        actionsDiv.innerHTML = '';
                    } else {
                        // limpiar loaders dentro del turno
                        try {
                            Array.from(mountHost.querySelectorAll('.spinner-border')).forEach(function (s) {
                                const wrap = s.closest('div');
                                if (wrap && wrap.textContent && wrap.textContent.toLowerCase().includes('cargando')) {
                                    wrap.remove();
                                }
                            });
                        } catch (e) {
                            // ignore
                        }
                    }
                    if (json && json.kind === 'ui_definition') {
                        const target = flowTurnInner
                            ? (mountHost.querySelector('[data-spa-flow-ui-mount]') || mountHost)
                            : actionsDiv;
                        renderDynamicUi(json, target, { url: fullUrl });
                    } else {
                        const target = flowTurnInner
                            ? (mountHost.querySelector('[data-spa-flow-ui-mount]') || mountHost)
                            : actionsDiv;
                        target.innerHTML = '<div class="alert alert-warning mb-0 mt-2">La respuesta no es una definición de UI válida.</div>';
                    }
                })
                .catch(err => {
                    console.error('Error cargando UI JSON (flow):', err);
                    const msg = (err && err.message) ? String(err.message) : 'Error al cargar la UI';
                    const target = flowTurnInner
                        ? (mountHost.querySelector('[data-spa-flow-ui-mount]') || mountHost)
                        : actionsDiv;
                    target.innerHTML = '<div class="alert alert-danger mb-0 mt-2">' + escapeHtml(msg) + '</div>';
                })
                .finally(() => {
                    if (!chatMessagesDiv) {
                        responseSection.classList.remove('d-none');
                        setTimeout(() => responseSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 100);
                    } else {
                        setTimeout(scrollChatToBottom, 20);
                    }
                });
                return;
            }
            
            // Si tiene explicación, mostrar respuesta (incluso si success es false)
            if (result.explanation !== undefined) {
                // Verificar si es respuesta CRUD con formulario
                if (result.form) {
                    displayCrudResponse(result);
                } else if (result.intention) {
                    // Es respuesta CRUD pero sin formulario (read, delete, etc)
                    displayCrudResponse(result);
                } else {
                    // Respuesta normal - manejar tanto 'action' (singular) como 'actions' (plural)
                    let actionsToDisplay = result.actions || [];
                    
                    // Si hay 'action' singular (ej: búsqueda por DNI), convertirla a array
                    if (result.action && !result.actions) {
                        actionsToDisplay = [result.action];
                        
                        // Si hay alternative_actions, agregarlas también
                        if (result.alternative_actions && Array.isArray(result.alternative_actions) && result.alternative_actions.length > 0) {
                            actionsToDisplay = actionsToDisplay.concat(result.alternative_actions);
                        }
                    }
                    
                    // Mostrar datos adicionales si existen (ej: datos de persona encontrada)
                    let explanation = result.explanation || '';
                    if (result.data && typeof result.data === 'object') {
                        const dataInfo = [];
                        if (result.data.nombre) {
                            dataInfo.push(`<strong>Nombre:</strong> ${escapeHtml(result.data.nombre)}`);
                        }
                        if (result.data.dni) {
                            dataInfo.push(`<strong>DNI:</strong> ${escapeHtml(result.data.dni)}`);
                        }
                        if (dataInfo.length > 0) {
                            explanation += '<div class="mt-2 p-2 bg-light rounded"><small>' + dataInfo.join(' | ') + '</small></div>';
                        }
                    }
                    
                    // Si success es false pero hay explicación, mostrar como información (no error)
                    if (result.success === false) {
                        const suggested = result.interaccion_sugerida && result.interaccion_sugerida.texto ? result.interaccion_sugerida.texto : null;
                        displayInfoResponse(explanation, actionsToDisplay, suggested);
                    } else {
                        displayResponse(explanation, actionsToDisplay);
                    }
                }
            } else if (result.success !== false) {
                // Sin explicación pero success es true - mostrar error genérico
                showError(result.error || result.message || 'Error al procesar la consulta');
            } else {
                // Error sin explicación
                showError(result.error || result.message || 'Error al procesar la consulta');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError(error.message || 'Error de conexión. Por favor, intente nuevamente.');
        })
        .finally(() => {
            setLoadingState(false);
        });
    }


    /**
     * Mostrar respuesta CRUD
     */
    function displayCrudResponse(data) {
        // Mostrar explicación
        explanationDiv.innerHTML = '<p class="mb-0">' + escapeHtml(data.explanation) + '</p>';
        
        // Si hay formulario, renderizarlo
        if (data.form && data.form.success) {
            actionsDiv.innerHTML = renderCrudForm(data.form, data.intention, data.entity_id);
        } else if (data.action) {
            // Acción directa (navegación, eliminación, etc)
            actionsDiv.innerHTML = renderCrudAction(data);
        } else if (data.suggested_actions && data.suggested_actions.length > 0) {
            // Acciones sugeridas cuando la entidad no existe
            actionsDiv.innerHTML = renderActionCards(data.suggested_actions);
            attachCardListeners();
        } else {
            actionsDiv.innerHTML = '<div class="col-12"><p class="text-muted mb-0">' + escapeHtml(data.message || 'No hay acciones disponibles') + '</p></div>';
        }

        // Mostrar sección de respuesta
        responseSection.classList.remove('d-none');

        // En modo chat, mantener el scroll dentro del panel.
        if (chatMessagesDiv) {
            setTimeout(scrollChatToBottom, 50);
        } else {
            // Layout legacy: scroll hacia la card de respuesta
            setTimeout(() => {
                responseSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 100);
        }
    }

    /**
     * Renderizar formulario CRUD dinámico
     */
    function renderCrudForm(formData, intention, entityId) {
        let html = '<div class="col-12"><div class="card"><div class="card-body">';
        html += '<h6 class="card-title mb-4">Formulario</h6>';
        html += '<form id="crud-dynamic-form" data-intention="' + intention + '" data-entity-id="' + (entityId || '') + '" data-form-action="' + escapeHtml(formData.form_action) + '">';
        
        formData.fields.forEach(field => {
            html += renderFormField(field);
        });
        
        html += '<div class="mt-4 d-flex gap-2">';
        html += '<button type="submit" class="btn btn-primary">Guardar</button>';
        html += '<button type="button" class="btn btn-secondary" onclick="document.getElementById(\'crud-dynamic-form\').reset()">Limpiar</button>';
        html += '</div>';
        html += '</form></div></div></div>';
        
        // Adjuntar listeners al formulario
        setTimeout(() => {
            attachFormListeners();
        }, 100);
        
        return html;
    }

    /**
     * Renderizar UI dinámica a partir de una definición genérica
     * Contrato actual: `ui_type = "ui_json"` con `blocks`.
     * @param {Object} json - Respuesta completa de la API de UI
     * @param {HTMLElement} container - Contenedor donde se debe renderizar la UI
     * @param {Object} options - Opciones adicionales (por ejemplo, url original)
     */
    function renderDynamicUi(json, container, options = {}) {
        if (!json || !container) {
            return;
        }

        const uiType = json.ui_type || 'ui_json';

        switch (uiType) {
            case 'ui_json':
                if (Array.isArray(json.blocks)) {
                    renderUiJsonBlocks(json, container, options);
                    break;
                }
                container.innerHTML = '<div class="alert alert-warning mb-0">UI JSON inválida: falta blocks.</div>';
                break;
            default:
                container.innerHTML = '<div class="alert alert-info">Este tipo de UI aún no está soportado en la web: ' + escapeHtml(uiType) + '</div>';
        }
    }

    /**
     * UI JSON (blocks): permite lista(s) + campos + custom_widgets en orden.
     */
    function renderUiJsonBlocks(json, container, options = {}) {
        const blocks = Array.isArray(json.blocks) ? json.blocks : [];
        if (blocks.length < 1) {
            container.innerHTML = '<div class="alert alert-warning mb-0">UI JSON sin blocks.</div>';
            return;
        }

        // Si el backend devuelve `success=false` + `errors`, mostrar un banner humano arriba.
        try {
            if (json && json.success === false && json.errors && typeof json.errors === 'object') {
                const msg = firstUiErrorMessage(json.errors);
                if (msg) {
                    container.innerHTML = '<div class="alert alert-danger mb-2" data-ui-json-error="1">' + escapeHtml(msg) + '</div>';
                }
            }
        } catch (e) { /* ignore */ }

        let html = '<div class="bio-ui-json-blocks spa-chat-embed-blocks d-flex flex-column gap-3 w-100">';
        blocks.forEach(function (b, idx) {
            if (!b || typeof b !== 'object') return;
            const kind = String(b.kind || '');
            const bid = b.id ? String(b.id) : ('block_' + idx);
            html += '<div class="bio-ui-json-block" data-block-kind="' + escapeHtml(kind) + '" data-block-id="' + escapeHtml(bid) + '"></div>';
        });
        html += '</div>';
        // Preservar banner de error si existe
        const existingErr = container.querySelector('[data-ui-json-error="1"]');
        const errHtml = existingErr ? existingErr.outerHTML : '';
        container.innerHTML = errHtml + html;

        blocks.forEach(function (b, idx) {
            if (!b || typeof b !== 'object') return;
            const kind = String(b.kind || '');
            const bid = b.id ? String(b.id) : ('block_' + idx);
            const mount = container.querySelector('[data-block-id="' + CSS.escape(bid) + '"]');
            if (!mount) return;
            if (kind === 'list') {
                renderUiJsonListBlock(b, mount, options);
            } else if (kind === 'fields') {
                renderUiJsonFieldsBlock(b, mount, options);
            } else {
                mount.innerHTML = '<div class="alert alert-warning mb-0">Block no soportado: ' + escapeHtml(kind) + '</div>';
            }
        });
    }

    function firstUiErrorMessage(errors) {
        try {
            if (!errors || typeof errors !== 'object') return '';
            // Preferir `_error` si existe
            if (errors._error && Array.isArray(errors._error) && errors._error.length >= 1) {
                const s = String(errors._error[0] || '').trim();
                if (s) return s;
            }
            const ks = Object.keys(errors);
            for (let i = 0; i < ks.length; i++) {
                const k = ks[i];
                const v = errors[k];
                if (Array.isArray(v) && v.length >= 1) {
                    const s = String(v[0] || '').trim();
                    if (s) return s;
                }
                if (typeof v === 'string' && String(v).trim() !== '') {
                    return String(v).trim();
                }
            }
        } catch (e) { /* ignore */ }
        return '';
    }

    function setInlineButtonSpinner(btn, loading) {
        if (!btn) return;
        try {
            if (!btn.dataset.originalHtml) {
                btn.dataset.originalHtml = btn.innerHTML;
            }
            if (loading) {
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
            } else if (btn.dataset.originalHtml) {
                btn.innerHTML = btn.dataset.originalHtml;
            }
        } catch (e) { /* ignore */ }
    }

    function markInlineButtonConfirmed(btn) {
        if (!btn) return;
        try {
            btn.disabled = true;
            btn.classList.remove('btn-primary', 'btn-success');
            btn.classList.add('btn-secondary');
            btn.textContent = 'Confirmado';
        } catch (e) { /* ignore */ }
    }

    function initCustomWidgetsInContainer(container, fields) {
        const list = Array.isArray(fields) ? fields : [];
        list.forEach(function (fd) {
            if (!fd || typeof fd !== 'object') return;
            if (String(fd.type || '') !== 'custom_widget') return;
            const wid = fd.widget_id ? String(fd.widget_id) : '';
            if (!wid) return;
            const assets = fd.assets && typeof fd.assets === 'object' ? fd.assets : null;
            const run = () => {
                container.querySelectorAll('.bio-ui-custom-widget').forEach(el => {
                    if (el.getAttribute('data-bio-ui-widget') !== wid) return;
                    const w = window.BioenlaceUiWidgets && window.BioenlaceUiWidgets[wid];
                    if (w && typeof w.init === 'function') {
                        try { w.init(el, fd); } catch (err) { console.error('[SPA] custom_widget init', wid, err); }
                    }
                });
            };
            if (assets) {
                ensureAssetsLoaded(assets).then(run);
            } else {
                run();
            }
        });
    }

    function renderUiJsonListBlock(block, container, options = {}) {
        const title = block.title ? String(block.title) : '';
        const items = Array.isArray(block.items) ? block.items : [];
        const draftField = block.draft_field ? String(block.draft_field) : '';
        const selection = block.selection && typeof block.selection === 'object' ? block.selection : {};
        const requiresConfirmation = selection.requires_confirmation === true;

        let locked = false;
        let selectedId = '';

        let html = '<div class="bio-ui-json-list">';
        if (title) {
            html += '<div class="fw-semibold mb-2">' + escapeHtml(title) + '</div>';
        }
        html += '<div class="d-flex gap-2 overflow-auto pb-2 flex-wrap">';
        items.forEach((it) => {
            const id = it && it.id !== undefined ? String(it.id) : '';
            const name = it && (it.name || it.label) ? String(it.name || it.label) : id;
            if (!id) return;
            html += '<button type="button" class="btn btn-outline-primary btn-sm text-nowrap position-relative" data-embed-pick="1" data-embed-id="' + escapeHtml(id) + '" data-embed-label="' + escapeHtml(name) + '">';
            html += '<span class="bio-ui-pick-check position-absolute top-50 end-0 translate-middle badge rounded-pill bg-success d-none" aria-hidden="true">✓</span>';
            html += escapeHtml(name);
            html += '</button>';
        });
        html += '</div>';
        if (requiresConfirmation) {
            html += '<div class="d-flex justify-content-end pt-2">';
            html += '<button type="button" class="btn btn-primary btn-sm" data-embed-confirm="1" disabled>Confirmar</button>';
            html += '</div>';
        }
        html += '</div>';
        container.innerHTML = html;

        const pickButtons = Array.from(container.querySelectorAll('button[data-embed-pick="1"]'));
        const confirmBtn = container.querySelector('button[data-embed-confirm="1"]');

        function setSelected(btn, id) {
            selectedId = id || '';
            pickButtons.forEach(b => {
                b.classList.remove('border', 'border-3');
                const ck = b.querySelector('.bio-ui-pick-check');
                if (ck) ck.classList.add('d-none');
            });
            if (btn) {
                btn.classList.add('border', 'border-3');
                const ck = btn.querySelector('.bio-ui-pick-check');
                if (ck) ck.classList.remove('d-none');
            }
            if (confirmBtn) confirmBtn.disabled = !selectedId;
        }

        function confirmSelection() {
            if (locked) return;
            if (!draftField) return;
            if (!selectedId) return;
            locked = true;
            try {
                pickButtons.forEach(b => { b.disabled = true; b.classList.add('disabled'); });
            } catch (e) { /* ignore */ }
            if (confirmBtn) markInlineButtonConfirmed(confirmBtn);
            try { draft = Object.assign({}, draft || {}, { [draftField]: selectedId }); } catch (e) { /* ignore */ }
            setTimeout(() => {
                if (queryInput) queryInput.value = '';
                handleSendQuery('');
            }, 0);
        }

        pickButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                if (locked) return;
                const id = this.getAttribute('data-embed-id') || '';
                if (!id) return;
                setSelected(this, id);
                if (!requiresConfirmation) confirmSelection();
            });
        });
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function () {
                if (locked) return;
                confirmSelection();
            });
        }
    }

    function renderUiJsonFieldsBlock(block, container, options = {}) {
        const title = block.title ? String(block.title) : '';
        const fields = Array.isArray(block.fields) ? block.fields : [];
        const submitUrl = options.url || null;

        let html = '<div class="bio-ui-json-fields">';
        if (title) {
            html += '<div class="fw-semibold mb-2">' + escapeHtml(title) + '</div>';
        }
        html += '<form data-ui-json-form="1">';
        fields.forEach(function (fd) {
            html += renderFormField(fd);
        });
        html += '<div class="d-flex justify-content-end pt-2">';
        html += '<button type="button" class="btn btn-success btn-sm" data-ui-json-submit="1">Confirmar</button>';
        html += '</div>';
        html += '</form></div>';
        container.innerHTML = html;

        const form = container.querySelector('form[data-ui-json-form="1"]');
        const submitBtn = container.querySelector('button[data-ui-json-submit="1"]');
        if (!form || !submitBtn || !submitUrl) return;

        initCustomWidgetsInContainer(container, fields);
        attachAutocompleteHandlers(container);

        submitBtn.addEventListener('click', function () {
            if (submitBtn.disabled) return;
            setInlineButtonSpinner(submitBtn, true);
            submitBtn.disabled = true;

            const body = new URLSearchParams();
            try {
                const fd = new FormData(form);
                fd.forEach((v, k) => { if (v != null && String(v) !== '') body.set(k, String(v)); });
            } catch (e) { /* ignore */ }
            try {
                if (window.spaConfig && window.spaConfig.csrfToken) {
                    body.set('_csrf', String(window.spaConfig.csrfToken));
                }
            } catch (e) { /* ignore */ }

            fetch(submitUrl, {
                method: 'POST',
                headers: window.BioenlaceApiClient.mergeHeaders({
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }),
                credentials: 'same-origin',
                body
            })
                .then(r => r.json().then(j => ({ ok: r.ok, json: j })))
                .then(({ ok, json }) => {
                    if (json && json.kind === 'ui_submit_result' && json.success) {
                        try {
                            container.querySelectorAll('input, select, textarea, button').forEach(function (el) { el.disabled = true; });
                        } catch (e) { /* ignore */ }
                        markInlineButtonConfirmed(submitBtn);
                        if (currentIntentId) {
                            setTimeout(() => { try { handleSendQuery(''); } catch (e) { /* ignore */ } }, 50);
                        }
                        return;
                    }
                    if (json && json.kind === 'ui_definition') {
                        // Error de validación: re-render y mostrar banner con `errors`.
                        renderDynamicUi(json, container, { url: submitUrl });
                        return;
                    }
                    container.innerHTML = '<div class="alert alert-danger mb-0">Error al guardar.</div>';
                })
                .catch(() => {
                    container.innerHTML = '<div class="alert alert-danger mb-0">Error de red al guardar.</div>';
                });
        });
    }

    // Nota: el soporte legacy de wizard/steps fue eliminado (corte total). Ver `renderUiJsonBlocks()`.
    /**
     * Renderizar campo de formulario
     */
    function renderCustomWidgetField(field) {
        const wid = field.widget_id || '';
        let html = '<div class="mb-3 bio-ui-custom-widget" data-bio-ui-widget="' + escapeHtml(wid) + '">';
        if (field.label) {
            html += '<label class="form-label">' + escapeHtml(field.label);
            if (field.required) {
                html += ' <span class="text-danger">*</span>';
            }
            html += '</label>';
        }
        const initial = field.initial_values && typeof field.initial_values === 'object' ? field.initial_values : {};
        (field.value_fields || []).forEach(name => {
            const v = initial[name] !== undefined && initial[name] !== null ? String(initial[name]) : '';
            html += '<input type="hidden" name="' + escapeHtml(name) + '" value="' + escapeHtml(v) + '">';
        });
        html += '<div class="table-responsive"><table class="w-100" data-weekly-scheduler-mount></table></div>';
        html += '</div>';
        return html;
    }

    function renderFormField(field) {
        if (field.type === 'hidden') {
            const v = field.value !== undefined && field.value !== null ? String(field.value) : '';
            return '<input type="hidden" name="' + escapeHtml(field.name) + '" value="' + escapeHtml(v) + '">';
        }
        if (field.type === 'custom_widget') {
            return renderCustomWidgetField(field);
        }

        let html = '<div class="mb-3">';
        html += '<label class="form-label">' + escapeHtml(field.label || '');
        if (field.required) {
            html += ' <span class="text-danger">*</span>';
        }
        html += '</label>';
        
        switch (field.type) {
            case 'autocomplete':
                html += renderAutocompleteField(field);
                break;
            case 'select':
                html += renderSelectField(field);
                break;
            case 'radio':
                html += renderRadioField(field);
                break;
            case 'number':
                html += renderNumberField(field);
                break;
            case 'date':
                html += renderDateField(field);
                break;
            case 'textarea':
                html += renderTextareaField(field);
                break;
            default:
                html += renderTextField(field);
        }
        
        html += '</div>';
        return html;
    }

    /**
     * Renderizar campo autocomplete (opciones remotas desde endpoint).
     * Soporta `show_search: false` + `filters` (chips) para flujos como slots de turnos.
     * Nota: implementación liviana para wizard web.
     */
    function renderAutocompleteField(field) {
        const showSearch = field.show_search !== false;
        const endpoint = field.endpoint || '';
        const filters = Array.isArray(field.filters) ? field.filters : [];
        const id = 'ac_' + (field.name || '').replace(/[^a-z0-9_]/gi, '_') + '_' + Math.floor(Math.random() * 100000);

        let html = '';
        // Valor seleccionado (hidden) + preview (readonly)
        html += '<input type="hidden" name="' + escapeHtml(field.name) + '" id="' + id + '_value" value="' + escapeHtml(field.value || '') + '">';
        html += '<div class="input-group">';
        html += '<input type="text" class="form-control" id="' + id + '_text" placeholder="Seleccionar..." readonly>';
        html += '<button type="button" class="btn btn-outline-primary" data-ac-field="' + escapeHtml(field.name || '') + '" data-ac-open="' + id + '">Elegir</button>';
        html += '</div>';
        html += '<div class="mt-2 d-none" data-ac-panel="' + id + '"></div>';

        // Guardar metadata en data-* para el handler
        html += '<div class="d-none"'
            + ' data-ac-meta="' + id + '"'
            + ' data-ac-endpoint="' + escapeHtml(endpoint) + '"'
            + ' data-ac-show-search="' + (showSearch ? '1' : '0') + '"'
            + ' data-ac-filters=\'' + escapeHtml(JSON.stringify(filters)) + '\''
            + ' data-ac-params=\'' + escapeHtml(JSON.stringify(field.params || {})) + '\''
            + '></div>';
        return html;
    }

    /**
     * Extrae valores del wizard form para armar query params según mapping.
     */
    function buildEndpointParamsFromWizardForm(paramsMapping) {
        const form = document.getElementById('wizard-form');
        if (!form) return {};
        const fd = new FormData(form);
        const out = {};
        if (paramsMapping && typeof paramsMapping === 'object') {
            Object.keys(paramsMapping).forEach((paramName) => {
                const fieldName = paramsMapping[paramName];
                if (!fieldName) return;
                const v = fd.get(fieldName);
                if (v !== null && ('' + v).trim() !== '') {
                    out[paramName] = v;
                }
            });
        }
        return out;
    }

    /**
     * Inicializa handlers de autocomplete dentro del wizard (se llama tras renderCurrentStep).
     */
    function attachAutocompleteHandlers(root) {
        const base = root && typeof root.querySelectorAll === 'function' ? root : document;
        const buttons = base.querySelectorAll('[data-ac-open]');
        buttons.forEach(btn => {
            if (btn.getAttribute('data-ac-bound') === '1') return;
            btn.setAttribute('data-ac-bound', '1');
            btn.addEventListener('click', async function () {
                const id = this.getAttribute('data-ac-open');
                const metaEl = base.querySelector('[data-ac-meta="' + id + '"]');
                const panel = base.querySelector('[data-ac-panel="' + id + '"]');
                if (!metaEl || !panel) return;

                const endpoint = metaEl.getAttribute('data-ac-endpoint') || '';
                const filters = JSON.parse(metaEl.getAttribute('data-ac-filters') || '[]');
                const paramsMapping = JSON.parse(metaEl.getAttribute('data-ac-params') || '{}');
                const params = buildEndpointParamsFromWizardForm(paramsMapping);

                panel.classList.remove('d-none');
                panel.innerHTML = '<div class="text-muted small">Cargando...</div>';
                try {
                    const url = new URL(endpoint, window.location.origin);
                    Object.keys(params).forEach(k => url.searchParams.set(k, params[k]));
                    const res = await fetch(url.toString(), { headers: window.BioenlaceApiClient.mergeHeaders({ 'Accept': 'application/json' }) });
                    const data = await res.json();

                    // Soporte slots-disponibles-como-paciente (por_dia)
                    let items = [];
                    if (endpoint.includes('slots-disponibles-como-paciente') && Array.isArray(data.por_dia)) {
                        // Derivar filtros básicos si existen
                        const wantsDia = filters.some(f => f && f.id === 'dia');
                        const wantsFranja = filters.some(f => f && f.id === 'franja');
                        const dias = wantsDia ? data.por_dia.map(d => d.fecha).filter(Boolean) : [];
                        const uniqDias = [...new Set(dias)];
                        let selectedDia = uniqDias[0] || null;
                        let selectedFranja = wantsFranja ? 'manana' : null;

                        const chipsHtml = [];
                        if (wantsDia && uniqDias.length) {
                            chipsHtml.push('<div class="mb-2"><div class="d-flex gap-2 overflow-auto" style="white-space:nowrap;">'
                                + uniqDias.map(fecha => '<button type="button" class="btn btn-sm ' + (fecha === selectedDia ? 'btn-primary' : 'btn-outline-primary') + '" data-ac-chip-dia="' + id + '" data-value="' + escapeHtml(fecha) + '">' + escapeHtml(fecha) + '</button>').join('')
                                + '</div></div>');
                        }
                        if (wantsFranja) {
                            const franjas = [{ v: 'manana', l: 'Mañana' }, { v: 'tarde', l: 'Tarde' }];
                            chipsHtml.push('<div class="mb-2"><div class="d-flex gap-2 overflow-auto" style="white-space:nowrap;">'
                                + franjas.map(fr => '<button type="button" class="btn btn-sm ' + (fr.v === selectedFranja ? 'btn-primary' : 'btn-outline-primary') + '" data-ac-chip-franja="' + id + '" data-value="' + fr.v + '">' + fr.l + '</button>').join('')
                                + '</div></div>');
                        }

                        function rebuildItems() {
                            items = [];
                            data.por_dia.forEach(d => {
                                if (!d || !d.fecha) return;
                                if (selectedDia && d.fecha !== selectedDia) return;
                                function add(list, franjaLabel) {
                                    (list || []).forEach(s => {
                                        if (!s) return;
                                        const idRrsa = s.id_rrhh_servicio_asignado;
                                        const hora = s.hora;
                                        if (!idRrsa || !hora) return;
                                        const value = '' + idRrsa + '|' + d.fecha + '|' + hora;
                                        items.push({ value: value, label: d.fecha + ' · ' + franjaLabel + ' · ' + hora });
                                    });
                                }
                                if (!selectedFranja) {
                                    add(d.manana, 'Mañana');
                                    add(d.tarde, 'Tarde');
                                } else if (selectedFranja === 'manana') {
                                    add(d.manana, 'Mañana');
                                } else {
                                    add(d.tarde, 'Tarde');
                                }
                            });
                            renderItems();
                        }

                        function renderItems() {
                            panel.innerHTML = chipsHtml.join('') + (items.length ? '<div class="d-flex gap-2 overflow-auto" style="white-space:nowrap;">'
                                + items.map(it => '<button type="button" class="btn btn-sm btn-outline-secondary" data-ac-item="' + id + '" data-value="' + escapeHtml(it.value) + '" data-label="' + escapeHtml(it.label) + '">' + escapeHtml(it.label) + '</button>').join('')
                                + '</div>' : '<div class="text-muted small">Sin resultados</div>');

                            // Bind chips/items
                            panel.querySelectorAll('[data-ac-chip-dia="' + id + '"]').forEach(b => b.addEventListener('click', () => { selectedDia = b.getAttribute('data-value'); rebuildItems(); }));
                            panel.querySelectorAll('[data-ac-chip-franja="' + id + '"]').forEach(b => b.addEventListener('click', () => { selectedFranja = b.getAttribute('data-value'); rebuildItems(); }));
                            panel.querySelectorAll('[data-ac-item="' + id + '"]').forEach(b => b.addEventListener('click', () => {
                                const v = b.getAttribute('data-value') || '';
                                const l = b.getAttribute('data-label') || '';
                                const valueEl = document.getElementById(id + '_value');
                                const textEl = document.getElementById(id + '_text');
                                if (valueEl) valueEl.value = v;
                                if (textEl) textEl.value = l;
                                panel.classList.add('d-none');
                            }));
                        }

                        rebuildItems();
                        return;
                    }

                    // Fallback: intentar results/items/data as list
                    const arr = Array.isArray(data.results) ? data.results
                        : (data.data && Array.isArray(data.data.results) ? data.data.results
                            : (Array.isArray(data.items) ? data.items : (Array.isArray(data.data) ? data.data : [])));
                    items = arr.map(it => {
                        const v = (it && typeof it === 'object') ? (it.id ?? it.value ?? '') : ('' + it);
                        const l = (it && typeof it === 'object') ? (it.text ?? it.name ?? it.label ?? v) : ('' + it);
                        return { value: '' + v, label: '' + l };
                    });
                    panel.innerHTML = items.length
                        ? '<div class="d-flex gap-2 overflow-auto" style="white-space:nowrap;">' + items.map(it => '<button type="button" class="btn btn-sm btn-outline-secondary" data-ac-item="' + id + '" data-value="' + escapeHtml(it.value) + '" data-label="' + escapeHtml(it.label) + '">' + escapeHtml(it.label) + '</button>').join('') + '</div>'
                        : '<div class="text-muted small">Sin resultados</div>';
                    panel.querySelectorAll('[data-ac-item="' + id + '"]').forEach(b => b.addEventListener('click', () => {
                        const v = b.getAttribute('data-value') || '';
                        const l = b.getAttribute('data-label') || '';
                        const valueEl = document.getElementById(id + '_value');
                        const textEl = document.getElementById(id + '_text');
                        if (valueEl) valueEl.value = v;
                        if (textEl) textEl.value = l;
                        panel.classList.add('d-none');
                    }));
                } catch (e) {
                    panel.innerHTML = '<div class="text-danger small">Error cargando opciones</div>';
                }
            });
        });
    }

    /**
     * Renderizar campo select
     */
    function renderSelectField(field) {
        const fv = field.value !== undefined && field.value !== null ? String(field.value) : '';
        let html = '<select class="form-select" name="' + escapeHtml(field.name) + '"' + (field.required ? ' required' : '') + '>';
        html += '<option value="">Seleccione...</option>';
        if (field.options) {
            field.options.forEach(option => {
                const value = typeof option === 'object' ? option.value : option;
                const label = typeof option === 'object' ? option.label : option;
                const sel = String(value) === fv ? ' selected' : '';
                html += '<option value="' + escapeHtml(value) + '"' + sel + '>' + escapeHtml(label) + '</option>';
            });
        }
        html += '</select>';
        return html;
    }

    /**
     * Renderizar campo radio (opciones seleccionables)
     */
    function renderRadioField(field) {
        let html = '<div class="d-flex flex-wrap gap-2">';
        if (field.options) {
            field.options.forEach(option => {
                const value = typeof option === 'object' ? option.value : option;
                const label = typeof option === 'object' ? option.label : option;
                const id = field.name + '_' + value;
                html += '<div class="form-check">';
                const checked = field.value !== undefined && field.value !== null && String(value) === String(field.value) ? ' checked' : '';
                html += '<input class="form-check-input" type="radio" name="' + escapeHtml(field.name) + '" id="' + id + '" value="' + escapeHtml(value) + '"' + (field.required ? ' required' : '') + checked + '>';
                html += '<label class="form-check-label" for="' + id + '">' + escapeHtml(label) + '</label>';
                html += '</div>';
            });
        }
        html += '</div>';
        return html;
    }

    /**
     * Renderizar campo numérico con opciones rápidas y botones +/-
     */
    function renderNumberField(field) {
        let html = '';
        
        // Opciones rápidas
        if (field.quick_options && field.quick_options.length > 0) {
            html += '<div class="mb-2 d-flex gap-2 flex-wrap">';
            field.quick_options.forEach(option => {
                html += '<button type="button" class="btn btn-outline-primary btn-sm quick-option-btn" data-field="' + escapeHtml(field.name) + '" data-value="' + option + '">' + option + '</button>';
            });
            html += '</div>';
        }
        
        // Input numérico con botones +/-
        html += '<div class="input-group">';
        html += '<button type="button" class="btn btn-outline-secondary number-decrement" data-field="' + escapeHtml(field.name) + '">-</button>';
        html += '<input type="number" class="form-control text-center" name="' + escapeHtml(field.name) + '" value="' + (field.value || '') + '"';
        if (field.min !== null) html += ' min="' + field.min + '"';
        if (field.max !== null) html += ' max="' + field.max + '"';
        if (field.step) html += ' step="' + field.step + '"';
        if (field.required) html += ' required';
        html += '>';
        html += '<button type="button" class="btn btn-outline-secondary number-increment" data-field="' + escapeHtml(field.name) + '">+</button>';
        html += '</div>';
        
        return html;
    }

    /**
     * Renderizar campo de fecha
     */
    function renderDateField(field) {
        let html = '<input type="date" class="form-control" name="' + escapeHtml(field.name) + '" value="' + (field.value || '') + '"' + (field.required ? ' required' : '') + '>';
        return html;
    }

    /**
     * Renderizar campo de texto
     */
    function renderTextField(field) {
        let html = '<input type="text" class="form-control" name="' + escapeHtml(field.name) + '" value="' + (field.value || '') + '"' + (field.required ? ' required' : '') + '>';
        return html;
    }

    /**
     * Renderizar campo textarea
     */
    function renderTextareaField(field) {
        let html = '<textarea class="form-control" name="' + escapeHtml(field.name) + '" rows="3"' + (field.required ? ' required' : '') + '>' + (field.value || '') + '</textarea>';
        return html;
    }

    /**
     * Renderizar acción CRUD (navegación, eliminación, etc)
     */
    function renderCrudAction(data) {
        let html = '<div class="col-12">';
        
        if (data.action.type === 'delete' && data.action.requires_confirmation) {
            html += '<div class="alert alert-warning">';
            html += '<p>' + escapeHtml(data.explanation) + '</p>';
            html += '<button class="btn btn-danger" onclick="confirmDelete(\'' + escapeHtml(data.action.route) + '\', ' + (data.entity_id || 'null') + ')">Confirmar Eliminación</button>';
            html += '<button class="btn btn-secondary ms-2" onclick="cancelDelete()">Cancelar</button>';
            html += '</div>';
        } else {
            html += '<div class="card"><div class="card-body">';
            html += '<p>' + escapeHtml(data.explanation) + '</p>';
            html += '<a href="' + escapeHtml(data.action.route) + '" class="btn btn-primary">Continuar</a>';
            html += '</div></div>';
        }
        
        html += '</div>';
        return html;
    }

    /**
     * Adjuntar listeners a formulario dinámico
     */
    function attachFormListeners() {
        const form = document.getElementById('crud-dynamic-form');
        if (!form) return;
        
        // Submit del formulario
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            submitCrudForm(this);
        });
        
        // Opciones rápidas para números
        document.querySelectorAll('.quick-option-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const fieldName = this.dataset.field;
                const value = this.dataset.value;
                const input = form.querySelector('input[name="' + fieldName + '"]');
                if (input) {
                    input.value = value;
                    // Resaltar botón seleccionado
                    document.querySelectorAll('.quick-option-btn[data-field="' + fieldName + '"]').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                }
            });
        });
        
        // Botones incremento/decremento
        document.querySelectorAll('.number-increment').forEach(btn => {
            btn.addEventListener('click', function() {
                const fieldName = this.dataset.field;
                const input = form.querySelector('input[name="' + fieldName + '"]');
                if (input) {
                    const current = parseInt(input.value) || 0;
                    const step = parseFloat(input.step) || 1;
                    input.value = current + step;
                }
            });
        });
        
        document.querySelectorAll('.number-decrement').forEach(btn => {
            btn.addEventListener('click', function() {
                const fieldName = this.dataset.field;
                const input = form.querySelector('input[name="' + fieldName + '"]');
                if (input) {
                    const current = parseInt(input.value) || 0;
                    const step = parseFloat(input.step) || 1;
                    const min = input.min ? parseFloat(input.min) : null;
                    const newValue = current - step;
                    if (min === null || newValue >= min) {
                        input.value = newValue;
                    }
                }
            });
        });
    }

    /**
     * Enviar formulario CRUD
     */
    function submitCrudForm(form) {
        const formData = new FormData(form);
        const action = form.dataset.formAction;
        const intention = form.dataset.intention;
        const entityId = form.dataset.entityId;
        
        // Agregar entity_id si es update
        if (intention === 'update' && entityId) {
            formData.append('id', entityId);
        }
        
        // Agregar CSRF
        formData.append('_csrf', window.spaConfig.csrfToken);
        
        // Mostrar loading
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';
        
        fetch(action, {
            method: 'POST',
            body: formData,
            headers: window.BioenlaceApiClient.mergeHeaders({
                'X-Requested-With': 'XMLHttpRequest'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showError('Operación realizada exitosamente');
                form.reset();
                // Opcional: recargar o redirigir
                if (data.url_siguiente) {
                    window.location.href = data.url_siguiente;
                }
            } else {
                showError(data.msg || data.error || 'Error al guardar');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Error de conexión al guardar');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }

    /**
     * Confirmar eliminación
     */
    window.confirmDelete = function(route, entityId) {
        if (!confirm('¿Estás seguro de que quieres eliminar este registro? Esta acción no se puede deshacer.')) {
            return;
        }
        
        const formData = new URLSearchParams({
            id: entityId,
            _csrf: window.spaConfig.csrfToken
        });
        
        fetch(route, {
            method: 'POST',
            headers: window.BioenlaceApiClient.mergeHeaders({
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            }),
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showError('Registro eliminado exitosamente');
            } else {
                showError(data.error || 'Error al eliminar');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Error de conexión al eliminar');
        });
    };

    /**
     * Cancelar eliminación
     */
    window.cancelDelete = function() {
        hideResponse();
    };

    /**
     * Manejar teclado en textarea
     */
    function handleKeyDown(e) {
        // Enter para enviar, Shift+Enter para nueva línea
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSendQuery();
        }
    }

    /**
     * Manejar input en textarea
     */
    function handleInput() {
        // Si el usuario empieza a escribir manualmente, colapsar atajos.
        try {
            if (queryInput && String(queryInput.value || '').trim() !== '') {
                collapseShortcutsPanel();
            }
        } catch (e) { /* ignore */ }
        // Auto-resize textarea
        queryInput.style.height = 'auto';
        queryInput.style.height = queryInput.scrollHeight + 'px';
    }

    /**
     * Mostrar respuesta de la IA
     */
    function displayResponse(explanation, actions) {
        // Mostrar explicación - si contiene HTML, no escapar, solo escapar el texto plano
        // Detectar si explanation ya contiene HTML (tags)
        let explanationHtml = explanation;
        if (explanation && !/<[a-z][\s\S]*>/i.test(explanation)) {
            // No contiene HTML, escapar el texto
            explanationHtml = escapeHtml(explanation);
        }
        explanationDiv.innerHTML = '<p class="mb-0">' + explanationHtml + '</p>';
        
        // Mostrar acciones
        if (actions && actions.length > 0) {
            actionsDiv.innerHTML = renderActionCards(actions);
            attachCardListeners();
        } else {
            actionsDiv.innerHTML = '<div class="col-12"><p class="text-muted mb-0">No se encontraron acciones específicas para esta consulta.</p></div>';
        }

        // Mostrar sección de respuesta
        responseSection.classList.remove('d-none');
        
        // Scroll suave a la respuesta
        setTimeout(() => {
            responseSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 100);
    }

    /**
     * Mostrar respuesta informativa (cuando success es false pero hay explicación útil)
     */
    function displayInfoResponse(explanation, actions, suggestedQuery) {
        // Mostrar explicación - si contiene HTML, no escapar
        let explanationHtml = explanation;
        if (explanation && !/<[a-z][\s\S]*>/i.test(explanation)) {
            explanationHtml = escapeHtml(explanation);
        }
        
        // Mostrar como información (no error)
        explanationDiv.innerHTML = '<div class="alert alert-info mb-0">' + explanationHtml + '</div>';
        
        // Si hay consulta sugerida, agregarla
        if (suggestedQuery) {
            explanationDiv.innerHTML += '<div class="mt-2"><small class="text-muted">Sugerencia: <button type="button" class="btn btn-link btn-sm p-0 align-baseline text-primary spa-suggested-query-btn">' + escapeHtml(suggestedQuery) + '</button></small></div>';
            const b = explanationDiv.querySelector('.spa-suggested-query-btn');
            if (b) {
                b.addEventListener('click', function () {
                    if (queryInput) {
                        queryInput.value = suggestedQuery;
                        handleInput();
                    }
                    handleSendQuery();
                });
            }
        }
        
        // Mostrar acciones si existen
        if (actions && actions.length > 0) {
            actionsDiv.innerHTML = renderActionCards(actions);
            attachCardListeners();
        } else {
            actionsDiv.innerHTML = '<div class="col-12"><p class="text-muted mb-0">No se encontraron acciones específicas para esta consulta.</p></div>';
        }

        // Mostrar sección de respuesta
        responseSection.classList.remove('d-none');
        
        // Scroll suave a la respuesta
        setTimeout(() => {
            responseSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 100);
    }

    /**
     * Mostrar error
     */
    function showError(message) {
        explanationDiv.innerHTML = '<div class="alert alert-danger mb-0">' + escapeHtml(message) + '</div>';
        actionsDiv.innerHTML = '';
        responseSection.classList.remove('d-none');
        if (chatMessagesDiv) {
            setTimeout(scrollChatToBottom, 50);
        }
    }

    /**
     * Ocultar respuesta
     */
    function hideResponse() {
        responseSection.classList.add('d-none');
    }

    /**
     * Renderizar tarjetas de acciones
     * @param {Array} actions - Array de acciones
     * @param {boolean} includeHeader - Si incluir el header "Acciones sugeridas"
     */
    function getClientOpenKind(action) {
        return (action && action.client_open && action.client_open.kind) ? String(action.client_open.kind) : '';
    }

    /**
     * inline | fullscreen — cómo abre el shell SPA (ambos sin layout Yii en el fetch).
     */
    function getClientOpenPresentation(action) {
        // Contrato nuevo: por defecto el motor abre inline. Fullscreen solo manual (por link).
        return 'inline';
    }

    function buildClientOpenUrl(action) {
        const co = action && action.client_open ? action.client_open : {};
        const kind = getClientOpenKind(action);

        if (kind === 'ui_json') {
            const api = co.api || {};
            return api.route || action.route || action.url || '';
        }

        if (kind === 'native') {
            if (co.web && typeof co.web.path === 'string' && co.web.path !== '') {
                let url = co.web.path;
                const q = co.web.query;
                if (q && typeof q === 'object' && Object.keys(q).length > 0) {
                    url += '?' + new URLSearchParams(q).toString();
                }
                return url;
            }
            const api = co.api || {};
            const route = api.route || action.route || action.url || '';
            if (api.query && typeof api.query === 'object' && Object.keys(api.query).length > 0) {
                return route + '?' + new URLSearchParams(api.query).toString();
            }
            return route;
        }

        return action && (action.route || action.url) ? (action.route || action.url) : '';
    }

    function getClientOpenAssets(action) {
        const co = action && action.client_open ? action.client_open : {};
        return co.assets && typeof co.assets === 'object' ? co.assets : null;
    }

    function ensureAssetsLoaded(assets) {
        if (!assets) return Promise.resolve();
        const css = Array.isArray(assets.css) ? assets.css : [];
        const js = Array.isArray(assets.js) ? assets.js : [];

        css.forEach(href => {
            if (!href) return;
            const abs = new URL(href, window.location.origin).href;
            const exists = [...document.querySelectorAll('link[rel="stylesheet"]')].some(l => l.href === abs);
            if (exists) return;
            const l = document.createElement('link');
            l.rel = 'stylesheet';
            l.href = abs;
            l.setAttribute('data-spa-asset', '1');
            document.head.appendChild(l);
        });

        return new Promise((resolve) => {
            let pending = 0;
            function doneOne() {
                pending--;
                if (pending <= 0) resolve();
            }
            if (!js.length) {
                resolve();
                return;
            }
            js.forEach(src => {
                if (!src) return;
                const abs = new URL(src, window.location.origin).href;
                const exists = [...document.querySelectorAll('script[src]')].some(s => s.src === abs);
                if (exists) return;
                pending++;
                const s = document.createElement('script');
                s.src = abs;
                s.async = false;
                s.setAttribute('data-spa-asset', '1');
                s.onload = doneOne;
                s.onerror = doneOne;
                document.body.appendChild(s);
            });
            if (pending === 0) resolve();
        });
    }

    function renderActionCards(actions, includeHeader = true) {
        if (!actions || actions.length === 0) {
            return '';
        }

        let html = '';
        if (includeHeader) {
            html = '<div class="col-12"><h6 class="mb-3 fw-semibold">Acciones sugeridas:</h6></div>';
        }

        actions.forEach((action, index) => {
            const cardId = `action-card-${Date.now()}-${index}`;
            
            // Generar nombre y descripción si no existen
            const actionName = action.name || action.display_name || 'Ver detalles';
            const actionDescription = action.description || '';
            
            const kind = getClientOpenKind(action);
            const url = buildClientOpenUrl(action);
            const assets = getClientOpenAssets(action);
            const actionId = action.action_id || '';
            let expandable = false;
            let fullPage = false;
            if (kind === 'native' || kind === 'ui_json') {
                // Contrato nuevo: el motor abre inline; fullscreen solo manual por link fuera del motor.
                expandable = true;
                fullPage = false;
            } else if (kind === 'intent') {
                // Intent conversacional: se dispara vía /api/v1/asistente/enviar con action_id.
                expandable = false;
                fullPage = false;
            }
 
            html += `
                <div class="col-12">
                    <div class="card h-100 spa-card shadow-sm" data-card-id="${cardId}" data-expandable="${expandable}" data-full-page="${fullPage}" data-open-kind="${escapeHtml(kind)}" data-action-url="${escapeHtml(url)}" data-action-id="${escapeHtml(String(actionId))}" data-action-assets='${assets ? escapeHtml(JSON.stringify(assets)) : ""}'>
                        <div class="card-body">
                            <h6 class="card-title text-primary fw-semibold mb-2">${escapeHtml(actionName)}</h6>
                            <p class="card-text text-muted small mb-0">${escapeHtml(actionDescription)}</p>
                            <div class="spa-card-expand-content d-none mt-3"></div>
                        </div>
                    </div>
                </div>
            `;
        });

        return html;
    }

    /**
     * Navegar a una URL dentro del stack SPA (p. ej. desde listados con links secundarios)
     * @param {string} url - URL absoluta o relativa al sitio
     * @param {string} [title] - Título de la página en el stack
     */
    function spaNavigateToUrl(url, title) {
        if (!url) {
            return;
        }
        const pageId = generatePageId(url);
        const cardTitle = title || 'Cargando...';
        navigateTo(pageId, cardTitle, '<p>Cargando...</p>', { url: url });
        loadPageContent(url, pageId, 'html', null);
    }

    window.spaNavigateToUrl = spaNavigateToUrl;

    /**
     * Adjuntar listeners a los cards .spa-card que aún no tienen listener
     * @param {ParentNode} [root] - Raíz para querySelectorAll (por defecto document)
     */
    function attachCardListeners(root) {
        const base = root && typeof root.querySelectorAll === 'function' ? root : document;
        const cards = base.querySelectorAll('.spa-card:not([data-spa-bound])');
        cards.forEach(card => {
            card.setAttribute('data-spa-bound', '1');
            card.addEventListener('click', function(e) {
                // No hacer nada si se hace click en el contenido expandido
                if (e.target.closest('.spa-card-expand-content')) {
                    return;
                }
                // Links/botones secundarios dentro del card (historia, etc.)
                if (e.target.closest('[data-spa-no-card]')) {
                    return;
                }

                const cardId = this.dataset.cardId;
                const expandable = this.dataset.expandable === 'true';
                const fullPage = this.dataset.fullPage === 'true';
                const actionUrl = this.dataset.actionUrl;
                const kind = this.dataset.openKind || '';
                const actionId = this.dataset.actionId || '';
                let assets = null;
                try {
                    assets = this.dataset.actionAssets ? JSON.parse(this.dataset.actionAssets) : null;
                } catch (e) {
                    assets = null;
                }

                if (kind === 'intent') {
                    // Disparar intent conversacional por action_id.
                    const asistenteUrl = window.location.origin + '/api/v1/asistente/enviar';
                    if (!actionId) {
                        alert('Acción inválida: falta action_id');
                        return;
                    }
                    setLoadingState(true);
                    fetch(asistenteUrl, {
                        method: 'POST',
                        headers: window.BioenlaceApiClient.mergeHeaders({
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }),
                        body: JSON.stringify({ action_id: String(actionId) })
                    })
                    .then(async (res) => {
                        const ct = res.headers.get('content-type') || '';
                        const payload = ct.includes('application/json') ? await res.json() : null;
                        if (!res.ok) {
                            const msg = payload && payload.message ? String(payload.message) : ('Error HTTP ' + res.status);
                            throw new Error(msg);
                        }
                        if (!payload || typeof payload !== 'object') {
                            throw new Error('Respuesta inválida del servidor');
                        }
                        // Reusar la misma tubería de render de mensajes del asistente.
                        handleAssistantResponse(payload);
                    })
                    .catch((e) => {
                        alert(e && e.message ? e.message : 'No se pudo ejecutar la acción');
                    })
                    .finally(() => {
                        setLoadingState(false);
                    });
                    return;
                }

                if (fullPage) {
                    // Abrir nueva página
                    const titleEl = this.querySelector('.card-title');
                    if (kind === 'ui_json') {
                        openFullPage(actionUrl, titleEl ? titleEl.textContent : 'Cargando...', 'ui', assets);
                    } else if (kind === 'native') {
                        openFullPage(actionUrl, titleEl ? titleEl.textContent : 'Cargando...', 'native', assets);
                    } else {
                        openFullPage(actionUrl, titleEl ? titleEl.textContent : 'Cargando...', 'html', assets);
                    }
                } else if (expandable) {
                    // Expandir in-place
                    toggleCardExpansion(cardId, actionUrl, kind, assets);
                } else {
                    if (actionUrl) {
                        window.location.href = actionUrl;
                    }
                }
            });
        });
    }

    window.attachSpaCardListeners = attachCardListeners;

    /**
     * Alternar expansión de card
     */
    function toggleCardExpansion(cardId, actionUrl, kind, assets) {
        const card = document.querySelector(`[data-card-id="${cardId}"]`);
        if (!card) return;

        const expandContent = card.querySelector('.spa-card-expand-content');
        const isExpanded = expandedCards.has(cardId);

        if (isExpanded) {
            // Colapsar
            expandContent.classList.add('d-none');
            card.classList.remove('spa-card-expanded');
            expandedCards.delete(cardId);
        } else {
            // Expandir
            card.classList.add('spa-card-expanding');
            expandContent.classList.remove('d-none');
            
            // Si no tiene contenido, cargarlo
            if (!expandContent.innerHTML || expandContent.innerHTML.trim() === '') {
                expandContent.innerHTML = '<div class="d-flex align-items-center justify-content-center gap-2 py-3 text-muted"><div class="spinner-border spinner-border-sm"></div> Cargando...</div>';
                loadCardContent(actionUrl, kind, assets, expandContent);
            }

            // Animación
            setTimeout(() => {
                card.classList.remove('spa-card-expanding');
                card.classList.add('spa-card-expanded');
                expandedCards.set(cardId, true);
            }, 50);
        }
    }

    /**
     * Cargar contenido de card vía AJAX
     */
    function loadCardContent(url, kind, assets, container) {
        if (!url) {
            container.innerHTML = '<div class="alert alert-warning">No hay contenido disponible</div>';
            return;
        }

        const fullUrl = resolveSpaFetchUrl(url);

        if (kind === 'ui_json') {
            fetch(fullUrl, {
                method: 'GET',
                headers: window.BioenlaceApiClient.mergeHeaders({
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error al cargar UI JSON');
                }
                return response.json();
            })
            .then(json => {
                container.innerHTML = '';
                if (json && json.kind === 'ui_definition') {
                    renderDynamicUi(json, container, { url: fullUrl });
                } else {
                    container.innerHTML = '<div class="alert alert-warning">La respuesta no es una definición de UI válida.</div>';
                }
            })
            .catch(error => {
                console.error('Error cargando UI JSON (inline):', error);
                container.innerHTML = '<div class="alert alert-danger">Error al cargar la UI</div>';
            });
            return;
        }

        ensureAssetsLoaded(assets).then(() => {
            return fetch(fullUrl, {
                method: 'GET',
                headers: window.BioenlaceApiClient.mergeHeaders({
                    'X-Requested-With': 'XMLHttpRequest'
                })
            });
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error al cargar contenido');
            }
            return response.text();
        })
        .then(html => {
            container.innerHTML = html;
            initializeNativeFragments(container);
        })
        .catch(error => {
            console.error('Error cargando contenido:', error);
            container.innerHTML = '<div class="alert alert-danger">Error al cargar el contenido</div>';
        });
    }

    /**
     * Inicializar fragments nativos embebibles.
     * Busca roots con data-native-component y llama a window.BioenlaceNativeComponents[name].init(root).
     */
    function initializeNativeFragments(container) {
        if (!container) return;
        const roots = container.querySelectorAll('[data-native-component]');
        roots.forEach(root => {
            const name = root.getAttribute('data-native-component');
            if (!name) return;
            const registry = window.BioenlaceNativeComponents || {};
            const comp = registry[name];
            if (!comp || typeof comp.init !== 'function') return;
            try {
                comp.init(root);
            } catch (e) {
                console.error('[SPA] Error init native component', name, e);
            }
        });
    }

    /**
     * Generar un ID único basado en la URL
     */
    function generatePageId(url) {
        // Crear un hash simple de la URL para usar como ID estable
        let hash = 0;
        for (let i = 0; i < url.length; i++) {
            const char = url.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convertir a entero de 32 bits
        }
        return 'page-' + Math.abs(hash).toString(36);
    }

    /**
     * Abrir página completa
     */
    function openFullPage(url, title, type, assets) {
        const pageId = generatePageId(url);
        navigateTo(pageId, title, '<div class="d-flex align-items-center justify-content-center py-5"><div class="spinner-border text-primary"></div></div>', { url: url, assets: assets || null });
        loadPageContent(url, pageId, type, assets || null);
    }

    /**
     * Abre UI JSON fullscreen si la URL trae ?spa_open_ui_json=/api/v1/<entidad>/<accion>... (p. ej. redirect desde Yii).
     */
    function tryOpenUiJsonFromQuery() {
        try {
            const params = new URLSearchParams(window.location.search);
            const raw = params.get('spa_open_ui_json');
            if (!raw || !String(raw).trim()) {
                return;
            }
            let path = String(raw).trim();
            if (!path.startsWith('http://') && !path.startsWith('https://')) {
                if (!path.startsWith('/')) {
                    path = '/' + path;
                }
                path = window.location.origin + path;
            }
            const title = params.get('spa_open_ui_title') || 'Formulario';
            openFullPage(path, title, 'ui', null);
        } catch (e) {
            console.warn('[SPA] spa_open_ui_json', e);
        }
    }

    /**
     * Cargar contenido de página vía AJAX
     */
    function loadPageContent(url, pageId, type, assets) {
        if (!url) {
            const pageElement = document.getElementById(`spa-page-${pageId}`);
            if (pageElement) {
                const content = pageElement.querySelector('.spa-page-content');
                if (content) {
                    content.innerHTML = '<div class="alert alert-warning">No hay contenido disponible</div>';
                }
            }
            return;
        }

        let fullUrl = resolveSpaFetchUrl(url);

        // Si es una UI dinámica (JSON), usar el renderizador de UI
        if (type === 'ui') {
            fetch(fullUrl, {
                method: 'GET',
                headers: window.BioenlaceApiClient.mergeHeaders({
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Error HTTP ${response.status}: ${response.statusText}`);
                }
                const contentType = response.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    return response.text().then(text => {
                        console.warn('Se esperaba JSON para UI dinámica, pero se recibió:', text.substring(0, 200));
                        throw new Error('Respuesta no válida para UI dinámica');
                    });
                }
                return response.json();
            })
            .then(json => {
                const pageElement = document.getElementById(`spa-page-${pageId}`);
                if (!pageElement) {
                    console.error(`No se encontró el elemento de página: spa-page-${pageId}`);
                    return;
                }
                const content = pageElement.querySelector('.spa-page-content');
                if (!content) {
                    console.error('No se encontró el contenedor .spa-page-content');
                    return;
                }

                if (json.kind === 'ui_definition') {
                    renderDynamicUi(json, content, { url: fullUrl });
                } else {
                    content.innerHTML = '<div class="alert alert-warning">La respuesta no es una definición de UI válida.</div>';
                }
            })
            .catch(error => {
                console.error('Error cargando UI dinámica:', error);
                const pageElement = document.getElementById(`spa-page-${pageId}`);
                if (pageElement) {
                    const content = pageElement.querySelector('.spa-page-content');
                    if (content) {
                        content.innerHTML = `<div class="alert alert-danger">
                            <strong>Error al cargar la UI dinámica</strong><br>
                            ${error.message}<br>
                            <small>URL: ${fullUrl}</small>
                        </div>`;
                    }
                }
            });

            return;
        }

        // Nativo SPA: HTML partial (sin layout), luego init de componentes.
        if (type === 'native') {
            ensureAssetsLoaded(assets).then(() => {
                return fetch(fullUrl, {
                    method: 'GET',
                    headers: window.BioenlaceApiClient.mergeHeaders({
                        'X-Requested-With': 'XMLHttpRequest'
                    })
                });
            })
            .then(response => {
                if (response.ok) {
                    return response.text();
                }
                throw new Error(`Error HTTP ${response.status}: ${response.statusText}`);
            })
            .then(html => {
                const pageElement = document.getElementById(`spa-page-${pageId}`);
                if (pageElement) {
                    const content = pageElement.querySelector('.spa-page-content');
                    if (content) {
                        content.innerHTML = html;
                        initializeNativeFragments(content);
                    }
                }
            })
            .catch(error => {
                console.error('Error cargando página nativa:', error);
                const pageElement = document.getElementById(`spa-page-${pageId}`);
                if (pageElement) {
                    const content = pageElement.querySelector('.spa-page-content');
                    if (content) {
                        content.innerHTML = `<div class="alert alert-danger">Error al cargar el contenido<br>${escapeHtml(error.message)}</div>`;
                    }
                }
            });
            return;
        }

        // Documento HTML completo (p. ej. navegación secundaria): parsear head/body.
        fetch(fullUrl, {
            method: 'GET',
            headers: window.BioenlaceApiClient.mergeHeaders({
                'X-Requested-With': 'XMLHttpRequest'
            })
        })
        .then(response => {
            if (response.ok) {
                return response.text();
            }
            throw new Error(`Error HTTP ${response.status}: ${response.statusText}`);
        })
        .then(html => {
            const pageElement = document.getElementById(`spa-page-${pageId}`);
            if (pageElement) {
                const content = pageElement.querySelector('.spa-page-content');
                if (content) {
                    aplicarHtmlPaginaEnSpa(content, html, fullUrl, type);
                } else {
                    console.error('No se encontró el contenedor .spa-page-content');
                }
            } else {
                console.error(`No se encontró el elemento de página: spa-page-${pageId}`);
            }
        })
        .catch(error => {
            console.error('Error cargando página:', error);
            const pageElement = document.getElementById(`spa-page-${pageId}`);
            if (pageElement) {
                const content = pageElement.querySelector('.spa-page-content');
                if (content) {
                    content.innerHTML = `<div class="alert alert-danger">
                        <strong>Error al cargar el contenido</strong><br>
                        ${error.message}<br>
                        <small>URL: ${fullUrl}</small>
                    </div>`;
                }
            }
        });
    }

    /**
     * Lista de fragmentos en href/src que ya existen en el shell SPA (no duplicar).
     */
    function getSpaGlobalAssetKeywords() {
        return [
            'bootstrap',
            'jquery',
            'yii.js',
            'bootstrap.bundle',
            'bootstrap.min',
            'ajax-wrapper.js',
            'turnos.js',
        ];
    }

    /**
     * Filtrar assets duplicados dentro de un nodo (body fragment).
     */
    function filtrarAssetsDuplicadosEnElemento(root) {
        if (!root) {
            return;
        }
        const assetsCargados = getSpaGlobalAssetKeywords();
        root.querySelectorAll('link[rel="stylesheet"]').forEach(link => {
            const href = link.getAttribute('href') || '';
            if (assetsCargados.some(asset => href.toLowerCase().includes(asset.toLowerCase()))) {
                link.remove();
            }
        });
        root.querySelectorAll('script[src]').forEach(script => {
            const src = script.getAttribute('src') || '';
            if (assetsCargados.some(asset => src.toLowerCase().includes(asset.toLowerCase()))) {
                script.remove();
            }
        });
    }

    /**
     * Inyecta en document.head los estilos del &lt;head&gt; de la página cargada (URLs absolutas).
     * Evita perder flatpickr/sweetalert2/etc. al meter solo innerHTML del body.
     */
    function injectHeadStylesheetsFromParsedDoc(headEl, basePageUrl) {
        if (!headEl) {
            return;
        }
        const base = basePageUrl.split('#')[0];
        headEl.querySelectorAll('link[rel="stylesheet"][href]').forEach(link => {
            const raw = (link.getAttribute('href') || '').trim();
            if (!raw || raw.startsWith('data:')) {
                return;
            }
            let abs;
            try {
                abs = new URL(raw, base).href;
            } catch (e) {
                return;
            }
            const yaInyectado = [...document.querySelectorAll('link[rel="stylesheet"]')].some(
                n => n.getAttribute('href') === abs || n.getAttribute('data-spa-injected-href') === abs
            );
            if (yaInyectado) {
                return;
            }
            const l = document.createElement('link');
            l.rel = 'stylesheet';
            l.href = abs;
            l.setAttribute('data-spa-injected-href', abs);
            document.head.appendChild(l);
        });
    }

    /**
     * Los &lt;script src&gt; insertados con innerHTML no se ejecutan. Cargarlos en orden.
     */
    function loadExternalScriptsSequential(urls, done) {
        let i = 0;
        function next() {
            if (i >= urls.length) {
                if (typeof done === 'function') {
                    done();
                }
                return;
            }
            const url = urls[i++];
            const yaScript = [...document.querySelectorAll('script[data-spa-injected-src]')].some(
                n => n.getAttribute('data-spa-injected-src') === url
            );
            if (yaScript) {
                next();
                return;
            }
            const el = document.createElement('script');
            el.src = url;
            el.async = false;
            el.setAttribute('data-spa-injected-src', url);
            el.onload = () => next();
            el.onerror = () => {
                console.error('[SPA] No se pudo cargar script:', url);
                next();
            };
            document.body.appendChild(el);
        }
        next();
    }

    /**
     * Respuesta HTML completa (layout Yii): parsear con DOMParser, inyectar CSS del head,
     * poner body en el contenedor y ejecutar scripts externos + inline.
     */
    function aplicarHtmlPaginaEnSpa(content, html, fullPageUrl, type) {
        const base = fullPageUrl.split('#')[0];
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        if (doc.head) {
            injectHeadStylesheetsFromParsedDoc(doc.head, base);
        }

        const bodyEl = doc.body;
        if (!bodyEl) {
            content.innerHTML = '<div class="alert alert-danger">La respuesta no es un documento HTML válido.</div>';
            return;
        }

        const bodyWrap = document.createElement('div');
        bodyWrap.innerHTML = bodyEl.innerHTML;
        filtrarAssetsDuplicadosEnElemento(bodyWrap);

        const externalSrcs = [];
        bodyWrap.querySelectorAll('script[src]').forEach(s => {
            const raw = (s.getAttribute('src') || '').trim();
            if (raw) {
                try {
                    externalSrcs.push(new URL(raw, base).href);
                } catch (e) {
                    console.warn('[SPA] src de script inválido:', raw);
                }
            }
            s.remove();
        });

        content.innerHTML = bodyWrap.innerHTML;

        loadExternalScriptsSequential(externalSrcs, () => {
            initializePageContent(content, type);
        });
    }

    /**
     * Filtrar assets duplicados (CSS y JS externos) del HTML
     * @deprecated Preferir aplicarHtmlPaginaEnSpa para páginas completas Yii
     */
    function filtrarAssetsDuplicados(html) {
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        filtrarAssetsDuplicadosEnElemento(tempDiv);
        return tempDiv.innerHTML;
    }

    /**
     * Inicializar contenido de página
     */
    function initializePageContent(container, type) {
        // Re-ejecutar scripts inline (solo los que quedaron después del filtrado)
        const scripts = container.querySelectorAll('script:not([src])');
        scripts.forEach(oldScript => {
            // Verificar que el script no esté vacío
            if (oldScript.innerHTML.trim()) {
                const newScript = document.createElement('script');
                Array.from(oldScript.attributes).forEach(attr => {
                    newScript.setAttribute(attr.name, attr.value);
                });
                newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                oldScript.parentNode.replaceChild(newScript, oldScript);
            }
        });
    }

    /**
     * Cargar acciones comunes
     */
    function loadCommonActions() {
        if (!shortcutsContent) {
            return; // No hay panel de atajos
        }
        
        // API: ver nota de duplicación /api arriba.
        const url = window.location.origin + '/api/v1/acciones/comunes';
        fetch(url, {
            method: 'GET',
            headers: window.BioenlaceApiClient.mergeHeaders({
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            })
        })
        .then(response => {
            // Verificar que la respuesta sea JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                // Si no es JSON, probablemente es un error HTML
                return response.text().then(text => {
                    console.warn('El servidor devolvió HTML en lugar de JSON:', text.substring(0, 200));
                    throw new Error('Respuesta no válida del servidor');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data && data.success && Array.isArray(data.categories)) {
                renderShortcutsCategories(data.categories);
                return;
            }
            if (data && data.success && Array.isArray(data.actions)) {
                // Fallback compat: si el backend solo devuelve actions planas.
                renderShortcutsFlat(data.actions);
                return;
            }
            renderShortcutsEmpty();
        })
        .catch(error => {
            console.warn('No se pudieron cargar las acciones comunes:', error);
            renderShortcutsEmpty('No se pudieron cargar los atajos.');
        });
    }

    function renderShortcutsEmpty(msg) {
        if (!shortcutsContent) return;
        const t = (msg && String(msg).trim() !== '') ? String(msg).trim() : 'No hay atajos disponibles.';
        shortcutsContent.innerHTML = '<div class="text-muted small">' + escapeHtml(t) + '</div>';
    }

    function renderShortcutsFlat(actions) {
        if (!shortcutsContent) return;
        const items = Array.isArray(actions) ? actions : [];
        if (items.length < 1) {
            renderShortcutsEmpty();
            return;
        }
        let html = '<div>';
        html += '<h4 class="h6 text-decoration-underline mb-2">Atajos</h4>';
        html += '<div class="d-grid gap-2">';
        items.forEach(function (a) {
            const name = a && (a.name || a.display_name) ? String(a.name || a.display_name) : (a && a.action_id ? String(a.action_id) : '');
            const desc = a && a.description ? String(a.description) : '';
            const co = a && a.client_open && typeof a.client_open === 'object' ? a.client_open : null;
            const iid = co && String(co.kind || '') === 'intent' ? String(co.intent_id || '') : (a && a.action_id ? String(a.action_id) : '');
            if (!iid) return;
            // UX nuevo: el click solo “pega texto” en el textarea. Usamos `name` como prompt preparado.
            html += '<button type="button" class="btn btn-outline-secondary text-start" data-shortcut-text="' + escapeHtml(name) + '">';
            html += '<div class="fw-semibold">' + escapeHtml(name) + '</div>';
            if (desc) html += '<div class="text-muted small">' + escapeHtml(desc) + '</div>';
            html += '</button>';
        });
        html += '</div></div>';
        shortcutsContent.innerHTML = html;
        attachShortcutListeners();
    }

    function renderShortcutsCategories(categories) {
        if (!shortcutsContent) return;
        const cats = Array.isArray(categories) ? categories : [];
        if (cats.length < 1) {
            renderShortcutsEmpty();
            return;
        }
        let html = '<div class="d-flex flex-column gap-3">';
        cats.forEach(function (c, idx) {
            const title = c && c.titulo ? String(c.titulo) : 'Atajos';
            const actions = c && Array.isArray(c.actions) ? c.actions : [];
            if (!actions || actions.length < 1) {
                return;
            }

            html += '<div>';
            html += '<h4 class="h6 text-decoration-underline mb-2">' + escapeHtml(title) + '</h4>';
            html += '<div class="d-grid gap-2">';
            actions.forEach(function (a) {
                const name = a && (a.name || a.display_name) ? String(a.name || a.display_name) : (a && a.action_id ? String(a.action_id) : '');
                const desc = a && a.description ? String(a.description) : '';
                const co = a && a.client_open && typeof a.client_open === 'object' ? a.client_open : null;
                const iid = co && String(co.kind || '') === 'intent' ? String(co.intent_id || '') : (a && a.action_id ? String(a.action_id) : '');
                if (!iid) return;
                // UX nuevo: el click solo “pega texto” en el textarea. Usamos `name` como prompt preparado.
                html += '<button type="button" class="btn btn-outline-secondary text-start" data-shortcut-text="' + escapeHtml(name) + '">';
                html += '<div class="fw-semibold">' + escapeHtml(name) + '</div>';
                if (desc) html += '<div class="text-muted small">' + escapeHtml(desc) + '</div>';
                html += '</button>';
            });
            html += '</div></div>';
        });
        html += '</div>';
        shortcutsContent.innerHTML = html;
        attachShortcutListeners();
    }

    function attachShortcutListeners() {
        if (!shortcutsContent) return;
        try {
            Array.from(shortcutsContent.querySelectorAll('button[data-shortcut-text]')).forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const text = this.getAttribute('data-shortcut-text') || '';
                    if (!queryInput) return;
                    queryInput.value = String(text || '').trim();
                    handleInput();
                    try { queryInput.focus(); } catch (e) { /* ignore */ }
                });
            });
        } catch (e) { /* ignore */ }
    }

    /**
     * Estado de carga
     */
    function setLoadingState(loading) {
        if (sendBtn) {
            sendBtn.disabled = loading;
        }
        if (shortcutsToggleBtn) {
            shortcutsToggleBtn.disabled = loading;
        }
        if (queryInput) {
            queryInput.disabled = loading;
        }
        if (!sendBtn) {
            return;
        }
        const spinner = sendBtn.querySelector('.spa-spinner');
        const sendIcon = sendBtn.querySelector('.spa-send-icon');
        const sendText = sendBtn.querySelector('.spa-send-text');
        // Soportar botones "solo ícono": `.spa-send-icon` puede no existir.
        // Fallback: si faltan spinner o texto, degradar a HTML swap.
        if (!spinner || !sendText) {
            try {
                if (!sendBtn.dataset.originalHtml) {
                    sendBtn.dataset.originalHtml = sendBtn.innerHTML;
                }
                if (loading) {
                    sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
                } else if (sendBtn.dataset.originalHtml) {
                    sendBtn.innerHTML = sendBtn.dataset.originalHtml;
                }
            } catch (e) {
                // ignore
            }
            return;
        }
        if (loading) {
            spinner.classList.remove('d-none');
            if (sendIcon) sendIcon.classList.add('d-none');
            // UX: durante el envío, mostrar solo spinner (sin texto).
            sendText.classList.add('d-none');
        } else {
            spinner.classList.add('d-none');
            if (sendIcon) sendIcon.classList.remove('d-none');
            sendText.classList.remove('d-none');
            // Mantener el botón como ícono/texto idle definido en la vista.
            const idle = (sendBtn && sendBtn.dataset && sendBtn.dataset.sendIdleText)
                ? String(sendBtn.dataset.sendIdleText)
                : (sendText.textContent || '');
            if (String(idle).trim() !== '') {
                sendText.textContent = String(idle);
            }
        }
    }

    /**
     * Escapar HTML para prevenir XSS
     */
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    /**
     * API mínima para onclick legacy en vistas (si hiciera falta).
     */
    window.spaAsistenteSubmitQuery = function (text) {
        if (queryInput && text != null && String(text).trim() !== '') {
            queryInput.value = String(text);
            handleInput();
        }
        handleSendQuery();
    };

    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            init();
            attachCardListeners();
            tryOpenUiJsonFromQuery();
        });
    } else {
        init();
        attachCardListeners();
        tryOpenUiJsonFromQuery();
    }

})();