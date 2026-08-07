/**
 * Máscara y validación cliente de SV de triage (TA / FC).
 * Rangos alineados con GuardiaTriageVitalsValidator (PHP).
 */
(function (global) {
  'use strict';

  var RANGES = {
    bp_sys: { min: 50, max: 250, label: 'TA sistólica' },
    bp_dia: { min: 30, max: 150, label: 'TA diastólica' },
    hr: { min: 20, max: 250, label: 'FC' },
  };

  function digitsOnlyMax3(raw) {
    return String(raw == null ? '' : raw).replace(/\D/g, '').slice(0, 3);
  }

  function bindVitalInput(el) {
    if (!el || el.dataset.triageVitalBound === '1') return;
    el.dataset.triageVitalBound = '1';
    el.setAttribute('inputmode', 'numeric');
    el.setAttribute('pattern', '[0-9]{2,3}');
    el.setAttribute('maxlength', '3');
    el.setAttribute('autocomplete', 'off');
    el.addEventListener('input', function () {
      var next = digitsOnlyMax3(el.value);
      if (el.value !== next) el.value = next;
    });
    el.addEventListener('paste', function (ev) {
      ev.preventDefault();
      var text = (ev.clipboardData || window.clipboardData).getData('text') || '';
      el.value = digitsOnlyMax3(text);
    });
  }

  function bindVitalInputs(root) {
    if (!root) return;
    root.querySelectorAll('[data-triage-vital]').forEach(bindVitalInput);
  }

  /**
   * @param {{bp_sys?: string|number, bp_dia?: string|number, hr?: string|number}} values
   * @returns {{ ok: true, vitals: object|undefined }|{ ok: false, message: string }}
   */
  function validateVitals(values) {
    values = values || {};
    var out = {};
    var keys = ['bp_sys', 'bp_dia', 'hr'];
    for (var i = 0; i < keys.length; i++) {
      var key = keys[i];
      var spec = RANGES[key];
      var raw = values[key];
      if (raw == null || String(raw).trim() === '') continue;
      var s = digitsOnlyMax3(raw);
      if (!/^\d{2,3}$/.test(s)) {
        return { ok: false, message: spec.label + ': ingresá un entero de 2 o 3 dígitos.' };
      }
      var n = parseInt(s, 10);
      if (n < spec.min || n > spec.max) {
        return {
          ok: false,
          message: spec.label + ' debe estar entre ' + spec.min + ' y ' + spec.max + '.',
        };
      }
      out[key] = n;
    }
    if (out.bp_sys != null && out.bp_dia != null && out.bp_sys <= out.bp_dia) {
      return { ok: false, message: 'TA sistólica debe ser mayor que la diastólica.' };
    }
    return { ok: true, vitals: Object.keys(out).length ? out : undefined };
  }

  function readVitalsFromForm(form) {
    if (!form) return {};
    function val(name) {
      var el = form.querySelector('[name="' + name + '"], [data-triage-vital="' + name + '"]');
      return el ? el.value : '';
    }
    return {
      bp_sys: val('bp_sys'),
      bp_dia: val('bp_dia'),
      hr: val('hr'),
    };
  }

  global.BioenlaceTriageVitals = {
    RANGES: RANGES,
    digitsOnlyMax3: digitsOnlyMax3,
    bindVitalInput: bindVitalInput,
    bindVitalInputs: bindVitalInputs,
    validateVitals: validateVitals,
    readVitalsFromForm: readVitalsFromForm,
  };
})(window);
