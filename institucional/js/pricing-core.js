/**
 * Núcleo compartido del calculador de licencia (COGS por atención + margen por volumen).
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

  function formatAttentions(n) {
    try {
      return new Intl.NumberFormat('es-AR').format(n);
    } catch (e) {
      return String(n);
    }
  }

  function classRow(config, code) {
    return ((config && config.sellable_classes) || {})[code] || {};
  }

  function classIncludesAudio(config, code) {
    return !!classRow(config, code).audio_included;
  }

  function classIncludesPatientChat(config, code) {
    var row = classRow(config, code);
    if (Object.prototype.hasOwnProperty.call(row, 'includes_patient_chat')) {
      return !!row.includes_patient_chat;
    }
    return code === 'AMB';
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

  function attentionVolumePresets(config) {
    var presets = (config && config.attention_volume_presets) || [];
    if (Array.isArray(presets) && presets.length) {
      return presets
        .map(function (p) {
          var n = Number(p && p.attentions);
          if (!(n > 0)) return null;
          return {
            attentions: n,
            label: (p && p.label) || formatAttentions(n),
            hint: (p && p.hint) || '',
          };
        })
        .filter(Boolean);
    }
    var scale = (config && config.attention_volume_scale) || [];
    return (Array.isArray(scale) ? scale : [])
      .map(Number)
      .filter(function (n) { return n > 0; })
      .map(function (n) {
        return { attentions: n, label: formatAttentions(n) + ' / mes', hint: '' };
      });
  }

  function attentionVolumeScale(config) {
    return attentionVolumePresets(config).map(function (p) { return p.attentions; });
  }

  function defaultAttentions(config, perfil) {
    var defaults = (config && config.defaults) || {};
    var scale = attentionVolumeScale(config);
    if (perfil === 'CONSULTORIO') {
      return Number(defaults.consultorio_attentions) || scale[0] || 200;
    }
    return Number(defaults.clinica_attentions) || scale[5] || scale[scale.length - 1] || 5000;
  }

  function findVolumePreset(config, attentions) {
    var n = Math.max(0, parseInt(attentions, 10) || 0);
    var presets = attentionVolumePresets(config);
    var i;
    for (i = 0; i < presets.length; i++) {
      if (presets[i].attentions === n) return presets[i];
    }
    return null;
  }

  function formatVolumeChoice(config, attentions) {
    var preset = findVolumePreset(config, attentions);
    if (preset) {
      return preset.label + ' · ' + formatAttentions(preset.attentions) + '/mes';
    }
    return formatAttentions(attentions) + ' / mes';
  }

  function volumeDiscountTiers(config) {
    var tiers = (config && config.volume_discount_tiers) || [];
    return Array.isArray(tiers) ? tiers.slice() : [];
  }

  function tierForTotalAttentions(config, totalAttentions) {
    var n = Math.max(0, parseInt(totalAttentions, 10) || 0);
    var tiers = volumeDiscountTiers(config);
    if (!tiers.length) {
      return {
        id: 'lista',
        label: 'Precio base',
        min_attentions: 1,
        max_attentions: null,
        margin_on_cost_percent: listMarginOnCostPercent(config),
        discount_vs_list_percent: 0,
      };
    }
    var i;
    var tier;
    var fallback = tiers[0];
    for (i = 0; i < tiers.length; i++) {
      tier = tiers[i];
      var min = Number(tier.min_attentions != null ? tier.min_attentions : tier.min_pes) || 0;
      var maxRaw = tier.max_attentions != null ? tier.max_attentions : tier.max_pes;
      var max = maxRaw == null || maxRaw === '' ? null : Number(maxRaw);
      if (n >= min && (max == null || n <= max)) {
        return tier;
      }
      if (n >= min) fallback = tier;
    }
    return fallback;
  }

  function discountVsListPercent(config, tier) {
    if (!tier) return 0;
    if (tier.discount_vs_list_percent != null && tier.discount_vs_list_percent !== '') {
      return Number(tier.discount_vs_list_percent) || 0;
    }
    var listMargin = listMarginOnCostPercent(config);
    var tierMargin = Number(tier.margin_on_cost_percent);
    if (!(listMargin > 0) || !(tierMargin >= 0)) return 0;
    var listFactor = 1 + listMargin / 100;
    var tierFactor = 1 + tierMargin / 100;
    if (listFactor <= 0 || tierFactor >= listFactor) return 0;
    return Math.round((1 - tierFactor / listFactor) * 100);
  }

  function nextVolumeStep(config, totalAttentions) {
    var n = Math.max(0, parseInt(totalAttentions, 10) || 0);
    var tiers = volumeDiscountTiers(config);
    var i;
    var tier;
    for (i = 0; i < tiers.length; i++) {
      tier = tiers[i];
      var min = Number(tier.min_attentions != null ? tier.min_attentions : tier.min_pes) || 0;
      if (min > n) {
        return {
          attentionsNeeded: min - n,
          discountPercent: discountVsListPercent(config, tier),
          tier: tier,
        };
      }
    }
    return null;
  }

  function marginOnCostPercentForTotalAttentions(config, totalAttentions) {
    var tier = tierForTotalAttentions(config, totalAttentions);
    if (tier && tier.margin_on_cost_percent != null) {
      return Number(tier.margin_on_cost_percent);
    }
    return listMarginOnCostPercent(config);
  }

  function totalAttentionsFromSelection(selection) {
    selection = selection || {};
    var qtyByClass = selection.classes || {};
    var total = 0;
    Object.keys(qtyByClass).forEach(function (code) {
      total += Math.max(0, parseInt(qtyByClass[code], 10) || 0);
    });
    return total;
  }

  function unitCogsForClass(config, code, addons) {
    addons = addons || {};
    var cogs = (config && config.cogs_usd_per_encounter) || {};
    var video = classAllowsVideollamada(config, code) && !!addons.videollamada;
    // Dictado incluido en todas las clases vendibles (audio_included).
    var audio = classIncludesAudio(config, code) || video;
    var total = Number(cogs.motivos_audio) || 0;
    total += Number(cogs.captura_ia) || 0;
    if (classIncludesPatientChat(config, code)) {
      total += Number(cogs.patient_chat_amb) || 0;
    }
    if (audio || video) {
      total += Number(cogs.dictado_stt) || 0;
    }
    if (video) {
      total += Number(cogs.videollamada) || 0;
    }
    return total;
  }

  /**
   * @param {number} [totalAttentions] atenciones totales del contrato; si se omite, margen de lista.
   */
  function unitPriceForClass(config, code, addons, totalAttentions) {
    var margin =
      totalAttentions == null || totalAttentions === ''
        ? listMarginOnCostPercent(config)
        : marginOnCostPercentForTotalAttentions(config, totalAttentions);
    return Math.round(unitCogsForClass(config, code, addons) * (1 + margin / 100) * 10000) / 10000;
  }

  function referenceEncounters(config) {
    var n = Number((config && config.reference_encounters_per_professional_month) || 400);
    return n > 0 ? n : 400;
  }

  function deriveMaxPesFromAttentions(config, attentions) {
    var qty = Math.max(0, parseInt(attentions, 10) || 0);
    if (qty <= 0) return 0;
    return Math.max(1, Math.ceil(qty / referenceEncounters(config)));
  }

  /**
   * @param {object} config
   * @param {{classes: Object<string, number>, addons?: {audio?: boolean, videollamada?: boolean}}} selection
   *   classes[code] = atenciones / mes
   */
  function estimate(config, selection) {
    selection = selection || {};
    var qtyByClass = selection.classes || {};
    var addons = selection.addons || {};
    var totalAttentions = totalAttentionsFromSelection(selection);
    var tier = tierForTotalAttentions(config, totalAttentions);
    var margin = marginOnCostPercentForTotalAttentions(config, totalAttentions);
    var listMargin = listMarginOnCostPercent(config);
    var total = 0;
    var listTotal = 0;
    var lines = [];
    Object.keys((config && config.sellable_classes) || {}).forEach(function (code) {
      var qty = Math.max(0, parseInt(qtyByClass[code], 10) || 0);
      if (qty <= 0) return;
      var unit = unitPriceForClass(config, code, addons, totalAttentions);
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
        line: Math.round(line * 100) / 100,
      });
    });
    var discountPercent =
      listTotal > 0 && total < listTotal
        ? Math.round(((listTotal - total) / listTotal) * 1000) / 10
        : discountVsListPercent(config, tier);
    var nextStep = nextVolumeStep(config, totalAttentions);
    return {
      total: Math.round(total * 100) / 100,
      listTotal: Math.round(listTotal * 100) / 100,
      currency: (config && config.currency) || 'USD',
      lines: lines,
      totalAttentions: totalAttentions,
      totalPes: totalAttentions,
      tier: tier,
      marginOnCostPercent: margin,
      listMarginOnCostPercent: listMargin,
      discountPercent: discountPercent,
      nextStep: nextStep,
      formattedTotal: formatMoney(total, (config && config.currency) || 'USD'),
      formattedListTotal: formatMoney(listTotal, (config && config.currency) || 'USD'),
    };
  }

  function readDomSelection(rowsRoot) {
    var sel = { classes: {}, addons: { audio: true, videollamada: false } };
    if (!rowsRoot) return sel;
    rowsRoot.querySelectorAll('[data-class]').forEach(function (row) {
      var code = row.getAttribute('data-class');
      var enabled = row.querySelector('input[type="checkbox"][name^="class_"]');
      var qty = row.querySelector('input[data-attentions], select[data-attentions]');
      if (!code || !enabled || !qty || !enabled.checked) return;
      var n = Math.max(0, parseInt(qty.value, 10) || 0);
      if (n > 0) sel.classes[code] = n;
      if (code === 'AMB') {
        var video = row.querySelector('input[data-addon="videollamada"]');
        var videoOn = !!(video && video.checked);
        sel.addons.videollamada = videoOn;
        sel.addons.audio = true;
      }
    });
    return sel;
  }

  function toSignupPlan(selection, config) {
    selection = selection || { classes: {}, addons: {} };
    var plan = { classes: {} };
    Object.keys(selection.classes || {}).forEach(function (code) {
      var attentions = Math.max(1, parseInt(selection.classes[code], 10) || 1);
      plan.classes[code] = {
        attentions_per_month: attentions,
        max_pes: deriveMaxPesFromAttentions(config, attentions),
        dictado_incluido: true,
        videollamada_permitida: code === 'AMB'
          ? !!(selection.addons && selection.addons.videollamada)
          : false,
      };
    });
    return plan;
  }

  global.BioenlacePricing = {
    formatMoney: formatMoney,
    formatAttentions: formatAttentions,
    formatVolumeChoice: formatVolumeChoice,
    unitPriceForClass: unitPriceForClass,
    unitCogsForClass: unitCogsForClass,
    estimate: estimate,
    readDomSelection: readDomSelection,
    toSignupPlan: toSignupPlan,
    classIncludesAudio: classIncludesAudio,
    classAllowsVideollamada: classAllowsVideollamada,
    classIncludesPatientChat: classIncludesPatientChat,
    totalAttentionsFromSelection: totalAttentionsFromSelection,
    totalPesFromSelection: totalAttentionsFromSelection,
    tierForTotalAttentions: tierForTotalAttentions,
    tierForTotalPes: tierForTotalAttentions,
    marginOnCostPercentForTotalAttentions: marginOnCostPercentForTotalAttentions,
    marginOnCostPercentForTotalPes: marginOnCostPercentForTotalAttentions,
    volumeDiscountTiers: volumeDiscountTiers,
    attentionVolumeScale: attentionVolumeScale,
    attentionVolumePresets: attentionVolumePresets,
    defaultAttentions: defaultAttentions,
    findVolumePreset: findVolumePreset,
    discountVsListPercent: discountVsListPercent,
    nextVolumeStep: nextVolumeStep,
    deriveMaxPesFromAttentions: deriveMaxPesFromAttentions,
  };
})(typeof window !== 'undefined' ? window : this);
