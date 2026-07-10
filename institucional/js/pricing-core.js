/**
 * Núcleo compartido del calculador de licencia (COGS + margen).
 * Usado por pricing-calculator.js y signup.js.
 */
(function (global) {
  'use strict';

  function formatMoney(n, currency) {
    try {
      return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: currency || 'USD',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
      }).format(n);
    } catch (e) {
      return (currency || 'USD') + ' ' + Math.round(n * 100) / 100;
    }
  }

  function referenceEncounters(config) {
    var n = Number((config && config.reference_encounters_per_professional_month) || 400);
    return n > 0 ? n : 400;
  }

  function classRow(config, code) {
    return ((config && config.sellable_classes) || {})[code] || {};
  }

  function volumeScale(config, code) {
    var enc = Number(classRow(config, code).encounters_per_professional_month) || referenceEncounters(config);
    return enc / referenceEncounters(config);
  }

  function classIncludesAudio(config, code) {
    return !!classRow(config, code).audio_included;
  }

  function classAllowsVideollamada(config, code) {
    var row = classRow(config, code);
    if (Object.prototype.hasOwnProperty.call(row, 'videollamada_allowed')) {
      return !!row.videollamada_allowed;
    }
    return true;
  }

  function referenceUnitCogs(config, audio, videollamada) {
    var cogs = (config && config.cogs_usd_per_professional_month) || {};
    var total = Number(cogs.base) || 0;
    if (audio) total += Number(cogs.audio) || 0;
    if (videollamada) total += Number(cogs.videollamada) || 0;
    return total;
  }

  function unitCogsForClass(config, code, addons) {
    addons = addons || {};
    var audio = classIncludesAudio(config, code) || (code === 'AMB' && !!addons.audio);
    var video = classAllowsVideollamada(config, code) && !!addons.videollamada;
    return referenceUnitCogs(config, audio, video) * volumeScale(config, code);
  }

  function unitPriceForClass(config, code, addons) {
    var margin = Number((config && config.margin_on_cost_percent) || 0);
    return Math.round(unitCogsForClass(config, code, addons) * (1 + margin / 100) * 100) / 100;
  }

  /**
   * @param {object} config
   * @param {{classes: Object<string, number>, addons?: {audio?: boolean, videollamada?: boolean}}} selection
   */
  function estimate(config, selection) {
    selection = selection || {};
    var qtyByClass = selection.classes || {};
    var addons = selection.addons || {};
    var total = 0;
    var lines = [];
    Object.keys((config && config.sellable_classes) || {}).forEach(function (code) {
      var qty = Math.max(0, parseInt(qtyByClass[code], 10) || 0);
      if (qty <= 0) return;
      var unit = unitPriceForClass(config, code, addons);
      var line = qty * unit;
      total += line;
      lines.push({
        code: code,
        label: config.sellable_classes[code].label || code,
        qty: qty,
        unit: unit,
        line: line,
      });
    });
    return {
      total: Math.round(total * 100) / 100,
      currency: (config && config.currency) || 'USD',
      lines: lines,
      formattedTotal: formatMoney(total, (config && config.currency) || 'USD'),
    };
  }

  /**
   * Lee la UI del calculador (#pricing-rows).
   */
  function readDomSelection(rowsRoot) {
    var sel = { classes: {}, addons: { audio: false, videollamada: false } };
    if (!rowsRoot) return sel;
    rowsRoot.querySelectorAll('[data-class]').forEach(function (row) {
      var code = row.getAttribute('data-class');
      var enabled = row.querySelector('input[type="checkbox"][name^="class_"]');
      var qty = row.querySelector('input[type="number"]');
      if (!code || !enabled || !qty || !enabled.checked) return;
      var n = Math.max(0, parseInt(qty.value, 10) || 0);
      if (n > 0) sel.classes[code] = n;
      if (code === 'AMB') {
        var audio = row.querySelector('input[data-addon="audio"]');
        var video = row.querySelector('input[data-addon="videollamada"]');
        sel.addons.audio = !!(audio && audio.checked);
        sel.addons.videollamada = !!(video && video.checked);
      }
    });
    return sel;
  }

  /**
   * Convierte selección del calculador al payload de alta (plan.classes).
   */
  function toSignupPlan(selection) {
    selection = selection || { classes: {}, addons: {} };
    var plan = { classes: {} };
    Object.keys(selection.classes || {}).forEach(function (code) {
      var qty = Math.max(1, parseInt(selection.classes[code], 10) || 1);
      plan.classes[code] = {
        max_pes: qty,
        dictado_incluido: code === 'AMB'
          ? !!(selection.addons && selection.addons.audio)
          : true,
        videollamada_permitida: code === 'AMB'
          ? !!(selection.addons && selection.addons.videollamada)
          : false,
      };
    });
    return plan;
  }

  global.BioenlacePricing = {
    formatMoney: formatMoney,
    unitPriceForClass: unitPriceForClass,
    estimate: estimate,
    readDomSelection: readDomSelection,
    toSignupPlan: toSignupPlan,
    classIncludesAudio: classIncludesAudio,
    classAllowsVideollamada: classAllowsVideollamada,
  };
})(typeof window !== 'undefined' ? window : this);
