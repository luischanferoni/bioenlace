/**
 * Widget UI JSON: preview de impacto al configurar agenda (intervalo versionado).
 */
(function (global) {
    'use strict';

    global.BioenlaceUiWidgets = global.BioenlaceUiWidgets || {};

    function findForm(root) {
        var el = root;
        while (el && el !== document.body) {
            if (el.querySelector && el.querySelector('form[data-ui-json-form="1"]')) {
                return el.querySelector('form[data-ui-json-form="1"]');
            }
            el = el.parentElement;
        }
        return document.querySelector('form[data-ui-json-form="1"]');
    }

    function collectBody(form) {
        var body = new URLSearchParams();
        try {
            var fd = new FormData(form);
            fd.forEach(function (v, k) {
                if (v != null && String(v) !== '') {
                    body.set(k, String(v));
                }
            });
        } catch (e) { /* ignore */ }
        try {
            if (global.spaConfig && global.spaConfig.csrfToken) {
                body.set('_csrf', String(global.spaConfig.csrfToken));
            }
        } catch (e2) { /* ignore */ }
        return body;
    }

    function createElement(tag, className, text) {
        var el = document.createElement(tag);
        if (className) {
            el.className = className;
        }
        if (text !== undefined) {
            el.textContent = text;
        }
        return el;
    }

    function showPanelMessage(panel, className, message) {
        panel.replaceChildren(createElement('div', className, message));
    }

    function renderPreview(panel, data) {
        if (!data || typeof data !== 'object') {
            showPanelMessage(panel, 'text-muted small', 'Sin datos de preview.');
            return;
        }
        var alert = createElement('div', 'alert alert-info small mb-0');
        alert.setAttribute('role', 'status');
        alert.appendChild(createElement('strong', '', 'Vista previa'));
        alert.appendChild(document.createElement('br'));
        if (data.mensaje) {
            alert.appendChild(document.createTextNode(String(data.mensaje)));
        }
        var summaryList = createElement('ul', 'mb-0 mt-2 ps-3');
        summaryList.appendChild(createElement(
            'li',
            '',
            'Intervalo: ' + String(data.intervalo_actual) + ' → ' + String(data.intervalo_nuevo) + ' min'
        ));
        summaryList.appendChild(createElement(
            'li',
            '',
            'Turnos futuros: ' + String(data.turnos_alineados || 0) + ' alineados, ' +
                String(data.turnos_en_conflicto || 0) + ' en conflicto'
        ));
        summaryList.appendChild(createElement(
            'li',
            '',
            'Cambios de intervalo este año: ' + String(data.cambios_intervalo_este_anio || 0) +
                ' / ' + String(data.cambios_intervalo_max_por_anio || 2)
        ));
        alert.appendChild(summaryList);
        if (Array.isArray(data.conflictos) && data.conflictos.length > 0) {
            var conflictHeading = createElement('p', 'mb-1 mt-2');
            conflictHeading.appendChild(createElement('strong', '', 'Conflictos (muestra):'));
            alert.appendChild(conflictHeading);
            var conflictsList = createElement('ul', 'mb-0 ps-3');
            data.conflictos.slice(0, 5).forEach(function (c) {
                conflictsList.appendChild(createElement(
                    'li',
                    '',
                    String(c.fecha || '') + ' ' + String(c.hora_actual || '') +
                        ' → antes ' + String(c.opcion_antes || '—') +
                        ' / después ' + String(c.opcion_despues || '—')
                ));
            });
            if (data.conflictos.length > 5) {
                conflictsList.appendChild(createElement(
                    'li',
                    'text-muted',
                    '… y ' + (data.conflictos.length - 5) + ' más'
                ));
            }
            alert.appendChild(conflictsList);
        }
        if (data.requiere_confirmacion) {
            alert.appendChild(createElement(
                'p',
                'mb-0 mt-2 text-warning',
                'Confirmá el guardado solo si aceptás el impacto en turnos futuros.'
            ));
        }
        panel.replaceChildren(alert);
    }

    function fetchPreview(root, panel, btn) {
        var form = findForm(root);
        if (!form) {
            showPanelMessage(panel, 'alert alert-warning small mb-0', 'No se encontró el formulario.');
            return;
        }
        if (btn) {
            btn.disabled = true;
        }
        showPanelMessage(panel, 'text-muted small', 'Calculando impacto…');
        // Misma ruta RBAC que el guardado (`configurar-agenda` + preview=1).
        var body = collectBody(form);
        body.set('preview', '1');
        fetch('/api/v1/profesional-agenda/configurar-agenda', {
            method: 'POST',
            headers: global.BioenlaceApiClient.mergeHeaders({
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }),
            credentials: 'same-origin',
            body: body
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
            .then(function (res) {
                if (res.ok && res.json && res.json.data) {
                    renderPreview(panel, res.json.data);
                } else {
                    var msg = (res.json && res.json.message) ? res.json.message : 'No se pudo obtener el preview.';
                    showPanelMessage(panel, 'alert alert-danger small mb-0', msg);
                }
            })
            .catch(function () {
                showPanelMessage(panel, 'alert alert-danger small mb-0', 'Error de red al calcular el impacto.');
            })
            .finally(function () {
                if (btn) {
                    btn.disabled = false;
                }
            });
    }

    global.BioenlaceUiWidgets.agenda_config_preview = {
        init: function (root, fieldDef) {
            var mount = root.querySelector('[data-weekly-scheduler-mount]');
            if (mount && mount.parentNode) {
                mount.parentNode.removeChild(mount);
            }
            var autoFetch = fieldDef && (fieldDef.auto_fetch_on_init === true || fieldDef.auto_fetch_on_init === 1);
            if (!autoFetch) {
                var btn = createElement('button', 'btn btn-outline-primary btn-sm mb-2', 'Ver impacto antes de guardar');
                btn.setAttribute('type', 'button');
                btn.setAttribute('data-agenda-preview-btn', '1');
                root.appendChild(btn);
            }
            var panelElement = document.createElement('div');
            panelElement.setAttribute('data-agenda-preview-panel', '1');
            root.appendChild(panelElement);
            var panel = root.querySelector('[data-agenda-preview-panel="1"]');
            var btn = root.querySelector('[data-agenda-preview-btn="1"]');
            if (!panel) {
                return;
            }
            if (btn) {
                btn.addEventListener('click', function () {
                    fetchPreview(root, panel, btn);
                });
            }
            if (autoFetch) {
                fetchPreview(root, panel, null);
            }
        }
    };
})(typeof window !== 'undefined' ? window : this);
