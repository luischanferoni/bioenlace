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
    clearNode(errorEl);
    var i = document.createElement('i');
    i.className = 'bi bi-exclamation-triangle me-2';
    errorEl.appendChild(i);
    errorEl.appendChild(document.createTextNode(String(msg || 'Error')));
    errorEl.className = 'alert alert-warning';
    errorEl.classList.remove('d-none');
  }

  function consumeHomeFlash(flashEl) {
    if (!flashEl) return;
    var raw = null;
    try {
      raw = sessionStorage.getItem('bioenlace_home_flash');
      if (raw) {
        sessionStorage.removeItem('bioenlace_home_flash');
      }
    } catch (e) {
      return;
    }
    if (!raw) return;
    var flash = null;
    try {
      flash = JSON.parse(raw);
    } catch (e) {
      return;
    }
    if (!flash || !flash.message) return;
    var level = flash.level === 'danger' || flash.level === 'warning' ? flash.level : 'success';
    var icon =
      level === 'success'
        ? 'bi-check-circle'
        : level === 'danger'
          ? 'bi-exclamation-triangle'
          : 'bi-info-circle';
    flashEl.className = 'alert alert-' + level + ' mb-3';
    flashEl.textContent = '';
    var i = document.createElement('i');
    i.className = 'bi ' + icon + ' me-2';
    flashEl.appendChild(i);
    flashEl.appendChild(document.createTextNode(String(flash.message)));
    flashEl.classList.remove('d-none');
  }

  function init() {
    var root = document.getElementById('pacientes-listado-container');
    if (!root) return;

    var container = document.getElementById('pacientes-listado-content');
    var loading = document.getElementById('pacientes-listado-loading');
    var errorEl = document.getElementById('pacientes-listado-error');
    if (!container || !loading || !errorEl) return;

    consumeHomeFlash(document.getElementById('pacientes-listado-flash'));

    var fecha = root.getAttribute('data-fecha') || '';
    var encounter = root.getAttribute('data-encounter') || '';
    var esGuardia = root.getAttribute('data-es-guardia') === '1';
    var puedeTriage = root.getAttribute('data-puede-triage') === '1';
    var urlHistoriaBase = root.getAttribute('data-url-historia') || '';
    var urlVerConsultaBase = root.getAttribute('data-url-ver-consulta') || '';
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

    /** Turno ATENDIDO o encounter documentado → pantalla de consulta (no HC). */
    function verConsultaUrl(personaId, turnoId, encounterId) {
      if (!urlVerConsultaBase) return null;
      var base = urlVerConsultaBase;
      var q = '';
      if (encounterId) {
        q = 'encounter_id=' + encodeURIComponent(encounterId);
      } else if (turnoId) {
        q = 'turno_id=' + encodeURIComponent(turnoId);
      } else {
        return null;
      }
      if (personaId) q += '&id=' + encodeURIComponent(personaId);
      return base + (base.indexOf('?') >= 0 ? '&' : '?') + q;
    }

    /** Misma semántica que móvil: ATENDIDO = ver consulta cargada; resto = abrir HC/captura. */
    function esTurnoConsultaCargada(t) {
      return !!(t && String(t.estado || '').toUpperCase() === 'ATENDIDO');
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
        var estado = String(t.estado || '').toUpperCase();
        var estadoClass = 'secondary';
        if (estado === 'PENDIENTE') {
          estadoClass = 'warning';
        } else if (estado === 'EN_ATENCION') {
          estadoClass = 'info';
        } else if (estado === 'ATENDIDO') {
          estadoClass = 'success';
        }
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
      var consultaCargada = esTurnoConsultaCargada(t);
      var a = colEl.querySelector('[data-role="link-historia"]');
      if (!a) return;
      if (consultaCargada) {
        var urlConsulta = verConsultaUrl(idPersona, t.id);
        if (urlConsulta) {
          a.href = urlConsulta;
          a.setAttribute('data-spa-title', 'Consulta cargada');
          a.setAttribute('aria-label', 'Ver consulta');
        }
      } else {
        var urlHistoria = historiaConContexto(idPersona, {
          parent: 'TURNO',
          parent_id: t.id,
        });
        if (urlHistoria) {
          a.href = urlHistoria;
          a.setAttribute('data-spa-title', 'Historia clínica');
          a.setAttribute('aria-label', 'Ver historia clínica');
        }
      }
    }

    function isTurnoPorAtender(t) {
      var estado = String((t && t.estado) || '').toUpperCase();
      return estado !== 'ATENDIDO';
    }

    function renderTurnosGroup(groupsSlot, title, items, emptyMessage) {
      if (!groupsSlot) return;
      var groupFrag = importTemplate('tpl-pacientes-turnos-group');
      if (!groupFrag) return;
      var groupRoot = groupFrag.querySelector('[data-role="turnos-group"]');
      var titleEl = groupRoot.querySelector('[data-field="titulo"]');
      if (titleEl) {
        titleEl.textContent = title;
      }
      var grid = groupRoot.querySelector('[data-slot="turnos-grid"]');
      var emptySlot = groupRoot.querySelector('[data-slot="empty"]');
      if (!items.length) {
        if (emptySlot) {
          emptySlot.classList.remove('d-none');
          showListadoEmpty(emptyMessage || 'Sin turnos en esta sección.', emptySlot);
        }
      } else if (grid) {
        items.forEach(function (t) {
          var itemFrag = importTemplate('tpl-paciente-turno');
          if (!itemFrag) return;
          var col = itemFrag.firstElementChild;
          if (!col) return;
          fillTurnoCard(col, t);
          grid.appendChild(col);
        });
      }
      groupsSlot.appendChild(groupFrag);
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
      var groupsSlot = wrapFrag.querySelector('[data-slot="turnos-groups"]');
      target.appendChild(wrapFrag);

      var porAtender = [];
      var atendidos = [];
      data.forEach(function (t) {
        if (isTurnoPorAtender(t)) {
          porAtender.push(t);
        } else {
          atendidos.push(t);
        }
      });

      renderTurnosGroup(
        groupsSlot,
        'Por atender (' + porAtender.length + ')',
        porAtender,
        'No hay turnos pendientes de atención.'
      );
      renderTurnosGroup(
        groupsSlot,
        'Atendidos (' + atendidos.length + ')',
        atendidos,
        'Todavía no hay turnos atendidos en esta fecha.'
      );
    }

    var internadosOrden = 'recorrido';
    var internadosItemsCache = [];
    var internadosTargetEl = null;

    function fillInternadoRow(rowEl, i, opts) {
      opts = opts || {};
      var showUbicacion = !!opts.showUbicacion;
      rowEl.querySelector('[data-field="nombre"]').textContent = i.nombre || '';
      var camaBadge = rowEl.querySelector('[data-field="cama-badge"]');
      if (camaBadge) {
        camaBadge.textContent = i.cama ? ('Cama ' + i.cama) : 'Cama';
      }
      var pisoEl = rowEl.querySelector('[data-field="piso"]');
      var salaEl = rowEl.querySelector('[data-field="sala"]');
      if (pisoEl) pisoEl.textContent = i.piso || '';
      if (salaEl) salaEl.textContent = i.sala || '';
      var docLine = rowEl.querySelector('[data-slot="documento-line"]');
      var docEl = rowEl.querySelector('[data-field="documento"]');
      if (docLine && docEl) {
        var doc = (i.documento || '').toString().trim();
        if (doc) {
          docEl.textContent = doc;
          docLine.classList.remove('d-none');
        } else {
          docLine.classList.add('d-none');
        }
      }
      var ubiLine = rowEl.querySelector('[data-slot="ubicacion-line"]');
      if (ubiLine) {
        if (showUbicacion) {
          ubiLine.classList.remove('d-none');
        } else {
          ubiLine.classList.add('d-none');
        }
      }

      var urlHistoria = historiaConContexto(i.id_persona, { parent: 'INTERNACION', parent_id: i.id });
      var ctaAtender = rowEl.querySelector('[data-role="cta-atender"]');
      if (ctaAtender && urlHistoria) {
        ctaAtender.href = urlHistoria;
        ctaAtender.setAttribute('data-spa-title', 'Historia clínica');
      }

      var ctaCambio = rowEl.querySelector('[data-role="cta-cambio-cama"]');
      if (ctaCambio) {
        ctaCambio.onclick = function (ev) {
          ev.preventDefault();
          openCambioCamaModal(i);
        };
      }
      var ctaAlta = rowEl.querySelector('[data-role="cta-alta"]');
      if (ctaAlta) {
        ctaAlta.onclick = function (ev) {
          ev.preventDefault();
          openAltaInternacionModal(i);
        };
      }
    }

    function appendInternadoRow(slot, i, opts) {
      var itemFrag = importTemplate('tpl-paciente-internado-row');
      if (!itemFrag) return;
      var row = itemFrag.firstElementChild;
      if (!row) return;
      fillInternadoRow(row, i, opts);
      slot.appendChild(row);
    }

    function sortInternadosPorNombre(items) {
      return items.slice().sort(function (a, b) {
        return String(a.nombre || '').localeCompare(String(b.nombre || ''), 'es', { sensitivity: 'base' });
      });
    }

    function internadosPisoKey(i) {
      var label = String(i.piso || '').trim().toLowerCase();
      if (label) {
        return 'l:' + label;
      }
      if (i.id_piso != null && String(i.id_piso) !== '') {
        return 'i:' + String(i.id_piso);
      }
      return 'x';
    }

    function internadosSalaKey(i) {
      var label = String(i.sala || '').trim().toLowerCase();
      if (label) {
        return 'l:' + label;
      }
      if (i.id_sala != null && String(i.id_sala) !== '') {
        return 'i:' + String(i.id_sala);
      }
      return 'x';
    }

    function groupInternadosPorRecorrido(items) {
      var groups = [];
      var pisoMap = {};
      items.forEach(function (i) {
        // Agrupar por etiqueta (no solo id): en demos hay varios pisos/salas con el mismo nombre.
        var pisoKey = internadosPisoKey(i);
        var salaKey = internadosSalaKey(i);
        if (!pisoMap[pisoKey]) {
          pisoMap[pisoKey] = {
            key: pisoKey,
            label: i.piso || 'Piso',
            nro: i.nro_piso != null ? Number(i.nro_piso) : 0,
            salas: {},
            salasList: [],
          };
          groups.push(pisoMap[pisoKey]);
        }
        var pisoG = pisoMap[pisoKey];
        if (!pisoG.salas[salaKey]) {
          pisoG.salas[salaKey] = {
            key: salaKey,
            label: i.sala || 'Sala',
            nro: i.nro_sala != null ? Number(i.nro_sala) : 0,
            items: [],
          };
          pisoG.salasList.push(pisoG.salas[salaKey]);
        }
        pisoG.salas[salaKey].items.push(i);
      });
      groups.sort(function (a, b) { return a.nro - b.nro; });
      groups.forEach(function (p) {
        p.salasList.sort(function (a, b) { return a.nro - b.nro; });
        p.salasList.forEach(function (s) {
          s.items.sort(function (a, b) {
            var byCama = (Number(a.nro_cama) || 0) - (Number(b.nro_cama) || 0);
            if (byCama !== 0) return byCama;
            return String(a.nombre || '').localeCompare(String(b.nombre || ''), 'es', { sensitivity: 'base' });
          });
        });
      });
      return groups;
    }

    function bindInternadosOrden(wrapRoot) {
      var group = wrapRoot.querySelector('[data-role="internados-orden"]');
      if (!group) return;
      Array.prototype.forEach.call(group.querySelectorAll('[data-orden]'), function (btn) {
        btn.classList.toggle('active', btn.getAttribute('data-orden') === internadosOrden);
        btn.addEventListener('click', function () {
          var next = btn.getAttribute('data-orden') || 'recorrido';
          if (next === internadosOrden) return;
          internadosOrden = next;
          renderInternados(internadosItemsCache, internadosTargetEl);
        });
      });
    }

    function renderInternados(data, targetEl) {
      var target = targetEl || container;
      internadosTargetEl = target;
      internadosItemsCache = Array.isArray(data) ? data.slice() : [];
      if (!internadosItemsCache.length) {
        showListadoEmpty(msgEmptyInternados, target);
        return;
      }
      clearNode(target);
      var wrapFrag = importTemplate('tpl-pacientes-internados-wrap');
      if (!wrapFrag) return;
      var rowsSlot = wrapFrag.querySelector('[data-slot="internados-rows"]');
      var wrapRoot = wrapFrag.querySelector('[data-role="internados-wrap"]') || wrapFrag.firstElementChild;
      target.appendChild(wrapFrag);
      if (wrapRoot) bindInternadosOrden(wrapRoot);

      if (internadosOrden === 'nombre') {
        sortInternadosPorNombre(internadosItemsCache).forEach(function (i) {
          appendInternadoRow(rowsSlot, i, { showUbicacion: true });
        });
        return;
      }

      var groups = groupInternadosPorRecorrido(internadosItemsCache);
      groups.forEach(function (pisoG) {
        var appendPacientes = function (slot, items) {
          items.forEach(function (i) {
            appendInternadoRow(slot, i, { showUbicacion: false });
          });
        };

        var pisoFrag = importTemplate('tpl-internados-group-piso');
        if (!pisoFrag) {
          pisoG.salasList.forEach(function (salaG) {
            appendPacientes(rowsSlot, salaG.items);
          });
          return;
        }
        var pisoRoot = pisoFrag.firstElementChild;
        var pisoLabel = pisoRoot.querySelector('[data-field="piso-label"]');
        if (pisoLabel) pisoLabel.textContent = pisoG.label;
        var salasSlot = pisoRoot.querySelector('[data-slot="salas"]');
        pisoG.salasList.forEach(function (salaG) {
          var salaFrag = importTemplate('tpl-internados-group-sala');
          if (!salaFrag) {
            appendPacientes(salasSlot, salaG.items);
            return;
          }
          var salaRoot = salaFrag.firstElementChild;
          var salaLabel = salaRoot.querySelector('[data-field="sala-label"]');
          if (salaLabel) salaLabel.textContent = salaG.label;
          var pacSlot = salaRoot.querySelector('[data-slot="pacientes"]');
          appendPacientes(pacSlot, salaG.items);
          salasSlot.appendChild(salaFrag);
        });
        rowsSlot.appendChild(pisoFrag);
      });
    }

    function guardiaEpisodioCerrado(g) {
      var e = g.circuito_estado || '';
      return e === 'finalizado' || e === 'derivado' || e === 'atendido';
    }

    function guardiaPuedeVerConsulta(g) {
      return !!(g && g.encounter_id && (g.circuito_estado === 'atendido' || g.circuito_estado === 'derivado'));
    }

    function formatDuracionMinutos(minutos) {
      if (window.BioenlaceFecha && typeof window.BioenlaceFecha.formatDuracionMinutos === 'function') {
        return window.BioenlaceFecha.formatDuracionMinutos(minutos);
      }
      var n = Math.round(Number(minutos));
      if (!isFinite(n) || n < 0) n = 0;
      if (n < 60) return n + ' min';
      var days = Math.floor(n / 1440);
      var hours = Math.floor((n % 1440) / 60);
      var mins = n % 60;
      var parts = [];
      if (days > 0) parts.push(days + ' d');
      if (hours > 0) parts.push(hours + ' h');
      if (mins > 0 && days === 0) parts.push(mins + ' min');
      return parts.length ? parts.join(' ') : '0 min';
    }

    function circuitoBadgeClass(g) {
      var sinTriage = g.circuito_estado === 'espera_triage' || g.prioridad_triage == null;
      if (sinTriage) {
        var nivel = g.triage_espera_nivel || '';
        if (nivel === 'rojo') {
          return 'bg-danger';
        }
        if (nivel === 'naranja') {
          return 'bg-warning text-dark';
        }
        return 'bg-secondary';
      }
      if (g.circuito_estado === 'en_atencion') {
        return 'bg-info text-dark';
      }
      return 'bg-secondary';
    }

    function nombrePacienteGuardia(g) {
      if (g.nombre_completo) return g.nombre_completo;
      var paciente = g.paciente || {};
      return paciente.nombre_completo || '';
    }

    function nivelRowClass(g) {
      var parts = [];
      var level = g.prioridad_triage;
      if (level == null || level === '') {
        parts.push('guardia-tablero-row--sin-triage');
      } else {
        parts.push('guardia-tablero-row--nivel-' + String(level));
      }
      return parts.join(' ');
    }

    function fillGuardiaTableroRow(rowEl, g) {
      rowEl.className = 'd-flex align-items-center justify-content-between p-3 mb-0 border-bottom border-start border-4 guardia-tablero-row ' + nivelRowClass(g);

      rowEl.querySelector('[data-field="nombre"]').textContent = nombrePacienteGuardia(g);

      var triage = g.triage || {};
      var motivoLine = rowEl.querySelector('[data-field="motivo-line"]');
      if (motivoLine) {
        motivoLine.textContent = triage.reason_text || '';
        motivoLine.classList.toggle('d-none', !triage.reason_text);
      }

      var circuitoBadge = rowEl.querySelector('[data-field="circuito-badge"]');
      if (circuitoBadge) {
        circuitoBadge.textContent = g.circuito_estado_label || g.circuito_estado || '';
        circuitoBadge.className = 'badge ' + circuitoBadgeClass(g);
      }

      var esperaLine = rowEl.querySelector('[data-field="espera-line"]');
      if (esperaLine) {
        var min = g.minutos_espera != null ? g.minutos_espera : 0;
        esperaLine.textContent = formatDuracionMinutos(min) + ' en espera';
      }

      var profLine = rowEl.querySelector('[data-field="profesional-line"]');
      if (profLine) {
        if (g.profesional_asignado) {
          profLine.textContent = 'Asignado: ' + g.profesional_asignado;
          profLine.classList.remove('d-none');
        } else {
          profLine.textContent = '';
          profLine.classList.add('d-none');
        }
      }

      var slaBadge = rowEl.querySelector('[data-field="sla-badge"]');
      if (slaBadge) {
        // El plazo de triage se comunica coloreando "Espera triage".
        if (g.sla_violado) {
          var slaLabel = 'Plazo médico';
          if (g.sla_umbral_minutos != null) {
            slaLabel += ' >' + formatDuracionMinutos(g.sla_umbral_minutos);
          }
          slaBadge.textContent = slaLabel;
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

      var ctaVerConsulta = rowEl.querySelector('[data-role="cta-ver-consulta"]');
      if (ctaVerConsulta) {
        var urlVc = guardiaPuedeVerConsulta(g)
          ? verConsultaUrl(g.id_persona, null, g.encounter_id)
          : null;
        if (urlVc) {
          ctaVerConsulta.href = urlVc;
          ctaVerConsulta.classList.remove('d-none');
        } else {
          ctaVerConsulta.removeAttribute('href');
          ctaVerConsulta.classList.add('d-none');
        }
      }

      var ctaTriage = rowEl.querySelector('[data-role="cta-triage"]');
      if (ctaTriage) {
        // Solo primer triage en tablero (staff). Re-triage / edición: solo en HC.
        ctaTriage.classList.toggle('d-none', !puedeTriage || !sinTriage || cerrado);
        ctaTriage.onclick = function () {
          openTriageModal(g, false);
        };
      }

      var ctaRetriage = rowEl.querySelector('[data-role="cta-retriage"]');
      if (ctaRetriage) {
        ctaRetriage.classList.add('d-none');
        ctaRetriage.onclick = null;
      }

      var ctaTomar = rowEl.querySelector('[data-role="cta-tomar"]');
      if (ctaTomar) {
        // Tomar caso absorbido por Atender (iniciar-atencion asigna PES).
        ctaTomar.classList.add('d-none');
      }

      var ctaDerivar = rowEl.querySelector('[data-role="cta-derivar"]');
      if (ctaDerivar) {
        // Derivación en captura clínica del encounter, no en tablero.
        ctaDerivar.classList.add('d-none');
      }

      var ctaFinalizar = rowEl.querySelector('[data-role="cta-finalizar"]');
      if (ctaFinalizar) {
        ctaFinalizar.classList.toggle('d-none', cerrado);
        ctaFinalizar.textContent = 'Paciente se retiró';
        ctaFinalizar.onclick = function () {
          openFinalizarModal(g);
        };
      }

      var ctaInternacion = rowEl.querySelector('[data-role="cta-internacion"]');
      if (ctaInternacion) {
        // Solicitar cama: lo deduce la captura del médico (pase a internación).
        // En tablero solo staff ingresa cama cuando ya hay pedido pendiente.
        if (cerrado || !g.internacion_pendiente) {
          ctaInternacion.classList.add('d-none');
        } else {
          ctaInternacion.textContent = 'Ingresar cama';
          ctaInternacion.classList.remove('d-none');
          ctaInternacion.onclick = function () {
            openIngresoCamaModal(g);
          };
        }
      }
    }

    var ingresoCamaModal = null;
    var cambioCamaModal = null;
    var altaInternacionModal = null;
    var cambioCamaInternacionId = null;
    var altaInternacionId = null;

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

    function fillSelectOptions(selectEl, options, emptyLabel, selectedValue) {
      if (!selectEl) return;
      clearNode(selectEl);
      if (emptyLabel != null) {
        var emptyOpt = document.createElement('option');
        emptyOpt.value = '';
        emptyOpt.textContent = emptyLabel;
        selectEl.appendChild(emptyOpt);
      }
      (options || []).forEach(function (o) {
        var opt = document.createElement('option');
        opt.value = o.value != null ? String(o.value) : '';
        opt.textContent = o.label != null ? String(o.label) : opt.value;
        if (selectedValue != null && String(selectedValue) === opt.value) {
          opt.selected = true;
        }
        selectEl.appendChild(opt);
      });
    }

    function getIngresoCamaModal() {
      if (!ingresoCamaModal) {
        var el = document.getElementById('guardia-ingreso-cama-modal');
        if (el && window.bootstrap && window.bootstrap.Modal) {
          ingresoCamaModal = new window.bootstrap.Modal(el);
        }
      }
      return ingresoCamaModal;
    }

    function showIngresoError(msg) {
      var errEl = document.getElementById('guardia-ingreso-error');
      if (!errEl) return;
      if (msg) {
        errEl.textContent = msg;
        errEl.classList.remove('d-none');
      } else {
        errEl.textContent = '';
        errEl.classList.add('d-none');
      }
    }

    async function openIngresoCamaModal(g) {
      var api = window.BioenlaceNativePage;
      var modal = getIngresoCamaModal();
      if (!api || !modal || !g) return;

      var idPersona = g.id_persona || (g.paciente && g.paciente.id) || 0;
      if (!idPersona) {
        showError(errorEl, 'Falta el paciente para el ingreso.');
        return;
      }

      var nameEl = document.getElementById('guardia-ingreso-paciente-nombre');
      if (nameEl) nameEl.textContent = nombrePacienteGuardia(g);
      var loadingEl = document.getElementById('guardia-ingreso-loading');
      var formEl = document.getElementById('guardia-ingreso-form');
      var submitBtn = document.getElementById('guardia-ingreso-submit');
      if (loadingEl) loadingEl.classList.remove('d-none');
      if (formEl) formEl.classList.add('d-none');
      if (submitBtn) submitBtn.disabled = true;
      showIngresoError(null);
      modal.show();

      try {
        var url = api.apiV1Url('clinical/internacion/ingreso-formulario');
        var u = new URL(url, window.location.origin);
        u.searchParams.set('id_persona', String(idPersona));
        u.searchParams.set('id_guardia', String(g.id));
        var json = await api.fetchJson(u.toString(), {
          method: 'GET',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        var root = json;
        if (json && json.data && json.data.kind === 'ui_definition') {
          root = json.data;
        }
        var ctx = (root && root.kind === 'ui_definition')
          ? (root.data || {})
          : (json && json.data && !json.kind ? json.data : (json || {}));
        if (!ctx || !Array.isArray(ctx.camas_disponibles)) {
          throw new Error((json && json.message) || 'No se pudo cargar el formulario de ingreso.');
        }

        document.getElementById('guardia-ingreso-id-persona').value = String(ctx.id_persona || idPersona);
        document.getElementById('guardia-ingreso-id-guardia').value = String(ctx.id_guardia || g.id);
        var camas = ctx.camas_disponibles || [];
        var puedeIngresar = ctx.puede_ingresar !== false && camas.length > 0;
        var camposEl = document.getElementById('guardia-ingreso-campos');
        var avisoEl = document.getElementById('guardia-ingreso-aviso-camas');
        var aviso = (ctx.camas_aviso || '').toString().trim();
        if (avisoEl) {
          if (aviso) {
            avisoEl.textContent = aviso;
            avisoEl.classList.remove('d-none');
          } else {
            avisoEl.textContent = '';
            avisoEl.classList.add('d-none');
          }
        }
        if (camposEl) {
          if (puedeIngresar) {
            camposEl.classList.remove('d-none');
          } else {
            camposEl.classList.add('d-none');
          }
        }
        if (submitBtn) {
          if (puedeIngresar) {
            submitBtn.classList.remove('d-none');
          } else {
            submitBtn.classList.add('d-none');
          }
          submitBtn.disabled = !puedeIngresar;
        }

        if (puedeIngresar) {
          fillSelectOptions(
            document.getElementById('guardia-ingreso-id-cama'),
            camas,
            '— Elegir cama —',
            ctx.id_cama
          );
          fillSelectOptions(
            document.getElementById('guardia-ingreso-id-pes'),
            ctx.profesionales || [],
            '— Elegir —',
            ctx.id_profesional_efector_servicio_default || null
          );
          fillSelectOptions(
            document.getElementById('guardia-ingreso-en'),
            ctx.ingresa_en || [],
            '—',
            null
          );
          fillSelectOptions(
            document.getElementById('guardia-ingreso-con'),
            ctx.ingresa_con || [],
            '—',
            null
          );
          fillSelectOptions(
            document.getElementById('guardia-ingreso-obra'),
            ctx.coberturas || [],
            '—',
            ctx.obra_social_default
          );
          var fechaEl = document.getElementById('guardia-ingreso-fecha');
          var horaEl = document.getElementById('guardia-ingreso-hora');
          var tipoEl = document.getElementById('guardia-ingreso-tipo');
          if (fechaEl) fechaEl.value = ctx.fecha_inicio || '';
          if (horaEl) horaEl.value = ctx.hora_inicio || '';
          if (tipoEl) tipoEl.value = String(ctx.id_tipo_ingreso_default || 1);
          var sitEl = document.getElementById('guardia-ingreso-situacion');
          if (sitEl) sitEl.value = '';
        }

        if (loadingEl) loadingEl.classList.add('d-none');
        if (formEl) formEl.classList.remove('d-none');
      } catch (e) {
        if (loadingEl) loadingEl.classList.add('d-none');
        showIngresoError(e && e.message ? e.message : 'No se pudo cargar el ingreso.');
      }
    }

    async function submitIngresoCamaModal() {
      var api = window.BioenlaceNativePage;
      if (!api) return;
      showIngresoError(null);
      var camposEl = document.getElementById('guardia-ingreso-campos');
      if (camposEl && camposEl.classList.contains('d-none')) {
        showIngresoError('No hay camas disponibles para completar el ingreso.');
        return;
      }
      var payload = {
        id_persona: document.getElementById('guardia-ingreso-id-persona').value,
        id_guardia: document.getElementById('guardia-ingreso-id-guardia').value,
        id_cama: document.getElementById('guardia-ingreso-id-cama').value,
        id_profesional_efector_servicio: document.getElementById('guardia-ingreso-id-pes').value,
        fecha_inicio: document.getElementById('guardia-ingreso-fecha').value,
        hora_inicio: document.getElementById('guardia-ingreso-hora').value,
        id_tipo_ingreso: document.getElementById('guardia-ingreso-tipo').value,
        ingresa_en: document.getElementById('guardia-ingreso-en').value,
        ingresa_con: document.getElementById('guardia-ingreso-con').value,
        obra_social: document.getElementById('guardia-ingreso-obra').value,
        situacion_al_ingresar: document.getElementById('guardia-ingreso-situacion').value,
      };
      if (!payload.id_cama || !payload.id_profesional_efector_servicio) {
        showIngresoError('Completá cama y profesional responsable.');
        return;
      }
      var submitBtn = document.getElementById('guardia-ingreso-submit');
      if (submitBtn) submitBtn.disabled = true;
      try {
        var url = api.apiV1Url('clinical/internacion/ingreso-formulario');
        var json = await api.fetchJson(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify(payload),
        });
        var ok = json && (json.success === true || json.kind === 'ui_submit_result');
        if (json && json.kind === 'ui_submit_result' && json.success === false) {
          ok = false;
        }
        if (!ok && json && json.success === false) {
          throw new Error(json.message || 'No se pudo registrar el ingreso.');
        }
        var modal = getIngresoCamaModal();
        if (modal) modal.hide();
        await loadGuardiaTablero(false);
      } catch (e) {
        showIngresoError(e && e.message ? e.message : 'No se pudo registrar el ingreso.');
        if (submitBtn) submitBtn.disabled = false;
      }
    }

    function unwrapUiDefinitionPayload(json) {
      var root = json;
      if (json && json.data && json.data.kind === 'ui_definition') {
        root = json.data;
      }
      var ctx = (root && root.kind === 'ui_definition')
        ? (root.data || {})
        : (json && json.data && !json.kind ? json.data : (json || {}));
      return { root: root, ctx: ctx || {} };
    }

    function getCambioCamaModal() {
      if (!cambioCamaModal) {
        var el = document.getElementById('internacion-cambio-cama-modal');
        if (el && window.bootstrap && window.bootstrap.Modal) {
          cambioCamaModal = new window.bootstrap.Modal(el);
        }
      }
      return cambioCamaModal;
    }

    function showCambioCamaError(msg) {
      var errEl = document.getElementById('internacion-cambio-cama-error');
      if (!errEl) return;
      if (msg) {
        errEl.textContent = msg;
        errEl.classList.remove('d-none');
      } else {
        errEl.textContent = '';
        errEl.classList.add('d-none');
      }
    }

    async function openCambioCamaModal(item) {
      var api = window.BioenlaceNativePage;
      var modal = getCambioCamaModal();
      if (!api || !modal || !item || !item.id) return;

      cambioCamaInternacionId = item.id;
      var nameEl = document.getElementById('internacion-cambio-cama-paciente');
      if (nameEl) nameEl.textContent = item.nombre || ('Internación #' + item.id);
      var loadingEl = document.getElementById('internacion-cambio-cama-loading');
      var formEl = document.getElementById('internacion-cambio-cama-form');
      var submitBtn = document.getElementById('internacion-cambio-cama-submit');
      if (loadingEl) loadingEl.classList.remove('d-none');
      if (formEl) formEl.classList.add('d-none');
      if (submitBtn) submitBtn.disabled = true;
      showCambioCamaError(null);
      modal.show();

      try {
        var url = api.apiV1Url('clinical/internacion/' + item.id + '/cambio-cama-formulario');
        var json = await api.fetchJson(url, {
          method: 'GET',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        var unwrapped = unwrapUiDefinitionPayload(json);
        var ctx = unwrapped.ctx;
        var camas = ctx.camas_disponibles || [];
        if (!Array.isArray(camas)) {
          throw new Error((json && json.message) || 'No se pudo cargar el cambio de cama.');
        }
        var camaActualEl = document.getElementById('internacion-cambio-cama-actual');
        if (camaActualEl) {
          camaActualEl.textContent = ctx.cama_actual_label
            ? ('Cama actual: ' + ctx.cama_actual_label)
            : '';
        }
        fillSelectOptions(
          document.getElementById('internacion-cambio-cama-id-cama'),
          camas,
          '— Elegir cama —'
        );
        var motivoEl = document.getElementById('internacion-cambio-cama-motivo');
        if (motivoEl) motivoEl.value = '';
        if (loadingEl) loadingEl.classList.add('d-none');
        if (formEl) formEl.classList.remove('d-none');
        if (submitBtn) submitBtn.disabled = camas.length === 0;
        if (camas.length === 0) {
          showCambioCamaError('No hay camas disponibles para el cambio.');
        }
      } catch (e) {
        if (loadingEl) loadingEl.classList.add('d-none');
        showCambioCamaError(e && e.message ? e.message : 'No se pudo cargar el cambio de cama.');
      }
    }

    async function submitCambioCamaModal() {
      var api = window.BioenlaceNativePage;
      if (!api || !cambioCamaInternacionId) return;
      var idCama = (document.getElementById('internacion-cambio-cama-id-cama') || {}).value || '';
      var motivo = (document.getElementById('internacion-cambio-cama-motivo') || {}).value || '';
      if (!idCama || !motivo.trim()) {
        showCambioCamaError('Completá cama destino y motivo.');
        return;
      }
      var submitBtn = document.getElementById('internacion-cambio-cama-submit');
      if (submitBtn) submitBtn.disabled = true;
      showCambioCamaError(null);
      try {
        var url = api.apiV1Url(
          'clinical/internacion/' + cambioCamaInternacionId + '/cambio-cama-formulario'
        );
        var json = await api.fetchJson(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({ id_cama: idCama, motivo: motivo }),
        });
        var ok = json && (json.success === true || json.kind === 'ui_submit_result');
        if (json && json.kind === 'ui_submit_result' && json.success === false) {
          ok = false;
        }
        if (!ok && json && json.success === false) {
          throw new Error(json.message || 'No se pudo registrar el cambio de cama.');
        }
        var modal = getCambioCamaModal();
        if (modal) modal.hide();
        await load();
      } catch (e) {
        showCambioCamaError(e && e.message ? e.message : 'No se pudo registrar el cambio de cama.');
        if (submitBtn) submitBtn.disabled = false;
      }
    }

    function getAltaInternacionModal() {
      if (!altaInternacionModal) {
        var el = document.getElementById('internacion-alta-modal');
        if (el && window.bootstrap && window.bootstrap.Modal) {
          altaInternacionModal = new window.bootstrap.Modal(el);
        }
      }
      return altaInternacionModal;
    }

    function showAltaInternacionError(msg) {
      var errEl = document.getElementById('internacion-alta-error');
      if (!errEl) return;
      if (msg) {
        errEl.textContent = msg;
        errEl.classList.remove('d-none');
      } else {
        errEl.textContent = '';
        errEl.classList.add('d-none');
      }
    }

    function mapOptionsValueLabel(rows, valueKey, labelKey) {
      return (rows || []).map(function (r) {
        return {
          value: String(r[valueKey] != null ? r[valueKey] : (r.value != null ? r.value : '')),
          label: String(r[labelKey] != null ? r[labelKey] : (r.label != null ? r.label : '')),
        };
      });
    }

    async function openAltaInternacionModal(item) {
      var api = window.BioenlaceNativePage;
      var modal = getAltaInternacionModal();
      if (!api || !modal || !item || !item.id) return;

      altaInternacionId = item.id;
      var nameEl = document.getElementById('internacion-alta-paciente');
      if (nameEl) nameEl.textContent = item.nombre || ('Internación #' + item.id);
      var loadingEl = document.getElementById('internacion-alta-loading');
      var formEl = document.getElementById('internacion-alta-form');
      var submitBtn = document.getElementById('internacion-alta-submit');
      if (loadingEl) loadingEl.classList.remove('d-none');
      if (formEl) formEl.classList.add('d-none');
      if (submitBtn) submitBtn.disabled = true;
      showAltaInternacionError(null);
      modal.show();

      try {
        var url = api.apiV1Url('clinical/internacion/' + item.id + '/alta-formulario');
        var json = await api.fetchJson(url, {
          method: 'GET',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        var unwrapped = unwrapUiDefinitionPayload(json);
        var ctx = unwrapped.ctx;
        var tipos = mapOptionsValueLabel(ctx.tipos_alta || [], 'id', 'label');
        var plantillas = mapOptionsValueLabel(ctx.plantillas || [], 'id', 'nombre');
        if (!Array.isArray(ctx.tipos_alta)) {
          throw new Error((json && json.message) || 'No se pudo cargar el alta.');
        }

        var respEl = document.getElementById('internacion-alta-responsable');
        if (respEl) {
          respEl.textContent = ctx.responsable_nombre
            ? ('Responsable: ' + ctx.responsable_nombre)
            : '';
        }
        var fechaEl = document.getElementById('internacion-alta-fecha');
        var horaEl = document.getElementById('internacion-alta-hora');
        var now = new Date();
        if (fechaEl) {
          fechaEl.value = now.toISOString().slice(0, 10);
        }
        if (horaEl) {
          horaEl.value = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
        }
        fillSelectOptions(
          document.getElementById('internacion-alta-tipo'),
          tipos,
          '— Elegir tipo —'
        );
        fillSelectOptions(
          document.getElementById('internacion-alta-plantilla'),
          plantillas,
          '— Sin plantilla —'
        );
        var epicEl = document.getElementById('internacion-alta-epicrisis');
        if (epicEl) epicEl.value = '';
        ['internacion-alta-chk-med', 'internacion-alta-chk-ind', 'internacion-alta-chk-ped'].forEach(function (id) {
          var chk = document.getElementById(id);
          if (chk) chk.checked = false;
        });

        if (loadingEl) loadingEl.classList.add('d-none');
        if (formEl) formEl.classList.remove('d-none');
        if (submitBtn) submitBtn.disabled = false;
      } catch (e) {
        if (loadingEl) loadingEl.classList.add('d-none');
        showAltaInternacionError(e && e.message ? e.message : 'No se pudo cargar el alta.');
      }
    }

    async function onAltaPlantillaChange() {
      var api = window.BioenlaceNativePage;
      var sel = document.getElementById('internacion-alta-plantilla');
      var ta = document.getElementById('internacion-alta-epicrisis');
      if (!api || !sel || !ta || !altaInternacionId || !sel.value) return;
      try {
        var url = api.apiV1Url(
          'clinical/internacion/' + altaInternacionId
            + '/preview-plantilla-epicrisis?plantilla_id=' + encodeURIComponent(sel.value)
        );
        var json = await api.fetchJson(url, {
          method: 'GET',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (json && json.success && json.data && json.data.epicrisis) {
          ta.value = json.data.epicrisis;
        }
      } catch (e) {
        /* preview opcional */
      }
    }

    async function submitAltaInternacionModal() {
      var api = window.BioenlaceNativePage;
      if (!api || !altaInternacionId) return;
      var payload = {
        fecha_fin: (document.getElementById('internacion-alta-fecha') || {}).value || '',
        hora_fin: (document.getElementById('internacion-alta-hora') || {}).value || '',
        id_tipo_alta: (document.getElementById('internacion-alta-tipo') || {}).value || '',
        plantilla_id: (document.getElementById('internacion-alta-plantilla') || {}).value || '',
        epicrisis: (document.getElementById('internacion-alta-epicrisis') || {}).value || '',
        checklist_medicacion: document.getElementById('internacion-alta-chk-med')
          && document.getElementById('internacion-alta-chk-med').checked ? '1' : '',
        checklist_indicaciones: document.getElementById('internacion-alta-chk-ind')
          && document.getElementById('internacion-alta-chk-ind').checked ? '1' : '',
        checklist_pedidos: document.getElementById('internacion-alta-chk-ped')
          && document.getElementById('internacion-alta-chk-ped').checked ? '1' : '',
      };
      if (!payload.fecha_fin || !payload.hora_fin || !payload.id_tipo_alta || !payload.epicrisis.trim()) {
        showAltaInternacionError('Completá fecha, hora, tipo de alta y epicrisis.');
        return;
      }
      if (!payload.checklist_medicacion || !payload.checklist_indicaciones || !payload.checklist_pedidos) {
        showAltaInternacionError('Confirmá el checklist de egreso.');
        return;
      }
      var submitBtn = document.getElementById('internacion-alta-submit');
      if (submitBtn) submitBtn.disabled = true;
      showAltaInternacionError(null);
      try {
        var url = api.apiV1Url('clinical/internacion/' + altaInternacionId + '/alta-formulario');
        var json = await api.fetchJson(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify(payload),
        });
        var ok = json && (json.success === true || json.kind === 'ui_submit_result');
        if (json && json.kind === 'ui_submit_result' && json.success === false) {
          ok = false;
        }
        if (!ok && json && json.success === false) {
          throw new Error(json.message || 'No se pudo registrar el alta.');
        }
        var modal = getAltaInternacionModal();
        if (modal) modal.hide();
        await load();
      } catch (e) {
        showAltaInternacionError(e && e.message ? e.message : 'No se pudo registrar el alta.');
        if (submitBtn) submitBtn.disabled = false;
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
      var vitals = triage.vitals || {};
      var sysEl = document.getElementById('guardia-triage-bp-sys');
      var diaEl = document.getElementById('guardia-triage-bp-dia');
      var hrEl = document.getElementById('guardia-triage-hr');
      if (sysEl) sysEl.value = triageModalIsRetriage && vitals.bp_sys != null ? String(vitals.bp_sys) : '';
      if (diaEl) diaEl.value = triageModalIsRetriage && vitals.bp_dia != null ? String(vitals.bp_dia) : '';
      if (hrEl) hrEl.value = triageModalIsRetriage && vitals.hr != null ? String(vitals.hr) : '';
      if (window.BioenlaceTriageVitals) {
        window.BioenlaceTriageVitals.bindVitalInputs(document.getElementById('guardia-triage-modal'));
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
      var now = new Date();
      var fechaEl = document.getElementById('guardia-finalizar-fecha');
      var horaEl = document.getElementById('guardia-finalizar-hora');
      var notaEl = document.getElementById('guardia-finalizar-nota');
      if (fechaEl) {
        fechaEl.value = now.getFullYear() + '-'
          + String(now.getMonth() + 1).padStart(2, '0') + '-'
          + String(now.getDate()).padStart(2, '0');
      }
      if (horaEl) {
        horaEl.value = String(now.getHours()).padStart(2, '0') + ':'
          + String(now.getMinutes()).padStart(2, '0');
      }
      if (notaEl) notaEl.value = '';
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
      var fechaEl = document.getElementById('guardia-finalizar-fecha');
      var horaEl = document.getElementById('guardia-finalizar-hora');
      var notaEl = document.getElementById('guardia-finalizar-nota');
      var fecha = fechaEl ? String(fechaEl.value || '').trim() : '';
      var hora = horaEl ? String(horaEl.value || '').trim() : '';
      if (!fecha || !hora) {
        if (errEl) {
          errEl.textContent = 'Indicá fecha y hora.';
          errEl.classList.remove('d-none');
        }
        return;
      }
      if (submitBtn) submitBtn.disabled = true;
      try {
        var nota = notaEl ? String(notaEl.value || '').trim() : '';
        var url = api.apiV1Url('clinical/emergency-guardia/' + finalizarModalGuardiaId + '/egreso-formulario');
        var body = new URLSearchParams();
        body.set('modo_egreso', 'administrativo');
        body.set('fecha_fin', fecha);
        body.set('hora_fin', hora);
        if (nota) body.set('nota_administrativa', nota);
        var json = await api.fetchJson(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: body.toString(),
        });
        if (json.success === false) {
          throw new Error(json.message || 'Error al finalizar');
        }
        var modal = getFinalizarModal();
        if (modal) modal.hide();
        await loadGuardiaTablero(false);
      } catch (e) {
        if (errEl) {
          errEl.textContent = e && e.message ? e.message : 'No se pudo registrar el retiro.';
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
      var vitalsCheck = window.BioenlaceTriageVitals
        ? window.BioenlaceTriageVitals.validateVitals({
            bp_sys: (document.getElementById('guardia-triage-bp-sys') || {}).value,
            bp_dia: (document.getElementById('guardia-triage-bp-dia') || {}).value,
            hr: (document.getElementById('guardia-triage-hr') || {}).value,
          })
        : { ok: true, vitals: undefined };
      if (!vitalsCheck.ok) {
        if (errEl) {
          errEl.textContent = vitalsCheck.message;
          errEl.classList.remove('d-none');
        }
        return;
      }

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
            vitals: vitalsCheck.vitals,
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
        if (guardiaPuedeVerConsulta(g)) {
          var urlConsulta = verConsultaUrl(g.id_persona, null, g.encounter_id);
          if (urlConsulta) {
            window.location.href = urlConsulta;
            return;
          }
        }
        var yaEnAtencion = g.circuito_estado === 'en_atencion';
        var captura = null;
        if (!yaEnAtencion) {
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
          captura = json.data && json.data.captura_url;
        }
        if (!captura && g.id_persona) {
          captura = '/paciente/historia?id=' + encodeURIComponent(g.id_persona)
            + '&parent=GUARDIA&parent_id=' + encodeURIComponent(g.id);
        }
        if (captura) {
          window.location.href = captura;
        }
      } catch (e) {
        showError(errorEl, e && e.message ? e.message : 'No se pudo iniciar la atención.');
      }
    }

    function kpiGroupFromEmergencyIndicators(d) {
      if (!d) return null;
      return {
        title: '',
        items: [
          { label: 'Activos', value: String(d.activos != null ? d.activos : 0) },
          { label: 'Sin triage', value: String(d.sin_triage != null ? d.sin_triage : 0) },
          { label: 'Ingresos hoy', value: String(d.ingresos_hoy != null ? d.ingresos_hoy : 0) },
        ],
      };
    }

    function findStaffKpiGroupData(panel) {
      var sections = (panel && panel.sections) || [];
      for (var i = 0; i < sections.length; i++) {
        var sec = sections[i];
        if (sec && sec.kind === 'staff_kpi_group' && sec.data && Array.isArray(sec.data.items) && sec.data.items.length) {
          return sec.data;
        }
      }
      var indicators = findPanelSection(panel, 'emergency_indicators');
      return kpiGroupFromEmergencyIndicators(indicators && indicators.data);
    }

    function renderCoberturaActivaBanner(coberturaData, parentEl) {
      // Ya no se muestra banner superior: el aviso vive solo en el vacío centrado del listado.
      return;
    }

    function renderGuardiaTablero(items, kpiGroupData, coberturaData) {
      clearNode(container);

      var panelFrag = importTemplate('tpl-clinical-list-panel-wrap');
      var kpiSlot = panelFrag ? panelFrag.querySelector('[data-slot="kpi-sections"]') : null;
      var listSlot = panelFrag ? panelFrag.querySelector('[data-slot="list-content"]') : null;
      if (panelFrag) {
        container.appendChild(panelFrag);
      }
      var listTarget = listSlot || container;

      if (kpiSlot && kpiGroupData) {
        renderStaffKpiGroup(kpiSlot, kpiGroupData);
      }
      if (coberturaData) {
        renderCoberturaActivaBanner(coberturaData, listTarget);
      }

      var sessionCob = (coberturaData && coberturaData.session) ? coberturaData.session : {};
      if (sessionCob.tiene_cobertura === false) {
        var msgSin = (sessionCob.mensaje_sin_cobertura || '').toString().trim() ||
          'No tenés horario de plantel de guardia cargado. Configurá tus horarios en el Asistente («Configurar mis horarios») o pedile a coordinación / administración del centro que te los asigne.';
        showListadoEmpty(msgSin, listTarget);
        return;
      }

      if (!items || !items.length) {
        showListadoEmpty(msgEmptyGuardias, listTarget);
        return;
      }

      var wrapFrag = importTemplate('tpl-pacientes-guardias-wrap');
      if (!wrapFrag) return;
      var rowsSlot = wrapFrag.querySelector('[data-slot="guardias-rows"]');
      listTarget.appendChild(wrapFrag);

      items.forEach(function (g) {
        var itemFrag = importTemplate('tpl-paciente-guardia-row');
        if (!itemFrag) return;
        var row = itemFrag.firstElementChild;
        if (!row) return;
        fillGuardiaTableroRow(row, g);
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

    function renderStaffKpiGroup(wrapRoot, data) {
      if (!wrapRoot || !data || !Array.isArray(data.items) || !data.items.length) return;
      var groupFrag = importTemplate('tpl-staff-kpi-group');
      if (!groupFrag) return;
      var groupRoot = groupFrag.querySelector('[data-role="kpi-group"]');
      if (!groupRoot) return;
      var titleWrap = groupRoot.querySelector('[data-slot="kpi-title-wrap"]');
      var titleEl = groupRoot.querySelector('[data-field="title"]');
      var titleText = data.title != null ? String(data.title).trim() : '';
      if (titleWrap && titleEl) {
        if (titleText) {
          titleEl.textContent = titleText;
          titleWrap.classList.remove('d-none');
        } else {
          titleEl.textContent = '';
          titleWrap.classList.add('d-none');
        }
      }
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

    function badgeClassForIntent(intent) {
      switch (String(intent || '').toLowerCase()) {
        case 'danger':
          return 'bg-danger';
        case 'warning':
          return 'bg-warning text-dark';
        case 'success':
          return 'bg-success';
        case 'info':
          return 'bg-info text-dark';
        case 'primary':
          return 'bg-primary';
        default:
          return 'bg-secondary';
      }
    }

    function renderIntakeContextBlock(rootEl, intakeContext) {
      if (!rootEl) return;
      var ctx = intakeContext && typeof intakeContext === 'object' ? intakeContext : null;
      if (!ctx || (!(ctx.lines && ctx.lines.length) && !ctx.reference_encounter && !ctx.section_label)) {
        rootEl.classList.add('d-none');
        return;
      }
      rootEl.classList.remove('d-none');

      var titleEl = rootEl.querySelector('[data-field="intake-title"]');
      if (titleEl) {
        titleEl.textContent = ctx.section_label || 'Contexto de la solicitud';
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
          var valueEl = document.createElement('div');
          valueEl.className = 'small';
          valueEl.textContent = value;
          linesSlot.appendChild(buildIntakeSection(label, valueEl));
        });
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
          var metaParts = [];
          if (detail.headline) {
            metaParts.push(String(detail.headline));
          } else {
            var efectorNombre =
              detail.efector && detail.efector.nombre ? String(detail.efector.nombre) : '';
            if (efectorNombre) metaParts.push(efectorNombre);
            var profDisplay =
              detail.profesional && detail.profesional.display
                ? String(detail.profesional.display)
                : '';
            if (profDisplay) metaParts.push(profDisplay);
          }
          var narrative = String(detail.narrativeText || '').trim();
          if (narrative.length > 600) {
            narrative = narrative.slice(0, 600) + '…';
          }
          var bodyNode = document.createElement('div');
          bodyNode.className = 'small';
          if (metaParts.length) {
            var meta = document.createElement('div');
            meta.className = 'text-muted small mb-1';
            meta.textContent = metaParts.join(' · ');
            bodyNode.appendChild(meta);
          }
          if (narrative) {
            var narr = document.createElement('div');
            narr.textContent = narrative;
            bodyNode.appendChild(narr);
          }
          detailSlot.appendChild(
            buildIntakeSection(detail.title || 'Atención de referencia', bodyNode)
          );
        } else {
          detailSlot.classList.add('d-none');
        }
      }

      var linksSlot = rootEl.querySelector('[data-slot="intake-links"]');
      if (!linksSlot) return;
      clearNode(linksSlot);
      var references = Array.isArray(ctx.references) ? ctx.references : [];
      references.forEach(function (ref) {
        if (!ref || !ref.kind || ref.kind === 'reference_encounter') return;
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

    function buildIntakeSection(title, bodyNode) {
      var wrap = document.createElement('div');
      wrap.className = 'mb-3';
      var titleEl = document.createElement('div');
      titleEl.className = 'fw-semibold small';
      titleEl.textContent = title;
      wrap.appendChild(titleEl);
      var hr = document.createElement('hr');
      hr.className = 'my-1';
      wrap.appendChild(hr);
      if (bodyNode) {
        wrap.appendChild(bodyNode);
      }
      return wrap;
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
      var messagesBox = document.getElementById('async-chat-messages');
      if (messagesBox) {
        if (p.showMessageThread) {
          messagesBox.classList.remove('d-none');
        } else {
          messagesBox.classList.add('d-none');
          clearNode(messagesBox);
        }
      }
      if (resolveSlot) {
        clearNode(resolveSlot);
        if (p.showResolutionActions) {
          resolveSlot.classList.remove('d-none');
          p.resolutions.forEach(function (r) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-outline-primary w-100';
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
      var footer = document.getElementById('async-chat-footer');
      if (footer) {
        var composeVisible = compose && !compose.classList.contains('d-none');
        var resolveVisible = resolveSlot && !resolveSlot.classList.contains('d-none');
        if (composeVisible || resolveVisible) {
          footer.classList.remove('d-none');
        } else {
          footer.classList.add('d-none');
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
        var modal = getAsyncChatModal();
        if (modal) modal.hide();
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
      var scroll = document.getElementById('async-chat-scroll');
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
      var target = scroll || box;
      target.scrollTop = target.scrollHeight;
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
        renderAsyncChatMessages(
          asyncChatState.chatPolicy && asyncChatState.chatPolicy.showMessageThread === false
            ? []
            : messages
        );
        if (json.data && json.data.intake_context) {
          asyncChatState.intakeContext = json.data.intake_context;
          renderIntakeContextBlock(
            document.getElementById('async-chat-intake-context'),
            json.data.intake_context
          );
        }
        if (loading) loading.classList.add('d-none');
        var showThread =
          !asyncChatState.chatPolicy || asyncChatState.chatPolicy.showMessageThread !== false;
        if (box) {
          if (showThread) {
            box.classList.remove('d-none');
          } else {
            box.classList.add('d-none');
            clearNode(box);
          }
        }
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

    function openAsyncChat(item, canCompose) {
      if (!item || !item.encounter_id) return;
      asyncChatState.encounterId = item.encounter_id;
      asyncChatState.item = item;
      asyncChatState.isStaff = !!(item.paciente && item.paciente.nombre_completo);
      asyncChatState.canCompose = canCompose !== false;
      asyncChatState.chatPolicy = null;
      asyncChatState.intakeContext = null;
      var titleEl = document.getElementById('asyncChatModalLabel');
      if (titleEl) {
        var parts = [];
        if (item.paciente && item.paciente.nombre_completo) parts.push(item.paciente.nombre_completo);
        if (item.servicio) parts.push(item.servicio);
        titleEl.textContent = parts.length ? parts.join(' · ') : 'Consulta clínica por mensaje';
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
      var tipoBadge = colEl.querySelector('[data-field="solicitud-tipo"]');
      if (tipoBadge) {
        var tipo = item.solicitud_tipo ? String(item.solicitud_tipo).trim() : '';
        if (tipo) {
          tipoBadge.textContent = tipo;
          tipoBadge.classList.remove('d-none');
        } else {
          tipoBadge.textContent = '';
          tipoBadge.classList.add('d-none');
        }
      }
      var badge = colEl.querySelector('[data-field="estado-badge"]');
      if (badge) {
        badge.className = 'badge bg-secondary';
        if (item.status === 'planned') badge.className = 'badge bg-warning text-dark';
        if (item.status === 'in-progress') badge.className = 'badge bg-success';
        badge.textContent = item.status_label || item.status || '';
      }
      var prioridadBadge = colEl.querySelector('[data-field="prioridad-badge"]');
      if (prioridadBadge) {
        var prio = item.prioridad && typeof item.prioridad === 'object' ? item.prioridad : null;
        var prioLabel = prio && prio.label ? String(prio.label).trim() : '';
        var prioIntent = prio && prio.intent ? String(prio.intent).trim() : '';
        if (prioLabel) {
          prioridadBadge.textContent = prioLabel;
          prioridadBadge.className = 'badge ' + badgeClassForIntent(prioIntent || 'danger');
          prioridadBadge.classList.remove('d-none');
        } else {
          prioridadBadge.textContent = '';
          prioridadBadge.classList.add('d-none');
        }
      }
      var slaSlot = colEl.querySelector('[data-slot="sla-alerta"]');
      if (slaSlot && item.sla && item.sla.incumplido) {
        slaSlot.classList.remove('d-none');
        var slaBadge = slaSlot.querySelector('[data-field="sla-badge"]');
        if (slaBadge) {
          slaBadge.textContent = 'Plazo vencido (' + (item.sla.horas_objetivo || '') + ' h)';
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
      if (!targetEl || !data) {
        return;
      }
      var groups = Array.isArray(data.groups) ? data.groups : null;
      var items = Array.isArray(data.items) ? data.items : [];
      clearNode(targetEl);

      if ((!groups || !groups.length) && !items.length) {
        showListadoEmpty(
          data.empty_message || 'No hay consultas clínicas por mensaje pendientes.',
          targetEl
        );
        return;
      }

      var wrapFrag = importTemplate('tpl-async-bandeja-wrap');
      if (!wrapFrag) return;
      var wrapRoot = wrapFrag.querySelector('[data-role="async-bandeja-wrap"]');
      targetEl.appendChild(wrapFrag);
      var slaResumen = wrapRoot.querySelector('[data-field="sla-resumen"]');
      if (slaResumen && data.sla_incumplidos > 0) {
        slaResumen.textContent = data.sla_incumplidos + ' con plazo vencido';
        slaResumen.classList.remove('d-none');
      }
      var groupsSlot = wrapRoot.querySelector('[data-slot="async-groups"]');
      if (!groupsSlot) return;

      if (groups && groups.length) {
        groups.forEach(function (group) {
          renderAsyncBandejaGroup(groupsSlot, group);
        });
        return;
      }

      // Fallback: lista plana (clientes/API sin groups).
      renderAsyncBandejaGroup(groupsSlot, {
        title: data.title || 'Consultas clínicas por mensaje',
        items: items,
        empty_message: data.empty_message || '',
      });
    }

    function renderAsyncBandejaGroup(groupsSlot, group) {
      if (!groupsSlot || !group) return;
      var groupFrag = importTemplate('tpl-async-bandeja-group');
      if (!groupFrag) return;
      var groupRoot = groupFrag.querySelector('[data-role="async-group"]');
      var titleEl = groupRoot.querySelector('[data-field="titulo"]');
      if (titleEl) {
        titleEl.textContent = group.title || '';
      }
      var grid = groupRoot.querySelector('[data-slot="async-grid"]');
      var emptySlot = groupRoot.querySelector('[data-slot="empty"]');
      var groupItems = Array.isArray(group.items) ? group.items : [];
      if (!groupItems.length) {
        if (emptySlot) {
          emptySlot.classList.remove('d-none');
          showListadoEmpty(
            group.empty_message || 'Sin solicitudes en esta sección.',
            emptySlot
          );
        }
      } else if (grid) {
        groupItems.forEach(function (item) {
          var cardFrag = importTemplate('tpl-async-solicitud-card');
          if (!cardFrag) return;
          var col = cardFrag.firstElementChild;
          if (!col) return;
          fillAsyncSolicitudCard(col, item);
          grid.appendChild(col);
        });
      }
      groupsSlot.appendChild(groupFrag);
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
      if (!kpiSections.length && !asyncSec && !coberturaSec) {
        return { listTarget: container, asyncSlot: null, asyncSec: null };
      }
      clearNode(container);
      var wrapFrag = importTemplate('tpl-clinical-list-panel-wrap');
      if (!wrapFrag) {
        return { listTarget: container, asyncSlot: null, asyncSec: asyncSec || null };
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
        renderGuardiaTablero(
          items,
          findStaffKpiGroupData(panel),
          coberturaSec ? coberturaSec.data : null
        );
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
          var cobInpat = findPanelSection(panel, 'staff_cobertura_activa');
          var sessionInpat = cobInpat && cobInpat.data && cobInpat.data.session
            ? cobInpat.data.session
            : {};
          if (sessionInpat.tiene_cobertura === false) {
            var msgInpat = (sessionInpat.mensaje_sin_cobertura || '').toString().trim() ||
              'No tenés horario de plantel de piso cargado. Configurá tus horarios en el Asistente («Configurar mis horarios») o pedile a coordinación / administración del centro que te los asigne.';
            showListadoEmpty(msgInpat, panelParts.listTarget);
            applyPanelChrome(panel);
            return;
          }
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
        // Panel VR (u otro clinical_list) solo con bandeja async / KPIs.
        if (panelParts.asyncSec) {
          if (panelParts.listTarget) {
            clearNode(panelParts.listTarget);
          }
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
          if (boardSecPoll) {
            var itemsPoll = boardSecPoll.data ? boardSecPoll.data.items || [] : [];
            renderGuardiaTablero(
              itemsPoll,
              findStaffKpiGroupData(panel),
              coberturaSecPoll ? coberturaSecPoll.data : null
            );
          } else {
            renderFromPanel(panel);
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
        sections: 'staff_cobertura_activa,staff_guardia_kpis,emergency_board',
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
    var ingresoCamaSubmit = document.getElementById('guardia-ingreso-submit');
    if (ingresoCamaSubmit) {
      ingresoCamaSubmit.addEventListener('click', submitIngresoCamaModal);
    }
    var cambioCamaSubmit = document.getElementById('internacion-cambio-cama-submit');
    if (cambioCamaSubmit) {
      cambioCamaSubmit.addEventListener('click', submitCambioCamaModal);
    }
    var altaInternacionSubmit = document.getElementById('internacion-alta-submit');
    if (altaInternacionSubmit) {
      altaInternacionSubmit.addEventListener('click', submitAltaInternacionModal);
    }
    var altaPlantillaSel = document.getElementById('internacion-alta-plantilla');
    if (altaPlantillaSel) {
      altaPlantillaSel.addEventListener('change', onAltaPlantillaChange);
    }
    var asyncChatSend = document.getElementById('async-chat-send');
    if (asyncChatSend) {
      asyncChatSend.addEventListener('click', sendAsyncChatMessage);
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
