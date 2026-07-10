/**
 * Calculador de licencia: profesionales × encounter_class + add-ons.
 * Precio = COGS_ref × (vol_clase/ref) × (1 + margin%). Audio/video según clase.
 */
(function () {
  const root = document.getElementById('pricing-calculator');
  if (!root) return;

  const totalEl = document.getElementById('pricing-total');
  const breakdownEl = document.getElementById('pricing-breakdown');
  const ctaEl = document.getElementById('pricing-cta');
  const rowsEl = document.getElementById('pricing-rows');
  const addonsEl = document.getElementById('pricing-addons');

  let config = null;

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

  function referenceEncounters() {
    const n = Number((config && config.reference_encounters_per_professional_month) || 400);
    return n > 0 ? n : 400;
  }

  function classRow(code) {
    return (config.sellable_classes || {})[code] || {};
  }

  function volumeScale(code) {
    const enc = Number(classRow(code).encounters_per_professional_month) || referenceEncounters();
    return enc / referenceEncounters();
  }

  function classIncludesAudio(code) {
    return !!classRow(code).audio_included;
  }

  function classAllowsVideollamada(code) {
    const row = classRow(code);
    if (Object.prototype.hasOwnProperty.call(row, 'videollamada_allowed')) {
      return !!row.videollamada_allowed;
    }
    return true;
  }

  function addonEnabled(key) {
    if (!addonsEl) return false;
    const input = addonsEl.querySelector('input[data-addon="' + key + '"]');
    return !!(input && input.checked);
  }

  function referenceUnitCogs(audio, videollamada) {
    const cogs = (config && config.cogs_usd_per_professional_month) || {};
    let total = Number(cogs.base) || 0;
    if (audio) total += Number(cogs.audio) || 0;
    if (videollamada) total += Number(cogs.videollamada) || 0;
    return total;
  }

  function unitCogsForClass(code) {
    const audio = addonEnabled('audio') || classIncludesAudio(code);
    const video = addonEnabled('videollamada') && classAllowsVideollamada(code);
    return referenceUnitCogs(audio, video) * volumeScale(code);
  }

  function unitPriceForClass(code) {
    const margin = Number((config && config.margin_on_cost_percent) || 0);
    return Math.round(unitCogsForClass(code) * (1 + margin / 100) * 100) / 100;
  }

  function readSelection() {
    const sel = {};
    if (!rowsEl) return sel;
    rowsEl.querySelectorAll('[data-class]').forEach((row) => {
      const code = row.getAttribute('data-class');
      const enabled = row.querySelector('input[type="checkbox"]');
      const qty = row.querySelector('input[type="number"]');
      if (!code || !enabled || !qty) return;
      if (!enabled.checked) return;
      const n = Math.max(0, parseInt(qty.value, 10) || 0);
      if (n > 0) sel[code] = n;
    });
    return sel;
  }

  function recalc() {
    if (!config) return;
    const sel = readSelection();
    let total = 0;
    const lines = [];
    Object.keys(config.sellable_classes || {}).forEach((code) => {
      const cls = config.sellable_classes[code];
      const qty = sel[code] || 0;
      if (qty <= 0) return;
      const unit = unitPriceForClass(code);
      const line = qty * unit;
      total += line;
      lines.push({
        code,
        label: cls.label,
        qty,
        unit,
        line,
      });
    });

    if (totalEl) {
      totalEl.textContent = formatMoney(total, config.currency) + ' / mes';
    }
    if (breakdownEl) {
      if (!lines.length) {
        breakdownEl.innerHTML =
          '<p class="pricing-calc__hint">Activá al menos un tipo de atención e indicá la cantidad de profesionales.</p>';
      } else {
        breakdownEl.innerHTML = lines
          .map(
            (l) =>
              '<div class="pricing-calc__line"><span>' +
              l.qty +
              ' profesional' +
              (l.qty === 1 ? '' : 'es') +
              ' × ' +
              l.label +
              ' (' +
              formatMoney(l.unit, config.currency) +
              '/mes)</span><strong>' +
              formatMoney(l.line, config.currency) +
              '</strong></div>'
          )
          .join('');
      }
    }
    if (ctaEl) {
      const summary = lines
        .map((l) => l.qty + ' profesional' + (l.qty === 1 ? '' : 'es') + ' ' + l.label)
        .join(', ');
      const extras = [];
      if (addonEnabled('audio')) extras.push('con audio (ambulatorio)');
      if (addonEnabled('videollamada')) extras.push('con videollamada (ambulatorio)');
      const extrasTxt = extras.length ? ' (' + extras.join(', ') + ')' : '';
      const msg =
        'Hola, quiero cotizar Bioenlace: ' +
        (summary || 'sin selección') +
        extrasTxt +
        '. Estimado orientativo ' +
        formatMoney(total, config.currency) +
        '/mes.';
      const href = (config.simulator && config.simulator.cta_href) || '#contacto';
      ctaEl.setAttribute('href', href);
      ctaEl.dataset.quoteMessage = msg;
    }
  }

  function syncQtyDisabled(row) {
    const enabled = row.querySelector('input[type="checkbox"]');
    const qty = row.querySelector('input[type="number"]');
    if (!enabled || !qty) return;
    qty.disabled = !enabled.checked;
    if (!enabled.checked) qty.value = '0';
    else if ((parseInt(qty.value, 10) || 0) < 1) qty.value = '1';
  }

  function buildAddons() {
    if (!addonsEl || !config) return;
    const addons = config.addons || {};
    addonsEl.innerHTML = '';
    const title = document.createElement('p');
    title.className = 'pricing-calc__addons-title';
    title.textContent = 'Opcionales (ambulatorio)';
    addonsEl.appendChild(title);

    Object.keys(addons).forEach((key) => {
      const addon = addons[key];
      const label = document.createElement('label');
      label.className = 'pricing-calc__addon';
      const checked = addon.default ? ' checked' : '';
      label.innerHTML =
        '<input type="checkbox" data-addon="' +
        key +
        '"' +
        checked +
        ' />' +
        '<span class="pricing-calc__addon-body">' +
        '<strong>' +
        (addon.label || key) +
        '</strong>' +
        '<span class="pricing-calc__short">' +
        (addon.description || '') +
        '</span></span>';
      addonsEl.appendChild(label);
      label.querySelector('input').addEventListener('change', () => {
        refreshUnitLabels();
        recalc();
      });
    });
  }

  function refreshUnitLabels() {
    if (!rowsEl || !config) return;
    rowsEl.querySelectorAll('[data-class]').forEach((row) => {
      const code = row.getAttribute('data-class');
      const unitEl = row.querySelector('.pricing-calc__unit');
      if (unitEl && code) {
        unitEl.textContent = formatMoney(unitPriceForClass(code), config.currency) + ' / profesional / mes';
      }
    });
  }

  function buildRows() {
    if (!rowsEl || !config) return;
    const classes = config.sellable_classes || {};
    rowsEl.innerHTML = '';
    Object.keys(classes).forEach((code) => {
      const cls = classes[code];
      const unit = unitPriceForClass(code);
      const row = document.createElement('div');
      row.className = 'pricing-calc__row';
      row.setAttribute('data-class', code);
      row.innerHTML =
        '<label class="pricing-calc__check">' +
        '<input type="checkbox" name="class_' +
        code +
        '" />' +
        '<span class="pricing-calc__check-body">' +
        '<strong>' +
        cls.label +
        '</strong>' +
        '<span class="pricing-calc__short">' +
        (cls.short || '') +
        '</span>' +
        '<span class="pricing-calc__unit">' +
        formatMoney(unit, config.currency) +
        ' / profesional / mes</span>' +
        '</span></label>' +
        '<label class="pricing-calc__qty">Profesionales' +
        '<input type="number" min="0" max="500" step="1" value="0" inputmode="numeric" aria-label="Cantidad de profesionales ' +
        cls.label +
        '" />' +
        '</label>';
      rowsEl.appendChild(row);
      const checkbox = row.querySelector('input[type="checkbox"]');
      const number = row.querySelector('input[type="number"]');
      syncQtyDisabled(row);
      checkbox.addEventListener('change', () => {
        syncQtyDisabled(row);
        recalc();
      });
      number.addEventListener('input', recalc);
    });
  }

  function fillStaticCopy() {
    const title = document.getElementById('pricing-title');
    const subtitle = document.getElementById('pricing-subtitle');
    const footnotes = document.getElementById('pricing-footnotes');
    const sim = config.simulator || {};
    if (title && sim.title) title.textContent = sim.title;
    if (subtitle && sim.subtitle) subtitle.textContent = sim.subtitle;
    if (ctaEl && sim.cta_label) ctaEl.textContent = sim.cta_label;
    if (footnotes && Array.isArray(sim.footnotes)) {
      footnotes.innerHTML = sim.footnotes.map((f) => '<li>' + f + '</li>').join('');
    }
    const tax = document.getElementById('pricing-tax-note');
    if (tax && config.tax_note) tax.textContent = config.tax_note;
  }

  if (ctaEl) {
    ctaEl.addEventListener('click', () => {
      const msg = ctaEl.dataset.quoteMessage;
      const contactMsg = document.getElementById('message');
      if (msg && contactMsg) {
        contactMsg.value = msg;
      }
    });
  }

  fetch('js/pricing-config.json', { cache: 'no-cache' })
    .then((r) => {
      if (!r.ok) throw new Error('No se pudo cargar precios');
      return r.json();
    })
    .then((data) => {
      config = data;
      fillStaticCopy();
      buildAddons();
      buildRows();
      recalc();
      root.classList.add('is-ready');
    })
    .catch(() => {
      root.classList.add('is-error');
      if (breakdownEl) {
        breakdownEl.innerHTML =
          '<p class="pricing-calc__hint">No se pudo cargar el calculador. Escribinos a contacto.</p>';
      }
    });
})();
