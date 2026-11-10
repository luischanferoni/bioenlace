/**
 * UI helpers for selecting a license plan. Prices are calculated by the API.
 */
(function (global) {
  'use strict';

  const ADDON_INCLUDED = 'included';
  const ADDON_OPTIONAL = 'optional';
  const ADDON_UNAVAILABLE = 'unavailable';

  function formatAttentions(value) {
    try {
      return new Intl.NumberFormat('es-AR').format(value);
    } catch (error) {
      return String(value);
    }
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

  function normalizeAddonState(state) {
    const value = String(state || '').trim().toLowerCase();
    if (value === ADDON_INCLUDED || value === ADDON_OPTIONAL) return value;
    return ADDON_UNAVAILABLE;
  }

  function addonsForClass(config, code) {
    const addons = (config && config.addons) || {};
    return Object.keys(addons).map((id) => {
      const addon = addons[id] || {};
      const label = addon.label || id;
      return {
        id,
        label,
        includedLabel: addon.included_label || label,
        unavailableLabel: addon.unavailable_label || '',
        planField: addon.plan_field || '',
        state: normalizeAddonState((addon.classes || {})[code]),
      };
    });
  }

  function readDomSelection(rowsRoot) {
    const selection = { classes: {}, addons: {} };
    if (!rowsRoot) return selection;
    rowsRoot.querySelectorAll('[data-class]').forEach((row) => {
      const code = row.getAttribute('data-class');
      const volume = row.querySelector(
        'input[data-attentions], select[data-attentions]',
      );
      if (!code || !volume) return;
      const attentions = Math.max(0, parseInt(volume.value, 10) || 0);
      if (attentions <= 0) return;
      selection.classes[code] = attentions;
      const chosen = {};
      row.querySelectorAll('input[data-addon]').forEach((input) => {
        chosen[input.getAttribute('data-addon')] = !!input.checked;
      });
      selection.addons[code] = chosen;
    });
    return selection;
  }

  function selectedAddonLabels(config, selection, code) {
    const chosen = ((selection && selection.addons) || {})[code] || {};
    return addonsForClass(config, code)
      .filter((addon) => addon.state === ADDON_OPTIONAL && chosen[addon.id])
      .map((addon) => addon.label);
  }

  function toSignupPlan(config, selection) {
    const plan = { classes: {} };
    Object.keys((selection && selection.classes) || {}).forEach((code) => {
      const attentions = Math.max(
        1,
        parseInt(selection.classes[code], 10) || 1,
      );
      const chosen = ((selection && selection.addons) || {})[code] || {};
      const row = { attentions_per_month: attentions };
      addonsForClass(config, code).forEach((addon) => {
        if (!addon.planField) return;
        row[addon.planField] =
          addon.state === ADDON_INCLUDED ||
          (addon.state === ADDON_OPTIONAL && !!chosen[addon.id]);
      });
      plan.classes[code] = row;
    });
    return plan;
  }

  global.BioenlacePricing = {
    ADDON_INCLUDED,
    ADDON_OPTIONAL,
    ADDON_UNAVAILABLE,
    formatAttentions,
    attentionVolumePresets,
    addonsForClass,
    readDomSelection,
    selectedAddonLabels,
    toSignupPlan,
  };
})(typeof window !== 'undefined' ? window : this);