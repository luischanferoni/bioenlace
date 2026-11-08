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

  function attentionVolumePresets(config, code) {
    const byClass = (config && config.attention_volume_presets) || {};
    const presets = byClass && byClass[code];
    if (!Array.isArray(presets)) return [];
    return presets
      .map((preset) => {
        const attentions = Number(preset && preset.attentions);
        if (!(attentions > 0)) return null;
        return {
          attentions,
          label: (preset && preset.label) || '',
          hint: (preset && preset.hint) || '',
        };
      })
      .filter(Boolean);
  }

  function defaultAttentions(config, code) {
    const defaults = (config && config.defaults && config.defaults.initial_attentions) || {};
    const initial =
      defaults && typeof defaults === 'object' ? Number(defaults[code]) : 0;
    if (initial > 0) return initial;
    const presets = attentionVolumePresets(config, code);
    return presets[0]?.attentions || 250;
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

  function toSignupPlan(selection) {
    const plan = { classes: {} };
    Object.keys((selection && selection.classes) || {}).forEach((code) => {
      const attentions = Math.max(
        1,
        parseInt(selection.classes[code], 10) || 1,
      );
      plan.classes[code] = {
        attentions_per_month: attentions,
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
