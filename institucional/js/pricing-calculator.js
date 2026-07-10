/**
 * Calculador: profesionales × clase + dictado/videollamada solo en AMB.
 * EMER/IMP: dictado fijo incluido, sin videollamada.
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

  function ambRow() {
    return rowsEl ? rowsEl.querySelector('[data-class="AMB"]') : null;
  }

  function ambAddonEnabled(key) {
    const row = ambRow();
    if (!row) return false;
    const classOn = row.querySelector('input[name="class_AMB"]');
    if (!classOn || !classOn.checked) return false;
    const input = row.querySelector('input[data-addon="' + key + '"]');
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
    const audio = classIncludesAudio(code) || (code === 'AMB' && ambAddonEnabled('audio'));
    const video = classAllowsVideollamada(code) && ambAddonEnabled('videollamada');
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
      const enabled = row.querySelector('input[type="checkbox"][name^="class_"]');
      const qty = row.querySelector('input[type="number"]');
      if (!code || !enabled || !qty) return;
      if (!enabled.checked) return;
      const n = Math.max(0, parseInt(qty.value, 10) || 0);
      if (n > 0) sel[code] = n;
    });
    return sel;
  }

  function syncAmbOptions(row) {
    if (!row || row.getAttribute('data-class') !== 'AMB') return;
    const enabled = row.querySelector('input[name="class_AMB"]');
    const on = !!(enabled && enabled.checked);
    row.querySelectorAll('input[data-addon]').forEach((input) => {
      input.disabled = !on;
      if (!on) input.checked = false;
    });
  }

  function syncQtyDisabled(row) {
    const enabled = row.querySelector('input[type="checkbox"][name^="class_"]');
    const qty = row.querySelector('input[type="number"]');
    if (!enabled || !qty) return;
    qty.disabled = !enabled.checked;
    if (!enabled.checked) qty.value = '0';
    else if ((parseInt(qty.value, 10) || 0) < 1) qty.value = '1';
    syncAmbOptions(row);
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
          .map((l) => {
            let note = '';
            if (l.code === 'AMB') {
              const bits = [];
              if (ambAddonEnabled('audio')) bits.push('con dictado');
              if (ambAddonEnabled('videollamada')) bits.push('con videollamada');
              note = bits.length ? ' · ' + bits.join(' · ') : '';
            } else {
              note = '';
            }
            return (
              '<div class="pricing-calc__line"><span>' +
              l.qty +
              ' profesional' +
              (l.qty === 1 ? '' : 'es') +
              ' × ' +
              l.label +
              note +
              ' (' +
              formatMoney(l.unit, config.currency) +
              '/mes)</span><strong>' +
              formatMoney(l.line, config.currency) +
              '</strong></div>'
            );
          })
          .join('');
      }
    }
    if (ctaEl) {
      const summary = lines
        .map((l) => {
          let extra = '';
          if (l.code === 'AMB') {
            const bits = [];
            if (ambAddonEnabled('audio')) bits.push('dictado');
            if (ambAddonEnabled('videollamada')) bits.push('videollamada');
            if (bits.length) extra = ' con ' + bits.join(' y ');
          }
          return l.qty + ' profesional' + (l.qty === 1 ? '' : 'es') + ' ' + l.label + extra;
        })
        .join(', ');
      const msg =
        'Hola, quiero cotizar Bioenlace: ' +
        (summary || 'sin selección') +
        '. Estimado orientativo ' +
        formatMoney(total, config.currency) +
        '/mes.';
      const href = (config.simulator && config.simulator.cta_href) || '#contacto';
      ctaEl.setAttribute('href', href);
      ctaEl.dataset.quoteMessage = msg;
    }
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

  function buildAmbOptionsHtml() {
    const addons = config.addons || {};
    const audio = addons.audio || {};
    const video = addons.videollamada || {};
    return (
      '<div class="pricing-calc__amb-options">' +
      '<label class="pricing-calc__option">' +
      '<input type="checkbox" data-addon="audio" disabled />' +
      '<span><strong>' +
      (audio.label || 'Dictado') +
      '</strong> <em>(opcional)</em></span></label>' +
      '<label class="pricing-calc__option">' +
      '<input type="checkbox" data-addon="videollamada" disabled />' +
      '<span><strong>' +
      (video.label || 'Videollamada') +
      '</strong> <em>(opcional)</em></span></label>' +
      '</div>'
    );
  }

  function buildFixedPolicyHtml(code) {
    if (classIncludesAudio(code) && !classAllowsVideollamada(code)) {
      return (
        '<p class="pricing-calc__policy">Dictado incluido · Sin videollamada</p>'
      );
    }
    return '';
  }

  function buildRows() {
    if (!rowsEl || !config) return;
    if (addonsEl) {
      addonsEl.innerHTML = '';
      addonsEl.hidden = true;
    }
    const classes = config.sellable_classes || {};
    rowsEl.innerHTML = '';
    Object.keys(classes).forEach((code) => {
      const cls = classes[code];
      const unit = unitPriceForClass(code);
      const row = document.createElement('div');
      row.className = 'pricing-calc__row';
      row.setAttribute('data-class', code);
      const optionsHtml =
        code === 'AMB' ? buildAmbOptionsHtml() : buildFixedPolicyHtml(code);
      row.innerHTML =
        '<div class="pricing-calc__main">' +
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
        optionsHtml +
        '</div>' +
        '<label class="pricing-calc__qty">Profesionales' +
        '<input type="number" min="0" max="500" step="1" value="0" inputmode="numeric" aria-label="Cantidad de profesionales ' +
        cls.label +
        '" />' +
        '</label>';
      rowsEl.appendChild(row);
      const checkbox = row.querySelector('input[name="class_' + code + '"]');
      const number = row.querySelector('input[type="number"]');
      syncQtyDisabled(row);
      checkbox.addEventListener('change', () => {
        syncQtyDisabled(row);
        refreshUnitLabels();
        recalc();
      });
      number.addEventListener('input', recalc);
      row.querySelectorAll('input[data-addon]').forEach((input) => {
        input.addEventListener('change', () => {
          refreshUnitLabels();
          recalc();
        });
      });
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
