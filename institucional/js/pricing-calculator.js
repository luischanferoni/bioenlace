/**
 * Calculador: profesionales × clase + dictado/videollamada solo en AMB.
 * EMER/IMP: dictado fijo incluido, sin videollamada.
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
  const addonsEl = document.getElementById('pricing-addons');
  const mode = (root.getAttribute('data-mode') || 'page').toLowerCase();

  let config = null;

  function ambRow() {
    return rowsEl ? rowsEl.querySelector('[data-class="AMB"]') : null;
  }

  function currentSelection() {
    return Pricing.readDomSelection(rowsEl);
  }

  function syncAmbOptions(row) {
    if (!row || row.getAttribute('data-class') !== 'AMB') return;
    const enabled = row.querySelector('input[name="class_AMB"]');
    const on = !!(enabled && enabled.checked);
    const audio = row.querySelector('input[data-addon="audio"]');
    const video = row.querySelector('input[data-addon="videollamada"]');
    if (video) video.disabled = !on;
    if (audio) {
      if (!on) {
        audio.disabled = true;
        audio.checked = false;
        if (video) video.checked = false;
      } else if (video && video.checked) {
        // Videollamada incluye transcripción de la llamada (= dictado una sola vez)
        audio.checked = true;
        audio.disabled = true;
      } else {
        audio.disabled = false;
      }
    }
    if (!on && video) video.checked = false;
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
    const selection = currentSelection();
    const result = Pricing.estimate(config, selection);
    refreshUnitLabels();

    if (totalEl) {
      totalEl.textContent = result.lines.length
        ? result.formattedTotal + ' / mes'
        : '—';
    }
    if (breakdownEl) {
      if (!result.lines.length) {
        breakdownEl.innerHTML =
          '<p class="pricing-calc__hint">Activá al menos un tipo de atención e indicá la cantidad de profesionales.</p>';
      } else {
        const tier = result.tier || {};
        const tierRange =
          tier.max_pes == null
            ? (tier.min_pes || 150) + '+ profesionales'
            : (tier.min_pes || 1) + '–' + tier.max_pes + ' profesionales';
        let meta =
          '<div class="pricing-calc__line pricing-calc__line--meta"><span>Tramo ' +
          (tier.label || 'Lista') +
          ' (' +
          tierRange +
          '; ' +
          result.totalPes +
          ' contratado' +
          (result.totalPes === 1 ? '' : 's') +
          ')</span></div>';
        if (result.discountPercent > 0) {
          meta +=
            '<div class="pricing-calc__line pricing-calc__line--meta"><span>Descuento por volumen −' +
            result.discountPercent +
            '% vs lista (' +
            result.formattedListTotal +
            '/mes)</span></div>';
        }
        breakdownEl.innerHTML =
          meta +
          result.lines
            .map((l) => {
              let note = '';
              if (l.code === 'AMB') {
                const bits = [];
                if (selection.addons.videollamada) {
                  bits.push('con videollamada (transcripción incluida)');
                } else if (selection.addons.audio) {
                  bits.push('con dictado');
                }
                note = bits.length ? ' · ' + bits.join(' · ') : '';
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
                Pricing.formatMoney(l.unit, result.currency) +
                '/mes)</span><strong>' +
                Pricing.formatMoney(l.line, result.currency) +
                '</strong></div>'
              );
            })
            .join('');
      }
    }

    root.dispatchEvent(
      new CustomEvent('bioenlace:pricing-change', {
        bubbles: true,
        detail: { selection: selection, estimate: result },
      })
    );

    if (ctaEl && mode !== 'signup') {
      const summary = result.lines
        .map((l) => {
          let extra = '';
            if (l.code === 'AMB') {
            const bits = [];
            if (selection.addons.videollamada) bits.push('videollamada');
            else if (selection.addons.audio) bits.push('dictado');
            if (bits.length) extra = ' con ' + bits.join(' y ');
          }
          return l.qty + ' profesional' + (l.qty === 1 ? '' : 'es') + ' ' + l.label + extra;
        })
        .join(', ');
      const msg =
        'Hola, quiero cotizar Bioenlace: ' +
        (summary || 'sin selección') +
        '. Estimado orientativo ' +
        result.formattedTotal +
        '/mes.';
      const href = (config.simulator && config.simulator.cta_href) || '#contacto';
      ctaEl.setAttribute('href', href);
      ctaEl.dataset.quoteMessage = msg;
    }
  }

  function refreshUnitLabels() {
    if (!rowsEl || !config) return;
    const selection = currentSelection();
    const addons = selection.addons;
    const totalPes = Pricing.totalPesFromSelection(selection);
    rowsEl.querySelectorAll('[data-class]').forEach((row) => {
      const code = row.getAttribute('data-class');
      const unitEl = row.querySelector('.pricing-calc__unit');
      if (unitEl && code) {
        unitEl.textContent =
          Pricing.formatMoney(
            Pricing.unitPriceForClass(config, code, addons, totalPes > 0 ? totalPes : null),
            config.currency
          ) + ' / profesional / mes';
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
      '</strong> <em>(opcional; incluido si hay videollamada)</em></span></label>' +
      '<label class="pricing-calc__option">' +
      '<input type="checkbox" data-addon="videollamada" disabled />' +
      '<span><strong>' +
      (video.label || 'Videollamada') +
      '</strong> <em>(opcional; incluye transcripción de la llamada)</em></span></label>' +
      '</div>'
    );
  }

  function buildFixedPolicyHtml(code) {
    if (Pricing.classIncludesAudio(config, code) && !Pricing.classAllowsVideollamada(config, code)) {
      return '<p class="pricing-calc__policy">Dictado incluido · Sin videollamada</p>';
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
      const unit = Pricing.unitPriceForClass(config, code, {});
      const row = document.createElement('div');
      row.className = 'pricing-calc__row';
      row.setAttribute('data-class', code);
      const optionsHtml = code === 'AMB' ? buildAmbOptionsHtml() : buildFixedPolicyHtml(code);
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
        Pricing.formatMoney(unit, config.currency) +
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
      number.addEventListener('input', () => {
        refreshUnitLabels();
        recalc();
      });
      row.querySelectorAll('input[data-addon]').forEach((input) => {
        input.addEventListener('change', () => {
          syncAmbOptions(row);
          refreshUnitLabels();
          recalc();
        });
      });
    });

    // Defaults útiles en alta: ambulatorio con 5 profesionales
    if (mode === 'signup') {
      const amb = rowsEl.querySelector('[data-class="AMB"]');
      if (amb) {
        const cb = amb.querySelector('input[name="class_AMB"]');
        const qty = amb.querySelector('input[type="number"]');
        if (cb) cb.checked = true;
        if (qty) qty.value = '5';
        syncQtyDisabled(amb);
      }
    }
  }

  function fillStaticCopy() {
    const title = document.getElementById('pricing-title');
    const subtitle = document.getElementById('pricing-subtitle');
    const footnotes = document.getElementById('pricing-footnotes');
    const sim = config.simulator || {};
    if (title && sim.title && mode !== 'signup') title.textContent = sim.title;
    if (subtitle && sim.subtitle && mode !== 'signup') subtitle.textContent = sim.subtitle;
    if (ctaEl && sim.cta_label && mode !== 'signup') ctaEl.textContent = sim.cta_label;
    if (footnotes && Array.isArray(sim.footnotes)) {
      footnotes.innerHTML = sim.footnotes.map((f) => '<li>' + f + '</li>').join('');
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
      return Pricing.toSignupPlan(currentSelection());
    },
    getEstimate: function () {
      return config ? Pricing.estimate(config, currentSelection()) : null;
    },
    isReady: function () {
      return !!config;
    },
  };

  fetch('js/pricing-config.json', { cache: 'no-cache' })
    .then((r) => {
      if (!r.ok) throw new Error('No se pudo cargar precios');
      return r.json();
    })
    .then((data) => {
      config = data;
      fillStaticCopy();
      buildRows();
      refreshUnitLabels();
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
