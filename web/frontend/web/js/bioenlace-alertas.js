/**
 * Bandeja de alertas in-app (GET/POST /api/v1/notificaciones/*).
 * Depende de window.spaConfig.baseUrl y BioenlaceApiClient.mergeHeaders.
 */
(function (window) {
    'use strict';

    var NS = (window.BioenlaceAlertas = window.BioenlaceAlertas || {});

    function apiBase() {
        var base = window.spaConfig && window.spaConfig.baseUrl
            ? String(window.spaConfig.baseUrl).replace(/\/$/, '')
            : '';
        return base;
    }

    function apiUrl(path) {
        var p = path.charAt(0) === '/' ? path : '/' + path;
        if (window.BioenlaceApiClient && typeof window.BioenlaceApiClient.normalizeApiV1Path === 'function') {
            p = window.BioenlaceApiClient.normalizeApiV1Path(p);
        } else if (p.indexOf('/api/v1/') !== 0) {
            p = '/api/v1/' + p.replace(/^\//, '');
        }
        if (p.indexOf('/api/') === 0) {
            return window.location.origin + p;
        }
        var b = apiBase();
        return b + p;
    }

    function headers() {
        return window.BioenlaceApiClient
            ? window.BioenlaceApiClient.mergeHeaders({ 'X-Requested-With': 'XMLHttpRequest' })
            : {};
    }

    function formatFecha(iso) {
        if (window.BioenlaceFecha && typeof window.BioenlaceFecha.formatNotificacion === 'function') {
            return window.BioenlaceFecha.formatNotificacion(iso);
        }
        if (!iso) return '';
        var d = new Date(iso);
        if (isNaN(d.getTime())) return String(iso);
        return d.toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' });
    }

    function intentDesdeTipo(tipo) {
        var t = String(tipo || '').trim();
        if (t === 'TURNO_REQUIERE_REUBICACION' || t === 'TURNO_CANCELADO_EFECTOR') {
            return { id: 'turnos.reubicar-como-paciente-flow', name: 'Reubicar turno' };
        }
        if (t === 'TURNO_AUTO_REUBICADO_RESOLUCION') {
            return { id: 'turnos.reprogramar-como-paciente-flow', name: 'Cambiar turno' };
        }
        return null;
    }

    NS.fetchList = function (opts) {
        opts = opts || {};
        var solo = opts.soloNoLeidas ? '1' : '0';
        var limit = opts.limit != null ? opts.limit : 30;
        var url = apiUrl('/notificaciones/listar')
            + '?solo_no_leidas=' + encodeURIComponent(solo)
            + '&limit=' + encodeURIComponent(String(limit));
        return fetch(url, { method: 'GET', headers: headers(), credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    };

    NS.marcarLeida = function (id) {
        var body = new URLSearchParams();
        if (id != null && id !== '') {
            body.set('id', String(id));
        }
        if (window.spaConfig && window.spaConfig.csrfToken) {
            body.set('_csrf', String(window.spaConfig.csrfToken));
        }
        return fetch(apiUrl('/notificaciones/marcar-leida'), {
            method: 'POST',
            headers: headers(),
            credentials: 'same-origin',
            body: body,
        }).then(function (r) { return r.json(); });
    };

    function tpl(id) {
        var t = document.getElementById(id);
        if (!t || !t.content) return null;
        return document.importNode(t.content, true);
    }

    function clear(el) {
        if (el) el.replaceChildren();
    }

    function mountItems(listEl, items) {
        clear(listEl);
        if (!items || !items.length) {
            var empty = tpl('tpl-alertas-empty');
            if (empty) {
                var msg = empty.querySelector('[data-field="message"]');
                if (msg) msg.textContent = 'No hay alertas.';
                listEl.appendChild(empty);
            }
            return;
        }
        var listFrag = tpl('tpl-alertas-list');
        if (!listFrag) return;
        var slot = listFrag.querySelector('[data-slot="items"]');
        items.forEach(function (it) {
            var itemFrag = tpl('tpl-alerta-item');
            if (!itemFrag) return;
            var li = itemFrag.querySelector('li') || itemFrag.firstElementChild;
            var leida = it.leida_at != null && String(it.leida_at).trim() !== '';
            if (!leida) li.classList.add('bioenlace-alerta-item--nueva');
            var tipo = it.tipo != null ? String(it.tipo) : '';
            var intent = intentDesdeTipo(tipo);
            if (intent) {
                li.setAttribute('data-intent-id', intent.id);
                li.setAttribute('data-intent-name', intent.name);
            } else {
                li.removeAttribute('data-intent-id');
                li.removeAttribute('data-intent-name');
            }
            if (it.id != null) {
                li.setAttribute('data-notif-id', String(it.id));
            } else {
                li.removeAttribute('data-notif-id');
            }
            var tit = itemFrag.querySelector('[data-field="titulo"]');
            if (tit) tit.textContent = it.titulo || 'Aviso';
            var cuerpo = itemFrag.querySelector('[data-field="cuerpo"]');
            if (cuerpo) cuerpo.textContent = it.cuerpo || '';
            var fecha = itemFrag.querySelector('[data-field="fecha"]');
            if (fecha) fecha.textContent = formatFecha(it.created_at);
            slot.appendChild(itemFrag);
        });
        listEl.appendChild(listFrag);
    }

    function updateBadge(count) {
        var btn = document.getElementById('spa-alertas-toggle-btn');
        if (!btn) return;
        var badge = btn.querySelector('.spa-alertas-badge');
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'spa-alertas-badge badge rounded-pill bg-danger';
            btn.appendChild(badge);
        }
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.classList.remove('d-none');
        } else {
            badge.textContent = '';
            badge.classList.add('d-none');
        }
    }

    NS.refreshBadge = function () {
        return NS.fetchList({ soloNoLeidas: true, limit: 1 }).then(function (res) {
            if (res && res.success && res.data) {
                updateBadge(res.data.no_leidas || 0);
            }
            return res;
        }).catch(function () { return null; });
    };

    function openPanel() {
        var panel = document.getElementById('spa-alertas-panel');
        if (!panel) return;
        panel.classList.add('spa-alertas-panel--open');
        panel.setAttribute('aria-hidden', 'false');
        var list = panel.querySelector('.spa-alertas-panel-body');
        if (list) {
            clear(list);
            var loading = tpl('tpl-alertas-loading');
            if (loading) list.appendChild(loading);
        }
        NS.fetchList({ limit: 50 }).then(function (res) {
            if (!list) return;
            if (res && res.success && res.data) {
                mountItems(list, res.data.items || []);
                updateBadge(res.data.no_leidas || 0);
            } else {
                clear(list);
                var err = tpl('tpl-alertas-empty');
                if (err) {
                    var msg = err.querySelector('[data-field="message"]');
                    if (msg) {
                        msg.textContent = 'No se pudieron cargar las alertas.';
                        msg.classList.remove('text-muted');
                        msg.classList.add('text-danger');
                    }
                    list.appendChild(err);
                }
            }
        });
    }

    function closePanel() {
        var panel = document.getElementById('spa-alertas-panel');
        if (!panel) return;
        panel.classList.remove('spa-alertas-panel--open');
        panel.setAttribute('aria-hidden', 'true');
    }

    NS.init = function () {
        var btn = document.getElementById('spa-alertas-toggle-btn');
        var closeBtn = document.getElementById('spa-alertas-close-btn');
        var panel = document.getElementById('spa-alertas-panel');
        if (!btn || !panel) return;

        btn.addEventListener('click', function () {
            if (panel.classList.contains('spa-alertas-panel--open')) {
                closePanel();
            } else {
                openPanel();
            }
        });
        if (closeBtn) {
            closeBtn.addEventListener('click', closePanel);
        }

        panel.addEventListener('click', function (e) {
            var item = e.target.closest('.bioenlace-alerta-item');
            if (!item) return;
            var notifId = item.getAttribute('data-notif-id');
            var intentId = item.getAttribute('data-intent-id');
            var intentName = item.getAttribute('data-intent-name') || '';
            if (notifId) {
                NS.marcarLeida(notifId).then(function () {
                    NS.refreshBadge();
                });
            }
            if (intentId) {
                closePanel();
                if (typeof window.spaStartFlowFromShortcut === 'function') {
                    window.spaStartFlowFromShortcut(intentId, intentName);
                } else {
                    var base = window.spaConfig && window.spaConfig.asistenteUrl
                        ? String(window.spaConfig.asistenteUrl)
                        : '/site/asistente';
                    var sep = base.indexOf('?') >= 0 ? '&' : '?';
                    var target = base + sep + 'spa_flow_intent=' + encodeURIComponent(intentId);
                    if (intentName) {
                        target += '&spa_flow_intent_name=' + encodeURIComponent(intentName);
                    }
                    window.location.href = target;
                }
            }
        });

        NS.refreshBadge();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', NS.init);
    } else {
        NS.init();
    }
})(window);
