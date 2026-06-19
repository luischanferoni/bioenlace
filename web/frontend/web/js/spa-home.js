/**
 * Lógica de la Página Inicial de la SPA
 * Maneja el textarea, consultas a IA, y renderizado de cards
 */

(function() {
    'use strict';

    /** Tras elegir en lista embebida: pausa antes del POST del snapshot (se ve borde/check). */
    const SPA_LIST_PICK_TO_SEND_MS = 340;
    /** Lista de un solo ítem sin confirmación: espera antes del click automático. */
    const SPA_LIST_SINGLE_AUTO_INTRO_MS = 480;

    /** URL absoluta para fetch desde el shell (misma regla que loadPageContent). */
    function resolveSpaFetchUrl(url) {
        if (!url) return '';
        if (url.startsWith('http://') || url.startsWith('https://')) {
            return url;
        }
        if (window.BioenlaceApiClient && typeof window.BioenlaceApiClient.normalizeApiV1Path === 'function') {
            url = window.BioenlaceApiClient.normalizeApiV1Path(url);
        }
        if (url.startsWith('/api/')) {
            return window.location.origin + url;
        }
        if (url.startsWith('/')) {
            return window.spaConfig.baseUrl + url;
        }
        return window.spaConfig.baseUrl + '/' + url;
    }

    function handleApiUnauthorized(status, body) {
        if (window.BioenlaceApiClient && typeof window.BioenlaceApiClient.handleUnauthorized === 'function') {
            return window.BioenlaceApiClient.handleUnauthorized(status, body);
        }
        return false;
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

    function applyDraftPlaceholdersToRoute(route) {
        var raw = route == null ? '' : String(route);
        if (raw === '' || raw.indexOf('{') === -1) {
            return raw;
        }
        return raw.replace(/\{([\w-]+)\}/g, function (m, field) {
            var dv = draft && Object.prototype.hasOwnProperty.call(draft, field) ? draft[field] : '';
            var sv = (dv == null ? '' : String(dv)).trim();
            if (!sv) {
                return m;
            }
            return encodeURIComponent(sv);
        });
    }

    function buildUrlForFlowTab(tab) {
        if (!tab || !tab.route) {
            return '';
        }
        const base = resolveSpaFetchUrl(applyDraftPlaceholdersToRoute(String(tab.route)));
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
                } else if (spec.indexOf('client.') !== 0 && spec !== '') {
                    u.searchParams.set(k, spec);
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

    /** Deshabilita inputs/botones de un bloque de flow ya descartado o de un paso anterior. */
    function disableFlowRowInteractions(row) {
        if (!row) {
            return;
        }
        row.classList.add('spa-chat-flow-row--superseded');
        row.setAttribute('data-flow-superseded', '1');
        try {
            row.querySelectorAll('input, select, textarea, button').forEach(function (el) {
                el.disabled = true;
            });
        } catch (e) { /* ignore */ }
    }

    function beginNewFlowActivation() {
        bioFlowActivationSeq += 1;
    }

    function supersedeAllFlowRows() {
        if (!chatMessagesDiv) {
            return;
        }
        chatMessagesDiv.querySelectorAll('.spa-chat-flow-row').forEach(disableFlowRowInteractions);
    }

    function supersedeDiscardedFlowRows(activeIntentId) {
        if (!chatMessagesDiv || !activeIntentId) {
            return;
        }
        const activeId = String(activeIntentId).trim();
        chatMessagesDiv.querySelectorAll('.spa-chat-flow-row').forEach(function (row) {
            const rowIntent = row.getAttribute('data-flow-intent-id') || '';
            if (rowIntent && rowIntent !== activeId) {
                disableFlowRowInteractions(row);
            }
        });
    }

    /** Mismo flow: deja habilitado solo el último bloque `.spa-chat-flow-row` de ese intent. */
    function supersedeOlderStepsOfActiveFlow(activeIntentId) {
        if (!chatMessagesDiv || !activeIntentId) {
            return;
        }
        const activeId = String(activeIntentId).trim();
        const rows = Array.from(chatMessagesDiv.querySelectorAll('.spa-chat-flow-row[data-flow-intent-id="' + activeId + '"]'));
        for (let i = 0; i < rows.length - 1; i++) {
            disableFlowRowInteractions(rows[i]);
        }
    }

    function humanizeFlowResultFieldKey(key) {
        const k = String(key || '');
        if (k === 'id') {
            return 'Nº';
        }
        if (k === 'fecha') {
            return 'Fecha';
        }
        if (k === 'hora') {
            return 'Hora';
        }
        return k.replace(/_/g, ' ');
    }

    const TURNO_CANCELACION_PACIENTE_LABELS = {
        PAC_ENFERMEDAD: 'Enfermedad o síntomas: no puedo asistir',
        PAC_OTRO_COMPROMISO: 'Otro compromiso u obligación',
        PAC_YA_MEJORE: 'Ya mejoré / ya no necesito esta consulta',
        PAC_RESERVA_ERRONEA: 'Reservé el turno por error',
        PAC_OTRO_TURNO_EN_OTRO_LUGAR: 'Conseguí otro turno (misma u otra institución)',
        PAC_TRANSPORTE: 'Dificultades de transporte o distancia',
        PAC_LABORAL_ACADEMICO: 'Motivos laborales o de estudio',
        PAC_OTRO: 'Otro motivo'
    };

    function etiquetaRazonCancelacionPaciente(code) {
        const c = String(code || '').trim();
        return TURNO_CANCELACION_PACIENTE_LABELS[c] || c;
    }

    function friendlyDateEs(fechaYmd) {
        const raw = String(fechaYmd || '').trim();
        if (raw.length < 10) return raw;
        const parts = raw.substring(0, 10).split('-');
        if (parts.length !== 3) return raw;
        const y = parseInt(parts[0], 10);
        const mo = parseInt(parts[1], 10) - 1;
        const d = parseInt(parts[2], 10);
        const slot = new Date(y, mo, d);
        if (isNaN(slot.getTime())) return raw;
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        slot.setHours(0, 0, 0, 0);
        const diff = Math.round((slot.getTime() - today.getTime()) / 86400000);
        if (diff === 0) return 'Hoy';
        if (diff === 1) return 'Mañana';
        if (diff === 2) return 'Pasado mañana';
        const weekdays = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
        return weekdays[slot.getDay()] + ' ' + slot.getDate() + '/' + (slot.getMonth() + 1);
    }

    function formatHoraCorta(hora) {
        const h = String(hora || '').trim();
        if (!h) return '';
        return h.length > 5 ? h.substring(0, 5) : h;
    }

    function formatCuandoDesdeFechaHora(fecha, hora) {
        const f = String(fecha || '').trim();
        const h = formatHoraCorta(hora);
        if (!f && !h) return '';
        if (!f) return h;
        const dia = friendlyDateEs(f);
        return h ? (dia + ' · ' + h) : dia;
    }

    function formatFechaEs(iso) {
        const s = String(iso || '').trim();
        const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s);
        if (!m) {
            return s;
        }
        return m[3] + '/' + m[2] + '/' + m[1].slice(-2);
    }

    function enrichUiSubmitSummaryData(data) {
        const out = data && typeof data === 'object' ? Object.assign({}, data) : {};
        const d = draft && typeof draft === 'object' ? draft : {};
        if (!out.fecha_inicio && d.fecha_inicio) {
            out.fecha_inicio = d.fecha_inicio;
        }
        if (!out.fecha_fin && d.fecha_fin) {
            out.fecha_fin = d.fecha_fin;
        }
        if (!out.condicion_laboral_label && d.condicion_laboral_label) {
            out.condicion_laboral_label = d.condicion_laboral_label;
        }
        const condFlow = d._flow_item_id_condicion_laboral;
        if (!out.condicion_laboral_label && condFlow && typeof condFlow === 'object') {
            const lbl = condFlow.label != null ? String(condFlow.label).trim() : (condFlow.name != null ? String(condFlow.name).trim() : '');
            if (lbl) {
                out.condicion_laboral_label = lbl;
            }
        }
        if (!out.servicio_detalle && d.servicio_detalle && typeof d.servicio_detalle === 'object') {
            out.servicio_detalle = d.servicio_detalle;
        }
        return out;
    }

    function flowSubmitMensajeFromData(data) {
        const d = data && typeof data === 'object' ? data : {};
        const msg = d.mensaje != null ? String(d.mensaje).trim() : '';
        return msg;
    }

    /** Detalle legible cuando la API no envía `mensaje` (fallback genérico para flows). */
    function buildGenericFlowSubmitDetailLines(data, snap) {
        const d = data && typeof data === 'object' ? data : {};
        const s = snap && typeof snap === 'object' ? snap : {};
        const lines = [];

        const shortMsg = (d.message != null && String(d.message).trim() !== '') ? String(d.message).trim() : '';
        if (shortMsg && !/^listo\.?$/i.test(shortMsg)) {
            lines.push(/[.!?]$/.test(shortMsg) ? shortMsg : shortMsg + '.');
        }

        if (s.profesional && s.profesional.label) {
            lines.push('Profesional: ' + String(s.profesional.label).trim());
        }
        let svc = (s.servicio && s.servicio.label) ? String(s.servicio.label).trim() : '';
        if (!svc && d.servicio_detalle && typeof d.servicio_detalle === 'object') {
            const sd = d.servicio_detalle;
            svc = (sd.nombre != null && String(sd.nombre).trim() !== '')
                ? String(sd.nombre).trim()
                : ((sd.descripcion != null && String(sd.descripcion).trim() !== '') ? String(sd.descripcion).trim() : '');
        }
        if (svc) {
            lines.push('Servicio: ' + svc);
        }
        if (s.efector && s.efector.label) {
            lines.push('Centro: ' + String(s.efector.label).trim());
        }
        if (s.turno && s.turno.label) {
            lines.push('Turno: ' + String(s.turno.label).trim());
        }

        const condLbl = d.condicion_laboral_label != null ? String(d.condicion_laboral_label).trim() : '';
        if (condLbl) {
            lines.push('Tipo: ' + condLbl);
        }

        const fi = d.fecha_inicio != null ? String(d.fecha_inicio).trim() : '';
        const ff = d.fecha_fin != null ? String(d.fecha_fin).trim() : '';
        if (fi && ff) {
            lines.push('Desde ' + formatFechaEs(fi) + ' hasta ' + formatFechaEs(ff));
        } else if (fi) {
            lines.push('Desde ' + formatFechaEs(fi));
        } else if (ff) {
            lines.push('Hasta ' + formatFechaEs(ff));
        }

        const cuando = formatCuandoDesdeFechaHora(d.fecha, d.hora) || nuevoHorarioLinea(s, d);
        if (cuando && !lines.some(function (ln) { return String(ln).indexOf(cuando) >= 0; })) {
            lines.push('Fecha: ' + cuando);
        }

        if (d.razon_cancelacion_label != null && String(d.razon_cancelacion_label).trim() !== '') {
            lines.push('Motivo: ' + String(d.razon_cancelacion_label).trim());
        }

        return lines;
    }

    /** Tras guardar la mini-UI del último paso (formulario): colapsar flow y mostrar resumen. */
    function finishActiveFlowAfterTerminalUiSubmit(fromEl, json) {
        const host = flowSubmitHostFromEl(fromEl);
        const row = host && host.closest ? host.closest('.spa-chat-flow-row') : null;
        const seq = row ? row.getAttribute('data-flow-activation-seq') : String(bioFlowActivationSeq);
        let title = '';
        try {
            const tEl = row && row.querySelector('.spa-flow-chat-title');
            if (tEl) {
                title = String(tEl.textContent || '').trim();
            }
        } catch (eTitle) { /* ignore */ }
        const rawData = json && json.data && typeof json.data === 'object' ? json.data : {};
        const d = enrichUiSubmitSummaryData(rawData);
        const intentForSummary = currentIntentId ? String(currentIntentId) : '';
        const snapForSummary = Object.assign({}, flowSnapshot || {});
        collapseCompletedFlowActivation(seq, d, title, intentForSummary, snapForSummary);
        clearFlowState();
        removeFlowPlanStrip();
    }

    function applyFlowPickToSnapshot(snap, draftField, item) {
        if (!snap || !item || typeof item !== 'object') return;
        const label = String(item.name || item.label || item.id || '').trim();
        const field = String(draftField || '');
        if (field === 'id') {
            snap.turno = { id: item.id != null ? String(item.id) : '' };
            if (label) snap.turno.label = label;
        } else if (field === 'slot_id') {
            const meta = item.meta && typeof item.meta === 'object' ? item.meta : {};
            const fecha = meta.fecha != null ? String(meta.fecha) : '';
            const hora = meta.hora != null ? String(meta.hora) : '';
            const cuando = formatCuandoDesdeFechaHora(fecha, hora);
            snap.slot = { id: item.id != null ? String(item.id) : '' };
            if (label) snap.slot.label = label;
            if (fecha) snap.slot.fecha = fecha;
            if (hora) snap.slot.hora = hora;
            if (cuando) snap.slot.cuando = cuando;
            if (meta.servicio) snap.slot.servicio = meta.servicio;
        } else if (field === 'razon_cancelacion') {
            const code = String(item.code || item.value || item.id || '').trim();
            const motivoLabel = String(item.label || item.name || '').trim()
                || (code ? etiquetaRazonCancelacionPaciente(code) : '');
            snap.motivo = {};
            if (code) snap.motivo.code = code;
            if (motivoLabel) snap.motivo.label = motivoLabel;
        } else if (field === 'eleccion') {
            snap.eleccion = {
                value: item.value != null ? String(item.value) : (item.id != null ? String(item.id) : ''),
            };
            if (label) snap.eleccion.label = label;
        } else if (field === 'id_servicio_asignado') {
            snap.servicio = { id: item.id != null ? String(item.id) : '' };
            if (label) snap.servicio.label = label;
        } else if (field === 'id_servicio') {
            snap.servicio = { id: item.id != null ? String(item.id) : '' };
            if (label) snap.servicio.label = label;
            const metaServ = item.meta && typeof item.meta === 'object' ? item.meta : {};
            if (metaServ.id_profesional_efector_servicio != null) {
                snap.profesional = {
                    id: String(metaServ.id_profesional_efector_servicio),
                    label: label,
                };
            }
        } else if (field === 'id_efector') {
            snap.efector = { id: item.id != null ? String(item.id) : '' };
            if (label) snap.efector.label = label;
        } else if (field === 'id_profesional_efector_servicio') {
            snap.profesional = { id: item.id != null ? String(item.id) : '' };
            if (label) snap.profesional.label = label;
        } else if (label) {
            snap[field] = { id: item.id != null ? String(item.id) : '', label: label };
        }
    }

    function nuevoHorarioLinea(snap, data) {
        const d = data && typeof data === 'object' ? data : {};
        const s = snap && typeof snap === 'object' ? snap : {};
        const slot = s.slot;
        if (slot && typeof slot === 'object') {
            const cuando = slot.cuando != null ? String(slot.cuando).trim() : '';
            if (cuando) return cuando;
            const lbl = slot.label != null ? String(slot.label).trim() : '';
            if (lbl) return lbl;
            const built = formatCuandoDesdeFechaHora(slot.fecha, slot.hora);
            if (built) return built;
        }
        return formatCuandoDesdeFechaHora(d.fecha, d.hora);
    }

    function formatFlowSubmitSummaryLines(data, intentId, flowSnapshotOpt) {
        const d = data && typeof data === 'object' ? data : {};
        const snap = flowSnapshotOpt && typeof flowSnapshotOpt === 'object' ? flowSnapshotOpt : {};
        const iid = String(intentId || '').trim();

        if (iid === 'turnos.cancelar-como-paciente-flow') {
            const lines = ['Cancelamos tu turno.'];
            if (snap.turno && snap.turno.label) lines.push(String(snap.turno.label).trim());
            var motivoLbl = (snap.motivo && snap.motivo.label) ? String(snap.motivo.label).trim() : '';
            if (!motivoLbl) {
                const code = d.razon_cancelacion != null ? String(d.razon_cancelacion).trim() : '';
                motivoLbl = (d.razon_cancelacion_label != null && String(d.razon_cancelacion_label).trim() !== '')
                    ? String(d.razon_cancelacion_label).trim()
                    : (code ? etiquetaRazonCancelacionPaciente(code) : '');
            }
            if (motivoLbl) lines.push('Motivo: ' + motivoLbl);
            return lines;
        }
        if (iid === 'turnos.modificar-como-paciente-flow') {
            const lines = ['Reprogramamos tu turno.'];
            if (snap.turno && snap.turno.label) lines.push('Turno anterior: ' + String(snap.turno.label).trim());
            const nuevo = nuevoHorarioLinea(snap, d);
            if (nuevo) lines.push('Nuevo horario: ' + nuevo);
            return lines;
        }
        if (iid === 'turnos.conflicto-agenda-flow') {
            const lines = ['Actualizamos tu turno por el cambio de agenda.'];
            if (snap.turno && snap.turno.label) lines.push(String(snap.turno.label).trim());
            if (snap.eleccion && snap.eleccion.label) lines.push('Opción: ' + String(snap.eleccion.label).trim());
            const nuevo = nuevoHorarioLinea(snap, d);
            if (nuevo) lines.push('Nuevo horario: ' + nuevo);
            const msg = d.message != null ? String(d.message).trim() : '';
            if (msg && lines.length === 1) lines.push(msg);
            return lines;
        }
        if (iid === 'turnos.reubicar-como-paciente-flow') {
            const lines = ['Reubicamos tu turno.'];
            if (snap.turno && snap.turno.label) lines.push('Turno anterior: ' + String(snap.turno.label).trim());
            if (snap.servicio && snap.servicio.label) lines.push('Servicio: ' + String(snap.servicio.label).trim());
            if (snap.efector && snap.efector.label) lines.push('Centro: ' + String(snap.efector.label).trim());
            if (snap.profesional && snap.profesional.label) lines.push('Profesional: ' + String(snap.profesional.label).trim());
            const nuevo = nuevoHorarioLinea(snap, d);
            if (nuevo) lines.push('Nuevo horario: ' + nuevo);
            return lines;
        }

        const mensajeApi = flowSubmitMensajeFromData(d);
        if (mensajeApi) {
            return [mensajeApi];
        }

        const genericLines = buildGenericFlowSubmitDetailLines(d, snap);
        if (genericLines.length) {
            return genericLines;
        }

        if (iid === 'turnos.crear-como-paciente') {
            var svc = (snap.servicio && snap.servicio.label) ? String(snap.servicio.label).trim() : '';
            if (!svc && d.servicio_detalle && typeof d.servicio_detalle === 'object') {
                const sd = d.servicio_detalle;
                svc = (sd.nombre != null && String(sd.nombre).trim() !== '')
                    ? String(sd.nombre).trim()
                    : ((sd.descripcion != null && String(sd.descripcion).trim() !== '') ? String(sd.descripcion).trim() : '');
            }
            const cuando = nuevoHorarioLinea(snap, d);
            if (svc && cuando) return ['Reservamos tu turno de ' + svc + ' (' + cuando + ').'];
            if (cuando) return ['Reservamos tu turno (' + cuando + ').'];
        }

        return ['Listo.'];
    }

    function buildFlowChatHeaderHtml(actionTitle) {
        const titleStr = typeof actionTitle === 'string' ? actionTitle.trim() : '';
        if (titleStr === '') {
            return '';
        }
        return '<div class="spa-flow-chat-header">'
            + '<h3 class="spa-flow-chat-title">' + escapeHtml(titleStr) + '</h3>'
            + '<div class="spa-flow-chat-rule" aria-hidden="true"></div>'
            + '</div>';
    }

    function buildFlowSubmitSummaryHtml(data, intentId, flowSnapshotOpt) {
        const lines = formatFlowSubmitSummaryLines(data, intentId, flowSnapshotOpt);
        const body = lines.length
            ? lines.map(function (ln) { return escapeHtml(ln); }).join('<br>')
            : escapeHtml('Listo.');
        return '<div class="spa-flow-completed-summary"><div class="alert alert-success mb-0 py-2">' + body + '</div></div>';
    }

    const FLOW_COLLAPSE_FADE_MS = 620;
    const FLOW_SUMMARY_ENTER_MS = 520;

    /** Tras submit exitoso: anima salida de mini-UIs y reemplaza por un resumen con los datos. */
    function collapseCompletedFlowActivation(activationSeq, data, actionTitle, intentIdOpt, flowSnapshotOpt) {
        if (!chatMessagesDiv || activationSeq == null || String(activationSeq) === '') {
            return;
        }
        const seq = String(activationSeq);
        const rows = Array.from(chatMessagesDiv.querySelectorAll('.spa-chat-flow-row[data-flow-activation-seq="' + seq + '"]'));
        if (!rows.length) {
            return;
        }
        removeFlowPlanStrip();
        rows.forEach(function (row) {
            row.classList.add('spa-chat-flow-row--collapsing');
        });
        setTimeout(function () {
            if (!chatMessagesDiv) {
                return;
            }
            const summaryHtml = buildFlowSubmitSummaryHtml(data, intentIdOpt, flowSnapshotOpt);
            const first = rows[0];
            if (!first || !first.isConnected) {
                return;
            }
            first.classList.remove('spa-chat-flow-row--superseded', 'spa-chat-flow-row--collapsing');
            first.removeAttribute('data-flow-superseded');
            first.classList.add('spa-chat-flow-row--completed');
            const inner = first.querySelector('.spa-chat-flow-turn');
            if (inner) {
                var headerHtml = '';
                var existingHeader = inner.querySelector('.spa-flow-chat-header');
                if (existingHeader) {
                    headerHtml = existingHeader.outerHTML;
                } else {
                    headerHtml = buildFlowChatHeaderHtml(actionTitle);
                }
                inner.innerHTML = headerHtml + summaryHtml;
                var summaryEl = inner.querySelector('.spa-flow-completed-summary');
                if (summaryEl) {
                    requestAnimationFrame(function () {
                        summaryEl.classList.add('spa-flow-completed-summary--visible');
                    });
                }
            }
            for (let i = 1; i < rows.length; i++) {
                if (rows[i] && rows[i].parentNode) {
                    rows[i].remove();
                }
            }
            setTimeout(scrollChatToBottom, FLOW_SUMMARY_ENTER_MS);
        }, FLOW_COLLAPSE_FADE_MS);
    }

    /**
     * Resuelve `body_template` del `flow_submit` con el `draft` actual del chat.
     * @param {object} bodyTemplate mapa `apiKey -> "draft.<campo>"` o literal.
     * @returns {{ body: object, missing: string[] }}
     */
    function resolveFlowSubmitBody(bodyTemplate) {
        var body = {};
        var missing = [];
        if (!bodyTemplate || typeof bodyTemplate !== 'object') {
            return { body: body, missing: missing };
        }
        Object.keys(bodyTemplate).forEach(function (k) {
            var raw = bodyTemplate[k];
            var s = (raw == null ? '' : String(raw)).trim();
            if (s.indexOf('draft.') === 0) {
                var optional = s.length > 7 && s.charAt(s.length - 1) === '?';
                var field = optional ? s.substring(6, s.length - 1).trim() : s.substring(6).trim();
                var val = (field && draft && Object.prototype.hasOwnProperty.call(draft, field))
                    ? draft[field]
                    : '';
                var sv = (val == null ? '' : String(val)).trim();
                if (!field || sv === '') {
                    if (!optional) {
                        missing.push(field || k);
                    }
                } else {
                    body[k] = sv;
                }
            } else if (s !== '') {
                body[k] = s;
            }
        });
        return { body: body, missing: missing };
    }

    /**
     * Render del botón "Confirmar y enviar" del último paso del flow (integrado al `open_ui` terminal).
     * Resuelve `body_template` con el `draft` global al apretar; si faltan campos, muestra error inline.
     * @param {HTMLElement} hostEl contenedor (la mini-UI del paso o el bloque del flow)
     * @param {{ route: string, method?: string, action_id?: string, body_template?: object }} fs `flow_submit` del payload
     * @param {function(): void} onFlowCleared tras éxito (limpiar intent/draft en el chat)
     */
    function revealFlowInlineSubmit(hostEl) {
        if (!hostEl) return;
        try {
            hostEl.querySelectorAll('.spa-flow-submit-inline--pending').forEach(function (el) {
                el.classList.remove('spa-flow-submit-inline--pending');
            });
        } catch (e) { /* ignore */ }
    }

    function scheduleRevealFlowInlineSubmit(hostEl) {
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                revealFlowInlineSubmit(hostEl);
            });
        });
    }

    function appendFlowDismissFooter(hostEl, dismiss, onFlowCleared) {
        if (!hostEl || !dismiss) {
            return;
        }
        var wrap = document.createElement('div');
        wrap.className = 'mt-3 pt-2 border-top spa-flow-dismiss-inline d-flex flex-wrap gap-2';
        (dismiss.actions || []).forEach(function (a) {
            if (!a || !a.href) {
                return;
            }
            var link = document.createElement('a');
            link.className = 'btn btn-sm ' + (a.variant === 'danger' ? 'btn-danger' : 'btn-outline-primary');
            link.href = String(a.href);
            link.textContent = a.label ? String(a.label) : String(a.href);
            wrap.appendChild(link);
        });
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-outline-secondary';
        btn.textContent = dismiss.label ? String(dismiss.label) : 'Entendido';
        btn.addEventListener('click', function () {
            if (typeof onFlowCleared === 'function') {
                onFlowCleared();
            }
            try {
                var row = hostEl.closest ? hostEl.closest('.spa-chat-flow-row') : null;
                if (row) {
                    disableFlowRowInteractions(row);
                }
            } catch (e) { /* ignore */ }
        });
        wrap.appendChild(btn);
        hostEl.appendChild(wrap);
    }

    /**
     * @param {{ deferReveal?: boolean }} opts si true, el botón queda oculto hasta `scheduleRevealFlowInlineSubmit`.
     */
    function appendFlowInlineSubmit(hostEl, fs, onFlowCleared, opts) {
        opts = opts && typeof opts === 'object' ? opts : {};
        if (!hostEl || !fs || !fs.route) {
            return;
        }
        var wrap = document.createElement('div');
        wrap.className = 'mt-3 pt-2 border-top spa-flow-submit-inline' +
            (opts.deferReveal === true ? ' spa-flow-submit-inline--pending' : '');
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-primary';
        btn.textContent = 'Confirmar y enviar';
        var errBox = document.createElement('div');
        errBox.className = 'small text-danger mt-2 d-none';
        wrap.appendChild(btn);
        wrap.appendChild(errBox);
        hostEl.appendChild(wrap);

        btn.addEventListener('click', function () {
            errBox.classList.add('d-none');
            errBox.textContent = '';
            var resolved = resolveFlowSubmitBody(fs.body_template);
            if (resolved.missing.length > 0) {
                errBox.textContent = resolved.missing.length === 1
                    ? ('Falta elegir: ' + resolved.missing[0].replace(/_/g, ' '))
                    : ('Faltan datos: ' + resolved.missing.map(function (m) { return m.replace(/_/g, ' '); }).join(', '));
                errBox.classList.remove('d-none');
                return;
            }
            btn.disabled = true;
            var postUrl = resolveSpaFetchUrl(applyDraftPlaceholdersToRoute(String(fs.route)));
            var bodyObj = resolved.body;
            fetch(postUrl, {
                method: 'POST',
                headers: window.BioenlaceApiClient.mergeHeaders({
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }),
                credentials: 'same-origin',
                body: JSON.stringify(bodyObj)
            })
                .then(function (r) {
                    return r.text().then(function (t) {
                        var j = null;
                        try {
                            j = t ? JSON.parse(t) : null;
                        } catch (e) {
                            j = null;
                        }
                        if (!r.ok) {
                            var msg = 'HTTP ' + r.status;
                            if (j && typeof j.message === 'string' && j.message.trim() !== '') {
                                msg = j.message.trim();
                            }
                            throw new Error(msg);
                        }
                        return j;
                    });
                })
                .then(function (json) {
                    if (json && json.kind === 'ui_submit_result' && json.success !== false) {
                        var row = hostEl.closest ? hostEl.closest('.spa-chat-flow-row') : null;
                        var seq = row ? row.getAttribute('data-flow-activation-seq') : String(bioFlowActivationSeq);
                        var title = '';
                        try {
                            var tEl = row && row.querySelector('.spa-flow-chat-title');
                            if (tEl) {
                                title = String(tEl.textContent || '').trim();
                            }
                        } catch (eTitle) { /* ignore */ }
                        var d = json.data && typeof json.data === 'object' ? enrichUiSubmitSummaryData(json.data) : null;
                        var snapForSummary = Object.assign({}, flowSnapshot || {});
                        var intentForSummary = currentIntentId ? String(currentIntentId) : '';
                        collapseCompletedFlowActivation(seq, d, title, intentForSummary, snapForSummary);
                        if (typeof onFlowCleared === 'function') {
                            onFlowCleared();
                        }
                        return;
                    }
                    if (json && json.kind === 'ui_definition' && json.errors) {
                        var ek = Object.keys(json.errors);
                        var first = ek.length ? String(json.errors[ek[0]]) : 'No se pudo validar.';
                        throw new Error(first);
                    }
                    throw new Error((json && json.message) ? String(json.message) : 'Respuesta inesperada');
                })
                .catch(function (err) {
                    console.error('flow_submit POST:', err);
                    errBox.textContent = (err && err.message) ? String(err.message) : 'Error al enviar';
                    errBox.classList.remove('d-none');
                    btn.disabled = false;
                });
        });
    }

    /**
     * @param {{ route?: string, body_template?: object }|null|undefined} fs
     * @returns {boolean}
     */
    function isActiveFlowSubmitRequest(fs) {
        return !!(fs && fs.route && fs.body_template && typeof fs.body_template === 'object');
    }

    /** @param {HTMLElement|null|undefined} fromEl */
    function flowSubmitHostFromEl(fromEl) {
        if (!fromEl || !fromEl.closest) {
            return fromEl || null;
        }
        return fromEl.closest('.spa-flow-step-ui') || fromEl.closest('.spa-chat-flow-ui') || fromEl;
    }

    /** @param {HTMLElement|null|undefined} fromEl */
    function revealFlowSubmitInlineForContainer(fromEl) {
        revealFlowInlineSubmit(flowSubmitHostFromEl(fromEl));
    }

    /** @param {HTMLElement|null|undefined} fromEl */
    function clearFlowSubmitMissingHint(fromEl) {
        const host = flowSubmitHostFromEl(fromEl);
        if (!host) {
            return;
        }
        try {
            host.querySelectorAll('.spa-flow-submit-inline .text-danger').forEach(function (errBox) {
                errBox.classList.add('d-none');
                errBox.textContent = '';
            });
        } catch (e) { /* ignore */ }
    }

    function mountFlowUiDefinition(json, mountEl, fullUrl, flowSubmitRequestOpt, enableFlowChainAutoAdvance, flowDismissOpt) {
        mountEl.innerHTML = '';
        if (json && json.kind === 'ui_definition') {
            const isTerminalFlowStep = isActiveFlowSubmitRequest(flowSubmitRequestOpt);
            const hasEditSparse = !!(json.ui_meta && json.ui_meta.edit_sparse);
            renderDynamicUi(json, mountEl, {
                url: fullUrl,
                rootContainer: mountEl,
                editSparseMountRoot: hasEditSparse ? mountEl : null,
                enableFlowChainAutoAdvance: enableFlowChainAutoAdvance === true && !isTerminalFlowStep,
                isTerminalFlowStep: isTerminalFlowStep
            });
            if (isTerminalFlowStep) {
                appendFlowInlineSubmit(mountEl, flowSubmitRequestOpt, function () {
                    clearFlowState();
                    removeFlowPlanStrip();
                }, { deferReveal: true });
                scheduleRevealFlowInlineSubmit(mountEl);
            } else if (flowDismissOpt) {
                appendFlowDismissFooter(mountEl, flowDismissOpt, function () {
                    clearFlowState();
                    removeFlowPlanStrip();
                });
            }
        } else {
            mountEl.innerHTML = '<div class="alert alert-warning mb-0">La respuesta no es una definición de UI válida.</div>';
        }
    }

    function fetchFlowUiDefinition(fullUrl, mountEl, flowSubmitRequestOpt, fetchOpts) {
        fetchOpts = fetchOpts && typeof fetchOpts === 'object' ? fetchOpts : {};
        const enableFlowChainAutoAdvance = fetchOpts.enableFlowChainAutoAdvance === true;
        const flowDismissOpt = fetchOpts.flowDismissOpt || null;
        const flowRow = mountEl.closest ? mountEl.closest('.spa-chat-flow-row') : null;
        if (flowRow && flowRow.__bioFlowUiCache && flowRow.__bioFlowUiCache[fullUrl]) {
            mountFlowUiDefinition(
                flowRow.__bioFlowUiCache[fullUrl],
                mountEl,
                fullUrl,
                flowSubmitRequestOpt,
                false,
                flowDismissOpt
            );
            setTimeout(scrollChatToBottom, 10);
            return Promise.resolve();
        }
        mountEl.innerHTML = '<div class="d-flex align-items-center justify-content-center gap-2 py-3 text-muted"><div class="spinner-border spinner-border-sm"></div> Cargando...</div>';
        return fetch(fullUrl, {
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
                if (flowRow && json && json.kind === 'ui_definition') {
                    if (!flowRow.__bioFlowUiCache) {
                        flowRow.__bioFlowUiCache = {};
                    }
                    flowRow.__bioFlowUiCache[fullUrl] = json;
                }
                mountFlowUiDefinition(json, mountEl, fullUrl, flowSubmitRequestOpt, enableFlowChainAutoAdvance, flowDismissOpt);
            })
            .catch(function (err) {
                console.error('Error cargando UI JSON (flow):', err);
                const msg = (err && err.message) ? String(err.message) : 'Error al cargar la UI';
                mountEl.innerHTML = '<div class="alert alert-danger mb-0">' + escapeHtml(msg) + '</div>';
            })
            .finally(function () {
                setTimeout(scrollChatToBottom, 10);
            });
    }


    // Referencias a elementos DOM
    const chatRoot = document.getElementById('spa-chat-root');
    const chatComposer = document.getElementById('spa-chat-composer');
    const queryInput = document.getElementById('spa-query-input');
    const sendBtn = document.getElementById('spa-send-btn');
    const shortcutsToggleBtn = document.getElementById('spa-shortcuts-toggle-btn');
    const shortcutsContent = document.getElementById('spa-shortcuts-content');
    const shortcutsToolbar = document.getElementById('spa-chat-toolbar');
    const chatMessagesDiv = document.getElementById('spa-chat-messages');
    const chatEmptyHint = document.getElementById('spa-chat-empty-hint');
    const welcomeActionsEl = document.getElementById('spa-chat-welcome-actions');

    /** Alinea el composer fijo al ancho del chat y reserva espacio para que el scroll no quede debajo. */
    function syncChatComposerLayout() {
        if (!chatRoot) {
            return;
        }
        try {
            if (chatComposer) {
                const r = chatRoot.getBoundingClientRect();
                chatComposer.style.left = Math.max(0, Math.round(r.left)) + 'px';
                chatComposer.style.width = Math.max(0, Math.round(r.width)) + 'px';
            }

            const gapPx = 16;
            let reservePx = 0;
            if (chatComposer) {
                const rect = chatComposer.getBoundingClientRect();
                const vh = window.innerHeight || document.documentElement.clientHeight || 0;
                // Desde el borde superior del composer hasta el fondo del viewport (incluye bottom: 88px en móvil).
                reservePx = Math.max(0, Math.ceil(vh - rect.top + gapPx));
            }

            const reserve = reservePx + 'px';
            document.documentElement.style.setProperty('--spa-chat-composer-reserve', reserve);
            if (chatMessagesDiv) {
                chatMessagesDiv.style.paddingBottom = reserve;
            }

            chatRoot.style.height = '';
        } catch (e) { /* ignore */ }
    }

    function bindChatComposerLayoutObserver() {
        if (!chatComposer || typeof ResizeObserver === 'undefined') {
            return;
        }
        try {
            const ro = new ResizeObserver(function () {
                syncChatComposerLayout();
            });
            ro.observe(chatComposer);
            if (queryInput) {
                ro.observe(queryInput);
            }
        } catch (e) { /* ignore */ }
    }

    // Estado de cards expandidos
    const expandedCards = new Map();

    // Estado conversacional (flow) — similar al cliente Flutter.
    let currentIntentId = null;
    let currentSubintentId = null;
    let draft = {};
    let flowSnapshot = {};
    /** Último manifiesto del flow activo (pasos, paso terminal, etc.). */
    let currentFlowManifest = null;

    function flowStepById(manifest, stepId) {
        if (!manifest || !stepId) {
            return null;
        }
        const steps = Array.isArray(manifest.steps) ? manifest.steps : [];
        const sid = String(stepId).trim();
        for (let i = 0; i < steps.length; i++) {
            const st = steps[i];
            if (st && String(st.id || '') === sid) {
                return st;
            }
        }
        return null;
    }

    function flowStepIsTerminal(manifest, stepId) {
        const st = flowStepById(manifest, stepId);
        if (!st) {
            return false;
        }
        const nxt = st.next;
        return nxt == null || String(nxt).trim() === '';
    }

    function uiSubmitDataMarksFlowComplete(data) {
        if (!data || typeof data !== 'object') {
            return false;
        }
        if (data.condicion_laboral_ui_completed != null && String(data.condicion_laboral_ui_completed).trim() !== '') {
            return true;
        }
        return false;
    }

    /** ¿El POST del formulario ya cerró el flow (paso terminal / marcador de provides)? */
    function shouldFinishFlowAfterFormUiSubmit(options, json) {
        if (options.isTerminalFlowStep === true) {
            return true;
        }
        if (!currentIntentId) {
            return false;
        }
        const data = json && json.data && typeof json.data === 'object' ? json.data : {};
        if (uiSubmitDataMarksFlowComplete(data)) {
            return true;
        }
        if (currentFlowManifest && currentSubintentId && flowStepIsTerminal(currentFlowManifest, currentSubintentId)) {
            return true;
        }
        return false;
    }

    function buildUiFormDraftDelta(form, fields) {
        const delta = {};
        if (!form) {
            return delta;
        }
        const fieldList = Array.isArray(fields) ? fields : [];
        try {
            const fd = new FormData(form);
            fd.forEach(function (v, k) {
                if (v == null || String(v).trim() === '') {
                    return;
                }
                const ff = fieldList.find(function (f) {
                    return f && String(f.name) === String(k);
                });
                if (ff && ff.include_in_submit === false) {
                    return;
                }
                delta[k] = String(v).trim();
                if (ff && String(ff.type) === 'select') {
                    const sel = form.querySelector('[name="' + String(k).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]');
                    let lbl = '';
                    if (sel && sel.selectedOptions && sel.selectedOptions[0]) {
                        lbl = String(sel.selectedOptions[0].textContent || '').trim();
                    }
                    if (!lbl && k === 'razon_cancelacion' && typeof etiquetaRazonCancelacionPaciente === 'function') {
                        lbl = etiquetaRazonCancelacionPaciente(delta[k]);
                    }
                    if (lbl) {
                        delta['_flow_item_' + k] = { code: delta[k], label: lbl };
                    }
                } else if (ff && (String(ff.type) === 'chips' || String(ff.type) === 'radio')) {
                    const nameSel = String(k).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
                    const activeChip = form.querySelector('.spa-ui-chip-btn.is-active[data-field="' + nameSel + '"]');
                    let lbl = activeChip ? String(activeChip.textContent || '').trim() : '';
                    if (!lbl) {
                        const checked = form.querySelector('input[type="radio"][name="' + nameSel + '"]:checked');
                        if (checked) {
                            const labEl = form.querySelector('label[for="' + String(checked.id || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]');
                            lbl = labEl ? String(labEl.textContent || '').trim() : '';
                        }
                    }
                    if (lbl) {
                        delta['_flow_item_' + k] = { code: delta[k], label: lbl };
                    }
                }
            });
        } catch (eForm) { /* ignore */ }
        return delta;
    }

    function applyDraftDelta(delta) {
        if (!delta || typeof delta !== 'object') return;
        const next = Object.assign({}, draft || {});
        const snap = Object.assign({}, flowSnapshot || {});
        Object.keys(delta).forEach(function (k) {
            if (k.indexOf('_flow_item_') === 0) {
                applyFlowPickToSnapshot(snap, k.substring('_flow_item_'.length), delta[k]);
            } else if (k !== 'flow_snapshot') {
                next[k] = delta[k];
            }
        });
        draft = next;
        flowSnapshot = snap;
    }

    function clearFlowState() {
        currentIntentId = null;
        currentSubintentId = null;
        draft = {};
        flowSnapshot = {};
        currentFlowManifest = null;
        writeFlowState();
    }

    /**
     * Alineado con SubIntentEngine::userWantsNearby (PHP): mantener el flow al pedir efectores cercanos.
     */
    function userSaysNearbyForEfectorChooser(content) {
        var s = String(content || '').trim().toLowerCase();
        if (!s) return false;
        return /\b(cerca|cercanos|cercano|cercanas|cercana|cercanía|cercania)\b/i.test(s);
    }
    /** Una activación = un inicio de flow (atajo, cambio de intent o vuelta tras otro flow). */
    let bioFlowActivationSeq = 0;

    function purgeLegacyFlowPlanWraps() {
        try {
            document.querySelectorAll('.spa-flow-plan-wrap').forEach(function (el) {
                el.parentNode && el.parentNode.removeChild(el);
            });
        } catch (e) { /* ignore */ }
    }

    function hideComposerFlowProgress() {
        purgeLegacyFlowPlanWraps();
    }

    /** @deprecated alias */
    function removeFlowPlanStrip() {
        hideComposerFlowProgress();
    }

    /**
     * Intent con un único subintent (p. ej. data-access.editar): el paso es la acción entera;
     * se oculta solo la etiqueta del subpaso, no el `action_name` del encabezado del flow.
     *
     * @param {object|null} fm
     * @returns {boolean}
     */
    function isPassthroughSingleStepFlow(fm) {
        if (!fm || typeof fm !== 'object') {
            return false;
        }
        const steps = Array.isArray(fm.steps) ? fm.steps : [];
        return steps.length === 1;
    }

    /**
     * Índice del paso activo en `manifest.steps` (por `active_subintent_id`).
     *
     * @param {object} fm
     * @returns {number}
     */
    function resolveFlowActiveStepIndex(fm) {
        if (!fm || typeof fm !== 'object') {
            return -1;
        }
        const steps = Array.isArray(fm.steps) ? fm.steps : [];
        if (!steps.length) {
            return -1;
        }
        const activeId = fm.active_subintent_id != null ? String(fm.active_subintent_id) : '';
        if (activeId === '') {
            return 0;
        }
        for (let i = 0; i < steps.length; i++) {
            const sid = steps[i] && steps[i].id != null ? String(steps[i].id) : '';
            if (sid !== '' && sid === activeId) {
                return i;
            }
        }
        return -1;
    }

    function resolveFlowStepIndexById(fm, stepId) {
        if (!fm || typeof fm !== 'object' || !stepId) {
            return -1;
        }
        const steps = Array.isArray(fm.steps) ? fm.steps : [];
        const target = String(stepId);
        for (let i = 0; i < steps.length; i++) {
            const sid = steps[i] && steps[i].id != null ? String(steps[i].id) : '';
            if (sid !== '' && sid === target) {
                return i;
            }
        }
        return -1;
    }

    function clearDraftProvidesFromStepIndex(fm, fromStepIdx) {
        if (!fm || typeof fm !== 'object' || fromStepIdx < 0) {
            return;
        }
        const steps = Array.isArray(fm.steps) ? fm.steps : [];
        for (let i = fromStepIdx; i < steps.length; i++) {
            const provides = steps[i] && Array.isArray(steps[i].provides) ? steps[i].provides : [];
            provides.forEach(function (p) {
                const raw = String(p || '').trim();
                if (raw === '') {
                    return;
                }
                const field = raw.indexOf('draft.') === 0 ? raw.slice(6) : raw;
                if (field === '') {
                    return;
                }
                try {
                    delete draft[field];
                    delete flowSnapshot[field];
                    delete draft['_flow_item_' + field];
                } catch (e) { /* ignore */ }
            });
        }
    }

    function clearFlowStepUiFromIndex(flowRow, fromStepIdx) {
        if (!flowRow || fromStepIdx < 0) {
            return;
        }
        const items = flowRow.querySelectorAll('.spa-flow-step-item');
        for (let i = fromStepIdx; i < items.length; i++) {
            const uiMount = items[i].querySelector('.spa-flow-step-ui');
            if (uiMount) {
                uiMount.innerHTML = '';
            }
        }
    }

    function unlockFlowListPicksInRow(flowRow) {
        if (!flowRow) {
            return;
        }
        try {
            flowRow.querySelectorAll('.bio-ui-json-list').forEach(function (el) {
                el.__bioListPickLocked = false;
                el.querySelectorAll('button[data-embed-pick="1"]').forEach(function (btn) {
                    btn.disabled = false;
                    btn.classList.remove('disabled');
                });
            });
        } catch (e) { /* ignore */ }
    }

    /**
     * @param {object} st
     * @param {number} idx
     * @returns {string}
     */
    function flowStepDisplayText(st, idx) {
        const stepTitle = st && st.assistant_text ? String(st.assistant_text).trim() : '';
        const sid = st && st.id != null ? String(st.id) : '';
        if (stepTitle !== '') {
            return stepTitle;
        }
        if (sid !== '') {
            return sid;
        }
        return 'Paso ' + (idx + 1);
    }

    /**
     * @param {number} idx
     * @param {number} activeIdx
     * @returns {string}
     */
    function flowStepItemStateClass(idx, activeIdx) {
        if (activeIdx < 0) {
            return idx === 0 ? 'spa-flow-step-item--active' : 'spa-flow-step-item--pending';
        }
        if (idx < activeIdx) {
            return 'spa-flow-step-item--done';
        }
        if (idx === activeIdx) {
            return 'spa-flow-step-item--active';
        }
        return 'spa-flow-step-item--pending';
    }

    /**
     * @param {object} st
     * @param {number} idx
     * @param {number} activeIdx
     * @returns {HTMLLIElement}
     */
    function createFlowStepListItem(st, idx, activeIdx, hideStepLabel) {
        const li = document.createElement('li');
        li.className = 'spa-flow-step-item ' + flowStepItemStateClass(idx, activeIdx);
        if (hideStepLabel === true) {
            li.classList.add('spa-flow-step-item--passthrough');
        }
        if (st && st.id != null) {
            li.setAttribute('data-step-id', String(st.id));
        }

        const body = document.createElement('div');
        body.className = 'spa-flow-step-body';

        if (hideStepLabel !== true) {
            const textEl = document.createElement('div');
            textEl.className = 'spa-flow-step-text';
            textEl.textContent = flowStepDisplayText(st, idx);
            body.appendChild(textEl);
        }

        const uiMount = document.createElement('div');
        uiMount.className = 'spa-flow-step-ui';

        body.appendChild(uiMount);
        li.appendChild(body);
        return li;
    }

    /**
     * @param {string} flowIntentId
     * @returns {HTMLElement|null}
     */
    function findActiveFlowRow(flowIntentId) {
        if (!chatMessagesDiv || !flowIntentId) {
            return null;
        }
        const seq = String(bioFlowActivationSeq);
        const row = chatMessagesDiv.querySelector(
            '.spa-chat-flow-row[data-flow-intent-id="' + flowIntentId + '"][data-flow-activation-seq="' + seq + '"]'
        );
        if (!row || row.classList.contains('spa-chat-flow-row--superseded')
            || row.classList.contains('spa-chat-flow-row--completed')) {
            return null;
        }
        return row;
    }

    /**
     * Crea o actualiza el panel del flow con todos los `assistant_text`; la UI vive en el paso activo
     * y se conserva en pasos ya completados (p. ej. lista auto-seleccionada de un solo ítem).
     *
     * @param {object|null} fm
     * @param {string} flowIntentId
     * @param {string} flowActionTitle
     * @returns {{ row: HTMLElement, activeMount: HTMLElement|null }|null}
     */
    function syncFlowStepsPanel(fm, flowIntentId, flowActionTitle) {
        if (!chatMessagesDiv || !fm || typeof fm !== 'object') {
            return null;
        }
        const steps = Array.isArray(fm.steps) ? fm.steps : [];
        if (!steps.length || !flowIntentId) {
            return null;
        }

        purgeLegacyFlowPlanWraps();
        const activeIdx = resolveFlowActiveStepIndex(fm);
        const passthroughFlow = isPassthroughSingleStepFlow(fm);
        let row = findActiveFlowRow(flowIntentId);
        let list;

        if (!row) {
            row = document.createElement('div');
            row.className = 'w-100 mb-3 spa-chat-flow-row';
            if (passthroughFlow) {
                row.classList.add('spa-chat-flow-row--passthrough');
            }
            row.setAttribute('data-flow-intent-id', flowIntentId);
            row.setAttribute('data-flow-activation-seq', String(bioFlowActivationSeq));

            const inner = document.createElement('div');
            inner.className = 'spa-chat-flow-turn w-100';

            const titleStr = typeof flowActionTitle === 'string' ? flowActionTitle.trim() : '';
            if (titleStr !== '' && shouldShowFlowChatHeader(fm)) {
                const header = document.createElement('div');
                header.className = 'spa-flow-chat-header';
                const hFlow = document.createElement('h3');
                hFlow.className = 'spa-flow-chat-title';
                hFlow.textContent = titleStr;
                header.appendChild(hFlow);
                const rule = document.createElement('div');
                rule.className = 'spa-flow-chat-rule';
                rule.setAttribute('aria-hidden', 'true');
                header.appendChild(rule);
                inner.appendChild(header);
            }

            list = document.createElement('ol');
            list.className = 'spa-flow-steps-list list-unstyled mb-0';
            steps.forEach(function (st, idx) {
                list.appendChild(createFlowStepListItem(st, idx, activeIdx, passthroughFlow));
            });
            inner.appendChild(list);
            row.appendChild(inner);
            chatMessagesDiv.appendChild(row);
            setTimeout(scrollChatToBottom, 10);
        } else {
            list = row.querySelector('.spa-flow-steps-list');
            if (!list) {
                return null;
            }
            const items = list.querySelectorAll('.spa-flow-step-item');
            if (items.length !== steps.length) {
                list.innerHTML = '';
                steps.forEach(function (st, idx) {
                    list.appendChild(createFlowStepListItem(st, idx, activeIdx, passthroughFlow));
                });
            } else {
                items.forEach(function (li, idx) {
                    li.className = 'spa-flow-step-item ' + flowStepItemStateClass(idx, activeIdx)
                        + (passthroughFlow ? ' spa-flow-step-item--passthrough' : '');
                    const st = steps[idx];
                    if (st && !passthroughFlow) {
                        const textEl = li.querySelector('.spa-flow-step-text');
                        if (textEl) {
                            textEl.textContent = flowStepDisplayText(st, idx);
                        }
                    }
                    if (idx > activeIdx) {
                        const uiMount = li.querySelector('.spa-flow-step-ui');
                        if (uiMount) {
                            uiMount.innerHTML = '';
                        }
                    }
                });
            }
        }

        let activeMount = null;
        if (activeIdx >= 0 && list) {
            const items = list.querySelectorAll('.spa-flow-step-item');
            const activeLi = items[activeIdx];
            if (activeLi) {
                activeMount = activeLi.querySelector('.spa-flow-step-ui');
                if (!activeMount) {
                    activeMount = document.createElement('div');
                    activeMount.className = 'spa-flow-step-ui';
                    const body = activeLi.querySelector('.spa-flow-step-body');
                    if (body) {
                        body.appendChild(activeMount);
                    } else {
                        activeLi.appendChild(activeMount);
                    }
                }
            }
        }

        return { row: row, activeMount: activeMount };
    }

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
            syncChatComposerLayout();
        }
        applyChatHeight();
        bindChatComposerLayoutObserver();
        purgeLegacyFlowPlanWraps();
        requestAnimationFrame(function () {
            applyChatHeight();
            purgeLegacyFlowPlanWraps();
        });
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

        // Cargar atajos (menú Atajos + panel inicial del chat vacío)
        if (shortcutsContent || welcomeActionsEl) {
            loadCommonActions();
        }

        // Event listeners solo si los elementos existen
        if (sendBtn && queryInput) {
            sendBtn.addEventListener('click', handleSendQuery);
            queryInput.addEventListener('keydown', handleKeyDown);
            queryInput.addEventListener('input', handleInput);

            // Focus en textarea al cargar
            queryInput.focus();
            handleInput();
        }

        // El menú Atajos se maneja como dropdown Bootstrap (no modal).
    }

    function startFlowFromShortcut(intentId, displayName) {
        const iid = String(intentId || '').trim();
        if (!iid) return;

        // Reset estado anterior y disparar flow por snapshot (sin escribir en el composer).
        supersedeAllFlowRows();
        beginNewFlowActivation();
        currentIntentId = iid;
        currentSubintentId = null;
        draft = {};
        flowSnapshot = {};
        writeFlowState();

        // Enviar snapshot sin texto (override string para no agregar burbuja de usuario).
        handleSendQuery('');
    }

    function scrollChatToBottom() {
        syncChatComposerLayout();
        if (!chatMessagesDiv) return;
        try {
            const root = document.scrollingElement || document.documentElement;
            if (root) {
                root.scrollTo({ top: root.scrollHeight, behavior: 'smooth' });
            }
        } catch (e) {
            try {
                chatMessagesDiv.scrollTop = chatMessagesDiv.scrollHeight;
            } catch (e2) { /* ignore */ }
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
        const base = 'spa-chat-bubble p-2 rounded-3';
        const roleMod = role === 'user'
            ? ' spa-chat-bubble--user'
            : (role === 'system' ? ' spa-chat-bubble--system' : ' spa-chat-bubble--assistant');
        const theme = role === 'user'
            ? ' bg-primary text-white'
            : (role === 'system' ? ' bg-light text-muted border' : ' bg-white border');
        bubble.className = base + roleMod + theme;
        bubble.style.maxWidth = '95%';
        bubble.innerHTML = html;

        row.appendChild(bubble);
        chatMessagesDiv.appendChild(row);
        setTimeout(scrollChatToBottom, 10);
        return bubble;
    }

    /**
     * Encabezado del flow en el panel de chat (solo una vez por activación).
     *
     * @param {object|null|undefined} fm
     * @returns {boolean}
     */
    function shouldShowFlowChatHeader(fm) {
        if (!fm || typeof fm !== 'object') {
            return false;
        }
        const intentId = fm.intent_id != null ? String(fm.intent_id).trim() : '';
        if (intentId === '') {
            return !!(fm.action_name != null && String(fm.action_name).trim() !== '');
        }
        if (!chatMessagesDiv) {
            return true;
        }
        const seq = String(bioFlowActivationSeq);
        const rows = chatMessagesDiv.querySelectorAll('.spa-chat-flow-row');
        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            if (row.getAttribute('data-flow-intent-id') === intentId
                && row.getAttribute('data-flow-activation-seq') === seq
                && row.querySelector('.spa-flow-chat-header')) {
                return false;
            }
        }
        return true;
    }

    /**
     * Respuesta "ancha" (mensaje del asistente) que puede incluir cards sugeridas u otras UIs.
     * Forma parte del historial conversacional: nunca se reemplaza ni se oculta.
     *
     * @param {Object} opts
     * @param {string} [opts.explanationHtml] - HTML ya escapado/sanitizado por el llamador
     * @param {string} [opts.actionsHtml] - HTML de cards en grilla `.row.g-3`
     * @param {string} [opts.variant] - 'plain' | 'info' | 'danger'
     * @returns {{row: HTMLDivElement, panel: HTMLDivElement, explanationEl: HTMLDivElement, actionsEl: HTMLDivElement}|null}
     */
    function appendAssistantResponsePanel(opts) {
        if (!chatMessagesDiv) return null;
        const o = opts && typeof opts === 'object' ? opts : {};
        const explanationHtml = typeof o.explanationHtml === 'string' ? o.explanationHtml : '';
        const actionsHtml = typeof o.actionsHtml === 'string' ? o.actionsHtml : '';
        const variant = typeof o.variant === 'string' ? o.variant : 'plain';

        const row = document.createElement('div');
        row.className = 'd-flex justify-content-start mb-3';

        const panel = document.createElement('div');
        panel.className = 'bg-white border rounded-4 p-3 w-100';

        const explanationEl = document.createElement('div');
        explanationEl.className = 'mb-3';
        if (variant === 'info') {
            explanationEl.innerHTML = '<div class="alert alert-info mb-0">' + explanationHtml + '</div>';
        } else if (variant === 'danger') {
            explanationEl.innerHTML = '<div class="alert alert-danger mb-0">' + explanationHtml + '</div>';
        } else {
            explanationEl.innerHTML = '<div class="mb-0 spa-chat-bubble-text spa-chat-bubble-text--assistant">' + explanationHtml + '</div>';
        }

        const actionsEl = document.createElement('div');
        actionsEl.className = 'row g-3';
        actionsEl.innerHTML = actionsHtml || '';

        panel.appendChild(explanationEl);
        panel.appendChild(actionsEl);
        row.appendChild(panel);
        chatMessagesDiv.appendChild(row);
        setTimeout(scrollChatToBottom, 10);
        return { row: row, panel: panel, explanationEl: explanationEl, actionsEl: actionsEl };
    }

    /** @param {object|null|undefined} envelope */
    function assistantFlowSession(envelope) {
        const s = envelope && envelope.session;
        return (s && typeof s === 'object') ? s : {};
    }

    /** @param {object|null|undefined} envelope */
    function assistantFlowStep(envelope) {
        const st = envelope && envelope.step;
        return (st && typeof st === 'object') ? st : {};
    }

    /** @param {object|null|undefined} envelope */
    function assistantFlowManifest(envelope) {
        const m = envelope && envelope.manifest;
        return (m && typeof m === 'object') ? m : null;
    }

    /** @param {object|null|undefined} envelope */
    function assistantFlowOpenUi(envelope) {
        const step = assistantFlowStep(envelope);
        if (!step.active) {
            return null;
        }
        return {
            action_id: step.action_id != null ? String(step.action_id) : '',
            client_open: step.client_open && typeof step.client_open === 'object' ? step.client_open : null
        };
    }

    /** @param {object|null|undefined} envelope */
    function assistantFlowDismiss(envelope) {
        const d = envelope && envelope.dismiss;
        if (!d || typeof d !== 'object' || d.active !== true) {
            return null;
        }
        return {
            label: d.label != null ? String(d.label) : 'Entendido',
            actions: Array.isArray(d.actions) ? d.actions : []
        };
    }

    /** @param {object|null|undefined} envelope */
    function assistantFlowSubmit(envelope) {
        const sub = envelope && envelope.submit;
        if (!sub || typeof sub !== 'object' || !sub.active) {
            return null;
        }
        return {
            route: sub.route != null ? String(sub.route) : '',
            method: sub.method != null ? String(sub.method) : 'POST',
            body_template: sub.body_template && typeof sub.body_template === 'object' ? sub.body_template : {}
        };
    }

    /**
     * Procesar payload JSON del asistente (POST /api/v1/asistente/enviar).
     * También usado al ejecutar una acción tipo intent desde una card.
     */
    function handleAssistantResponse(data) {
            const intentIdBeforeResponse = currentIntentId ? String(currentIntentId) : '';

            const envelope = data && typeof data === 'object' ? data : {};
            const kind = envelope.kind ? String(envelope.kind) : '';
            const flowText = typeof envelope.text === 'string' ? envelope.text : null;
            const primaryText = (flowText && flowText.trim() !== '') ? flowText.trim() : '';
            const session = assistantFlowSession(envelope);

            if (kind === 'message') {
                setLoadingState(false);
                removeFlowPlanStrip();
                appendChatBubble('bot', '<div class="mb-0 spa-chat-bubble-text spa-chat-bubble-text--assistant">' + escapeHtml(primaryText || 'Ok.') + '</div>');
                setTimeout(scrollChatToBottom, 20);
                return;
            }

            if (kind === 'interactive') {
                setLoadingState(false);
                removeFlowPlanStrip();
                supersedeAllFlowRows();
                const remText = primaryText || 'Elegí una opción';
                const wrap = appendChatBubble('bot', '<div class="mb-0 spa-chat-bubble-text spa-chat-bubble-text--assistant">' + escapeHtml(remText) + '</div>');
                const buttons = Array.isArray(envelope.buttons) ? envelope.buttons : [];
                if (wrap && buttons.length > 0) {
                    const row = document.createElement('div');
                    row.className = 'd-flex flex-wrap justify-content-center gap-2 mt-2 spa-intent-remediation';
                    buttons.forEach(function (ch) {
                        if (!ch || !ch.intent_id) {
                            return;
                        }
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'btn btn-sm btn-outline-secondary';
                        b.textContent = ch.label ? String(ch.label) : String(ch.intent_id);
                        b.addEventListener('click', function () {
                            row.querySelectorAll('button').forEach(function (x) {
                                x.disabled = true;
                                x.classList.remove('btn-secondary');
                                x.classList.add('btn-outline-secondary');
                            });
                            b.classList.remove('btn-outline-secondary');
                            b.classList.add('btn-secondary');
                            supersedeAllFlowRows();
                            beginNewFlowActivation();
                            currentIntentId = String(ch.intent_id);
                            currentSubintentId = null;
                            draft = {};
                            flowSnapshot = {};
                            writeFlowState();
                            handleSendQuery('');
                        });
                        row.appendChild(b);
                    });
                    if (chatMessagesDiv) {
                        const rowWrap = document.createElement('div');
                        rowWrap.className = 'd-flex justify-content-center w-100 mb-3';
                        rowWrap.appendChild(row);
                        chatMessagesDiv.appendChild(rowWrap);
                    }
                }
                setTimeout(scrollChatToBottom, 20);
                return;
            }

            // Si trae session, sincronizar estado conversacional.
            if (session.intent_id) {
                const newIntentId = String(session.intent_id);
                if (intentIdBeforeResponse && intentIdBeforeResponse !== newIntentId) {
                    supersedeDiscardedFlowRows(newIntentId);
                    beginNewFlowActivation();
                }
                currentIntentId = newIntentId;
                writeFlowState();
            }
            if (session.subintent_id) {
                currentSubintentId = String(session.subintent_id);
                writeFlowState();
            }
            if (session.draft_delta && typeof session.draft_delta === 'object') {
                try {
                    applyDraftDelta(session.draft_delta || {});
                    writeFlowState();
                } catch (e) {
                    // ignore
                }
            }

            // Flow conversacional: mini-UI en bloque a ancho completo (no burbuja angosta).
            if (kind === 'flow') {
                const fm = assistantFlowManifest(envelope);
                if (fm && typeof fm === 'object') {
                    currentFlowManifest = fm;
                }

                let flowActionTitle = '';
                if (fm && fm.action_name != null && String(fm.action_name).trim() !== '') {
                    flowActionTitle = String(fm.action_name).trim();
                }

                const flowIntentId = fm && fm.intent_id != null ? String(fm.intent_id).trim()
                    : (session.intent_id ? String(session.intent_id).trim() : '');

                const panel = syncFlowStepsPanel(fm, flowIntentId, flowActionTitle);
                if (!panel || !panel.row) {
                    showError('No se pudo mostrar el flujo en el chat.');
                    return;
                }
                unlockFlowListPicksInRow(panel.row);

                const flowSectionInner = panel.activeMount;

                const openUi = assistantFlowOpenUi(envelope);
                const co = openUi && openUi.client_open && typeof openUi.client_open === 'object' ? openUi.client_open : null;
                const activeStep = fm && fm.active_step && typeof fm.active_step === 'object' ? fm.active_step : null;

                const uiMeta = activeStep && activeStep.ui && typeof activeStep.ui === 'object' ? activeStep.ui : null;
                const tabs = uiMeta && Array.isArray(uiMeta.tabs) ? uiMeta.tabs : [];
                const defaultTabId = uiMeta && uiMeta.default_tab != null ? String(uiMeta.default_tab) : '';

                const fsr = assistantFlowSubmit(envelope);
                const fdr = assistantFlowDismiss(envelope);
                const flowFetchOpts = { enableFlowChainAutoAdvance: true, flowDismissOpt: fdr };

                // `open_ui` en esta respuesta: lo que el servidor pide montar ahora. El manifiesto describe el paso
                // (puede incluir tabs del paso anterior); no implica que siempre haya que abrir URL en este turno.
                const hasOpenUi = !!(openUi && openUi.action_id);
                const okUiJson = co && String(co.kind || '') === 'ui_json' && co.api && co.api.route;
                const serverAskedForUi = hasOpenUi || !!okUiJson;

                function flowSubmitClearState() {
                    clearFlowState();
                    removeFlowPlanStrip();
                }

                // Resolver URL: primero `client_open` ui_json del payload; si hay `flow_submit` adjunto,
                // se monta la mini-UI del paso activo y el botón de cierre se integra al final del bloque
                // (mismo paso, no mensaje aparte).
                let fullUrl = '';
                if (okUiJson) {
                    const route = applyDraftPlaceholdersToRoute(String(co.api.route || ''));
                    fullUrl = mergeApiQueryIntoUrl(resolveSpaFetchUrl(route), co.api);
                } else if (fsr && fsr.route && tabs.length >= 1) {
                    let defIdx = 0;
                    for (let ti = 0; ti < tabs.length; ti++) {
                        if (defaultTabId !== '' && String(tabs[ti].id) === defaultTabId) {
                            defIdx = ti;
                            break;
                        }
                    }
                    fullUrl = buildUrlForFlowTab(tabs[defIdx]);
                } else if (tabs.length >= 1 && !(hasOpenUi && !okUiJson)) {
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
                    // Sin nada que montar: el flujo puede haber terminado sin `open_ui` (no es anómalo).
                    if (!serverAskedForUi) {
                        currentIntentId = null;
                        currentSubintentId = null;
                        draft = {};
                        flowSnapshot = {};
                        writeFlowState();
                        removeFlowPlanStrip();
                        setTimeout(scrollChatToBottom, 20);
                        return;
                    }
                    if (!flowSectionInner) {
                        showError('No se pudo montar la UI del paso activo.');
                        return;
                    }
                    if (fsr && fsr.route && fsr.body_template && typeof fsr.body_template === 'object') {
                        removeFlowPlanStrip();
                        flowSectionInner.innerHTML = '';
                        appendFlowInlineSubmit(flowSectionInner, fsr, flowSubmitClearState);
                        setTimeout(scrollChatToBottom, 20);
                        return;
                    }
                    removeFlowPlanStrip();
                    flowSectionInner.innerHTML = '';
                    const errHtml = openUi && openUi.action_id
                        ? ('<div class="alert alert-danger mb-0 mt-2">No puedo abrir la mini-UI requerida (' + escapeHtml(String(openUi.action_id)) + ').</div>')
                        : ('<div class="alert alert-danger mb-0 mt-2">No puedo determinar la UI a abrir para este paso.</div>');
                    const errWrap = document.createElement('div');
                    errWrap.className = 'mt-2';
                    errWrap.innerHTML = errHtml;
                    flowSectionInner.appendChild(errWrap);
                    setTimeout(scrollChatToBottom, 20);
                    return;
                }

                if (!flowSectionInner) {
                    showError('No se pudo montar la UI del paso activo.');
                    return;
                }

                // Montaje: solo en el paso activo (`.spa-flow-step-ui`).
                flowSectionInner.innerHTML = '';
                const mountHost = flowSectionInner;
                /** Nodo dedicado para `renderDynamicUi` (no sustituir el contenedor del título). */
                let flowUiMount = null;

                if (tabs.length >= 2) {
                    const tabRow = document.createElement('div');
                    tabRow.className = 'd-flex flex-wrap gap-2 mb-2 mt-2';
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
                                fetchFlowUiDefinition(u.toString(), mountEl, fsr, flowFetchOpts);
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
                        fetchFlowUiDefinition(url, mountEl, fsr, flowFetchOpts);
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

                // No montar en el root del bloque: `renderDynamicUi` reemplaza innerHTML del hijo, no el h4.
                flowUiMount = document.createElement('div');
                flowUiMount.className = 'spa-chat-flow-ui w-100 mt-2';
                flowUiMount.setAttribute('data-spa-flow-ui-mount', '1');
                mountHost.appendChild(flowUiMount);
                fetchFlowUiDefinition(fullUrl, flowUiMount, fsr, flowFetchOpts);
                return;
            }

            removeFlowPlanStrip();

            // Respuestas legacy (CRUD / motor antiguo sin sobre v3).
            const result = envelope;
            
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
    }

    /**
     * Manejar envío de consulta
     */
    function handleSendQuery(contentOverride) {
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
        if (chatEmptyHint) {
            chatEmptyHint.classList.add('d-none');
        }
        syncShortcutsToolbarVisibility();

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
            appendChatBubble('user', '<div class="mb-0 spa-chat-bubble-text spa-chat-bubble-text--user">' + escapeHtml(query) + '</div>');
        }

        // Usar endpoint de la API. Importante: en entornos donde el frontend vive bajo /api,
        // window.spaConfig.baseUrl puede ser https://host/api y concatenar "/api/..." duplica.
        const asistenteUrl = window.location.origin + '/api/v1/asistente/enviar';
        const body = {};

        // Texto libre = nueva consulta al IntentEngine; se conserva el flow solo para “cerca…” (misma heurística que SubIntentEngine).
        if (currentIntentId && query !== '' && !userSaysNearbyForEfectorChooser(query)) {
            supersedeAllFlowRows();
            currentIntentId = null;
            currentSubintentId = null;
            draft = {};
            flowSnapshot = {};
            writeFlowState();
        }

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
                    // Cuerpo JSON típico API: { "success": false, "message": "...", "errors": ... }
                    let msgFromBody = '';
                    if (text && String(text).trim() !== '') {
                        try {
                            const j = JSON.parse(text);
                            if (handleApiUnauthorized(response.status, j)) {
                                return;
                            }
                            if (j && typeof j === 'object') {
                                if (j.message != null && String(j.message).trim() !== '') {
                                    msgFromBody = String(j.message).trim();
                                } else if (j.error != null && String(j.error).trim() !== '') {
                                    msgFromBody = String(j.error).trim();
                                }
                            }
                        } catch (parseErr) {
                            // No era JSON; seguimos con mensajes por código HTTP.
                        }
                    }
                    if (handleApiUnauthorized(response.status, null)) {
                        return;
                    }
                    if (msgFromBody) {
                        throw new Error(msgFromBody);
                    }
                    if (response.status === 400) {
                        throw new Error('Error de validación. Por favor, verifica tu consulta e intenta nuevamente.');
                    }
                    if (response.status === 401) {
                        throw new Error('Debes estar autenticado para usar esta funcionalidad.');
                    }
                    const st = response.statusText ? String(response.statusText).trim() : '';
                    throw new Error(st ? ('Error ' + response.status + ': ' + st) : ('Error ' + response.status));
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
        .then(handleAssistantResponse)
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
        const explanationHtml = escapeHtml(data.explanation || '');
        let actionsHtml = '';

        // Si hay formulario, renderizarlo
        if (data.form && data.form.success) {
            actionsHtml = renderCrudForm(data.form, data.intention, data.entity_id);
        } else if (data.action) {
            // Acción directa (navegación, eliminación, etc)
            actionsHtml = renderCrudAction(data);
        } else if (data.suggested_actions && data.suggested_actions.length > 0) {
            // Acciones sugeridas cuando la entidad no existe
            actionsHtml = renderActionCards(data.suggested_actions);
        } else {
            actionsHtml = '<div class="col-12"><p class="text-muted mb-0">' + escapeHtml(data.message || 'No hay acciones disponibles') + '</p></div>';
        }

        const panel = appendAssistantResponsePanel({ explanationHtml: explanationHtml, actionsHtml: actionsHtml, variant: 'plain' });
        if (panel && panel.actionsEl) {
            attachCardListeners(panel.actionsEl);
        } else {
            attachCardListeners();
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
    function editSparseContextFromJson(json, options) {
        if (options && options.editSparse && typeof options.editSparse === 'object') {
            return options.editSparse;
        }
        if (json && json.ui_meta && json.ui_meta.edit_sparse && typeof json.ui_meta.edit_sparse === 'object') {
            return json.ui_meta.edit_sparse;
        }
        return null;
    }

    function ensureEditSparseChainRoot(mountEl) {
        if (!mountEl) {
            return null;
        }
        let chain = mountEl.querySelector(':scope > .bio-edit-sparse-chain');
        if (chain) {
            return chain;
        }
        chain = document.createElement('div');
        chain.className = 'bio-edit-sparse-chain d-flex flex-column gap-3 w-100';
        const nodes = [];
        while (mountEl.firstChild) {
            nodes.push(mountEl.removeChild(mountEl.firstChild));
        }
        if (nodes.length > 0) {
            const firstStep = document.createElement('div');
            firstStep.className = 'bio-edit-sparse-step w-100';
            nodes.forEach(function (n) {
                firstStep.appendChild(n);
            });
            chain.appendChild(firstStep);
        }
        mountEl.appendChild(chain);
        return chain;
    }

    function removeEditSparseStepsAfter(stepEl) {
        if (!stepEl || !stepEl.parentNode) {
            return;
        }
        let sib = stepEl.nextElementSibling;
        while (sib) {
            const next = sib.nextElementSibling;
            if (sib.classList.contains('bio-edit-sparse-step')
                || sib.classList.contains('bio-edit-sparse-loader')
                || sib.classList.contains('bio-edit-sparse-error')) {
                sib.parentNode.removeChild(sib);
            }
            sib = next;
        }
    }

    function appendEditSparseChainMessage(chain, mountEl, message, severity) {
        const sev = severity === 'warning' ? 'warning' : 'danger';
        const wrap = document.createElement('div');
        wrap.className = 'bio-edit-sparse-step bio-edit-sparse-error w-100';
        wrap.innerHTML = '<div class="alert alert-' + sev + ' mb-0">'
            + escapeHtml(message)
            + '</div>';
        if (chain) {
            chain.appendChild(wrap);
        } else if (mountEl) {
            mountEl.innerHTML = '';
            mountEl.appendChild(wrap);
        }
    }

    function buildEditSparseAdvanceUrl(baseUrl, editSparse, selectedId, item) {
        const url = new URL(resolveSpaFetchUrl(String(baseUrl || '')), window.location.origin);
        const meta = item && item.meta && typeof item.meta === 'object' ? item.meta : null;
        const advanceViaFieldMeta = editSparse.advance_via_field_meta === true
            && meta
            && meta.aspect_id != null
            && String(meta.aspect_id).trim() !== '';

        let nextStep = editSparse.next_step != null ? String(editSparse.next_step).trim() : 'aspects';
        if (advanceViaFieldMeta) {
            nextStep = 'form';
        }
        if (nextStep !== '') {
            url.searchParams.set('step', nextStep);
        }
        if (editSparse.surface_id != null && String(editSparse.surface_id).trim() !== '') {
            url.searchParams.set('surface_id', String(editSparse.surface_id).trim());
        }
        const pesParam = editSparse.pes_param != null
            ? String(editSparse.pes_param).trim()
            : 'id_profesional_efector_servicio';
        const selParam = editSparse.selection_param != null
            ? String(editSparse.selection_param).trim()
            : 'id_persona';
        if (advanceViaFieldMeta) {
            url.searchParams.set('aspect_ids', String(meta.aspect_id).trim());
            if (meta.fields != null && String(meta.fields).trim() !== '') {
                url.searchParams.set('fields', String(meta.fields).trim());
            }
        } else if (selectedId) {
            url.searchParams.set(pesParam, String(selectedId));
        }
        if (meta) {
            if (meta[selParam] != null && String(meta[selParam]).trim() !== '') {
                url.searchParams.set(selParam, String(meta[selParam]).trim());
            }
            if (meta.id_servicio != null && String(meta.id_servicio).trim() !== '') {
                url.searchParams.set('id_servicio', String(meta.id_servicio).trim());
            }
        }
        return url.toString();
    }

    function fetchEditSparseUiDefinition(nextUrl, rootContainer, options) {
        options = options && typeof options === 'object' ? options : {};
        const mountEl = options.editSparseMountRoot || rootContainer;
        if (!mountEl) {
            return Promise.resolve();
        }
        const chain = ensureEditSparseChainRoot(mountEl);
        const loader = document.createElement('div');
        loader.className = 'd-flex align-items-center justify-content-center gap-2 py-2 text-muted bio-edit-sparse-loader';
        loader.innerHTML = '<div class="spinner-border spinner-border-sm"></div> Cargando...';
        if (chain) {
            chain.appendChild(loader);
        } else {
            mountEl.innerHTML = loader.outerHTML;
        }
        return fetch(nextUrl, {
            method: 'GET',
            headers: window.BioenlaceApiClient.mergeHeaders({
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            })
        })
            .then(function (r) {
                if (!r.ok) {
                    return r.text().then(function (t) {
                        let msg = '';
                        try {
                            const j = JSON.parse(t);
                            if (j && typeof j.message === 'string') {
                                msg = j.message.trim();
                            }
                        } catch (parseErr) { /* ignore */ }
                        throw new Error(msg !== '' ? msg : ('HTTP ' + r.status));
                    });
                }
                return r.json();
            })
            .then(function (json) {
                if (loader.parentNode) {
                    loader.parentNode.removeChild(loader);
                }
                if (json && json.kind === 'ui_definition') {
                    const stepEl = document.createElement('div');
                    stepEl.className = 'bio-edit-sparse-step w-100';
                    if (chain) {
                        chain.appendChild(stepEl);
                    } else {
                        mountEl.innerHTML = '';
                        mountEl.appendChild(stepEl);
                    }
                    renderDynamicUi(json, stepEl, Object.assign({}, options, {
                        url: nextUrl,
                        rootContainer: stepEl,
                        editSparseMountRoot: mountEl,
                        enableFlowChainAutoAdvance: options.enableFlowChainAutoAdvance !== false
                    }));
                } else {
                    appendEditSparseChainMessage(
                        chain,
                        mountEl,
                        'La respuesta no es una definición de UI válida.',
                        'warning'
                    );
                }
            })
            .catch(function (err) {
                if (loader.parentNode) {
                    loader.parentNode.removeChild(loader);
                }
                const msg = (err && err.message) ? String(err.message).trim() : '';
                appendEditSparseChainMessage(
                    chain,
                    mountEl,
                    msg !== '' ? msg : 'Error al cargar el siguiente paso.',
                    'danger'
                );
            });
    }

    function renderDynamicUi(json, container, options = {}) {
        if (!json || !container) {
            return;
        }

        const uiType = json.ui_type || 'ui_json';
        const renderOptions = Object.assign({}, options, {
            rootContainer: options.rootContainer || container,
            editSparse: editSparseContextFromJson(json, options)
        });

        switch (uiType) {
            case 'ui_json':
                if (Array.isArray(json.blocks)) {
                    renderUiJsonBlocks(json, container, renderOptions);
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
        let blocks = Array.isArray(json.blocks) ? json.blocks : [];
        if (blocks.length > 0 && blocks.every(function (b) {
            return b && typeof b === 'object' && b.display_order != null;
        })) {
            blocks = blocks.slice().sort(function (a, b) {
                return (Number(a.display_order) || 0) - (Number(b.display_order) || 0);
            });
        }
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
            } else if (kind === 'message') {
                renderUiJsonMessageBlock(b, mount);
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

    function listBlockItemsFromUiDefinition(json, blockId) {
        try {
            const blocks = json && Array.isArray(json.blocks) ? json.blocks : [];
            let b = null;
            if (blockId) {
                const sid = String(blockId);
                for (let i = 0; i < blocks.length; i++) {
                    if (blocks[i] && String(blocks[i].id || '') === sid) {
                        b = blocks[i];
                        break;
                    }
                }
            }
            if (!b) {
                for (let j = 0; j < blocks.length; j++) {
                    if (blocks[j] && String(blocks[j].kind || '') === 'list') {
                        b = blocks[j];
                        break;
                    }
                }
            }
            return b && Array.isArray(b.items) ? b.items : [];
        } catch (e) {
            return [];
        }
    }

    function setUrlQueryParam(href, key, value) {
        try {
            const u = new URL(href, window.location.origin);
            if (value === '' || value === null || value === undefined) {
                u.searchParams.delete(key);
            } else {
                u.searchParams.set(key, String(value));
            }
            return u.toString();
        } catch (e) {
            return href;
        }
    }

    function uiJsonListPresentationClass(block) {
        const allowedTile = { compact: 1, medium: 1, large: 1 };
        const allowedShape = { square: 1, wide: 1, auto: 1 };
        const p = block.presentation && typeof block.presentation === 'object' ? block.presentation : {};
        let tile = p.tile != null ? String(p.tile).trim().toLowerCase() : 'medium';
        let shape = p.shape != null ? String(p.shape).trim().toLowerCase() : 'wide';
        if (!allowedTile[tile]) tile = 'medium';
        if (!allowedShape[shape]) shape = 'wide';
        return 'bio-ui-json-list--tile-' + tile + ' bio-ui-json-list--shape-' + shape;
    }

    function renderUiJsonMessageBlock(block, container) {
        const text = block.text != null ? String(block.text) : (block.body != null ? String(block.body) : '');
        if (text.trim() === '') {
            container.innerHTML = '';
            return;
        }
        const severity = block.severity ? String(block.severity) : '';
        const alertClass = severity === 'warning' ? ' alert alert-warning' : (severity === 'danger' ? ' alert alert-danger' : '');
        let html = '<div class="bio-ui-json-message' + alertClass + '">';
        html += '<div class="bio-ui-json-message-body mb-0" style="white-space:pre-wrap;">'
            + escapeHtml(text)
            + '</div></div>';
        container.innerHTML = html;
    }

    function uiJsonListIsReadOnly(block) {
        const selection = block.selection && typeof block.selection === 'object' ? block.selection : {};
        const mode = selection.mode != null ? String(selection.mode).trim().toLowerCase() : '';
        if (mode === 'none') {
            return true;
        }
        const p = block.presentation && typeof block.presentation === 'object' ? block.presentation : {};
        if (String(p.layout || '').trim().toLowerCase() === 'table') {
            return true;
        }
        return !(block.draft_field && String(block.draft_field).trim() !== '');
    }

    function uiJsonListColumnsFromBlock(block) {
        const cols = Array.isArray(block.columns) ? block.columns : [];
        const out = [];
        cols.forEach(function (col) {
            if (!col || typeof col !== 'object') return;
            const field = col.field != null ? String(col.field).trim() : '';
            if (!field) return;
            const label = col.label != null ? String(col.label).trim() : field;
            out.push({ field: field, label: label });
        });
        if (out.length === 0) {
            out.push({ field: 'name', label: 'Nombre' });
        }
        return out;
    }

    function uiJsonListCellValue(item, field) {
        if (!item || typeof item !== 'object' || !field) return '';
        if (Object.prototype.hasOwnProperty.call(item, field)) {
            const v = item[field];
            return v == null ? '' : String(v).trim();
        }
        if (field.indexOf('meta.') === 0) {
            const meta = item.meta;
            const key = field.substring(5);
            if (meta && typeof meta === 'object' && Object.prototype.hasOwnProperty.call(meta, key)) {
                const v = meta[key];
                return v == null ? '' : String(v).trim();
            }
        }
        return '';
    }

    function renderUiJsonListTableBlock(block, container) {
        const title = block.title ? String(block.title) : '';
        const items = Array.isArray(block.items) ? block.items : [];
        const emptyMsg = block.empty_message ? String(block.empty_message) : 'No hay registros que coincidan con los filtros.';
        const columns = uiJsonListColumnsFromBlock(block);

        let html = '<div class="bio-ui-json-list bio-ui-json-list--layout-table">';
        if (title) {
            html += '<div class="fw-semibold mb-2">' + escapeHtml(title) + '</div>';
        }
        if (items.length === 0) {
            html += '<div class="small text-muted mb-0">' + escapeHtml(emptyMsg) + '</div>';
        } else {
            html += '<div class="table-responsive"><table class="table table-sm table-striped bio-ui-json-list-table mb-0">';
            html += '<thead><tr>';
            columns.forEach(function (col) {
                html += '<th scope="col">' + escapeHtml(col.label) + '</th>';
            });
            html += '</tr></thead><tbody>';
            items.forEach(function (it) {
                if (!it || typeof it !== 'object') return;
                html += '<tr>';
                columns.forEach(function (col) {
                    html += '<td>' + escapeHtml(uiJsonListCellValue(it, col.field)) + '</td>';
                });
                html += '</tr>';
            });
            html += '</tbody></table></div>';
        }
        html += '</div>';
        container.innerHTML = html;
    }

    function renderUiJsonListBlock(block, container, options = {}) {
        if (uiJsonListIsReadOnly(block)) {
            renderUiJsonListTableBlock(block, container);
            return;
        }
        const title = block.title ? String(block.title) : '';
        const items = Array.isArray(block.items) ? block.items : [];
        const draftField = block.draft_field ? String(block.draft_field) : '';
        const selection = block.selection && typeof block.selection === 'object' ? block.selection : {};
        const requiresConfirmation = selection.requires_confirmation === true;
        const emptyMsg = block.empty_message ? String(block.empty_message) : '';
        const itemKind = block.item && block.item.kind ? String(block.item.kind) : '';
        const blockId = block.id != null ? String(block.id) : '';
        const baseListUrl = options.url ? String(options.url) : '';
        const presClass = uiJsonListPresentationClass(block);

        container.__bioListPickLocked = container.__bioListPickLocked === true;
        let selectedId = '';
        let itemsById = {};

        function rebuildItemsById(listItems) {
            itemsById = {};
            (Array.isArray(listItems) ? listItems : []).forEach(function (it) {
                if (it && it.id !== undefined) {
                    itemsById[String(it.id)] = it;
                }
            });
        }
        rebuildItemsById(items);

        function buildPickButtonsHtml(listItems) {
            let h = '<div class="d-flex gap-2 overflow-auto pb-2 flex-wrap">';
            (Array.isArray(listItems) ? listItems : []).forEach((it) => {
                const id = it && it.id !== undefined ? String(it.id) : '';
                const name = it && (it.name || it.label) ? String(it.name || it.label) : id;
                if (!id) return;
                h += '<button type="button" class="btn btn-outline-primary btn-sm text-nowrap position-relative" data-embed-pick="1" data-embed-id="' + escapeHtml(id) + '" data-embed-label="' + escapeHtml(name) + '">';
                h += '<span class="bio-ui-pick-check position-absolute top-50 end-0 translate-middle badge rounded-pill bg-success d-none" aria-hidden="true">✓</span>';
                h += escapeHtml(name);
                h += '</button>';
            });
            h += '</div>';
            return h;
        }

        const showPersonaSearch = itemKind === 'persona' && baseListUrl !== '';

        let html = '<div class="bio-ui-json-list ' + presClass + '">';
        if (title) {
            html += '<div class="fw-semibold mb-2">' + escapeHtml(title) + '</div>';
        }
        if (showPersonaSearch) {
            html += '<div class="mb-2">';
            html += '<label class="form-label small text-muted mb-1">Buscar persona</label>';
            html += '<input type="search" class="form-control form-control-sm" autocomplete="off" placeholder="Nombre, apellido o documento…" data-bio-list-persona-q="1" />';
            html += '</div>';
        }
        if (items.length === 0 && emptyMsg) {
            html += '<div class="small text-muted mb-2" data-bio-list-empty-hint="1">' + escapeHtml(emptyMsg) + '</div>';
        }
        html += '<div class="bio-ui-json-list-items" data-bio-list-items="1">';
        html += buildPickButtonsHtml(items);
        html += '</div>';
        const effectiveRequiresConfirmation = requiresConfirmation && options.isTerminalFlowStep !== true;
        if (effectiveRequiresConfirmation) {
            html += '<div class="d-flex justify-content-end pt-2">';
            html += '<button type="button" class="btn btn-primary btn-sm" data-embed-confirm="1" disabled>Confirmar</button>';
            html += '</div>';
        }
        html += '</div>';
        container.innerHTML = html;

        const itemsMount = container.querySelector('[data-bio-list-items="1"]');
        const emptyHintEl = container.querySelector('[data-bio-list-empty-hint="1"]');
        const confirmBtn = container.querySelector('button[data-embed-confirm="1"]');
        const searchInput = container.querySelector('input[data-bio-list-persona-q="1"]');

        function allPickButtons() {
            return Array.from(container.querySelectorAll('button[data-embed-pick="1"]'));
        }

        function setSelected(btn, id) {
            selectedId = id || '';
            allPickButtons().forEach(b => {
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

        function isListPickLocked() {
            return container.__bioListPickLocked === true;
        }

        function confirmSelection() {
            if (isListPickLocked()) return;
            if (!draftField) return;
            if (!selectedId) return;

            const isTerminalFlowStep = options.isTerminalFlowStep === true;
            const editSparse = options.editSparse && typeof options.editSparse === 'object'
                ? options.editSparse
                : null;
            const item = itemsById[selectedId];

            const flowRow = container.closest('.spa-chat-flow-row');
            const stepLi = container.closest('.spa-flow-step-item');
            const stepId = stepLi ? stepLi.getAttribute('data-step-id') : '';
            const pickedStepIdx = resolveFlowStepIndexById(currentFlowManifest, stepId);
            const activeIdx = resolveFlowActiveStepIndex(currentFlowManifest);
            if (pickedStepIdx >= 0 && activeIdx >= 0 && pickedStepIdx <= activeIdx) {
                clearDraftProvidesFromStepIndex(currentFlowManifest, pickedStepIdx);
                if (flowRow) {
                    clearFlowStepUiFromIndex(flowRow, pickedStepIdx + 1);
                }
                if (pickedStepIdx < activeIdx && currentFlowManifest) {
                    const rewindSteps = Array.isArray(currentFlowManifest.steps) ? currentFlowManifest.steps : [];
                    const rewindStep = rewindSteps[pickedStepIdx];
                    if (rewindStep && rewindStep.id != null) {
                        currentSubintentId = String(rewindStep.id);
                        writeFlowState();
                    }
                }
            }

            if (editSparse && options.url) {
                const sparseRoot = options.editSparseMountRoot || options.rootContainer;
                if (!sparseRoot) {
                    return;
                }
                if (container.__bioEditSparseAdvancing) {
                    return;
                }
                container.__bioEditSparseAdvancing = true;
                const nextUrl = buildEditSparseAdvanceUrl(options.url, editSparse, selectedId, item);
                const stepWrap = container.closest ? container.closest('.bio-edit-sparse-step') : null;
                removeEditSparseStepsAfter(stepWrap);
                fetchEditSparseUiDefinition(nextUrl, sparseRoot, {
                    url: nextUrl,
                    rootContainer: sparseRoot,
                    editSparseMountRoot: sparseRoot,
                    enableFlowChainAutoAdvance: options.enableFlowChainAutoAdvance
                }).finally(function () {
                    container.__bioEditSparseAdvancing = false;
                });
                setTimeout(scrollChatToBottom, 20);
                return;
            }

            try {
                const delta = {};
                delta[draftField] = selectedId;
                if (item) {
                    delta['_flow_item_' + draftField] = item;
                    const meta = item.meta && typeof item.meta === 'object' ? item.meta : {};
                    if (draftField === 'id_servicio' && meta.id_profesional_efector_servicio != null) {
                        const pesId = String(meta.id_profesional_efector_servicio).trim();
                        if (pesId !== '') {
                            delta.id_profesional_efector_servicio = pesId;
                        }
                    }
                }
                applyDraftDelta(delta);
                writeFlowState();
            } catch (e) { /* ignore */ }

            // Último paso del flow (flow_submit activo): solo merge local; el POST lo dispara el botón inline.
            if (isTerminalFlowStep) {
                clearFlowSubmitMissingHint(container);
                revealFlowSubmitInlineForContainer(container);
                return;
            }

            container.__bioListPickLocked = true;
            try {
                allPickButtons().forEach(b => { b.disabled = true; b.classList.add('disabled'); });
            } catch (e) { /* ignore */ }
            if (confirmBtn) markInlineButtonConfirmed(confirmBtn);
            setTimeout(function () {
                if (queryInput) {
                    queryInput.value = '';
                    handleInput();
                }
                handleSendQuery('');
            }, SPA_LIST_PICK_TO_SEND_MS);
        }

        let singleAutoTimer = null;
        if (container.__bioenlaceListPickHandler) {
            try {
                container.removeEventListener('click', container.__bioenlaceListPickHandler);
            } catch (e) { /* ignore */ }
            container.__bioenlaceListPickHandler = null;
        }
        function onListPickClick(ev) {
            const btn = ev.target && ev.target.closest ? ev.target.closest('button[data-embed-pick="1"]') : null;
            if (!btn || !container.contains(btn)) return;
            if (singleAutoTimer) {
                clearTimeout(singleAutoTimer);
                singleAutoTimer = null;
            }
            const editSparseCtx = options.editSparse && typeof options.editSparse === 'object'
                ? options.editSparse
                : null;
            if (editSparseCtx && container.__bioEditSparseAdvancing) {
                return;
            }
            if (isListPickLocked() && !editSparseCtx) return;
            const id = btn.getAttribute('data-embed-id') || '';
            if (!id) return;
            setSelected(btn, id);
            if (!effectiveRequiresConfirmation) confirmSelection();
        }
        container.__bioenlaceListPickHandler = onListPickClick;
        container.addEventListener('click', onListPickClick);
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function () {
                if (isListPickLocked()) return;
                confirmSelection();
            });
        }

        let searchDebounce = null;
        if (showPersonaSearch && searchInput && itemsMount) {
            searchInput.addEventListener('input', function () {
                if (isListPickLocked()) return;
                const q = String(searchInput.value || '').trim();
                if (searchDebounce) clearTimeout(searchDebounce);
                searchDebounce = setTimeout(function () {
                    searchDebounce = null;
                    if (isListPickLocked()) return;
                    if (q.length < 1) {
                        itemsMount.innerHTML = buildPickButtonsHtml([]);
                        if (emptyHintEl && emptyMsg) emptyHintEl.classList.remove('d-none');
                        return;
                    }
                    const url = setUrlQueryParam(baseListUrl, 'q', q);
                    itemsMount.innerHTML = '<div class="d-flex align-items-center gap-2 py-2 text-muted small"><div class="spinner-border spinner-border-sm"></div> Buscando…</div>';
                    if (emptyHintEl) emptyHintEl.classList.add('d-none');
                    fetch(url, {
                        method: 'GET',
                        headers: window.BioenlaceApiClient.mergeHeaders({
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        })
                    })
                        .then(function (r) {
                            if (!r.ok) throw new Error('HTTP ' + r.status);
                            return r.json();
                        })
                        .then(function (json) {
                            if (isListPickLocked()) return;
                            const nextItems = listBlockItemsFromUiDefinition(json, blockId);
                            rebuildItemsById(nextItems);
                            itemsMount.innerHTML = buildPickButtonsHtml(nextItems);
                            if (emptyHintEl) {
                                if (nextItems.length < 1 && emptyMsg) {
                                    emptyHintEl.textContent = emptyMsg;
                                    emptyHintEl.classList.remove('d-none');
                                } else {
                                    emptyHintEl.classList.add('d-none');
                                }
                            }
                        })
                        .catch(function () {
                            if (isListPickLocked()) return;
                            itemsMount.innerHTML = '<div class="alert alert-warning mb-0 py-2 small">No se pudo buscar. Intentá de nuevo.</div>';
                            if (emptyHintEl && emptyMsg) emptyHintEl.classList.remove('d-none');
                        });
                }, 320);
            });
        }

        const pickButtons = allPickButtons();
        if (options.enableFlowChainAutoAdvance === true
            && !options.isTerminalFlowStep
            && pickButtons.length === 1
            && !requiresConfirmation
            && draftField) {
            singleAutoTimer = setTimeout(function () {
                singleAutoTimer = null;
                if (isListPickLocked()) return;
                const only = allPickButtons()[0];
                if (!only || only.disabled) return;
                only.click();
            }, SPA_LIST_SINGLE_AUTO_INTRO_MS);
        }
    }

    function renderUiJsonFieldsBlock(block, container, options = {}) {
        const title = block.title ? String(block.title) : '';
        const fields = Array.isArray(block.fields) ? block.fields : [];
        let submitUrl = options.url || null;
        const submitApi = block.submit_api && typeof block.submit_api === 'object' ? block.submit_api : null;
        if (submitApi && submitApi.route) {
            submitUrl = resolveSpaFetchUrl(String(submitApi.route));
        }

        const grid = fieldsBlockUsesBootstrapGrid(fields);
        const hiddenFields = [];
        const visibleFields = [];
        fields.forEach(function (fd) {
            if (fd && String(fd.type || '') === 'hidden') {
                hiddenFields.push(fd);
            } else {
                visibleFields.push(fd);
            }
        });

        let html = '<div class="bio-ui-json-fields">';
        if (title) {
            html += '<div class="fw-semibold mb-2">' + escapeHtml(title) + '</div>';
        }
        html += '<form data-ui-json-form="1">';
        hiddenFields.forEach(function (fd) {
            html += renderFormField(fd, { useGrid: false });
        });
        if (grid) {
            html += '<div class="row g-3">';
        }
        visibleFields.forEach(function (fd) {
            html += renderFormField(fd, { useGrid: grid });
        });
        if (grid) {
            html += '</div>';
        }
        const hideSubmit = block.hide_submit === true || block.hide_submit === 1 || block.hide_submit === '1'
            || options.isTerminalFlowStep === true;
        if (!hideSubmit) {
            html += '<div class="d-flex justify-content-end pt-2">';
            html += '<button type="button" class="btn btn-success btn-sm" data-ui-json-submit="1">Confirmar</button>';
            html += '</div>';
        }
        html += '</form></div>';
        container.innerHTML = html;

        const form = container.querySelector('form[data-ui-json-form="1"]');
        const submitBtn = hideSubmit ? null : container.querySelector('button[data-ui-json-submit="1"]');
        if (!form) {
            return;
        }

        initCustomWidgetsInContainer(container, fields);
        attachAutocompleteHandlers(container);
        bindDynamicFormFieldControls(form);
        initSpaDateInputs(container);

        function syncTerminalFormDraftFromForm() {
            if (options.isTerminalFlowStep !== true) {
                return;
            }
            try {
                const delta = buildUiFormDraftDelta(form, fields);
                if (Object.keys(delta).length < 1) {
                    return;
                }
                applyDraftDelta(delta);
                writeFlowState();
                clearFlowSubmitMissingHint(container);
                revealFlowSubmitInlineForContainer(container);
            } catch (eSync) { /* ignore */ }
        }

        if (options.isTerminalFlowStep === true) {
            form.addEventListener('change', syncTerminalFormDraftFromForm);
            syncTerminalFormDraftFromForm();
        }

        if (hideSubmit || !submitBtn || !submitUrl) {
            return;
        }

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
                        try {
                            const formDelta = buildUiFormDraftDelta(form, fields);
                            if (Object.keys(formDelta).length > 0) {
                                applyDraftDelta(formDelta);
                            }
                        } catch (eFormDelta) { /* ignore */ }
                        if (json.data && typeof json.data === 'object' && !Array.isArray(json.data)) {
                            try {
                                const delta = Object.assign({}, json.data);
                                try {
                                    const rc = delta.razon_cancelacion;
                                    if (rc != null && String(rc).trim() !== '') {
                                        const sel = form.querySelector('[name="razon_cancelacion"]');
                                        let lbl = '';
                                        if (sel && sel.selectedOptions && sel.selectedOptions[0]) {
                                            lbl = String(sel.selectedOptions[0].textContent || '').trim();
                                        }
                                        if (!lbl) {
                                            lbl = etiquetaRazonCancelacionPaciente(rc);
                                        }
                                        delta['_flow_item_razon_cancelacion'] = {
                                            code: String(rc),
                                            label: lbl
                                        };
                                    }
                                } catch (eRc) { /* ignore */ }
                                applyDraftDelta(delta);
                                writeFlowState();
                            } catch (e) { /* ignore */ }
                        }
                        if (currentIntentId && shouldFinishFlowAfterFormUiSubmit(options, json)) {
                            finishActiveFlowAfterTerminalUiSubmit(container, json);
                        } else if (currentIntentId) {
                            setTimeout(() => { try { handleSendQuery(''); } catch (e) { /* ignore */ } }, 50);
                        }
                        return;
                    }
                    if (json && json.kind === 'ui_definition') {
                        // Error de validación o siguiente paso: re-render en el contenedor raíz.
                        const rerenderTarget = options.rootContainer || container;
                        renderDynamicUi(json, rerenderTarget, {
                            url: submitUrl,
                            rootContainer: rerenderTarget
                        });
                        return;
                    }
                    const errMsg = (json && typeof json.message === 'string' && json.message.trim() !== '')
                        ? json.message.trim()
                        : 'Error al guardar.';
                    container.innerHTML = '<div class="alert alert-danger mb-0">' + escapeHtml(errMsg) + '</div>';
                })
                .catch(() => {
                    container.innerHTML = '<div class="alert alert-danger mb-0">Error de red al guardar.</div>';
                });
        });
    }

    // Nota: el soporte legacy de wizard/steps fue eliminado (corte total). Ver `renderUiJsonBlocks()`.

    /**
     * Grid Bootstrap (12 cols) por campo. Requiere ancestro `.row` ({@see fieldsBlockUsesBootstrapGrid}).
     * `layout.col`: 1–12; `layout.breakpoint`: sm|md|lg|xl|xxl (default md → `col-md-*`).
     */
    function fieldBootstrapColClass(field, useGrid) {
        if (!useGrid) {
            return 'mb-3';
        }
        const layout = field.layout && typeof field.layout === 'object' ? field.layout : null;
        if (layout && typeof layout.col === 'number') {
            let n = Math.round(Number(layout.col));
            if (!Number.isFinite(n)) {
                return 'col-12 mb-3';
            }
            n = Math.min(12, Math.max(1, n));
            let bp = layout.breakpoint != null ? String(layout.breakpoint).trim().toLowerCase() : 'md';
            const allowed = { sm: 1, md: 1, lg: 1, xl: 1, xxl: 1 };
            if (!allowed[bp]) {
                bp = 'md';
            }
            return 'col-' + bp + '-' + n + ' mb-3';
        }
        return 'col-12 mb-3';
    }

    function fieldsBlockUsesBootstrapGrid(fields) {
        if (!Array.isArray(fields)) {
            return false;
        }
        return fields.some(function (fd) {
            if (!fd || typeof fd !== 'object') {
                return false;
            }
            if (String(fd.type || '') === 'hidden') {
                return false;
            }
            const layout = fd.layout;
            return layout && typeof layout === 'object' && typeof layout.col === 'number';
        });
    }

    /**
     * Renderizar campo de formulario
     * @param {object} opts - `{ useGrid?: boolean }` si el bloque fields usa `.row` (layout Bootstrap).
     */
    function renderCustomWidgetField(field, opts) {
        opts = opts || {};
        const useGrid = opts.useGrid === true;
        const outerClass = fieldBootstrapColClass(field, useGrid);
        const wid = field.widget_id || '';
        let html = '<div class="' + outerClass + ' bio-ui-custom-widget" data-bio-ui-widget="' + escapeHtml(wid) + '">';
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

    function renderFormField(field, opts) {
        opts = opts || {};
        const useGrid = opts.useGrid === true;
        if (field.type === 'hidden') {
            const v = field.value !== undefined && field.value !== null ? String(field.value) : '';
            return '<input type="hidden" name="' + escapeHtml(field.name) + '" value="' + escapeHtml(v) + '">';
        }
        if (field.type === 'custom_widget') {
            return renderCustomWidgetField(field, opts);
        }

        const outerClass = fieldBootstrapColClass(field, useGrid);
        let html = '<div class="' + outerClass + '">';
        const lblRaw = field.label != null ? String(field.label).trim() : '';
        if (lblRaw !== '' || field.required) {
            html += '<label class="form-label">' + escapeHtml(lblRaw);
            if (field.required) {
                html += ' <span class="text-danger">*</span>';
            }
            html += '</label>';
        }

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
            case 'chips':
                html += renderChipsField(field);
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
                            const franjas = [{ v: 'manana', l: 'Por la mañana' }, { v: 'tarde', l: 'Por la tarde' }];
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
                                        const idPes = s.id_profesional_efector_servicio;
                                        const hora = s.hora;
                                        if (!hora) return;
                                        if (!idPes || idPes <= 0) return;
                                        const value = 'pes:' + idPes + '|' + d.fecha + '|' + hora;
                                        const svcNombre = (s.servicio && s.servicio.nombre) ? String(s.servicio.nombre) : '';
                                        const labelCore = d.fecha + ' · ' + franjaLabel + ' · ' + hora;
                                        items.push({ value: value, label: svcNombre ? (labelCore + ' · ' + svcNombre) : labelCore });
                                    });
                                }
                                if (!selectedFranja) {
                                    add(d.manana, 'Por la mañana');
                                    add(d.tarde, 'Por la tarde');
                                } else if (selectedFranja === 'manana') {
                                    add(d.manana, 'Por la mañana');
                                } else {
                                    add(d.tarde, 'Por la tarde');
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

    function normalizeFieldOptions(field) {
        if (!field || field.options == null) {
            return [];
        }
        if (Array.isArray(field.options)) {
            return field.options;
        }
        return [];
    }

    /**
     * Renderizar campo radio (opciones seleccionables)
     */
    function renderRadioField(field) {
        let html = '<div class="d-flex flex-wrap gap-2">';
        normalizeFieldOptions(field).forEach(function (option) {
            const value = typeof option === 'object' ? option.value : option;
            const label = typeof option === 'object' ? option.label : option;
            const id = field.name + '_' + value;
            html += '<div class="form-check">';
            const checked = field.value !== undefined && field.value !== null && String(value) === String(field.value) ? ' checked' : '';
            html += '<input class="form-check-input" type="radio" name="' + escapeHtml(field.name) + '" id="' + id + '" value="' + escapeHtml(value) + '"' + (field.required ? ' required' : '') + checked + '>';
            html += '<label class="form-check-label" for="' + id + '">' + escapeHtml(label) + '</label>';
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    /**
     * Renderizar campo chips (selección única con botones tipo chip).
     */
    function renderChipsField(field) {
        const current = field.value !== undefined && field.value !== null ? String(field.value) : '';
        let html = '<input type="hidden" class="spa-ui-chip-value" name="' + escapeHtml(field.name) + '" value="' + escapeHtml(current) + '"';
        if (field.required) {
            html += ' required';
        }
        html += '>';
        html += '<div class="spa-ui-chips d-flex flex-wrap gap-2" role="radiogroup"';
        if (field.label) {
            html += ' aria-label="' + escapeHtml(String(field.label)) + '"';
        }
        html += '>';
        normalizeFieldOptions(field).forEach(function (option) {
            const value = typeof option === 'object' ? option.value : option;
            const label = typeof option === 'object' ? option.label : option;
            const active = current !== '' && String(value) === current ? ' is-active' : '';
            const pressed = active ? 'true' : 'false';
            html += '<button type="button" class="spa-ui-chip-btn' + active + '" data-field="' + escapeHtml(field.name) + '" data-value="' + escapeHtml(value) + '" aria-pressed="' + pressed + '">' + escapeHtml(label) + '</button>';
        });
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
        const v = field.value !== undefined && field.value !== null ? String(field.value) : '';
        let html = '<input type="text" class="form-control spa-ui-date-input" data-spa-date-input="1" inputmode="numeric" autocomplete="off" name="' + escapeHtml(field.name) + '" value="' + escapeHtml(v) + '" placeholder="dd/mm/aa"';
        if (field.required) {
            html += ' required';
        }
        if (field.min != null && String(field.min).trim() !== '') {
            html += ' data-min="' + escapeHtml(String(field.min)) + '"';
        }
        if (field.max != null && String(field.max).trim() !== '') {
            html += ' data-max="' + escapeHtml(String(field.max)) + '"';
        }
        html += '>';
        return html;
    }

    const SPA_FLATPICKR_LOCALE_ES = {
        weekdays: {
            shorthand: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
            longhand: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado']
        },
        months: {
            shorthand: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
            longhand: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
        },
        firstDayOfWeek: 1,
        rangeSeparator: ' a ',
        weekAbbreviation: 'Sem',
        scrollTitle: 'Desplazar para cambiar',
        toggleTitle: 'Click para abrir el calendario',
        time_24hr: true
    };

    function initSpaDateInputs(root) {
        const scope = root && root.querySelectorAll ? root : document;
        const inputs = scope.querySelectorAll
            ? scope.querySelectorAll('input[data-spa-date-input="1"]')
            : [];
        inputs.forEach(function (input) {
            if (input.dataset.spaDateBound === '1') {
                return;
            }
            input.dataset.spaDateBound = '1';

            if (typeof flatpickr === 'undefined') {
                input.addEventListener('click', function () {
                    if (typeof input.showPicker === 'function') {
                        try {
                            input.showPicker();
                        } catch (e) { /* ignore */ }
                    }
                });
                input.addEventListener('focus', function () {
                    if (typeof input.showPicker === 'function') {
                        try {
                            input.showPicker();
                        } catch (e) { /* ignore */ }
                    }
                });
                return;
            }

            if (input._flatpickr) {
                try {
                    input._flatpickr.destroy();
                } catch (e) { /* ignore */ }
            }

            const opts = {
                altInput: true,
                altFormat: 'd/m/y',
                dateFormat: 'Y-m-d',
                locale: SPA_FLATPICKR_LOCALE_ES,
                allowInput: false,
                clickOpens: true,
                disableMobile: true,
                onChange: function (_selectedDates, _dateStr, instance) {
                    try {
                        instance.input.dispatchEvent(new Event('change', { bubbles: true }));
                    } catch (e) { /* ignore */ }
                }
            };
            const minRaw = input.getAttribute('data-min');
            const maxRaw = input.getAttribute('data-max');
            if (minRaw && String(minRaw).trim() !== '') {
                opts.minDate = String(minRaw).trim();
            }
            if (maxRaw && String(maxRaw).trim() !== '') {
                opts.maxDate = String(maxRaw).trim();
            }
            flatpickr(input, opts);

            const fp = input._flatpickr;
            if (fp && fp.altInput) {
                fp.altInput.classList.add('form-control', 'spa-ui-date-input');
                fp.altInput.setAttribute('placeholder', 'dd/mm/aa');
                fp.altInput.addEventListener('click', function () {
                    if (fp.isOpen) {
                        return;
                    }
                    fp.open();
                });
            }
        });
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
        const ro = field.readonly === true || field.readonly === 1 || field.readonly === '1' || field.readonly === 'true';
        const rows = field.rows != null && String(field.rows).trim() !== '' ? Math.max(2, parseInt(String(field.rows), 10) || 4) : 4;
        let html = '<textarea class="form-control" name="' + escapeHtml(field.name) + '" rows="' + rows + '"'
            + (field.required ? ' required' : '')
            + (ro ? ' readonly' : '')
            + '>' + (field.value || '') + '</textarea>';
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

    function bindDynamicFormFieldControls(form) {
        if (!form) {
            return;
        }

        form.querySelectorAll('.quick-option-btn, .spa-ui-chip-btn').forEach(function (btn) {
            if (btn.dataset.fieldControlBound === '1') {
                return;
            }
            btn.dataset.fieldControlBound = '1';
            btn.addEventListener('click', function () {
                const fieldName = this.dataset.field;
                const value = this.dataset.value;
                if (!fieldName) {
                    return;
                }
                const nameSel = String(fieldName).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
                const input = form.querySelector('input.spa-ui-chip-value[name="' + nameSel + '"]')
                    || form.querySelector('input[name="' + nameSel + '"]:not([type="radio"])');
                if (!input) {
                    return;
                }
                input.value = value;
                form.querySelectorAll('.quick-option-btn[data-field="' + nameSel + '"], .spa-ui-chip-btn[data-field="' + nameSel + '"]').forEach(function (b) {
                    b.classList.remove('active', 'is-active');
                    b.setAttribute('aria-pressed', 'false');
                });
                this.classList.add(this.classList.contains('spa-ui-chip-btn') ? 'is-active' : 'active');
                this.setAttribute('aria-pressed', 'true');
                try {
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                } catch (e) { /* ignore */ }
            });
        });

        form.querySelectorAll('.number-increment').forEach(function (btn) {
            if (btn.dataset.fieldControlBound === '1') {
                return;
            }
            btn.dataset.fieldControlBound = '1';
            btn.addEventListener('click', function () {
                const fieldName = this.dataset.field;
                const nameSel = String(fieldName).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
                const input = form.querySelector('input[name="' + nameSel + '"]');
                if (input) {
                    const current = parseInt(input.value, 10) || 0;
                    const step = parseFloat(input.step) || 1;
                    input.value = current + step;
                }
            });
        });

        form.querySelectorAll('.number-decrement').forEach(function (btn) {
            if (btn.dataset.fieldControlBound === '1') {
                return;
            }
            btn.dataset.fieldControlBound = '1';
            btn.addEventListener('click', function () {
                const fieldName = this.dataset.field;
                const nameSel = String(fieldName).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
                const input = form.querySelector('input[name="' + nameSel + '"]');
                if (input) {
                    const current = parseInt(input.value, 10) || 0;
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
        
        bindDynamicFormFieldControls(form);
        initSpaDateInputs(form);
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
        // En modo conversacional no se ocultan mensajes previos.
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
        if (!queryInput) {
            return;
        }
        // Auto-resize textarea (1 línea inicial; crece hasta max-height en CSS)
        queryInput.style.height = 'auto';
        const maxPx = Math.min(
            (window.innerHeight || document.documentElement.clientHeight || 600) * 0.4,
            192
        );
        const next = Math.min(queryInput.scrollHeight, maxPx);
        queryInput.style.height = next + 'px';
        queryInput.style.overflowY = queryInput.scrollHeight > maxPx ? 'auto' : 'hidden';
        syncChatComposerLayout();
        toggleWelcomeActionsForComposer();
    }

    /**
     * Oculta las tarjetas de bienvenida mientras el usuario escribe (siguen en menú Atajos).
     */
    function toggleWelcomeActionsForComposer() {
        if (!welcomeActionsEl || !chatEmptyHint) {
            return;
        }
        if (chatEmptyHint.classList.contains('d-none')) {
            return;
        }
        const raw = queryInput ? String(queryInput.value || '') : '';
        const hasText = raw.trim().length > 0;
        if (hasText) {
            welcomeActionsEl.classList.add('d-none');
        } else {
            welcomeActionsEl.classList.remove('d-none');
        }
        syncShortcutsToolbarVisibility();
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

        const actionsHtml = (actions && actions.length > 0)
            ? renderActionCards(actions)
            : '<div class="col-12"><p class="text-muted mb-0">No se encontraron acciones específicas para esta consulta.</p></div>';

        const panel = appendAssistantResponsePanel({ explanationHtml: explanationHtml, actionsHtml: actionsHtml, variant: 'plain' });
        if (panel && panel.actionsEl) {
            attachCardListeners(panel.actionsEl);
        } else {
            attachCardListeners();
        }
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

        let extraHtml = '';
        if (suggestedQuery) {
            extraHtml = '<div class="mt-2"><small class="text-muted">Sugerencia: <button type="button" class="btn btn-link btn-sm p-0 align-baseline text-primary spa-suggested-query-btn">' + escapeHtml(suggestedQuery) + '</button></small></div>';
        }

        const actionsHtml = (actions && actions.length > 0)
            ? renderActionCards(actions)
            : '<div class="col-12"><p class="text-muted mb-0">No se encontraron acciones específicas para esta consulta.</p></div>';

        const panel = appendAssistantResponsePanel({ explanationHtml: explanationHtml + extraHtml, actionsHtml: actionsHtml, variant: 'info' });
        if (panel && panel.explanationEl && suggestedQuery) {
            const b = panel.explanationEl.querySelector('.spa-suggested-query-btn');
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
        if (panel && panel.actionsEl) {
            attachCardListeners(panel.actionsEl);
        } else {
            attachCardListeners();
        }
    }

    /**
     * Mostrar error
     */
    function showError(message) {
        appendAssistantResponsePanel({
            explanationHtml: escapeHtml(message),
            actionsHtml: '',
            variant: 'danger'
        });
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
        if (!expandContent) {
            return;
        }
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

    /** Inicia un flow si la URL trae ?spa_flow_intent=… (p. ej. desde alertas en otra pantalla). */
    function tryStartFlowFromQuery() {
        try {
            const params = new URLSearchParams(window.location.search);
            const intentId = params.get('spa_flow_intent');
            if (!intentId || !String(intentId).trim()) {
                return;
            }
            const intentName = params.get('spa_flow_intent_name') || '';
            startFlowFromShortcut(String(intentId).trim(), String(intentName));
            params.delete('spa_flow_intent');
            params.delete('spa_flow_intent_name');
            const qs = params.toString();
            const clean = window.location.pathname + (qs ? '?' + qs : '') + window.location.hash;
            window.history.replaceState({}, '', clean);
        } catch (e) {
            console.warn('[SPA] spa_flow_intent', e);
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

    function shortcutMetaFromAction(a) {
        if (!a || typeof a !== 'object') {
            return null;
        }
        const name = (a.name || a.display_name) ? String(a.name || a.display_name) : (a.action_id ? String(a.action_id) : '');
        const desc = a.description ? String(a.description) : '';
        const co = a.client_open && typeof a.client_open === 'object' ? a.client_open : null;
        const iid = co && String(co.kind || '') === 'intent' ? String(co.intent_id || '') : (a.action_id ? String(a.action_id) : '');
        if (!iid) {
            return null;
        }
        return { name: name, desc: desc, iid: iid };
    }

    function shortcutCardHtml(meta) {
        return '<button type="button" class="spa-shortcut-card" data-shortcut-intent-id="' + escapeHtml(meta.iid) + '" data-shortcut-name="' + escapeHtml(meta.name) + '">' +
            '<span class="spa-shortcut-card-body">' +
            '<span class="spa-shortcut-card-title">' + escapeHtml(meta.name) + '</span>' +
            (meta.desc ? '<span class="spa-shortcut-card-desc">' + escapeHtml(meta.desc) + '</span>' : '') +
            '</span>' +
            '<span class="spa-shortcut-card-chevron" aria-hidden="true">›</span>' +
            '</button>';
    }

    function welcomeShortcutButtonHtml(meta) {
        return shortcutCardHtml(meta);
    }

    /**
     * Panel inicial con atajos: ocultar menú «Atajos» del toolbar; al escribir o elegir atajo, mostrarlo.
     */
    function syncShortcutsToolbarVisibility() {
        if (!shortcutsToolbar) {
            return;
        }
        const panelVisible = isWelcomeShortcutsPanelVisible();
        shortcutsToolbar.classList.toggle('d-none', panelVisible);
    }

    function isWelcomeShortcutsPanelVisible() {
        if (!chatEmptyHint || chatEmptyHint.classList.contains('d-none')) {
            return false;
        }
        if (!welcomeActionsEl || welcomeActionsEl.classList.contains('d-none')) {
            return false;
        }
        const raw = queryInput ? String(queryInput.value || '') : '';
        if (raw.trim().length > 0) {
            return false;
        }
        return welcomeActionsEl.querySelector('.spa-shortcut-card, .spa-chat-welcome-categories') !== null;
    }

    function renderWelcomeShortcutsEmpty(msg) {
        if (!welcomeActionsEl) {
            return;
        }
        const t = (msg && String(msg).trim() !== '') ? String(msg).trim() : '';
        welcomeActionsEl.innerHTML = t !== ''
            ? '<div class="text-muted small">' + escapeHtml(t) + '</div>'
            : '<div class="text-muted small">No hay atajos disponibles.</div>';
        syncShortcutsToolbarVisibility();
    }

    function appendShortcutCardsGridHtml(actions) {
        let html = '';
        const items = Array.isArray(actions) ? actions : [];
        items.forEach(function (a) {
            const m = shortcutMetaFromAction(a);
            if (!m) {
                return;
            }
            html += welcomeShortcutButtonHtml(m);
        });
        return html;
    }

    function renderWelcomeShortcutsCategories(categories) {
        if (!welcomeActionsEl) {
            return;
        }
        const cats = Array.isArray(categories) ? categories : [];
        if (cats.length < 1) {
            renderWelcomeShortcutsEmpty();
            return;
        }
        let html = '<div class="spa-chat-welcome-categories d-flex flex-column gap-3">';
        cats.forEach(function (c) {
            const title = c && c.titulo ? String(c.titulo) : 'Atajos';
            const subgroups = c && Array.isArray(c.subgroups) ? c.subgroups : [];
            const actions = c && Array.isArray(c.actions) ? c.actions : [];
            if (subgroups.length > 0) {
                let hasAny = false;
                let sectionHtml = '<section class="spa-chat-welcome-category">';
                sectionHtml += '<h3 class="spa-chat-welcome-category-title h6 mb-2">' + escapeHtml(title) + '</h3>';
                subgroups.forEach(function (sg) {
                    const sgTitle = sg && sg.titulo ? String(sg.titulo) : '';
                    const sgActions = sg && Array.isArray(sg.actions) ? sg.actions : [];
                    if (!sgActions.length) {
                        return;
                    }
                    hasAny = true;
                    sectionHtml += '<div class="spa-chat-welcome-subgroup mb-2">';
                    if (sgTitle) {
                        sectionHtml += '<h4 class="spa-chat-welcome-subgroup-title h6 mb-2">' + escapeHtml(sgTitle) + '</h4>';
                    }
                    sectionHtml += '<div class="spa-shortcut-cards-grid">';
                    sectionHtml += appendShortcutCardsGridHtml(sgActions);
                    sectionHtml += '</div></div>';
                });
                sectionHtml += '</section>';
                if (hasAny) {
                    html += sectionHtml;
                }
                return;
            }
            if (!actions.length) {
                return;
            }
            html += '<section class="spa-chat-welcome-category">';
            html += '<h3 class="spa-chat-welcome-category-title h6 mb-2">' + escapeHtml(title) + '</h3>';
            html += '<div class="spa-shortcut-cards-grid">';
            html += appendShortcutCardsGridHtml(actions);
            html += '</div></section>';
        });
        html += '</div>';
        welcomeActionsEl.innerHTML = html;
        attachWelcomeShortcutListeners();
        toggleWelcomeActionsForComposer();
        syncShortcutsToolbarVisibility();
    }

    function renderWelcomeShortcutsFlat(actions) {
        if (!welcomeActionsEl) {
            return;
        }
        const items = Array.isArray(actions) ? actions : [];
        if (items.length < 1) {
            renderWelcomeShortcutsEmpty();
            return;
        }
        let html = '<div class="spa-chat-welcome-category">';
        html += '<h3 class="spa-chat-welcome-category-title h6 mb-2">Atajos</h3>';
        html += '<div class="spa-shortcut-cards-grid">';
        items.forEach(function (a) {
            const m = shortcutMetaFromAction(a);
            if (!m) {
                return;
            }
            html += welcomeShortcutButtonHtml(m);
        });
        html += '</div></div>';
        welcomeActionsEl.innerHTML = html;
        attachWelcomeShortcutListeners();
        toggleWelcomeActionsForComposer();
        syncShortcutsToolbarVisibility();
    }

    function attachWelcomeShortcutListeners() {
        if (!welcomeActionsEl) {
            return;
        }
        try {
            Array.from(welcomeActionsEl.querySelectorAll('button[data-shortcut-intent-id]')).forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const iid = this.getAttribute('data-shortcut-intent-id') || '';
                    const name = this.getAttribute('data-shortcut-name') || '';
                    startFlowFromShortcut(iid, name);
                });
            });
        } catch (e) { /* ignore */ }
    }

    /**
     * Cargar acciones comunes (menú Atajos + panel inicial del chat)
     */
    function loadCommonActions() {
        if (!shortcutsContent && !welcomeActionsEl) {
            return;
        }

        // API: ver nota de duplicación /api arriba.
        const url = window.location.origin + '/api/v1/acciones/comunes';
        const fetchPromise =
            window.BioenlaceApiClient && typeof window.BioenlaceApiClient.fetchJson === 'function'
                ? window.BioenlaceApiClient.fetchJson(url, {
                    method: 'GET',
                    headers: window.BioenlaceApiClient.mergeHeaders({
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    })
                })
                : fetch(url, {
                    method: 'GET',
                    headers: window.BioenlaceApiClient.mergeHeaders({
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }),
                    credentials: 'same-origin'
                }).then(response => {
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        if (handleApiUnauthorized(response.status, null)) {
                            return null;
                        }
                        return response.text().then(text => {
                            console.warn('El servidor devolvió HTML en lugar de JSON:', text.substring(0, 200));
                            throw new Error('Respuesta no válida del servidor');
                        });
                    }
                    return response.json().then(data => ({ response: response, json: data }));
                });

        fetchPromise
        .then(result => {
            if (!result) {
                return;
            }
            const response = result.response;
            const data = result.json != null ? result.json : result.data;
            if (handleApiUnauthorized(response.status, data)) {
                return;
            }
            if (!response.ok) {
                throw new Error((data && data.message) ? String(data.message) : ('HTTP ' + response.status));
            }
            if (data && data.success && Array.isArray(data.categories)) {
                renderShortcutsCategories(data.categories);
                renderWelcomeShortcutsCategories(data.categories);
                return;
            }
            if (data && data.success && Array.isArray(data.actions)) {
                // Fallback compat: si el backend solo devuelve actions planas.
                renderShortcutsFlat(data.actions);
                renderWelcomeShortcutsFlat(data.actions);
                return;
            }
            renderShortcutsEmpty();
            renderWelcomeShortcutsEmpty();
        })
        .catch(error => {
            console.warn('No se pudieron cargar las acciones comunes:', error);
            renderShortcutsEmpty('No se pudieron cargar los atajos.');
            renderWelcomeShortcutsEmpty('No se pudieron cargar los atajos.');
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
        html += '<div class="spa-shortcut-cards-grid">';
        items.forEach(function (a) {
            const m = shortcutMetaFromAction(a);
            if (!m) {
                return;
            }
            html += shortcutCardHtml(m);
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
        cats.forEach(function (c) {
            const title = c && c.titulo ? String(c.titulo) : 'Atajos';
            const subgroups = c && Array.isArray(c.subgroups) ? c.subgroups : [];
            const actions = c && Array.isArray(c.actions) ? c.actions : [];

            if (subgroups.length > 0) {
                let hasAny = false;
                let blockHtml = '<div>';
                blockHtml += '<h4 class="spa-chat-welcome-category-title h6 mb-2">' + escapeHtml(title) + '</h4>';
                subgroups.forEach(function (sg) {
                    const sgTitle = sg && sg.titulo ? String(sg.titulo) : '';
                    const sgActions = sg && Array.isArray(sg.actions) ? sg.actions : [];
                    if (!sgActions.length) {
                        return;
                    }
                    hasAny = true;
                    blockHtml += '<div class="spa-chat-welcome-subgroup mb-2">';
                    if (sgTitle) {
                        blockHtml += '<h5 class="spa-chat-welcome-subgroup-title h6 mb-2">' + escapeHtml(sgTitle) + '</h5>';
                    }
                    blockHtml += '<div class="spa-shortcut-cards-grid">';
                    sgActions.forEach(function (a) {
                        const m = shortcutMetaFromAction(a);
                        if (!m) {
                            return;
                        }
                        blockHtml += shortcutCardHtml(m);
                    });
                    blockHtml += '</div></div>';
                });
                blockHtml += '</div>';
                if (hasAny) {
                    html += blockHtml;
                }
                return;
            }

            if (!actions || actions.length < 1) {
                return;
            }

            html += '<div>';
            html += '<h4 class="spa-chat-welcome-category-title h6 mb-2">' + escapeHtml(title) + '</h4>';
            html += '<div class="spa-shortcut-cards-grid">';
            actions.forEach(function (a) {
                const m = shortcutMetaFromAction(a);
                if (!m) {
                    return;
                }
                html += shortcutCardHtml(m);
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
            Array.from(shortcutsContent.querySelectorAll('button[data-shortcut-intent-id]')).forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const iid = this.getAttribute('data-shortcut-intent-id') || '';
                    const name = this.getAttribute('data-shortcut-name') || '';
                    startFlowFromShortcut(iid, name);
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

    window.spaStartFlowFromShortcut = function (intentId, displayName) {
        startFlowFromShortcut(intentId, displayName);
    };

    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            init();
            attachCardListeners();
            tryOpenUiJsonFromQuery();
            tryStartFlowFromQuery();
        });
    } else {
        init();
        attachCardListeners();
        tryOpenUiJsonFromQuery();
        tryStartFlowFromQuery();
    }

})();