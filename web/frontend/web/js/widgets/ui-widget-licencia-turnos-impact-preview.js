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

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatFechaEs(iso) {
        var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(iso || '').trim());
        if (!m) {
            return String(iso || '');
        }
        return m[3] + '/' + m[2] + '/' + m[1].slice(-2);
    }

    function renderPreview(panel, data) {
        if (!data || typeof data !== 'object') {
            panel.innerHTML = '<div class="text-muted small">Sin datos de preview.</div>';
            return;
        }
        var total = parseInt(data.turnos_afectados_total, 10) || 0;
        var html = '<div class="alert ' + (total > 0 ? 'alert-warning' : 'alert-info') + ' small mb-0" role="status">';
        if (data.mensaje) {
            html += '<p class="mb-2">' + escapeHtml(String(data.mensaje)) + '</p>';
        }
        if (total <= 0) {
            html += '</div>';
            panel.innerHTML = html;
            return;
        }
        html += '<p class="mb-1"><strong>Turnos pendientes (' + total + '):</strong></p><ul class="mb-0 ps-3">';
        var list = Array.isArray(data.turnos) ? data.turnos : [];
        list.forEach(function (t) {
            var line = formatFechaEs(t.fecha) + ' ' + escapeHtml(String(t.hora || ''));
            if (t.paciente) {
                line += ' — ' + escapeHtml(String(t.paciente));
            }
            html += '<li>' + line + '</li>';
        });
        if (total > list.length) {
            html += '<li class="text-muted">… y ' + (total - list.length) + ' más</li>';
        }
        html += '</ul>';
        html += '<p class="mb-0 mt-2 text-warning">Confirmá solo si aceptás que esos turnos pasen a resolución.</p>';
        html += '</div>';
        panel.innerHTML = html;
    }

    function fetchPreview(root, panel) {
        var form = findForm(root);
        if (!form) {
            panel.innerHTML = '<div class="alert alert-warning small mb-0">No se encontró el formulario.</div>';
            return;
        }
        panel.innerHTML = '<div class="text-muted small">Calculando impacto en turnos…</div>';
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
                    var msg = (res.json && res.json.message) ? res.json.message : 'No se pudo obtener el impacto.';
                    panel.innerHTML = '<div class="alert alert-danger small mb-0">' + escapeHtml(msg) + '</div>';
                }
            })
            .catch(function () {
                panel.innerHTML = '<div class="alert alert-danger small mb-0">Error de red al calcular el impacto.</div>';
            });
    }

    global.BioenlaceUiWidgets.licencia_turnos_impact_preview = {
        init: function (root, fieldDef) {
            var mount = root.querySelector('[data-weekly-scheduler-mount]');
            if (mount && mount.parentNode) {
                mount.parentNode.removeChild(mount);
            }
            root.insertAdjacentHTML('beforeend', '<div data-licencia-impact-panel="1"></div>');
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
