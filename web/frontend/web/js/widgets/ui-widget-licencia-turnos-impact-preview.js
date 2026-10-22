/**
 * Widget UI JSON: preview de turnos afectados por licencia / indisponibilidad.
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
        body.set('preview', '1');
        return body;
    }

    function formatFechaEs(iso) {
        var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(iso || '').trim());
        if (!m) {
            return String(iso || '');
        }
        return m[3] + '/' + m[2] + '/' + m[1].slice(-2);
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
        var total = parseInt(data.turnos_afectados_total, 10) || 0;
        var alert = createElement('div', 'alert ' + (total > 0 ? 'alert-warning' : 'alert-info') + ' small mb-0');
        alert.setAttribute('role', 'status');
        if (data.mensaje) {
            alert.appendChild(createElement('p', 'mb-2', String(data.mensaje)));
        }
        if (total <= 0) {
            panel.replaceChildren(alert);
            return;
        }
        var heading = createElement('p', 'mb-1');
        heading.appendChild(createElement('strong', '', 'Turnos pendientes (' + total + '):'));
        alert.appendChild(heading);
        var listElement = createElement('ul', 'mb-0 ps-3');
        var list = Array.isArray(data.turnos) ? data.turnos : [];
        list.forEach(function (t) {
            var line = formatFechaEs(t.fecha) + ' ' + String(t.hora || '');
            if (t.paciente) {
                line += ' — ' + String(t.paciente);
            }
            listElement.appendChild(createElement('li', '', line));
        });
        if (total > list.length) {
            listElement.appendChild(createElement('li', 'text-muted', '… y ' + (total - list.length) + ' más'));
        }
        alert.appendChild(listElement);
        alert.appendChild(createElement(
            'p',
            'mb-0 mt-2 text-warning',
            'Confirmá solo si aceptás que esos turnos pasen a resolución.'
        ));
        panel.replaceChildren(alert);
    }

    function fetchPreview(root, panel) {
        var form = findForm(root);
        if (!form) {
            showPanelMessage(panel, 'alert alert-warning small mb-0', 'No se encontró el formulario.');
            return;
        }
        showPanelMessage(panel, 'text-muted small', 'Calculando impacto en turnos…');
        fetch('/api/v1/profesional-efector-servicio/preview-impacto-licencia', {
            method: 'POST',
            headers: global.BioenlaceApiClient.mergeHeaders({
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }),
            credentials: 'same-origin',
            body: collectBody(form)
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
            .then(function (res) {
                if (res.ok && res.json && res.json.data) {
                    renderPreview(panel, res.json.data);
                } else {
                    var msg = (res.json && res.json.error && res.json.error.message)
                        ? res.json.error.message
                        : 'No se pudo obtener el impacto.';
                    showPanelMessage(panel, 'alert alert-danger small mb-0', msg);
                }
            })
            .catch(function () {
                showPanelMessage(panel, 'alert alert-danger small mb-0', 'Error de red al calcular el impacto.');
            });
    }

    global.BioenlaceUiWidgets.licencia_turnos_impact_preview = {
        init: function (root, fieldDef) {
            var mount = root.querySelector('[data-weekly-scheduler-mount]');
            if (mount && mount.parentNode) {
                mount.parentNode.removeChild(mount);
            }
            var panelElement = document.createElement('div');
            panelElement.setAttribute('data-licencia-impact-panel', '1');
            root.appendChild(panelElement);
            var panel = root.querySelector('[data-licencia-impact-panel="1"]');
            if (!panel) {
                return;
            }
            var autoFetch = fieldDef && (fieldDef.auto_fetch_on_init === true || fieldDef.auto_fetch_on_init === 1);
            if (autoFetch) {
                fetchPreview(root, panel);
            }
        }
    };
})(typeof window !== 'undefined' ? window : this);
