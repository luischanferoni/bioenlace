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

  function syncAmbOptions(row) {
    if (!row || row.getAttribute('data-class') !== 'AMB') return;
    const enabled = row.querySelector('input[name="class_AMB"]');
    const on = !!(enabled && enabled.checked);
    const video = row.querySelector('input[data-addon="videollamada"]');
    if (video) {
      video.disabled = !on;
      if (!on) video.checked = false;
    }
  }

  function volumePresetsForRow(row) {
    return Pricing.attentionVolumePresets(config, row.getAttribute('data-class'));
  }

  function syncStepButtons(row) {
    const range = row.querySelector('[data-volume-range]');
    const enabled = row.querySelector('input[type="checkbox"][name^="class_"]');
    const presets = volumePresetsForRow(row);
    const index = range ? Number(range.value) || 0 : 0;
    const on = !!(enabled && enabled.checked);
    row.querySelectorAll('[data-step]').forEach((button) => {
      const step = Number(button.getAttribute('data-step')) || 0;
      button.disabled = !on || (step < 0 ? index <= 0 : index >= presets.length);
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

    if (range) {
      range.value = String(next);
      range.setAttribute(
        'aria-valuetext',
        preset
          ? Pricing.formatAttentions(preset.attentions) +
            ' atenciones por mes' +
            (preset.label ? ', ' + preset.label : '')
          : 'Sin volumen',
      );
    }
    if (hidden) hidden.value = preset ? String(preset.attentions) : '';
    if (readout) {
      if (!preset) {
        readout.innerHTML =
          '<strong class="is-empty">Sin volumen</strong>' +
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
  }

  function setVolume(row, attentions) {
    const presets = volumePresetsForRow(row);
    const index = presets.findIndex(
      (preset) => Number(preset.attentions) === Number(attentions),
    );
    setVolumeIndex(row, index >= 0 ? index + 1 : 0);
  }

  function syncQtyDisabled(row) {
    const enabled = row.querySelector('input[type="checkbox"][name^="class_"]');
    const range = row.querySelector('[data-volume-range]');
    const hidden = row.querySelector('input[data-attentions]');
    if (!enabled || !range || !hidden) return;
    const on = !!enabled.checked;
    range.disabled = !on;
    if (!on) {
      setVolumeIndex(row, 0);
    } else if (!hidden.value) {
      if (mode === 'signup') {
        setVolume(row, Pricing.defaultAttentions(config, row.getAttribute('data-class')));
      } else {
        setVolumeIndex(row, 0);
      }
    }
    syncStepButtons(row);
    syncAmbOptions(row);
  }

  function recalc() {
    if (!config || !apiBaseUrl) return;
    const selection = currentSelection();
    const requestId = ++quoteRequestId;
    const selectedClasses = Object.keys(selection.classes || {});
    window.clearTimeout(quoteTimer);
    latestQuote = null;
    refreshUnitLabels();

    if (totalEl) {
      totalEl.textContent = selectedClasses.length ? 'Calculando…' : '—';
    }
    if (breakdownEl) {
      if (!selectedClasses.length) {
        breakdownEl.innerHTML =
          '<p class="pricing-calc__hint">Activá al menos un tipo de atención y elegí un volumen.</p>';
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
        const extra =
          code === 'AMB' && selection.addons.videollamada
            ? ' · videollamada'
            : '';
        return (
          Pricing.formatAttentions(selection.classes[code]) +
          ' · ' +
          (row.label || code) +
          extra
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

  function formatQuoteUnitPrice(amount) {
    const formatted = new Intl.NumberFormat('es-AR', {
      minimumFractionDigits: 4,
      maximumFractionDigits: 4,
    }).format(Number(amount));
    return 'USD ' + formatted;
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
        plan: Pricing.toSignupPlan(selection),
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
          const videoAddon =
            item.code === 'AMB' && selection.addons.videollamada
              ? ' (videollamada)'
              : '';
          return (
            '<div class="pricing-calc__line"><span>' +
            escapeHtml(row.label || item.code) +
            escapeHtml(videoAddon) +
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
            formatQuoteMoney(quote.list_amount_usd, 'USD') +
            '</strong></div><div class="pricing-calc__line"><span>Descuento por volumen (' +
            escapeHtml(String(discountPercent)) +
            '%)</span><strong>− ' +
            formatQuoteMoney(quote.discount_amount_usd, 'USD') +
            '</strong></div>'
          : '';
      const rateDate = ''/*quote.exchange_rate_effective_date
        ? '<div class="pricing-calc__line"><span>Cotización vigente desde</span><strong>' +
          escapeHtml(quote.exchange_rate_effective_date) +
          '</strong></div>'
        : ''*/;
      breakdownEl.innerHTML =
        itemLines +
        discount +        
        '<div class="pricing-calc__line"><span>Neto en pesos</span><strong>' +
        formatQuoteMoney(quote.amount_net_ars, 'ARS') +
        '</strong></div>' +
        '<div class="pricing-calc__line"><span>IVA</span><strong>' +
        formatQuoteMoney(quote.vat_ars, 'ARS') +
        '</strong></div>' +
        rateDate;
    }
    root.dispatchEvent(
      new CustomEvent('bioenlace:pricing-change', {
        bubbles: true,
        detail: { selection: selection, estimate: quote },
      }),
    );
  }

  function refreshUnitLabels() {
    // Precio unitario no se muestra en la UI; el total y el breakdown alcanzan.
  }

  function buildAmbOptionsHtml() {
    const video = (config.addons && config.addons.videollamada) || {};
    const onPlansPage = document.body.classList.contains('pricing-page');
    return (
      '<div class="pricing-calc__amb-options">' +
      (onPlansPage ? '' : '<span class="pricing-calc__policy">Dictado incluido</span>') +
      '<label class="pricing-calc__option">' +
      '<input type="checkbox" data-addon="videollamada" disabled />' +
      '<span><strong>' +
      (video.label || 'Videollamada') +
      '</strong> <em>(opcional)</em></span></label>' +
      '</div>'
    );
  }

  function buildFixedPolicyHtml(code) {
    if (document.body.classList.contains('pricing-page')) {
      return '';
    }
    if (Pricing.classIncludesAudio(config, code) && !Pricing.classAllowsVideollamada(config, code)) {
      return '<p class="pricing-calc__policy">Dictado incluido · Sin videollamada</p>';
    }
    return '<p class="pricing-calc__policy">Dictado incluido</p>';
  }

  function buildVolumeSliderHtml(code, selected) {
    const presets = Pricing.attentionVolumePresets(config, code);
    const max = presets.length;

    return (
      '<div class="pricing-calc__qty">' +
      '<span class="pricing-calc__qty-label">Volumen de atenciones mensual</span>' +
      '<div class="pricing-calc__slider">' +
      '<button type="button" class="pricing-calc__slider-step" data-step="-1" aria-label="Menos atenciones" disabled>−</button>' +
      '<div class="pricing-calc__slider-track">' +
      '<input type="range" min="0" max="' +
      max +
      '" step="1" value="0" data-volume-range aria-label="Volumen de atenciones mensual ' +
      escapeHtml(code) +
      '" aria-valuetext="Sin volumen" disabled />' +
      '</div>' +
      '<button type="button" class="pricing-calc__slider-step" data-step="1" aria-label="Más atenciones" disabled>+</button>' +
      '</div>' +
      '</span></div>' +
      '<div class="pricing-calc__slider-readout" data-volume-readout aria-live="polite">' +
      '<strong class="is-empty">Sin volumen</strong><span>Mové el control o usá − / +</span>' +
      '</div>' +
      '<input type="hidden" data-attentions value="' +
      (selected || '') +
      '" />' +
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
      const optionsHtml = code === 'AMB' ? buildAmbOptionsHtml() : buildFixedPolicyHtml(code);
      row.innerHTML =
        '<div class="pricing-calc__main">' +
        '<label class="pricing-calc__check">' +
        '<input type="checkbox" name="class_' +
        escapeHtml(code) +
        '" />' +
        '<span class="pricing-calc__check-body">' +
        '<strong>' +
        escapeHtml(cls.label || code) +
        '</strong>' +
        '<span class="pricing-calc__short">' +
        escapeHtml(cls.short || '') +
        '</span>' +
        '</span></label>' +
         buildVolumeSliderHtml(code, '') +
        '</div>' +
        optionsHtml;
      rowsEl.appendChild(row);
      const checkbox = row.querySelector('input[name="class_' + code + '"]');
      syncQtyDisabled(row);
      checkbox.addEventListener('change', () => {
        syncQtyDisabled(row);
        refreshUnitLabels();
        recalc();
      });
      const range = row.querySelector('[data-volume-range]');
      if (range) {
        range.addEventListener('input', () => {
          setVolumeIndex(row, range.value);
        });
        range.addEventListener('change', () => {
          setVolumeIndex(row, range.value);
          refreshUnitLabels();
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
          refreshUnitLabels();
          recalc();
        });
      });
      row.querySelectorAll('input[data-addon]').forEach((input) => {
        input.addEventListener('change', () => {
          syncAmbOptions(row);
          refreshUnitLabels();
          recalc();
        });
      });
    });

    if (mode === 'signup') {
      const amb = rowsEl.querySelector('[data-class="AMB"]');
      if (amb) {
        const cb = amb.querySelector('input[name="class_AMB"]');
        if (cb) cb.checked = true;
        setVolume(amb, Pricing.defaultAttentions(config, 'AMB'));
        syncQtyDisabled(amb);
      }
    }
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function fillPlansPageCopy() {
    const listEl = document.getElementById('pricing-includes-list');
    if (!listEl || !config) return;
    const classes = config.sellable_classes || {};
    const items = Object.keys(classes).reduce((all, code) => {
      const includes = Array.isArray(classes[code].includes)
        ? classes[code].includes
        : [];
      includes.forEach((item) => {
        if (item) all.push((classes[code].label || code) + ': ' + item);
      });
      return all;
    }, []);
    if (!items.length) {
      listEl.innerHTML = '';
      const section = document.getElementById('incluye');
      if (section) section.hidden = true;
      return;
    }
    listEl.innerHTML = items.map((item) => '<li>' + escapeHtml(item) + '</li>').join('');
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
    fillPlansPageCopy();
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
      return Pricing.toSignupPlan(currentSelection());
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
      refreshUnitLabels();
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
