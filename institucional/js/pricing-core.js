/**
 * UI helpers for selecting a license plan. Prices are calculated by the API.
 */
(function (global) {
  'use strict';

  function formatAttentions(value) {
    try {
      return new Intl.NumberFormat('es-AR').format(value);
    } catch (error) {
      return String(value);
    }
  }

  function classRow(config, code) {
    return ((config && config.sellable_classes) || {})[code] || {};
  }

  function classIncludesAudio(config, code) {
    return !!classRow(config, code).audio_included;
  }

  function classAllowsVideollamada(config, code) {
    const row = classRow(config, code);
    return Object.prototype.hasOwnProperty.call(row, 'videollamada_allowed')
      ? !!row.videollamada_allowed
      : true;
  }

  function attentionVolumePresets(config) {
    const presets = (config && config.attention_volume_presets) || [];
    if (Array.isArray(presets) && presets.length) {
      return presets
        .map((preset) => {
          const attentions = Number(preset && preset.attentions);
          if (!(attentions > 0)) return null;
          return {
            attentions,
            hint: (preset && preset.hint) || '',
          };
        })
        .filter(Boolean);
    }
    const scale = (config && config.attention_volume_scale) || [];
    return (Array.isArray(scale) ? scale : [])
      .map(Number)
      .filter((attentions) => attentions > 0)
      .map((attentions) => ({ attentions, hint: '' }));
  }

  function defaultAttentions(config, profile) {
    const defaults = (config && config.defaults) || {};
    const presets = attentionVolumePresets(config);
    if (profile === 'CONSULTORIO') {
      return Number(defaults.consultorio_attentions) || presets[0]?.attentions || 200;
    }
    return (
      Number(defaults.clinica_attentions) ||
      presets[5]?.attentions ||
      presets[presets.length - 1]?.attentions ||
      5000
    );
  }

  function deriveMaxPesFromAttentions(config, attentions) {
    const reference = Number(
      config && config.reference_encounters_per_professional_month,
    );
    if (!(reference > 0)) {
      throw new Error('El catálogo no informa el volumen de referencia.');
    }
    return Math.max(1, Math.ceil(Number(attentions) / reference));
  }

  function readDomSelection(rowsRoot) {
    const selection = {
      classes: {},
      addons: { audio: true, videollamada: false },
    };
    if (!rowsRoot) return selection;
    rowsRoot.querySelectorAll('[data-class]').forEach((row) => {
      const code = row.getAttribute('data-class');
      const enabled = row.querySelector(
        'input[type="checkbox"][name^="class_"]',
      );
      const volume = row.querySelector(
        'input[data-attentions], select[data-attentions]',
      );
      if (!code || !enabled || !volume || !enabled.checked) return;
      const attentions = Math.max(0, parseInt(volume.value, 10) || 0);
      if (attentions > 0) selection.classes[code] = attentions;
      if (code === 'AMB') {
        const video = row.querySelector('input[data-addon="videollamada"]');
        selection.addons.videollamada = !!(video && video.checked);
      }
    });
    return selection;
  }

  function toSignupPlan(selection, config) {
    const plan = { classes: {} };
    Object.keys((selection && selection.classes) || {}).forEach((code) => {
      const attentions = Math.max(
        1,
        parseInt(selection.classes[code], 10) || 1,
      );
      plan.classes[code] = {
        attentions_per_month: attentions,
        max_pes: deriveMaxPesFromAttentions(config, attentions),
        dictado_incluido: true,
        videollamada_permitida:
          code === 'AMB' &&
          !!(selection.addons && selection.addons.videollamada),
      };
    });
    return plan;
  }

  global.BioenlacePricing = {
    formatAttentions,
    readDomSelection,
    toSignupPlan,
    classIncludesAudio,
    classAllowsVideollamada,
    attentionVolumePresets,
    defaultAttentions,
  };
})(typeof window !== 'undefined' ? window : this);
