/**
 * Listado de pacientes (vista nativa tipo 1).
 * Renderiza templates HTML y busca datos siempre desde API v1.
 */
(function () {
  'use strict';

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
    var urlHistoriaBase = root.getAttribute('data-url-historia') || '';
    var urlInternacionView = root.getAttribute('data-url-internacion-view') || '';

    var msgEmptyTurnos = root.getAttribute('data-msg-empty-turnos') || 'Sin resultados.';
    var msgEmptyInternados = root.getAttribute('data-msg-empty-internados') || 'Sin resultados.';
    var msgEmptyGuardias = root.getAttribute('data-msg-empty-guardias') || 'Sin resultados.';
    var msgEmptyCirugias = root.getAttribute('data-msg-empty-cirugias') || 'Sin resultados.';

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

    function fillTurnoCard(colEl, t, idx) {
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

      data.forEach(function (t, idx) {
        var itemFrag = importTemplate('tpl-paciente-turno');
        if (!itemFrag) return;
        var col = itemFrag.firstElementChild;
        if (!col) return;
        fillTurnoCard(col, t, idx);
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

      data.forEach(function (i, idx) {
        var itemFrag = importTemplate('tpl-paciente-internado-row');
        if (!itemFrag) return;
        var row = itemFrag.firstElementChild;
        if (!row) return;
        fillInternadoRow(row, i, idx);
        rowsSlot.appendChild(row);
      });
    }

    function fillGuardiaRow(rowEl, g) {
      rowEl.querySelector('[data-field="nombre"]').textContent = g.nombre_completo || '';
      rowEl.querySelector('[data-field="documento-line"]').textContent =
        (g.tipo_documento ? (g.tipo_documento + ': ') : '') + (g.documento || '');

      var urlHistoria = historiaConContexto(g.id_persona, { parent: 'GUARDIA', parent_id: g.id });
      var cta = rowEl.querySelector('[data-role="cta-atender"]');
      if (cta && urlHistoria) {
        cta.classList.remove('disabled');
        cta.href = urlHistoria;
      } else if (cta) {
        cta.classList.add('disabled');
        cta.removeAttribute('href');
      }
    }

    function renderGuardias(data) {
      if (!data || !data.length) {
        showListadoEmpty(msgEmptyGuardias);
        return;
      }
      clearNode(container);
      var wrapFrag = importTemplate('tpl-pacientes-guardias-wrap');
      if (!wrapFrag) return;
      var rowsSlot = wrapFrag.querySelector('[data-slot="guardias-rows"]');
      container.appendChild(wrapFrag);

      data.forEach(function (g, idx) {
        var itemFrag = importTemplate('tpl-paciente-guardia-row');
        if (!itemFrag) return;
        var row = itemFrag.firstElementChild;
        if (!row) return;
        fillGuardiaRow(row, g, idx);
        rowsSlot.appendChild(row);
      });
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
      var wrapFrag = importTemplate('tpl-pacientes-cirugias-wrap');
      if (!wrapFrag) return;
      var row = wrapFrag.querySelector('[data-role="cirugias-grid"]');
      container.appendChild(wrapFrag);

      data.forEach(function (c, idx) {
        var itemFrag = importTemplate('tpl-paciente-cirugia');
        if (!itemFrag) return;
        var col = itemFrag.firstElementChild;
        if (!col) return;
        fillCirugiaCard(col, c, idx);
        row.appendChild(col);
      });
    }

    async function load() {
      errorEl.classList.add('d-none');
      setLoading(true);
      try {
        var api = window.BioenlaceNativePage;
        if (!api) throw new Error('NativePage bridge no disponible');

        var url = api.apiV1Url('pacientes');
        var u = new URL(url);
        if (fecha) u.searchParams.set('fecha', fecha);
        if (encounter) u.searchParams.set('encounter_class', encounter);

        var json = await api.fetchJson(u.toString(), {
          method: 'GET',
          headers: api.apiHeaders({ 'X-Requested-With': 'XMLHttpRequest' }),
        });

        var payload = json.data || json;
        var items = payload.items || payload.data || payload.turnos || [];

        // Backward/forward: infer por encounter_class
        if (payload.turnos) renderTurnos(payload.turnos);
        else if (payload.internados) renderInternados(payload.internados);
        else if (payload.guardias) renderGuardias(payload.guardias);
        else if (payload.cirugias) renderCirugias(payload.cirugias);
        else if (Array.isArray(items)) renderTurnos(items);
        else showListadoEmpty('Sin resultados.');

        setLoading(false);
        if (api.bindSpaNavLinks) api.bindSpaNavLinks(container);
      } catch (e) {
        setLoading(false);
        showError(errorEl, e && e.message ? e.message : 'No se pudo cargar el listado.');
      }
    }

    load();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

