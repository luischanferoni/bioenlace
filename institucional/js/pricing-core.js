/**
 * Núcleo compartido del calculador de licencia (COGS + margen por tramo de PES).
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

  function listMarginOnCostPercent(config) {
    return Number((config && config.margin_on_cost_percent) || 0);
  }

  /**
   * Tramos por PES totales (suma AMB+EMER+IMP). Sin tramos → margen de lista.
   */
  function volumeDiscountTiers(config) {
    var tiers = (config && config.volume_discount_tiers) || [];
    return Array.isArray(tiers) ? tiers.slice() : [];
  }

  function tierForTotalPes(config, totalPes) {
    var n = Math.max(0, parseInt(totalPes, 10) || 0);
    var tiers = volumeDiscountTiers(config);
    if (!tiers.length) {
      return {
        id: 'lista',
        label: 'Lista',
        min_pes: 1,
        max_pes: null,
        margin_on_cost_percent: listMarginOnCostPercent(config),
        margin_after_iibb_ganancias_percent: null,
      };
    }
    var i;
    var tier;
    var fallback = tiers[0];
    for (i = 0; i < tiers.length; i++) {
      tier = tiers[i];
      var min = Number(tier.min_pes) || 0;
      var max = tier.max_pes == null || tier.max_pes === '' ? null : Number(tier.max_pes);
      if (n >= min && (max == null || n <= max)) {
        return tier;
      }
      if (n >= min) fallback = tier;
    }
    return fallback;
  }

  function marginOnCostPercentForTotalPes(config, totalPes) {
    var tier = tierForTotalPes(config, totalPes);
    if (tier && tier.margin_on_cost_percent != null) {
      return Number(tier.margin_on_cost_percent);
    }
    return listMarginOnCostPercent(config);
  }

  function totalPesFromSelection(selection) {
    selection = selection || {};
    var qtyByClass = selection.classes || {};
    var total = 0;
    Object.keys(qtyByClass).forEach(function (code) {
      total += Math.max(0, parseInt(qtyByClass[code], 10) || 0);
    });
    return total;
  }

  function referenceUnitCogs(config, audio, videollamada) {
    var cogs = (config && config.cogs_usd_per_professional_month) || {};
    var total = Number(cogs.base) || 0;
    // Videollamada: el transcript de la llamada alimenta §2/§4 → STT profesional una sola vez
    // (no sumar dictado + video como dos STT).
    if (audio || videollamada) total += Number(cogs.audio) || 0;
    if (videollamada) total += Number(cogs.videollamada) || 0;
    return total;
  }

  function unitCogsForClass(config, code, addons) {
    addons = addons || {};
    var video = classAllowsVideollamada(config, code) && !!addons.videollamada;
    var audio =
      classIncludesAudio(config, code) ||
      (code === 'AMB' && (!!addons.audio || video));
    return referenceUnitCogs(config, audio, video) * volumeScale(config, code);
  }

  /**
   * @param {number} [totalPes] PES totales del contrato; si se omite, usa margen de lista.
   */
  function unitPriceForClass(config, code, addons, totalPes) {
    var margin =
      totalPes == null || totalPes === ''
        ? listMarginOnCostPercent(config)
        : marginOnCostPercentForTotalPes(config, totalPes);
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
    var totalPes = totalPesFromSelection(selection);
    var tier = tierForTotalPes(config, totalPes);
    var margin = marginOnCostPercentForTotalPes(config, totalPes);
    var listMargin = listMarginOnCostPercent(config);
    var total = 0;
    var listTotal = 0;
    var lines = [];
    Object.keys((config && config.sellable_classes) || {}).forEach(function (code) {
      var qty = Math.max(0, parseInt(qtyByClass[code], 10) || 0);
      if (qty <= 0) return;
      var unit = unitPriceForClass(config, code, addons, totalPes);
      var listUnit = unitPriceForClass(config, code, addons);
      var line = qty * unit;
      var listLine = qty * listUnit;
      total += line;
      listTotal += listLine;
      lines.push({
        code: code,
        label: config.sellable_classes[code].label || code,
        qty: qty,
        unit: unit,
        listUnit: listUnit,
        line: line,
      });
    });
    var discountPercent =
      listTotal > 0 && total < listTotal
        ? Math.round(((listTotal - total) / listTotal) * 1000) / 10
        : 0;
    return {
      total: Math.round(total * 100) / 100,
      listTotal: Math.round(listTotal * 100) / 100,
      currency: (config && config.currency) || 'USD',
      lines: lines,
      totalPes: totalPes,
      tier: tier,
      marginOnCostPercent: margin,
      listMarginOnCostPercent: listMargin,
      discountPercent: discountPercent,
      formattedTotal: formatMoney(total, (config && config.currency) || 'USD'),
      formattedListTotal: formatMoney(listTotal, (config && config.currency) || 'USD'),
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
        var videoOn = !!(video && video.checked);
        sel.addons.videollamada = videoOn;
        sel.addons.audio = !!(audio && audio.checked) || videoOn;
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
          ? !!(selection.addons && (selection.addons.audio || selection.addons.videollamada))
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
    totalPesFromSelection: totalPesFromSelection,
    tierForTotalPes: tierForTotalPes,
    marginOnCostPercentForTotalPes: marginOnCostPercentForTotalPes,
    volumeDiscountTiers: volumeDiscountTiers,
  };
})(typeof window !== 'undefined' ? window : this);
