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
    var triageModal = null;
    var triageModalGuardiaId = 0;
    var triageModalIsRetriage = false;
    var derivarModal = null;
    var derivarModalGuardiaId = 0;
    var finalizarModal = null;
    var finalizarModalGuardiaId = 0;
    var efectoresDerivacionCache = null;

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

    function guardiaEpisodioCerrado(g) {
      var e = g.circuito_estado || '';
      return e === 'finalizado' || e === 'derivado';
    }

    function nombrePacienteGuardia(g) {
      var paciente = g.paciente || {};
      return paciente.nombre_completo || g.nombre_completo || '';
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

      var cerrado = guardiaEpisodioCerrado(g);
      var sinTriage = g.circuito_estado === 'espera_triage' || g.prioridad_triage == null;
      var ctaAtender = rowEl.querySelector('[data-role="cta-atender"]');
      if (ctaAtender) {
        ctaAtender.classList.toggle('d-none', sinTriage || cerrado);
        ctaAtender.onclick = function (ev) {
          ev.preventDefault();
          iniciarAtencionGuardia(g);
        };
      }

      var ctaTriage = rowEl.querySelector('[data-role="cta-triage"]');
      if (ctaTriage) {
        ctaTriage.classList.toggle('d-none', !sinTriage || cerrado);
        ctaTriage.onclick = function () {
          openTriageModal(g, false);
        };
      }

      var ctaRetriage = rowEl.querySelector('[data-role="cta-retriage"]');
      if (ctaRetriage) {
        ctaRetriage.classList.toggle('d-none', sinTriage || cerrado);
        ctaRetriage.onclick = function () {
          openTriageModal(g, true);
        };
      }

      var ctaTomar = rowEl.querySelector('[data-role="cta-tomar"]');
      if (ctaTomar) {
        var sinAsignar = !g.profesional_asignado && !g.id_profesional_efector_servicio;
        ctaTomar.classList.toggle('d-none', !sinAsignar || cerrado);
        ctaTomar.onclick = function () {
          tomarCasoGuardia(g);
        };
      }

      var ctaDerivar = rowEl.querySelector('[data-role="cta-derivar"]');
      if (ctaDerivar) {
        ctaDerivar.classList.toggle('d-none', sinTriage || cerrado);
        ctaDerivar.onclick = function () {
          openDerivarModal(g);
        };
      }

      var ctaFinalizar = rowEl.querySelector('[data-role="cta-finalizar"]');
      if (ctaFinalizar) {
        ctaFinalizar.classList.toggle('d-none', sinTriage || cerrado);
        ctaFinalizar.onclick = function () {
          openFinalizarModal(g);
        };
      }
    }

    function getTriageModal() {
      if (!triageModal) {
        var el = document.getElementById('guardia-triage-modal');
        if (el && window.bootstrap && window.bootstrap.Modal) {
          triageModal = new window.bootstrap.Modal(el);
        }
      }
      return triageModal;
    }

    function openTriageModal(g, isRetriage) {
      triageModalIsRetriage = !!isRetriage;
      triageModalGuardiaId = g.id;
      var nameEl = document.getElementById('guardia-triage-paciente-nombre');
      if (nameEl) nameEl.textContent = nombrePacienteGuardia(g);
      var titleEl = document.getElementById('guardiaTriageModalLabel');
      if (titleEl) {
        titleEl.textContent = triageModalIsRetriage ? 'Actualizar triage' : 'Triage';
      }
      var submitBtn = document.getElementById('guardia-triage-submit');
      if (submitBtn) {
        submitBtn.textContent = triageModalIsRetriage ? 'Guardar cambios' : 'Registrar triage';
      }
      var reasonEl = document.getElementById('guardia-triage-reason');
      var triage = g.triage || {};
      if (reasonEl) {
        reasonEl.value = triageModalIsRetriage ? (triage.reason_text || '') : '';
      }
      if (triageModalIsRetriage && g.prioridad_triage != null) {
        var levelRadio = document.getElementById('guardia-triage-level-' + String(g.prioridad_triage));
        if (levelRadio) levelRadio.checked = true;
      }
      var errEl = document.getElementById('guardia-triage-error');
      if (errEl) errEl.classList.add('d-none');
      var modal = getTriageModal();
      if (modal) modal.show();
    }

    async function tomarCasoGuardia(g) {
      var api = window.BioenlaceNativePage;
      if (!api || !g.id) return;
      try {
        var url = api.apiV1Url('clinical/emergency-guardia/' + g.id + '/asignar');
        var json = await api.fetchJson(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: '{}',
        });
        if (json.success === false) {
          throw new Error(json.message || 'No se pudo asignar el caso');
        }
        await loadGuardiaTablero(false);
      } catch (e) {
        showError(errorEl, e && e.message ? e.message : 'No se pudo tomar el caso.');
      }
    }

    function getDerivarModal() {
      if (!derivarModal) {
        var el = document.getElementById('guardia-derivar-modal');
        if (el && window.bootstrap && window.bootstrap.Modal) {
          derivarModal = new window.bootstrap.Modal(el);
        }
      }
      return derivarModal;
    }

    function getFinalizarModal() {
      if (!finalizarModal) {
        var el = document.getElementById('guardia-finalizar-modal');
        if (el && window.bootstrap && window.bootstrap.Modal) {
          finalizarModal = new window.bootstrap.Modal(el);
        }
      }
      return finalizarModal;
    }

    async function loadEfectoresDerivacionSelect(selectEl) {
      var api = window.BioenlaceNativePage;
      if (!api || !selectEl) return;
      if (efectoresDerivacionCache) {
        fillEfectoresSelect(selectEl, efectoresDerivacionCache);
        return;
      }
      var url = api.apiV1Url('clinical/emergency-guardia/listar-efectores-derivacion');
      var json = await api.fetchJson(url, {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (json.success === false) {
        throw new Error(json.message || 'No se pudieron cargar efectores');
      }
      efectoresDerivacionCache = json.data || [];
      fillEfectoresSelect(selectEl, efectoresDerivacionCache);
    }

    function fillEfectoresSelect(selectEl, items) {
      selectEl.innerHTML = '';
      var opt0 = document.createElement('option');
      opt0.value = '';
      opt0.textContent = 'Seleccione efector…';
      selectEl.appendChild(opt0);
      (items || []).forEach(function (ef) {
        var opt = document.createElement('option');
        opt.value = String(ef.id_efector);
        opt.textContent = ef.nombre || ('Efector ' + ef.id_efector);
        selectEl.appendChild(opt);
      });
    }

    async function openDerivarModal(g) {
      derivarModalGuardiaId = g.id;
      var nameEl = document.getElementById('guardia-derivar-paciente-nombre');
      if (nameEl) nameEl.textContent = nombrePacienteGuardia(g);
      var errEl = document.getElementById('guardia-derivar-error');
      if (errEl) errEl.classList.add('d-none');
      var selectEl = document.getElementById('guardia-derivar-efector');
      var condEl = document.getElementById('guardia-derivar-condiciones');
      if (condEl) condEl.value = '';
      try {
        await loadEfectoresDerivacionSelect(selectEl);
      } catch (e) {
        if (errEl) {
          errEl.textContent = e && e.message ? e.message : 'Error al cargar efectores.';
          errEl.classList.remove('d-none');
        }
      }
      var modal = getDerivarModal();
      if (modal) modal.show();
    }

    async function submitDerivarModal() {
      var api = window.BioenlaceNativePage;
      if (!api || !derivarModalGuardiaId) return;
      var selectEl = document.getElementById('guardia-derivar-efector');
      var condEl = document.getElementById('guardia-derivar-condiciones');
      var errEl = document.getElementById('guardia-derivar-error');
      var idDest = selectEl ? parseInt(selectEl.value, 10) : 0;
      if (!idDest) {
        if (errEl) {
          errEl.textContent = 'Seleccione el efector destino.';
          errEl.classList.remove('d-none');
        }
        return;
      }
      var submitBtn = document.getElementById('guardia-derivar-submit');
      if (submitBtn) submitBtn.disabled = true;
      try {
        var url = api.apiV1Url('clinical/emergency-guardia/' + derivarModalGuardiaId + '/derivar');
        var json = await api.fetchJson(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({
            id_efector_derivacion: idDest,
            condiciones_derivacion: condEl ? condEl.value.trim() : '',
          }),
        });
        if (json.success === false) {
          throw new Error(json.message || 'Error al derivar');
        }
        var modal = getDerivarModal();
        if (modal) modal.hide();
        await loadGuardiaTablero(false);
      } catch (e) {
        if (errEl) {
          errEl.textContent = e && e.message ? e.message : 'No se pudo derivar.';
          errEl.classList.remove('d-none');
        }
      } finally {
        if (submitBtn) submitBtn.disabled = false;
      }
    }

    function openFinalizarModal(g) {
      finalizarModalGuardiaId = g.id;
      var nameEl = document.getElementById('guardia-finalizar-paciente-nombre');
      if (nameEl) nameEl.textContent = nombrePacienteGuardia(g);
      var errEl = document.getElementById('guardia-finalizar-error');
      if (errEl) errEl.classList.add('d-none');
      var modal = getFinalizarModal();
      if (modal) modal.show();
    }

    async function submitFinalizarModal() {
      var api = window.BioenlaceNativePage;
      if (!api || !finalizarModalGuardiaId) return;
      var errEl = document.getElementById('guardia-finalizar-error');
      var submitBtn = document.getElementById('guardia-finalizar-submit');
      if (submitBtn) submitBtn.disabled = true;
      try {
        var url = api.apiV1Url('clinical/emergency-guardia/' + finalizarModalGuardiaId + '/finalizar');
        var json = await api.fetchJson(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: '{}',
        });
        if (json.success === false) {
          throw new Error(json.message || 'Error al finalizar');
        }
        var modal = getFinalizarModal();
        if (modal) modal.hide();
        await loadGuardiaTablero(false);
      } catch (e) {
        if (errEl) {
          errEl.textContent = e && e.message ? e.message : 'No se pudo registrar el egreso.';
          errEl.classList.remove('d-none');
        }
      } finally {
        if (submitBtn) submitBtn.disabled = false;
      }
    }

    async function submitTriageModal() {
      var api = window.BioenlaceNativePage;
      if (!api || !triageModalGuardiaId) return;
      var reasonEl = document.getElementById('guardia-triage-reason');
      var reason = reasonEl ? reasonEl.value.trim() : '';
      var errEl = document.getElementById('guardia-triage-error');
      if (!reason) {
        if (errEl) {
          errEl.textContent = 'Indique el motivo de consulta.';
          errEl.classList.remove('d-none');
        }
        return;
      }
      var levelInput = document.querySelector('input[name="guardia_triage_level"]:checked');
      var level = levelInput ? parseInt(levelInput.value, 10) : 3;
      var vitals = {};
      var sys = document.getElementById('guardia-triage-bp-sys');
      var dia = document.getElementById('guardia-triage-bp-dia');
      var hr = document.getElementById('guardia-triage-hr');
      if (sys && sys.value) vitals.bp_sys = parseInt(sys.value, 10);
      if (dia && dia.value) vitals.bp_dia = parseInt(dia.value, 10);
      if (hr && hr.value) vitals.hr = parseInt(hr.value, 10);

      var submitBtn = document.getElementById('guardia-triage-submit');
      if (submitBtn) submitBtn.disabled = true;
      try {
        var url = api.apiV1Url('clinical/emergency-guardia/' + triageModalGuardiaId + '/registrar-triage');
        var json = await api.fetchJson(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({
            level: level,
            reason_text: reason,
            vitals: Object.keys(vitals).length ? vitals : undefined,
          }),
        });
        if (json.success === false) {
          throw new Error(json.message || 'Error al registrar triage');
        }
        var modal = getTriageModal();
        if (modal) modal.hide();
        await loadGuardiaTablero(false);
      } catch (e) {
        if (errEl) {
          errEl.textContent = e && e.message ? e.message : 'No se pudo registrar el triage.';
          errEl.classList.remove('d-none');
        }
      } finally {
        if (submitBtn) submitBtn.disabled = false;
      }
    }

    async function iniciarAtencionGuardia(g) {
      var api = window.BioenlaceNativePage;
      if (!api) return;
      try {
        var url = api.apiV1Url('clinical/emergency-guardia/' + g.id + '/iniciar-atencion');
        var json = await api.fetchJson(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: '{}',
        });
        if (json.success === false) {
          throw new Error(json.message || 'No se pudo iniciar la atención');
        }
        var captura = json.data && json.data.captura_url;
        if (captura) {
          window.location.href = captura;
        }
      } catch (e) {
        showError(errorEl, e && e.message ? e.message : 'No se pudo iniciar la atención.');
      }
    }

    async function loadTableroResumen(wrapEl) {
      if (!wrapEl) return;
      var api = window.BioenlaceNativePage;
      if (!api) return;
      try {
        var url = api.apiV1Url('clinical/emergency-guardia/indicadores-resumen');
        var json = await api.fetchJson(url, {
          method: 'GET',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        var d = json.data || {};
        var el = wrapEl.querySelector('[data-role="tablero-resumen"]');
        if (!el) return;
        var parts = [];
        if (d.activos != null) parts.push(d.activos + ' activos');
        if (d.sin_triage != null) parts.push(d.sin_triage + ' sin triage');
        var t = d.tiempos_hoy || {};
        if (t.minutos_a_medico != null) parts.push('mediana a médico: ' + t.minutos_a_medico + ' min');
        el.textContent = parts.join(' · ');
        el.classList.remove('d-none');
      } catch (e) { /* resumen opcional */ }
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
      loadTableroResumen(wrapRoot);

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

    var triageSubmit = document.getElementById('guardia-triage-submit');
    if (triageSubmit) {
      triageSubmit.addEventListener('click', submitTriageModal);
    }
    var derivarSubmit = document.getElementById('guardia-derivar-submit');
    if (derivarSubmit) {
      derivarSubmit.addEventListener('click', submitDerivarModal);
    }
    var finalizarSubmit = document.getElementById('guardia-finalizar-submit');
    if (finalizarSubmit) {
      finalizarSubmit.addEventListener('click', submitFinalizarModal);
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
