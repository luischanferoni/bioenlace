/**
 * Panel de inicio web (site/index): GET /api/v1/home/panel — turnos / internados / tablero guardia / cirugías.
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

  function formatHoraSinSegundos(hora) {
    var s = String(hora || '').trim();
    if (!s) return '';
    var m = s.match(/^(\d{1,2}:\d{2})/);
    return m ? m[1] : s;
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
    var urlAsistente = root.getAttribute('data-url-asistente') || '';

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

    var patientHomeState = {
      wrapRoot: null,
      pasados: [],
      pasadosTotal: 0,
      pasadosLoading: false,
      tab: 'proximos',
    };

    var asyncChatState = {
      encounterId: null,
      canCompose: true,
      modal: null,
      closeModal: null,
      chatPolicy: null,
      isStaff: false,
      item: null,
      mediaRecorder: null,
      mediaChunks: [],
      isRecording: false,
      pendingUploadType: null,
    };

    var PASADOS_PAGE_LIMIT = 20;

    function setLoading(isLoading) {
      loading.classList.toggle('d-none', !isLoading);
      container.classList.toggle('d-none', isLoading);
    }

    function showListadoEmpty(message, targetEl) {
      var el = targetEl || container;
      clearNode(el);
      var frag = importTemplate('tpl-pacientes-alert-empty');
      if (!frag) return;
      var msgEl = frag.querySelector('[data-field="message"]');
      if (msgEl) msgEl.textContent = message;
      el.appendChild(frag);
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

    function fillModalidadInsight(colEl, insight) {
      var slot = colEl.querySelector('[data-slot="modalidad-insight"]');
      if (!slot) return;
      if (!insight || !insight.summary) {
        slot.classList.add('d-none');
        return;
      }
      var tone = insight.tone === 'secondary' ? 'alert-secondary' : 'alert-info';
      slot.className = 'alert alert-sm mt-3 mb-0 py-2 px-2 small ' + tone;
      slot.classList.remove('d-none');
      var summaryEl = slot.querySelector('[data-field="insight-summary"]');
      if (summaryEl) summaryEl.textContent = insight.summary;
      var listEl = slot.querySelector('[data-slot="insight-modalidades"]');
      if (listEl) {
        clearNode(listEl);
        (insight.modalidades || []).forEach(function (m) {
          var li = document.createElement('li');
          var strong = document.createElement('strong');
          strong.textContent = m.label || m.code || '';
          li.appendChild(strong);
          if (m.description) {
            li.appendChild(document.createTextNode(': ' + m.description));
          }
          listEl.appendChild(li);
        });
      }
      var footerEl = slot.querySelector('[data-field="insight-footer"]');
      if (footerEl) {
        clearNode(footerEl);
        var hasFooter = false;
        if (insight.footer) {
          footerEl.appendChild(document.createTextNode(insight.footer));
          hasFooter = true;
        }
        if (insight.agenda_config && insight.agenda_config.link_label) {
          if (hasFooter) {
            footerEl.appendChild(document.createTextNode(' '));
          }
          var cfg = insight.agenda_config;
          var link = document.createElement('a');
          link.href = cfg.assistant_url_path || urlAsistente || '#';
          link.className = 'link-primary fw-semibold';
          link.textContent = cfg.link_label;
          link.setAttribute('data-spa-nav', '1');
          link.setAttribute('data-spa-title', cfg.link_label);
          footerEl.appendChild(link);
          hasFooter = true;
        }
        footerEl.classList.toggle('d-none', !hasFooter);
      }
    }

    function fillTurnoCard(colEl, t) {
      colEl.querySelector('[data-field="nombre"]').textContent =
        (t.paciente && t.paciente.nombre_completo) ? t.paciente.nombre_completo : 'Sin paciente';
      colEl.querySelector('[data-field="hora"]').textContent = formatHoraSinSegundos(t.hora || '');
      colEl.querySelector('[data-field="servicio"]').textContent = t.servicio || 'Sin servicio';

      var badge = colEl.querySelector('[data-field="estado-badge"]');
      if (badge) {
        var estadoClass = (t.estado === 'PENDIENTE') ? 'warning' : 'secondary';
        badge.className = 'badge bg-' + estadoClass;
        badge.textContent = t.estado_label || t.estado || '';
      }

      var tipoBadge = colEl.querySelector('[data-field="tipo-atencion-badge"]');
      if (tipoBadge) {
        var tipoLabel = (t.tipo_atencion_label || '').toString().trim();
        if (!tipoLabel) {
          tipoLabel = t.tipo_atencion === 'teleconsulta' ? 'Videollamada' : (t.tipo_atencion === 'presencial' ? 'Presencial' : '');
        }
        if (tipoLabel) {
          tipoBadge.className = t.tipo_atencion === 'teleconsulta' ? 'badge bg-info' : 'badge bg-secondary';
          tipoBadge.textContent = tipoLabel;
          tipoBadge.classList.remove('d-none');
        } else {
          tipoBadge.classList.add('d-none');
        }
      }

      fillModalidadInsight(colEl, t.modalidad_insight);

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

    function renderTurnos(data, targetEl) {
      var target = targetEl || container;
      if (!data || !data.length) {
        showListadoEmpty(msgEmptyTurnos, target);
        return;
      }
      clearNode(target);
      var wrapFrag = importTemplate('tpl-pacientes-turnos-wrap');
      if (!wrapFrag) return;
      var row = wrapFrag.querySelector('[data-role="turnos-grid"]');
      target.appendChild(wrapFrag);

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

    function renderInternados(data, targetEl) {
      var target = targetEl || container;
      if (!data || !data.length) {
        showListadoEmpty(msgEmptyInternados, target);
        return;
      }
      clearNode(target);
      var wrapFrag = importTemplate('tpl-pacientes-internados-wrap');
      if (!wrapFrag) return;
      var rowsSlot = wrapFrag.querySelector('[data-slot="internados-rows"]');
      target.appendChild(wrapFrag);

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
      var parts = [];
      var level = g.prioridad_triage;
      if (level == null || level === '') {
        parts.push('guardia-tablero-row--sin-triage');
      } else {
        parts.push('guardia-tablero-row--nivel-' + String(level));
      }
      if (g.sla_violado) {
        parts.push('guardia-tablero-row--sla-violado');
      }
      return parts.join(' ');
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

      var slaBadge = rowEl.querySelector('[data-field="sla-badge"]');
      if (slaBadge) {
        if (g.sla_violado) {
          var slaLabel = g.sla_tipo === 'triage' ? 'SLA triage' : 'SLA médico';
          slaBadge.textContent = slaLabel + (g.sla_umbral_minutos != null ? ' >' + g.sla_umbral_minutos + 'm' : '');
          slaBadge.classList.remove('d-none');
        } else {
          slaBadge.classList.add('d-none');
        }
      }

      var internacionBadge = rowEl.querySelector('[data-field="internacion-badge"]');
      if (internacionBadge) {
        internacionBadge.classList.toggle('d-none', !g.internacion_pendiente);
      }

      var clinical = g.clinical || {};
      var clinicalLine = rowEl.querySelector('[data-field="clinical-line"]');
      if (clinicalLine) {
        var parts = [];
        if (clinical.orders_count > 0) {
          parts.push(clinical.orders_count + ' pedido(s)');
        }
        if (clinical.orders_lab_pending > 0) {
          parts.push(clinical.orders_lab_pending + ' lab pend.');
        }
        if (clinical.laboratory_reports_count > 0) {
          parts.push(clinical.laboratory_reports_count + ' informe(s)');
        }
        if (parts.length) {
          clinicalLine.textContent = parts.join(' · ');
          clinicalLine.classList.remove('d-none');
        } else {
          clinicalLine.classList.add('d-none');
        }
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

      var ctaClinical = rowEl.querySelector('[data-role="cta-clinical"]');
      if (ctaClinical) {
        ctaClinical.classList.toggle('d-none', cerrado);
        ctaClinical.onclick = function () {
          openClinicalModal(g);
        };
      }

      var ctaInternacion = rowEl.querySelector('[data-role="cta-internacion"]');
      if (ctaInternacion) {
        var showInt = !cerrado && !g.internacion_pendiente;
        ctaInternacion.classList.toggle('d-none', !showInt);
        ctaInternacion.onclick = function () {
          solicitarInternacionGuardia(g);
        };
      }
      if (g.internacion_pendiente && g.internacion_ingreso_url) {
        var linkInt = rowEl.querySelector('[data-role="cta-internacion-link"]');
        if (!linkInt && ctaInternacion) {
          ctaInternacion.textContent = 'Ingresar cama';
          ctaInternacion.classList.remove('d-none');
          ctaInternacion.onclick = function () {
            window.location.href = g.internacion_ingreso_url;
          };
        }
      }
    }

    var clinicalModal = null;
    var clinicalModalGuardiaId = 0;

    function getClinicalModal() {
      if (!clinicalModal) {
        var el = document.getElementById('guardia-clinical-modal');
        if (el && window.bootstrap && window.bootstrap.Modal) {
          clinicalModal = new window.bootstrap.Modal(el);
        }
      }
      return clinicalModal;
    }

    async function openClinicalModal(g) {
      clinicalModalGuardiaId = g.id;
      var nameEl = document.getElementById('guardia-clinical-paciente-nombre');
      if (nameEl) nameEl.textContent = nombrePacienteGuardia(g);
      var errEl = document.getElementById('guardia-clinical-error');
      if (errEl) errEl.classList.add('d-none');
      var modal = getClinicalModal();
      if (modal) modal.show();
      await loadClinicalModalContent(g);
    }

    async function loadClinicalModalContent(g) {
      var api = window.BioenlaceNativePage;
      if (!api || !clinicalModalGuardiaId) return;
      var loading = document.getElementById('guardia-clinical-loading');
      var ordersEl = document.getElementById('guardia-clinical-orders');
      var labEl = document.getElementById('guardia-clinical-lab');
      var capturaLink = document.getElementById('guardia-clinical-captura-link');
      if (loading) loading.classList.remove('d-none');
      if (ordersEl) ordersEl.classList.add('d-none');
      if (labEl) labEl.classList.add('d-none');
      try {
        var url = api.apiV1Url('clinical/emergency-guardia/' + clinicalModalGuardiaId + '/resumen-clinico');
        var json = await api.fetchJson(url, {
          method: 'GET',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        var d = json.data || {};
        if (capturaLink && d.captura_url) {
          capturaLink.href = d.captura_url;
          capturaLink.classList.remove('d-none');
        }
        if (ordersEl) {
          ordersEl.innerHTML = '';
          (d.orders || []).forEach(function (o) {
            var li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between';
            li.innerHTML = '<span>' + (o.display || 'Pedido') + '</span><span class="badge bg-secondary">' + (o.result_status || '') + '</span>';
            ordersEl.appendChild(li);
          });
          ordersEl.classList.toggle('d-none', !(d.orders && d.orders.length));
        }
        if (labEl) {
          labEl.innerHTML = '';
          (d.laboratory_reports || []).forEach(function (r) {
            var li = document.createElement('li');
            li.className = 'list-group-item';
            li.textContent = (r.display || 'Informe') + (r.issued_at ? ' — ' + r.issued_at : '');
            labEl.appendChild(li);
          });
          labEl.classList.toggle('d-none', !(d.laboratory_reports && d.laboratory_reports.length));
        }
      } catch (e) {
        var errEl = document.getElementById('guardia-clinical-error');
        if (errEl) {
          errEl.textContent = e && e.message ? e.message : 'No se pudo cargar el resumen.';
          errEl.classList.remove('d-none');
        }
      } finally {
        if (loading) loading.classList.add('d-none');
      }
    }

    async function submitClinicalPedido() {
      var api = window.BioenlaceNativePage;
      var input = document.getElementById('guardia-clinical-pedido-display');
      var display = input ? input.value.trim() : '';
      if (!api || !clinicalModalGuardiaId || !display) return;
      try {
        var url = api.apiV1Url('clinical/emergency-guardia/' + clinicalModalGuardiaId + '/crear-pedido');
        await api.fetchJson(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({ display: display, category: 'laboratory' }),
        });
        if (input) input.value = '';
        await loadGuardiaTablero(false);
        await loadClinicalModalContent({ id: clinicalModalGuardiaId });
      } catch (e) {
        var errEl = document.getElementById('guardia-clinical-error');
        if (errEl) {
          errEl.textContent = e && e.message ? e.message : 'No se pudo crear el pedido.';
          errEl.classList.remove('d-none');
        }
      }
    }

    async function solicitarInternacionGuardia(g) {
      var api = window.BioenlaceNativePage;
      if (!api || !g.id) return;
      try {
        var url = api.apiV1Url('clinical/emergency-guardia/' + g.id + '/solicitar-internacion');
        await api.fetchJson(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: '{}',
        });
        await loadGuardiaTablero(false);
      } catch (e) {
        showError(errorEl, e && e.message ? e.message : 'No se pudo solicitar internación.');
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
            solicitar_internacion: !!(document.getElementById('guardia-derivar-solicitar-internacion') || {}).checked,
            notificar_internacion_id_efector: idDest,
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
        if (d.sla_incumplidos_tablero != null) {
          parts.push(d.sla_incumplidos_tablero + ' SLA incumplido(s)');
        }
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
      var exportBtn = wrapEl.querySelector('[data-role="tablero-export-csv"]');
      if (exportBtn) {
        exportBtn.addEventListener('click', function (ev) {
          var api = window.BioenlaceNativePage;
          if (!api) return;
          exportBtn.href = api.apiV1Url('clinical/emergency-guardia/indicadores-export-csv');
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

    function renderCoberturaActivaBanner(coberturaData, parentEl) {
      if (!coberturaData || !parentEl) return;
      var items = coberturaData.items || [];
      var box = document.createElement('div');
      box.className = 'alert alert-light border mb-3 py-2';
      var title = document.createElement('div');
      title.className = 'fw-semibold small mb-1';
      title.textContent = coberturaData.title || 'Cobertura';
      box.appendChild(title);
      var session = coberturaData.session || {};
      if (session.tiene_cobertura === false) {
        var warn = document.createElement('div');
        warn.className = 'text-warning small mb-1';
        warn.textContent = 'No tenés cobertura vigente: no podrás tomar casos hasta cargarla.';
        box.appendChild(warn);
      } else if (session.tiene_cobertura === true) {
        var ok = document.createElement('div');
        ok.className = 'text-success small mb-1';
        ok.textContent = 'Estás en el plantel activo.';
        box.appendChild(ok);
      }
      if (!items.length) {
        var empty = document.createElement('div');
        empty.className = 'text-muted small';
        empty.textContent = coberturaData.empty_message || 'Sin cobertura cargada.';
        box.appendChild(empty);
      } else {
        var row = document.createElement('div');
        row.className = 'd-flex flex-wrap gap-2';
        items.forEach(function (c) {
          var chip = document.createElement('span');
          chip.className = 'badge text-bg-secondary';
          var nombre = (c.persona && c.persona.nombre_completo) ? c.persona.nombre_completo : 'Profesional';
          var rol = c.rol ? ' · ' + c.rol : '';
          var ini = (c.inicio || '').toString().substring(11, 16);
          var fin = (c.fin || '').toString().substring(11, 16);
          var horas = (ini || fin) ? ' · ' + ini + '–' + fin : '';
          chip.textContent = nombre + rol + horas;
          row.appendChild(chip);
        });
        box.appendChild(row);
      }
      parentEl.insertBefore(box, parentEl.firstChild);
    }

    function renderGuardiaTablero(items, indicatorsData, coberturaData) {
      if (!items || !items.length) {
        showListadoEmpty(msgEmptyGuardias);
        if (coberturaData) {
          renderCoberturaActivaBanner(coberturaData, container);
        }
        return;
      }
      clearNode(container);
      var wrapFrag = importTemplate('tpl-pacientes-guardias-wrap');
      if (!wrapFrag) return;
      var rowsSlot = wrapFrag.querySelector('[data-slot="guardias-rows"]');
      var wrapRoot = wrapFrag.querySelector('[data-role="guardias-wrap"]');
      container.appendChild(wrapFrag);
      if (coberturaData) {
        renderCoberturaActivaBanner(coberturaData, wrapRoot || container);
      }
      bindTableroRefresh(wrapRoot);
      if (indicatorsData) {
        applyTableroResumenFromData(wrapRoot, indicatorsData);
      } else {
        loadTableroResumen(wrapRoot);
      }

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

    function renderCirugias(data, targetEl) {
      var target = targetEl || container;
      if (!data || !data.length) {
        showListadoEmpty(msgEmptyCirugias, target);
        return;
      }
      clearNode(target);
      var wrapFrag = importTemplate('tpl-pacientes-cirurgias-wrap');
      if (!wrapFrag) return;
      var row = wrapFrag.querySelector('[data-role="cirugias-grid"]');
      target.appendChild(wrapFrag);

      data.forEach(function (c) {
        var itemFrag = importTemplate('tpl-paciente-cirugia');
        if (!itemFrag) return;
        var col = itemFrag.firstElementChild;
        if (!col) return;
        fillCirugiaCard(col, c);
        row.appendChild(col);
      });
    }

    function renderActionCards(data) {
      var categories = Array.isArray(data.categories) ? data.categories : [];
      var actions = Array.isArray(data.actions) ? data.actions : [];
      if (!categories.length && !actions.length) {
        showListadoEmpty('No hay atajos disponibles para tu rol.');
        return;
      }
      clearNode(container);
      var wrapFrag = importTemplate('tpl-home-action-cards-wrap');
      if (!wrapFrag) return;
      var wrapRoot = wrapFrag.querySelector('[data-role="action-cards-wrap"]');
      container.appendChild(wrapFrag);

      function appendActionLink(slotEl, action) {
        if (!action || !slotEl) return;
        var itemFrag = importTemplate('tpl-home-action-card');
        if (!itemFrag) return;
        var link = itemFrag.querySelector('[data-role="action-link"]');
        if (!link) return;
        var co = action.client_open && typeof action.client_open === 'object' ? action.client_open : null;
        var path = co && co.web && co.web.path ? String(co.web.path) : '';
        var nombre = action.name || action.display_name || action.action_id || 'Atajo';
        link.querySelector('[data-field="nombre"]').textContent = nombre;
        var descEl = link.querySelector('[data-field="descripcion"]');
        if (action.description) {
          descEl.textContent = action.description;
        } else {
          descEl.classList.add('d-none');
        }
        if (path) {
          link.href = path;
          link.setAttribute('data-spa-nav', '1');
        } else if (action.action_id) {
          link.href = '/site/asistente?intent=' + encodeURIComponent(String(action.action_id));
          link.setAttribute('data-spa-nav', '1');
        } else {
          link.classList.add('disabled');
          link.removeAttribute('href');
        }
        slotEl.appendChild(itemFrag);
      }

      if (categories.length) {
        categories.forEach(function (cat) {
          var catFrag = importTemplate('tpl-home-action-card-category');
          if (!catFrag) return;
          var catRoot = catFrag.querySelector('[data-role="action-category"]');
          catRoot.querySelector('[data-field="titulo"]').textContent = cat.titulo || 'Atajos';
          var slot = catRoot.querySelector('[data-slot="actions"]');
          (cat.actions || []).forEach(function (a) {
            appendActionLink(slot, a);
          });
          wrapRoot.appendChild(catFrag);
        });
      } else {
        var flatFrag = importTemplate('tpl-home-action-card-category');
        if (flatFrag) {
          var flatRoot = flatFrag.querySelector('[data-role="action-category"]');
          flatRoot.querySelector('[data-field="titulo"]').textContent = 'Atajos';
          var flatSlot = flatRoot.querySelector('[data-slot="actions"]');
          actions.forEach(function (a) {
            appendActionLink(flatSlot, a);
          });
          wrapRoot.appendChild(flatFrag);
        }
      }
    }

    function asistenteUrl(intentId) {
      return asistenteFlowUrl(intentId, {});
    }

  /**
   * URL del asistente con flow y draft inicial (query draft_*).
   * @param {string} intentId
   * @param {Record<string, string|number>} draftParams
   */
    function asistenteFlowUrl(intentId, draftParams) {
      var qs = new URLSearchParams();
      qs.set('spa_flow_intent', String(intentId || ''));
      var draft = draftParams && typeof draftParams === 'object' ? draftParams : {};
      Object.keys(draft).forEach(function (key) {
        var val = draft[key];
        if (val === undefined || val === null || val === '') return;
        qs.set('draft_' + key, String(val));
      });
      return '/site/asistente?' + qs.toString();
    }

    function applyPanelChrome(panel) {
      if (!panel || !panel.title) return;
      var h2 = document.querySelector('.mb-4 h2');
      if (h2) h2.textContent = panel.title;
    }

    function formatFechaAmigable(fechaYmd) {
      if (!fechaYmd) return '';
      var parts = String(fechaYmd).split('-');
      if (parts.length !== 3) return fechaYmd;
      var y = parseInt(parts[0], 10);
      var mo = parseInt(parts[1], 10);
      var d = parseInt(parts[2], 10);
      if (isNaN(y) || isNaN(mo) || isNaN(d)) return fechaYmd;
      var slot = new Date(y, mo - 1, d);
      var today = new Date();
      today.setHours(0, 0, 0, 0);
      slot.setHours(0, 0, 0, 0);
      var diffDays = Math.round((slot.getTime() - today.getTime()) / 86400000);
      if (diffDays === 0) return 'Hoy';
      if (diffDays === 1) return 'Mañana';
      if (diffDays === 2) return 'Pasado mañana';
      var weekdays = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
      return weekdays[slot.getDay()] + ' ' + String(d).padStart(2, '0') + '/' + String(mo).padStart(2, '0');
    }

    function proximidadTurno(fechaYmd) {
      if (!fechaYmd) return null;
      var parts = String(fechaYmd).split('-');
      if (parts.length !== 3) return null;
      var y = parseInt(parts[0], 10);
      var mo = parseInt(parts[1], 10);
      var d = parseInt(parts[2], 10);
      if (isNaN(y) || isNaN(mo) || isNaN(d)) return null;
      var slot = new Date(y, mo - 1, d);
      var today = new Date();
      today.setHours(0, 0, 0, 0);
      slot.setHours(0, 0, 0, 0);
      var diffDays = Math.round((slot.getTime() - today.getTime()) / 86400000);
      if (diffDays === 0) return 'hoy';
      if (diffDays === 1) return 'manana';
      return 'mas';
    }

    function appendAsistenteAction(slotEl, label, intentId, btnClass, draftParams) {
      if (!slotEl) return;
      var a = document.createElement('a');
      a.className = btnClass || 'btn btn-sm btn-outline-primary';
      a.href = asistenteFlowUrl(intentId, draftParams || {});
      a.setAttribute('data-spa-nav', '1');
      a.textContent = label;
      slotEl.appendChild(a);
    }

    function fillPatientTurnoCard(colEl, t) {
      var servicio = t.servicio || 'Turno';
      colEl.querySelector('[data-field="servicio"]').textContent = servicio;
      colEl.querySelector('[data-field="fecha"]').textContent = formatFechaAmigable(t.fecha);
      colEl.querySelector('[data-field="hora"]').textContent = t.hora || '—';

      var profSlot = colEl.querySelector('[data-slot="profesional"]');
      if (t.profesional && profSlot) {
        profSlot.classList.remove('d-none');
        colEl.querySelector('[data-field="profesional"]').textContent = t.profesional;
      }

      var modSlot = colEl.querySelector('[data-slot="modalidad"]');
      var modLabel = (t.tipo_atencion_label || '').toString().trim();
      if (modSlot && modLabel) {
        modSlot.classList.remove('d-none');
        colEl.querySelector('[data-field="modalidad"]').textContent = modLabel;
      }

      var enRes = t.en_resolucion === true || t.estado === 'EN_RESOLUCION';
      var proxBadge = colEl.querySelector('[data-field="proximidad-badge"]');
      if (!enRes && proxBadge) {
        var prox = proximidadTurno(t.fecha);
        if (prox === 'hoy') {
          proxBadge.textContent = 'Hoy';
          proxBadge.className = 'badge bg-danger';
          proxBadge.classList.remove('d-none');
        } else if (prox === 'manana') {
          proxBadge.textContent = 'Mañana';
          proxBadge.className = 'badge bg-info text-dark';
          proxBadge.classList.remove('d-none');
        } else if (prox === 'mas') {
          proxBadge.textContent = 'Próximamente';
          proxBadge.className = 'badge bg-success';
          proxBadge.classList.remove('d-none');
        }
      }

      var estadoBadge = colEl.querySelector('[data-field="estado-badge"]');
      if (estadoBadge) {
        var estadoClass = enRes ? 'warning' : (t.estado === 'PENDIENTE' ? 'primary' : 'secondary');
        estadoBadge.className = 'badge bg-' + estadoClass;
        estadoBadge.textContent = t.estado_label || t.estado || '';
      }

      var actions = colEl.querySelector('[data-slot="actions"]');
      if (actions) {
        if (enRes) {
          appendAsistenteAction(actions, 'Elegir nuevo horario', 'turnos.reubicar-como-paciente-flow', 'btn btn-sm btn-warning');
        } else {
          appendAsistenteAction(actions, 'Gestionar turno', 'turnos.elegir-pendiente-como-paciente', 'btn btn-sm btn-outline-primary');
        }
      }
    }

    function appendPatientTurnoCards(slotEl, turnos) {
      if (!slotEl || !turnos || !turnos.length) return;
      turnos.forEach(function (t) {
        var itemFrag = importTemplate('tpl-patient-turno-card');
        if (!itemFrag) return;
        var col = itemFrag.firstElementChild;
        if (!col) return;
        fillPatientTurnoCard(col, t);
        slotEl.appendChild(itemFrag);
      });
    }

    function fillPatientTurnoListItem(rowEl, t) {
      rowEl.querySelector('[data-field="servicio"]').textContent = t.servicio || 'Turno';
      rowEl.querySelector('[data-field="fecha"]').textContent = formatFechaAmigable(t.fecha);
      var horaEl = rowEl.querySelector('[data-field="hora"]');
      var sepEl = rowEl.querySelector('[data-field="hora-sep"]');
      if (t.hora) {
        horaEl.textContent = t.hora;
        if (sepEl) sepEl.classList.remove('d-none');
      } else if (sepEl) {
        sepEl.classList.add('d-none');
      }
      var modEl = rowEl.querySelector('[data-field="modalidad"]');
      var modSep = rowEl.querySelector('[data-field="modalidad-sep"]');
      var modLabel = (t.tipo_atencion_label || '').toString().trim();
      if (modEl && modLabel) {
        modEl.textContent = modLabel;
        modEl.classList.remove('d-none');
        if (modSep) modSep.classList.remove('d-none');
      } else if (modEl) {
        modEl.classList.add('d-none');
        if (modSep) modSep.classList.add('d-none');
      }
      var badge = rowEl.querySelector('[data-field="estado-badge"]');
      if (badge) {
        badge.textContent = t.estado_label || t.estado || '';
      }
    }

    function renderPatientCarePlans(sectionSlot, items) {
      if (!items || !items.length) return;
      var secFrag = importTemplate('tpl-patient-home-section');
      if (!secFrag) return;
      var secRoot = secFrag.querySelector('[data-role="patient-section"]');
      secRoot.querySelector('[data-field="titulo"]').textContent = 'Tratamiento activo';
      var grid = secRoot.querySelector('[data-slot="items"]');
      var solicitudesActivas = [];
      var solicitudesHistorial = [];
      items.forEach(function (plan) {
        var itemFrag = importTemplate('tpl-patient-care-plan-card');
        if (!itemFrag) return;
        var col = itemFrag.firstElementChild;
        if (!col) return;
        var titulo = plan.title || plan.categoryLabel || 'Plan de tratamiento';
        col.querySelector('[data-field="titulo"]').textContent = titulo;
        var catEl = col.querySelector('[data-field="categoria"]');
        if (plan.categoryLabel && plan.title) {
          catEl.textContent = plan.categoryLabel;
        } else {
          catEl.classList.add('d-none');
        }
        var estadoTxt = plan.statusLabel || plan.status || '';
        var pendientes = Array.isArray(plan.solicitudes_activas)
          ? plan.solicitudes_activas.length
          : (parseInt(plan.solicitudes_pendientes_count, 10) || 0);
        if (pendientes > 0) {
          estadoTxt += (estadoTxt ? ' · ' : '') + pendientes + ' solicitud' + (pendientes === 1 ? '' : 'es');
        }
        col.querySelector('[data-field="estado"]').textContent = estadoTxt;
        var acts = Array.isArray(plan.activitySummaries) ? plan.activitySummaries : [];
        var actsSlot = col.querySelector('[data-slot="actividades"]');
        if (acts.length && actsSlot) {
          actsSlot.classList.remove('d-none');
          acts.forEach(function (line) {
            var li = document.createElement('li');
            li.textContent = line;
            actsSlot.appendChild(li);
          });
        }
        grid.appendChild(itemFrag);
        if (Array.isArray(plan.solicitudes_activas)) {
          solicitudesActivas = solicitudesActivas.concat(plan.solicitudes_activas);
        }
        if (Array.isArray(plan.solicitudes_historial)) {
          solicitudesHistorial = solicitudesHistorial.concat(plan.solicitudes_historial);
        }
      });
      sectionSlot.appendChild(secFrag);
      if (solicitudesActivas.length) {
        renderPatientAsyncItemsSection(
          sectionSlot,
          'Solicitudes del tratamiento',
          solicitudesActivas,
          false
        );
      }
      if (solicitudesHistorial.length) {
        renderPatientAsyncItemsSection(
          sectionSlot,
          'Solicitudes anteriores del tratamiento',
          solicitudesHistorial,
          true
        );
      }
    }

    function bindPatientHomeTabs(wrapRoot) {
      if (!wrapRoot || wrapRoot.getAttribute('data-tabs-bound') === '1') return;
      wrapRoot.setAttribute('data-tabs-bound', '1');
      var tabProx = wrapRoot.querySelector('[data-role="patient-tab-proximos"]');
      var tabPas = wrapRoot.querySelector('[data-role="patient-tab-pasados"]');
      wrapRoot.querySelectorAll('[data-role="patient-turnos-tabs"] [data-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var tab = btn.getAttribute('data-tab') || 'proximos';
          patientHomeState.tab = tab;
          wrapRoot.querySelectorAll('[data-role="patient-turnos-tabs"] .nav-link').forEach(function (b) {
            b.classList.toggle('active', b === btn);
          });
          if (tabProx) tabProx.classList.toggle('d-none', tab !== 'proximos');
          if (tabPas) tabPas.classList.toggle('d-none', tab !== 'pasados');
          if (tab === 'pasados' && patientHomeState.pasados.length === 0 && !patientHomeState.pasadosLoading) {
            loadPatientPasados(wrapRoot, true);
          }
        });
      });
      var loadMore = wrapRoot.querySelector('[data-role="pasados-load-more"]');
      if (loadMore) {
        loadMore.addEventListener('click', function () {
          loadPatientPasados(wrapRoot, false);
        });
      }
    }

    async function loadPatientPasados(wrapRoot, reset) {
      if (patientHomeState.pasadosLoading) return;
      if (!reset && patientHomeState.pasados.length >= patientHomeState.pasadosTotal) return;

      var listEl = wrapRoot.querySelector('[data-slot="pasados-list"]');
      var loadMoreBtn = wrapRoot.querySelector('[data-role="pasados-load-more"]');
      if (!listEl) return;

      patientHomeState.pasadosLoading = true;
      if (reset) {
        patientHomeState.pasados = [];
        patientHomeState.pasadosTotal = 0;
        clearNode(listEl);
      }
      if (loadMoreBtn) loadMoreBtn.classList.add('d-none');

      try {
        var api = window.BioenlaceNativePage;
        if (!api) throw new Error('NativePage bridge no disponible');
        var url = api.apiV1Url('turnos/listar-como-paciente');
        var json = await api.fetchJson(url, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            alcance: 'pasados',
            limit: PASADOS_PAGE_LIMIT,
            offset: reset ? 0 : patientHomeState.pasados.length,
          }),
        });
        if (json.success !== true) {
          throw new Error(json.message || 'No se pudo cargar el historial.');
        }
        var block = json.data || json;
        var turnos = Array.isArray(block.turnos) ? block.turnos : [];
        var total = block.total != null ? parseInt(block.total, 10) : turnos.length;
        if (reset) {
          patientHomeState.pasados = turnos.slice();
        } else {
          patientHomeState.pasados = patientHomeState.pasados.concat(turnos);
        }
        patientHomeState.pasadosTotal = isNaN(total) ? patientHomeState.pasados.length : total;

        if (reset && turnos.length === 0) {
          var emptyFrag = importTemplate('tpl-pacientes-alert-empty');
          if (emptyFrag) {
            var msgEl = emptyFrag.querySelector('[data-field="message"]');
            if (msgEl) msgEl.textContent = 'No hay turnos en tu historial.';
            listEl.appendChild(emptyFrag);
          }
        } else {
          turnos.forEach(function (t) {
            var itemFrag = importTemplate('tpl-patient-turno-list-item');
            if (!itemFrag) return;
            var row = itemFrag.querySelector('[data-role="patient-turno-list-item"]');
            if (!row) return;
            fillPatientTurnoListItem(row, t);
            listEl.appendChild(itemFrag);
          });
        }

        if (loadMoreBtn) {
          var hayMas = patientHomeState.pasados.length < patientHomeState.pasadosTotal;
          loadMoreBtn.classList.toggle('d-none', !hayMas);
        }
      } catch (e) {
        showError(errorEl, e && e.message ? e.message : 'No se pudo cargar el historial.');
      } finally {
        patientHomeState.pasadosLoading = false;
      }
    }

    function renderPatientHome(panel) {
      var asyncSec = findPanelSection(panel, 'patient_async_consultations');
      var upcomingSec = findPanelSection(panel, 'patient_upcoming_appointments');
      var careSec = findPanelSection(panel, 'patient_care_plans_active');
      var enResolucion = upcomingSec && upcomingSec.data && upcomingSec.data.en_resolucion
        ? (upcomingSec.data.en_resolucion.turnos || [])
        : [];
      var pendientes = upcomingSec && upcomingSec.data && upcomingSec.data.pendientes
        ? (upcomingSec.data.pendientes.turnos || [])
        : [];
      var careItems = careSec && careSec.data ? (careSec.data.items || []) : [];

      clearNode(container);
      var wrapFrag = importTemplate('tpl-patient-home-wrap');
      if (!wrapFrag) {
        showListadoEmpty('No se pudo renderizar el panel de inicio.');
        return;
      }
      var wrapRoot = wrapFrag.querySelector('[data-role="patient-home-wrap"]');
      container.appendChild(wrapFrag);
      patientHomeState.wrapRoot = wrapRoot;

      var banner = wrapRoot.querySelector('[data-role="patient-en-resolucion-banner"]');
      if (enResolucion.length && banner) {
        var t0 = enResolucion[0];
        var txt = (t0.servicio ? String(t0.servicio) + ' — ' : '')
          + formatFechaAmigable(t0.fecha)
          + (t0.hora ? (' ' + t0.hora) : '');
        banner.querySelector('[data-field="en-resolucion-texto"]').textContent = txt;
        var cta = banner.querySelector('[data-role="en-resolucion-cta"]');
        if (cta) cta.href = asistenteUrl('turnos.reubicar-como-paciente-flow');
        banner.classList.remove('d-none');
      }

      var sectionsSlot = wrapRoot.querySelector('[data-slot="patient-sections"]');
      if (careItems.length && sectionsSlot) {
        renderPatientCarePlans(sectionsSlot, careItems);
      }
      if (asyncSec && asyncSec.data && sectionsSlot) {
        renderPatientAsyncSection(sectionsSlot, asyncSec.data);
      }

      var proxGrid = wrapRoot.querySelector('[data-slot="proximos-grid"]');
      var proximos = enResolucion.concat(pendientes);
      if (proximos.length && proxGrid) {
        appendPatientTurnoCards(proxGrid, proximos);
      } else if (proxGrid) {
        var emptyFrag = importTemplate('tpl-pacientes-alert-empty');
        if (emptyFrag) {
          var msgEl = emptyFrag.querySelector('[data-field="message"]');
          if (msgEl) {
            msgEl.textContent = 'No tenés turnos próximos.';
          }
          proxGrid.appendChild(emptyFrag);
          var solicitar = document.createElement('a');
          solicitar.className = 'btn btn-primary btn-sm mt-2';
          solicitar.href = asistenteUrl('atencion.necesito-atencion');
          solicitar.setAttribute('data-spa-nav', '1');
          solicitar.textContent = 'Solicitar turno';
          proxGrid.appendChild(solicitar);
        }
      }

      bindPatientHomeTabs(wrapRoot);
    }

    function findPanelSection(panel, kind) {
      var sections = panel.sections || [];
      for (var i = 0; i < sections.length; i++) {
        if (sections[i].kind === kind) {
          return sections[i];
        }
      }
      return null;
    }

    function applyTableroResumenFromData(wrapEl, d) {
      if (!wrapEl || !d) return;
      var el = wrapEl.querySelector('[data-role="tablero-resumen"]');
      if (!el) return;
      var parts = [];
      if (d.activos != null) parts.push(d.activos + ' activos');
      if (d.sin_triage != null) parts.push(d.sin_triage + ' sin triage');
      if (d.sla_incumplidos_tablero != null) {
        parts.push(d.sla_incumplidos_tablero + ' SLA incumplido(s)');
      }
      var t = d.tiempos_hoy || {};
      if (t.minutos_a_medico != null) parts.push('mediana a médico: ' + t.minutos_a_medico + ' min');
      el.textContent = parts.join(' · ');
      el.classList.remove('d-none');
    }

    function renderStaffKpiGroup(wrapRoot, data) {
      if (!wrapRoot || !data || !Array.isArray(data.items) || !data.items.length) return;
      var groupFrag = importTemplate('tpl-staff-kpi-group');
      if (!groupFrag) return;
      var groupRoot = groupFrag.querySelector('[data-role="kpi-group"]');
      if (!groupRoot) return;
      groupRoot.querySelector('[data-field="title"]').textContent = data.title || 'Indicadores';
      var slot = groupRoot.querySelector('[data-slot="kpi-items"]');
      data.items.forEach(function (item) {
        var itemFrag = importTemplate('tpl-staff-kpi-item');
        if (!itemFrag) return;
        var col = itemFrag.firstElementChild;
        if (!col) return;
        col.querySelector('[data-field="label"]').textContent = item.label || '';
        col.querySelector('[data-field="value"]').textContent = item.value != null ? String(item.value) : '—';
        slot.appendChild(col);
      });
      wrapRoot.appendChild(groupFrag);
    }

    function renderStaffDashboard(panel) {
      clearNode(container);
      var wrapFrag = importTemplate('tpl-staff-dashboard-wrap');
      if (!wrapFrag) {
        showListadoEmpty('No se pudo renderizar el panel.');
        return;
      }
      var wrapRoot = wrapFrag.querySelector('[data-role="staff-dashboard-wrap"]');
      container.appendChild(wrapFrag);

      var sections = panel.sections || [];
      sections.forEach(function (sec) {
        if (sec.kind === 'staff_kpi_group' && sec.data) {
          renderStaffKpiGroup(wrapRoot, sec.data);
        }
      });

      if (!wrapRoot.children.length) {
        showListadoEmpty('No hay indicadores disponibles para tu rol en este efector.');
      }
    }

    function formatAsyncCreatedAt(iso) {
      if (!iso) return '';
      var d = new Date(String(iso).replace(' ', 'T'));
      if (isNaN(d.getTime())) return String(iso);
      return d.toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' });
    }

    function renderIntakeContextBlock(rootEl, intakeContext) {
      if (!rootEl) return;
      var ctx = intakeContext && typeof intakeContext === 'object' ? intakeContext : null;
      if (!ctx || (!(ctx.lines && ctx.lines.length) && !ctx.reference_encounter && !ctx.tipo_label)) {
        rootEl.classList.add('d-none');
        return;
      }
      rootEl.classList.remove('d-none');

      var titleEl = rootEl.querySelector('[data-field="intake-title"]');
      if (titleEl) {
        titleEl.textContent = ctx.section_label || 'Contexto de la solicitud';
      }

      var tipoEl = rootEl.querySelector('[data-field="intake-tipo"]');
      if (tipoEl) {
        if (ctx.tipo_label) {
          tipoEl.textContent = ctx.tipo_label;
          tipoEl.classList.remove('d-none');
        } else {
          tipoEl.classList.add('d-none');
        }
      }

      var linesSlot = rootEl.querySelector('[data-slot="intake-lines"]');
      if (linesSlot) {
        clearNode(linesSlot);
        var lines = Array.isArray(ctx.lines) ? ctx.lines : [];
        lines.forEach(function (line) {
          if (!line || line.code === 'reference_encounter') return;
          var label = String(line.label || '').trim();
          var value = String(line.value || '').trim();
          if (!label || !value) return;
          var row = document.createElement('div');
          row.innerHTML =
            '<strong>' +
            escapeHtml(label) +
            ':</strong> ' +
            escapeHtml(value);
          linesSlot.appendChild(row);
        });
      }

      var summaryEl = rootEl.querySelector('[data-field="intake-summary"]');
      if (summaryEl) {
        summaryEl.classList.add('d-none');
        summaryEl.textContent = '';
      }

      var detailSlot = rootEl.querySelector('[data-slot="intake-encounter-detail"]');
      if (detailSlot) {
        clearNode(detailSlot);
        var refEnc = ctx.reference_encounter && typeof ctx.reference_encounter === 'object'
          ? ctx.reference_encounter
          : null;
        var detail = refEnc && refEnc.detail && typeof refEnc.detail === 'object' ? refEnc.detail : null;
        if (detail) {
          detailSlot.classList.remove('d-none');
          var detailTitle = document.createElement('div');
          detailTitle.className = 'fw-semibold';
          detailTitle.textContent = detail.title || 'Atención de referencia';
          detailSlot.appendChild(detailTitle);
          if (detail.headline) {
            var hl = document.createElement('div');
            hl.className = 'text-muted';
            hl.textContent = detail.headline;
            detailSlot.appendChild(hl);
          }
          var efectorNombre =
            detail.efector && detail.efector.nombre ? String(detail.efector.nombre) : '';
          if (efectorNombre) {
            var ef = document.createElement('div');
            ef.textContent = efectorNombre;
            detailSlot.appendChild(ef);
          }
          var profDisplay =
            detail.profesional && detail.profesional.display
              ? String(detail.profesional.display)
              : '';
          if (profDisplay) {
            var pr = document.createElement('div');
            pr.textContent = 'Profesional: ' + profDisplay;
            detailSlot.appendChild(pr);
          }
          var narrative = String(detail.narrativeText || '').trim();
          if (narrative) {
            var nar = document.createElement('div');
            nar.className = 'mt-1';
            nar.textContent =
              narrative.length > 600 ? narrative.slice(0, 600) + '…' : narrative;
            detailSlot.appendChild(nar);
          }
        } else {
          detailSlot.classList.add('d-none');
        }
      }

      var linksSlot = rootEl.querySelector('[data-slot="intake-links"]');
      if (!linksSlot) return;
      clearNode(linksSlot);
      var references = Array.isArray(ctx.references) ? ctx.references : [];
      references.forEach(function (ref) {
        if (!ref || !ref.kind) return;
        var personaId = ref.subject_persona_id;
        if (!personaId) return;
        var href = historiaConContexto(personaId, {});
        if (!href) return;
        if (ref.kind === 'reference_encounter' && ref.encounter_id) {
          href +=
            (href.indexOf('?') >= 0 ? '&' : '?') +
            'id_consulta=' +
            encodeURIComponent(ref.encounter_id);
        }
        var a = document.createElement('a');
        a.href = href;
        a.className =
          ref.kind === 'clinical_history'
            ? 'btn btn-primary btn-sm me-2 mb-1'
            : 'btn btn-outline-secondary btn-sm me-2 mb-1';
        a.textContent =
          ref.label ||
          (ref.kind === 'clinical_history'
            ? 'Ver historia clínica'
            : 'Ver atención de referencia');
        a.setAttribute('data-spa-nav', '1');
        a.setAttribute('data-spa-title', a.textContent);
        linksSlot.appendChild(a);
      });
    }

    function escapeHtml(s) {
      return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    function fillAsyncIntakeContext(colEl, item) {
      var slot = colEl.querySelector('[data-slot="intake-context"]');
      if (!slot) return;
      // Bandeja staff: sin intake_context (reason_preview alcanza); el chat lo carga al abrir.
      clearNode(slot);
      slot.classList.add('d-none');
    }

    function getAsyncChatModal() {
      if (!asyncChatState.modal) {
        var el = document.getElementById('async-chat-modal');
        if (el && window.bootstrap && window.bootstrap.Modal) {
          asyncChatState.modal = new window.bootstrap.Modal(el);
        }
      }
      return asyncChatState.modal;
    }

    function getAsyncChatHelpers() {
      return window.BioenlaceAsyncConsultaChat || null;
    }

    function getAsyncChatCloseModal() {
      if (!asyncChatState.closeModal) {
        var el = document.getElementById('async-chat-close-modal');
        if (el && window.bootstrap && window.bootstrap.Modal) {
          asyncChatState.closeModal = new window.bootstrap.Modal(el);
        }
      }
      return asyncChatState.closeModal;
    }

    function applyAsyncChatPolicyUI(policy) {
      var compose = document.getElementById('async-chat-compose');
      var resolveSlot = document.getElementById('async-chat-resolve-actions');
      var hintEl = document.getElementById('async-chat-policy-hint');
      var actionsSlot = document.getElementById('async-chat-header-actions');
      var p = policy || asyncChatState.chatPolicy;
      if (!p) return;

      if (hintEl) {
        if (p.hint) {
          hintEl.textContent = p.hint;
          hintEl.classList.remove('d-none');
        } else {
          hintEl.classList.add('d-none');
        }
      }
      if (compose) {
        if (p.composerEnabled && asyncChatState.canCompose) {
          compose.classList.remove('d-none');
        } else {
          compose.classList.add('d-none');
        }
      }
      if (resolveSlot) {
        clearNode(resolveSlot);
        if (p.showResolutionActions) {
          resolveSlot.classList.remove('d-none');
          p.resolutions.forEach(function (r, idx) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = idx === 0 ? 'btn btn-primary btn-sm me-2 mb-2' : 'btn btn-outline-secondary btn-sm me-2 mb-2';
            btn.textContent = r.label;
            btn.addEventListener('click', function () {
              resolveAsyncChatWithCode(r);
            });
            resolveSlot.appendChild(btn);
          });
        } else {
          resolveSlot.classList.add('d-none');
        }
      }
      var attachSlot = document.getElementById('async-chat-attach-actions');
      if (attachSlot) {
        clearNode(attachSlot);
        if (p.composerEnabled && asyncChatState.canCompose && p.uploadEnabled) {
          if (p.canUploadImage) {
            var imgBtn = document.createElement('button');
            imgBtn.type = 'button';
            imgBtn.className = 'btn btn-outline-secondary btn-sm';
            imgBtn.textContent = 'Adjuntar imagen';
            imgBtn.addEventListener('click', function () {
              triggerAsyncChatFilePick('imagen');
            });
            attachSlot.appendChild(imgBtn);
          }
          if (p.canUploadDocument) {
            var pdfBtn = document.createElement('button');
            pdfBtn.type = 'button';
            pdfBtn.className = 'btn btn-outline-secondary btn-sm';
            pdfBtn.textContent = 'Adjuntar PDF';
            pdfBtn.addEventListener('click', function () {
              triggerAsyncChatFilePick('documento');
            });
            attachSlot.appendChild(pdfBtn);
          }
          if (p.canUploadAudio) {
            var audioBtn = document.createElement('button');
            audioBtn.type = 'button';
            audioBtn.className = 'btn btn-outline-secondary btn-sm';
            audioBtn.id = 'async-chat-audio-btn';
            audioBtn.textContent = asyncChatState.isRecording ? 'Detener audio' : 'Grabar audio';
            audioBtn.addEventListener('click', toggleAsyncChatAudioRecording);
            attachSlot.appendChild(audioBtn);
          }
        }
      }
      if (actionsSlot) {
        clearNode(actionsSlot);
        if (p.canCancel) {
          var cancelBtn = document.createElement('button');
          cancelBtn.type = 'button';
          cancelBtn.className = 'btn btn-outline-danger btn-sm';
          cancelBtn.textContent = 'Retirar solicitud';
          cancelBtn.addEventListener('click', cancelAsyncChatComoPaciente);
          actionsSlot.appendChild(cancelBtn);
        }
        if (p.canClose && !p.showResolutionActions) {
          var closeBtn = document.createElement('button');
          closeBtn.type = 'button';
          closeBtn.className = 'btn btn-outline-secondary btn-sm';
          closeBtn.textContent = 'Cerrar consulta';
          closeBtn.addEventListener('click', openAsyncChatCloseModal);
          actionsSlot.appendChild(closeBtn);
        }
      }
    }

    function resolveAsyncChatWithCode(resolution) {
      if (!resolution || !resolution.code) return;
      var note = '';
      if (resolution.requireNote) {
        note = window.prompt('Nota para el paciente (obligatoria):', '') || '';
        note = String(note).trim();
        if (!note) {
          window.alert('Indicá una nota para el paciente.');
          return;
        }
      } else if (!window.confirm('¿Confirmás: ' + resolution.label + '?')) {
        return;
      }
      confirmAsyncChatCloseWith(resolution.code, note);
    }

    async function confirmAsyncChatCloseWith(resolutionCode, note) {
      var api = window.BioenlaceNativePage;
      var errEl =
        document.getElementById('async-chat-close-error') ||
        document.getElementById('async-chat-error');
      if (!api || !asyncChatState.encounterId || !resolutionCode) return;
      if (errEl) errEl.classList.add('d-none');
      try {
        var url = api.apiV1Url('consulta-async/cerrar-como-staff');
        var json = await api.fetchJson(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({
            encounter_id: asyncChatState.encounterId,
            resolution_code: resolutionCode,
            note: note ? String(note).trim() : '',
          }),
        });
        if (json.success === false) {
          throw new Error(json.message || 'No se pudo cerrar la consulta.');
        }
        var closeModal = getAsyncChatCloseModal();
        if (closeModal) closeModal.hide();
        await loadAsyncChatMessages(asyncChatState.encounterId);
        await loadPanel({ showSpinner: false });
      } catch (e) {
        if (errEl) {
          errEl.textContent = e && e.message ? e.message : 'Error al cerrar.';
          errEl.classList.remove('d-none');
        }
      }
    }

    function openAsyncChatAttachment(url, type) {
      var api = window.BioenlaceNativePage;
      if (!api || !url) return;
      var headers = window.BioenlaceApiClient && window.BioenlaceApiClient.mergeHeaders
        ? window.BioenlaceApiClient.mergeHeaders({ 'X-Requested-With': 'XMLHttpRequest' })
        : { 'X-Requested-With': 'XMLHttpRequest' };
      fetch(url, { method: 'GET', headers: headers, credentials: 'same-origin' })
        .then(function (res) {
          if (!res.ok) throw new Error('No se pudo abrir el adjunto.');
          return res.blob();
        })
        .then(function (blob) {
          var objectUrl = URL.createObjectURL(blob);
          window.open(objectUrl, '_blank', 'noopener');
        })
        .catch(function (e) {
          var errEl = document.getElementById('async-chat-error');
          if (errEl) {
            errEl.textContent = e && e.message ? e.message : 'No se pudo abrir el adjunto.';
            errEl.classList.remove('d-none');
          }
        });
    }

    function renderAsyncChatMessages(messages) {
      var box = document.getElementById('async-chat-messages');
      var helpers = getAsyncChatHelpers();
      if (!box) return;
      clearNode(box);
      (messages || []).forEach(function (m) {
        if (helpers && helpers.renderMessage) {
          box.appendChild(helpers.renderMessage(m, openAsyncChatAttachment));
        } else {
          var row = document.createElement('div');
          row.className = 'mb-2 small';
          row.textContent = m.content || '';
          box.appendChild(row);
        }
      });
      box.scrollTop = box.scrollHeight;
    }

    function triggerAsyncChatFilePick(messageType) {
      var input = document.getElementById('async-chat-file-input');
      if (!input) return;
      asyncChatState.pendingUploadType = messageType || 'documento';
      if (messageType === 'imagen') {
        input.accept = 'image/jpeg,image/png,image/webp,image/heic,.jpg,.jpeg,.png,.webp,.heic';
      } else {
        input.accept = 'application/pdf,.pdf';
      }
      input.click();
    }

    async function uploadAsyncChatFile(file, messageType) {
      var api = window.BioenlaceNativePage;
      var errEl = document.getElementById('async-chat-error');
      if (!api || !asyncChatState.encounterId || !file) return;
      if (errEl) errEl.classList.add('d-none');
      var form = new FormData();
      form.append('encounter_id', asyncChatState.encounterId);
      form.append('message_type', messageType);
      form.append('file', file);
      try {
        var url = api.apiV1Url('consulta-chat/subir');
        var headers = window.BioenlaceApiClient && window.BioenlaceApiClient.mergeHeaders
          ? window.BioenlaceApiClient.mergeHeaders({ 'X-Requested-With': 'XMLHttpRequest' })
          : { 'X-Requested-With': 'XMLHttpRequest' };
        var res = await fetch(url, { method: 'POST', headers: headers, body: form, credentials: 'same-origin' });
        var json = await res.json();
        if (json.success === false) {
          throw new Error(json.message || 'No se pudo subir el archivo.');
        }
        await loadAsyncChatMessages(asyncChatState.encounterId);
      } catch (e) {
        if (errEl) {
          errEl.textContent = e && e.message ? e.message : 'Error al subir.';
          errEl.classList.remove('d-none');
        }
      }
    }

    async function onAsyncChatFileSelected(ev) {
      var input = ev.target;
      var file = input && input.files && input.files[0] ? input.files[0] : null;
      if (input) input.value = '';
      if (!file) return;
      var messageType = asyncChatState.pendingUploadType || 'documento';
      asyncChatState.pendingUploadType = null;
      var errEl = document.getElementById('async-chat-error');
      if (messageType === 'imagen') {
        var imgOk = !file.type || file.type.indexOf('image/') === 0;
        if (!imgOk) {
          if (errEl) {
            errEl.textContent = 'Solo se permiten imágenes.';
            errEl.classList.remove('d-none');
          }
          return;
        }
      } else if (file.type && file.type !== 'application/pdf') {
        if (errEl) {
          errEl.textContent = 'Solo se permiten documentos PDF.';
          errEl.classList.remove('d-none');
        }
        return;
      }
      await uploadAsyncChatFile(file, messageType);
    }

    async function toggleAsyncChatAudioRecording() {
      var errEl = document.getElementById('async-chat-error');
      if (asyncChatState.isRecording && asyncChatState.mediaRecorder) {
        asyncChatState.mediaRecorder.stop();
        return;
      }
      if (!navigator.mediaDevices || !window.MediaRecorder) {
        if (errEl) {
          errEl.textContent = 'Tu navegador no permite grabar audio.';
          errEl.classList.remove('d-none');
        }
        return;
      }
      try {
        var stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        asyncChatState.mediaChunks = [];
        var recorder = new MediaRecorder(stream);
        asyncChatState.mediaRecorder = recorder;
        recorder.ondataavailable = function (ev) {
          if (ev.data && ev.data.size > 0) asyncChatState.mediaChunks.push(ev.data);
        };
        recorder.onstop = async function () {
          stream.getTracks().forEach(function (t) { t.stop(); });
          asyncChatState.isRecording = false;
          var btn = document.getElementById('async-chat-audio-btn');
          if (btn) btn.textContent = 'Grabar audio';
          var blob = new Blob(asyncChatState.mediaChunks, { type: 'audio/webm' });
          asyncChatState.mediaChunks = [];
          asyncChatState.mediaRecorder = null;
          if (blob.size <= 0) return;
          await uploadAsyncChatFile(new File([blob], 'audio.webm', { type: 'audio/webm' }), 'audio');
        };
        recorder.start();
        asyncChatState.isRecording = true;
        var btn = document.getElementById('async-chat-audio-btn');
        if (btn) btn.textContent = 'Detener audio';
      } catch (e) {
        if (errEl) {
          errEl.textContent = e && e.message ? e.message : 'No se pudo acceder al micrófono.';
          errEl.classList.remove('d-none');
        }
      }
    }

    async function loadAsyncChatMessages(encounterId) {
      var api = window.BioenlaceNativePage;
      var loading = document.getElementById('async-chat-loading');
      var box = document.getElementById('async-chat-messages');
      var compose = document.getElementById('async-chat-compose');
      var errEl = document.getElementById('async-chat-error');
      if (!api || !encounterId) return;
      if (loading) loading.classList.remove('d-none');
      if (box) box.classList.add('d-none');
      if (compose) compose.classList.add('d-none');
      if (errEl) errEl.classList.add('d-none');
      try {
        var url = api.apiV1Url('consulta-chat/mensajes/' + encodeURIComponent(encounterId));
        var json = await api.fetchJson(url, {
          method: 'GET',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (json.success === false) {
          throw new Error(json.message || 'No se pudieron cargar los mensajes.');
        }
        var messages = json.data && json.data.messages ? json.data.messages : [];
        var helpers = getAsyncChatHelpers();
        asyncChatState.chatPolicy = helpers && helpers.parsePolicy
          ? helpers.parsePolicy(json.data && json.data.chat_policy)
          : null;
        renderAsyncChatMessages(messages);
        if (json.data && json.data.intake_context) {
          asyncChatState.intakeContext = json.data.intake_context;
          renderIntakeContextBlock(
            document.getElementById('async-chat-intake-context'),
            json.data.intake_context
          );
        }
        if (loading) loading.classList.add('d-none');
        if (box) box.classList.remove('d-none');
        applyAsyncChatPolicyUI(asyncChatState.chatPolicy);
      } catch (e) {
        if (loading) loading.classList.add('d-none');
        if (errEl) {
          errEl.textContent = e && e.message ? e.message : 'Error al cargar el chat.';
          errEl.classList.remove('d-none');
        }
      }
    }

    async function sendAsyncChatMessage() {
      var api = window.BioenlaceNativePage;
      var input = document.getElementById('async-chat-input');
      var errEl = document.getElementById('async-chat-error');
      if (!api || !asyncChatState.encounterId || !input) return;
      var text = String(input.value || '').trim();
      if (!text) return;
      if (errEl) errEl.classList.add('d-none');
      try {
        var url = api.apiV1Url('consulta-chat/enviar');
        var json = await api.fetchJson(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({
            encounter_id: asyncChatState.encounterId,
            message: text,
          }),
        });
        if (json.success === false) {
          throw new Error(json.message || 'No se pudo enviar el mensaje.');
        }
        input.value = '';
        await loadAsyncChatMessages(asyncChatState.encounterId);
      } catch (e) {
        if (errEl) {
          errEl.textContent = e && e.message ? e.message : 'Error al enviar.';
          errEl.classList.remove('d-none');
        }
      }
    }

    async function cancelAsyncChatComoPaciente() {
      var api = window.BioenlaceNativePage;
      if (!api || !asyncChatState.encounterId) return;
      if (!window.confirm('¿Retirar esta solicitud? Podés iniciar otra más adelante.')) return;
      var errEl = document.getElementById('async-chat-error');
      if (errEl) errEl.classList.add('d-none');
      try {
        var url = api.apiV1Url('consulta-async/cancelar-como-paciente');
        var json = await api.fetchJson(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({ encounter_id: asyncChatState.encounterId }),
        });
        if (json.success === false) {
          throw new Error(json.message || 'No se pudo retirar la solicitud.');
        }
        var modal = getAsyncChatModal();
        if (modal) modal.hide();
        await loadPanel({ showSpinner: false });
      } catch (e) {
        if (errEl) {
          errEl.textContent = e && e.message ? e.message : 'Error al retirar.';
          errEl.classList.remove('d-none');
        }
      }
    }

    function openAsyncChatCloseModal() {
      var policy = asyncChatState.chatPolicy;
      if (!policy || !policy.resolutions || !policy.resolutions.length) return;
      var select = document.getElementById('async-chat-close-resolution');
      var note = document.getElementById('async-chat-close-note');
      var errEl = document.getElementById('async-chat-close-error');
      if (!select) return;
      clearNode(select);
      policy.resolutions.forEach(function (r) {
        var opt = document.createElement('option');
        opt.value = r.code;
        opt.textContent = r.label;
        select.appendChild(opt);
      });
      if (note) note.value = '';
      if (errEl) errEl.classList.add('d-none');
      var modal = getAsyncChatCloseModal();
      if (modal) modal.show();
    }

    async function confirmAsyncChatClose() {
      var select = document.getElementById('async-chat-close-resolution');
      var note = document.getElementById('async-chat-close-note');
      if (!select) return;
      await confirmAsyncChatCloseWith(
        select.value,
        note ? String(note.value || '').trim() : ''
      );
    }

    function openAsyncChat(item, canCompose) {
      if (!item || !item.encounter_id) return;
      asyncChatState.encounterId = item.encounter_id;
      asyncChatState.item = item;
      asyncChatState.isStaff = !!(item.paciente && item.paciente.nombre_completo);
      asyncChatState.canCompose = canCompose !== false;
      asyncChatState.chatPolicy = null;
      asyncChatState.intakeContext = null;
      var subtitle = document.getElementById('async-chat-subtitle');
      if (subtitle) {
        var parts = [];
        if (item.paciente && item.paciente.nombre_completo) parts.push(item.paciente.nombre_completo);
        if (item.servicio) parts.push(item.servicio);
        subtitle.textContent = parts.join(' — ');
      }
      renderIntakeContextBlock(
        document.getElementById('async-chat-intake-context'),
        null
      );
      var input = document.getElementById('async-chat-input');
      if (input) input.value = '';
      var headerActions = document.getElementById('async-chat-header-actions');
      if (headerActions) clearNode(headerActions);
      var hintEl = document.getElementById('async-chat-policy-hint');
      if (hintEl) hintEl.classList.add('d-none');
      var compose = document.getElementById('async-chat-compose');
      if (compose) compose.classList.add('d-none');
      var modal = getAsyncChatModal();
      if (modal) modal.show();
      loadAsyncChatMessages(item.encounter_id);
    }

    async function tomarAsyncCaso(item) {
      var api = window.BioenlaceNativePage;
      if (!api || !item || !item.encounter_id) return;
      try {
        var url = api.apiV1Url('consulta-async/tomar-como-staff');
        var json = await api.fetchJson(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({ encounter_id: item.encounter_id }),
        });
        if (json.success === false) {
          throw new Error(json.message || 'No se pudo tomar la solicitud.');
        }
        await loadPanel({ showSpinner: false });
        openAsyncChat(item, true);
      } catch (e) {
        showError(errorEl, e && e.message ? e.message : 'No se pudo tomar la solicitud.');
      }
    }

    function fillAsyncSolicitudCard(colEl, item) {
      var paciente = item.paciente || {};
      colEl.querySelector('[data-field="paciente"]').textContent = paciente.nombre_completo || 'Paciente';
      colEl.querySelector('[data-field="servicio"]').textContent = item.servicio || '';
      colEl.querySelector('[data-field="created-at"]').textContent = formatAsyncCreatedAt(item.created_at);
      colEl.querySelector('[data-field="preview"]').textContent = item.reason_preview || '';
      fillAsyncIntakeContext(colEl, item);
      var badge = colEl.querySelector('[data-field="estado-badge"]');
      if (badge) {
        badge.className = 'badge bg-secondary';
        if (item.status === 'planned') badge.className = 'badge bg-warning text-dark';
        if (item.status === 'in-progress') badge.className = 'badge bg-success';
        badge.textContent = item.status_label || item.status || '';
      }
      var prioridadBadge = colEl.querySelector('[data-field="prioridad-badge"]');
      if (prioridadBadge) {
        var rank = item.prioridad && item.prioridad.rank != null ? parseInt(item.prioridad.rank, 10) : 0;
        if (rank > 0 && rank <= 3) {
          prioridadBadge.textContent = 'Prioridad ' + rank;
          prioridadBadge.classList.remove('d-none');
        } else {
          prioridadBadge.classList.add('d-none');
        }
      }
      var slaSlot = colEl.querySelector('[data-slot="sla-alerta"]');
      if (slaSlot && item.sla && item.sla.incumplido) {
        slaSlot.classList.remove('d-none');
        var slaBadge = slaSlot.querySelector('[data-field="sla-badge"]');
        if (slaBadge) {
          slaBadge.textContent = 'SLA vencido (' + (item.sla.horas_objetivo || '') + ' h)';
        }
      } else if (slaSlot) {
        slaSlot.classList.add('d-none');
      }
      var actions = colEl.querySelector('[data-slot="actions"]');
      if (!actions) return;
      clearNode(actions);
      if (item.acciones && item.acciones.tomar) {
        var tomar = document.createElement('button');
        tomar.type = 'button';
        tomar.className = 'btn btn-sm btn-primary';
        tomar.textContent = 'Tomar y responder';
        tomar.addEventListener('click', function () { tomarAsyncCaso(item); });
        actions.appendChild(tomar);
      }
      if (item.acciones && item.acciones.abrir_chat) {
        var chat = document.createElement('button');
        chat.type = 'button';
        chat.className = 'btn btn-sm btn-outline-primary';
        chat.textContent = 'Ver conversación';
        chat.addEventListener('click', function () { openAsyncChat(item, true); });
        actions.appendChild(chat);
      }
    }

    function renderAsyncBandeja(data, targetEl) {
      if (!targetEl || !data || !Array.isArray(data.items) || !data.items.length) {
        if (targetEl) clearNode(targetEl);
        return;
      }
      clearNode(targetEl);
      var wrapFrag = importTemplate('tpl-async-bandeja-wrap');
      if (!wrapFrag) return;
      var wrapRoot = wrapFrag.querySelector('[data-role="async-bandeja-wrap"]');
      targetEl.appendChild(wrapFrag);
      wrapRoot.querySelector('[data-field="title"]').textContent = data.title || 'Consultas clínicas por mensaje';
      var slaResumen = wrapRoot.querySelector('[data-field="sla-resumen"]');
      if (slaResumen && data.sla_incumplidos > 0) {
        slaResumen.textContent = data.sla_incumplidos + ' con SLA vencido';
        slaResumen.classList.remove('d-none');
      }
      var grid = wrapRoot.querySelector('[data-slot="async-grid"]');
      data.items.forEach(function (item) {
        var cardFrag = importTemplate('tpl-async-solicitud-card');
        if (!cardFrag) return;
        var col = cardFrag.firstElementChild;
        if (!col) return;
        fillAsyncSolicitudCard(col, item);
        grid.appendChild(col);
      });
    }

    function fillPatientAsyncCard(col, item, esHistorial) {
      col.querySelector('[data-field="servicio"]').textContent = item.servicio || '';
      col.querySelector('[data-field="created-at"]').textContent = formatAsyncCreatedAt(item.created_at);
      col.querySelector('[data-field="preview"]').textContent = item.reason_preview || '';
      var badge = col.querySelector('[data-field="estado-badge"]');
      if (badge) {
        badge.textContent = item.status_label || item.status || '';
      }
      var tipoBadge = col.querySelector('[data-field="solicitud-tipo"]');
      if (tipoBadge) {
        var tipo = item.solicitud_tipo ? String(item.solicitud_tipo).trim() : '';
        if (tipo) {
          tipoBadge.textContent = tipo;
          tipoBadge.classList.remove('d-none');
        } else {
          tipoBadge.classList.add('d-none');
        }
      }
      var resolucionEl = col.querySelector('[data-field="resolucion"]');
      if (resolucionEl) {
        var resolucion = item.resolution_label ? String(item.resolution_label).trim() : '';
        if (esHistorial && resolucion) {
          resolucionEl.textContent = 'Resolución: ' + resolucion;
          resolucionEl.classList.remove('d-none');
        } else {
          resolucionEl.classList.add('d-none');
        }
      }
      var actions = col.querySelector('[data-slot="actions"]');
      if (!actions) return;
      clearNode(actions);
      if (item.acciones && item.acciones.abrir_chat) {
        var chat = document.createElement('button');
        chat.type = 'button';
        chat.className = 'btn btn-sm btn-outline-primary';
        chat.textContent = esHistorial ? 'Ver conversación' : 'Ver mensajes';
        chat.addEventListener('click', function () { openAsyncChat(item, !esHistorial); });
        actions.appendChild(chat);
      }
      if (item.acciones && item.acciones.cancelar) {
        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'btn btn-sm btn-outline-danger';
        cancel.textContent = 'Retirar solicitud';
        cancel.addEventListener('click', function () { cancelAsyncSolicitudDesdeCard(item); });
        actions.appendChild(cancel);
      }
    }

    async function cancelAsyncSolicitudDesdeCard(item) {
      var api = window.BioenlaceNativePage;
      var encounterId = item && item.encounter_id ? parseInt(item.encounter_id, 10) : 0;
      if (!api || !(encounterId > 0)) return;
      if (!window.confirm('¿Retirar esta solicitud? Solo podés hacerlo mientras el equipo aún no la atiende.')) return;
      try {
        var url = api.apiV1Url('consulta-async/cancelar-como-paciente');
        var json = await api.fetchJson(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({ encounter_id: encounterId }),
        });
        if (json.success === false) {
          throw new Error(json.message || 'No se pudo retirar la solicitud.');
        }
        await loadPanel({ showSpinner: false });
      } catch (e) {
        window.alert(e && e.message ? e.message : 'Error al retirar la solicitud.');
      }
    }

    function asyncPerteneceATratamiento(item) {
      if (!item) return false;
      if (item.ui_group === 'tratamiento') return true;
      if (item.ui_group === 'consultas') return false;
      var carePlanId = item.care_plan_id != null ? parseInt(item.care_plan_id, 10) : 0;
      return carePlanId > 0;
    }

    function splitAsyncByUiGroup(items) {
      var tratamiento = [];
      var consultas = [];
      (items || []).forEach(function (item) {
        if (asyncPerteneceATratamiento(item)) {
          tratamiento.push(item);
        } else {
          consultas.push(item);
        }
      });
      return { tratamiento: tratamiento, consultas: consultas };
    }

    function renderPatientAsyncItemsSection(sectionsSlot, title, items, esHistorial) {
      if (!sectionsSlot || !Array.isArray(items) || !items.length) return;
      var secFrag = importTemplate('tpl-patient-home-section');
      if (!secFrag) return;
      var secRoot = secFrag.querySelector('[data-role="patient-section"]');
      secRoot.querySelector('[data-field="titulo"]').textContent = title || 'Consultas clínicas por mensaje';
      var itemsSlot = secRoot.querySelector('[data-slot="items"]');
      items.forEach(function (item) {
        var cardFrag = importTemplate('tpl-patient-async-card');
        if (!cardFrag) return;
        var col = cardFrag.firstElementChild;
        if (!col) return;
        fillPatientAsyncCard(col, item, esHistorial);
        itemsSlot.appendChild(col);
      });
      sectionsSlot.appendChild(secFrag);
    }

    function renderPatientAsyncSection(sectionsSlot, data) {
      if (!sectionsSlot || !data) return;
      // Solo consultas generales: las de tratamiento se anidan en care_plans_active.
      var activas = splitAsyncByUiGroup(data.items || []);
      var history = data.history || {};
      var hist = splitAsyncByUiGroup(history.items || []);

      renderPatientAsyncItemsSection(
        sectionsSlot,
        data.title,
        activas.consultas,
        false
      );
      if (hist.consultas.length) {
        renderPatientAsyncItemsSection(
          sectionsSlot,
          history.title || 'Consultas anteriores',
          hist.consultas,
          true
        );
      }
    }

    function beginClinicalListPanel(panel) {
      var kpiSections = (panel.sections || []).filter(function (sec) {
        return sec.kind === 'staff_kpi_group' && sec.data && Array.isArray(sec.data.items) && sec.data.items.length;
      });
      var coberturaSec = findPanelSection(panel, 'staff_cobertura_activa');
      var asyncSec = findPanelSection(panel, 'async_consultations_queue');
      var hasAsync = asyncSec && asyncSec.data && asyncSec.data.items && asyncSec.data.items.length;
      if (!kpiSections.length && !hasAsync && !coberturaSec) {
        return { listTarget: container, asyncSlot: null };
      }
      clearNode(container);
      var wrapFrag = importTemplate('tpl-clinical-list-panel-wrap');
      if (!wrapFrag) {
        return { listTarget: container, asyncSlot: null };
      }
      var kpiSlot = wrapFrag.querySelector('[data-slot="kpi-sections"]');
      var listSlot = wrapFrag.querySelector('[data-slot="list-content"]');
      var asyncSlot = wrapFrag.querySelector('[data-slot="async-bandeja"]');
      container.appendChild(wrapFrag);
      kpiSections.forEach(function (sec) {
        renderStaffKpiGroup(kpiSlot, sec.data);
      });
      if (coberturaSec && coberturaSec.data) {
        renderCoberturaActivaBanner(coberturaSec.data, listSlot || container);
      }
      return {
        listTarget: listSlot || container,
        asyncSlot: asyncSlot,
        asyncSec: asyncSec,
      };
    }

    function renderFromPanel(panel) {
      var layout = panel.layout || '';
      if (layout === 'staff_dashboard') {
        renderStaffDashboard(panel);
        applyPanelChrome(panel);
        return;
      }
      if (layout === 'clinical_board') {
        var coberturaSec = findPanelSection(panel, 'staff_cobertura_activa');
        var boardSec = findPanelSection(panel, 'emergency_board');
        var items = boardSec && boardSec.data ? boardSec.data.items || [] : [];
        var indicatorsSec = findPanelSection(panel, 'emergency_indicators');
        renderGuardiaTablero(items, indicatorsSec ? indicatorsSec.data : null, coberturaSec ? coberturaSec.data : null);
        applyPanelChrome(panel);
        return;
      }
      if (layout === 'clinical_list') {
        var panelParts = beginClinicalListPanel(panel);
        if (panelParts.asyncSlot && panelParts.asyncSec) {
          renderAsyncBandeja(panelParts.asyncSec.data, panelParts.asyncSlot);
        }
        var appt = findPanelSection(panel, 'appointments_day');
        if (appt) {
          renderTurnos((appt.data && appt.data.items) || [], panelParts.listTarget);
          applyPanelChrome(panel);
          return;
        }
        var inpat = findPanelSection(panel, 'inpatients');
        if (inpat) {
          renderInternados((inpat.data && inpat.data.items) || [], panelParts.listTarget);
          applyPanelChrome(panel);
          return;
        }
        var surg = findPanelSection(panel, 'surgeries_day');
        if (surg) {
          renderCirugias((surg.data && surg.data.items) || [], panelParts.listTarget);
          applyPanelChrome(panel);
          return;
        }
      }
      if (layout === 'cards') {
        var cardsSec = findPanelSection(panel, 'action_cards');
        if (cardsSec) {
          renderActionCards(cardsSec.data || {});
          applyPanelChrome(panel);
          return;
        }
        showListadoEmpty('Sin acciones disponibles en el panel.');
        applyPanelChrome(panel);
        return;
      }
      if (layout === 'patient_home') {
        renderPatientHome(panel);
        applyPanelChrome(panel);
        return;
      }
      showListadoEmpty('Sin resultados.');
    }

    async function loadPanel(options) {
      options = options || {};
      if (options.showSpinner !== false) {
        errorEl.classList.add('d-none');
        setLoading(true);
      }
      try {
        var api = window.BioenlaceNativePage;
        if (!api) throw new Error('NativePage bridge no disponible');

        var url = api.apiV1Url('home/panel');
        var u = new URL(url);
        if (fecha) u.searchParams.set('fecha', fecha);
        if (options.sections) u.searchParams.set('sections', options.sections);

        var json = await api.fetchJson(u.toString(), {
          method: 'GET',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (json.success === false) {
          throw new Error(json.message || 'No se pudo cargar el panel.');
        }

        var panel = json.data || {};
        if (options.sections) {
          var coberturaSecPoll = findPanelSection(panel, 'staff_cobertura_activa');
          var boardSecPoll = findPanelSection(panel, 'emergency_board');
          var indSecPoll = findPanelSection(panel, 'emergency_indicators');
          if (boardSecPoll) {
            var itemsPoll = boardSecPoll.data ? boardSecPoll.data.items || [] : [];
            renderGuardiaTablero(
              itemsPoll,
              indSecPoll ? indSecPoll.data : null,
              coberturaSecPoll ? coberturaSecPoll.data : null
            );
          } else if (indSecPoll) {
            var wrapRootPoll = container.querySelector('[data-role="guardias-wrap"]');
            applyTableroResumenFromData(wrapRootPoll, indSecPoll.data);
          }
        } else {
          renderFromPanel(panel);
        }
        setLoading(false);

        if (api.bindSpaNavLinks) api.bindSpaNavLinks(container);
      } catch (e) {
        setLoading(false);
        showError(errorEl, e && e.message ? e.message : 'No se pudo cargar el panel.');
      }
    }

    async function loadGuardiaTablero(showSpinner) {
      await loadPanel({
        showSpinner: showSpinner,
        sections: 'staff_cobertura_activa,emergency_board,emergency_indicators',
      });
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
          await loadPanel({ showSpinner: true });
          startTableroPoll();
        } else {
          stopTableroPoll();
          await loadPanel({ showSpinner: true });
        }
      } catch (e) {
        setLoading(false);
        showError(errorEl, e && e.message ? e.message : 'No se pudo cargar el panel.');
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
    var clinicalPedidoSubmit = document.getElementById('guardia-clinical-pedido-submit');
    if (clinicalPedidoSubmit) {
      clinicalPedidoSubmit.addEventListener('click', submitClinicalPedido);
    }
    var asyncChatSend = document.getElementById('async-chat-send');
    if (asyncChatSend) {
      asyncChatSend.addEventListener('click', sendAsyncChatMessage);
    }
    var asyncChatCloseConfirm = document.getElementById('async-chat-close-confirm');
    if (asyncChatCloseConfirm) {
      asyncChatCloseConfirm.addEventListener('click', confirmAsyncChatClose);
    }
    var asyncChatFileInput = document.getElementById('async-chat-file-input');
    if (asyncChatFileInput) {
      asyncChatFileInput.addEventListener('change', onAsyncChatFileSelected);
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
