/**
 * Listado de pacientes (inicio web): turnos / internados / tablero guardia / cirugías.
 * Datos vía API v1 según encounter en sesión.
 */
(function () {
  'use strict';

  var TABLERO_POLL_MS = 30000;

  function importTemplate(templateId) {
    var tpl = document.getElementById(templateId);
    if (!tpl || !tpl.content) return null;
    return document.importNode(tpl.content, true);
  }

  function clearNode(el) {
    while (el && el.firstChild) el.removeChild(el.firstChild);
  }

  function showError(errorEl, msg) {
    if (!errorEl) return;
    errorEl.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>' + String(msg || 'Error');
    errorEl.classList.remove('d-none');
  }

  function init() {
    var root = document.getElementById('pacientes-listado-container');
    if (!root) return;

    var container = document.getElementById('pacientes-listado-content');
    var loading = document.getElementById('pacientes-listado-loading');
    var errorEl = document.getElementById('pacientes-listado-error');
    if (!container || !loading || !errorEl) return;

    var fecha = root.getAttribute('data-fecha') || '';
    var encounter = root.getAttribute('data-encounter') || '';
    var esGuardia = root.getAttribute('data-es-guardia') === '1';
    var urlHistoriaBase = root.getAttribute('data-url-historia') || '';
    var urlInternacionView = root.getAttribute('data-url-internacion-view') || '';

    var msgEmptyTurnos = root.getAttribute('data-msg-empty-turnos') || 'Sin resultados.';
    var msgEmptyInternados = root.getAttribute('data-msg-empty-internados') || 'Sin resultados.';
    var msgEmptyGuardias = root.getAttribute('data-msg-empty-guardias') || 'Sin resultados.';
    var msgEmptyCirugias = root.getAttribute('data-msg-empty-cirugias') || 'Sin resultados.';

    var pollTimer = null;

    function setLoading(isLoading) {
      loading.classList.toggle('d-none', !isLoading);
      container.classList.toggle('d-none', isLoading);
    }

    function showListadoEmpty(message) {
      clearNode(container);
      var frag = importTemplate('tpl-pacientes-alert-empty');
      if (!frag) return;
      var msgEl = frag.querySelector('[data-field="message"]');
      if (msgEl) msgEl.textContent = message;
      container.appendChild(frag);
    }

    function historiaConContexto(personaId, ctx) {
      if (!personaId || !urlHistoriaBase) return null;
      var base = urlHistoriaBase;
      var q = 'id=' + encodeURIComponent(personaId);
      if (ctx && typeof ctx === 'object') {
        if (ctx.parent) q += '&parent=' + encodeURIComponent(ctx.parent);
        if (ctx.parent_id != null) q += '&parent_id=' + encodeURIComponent(ctx.parent_id);
      }
      return base + (base.indexOf('?') >= 0 ? '&' : '?') + q;
    }

    function fillTurnoCard(colEl, t) {
      colEl.querySelector('[data-field="nombre"]').textContent =
        (t.paciente && t.paciente.nombre_completo) ? t.paciente.nombre_completo : 'Sin paciente';
      colEl.querySelector('[data-field="hora"]').textContent = t.hora || '';
      colEl.querySelector('[data-field="servicio"]').textContent = t.servicio || 'Sin servicio';

      var badge = colEl.querySelector('[data-field="estado-badge"]');
      if (badge) {
        var estadoClass = (t.estado === 'PENDIENTE') ? 'warning' : 'secondary';
        badge.className = 'badge bg-' + estadoClass;
        badge.textContent = t.estado_label || t.estado || '';
      }

      var obsSlot = colEl.querySelector('[data-slot="observaciones"]');
      if (t.observaciones && obsSlot) {
        obsSlot.classList.remove('d-none');
        var obsText = obsSlot.querySelector('[data-field="observaciones"]');
        if (obsText) obsText.textContent = t.observaciones;
      }

      var idPersona = t.id_persona || (t.paciente ? t.paciente.id : null);
      var urlHistoria = historiaConContexto(idPersona, { parent: 'TURNO', parent_id: t.id });
      var a = colEl.querySelector('[data-role="link-historia"]');
      if (a && urlHistoria) a.href = urlHistoria;
    }

    function renderTurnos(data) {
      if (!data || !data.length) {
        showListadoEmpty(msgEmptyTurnos);
        return;
      }
      clearNode(container);
      var wrapFrag = importTemplate('tpl-pacientes-turnos-wrap');
      if (!wrapFrag) return;
      var row = wrapFrag.querySelector('[data-role="turnos-grid"]');
      container.appendChild(wrapFrag);

      data.forEach(function (t) {
        var itemFrag = importTemplate('tpl-paciente-turno');
        if (!itemFrag) return;
        var col = itemFrag.firstElementChild;
        if (!col) return;
        fillTurnoCard(col, t);
        row.appendChild(col);
      });
    }

    function fillInternadoRow(rowEl, i) {
      rowEl.querySelector('[data-field="nombre"]').textContent = i.nombre || '';
      rowEl.querySelector('[data-field="piso"]').textContent = i.piso || '';
      rowEl.querySelector('[data-field="sala"]').textContent = i.sala || '';
      rowEl.querySelector('[data-field="cama"]').textContent = i.cama || '';

      var urlView = urlInternacionView ? (urlInternacionView + '?id=' + encodeURIComponent(String(i.id))) : null;
      var aAtender = rowEl.querySelector('[data-role="link-atender"]');
      if (aAtender && urlView) aAtender.href = urlView;

      var urlHistoria = historiaConContexto(i.id_persona, { parent: 'INTERNACION', parent_id: i.id });
      var aHist = rowEl.querySelector('[data-role="link-historia"]');
      if (aHist && urlHistoria) {
        aHist.href = urlHistoria;
        aHist.classList.remove('d-none');
      }
    }

    function renderInternados(data) {
      if (!data || !data.length) {
        showListadoEmpty(msgEmptyInternados);
        return;
      }
      clearNode(container);
      var wrapFrag = importTemplate('tpl-pacientes-internados-wrap');
      if (!wrapFrag) return;
      var rowsSlot = wrapFrag.querySelector('[data-slot="internados-rows"]');
      container.appendChild(wrapFrag);

      data.forEach(function (i) {
        var itemFrag = importTemplate('tpl-paciente-internado-row');
        if (!itemFrag) return;
        var row = itemFrag.firstElementChild;
        if (!row) return;
        fillInternadoRow(row, i);
        rowsSlot.appendChild(row);
      });
    }

    function nivelRowClass(g) {
      var level = g.prioridad_triage;
      if (level == null || level === '') {
        return 'guardia-tablero-row--sin-triage';
      }
      return 'guardia-tablero-row--nivel-' + String(level);
    }

    function fillGuardiaTableroRow(rowEl, g) {
      rowEl.className = 'd-flex align-items-center justify-content-between p-3 mb-0 border-bottom guardia-tablero-row ' + nivelRowClass(g);

      var paciente = g.paciente || {};
      var nombre = paciente.nombre_completo || g.nombre_completo || '';
      rowEl.querySelector('[data-field="nombre"]').textContent = nombre;
      rowEl.querySelector('[data-field="documento-line"]').textContent =
        (paciente.tipo_documento ? (paciente.tipo_documento + ': ') : (g.tipo_documento ? g.tipo_documento + ': ' : '')) +
        (paciente.documento || g.documento || '');

      var triage = g.triage || {};
      var motivoLine = rowEl.querySelector('[data-field="motivo-line"]');
      if (motivoLine) {
        motivoLine.textContent = triage.reason_text || '';
        motivoLine.classList.toggle('d-none', !triage.reason_text);
      }

      var nivelBadge = rowEl.querySelector('[data-field="nivel-badge"]');
      if (nivelBadge) {
        if (g.prioridad_triage != null) {
          nivelBadge.textContent = String(g.prioridad_triage);
          nivelBadge.style.backgroundColor = triage.level_color || '#6c757d';
          nivelBadge.classList.remove('bg-secondary');
        } else {
          nivelBadge.textContent = '?';
          nivelBadge.className = 'badge bg-secondary guardia-tablero-badge-nivel';
        }
      }

      var circuitoBadge = rowEl.querySelector('[data-field="circuito-badge"]');
      if (circuitoBadge) {
        circuitoBadge.textContent = g.circuito_estado_label || g.circuito_estado || '';
      }

      var esperaLine = rowEl.querySelector('[data-field="espera-line"]');
      if (esperaLine) {
        var min = g.minutos_espera != null ? g.minutos_espera : 0;
        esperaLine.textContent = min + ' min en espera';
      }

      var profLine = rowEl.querySelector('[data-field="profesional-line"]');
      if (profLine && g.profesional_asignado) {
        profLine.textContent = 'Asignado: ' + g.profesional_asignado;
        profLine.classList.remove('d-none');
      }

      var idPersona = g.id_persona || paciente.id;
      var urlHistoria = historiaConContexto(idPersona, { parent: 'GUARDIA', parent_id: g.id });
      var ctaAtender = rowEl.querySelector('[data-role="cta-atender"]');
      if (ctaAtender && urlHistoria) {
        ctaAtender.href = urlHistoria;
        ctaAtender.classList.remove('disabled');
      } else if (ctaAtender) {
        ctaAtender.classList.add('disabled');
        ctaAtender.removeAttribute('href');
      }

      var sinTriage = g.circuito_estado === 'espera_triage' || g.prioridad_triage == null;
      var ctaTriage = rowEl.querySelector('[data-role="cta-triage"]');
      if (ctaTriage) {
        ctaTriage.classList.toggle('d-none', !sinTriage);
        ctaTriage.onclick = function () {
          if (urlHistoria) window.location.href = urlHistoria;
        };
      }
    }

    function bindTableroRefresh(wrapEl) {
      if (!wrapEl) return;
      var btn = wrapEl.querySelector('[data-role="tablero-refresh"]');
      if (btn) {
        btn.addEventListener('click', function () {
          loadGuardiaTablero(false);
        });
      }
    }

    function setTableroUpdated(wrapEl) {
      if (!wrapEl) return;
      var el = wrapEl.querySelector('[data-role="tablero-updated"]');
      if (el) {
        var now = new Date();
        el.textContent = 'Actualizado ' + now.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
      }
    }

    function renderGuardiaTablero(items) {
      if (!items || !items.length) {
        showListadoEmpty(msgEmptyGuardias);
        return;
      }
      clearNode(container);
      var wrapFrag = importTemplate('tpl-pacientes-guardias-wrap');
      if (!wrapFrag) return;
      var rowsSlot = wrapFrag.querySelector('[data-slot="guardias-rows"]');
      var wrapRoot = wrapFrag.querySelector('[data-role="guardias-wrap"]');
      container.appendChild(wrapFrag);
      bindTableroRefresh(wrapRoot);

      items.forEach(function (g) {
        var itemFrag = importTemplate('tpl-paciente-guardia-row');
        if (!itemFrag) return;
        var row = itemFrag.firstElementChild;
        if (!row) return;
        fillGuardiaTableroRow(row, g);
        rowsSlot.appendChild(row);
      });

      setTableroUpdated(wrapRoot);
    }

    function fillCirugiaCard(colEl, c) {
      colEl.querySelector('[data-field="nombre"]').textContent =
        (c.paciente && c.paciente.nombre_completo) ? c.paciente.nombre_completo : 'Sin paciente';
      colEl.querySelector('[data-field="sala"]').textContent = c.sala_nombre || '—';
      colEl.querySelector('[data-field="inicio"]').textContent = c.fecha_hora_inicio || '';

      var badge = colEl.querySelector('[data-field="estado-badge"]');
      if (badge) {
        var estadoClass = (c.estado === 'REALIZADA' || c.estado === 'CANCELADA') ? 'secondary' : 'warning';
        badge.className = 'badge bg-' + estadoClass;
        badge.textContent = c.estado_label || c.estado || '';
      }

      var idPersona = c.id_persona || (c.paciente ? c.paciente.id : null);
      var urlHistoria = historiaConContexto(idPersona, { parent: 'CIRUGIA', parent_id: c.id });
      var a = colEl.querySelector('[data-role="link-historia"]');
      if (a && urlHistoria) a.href = urlHistoria;
    }

    function renderCirugias(data) {
      if (!data || !data.length) {
        showListadoEmpty(msgEmptyCirugias);
        return;
      }
      clearNode(container);
      var wrapFrag = importTemplate('tpl-pacientes-cirurgias-wrap');
      if (!wrapFrag) return;
      var row = wrapFrag.querySelector('[data-role="cirugias-grid"]');
      container.appendChild(wrapFrag);

      data.forEach(function (c) {
        var itemFrag = importTemplate('tpl-paciente-cirugia');
        if (!itemFrag) return;
        var col = itemFrag.firstElementChild;
        if (!col) return;
        fillCirugiaCard(col, c);
        row.appendChild(col);
      });
    }

    function renderByKind(kind, data) {
      if (kind === 'turnos') {
        renderTurnos(data);
      } else if (kind === 'internados') {
        renderInternados(data);
      } else if (kind === 'guardias') {
        renderGuardiaTablero(data);
      } else if (kind === 'cirugias') {
        renderCirugias(data);
      } else if (Array.isArray(data) && data.length) {
        renderTurnos(data);
      } else {
        showListadoEmpty('Sin resultados.');
      }
    }

    async function loadPacientes() {
      var api = window.BioenlaceNativePage;
      if (!api) throw new Error('NativePage bridge no disponible');

      var url = api.apiV1Url('pacientes');
      var u = new URL(url);
      if (fecha) u.searchParams.set('fecha', fecha);

      var json = await api.fetchJson(u.toString(), {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });

      var kind = json.kind || '';
      var data = Array.isArray(json.data) ? json.data : [];
      renderByKind(kind, data);

      if (api.bindSpaNavLinks) api.bindSpaNavLinks(container);
    }

    async function loadGuardiaTablero(showSpinner) {
      if (showSpinner !== false) {
        errorEl.classList.add('d-none');
        setLoading(true);
      }
      try {
        var api = window.BioenlaceNativePage;
        if (!api) throw new Error('NativePage bridge no disponible');

        var url = api.apiV1Url('clinical/emergency-guardia/tablero');
        var json = await api.fetchJson(url, {
          method: 'GET',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        var payload = json.data || {};
        var items = payload.items || [];
        renderGuardiaTablero(items);
        setLoading(false);

        if (api.bindSpaNavLinks) api.bindSpaNavLinks(container);
      } catch (e) {
        setLoading(false);
        showError(errorEl, e && e.message ? e.message : 'No se pudo cargar el tablero de guardia.');
      }
    }

    function startTableroPoll() {
      stopTableroPoll();
      pollTimer = window.setInterval(function () {
        loadGuardiaTablero(false);
      }, TABLERO_POLL_MS);
    }

    function stopTableroPoll() {
      if (pollTimer) {
        window.clearInterval(pollTimer);
        pollTimer = null;
      }
    }

    async function load() {
      errorEl.classList.add('d-none');
      setLoading(true);
      try {
        if (esGuardia || encounter === 'EMER') {
          await loadGuardiaTablero(true);
          startTableroPoll();
        } else {
          stopTableroPoll();
          await loadPacientes();
          setLoading(false);
        }
      } catch (e) {
        setLoading(false);
        showError(errorEl, e && e.message ? e.message : 'No se pudo cargar el listado.');
      }
    }

    load();

    window.addEventListener('beforeunload', stopTableroPoll);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
