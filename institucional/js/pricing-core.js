/**
 * UI helpers for selecting a license plan. Prices are calculated by the API.
 */
(function (global) {
  'use strict';

  const ADDON_INCLUDED = 'included';
  const ADDON_OPTIONAL = 'optional';
  const ADDON_UNAVAILABLE = 'unavailable';
  const ADDON_SCOPE_ALL = 'all';
  const ADDON_SCOPE_PER_CLASS = 'per_class';

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

  function sellableClasses(config) {
    const classes = (config && config.sellable_classes) || {};
    return classes && typeof classes === 'object' ? classes : {};
  }

  function classLabel(config, code) {
    const row = sellableClasses(config)[code] || {};
    return row.label || code;
  }

  /**
   * Addons declarados por el backend con su estado por tipo de atención.
   * `includedIn` / `optionalIn` / `unavailableIn` salen de `addons[*].classes`,
   * que es la única fuente: el frontend no repite la matriz.
   */
  function addonEntries(config) {
    const addons = (config && config.addons) || {};
    const codes = Object.keys(sellableClasses(config));
    return Object.keys(addons)
      .map((id) => {
        const addon = addons[id] || {};
        const states = {};
        const includedIn = [];
        const optionalIn = [];
        const unavailableIn = [];
        codes.forEach((code) => {
          const state = normalizeAddonState((addon.classes || {})[code]);
          states[code] = state;
          if (state === ADDON_INCLUDED) includedIn.push(code);
          else if (state === ADDON_OPTIONAL) optionalIn.push(code);
          else unavailableIn.push(code);
        });
        return {
          id,
          label: addon.label || id,
          includedLabel: addon.included_label || addon.label || id,
          unavailableLabel: addon.unavailable_label || '',
          description: addon.description || '',
          planField: addon.plan_field || '',
          scope:
            String(addon.scope || '').trim().toLowerCase() === ADDON_SCOPE_ALL
              ? ADDON_SCOPE_ALL
              : ADDON_SCOPE_PER_CLASS,
          states,
          includedIn,
          optionalIn,
          unavailableIn,
        };
      })
      .filter((addon) => addon.includedIn.length || addon.optionalIn.length);
  }

  /** Tipos de atención a los que aplica el addon (opcionales si los hay). */
  function addonScopeCodes(addon) {
    return addon.optionalIn.length ? addon.optionalIn : addon.includedIn;
  }

  function addonScopeLabel(config, addon) {
    const codes = addonScopeCodes(addon);
    if (!codes.length) return '';
    const total = Object.keys(sellableClasses(config)).length;
    if (codes.length === total) return 'Todos los tipos de atención';
    return codes.map((code) => classLabel(config, code)).join(' · ');
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
    });
    rowsRoot.querySelectorAll('input[data-addon]').forEach((input) => {
      selection.addons[input.getAttribute('data-addon')] = !!input.checked;
    });
    return selection;
  }

  function addonChosen(selection, addonId) {
    return !!(((selection && selection.addons) || {})[addonId]);
  }

  /** Addons opcionales elegidos que además aplican a ese tipo de atención. */
  function selectedAddonLabels(config, selection, code) {
    return addonEntries(config)
      .filter(
        (addon) =>
          addon.optionalIn.indexOf(code) !== -1 && addonChosen(selection, addon.id),
      )
      .map((addon) => addon.label);
  }

  function toSignupPlan(config, selection) {
    const entries = addonEntries(config);
    const plan = { classes: {} };
    Object.keys((selection && selection.classes) || {}).forEach((code) => {
      const attentions = Math.max(
        1,
        parseInt(selection.classes[code], 10) || 1,
      );
      const row = { attentions_per_month: attentions };
      entries.forEach((addon) => {
        if (!addon.planField) return;
        row[addon.planField] =
          addon.states[code] === ADDON_INCLUDED ||
          (addon.states[code] === ADDON_OPTIONAL && addonChosen(selection, addon.id));
      });
      plan.classes[code] = row;
    });
    return plan;
  }

  global.BioenlacePricing = {
    ADDON_INCLUDED,
    ADDON_OPTIONAL,
    ADDON_UNAVAILABLE,
    ADDON_SCOPE_ALL,
    ADDON_SCOPE_PER_CLASS,
    formatAttentions,
    attentionVolumePresets,
    addonEntries,
    addonScopeLabel,
    readDomSelection,
    selectedAddonLabels,
    toSignupPlan,
  };
})(typeof window !== 'undefined' ? window : this);