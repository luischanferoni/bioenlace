/**
 * Selector de planes y cotizaciones mensuales provistas por el backend.
 * Requiere pricing-core.js (window.BioenlacePricing).
 */
(function () {
  const root = document.getElementById('pricing-calculator');
  if (!root || !window.BioenlacePricing) return;

  const Pricing = window.BioenlacePricing;
  const totalEl = document.getElementById('pricing-total');
  const breakdownEl = document.getElementById('pricing-breakdown');
  const ctaEl = document.getElementById('pricing-cta');
  const rowsEl = document.getElementById('pricing-rows');
  const mode = (root.getAttribute('data-mode') || 'page').toLowerCase();

  let config = null;
  let apiBaseUrl = null;
  let latestQuote = null;
  let quoteRequestId = 0;
  let quoteTimer = null;

  function currentSelection() {
    return Pricing.readDomSelection(rowsEl);
  }

  function volumePresetsForRow(row) {
    return Pricing.attentionVolumePresets(config, row.getAttribute('data-class'));
  }

  function syncStepButtons(row) {
    const range = row.querySelector('[data-volume-range]');
    const presets = volumePresetsForRow(row);
    const index = range ? Number(range.value) || 0 : 0;
    row.querySelectorAll('[data-step]').forEach((button) => {
      const step = Number(button.getAttribute('data-step')) || 0;
      button.disabled = step < 0 ? index <= 0 : index >= presets.length;
    });
  }

  function setVolumeIndex(row, index) {
    const presets = volumePresetsForRow(row);
    const max = presets.length;
    const next = Math.max(0, Math.min(max, parseInt(index, 10) || 0));
    const range = row.querySelector('[data-volume-range]');
    const hidden = row.querySelector('input[data-attentions]');
    const readout = row.querySelector('[data-volume-readout]');
    const preset = next === 0 ? null : presets[next - 1];

    row.classList.toggle('is-active', !!preset);
    if (range) {
      range.value = String(next);
      range.setAttribute(
        'aria-valuetext',
        preset
          ? Pricing.formatAttentions(preset.attentions) +
            ' atenciones por mes' +
            (preset.label ? ', ' + preset.label : '')
          : '',
      );
    }
    if (hidden) hidden.value = preset ? String(preset.attentions) : '';
    if (readout) {
      if (!preset) {
        readout.innerHTML =
          '<span>Mové el control o usá − / +</span>';
      } else {
        const hint = preset.hint || preset.label || '';
        readout.innerHTML =
          '<strong>' +
          escapeHtml(Pricing.formatAttentions(preset.attentions)) +
          '/mes</strong> ' +
          (hint ? '<span>' + escapeHtml(hint) + '</span>' : '');
      }
    }
    syncStepButtons(row);
    syncAddonControls();
  }

  function recalc() {
    if (!config || !apiBaseUrl) return;
    const selection = currentSelection();
    const requestId = ++quoteRequestId;
    const selectedClasses = Object.keys(selection.classes || {});
    window.clearTimeout(quoteTimer);
    latestQuote = null;

    if (totalEl) {
      totalEl.textContent = selectedClasses.length ? 'Calculando…' : '—';
    }
    if (breakdownEl) {
      if (!selectedClasses.length) {
        breakdownEl.innerHTML =
          '<p class="pricing-calc__hint">Elegí un volumen de atenciones para ver el precio.</p>';
      } else {
        breakdownEl.innerHTML =
          '<p class="pricing-calc__hint">Consultando el precio actualizado…</p>';
      }
    }

    root.dispatchEvent(
      new CustomEvent('bioenlace:pricing-change', {
        bubbles: true,
        detail: { selection: selection, estimate: null },
      })
    );

    if (ctaEl && mode !== 'signup') {
      const summary = selectionSummary(selection);
      const msg =
        'Hola, quiero cotizar Bioenlace: ' +
        (summary || 'sin selección') +
        '.';
      const href = (config.simulator && config.simulator.cta_href) || '#contacto';
      ctaEl.setAttribute('href', href);
      ctaEl.dataset.quoteMessage = msg;
    }

    if (!selectedClasses.length) return;
    quoteTimer = window.setTimeout(() => {
      requestQuote(selection, requestId);
    }, 250);
  }

  function selectionSummary(selection) {
    return Object.keys(selection.classes || {})
      .map((code) => {
        const row = (config.sellable_classes || {})[code] || {};
        const addons = Pricing.selectedAddonLabels(config, selection, code);
        return (
          Pricing.formatAttentions(selection.classes[code]) +
          ' · ' +
          (row.label || code) +
          (addons.length ? ' · ' + addons.join(' · ') : '')
        );
      })
      .join(', ');
  }

  function formatQuoteMoney(amount, currency) {
    const formatted = new Intl.NumberFormat('es-AR', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(Number(amount));
    return currency + ' ' + formatted;
  }

  function unwrapResponse(body) {
    if (body && body.error) {
      throw new Error(body.error.message || 'No se pudo calcular el precio.');
    }
    if (body && body.success === false) {
      throw new Error(body.message || 'No se pudo calcular el precio.');
    }
    return body && Object.prototype.hasOwnProperty.call(body, 'data')
      ? body.data
      : body;
  }

  function requestQuote(selection, requestId) {
    fetch(apiBaseUrl + '/licencia/cotizar', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'omit',
      body: JSON.stringify({
        plan: Pricing.toSignupPlan(config, selection),
      }),
    })
      .then((response) =>
        response.json().then((body) => {
          if (!response.ok) {
            const data = unwrapResponse(body);
            throw new Error(
              (data && data.message) || 'No se pudo calcular el precio.',
            );
          }
          return unwrapResponse(body);
        }),
      )
      .then((quote) => {
        if (requestId !== quoteRequestId) return;
        const amounts = [
          quote.amount_usd,
          quote.amount_net_ars,
          quote.vat_ars,
          quote.amount_total_ars,
          quote.list_amount_usd,
          quote.discount_amount_usd,
          quote.list_amount_ars,
          quote.discount_amount_ars,
          quote.discount_percent,
          quote.total_attentions,
        ].map(Number);
        const amountUsd = Number(quote.amount_usd);
        const listAmountUsd = Number(quote.list_amount_usd);
        const discountAmountUsd = Number(quote.discount_amount_usd);
        if (
          amounts.some((amount) => !Number.isFinite(amount)) ||
          listAmountUsd < amountUsd ||
          discountAmountUsd < 0 ||
          discountAmountUsd > listAmountUsd ||
          Number(quote.discount_percent) < 0 ||
          !Array.isArray(quote.items) ||
          quote.items.length === 0 ||
          quote.items.some((item) =>
            !item ||
            typeof item.code !== 'string' ||
            !(Number(item.attentions_per_month) > 0) ||
            !(Number(item.unit_amount_usd) >= 0) ||
            !(Number(item.amount_usd) >= 0) ||
            !Number.isFinite(Number(item.unit_amount_usd)) ||
            !Number.isFinite(Number(item.amount_usd))
          )
        ) {
          throw new Error('La cotización recibida no es válida.');
        }
        latestQuote = quote;
        renderQuote(selection, quote);
        root.classList.remove('is-error');
      })
      .catch((error) => {
        if (requestId !== quoteRequestId) return;
        latestQuote = null;
        if (totalEl) totalEl.textContent = '—';
        if (breakdownEl) {
          breakdownEl.innerHTML =
            '<p class="pricing-calc__hint">' +
            escapeHtml(error.message || 'No se pudo calcular el precio.') +
            '</p>';
        }
        root.classList.add('is-error');
      });
  }

  function renderQuote(selection, quote) {
    if (totalEl) {
      totalEl.textContent =
        formatQuoteMoney(quote.amount_total_ars, 'ARS');
    }
    if (breakdownEl) {
      const itemLines = quote.items
        .map((item) => {
          const row = (config.sellable_classes || {})[item.code] || {};
          const addons = Pricing.selectedAddonLabels(config, selection, item.code);
          return (
            '<div class="pricing-calc__line"><span>' +
            escapeHtml(row.label || item.code) +
            (addons.length ? ' (' + escapeHtml(addons.join(', ')) + ')' : '') +
            ' · ' +
            Pricing.formatAttentions(item.attentions_per_month) +
            ' atenciones/mes ' +
            '</span></div>'
          );
        })
        .join('');
      const discountPercent = Number(quote.discount_percent);
      const discount =
        discountPercent > 0
          ? '<div class="pricing-calc__line"><span>Precio de lista</span><strong>' +
            formatQuoteMoney(quote.list_amount_ars, 'ARS') +
            '</strong></div><div class="pricing-calc__line"><span>Descuento por volumen (' +
            escapeHtml(String(discountPercent)) +
            '%)</span><strong>− ' +
            formatQuoteMoney(quote.discount_amount_ars, 'ARS') +
            '</strong></div>'
          : '';
      breakdownEl.innerHTML =
        itemLines +
        discount +
        '<div class="pricing-calc__line"><span>Neto</span><strong>' +
        formatQuoteMoney(quote.amount_net_ars, 'ARS') +
        '</strong></div>' +
        '<div class="pricing-calc__line"><span>IVA</span><strong>' +
        formatQuoteMoney(quote.vat_ars, 'ARS') +
        '</strong></div>';
    }
    root.dispatchEvent(
      new CustomEvent('bioenlace:pricing-change', {
        bubbles: true,
        detail: { selection: selection, estimate: quote },
      }),
    );
  }

  /**
   * Funcionalidades y add-ons de la licencia. Cada addon declara su estado por
   * tipo de atención en el backend: los incluidos se informan (y dónde aplican),
   * los opcionales se tildan con un único control que impacta en el precio.
   */
  function buildGlobalAddonsHtml() {
    const cards = Pricing.addonEntries(config).map((addon) => {
      const scope = Pricing.addonScopeLabel(config, addon);
      const scopeHtml = scope
        ? '<span class="pricing-calc__addon-scope">Aplica a: ' +
          escapeHtml(scope) +
          '</span>'
        : '';
      const desc = addon.description
        ? '<span class="pricing-calc__addon-desc">' +
          escapeHtml(addon.description) +
          '</span>'
        : '';
      const body =
        '<span class="pricing-calc__addon-head"><strong>' +
        escapeHtml(addon.label) +
        '</strong><span class="pricing-calc__addon-badge is-' +
        (addon.optionalIn.length ? 'optional' : 'included') +
        '">' +
        (addon.optionalIn.length ? 'Opcional' : 'Incluido') +
        '</span></span>' +
        desc +
        scopeHtml;

      if (addon.optionalIn.length) {
        return (
          '<label class="pricing-calc__addon is-optional">' +
          '<input type="checkbox" data-addon="' +
          escapeHtml(addon.id) +
          '" disabled />' +
          '<span class="pricing-calc__addon-body">' +
          body +
          '</span></label>'
        );
      }

      return (
        '<div class="pricing-calc__addon is-included">' +
        '<span class="pricing-calc__addon-body">' +
        body +
        '</span></div>'
      );
    });
    if (!cards.length) return '';

    return (
      '<div class="pricing-calc__global-addons">' +
      '<p class="pricing-calc__global-title">Funcionalidades y add-ons</p>' +
      '<div class="pricing-calc__addon-list">' +
      cards.join('') +
      '</div>' +
      '</div>'
    );
  }

  function buildVolumeSliderHtml(code) {
    const presets = Pricing.attentionVolumePresets(config, code);
    const max = presets.length;

    return (
      '<div class="pricing-calc__qty">' +
      '<div class="pricing-calc__slider">' +
      '<button type="button" class="pricing-calc__slider-step" data-step="-1" aria-label="Menos atenciones" disabled>−</button>' +
      '<div class="pricing-calc__slider-track">' +
      '<input type="range" min="0" max="' +
      max +
      '" step="1" value="0" data-volume-range aria-label="Volumen de atenciones mensual ' +
      escapeHtml(code) +
      '" aria-valuetext="Sin volumen" />' +
      '</div>' +
      '<button type="button" class="pricing-calc__slider-step" data-step="1" aria-label="Más atenciones" disabled>+</button>' +
      '</div>' +
      '<div class="pricing-calc__slider-readout" data-volume-readout aria-live="polite">' +
      '<span>Mové el control o usá − / +</span>' +
      '</div>' +
      '<input type="hidden" data-attentions value="" />' +
      '</div>'
    );
  }

  function buildRows() {
    if (!rowsEl || !config) return;
    const classes = config.sellable_classes || {};
    rowsEl.innerHTML = '';
    Object.keys(classes).forEach((code) => {
      const cls = classes[code];
      const row = document.createElement('div');
      row.className = 'pricing-calc__row';
      row.setAttribute('data-class', code);
      row.innerHTML =
        '<div class="pricing-calc__main">' +
        '<div class="pricing-calc__check">' +
        '<span class="pricing-calc__check-body">' +
        '<strong>' +
        escapeHtml(cls.label || code) +
        '</strong>' +
        '<span class="pricing-calc__short">' +
        escapeHtml(cls.short || '') +
        '</span>' +
        '</span></div>' +
        buildVolumeSliderHtml(code) +
        '</div>';
      rowsEl.appendChild(row);
      setVolumeIndex(row, 0);
      const range = row.querySelector('[data-volume-range]');
      if (range) {
        range.addEventListener('input', () => {
          setVolumeIndex(row, range.value);
        });
        range.addEventListener('change', () => {
          setVolumeIndex(row, range.value);
          recalc();
        });
      }
      row.querySelectorAll('[data-step]').forEach((button) => {
        button.addEventListener('click', () => {
          if (button.disabled || !range) return;
          setVolumeIndex(
            row,
            Number(range.value) + Number(button.getAttribute('data-step')),
          );
          recalc();
        });
      });
    });

    const globalHtml = buildGlobalAddonsHtml();
    if (globalHtml) {
      const global = document.createElement('div');
      global.innerHTML = globalHtml;
      const block = global.firstElementChild;
      rowsEl.appendChild(block);
      block.querySelectorAll('input[data-addon]').forEach((input) => {
        input.addEventListener('change', () => recalc());
      });
    }
    syncAddonControls();
  }

  /** Un addon opcional solo se puede elegir si hay volumen en algún tipo de atención. */
  function syncAddonControls() {
    if (!rowsEl) return;
    const on = Array.prototype.some.call(
      rowsEl.querySelectorAll('[data-class]'),
      (row) => {
        const hidden = row.querySelector('input[data-attentions]');
        return !!(hidden && Number(hidden.value) > 0);
      },
    );
    rowsEl.querySelectorAll('input[data-addon]').forEach((input) => {
      input.disabled = !on;
      if (!on) input.checked = false;
    });
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function fillStaticCopy() {
    const footnotes = document.getElementById('pricing-footnotes');
    const sim = config.simulator || {};
    // En precios.html títulos/subtítulos están en el HTML (fuente de verdad).
    // simulator.title/subtitle solo aplican si no hay copy ya en el DOM (otros embeds).
    const onPlansPage = document.body.classList.contains('pricing-page');
    if (!onPlansPage && mode !== 'signup') {
      const title = document.getElementById('pricing-title');
      const subtitle = document.getElementById('pricing-subtitle');
      if (title && sim.title) title.textContent = sim.title;
      if (subtitle && sim.subtitle) subtitle.textContent = sim.subtitle;
    }
    if (ctaEl && sim.cta_label && mode !== 'signup') ctaEl.textContent = sim.cta_label;
    if (ctaEl && sim.cta_href && mode !== 'signup') ctaEl.setAttribute('href', sim.cta_href);
    if (footnotes) {
      const items = Array.isArray(sim.footnotes) ? sim.footnotes.filter(Boolean) : [];
      if (!items.length) {
        footnotes.innerHTML = '';
        footnotes.hidden = true;
      } else {
        footnotes.hidden = false;
        footnotes.innerHTML = items.map((f) => '<li>' + escapeHtml(f) + '</li>').join('');
      }
    }
    const tax = document.getElementById('pricing-tax-note');
    if (tax && config.tax_note) tax.textContent = config.tax_note;
  }

  function persistSelectionForSignup(selection) {
    try {
      sessionStorage.setItem(
        'bioenlace_pricing_selection',
        JSON.stringify({
          classes: selection.classes || {},
          addons: selection.addons || {},
          saved_at: Date.now(),
        })
      );
    } catch (e) {
      // sessionStorage puede fallar en modo privado estricto; el alta usa defaults.
    }
  }

  if (ctaEl) {
    if (mode === 'signup') {
      ctaEl.hidden = true;
    } else {
      ctaEl.addEventListener('click', (event) => {
        const selection = currentSelection();
        const href = (ctaEl.getAttribute('href') || '').trim();
        const goesToSignup = href.indexOf('alta.html') !== -1;

        if (goesToSignup) {
          persistSelectionForSignup(selection);
          return;
        }

        const msg = ctaEl.dataset.quoteMessage;
        const contactMsg = document.getElementById('message');
        if (msg && contactMsg) {
          contactMsg.value = msg;
        }
        if (href === '#contacto' && contactMsg) {
          event.preventDefault();
          contactMsg.focus();
          const offsetTop = document.getElementById('contacto')
            ? document.getElementById('contacto').offsetTop - 72
            : 0;
          window.scrollTo({ top: offsetTop, behavior: 'smooth' });
        }
      });
    }
  }

  window.BioenlacePricingCalculator = {
    getSelection: currentSelection,
    getPlan: function () {
      return Pricing.toSignupPlan(config, currentSelection());
    },
    getEstimate: function () {
      return latestQuote;
    },
    isReady: function () {
      return !!config && !!apiBaseUrl;
    },
  };

  function resolveApiBaseUrl(apiConfig) {
    const host = window.location.hostname;
    const isLocal =
      host === 'localhost' ||
      host === '127.0.0.1' ||
      host === '[::1]' ||
      host.endsWith('.local') ||
      host.endsWith('.test') ||
      host.endsWith('.localhost');
    const base =
      isLocal && apiConfig.apiBaseUrlLocal
        ? apiConfig.apiBaseUrlLocal
        : apiConfig.apiBaseUrl;
    if (!base) throw new Error('La API de precios no está configurada.');
    return String(base).replace(/\/+$/, '');
  }

  function fetchJson(url) {
    return fetch(url, {
      headers: { Accept: 'application/json' },
      credentials: 'omit',
    })
      .then((response) =>
        response.json().then((body) => {
          if (!response.ok) {
            const data = unwrapResponse(body);
            throw new Error(
              (data && data.message) || 'No se pudo cargar el catálogo de precios.',
            );
          }
          return unwrapResponse(body);
        }),
      );
  }

  fetchJson('js/api-config.json')
    .then((apiConfig) => {
      apiBaseUrl = resolveApiBaseUrl(apiConfig);
      return fetchJson(apiBaseUrl + '/licencia/planes');
    })
    .then((catalog) => {
      if (!catalog || !catalog.sellable_classes) {
        throw new Error('El catálogo de planes no tiene un formato válido.');
      }
      config = catalog;
      fillStaticCopy();
      buildRows();
      recalc();
      root.classList.add('is-ready');
    })
    .catch((error) => {
      root.classList.add('is-error');
      if (breakdownEl) {
        breakdownEl.innerHTML =
          '<p class="pricing-calc__hint">' +
          escapeHtml(error.message || 'No se pudo cargar el calculador.') +
          '</p>';
      }
    });
})();
