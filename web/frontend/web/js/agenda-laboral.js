/**
 * Agenda laboral embebible.
 *
 * Contrato:
 * - El HTML debe estar contenido en un root (HTMLElement) que tenga dentro:
 *   - [data-al-loading], [data-al-list], [data-al-error], [data-al-reload], [data-al-card-template]
 * - Requiere: jQuery + scheduler.js cargados en la página.
 *
 * Exposición:
 * - window.BioenlaceNativeComponents.agenda_laboral.init(rootEl)
 */
(function ($) {
  'use strict';

  var ACCURACY = 1;
  var DAY_FIELDS = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
  var DAY2_FIELDS = [
    'lunes_2',
    'martes_2',
    'miercoles_2',
    'jueves_2',
    'viernes_2',
    'sabado_2',
    'domingo_2',
  ];

  function apiHeaders() {
    if (typeof window.getBioenlaceApiClientHeaders === 'function') {
      return window.getBioenlaceApiClientHeaders();
    }
    return {};
  }

  function isDaySi(v) {
    if (v === true || v === 1) return true;
    var s = String(v || '').toUpperCase();
    return s === 'SI' || s === '1';
  }

  function normalizeTimeForInput(s) {
    if (s === null || s === undefined || s === '') return '';
    var str = String(s);
    return str.length >= 5 ? str.slice(0, 5) : str;
  }

  function timeToSeconds(hm) {
    if (!hm) return null;
    var p = String(hm).trim().split(':');
    var h = parseInt(p[0], 10);
    var m = parseInt(p[1] || '0', 10);
    if (!isFinite(h) || !isFinite(m)) return null;
    return h * 3600 + m * 60;
  }

  function jornadaFloorHoursFromRoot(cardEl) {
    var hiEl = cardEl.querySelector('.al_field[data-field="hora_inicio"]');
    var hfEl = cardEl.querySelector('.al_field[data-field="hora_fin"]');
    var hi = hiEl ? hiEl.value : '';
    var hf = hfEl ? hfEl.value : '';
    if (!hi || !hf) return 14;
    var a = timeToSeconds(hi);
    var b = timeToSeconds(hf);
    if (a === null || b === null) return 14;
    var diff = b - a;
    if (diff < 0) diff += 24 * 3600;
    return Math.floor(diff / 3600);
  }

  function calcularTiempoHint(horasInt, cantidadRaw) {
    var cantidad = Number(String(cantidadRaw || '').trim());
    if (!isFinite(cantidad) || cantidad === 0) return '—';
    var tiempo_x_paciente = horasInt / cantidad;
    var t_texto;
    if (tiempo_x_paciente >= 1) {
      var f = tiempo_x_paciente % 1;
      if (f !== 0) {
        tiempo_x_paciente = Math.floor(tiempo_x_paciente) + ':' + Math.round(f * 60);
      }
      if (tiempo_x_paciente == 1) {
        t_texto = tiempo_x_paciente + 'h';
      } else {
        t_texto = tiempo_x_paciente + 'hs';
      }
    } else {
      t_texto = Math.round((horasInt / cantidad) * 60) + 'min';
    }
    return t_texto;
  }

  function refreshJornadaAndHint(cardEl) {
    var inner = cardEl.querySelector('.al_agenda_card_inner');
    var hint = cardEl.querySelector('.al_cupo_hint');
    var cupo = cardEl.querySelector('.al_cupo_input');
    if (!inner || !hint || !cupo) return;
    var h = jornadaFloorHoursFromRoot(cardEl);
    inner.setAttribute('data-jornada-horas', String(h));
    hint.textContent = calcularTiempoHint(h, cupo.value);
  }

  /**
   * CSV almacenado en lunes_2..domingo_2: índices de celda (0..24*accuracy-1) y/o HH:mm (se toma la hora si accuracy=1).
   */
  function parseSlotsCsv(str, accuracy) {
    var max = 24 * accuracy;
    if (!str || !String(str).trim()) return [];
    return String(str)
      .split(',')
      .map(function (s) {
        return s.trim();
      })
      .filter(Boolean)
      .map(function (token) {
        if (/^\d+$/.test(token)) {
          var n = parseInt(token, 10);
          return n >= 0 && n < max ? n : null;
        }
        var m = token.match(/^(\d{1,2}):(\d{2})$/);
        if (m) {
          var h = parseInt(m[1], 10);
          var min = parseInt(m[2], 10);
          if (!isFinite(h) || !isFinite(min)) return null;
          if (accuracy === 1) {
            return h >= 0 && h < 24 ? h : null;
          }
          var slot = h * accuracy + Math.floor(min / (60 / accuracy));
          return slot >= 0 && slot < max ? slot : null;
        }
        return null;
      })
      .filter(function (x) {
        return x !== null;
      });
  }

  function slotsToCsv(arr) {
    if (!arr || !arr.length) return '';
    return arr
      .slice()
      .sort(function (a, b) {
        return a - b;
      })
      .join(',');
  }

  function readSchedulerDataFromCard($card) {
    var data = {};
    for (var i = 0; i < 7; i++) {
      var field = DAY2_FIELDS[i];
      var v = $card.find('.al_field[data-field="' + field + '"]').val() || '';
      data[i + 1] = parseSlotsCsv(v, ACCURACY);
    }
    return data;
  }

  function ensureSchedulerLocaleEs() {
    if ($.fn.scheduler.locales.es) return;
    $.fn.scheduler.locales.es = $.extend(true, {}, $.fn.scheduler.locales.en, {
      WEEK_DAYS: ['LUNES', 'MARTES', 'MIÉRCOLES', 'JUEVES', 'VIERNES', 'SÁBADO', 'DOMINGO'],
      DRAG_TIP: 'Arrastrá sobre las celdas para seleccionar franjas',
      TIME_TITLE: 'HORA',
      WEEK_TITLE: 'DÍA',
      RESET: 'Limpiar',
    });
  }

  function syncSchedulerWidget($card) {
    ensureSchedulerLocaleEs();
    var $table = $card.find('.al_scheduler_table');
    if (!$table.length) return;

    var data = readSchedulerDataFromCard($card);
    var onSelect = function () {
      var valores = $(this).scheduler('val');
      for (var i = 1; i <= 7; i++) {
        var arr = valores[i] ? valores[i].slice() : [];
        var s = slotsToCsv(arr);
        var field = DAY2_FIELDS[i - 1];
        var $el = $card.find('.al_field[data-field="' + field + '"]');
        if ($el.val() !== s) {
          $el.val(s);
          $el.trigger('change');
        }
      }
    };

    if ($table.data('scheduler')) {
      $table.scheduler('val', data);
      return;
    }

    $table.scheduler({
      locale: 'es',
      accuracy: ACCURACY,
      data: data,
      onSelect: onSelect,
    });
  }

  function normalizeValue(field, el) {
    if (field === 'acepta_consultas_online') {
      return !!el.checked;
    }
    if (DAY_FIELDS.indexOf(field) >= 0) {
      return !!el.checked;
    }
    if (field === 'id_tipo_dia') {
      var v = el.value;
      if (v === '' || v === null) return null;
      var n = parseInt(v, 10);
      return isFinite(n) ? n : null;
    }
    if (field === 'cupo_pacientes' || field === 'duracion_slot_minutos') {
      var raw = String(el.value || '').trim();
      if (raw === '') return null;
      var num = Number(raw);
      return isFinite(num) ? num : null;
    }
    if (
      field === 'fecha_inicio' ||
      field === 'fecha_fin' ||
      field === 'hora_inicio' ||
      field === 'hora_fin'
    ) {
      var r = String(el.value || '').trim();
      return r === '' ? null : r;
    }
    return String(el.value || '');
  }

  function getServiceLabel(item) {
    try {
      var rs = item.rrhhServicioAsignado;
      var svc = rs && rs.servicio ? rs.servicio : null;
      if (svc && svc.nombre) return String(svc.nombre);
    } catch (e) {}
    return 'Servicio';
  }

  function setStatus(cardEl, text, kind) {
    var s = cardEl.querySelector('.al_status');
    if (!s) return;
    s.textContent = text || '';
    s.classList.remove('text-muted', 'text-success', 'text-danger', 'text-warning');
    if (kind === 'ok') s.classList.add('text-success');
    else if (kind === 'err') s.classList.add('text-danger');
    else if (kind === 'warn') s.classList.add('text-warning');
    else s.classList.add('text-muted');
  }

  function fillCard(cardEl, item) {
    var serviceEl = cardEl.querySelector('.al_service');
    if (serviceEl) serviceEl.textContent = getServiceLabel(item);

    var fields = cardEl.querySelectorAll('.al_field');
    fields.forEach(function (input) {
      var f = input.getAttribute('data-field');
      if (!f) return;
      if (f === 'acepta_consultas_online') {
        input.checked = !!item[f];
      } else if (DAY_FIELDS.indexOf(f) >= 0) {
        input.checked = isDaySi(item[f]);
      } else if (f === 'id_tipo_dia') {
        var v = item[f];
        input.value = v !== null && v !== undefined && v !== '' ? String(v) : '';
      } else if (f === 'hora_inicio' || f === 'hora_fin') {
        input.value = normalizeTimeForInput(item[f]);
      } else {
        input.value = item[f] === null || item[f] === undefined ? '' : String(item[f]);
      }
    });
    refreshJornadaAndHint(cardEl);
  }

  function attachAutosave(cardEl, item) {
    var id = item.id_agenda_rrhh || item.id || null;
    if (!id) return;

    var timers = new Map();
    var $card = $(cardEl);

    async function saveField(field, value) {
      setStatus(cardEl, 'Guardando…', 'warn');
      var url = '/api/v1/agenda/actualizar/' + encodeURIComponent(String(id));
      var body = {};
      body[field] = value;
      try {
        var resp = await fetch(url, {
          method: 'PATCH',
          headers: Object.assign({ 'Content-Type': 'application/json' }, apiHeaders()),
          body: JSON.stringify(body),
        });
        var payload = await resp.json().catch(function () {
          return null;
        });
        if (!resp.ok || !payload || payload.success !== true) {
          var msg =
            payload && (payload.message || payload.error)
              ? payload.message || payload.error
              : 'HTTP ' + resp.status;
          setStatus(cardEl, msg, 'err');
          return;
        }
        setStatus(cardEl, 'Guardado', 'ok');
      } catch (e) {
        setStatus(cardEl, 'Error de red', 'err');
      }
    }

    function scheduleFieldSave(target) {
      var field = target.getAttribute('data-field');
      if (!field) return;
      var immediate =
        target.type === 'checkbox' ||
        target.tagName === 'SELECT' ||
        target.type === 'date' ||
        target.type === 'time';
      var run = function () {
        timers.delete(field);
        saveField(field, normalizeValue(field, target));
      };
      if (immediate) {
        if (timers.has(field)) clearTimeout(timers.get(field));
        run();
      } else {
        if (timers.has(field)) clearTimeout(timers.get(field));
        timers.set(
          field,
          setTimeout(run, 450)
        );
      }
    }

    cardEl.addEventListener('input', function (ev) {
      var target = ev.target;
      if (!target || !target.classList || !target.classList.contains('al_field')) return;
      if (target.type === 'checkbox') return;
      var field = target.getAttribute('data-field');
      if (field === 'hora_inicio' || field === 'hora_fin' || field === 'cupo_pacientes') {
        refreshJornadaAndHint(cardEl);
      }
      scheduleFieldSave(target);
    });

    cardEl.addEventListener('change', function (ev) {
      var target = ev.target;
      if (!target || !target.classList || !target.classList.contains('al_field')) return;
      var field = target.getAttribute('data-field');
      if (field === 'hora_inicio' || field === 'hora_fin' || field === 'cupo_pacientes') {
        refreshJornadaAndHint(cardEl);
      }
      scheduleFieldSave(target);
    });
  }

  function initAgendaLaboralEmbed(rootEl) {
    var root = rootEl && rootEl.nodeType === 1 ? rootEl : document;
    var elLoading = root.querySelector('[data-al-loading]');
    var elList = root.querySelector('[data-al-list]');
    var elError = root.querySelector('[data-al-error]');
    var btnReload = root.querySelector('[data-al-reload]');
    var tpl = root.querySelector('[data-al-card-template]');

    if (!elList || !tpl) return;

    function showError(msg) {
      elError.textContent = msg || 'Error';
      elError.classList.remove('d-none');
    }

    function clearError() {
      elError.textContent = '';
      elError.classList.add('d-none');
    }

    function setLoading(isLoading) {
      elLoading.classList.toggle('d-none', !isLoading);
      elList.classList.toggle('d-none', isLoading);
    }

    function render(items) {
      elList.innerHTML = '';
      (items || []).forEach(function (item) {
        var node = document.importNode(tpl.content, true);
        var col = node.querySelector('.col-12');
        if (!col) return;
        fillCard(col, item);
        setStatus(col, '', 'muted');
        attachAutosave(col, item);
        syncSchedulerWidget($(col));
        elList.appendChild(node);
      });
    }

    async function load() {
      clearError();
      setLoading(true);
      try {
        var resp = await fetch('/api/v1/agenda/listar?per-page=100', { headers: apiHeaders() });
        var payload = await resp.json();
        if (!resp.ok || !payload || payload.success !== true) {
          var msg =
            payload && (payload.message || payload.error)
              ? payload.message || payload.error
              : 'HTTP ' + resp.status;
          throw new Error(msg);
        }
        var items = payload.items || payload.data || [];
        render(items);
        setLoading(false);
      } catch (e) {
        setLoading(true);
        showError(e && e.message ? e.message : 'No se pudieron cargar las agendas.');
      }
    }

    if (btnReload) btnReload.addEventListener('click', load);
    load();
  }

  window.BioenlaceNativeComponents = window.BioenlaceNativeComponents || {};
  window.BioenlaceNativeComponents.agenda_laboral = {
    init: initAgendaLaboralEmbed,
  };
})(jQuery);
