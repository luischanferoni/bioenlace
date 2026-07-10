/**
 * Calculador de licencia: PES × encounter_class.
 * Config: pricing-config.json (alineado a metadata PHP).
 */
(function () {
  const root = document.getElementById('pricing-calculator');
  if (!root) return;

  const totalEl = document.getElementById('pricing-total');
  const breakdownEl = document.getElementById('pricing-breakdown');
  const ctaEl = document.getElementById('pricing-cta');
  const rowsEl = document.getElementById('pricing-rows');

  let config = null;

  function formatMoney(n, currency) {
    try {
      return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: currency || 'USD',
        maximumFractionDigits: 0,
      }).format(n);
    } catch (e) {
      return (currency || 'USD') + ' ' + Math.round(n);
    }
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
      const line = qty * (Number(cls.price_per_pes) || 0);
      total += line;
      lines.push({
        code,
        label: cls.label,
        qty,
        unit: cls.price_per_pes,
        line,
      });
    });

    if (totalEl) {
      totalEl.textContent = formatMoney(total, config.currency) + ' / mes';
    }
    if (breakdownEl) {
      if (!lines.length) {
        breakdownEl.innerHTML = '<p class="pricing-calc__hint">Activá al menos un tipo de atención e indicá la cantidad de PES.</p>';
      } else {
        breakdownEl.innerHTML = lines
          .map(
            (l) =>
              '<div class="pricing-calc__line"><span>' +
              l.qty +
              ' PES × ' +
              l.label +
              ' (' +
              formatMoney(l.unit, config.currency) +
              ')</span><strong>' +
              formatMoney(l.line, config.currency) +
              '</strong></div>'
          )
          .join('');
      }
    }
    if (ctaEl) {
      const summary = lines
        .map((l) => l.qty + ' PES ' + l.label)
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

  function syncQtyDisabled(row) {
    const enabled = row.querySelector('input[type="checkbox"]');
    const qty = row.querySelector('input[type="number"]');
    if (!enabled || !qty) return;
    qty.disabled = !enabled.checked;
    if (!enabled.checked) qty.value = '0';
    else if ((parseInt(qty.value, 10) || 0) < 1) qty.value = '1';
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
        formatMoney(cls.price_per_pes, config.currency) +
        ' / PES / mes</span>' +
        '</span></label>' +
        '<label class="pricing-calc__qty">PES' +
        '<input type="number" min="0" max="500" step="1" value="0" inputmode="numeric" aria-label="Cantidad de PES ' +
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
