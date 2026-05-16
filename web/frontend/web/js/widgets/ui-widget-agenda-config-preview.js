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

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderPreview(panel, data) {
        if (!data || typeof data !== 'object') {
            panel.innerHTML = '<div class="text-muted small">Sin datos de preview.</div>';
            return;
        }
        var html = '<div class="alert alert-info small mb-0" role="status">';
        html += '<strong>Vista previa</strong><br>';
        if (data.mensaje) {
            html += escapeHtml(String(data.mensaje));
        }
        html += '<ul class="mb-0 mt-2 ps-3">';
        html += '<li>Intervalo: ' + escapeHtml(String(data.intervalo_actual)) + ' → ' + escapeHtml(String(data.intervalo_nuevo)) + ' min</li>';
        html += '<li>Turnos futuros: ' + escapeHtml(String(data.turnos_alineados || 0)) + ' alineados, ';
        html += escapeHtml(String(data.turnos_en_conflicto || 0)) + ' en conflicto</li>';
        html += '<li>Cambios de intervalo este año: ' + escapeHtml(String(data.cambios_intervalo_este_anio || 0));
        html += ' / ' + escapeHtml(String(data.cambios_intervalo_max_por_anio || 2)) + '</li>';
        html += '</ul>';
        if (Array.isArray(data.conflictos) && data.conflictos.length > 0) {
            html += '<p class="mb-1 mt-2"><strong>Conflictos (muestra):</strong></p><ul class="mb-0 ps-3">';
            data.conflictos.slice(0, 5).forEach(function (c) {
                html += '<li>' + escapeHtml(String(c.fecha || '')) + ' ' + escapeHtml(String(c.hora_actual || ''));
                html += ' → antes ' + escapeHtml(String(c.opcion_antes || '—'));
                html += ' / después ' + escapeHtml(String(c.opcion_despues || '—')) + '</li>';
            });
            if (data.conflictos.length > 5) {
                html += '<li class="text-muted">… y ' + (data.conflictos.length - 5) + ' más</li>';
            }
            html += '</ul>';
        }
        if (data.requiere_confirmacion) {
            html += '<p class="mb-0 mt-2 text-warning">Debe confirmar el cambio (campo «Confirmo el cambio…» = Sí) antes de guardar.</p>';
        }
        html += '</div>';
        panel.innerHTML = html;
    }

    global.BioenlaceUiWidgets.agenda_config_preview = {
        init: function (root) {
            var mount = root.querySelector('[data-weekly-scheduler-mount]');
            if (mount && mount.parentNode) {
                mount.parentNode.removeChild(mount);
            }
            root.insertAdjacentHTML(
                'beforeend',
                '<button type="button" class="btn btn-outline-primary btn-sm mb-2" data-agenda-preview-btn="1">Ver impacto antes de guardar</button>' +
                '<div data-agenda-preview-panel="1"></div>'
            );
            var panel = root.querySelector('[data-agenda-preview-panel="1"]');
            var btn = root.querySelector('[data-agenda-preview-btn="1"]');
            if (!panel || !btn) {
                return;
            }
            btn.addEventListener('click', function () {
                var form = findForm(root);
                if (!form) {
                    panel.innerHTML = '<div class="alert alert-warning small mb-0">No se encontró el formulario.</div>';
                    return;
                }
                btn.disabled = true;
                panel.innerHTML = '<div class="text-muted small">Calculando impacto…</div>';
                fetch('/api/v1/profesional-agenda/preview-configurar-agenda', {
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
                            var msg = (res.json && res.json.message) ? res.json.message : 'No se pudo obtener el preview.';
                            panel.innerHTML = '<div class="alert alert-danger small mb-0">' + escapeHtml(msg) + '</div>';
                        }
                    })
                    .catch(function () {
                        panel.innerHTML = '<div class="alert alert-danger small mb-0">Error de red al calcular el impacto.</div>';
                    })
                    .finally(function () {
                        btn.disabled = false;
                    });
            });
        }
    };
})(typeof window !== 'undefined' ? window : this);
