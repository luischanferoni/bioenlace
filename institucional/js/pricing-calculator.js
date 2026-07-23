/**
 * Calculador: atenciones/mes × clase + dictado/videollamada solo en AMB.
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
    const qty = row.querySelector('[data-attentions]');
    if (!enabled || !qty) return;
    qty.disabled = !enabled.checked;
    if (!enabled.checked) {
      qty.value = '';
    } else if (!qty.value) {
      const scale = Pricing.attentionVolumeScale(config);
      qty.value = String(scale[0] || 1000);
    }
    syncAmbOptions(row);
  }

  function tierRangeLabel(tier) {
    if (!tier) return '';
    const min = Number(tier.min_attentions != null ? tier.min_attentions : tier.min_pes) || 1;
    const maxRaw = tier.max_attentions != null ? tier.max_attentions : tier.max_pes;
    if (maxRaw == null || maxRaw === '') {
      return Pricing.formatAttentions(min) + '+ atenciones / mes';
    }
    return Pricing.formatAttentions(min) + '–' + Pricing.formatAttentions(maxRaw) + ' / mes';
  }

  function buildVolumeDiscountPanel(activeTierId) {
    if (!addonsEl || !config) return;
    const tiers = Pricing.volumeDiscountTiers(config);
    if (!tiers.length) {
      addonsEl.innerHTML = '';
      addonsEl.hidden = true;
      return;
    }
    addonsEl.hidden = false;
    const rows = tiers
      .map((tier) => {
        const pct = Pricing.discountVsListPercent(config, tier);
        const benefit = pct > 0 ? '−' + pct + '%' : 'Precio base';
        const active = tier.id === activeTierId ? ' is-active' : '';
        return (
          '<li class="pricing-calc__volume-item' +
          active +
          '"><span>' +
          tierRangeLabel(tier) +
          '</span><strong>' +
          benefit +
          '</strong></li>'
        );
      })
      .join('');
    addonsEl.innerHTML =
      '<div class="pricing-calc__volume">' +
      '<p class="pricing-calc__volume-title">Descuentos por volumen</p>' +
      '<ul class="pricing-calc__volume-list">' +
      rows +
      '</ul>' +
      '<p class="pricing-calc__volume-nudge" id="pricing-volume-nudge" hidden></p>' +
      '</div>';
  }

  function updateVolumeNudge(result) {
    const nudgeEl = document.getElementById('pricing-volume-nudge');
    if (!nudgeEl) return;
    const next = result && result.nextStep;
    const hasSelection = result && result.lines && result.lines.length;
    const needed = next && (next.attentionsNeeded || next.professionalsNeeded);
    if (!hasSelection || !next || !(needed > 0) || !(next.discountPercent > 0)) {
      nudgeEl.hidden = true;
      nudgeEl.innerHTML = '';
      return;
    }
    nudgeEl.hidden = false;
    nudgeEl.innerHTML =
      'Sumá <strong>' +
      Pricing.formatAttentions(needed) +
      ' atenciones / mes más</strong> y obtené <strong>' +
      next.discountPercent +
      '% de descuento</strong>';
  }

  function recalc() {
    if (!config) return;
    const selection = currentSelection();
    const result = Pricing.estimate(config, selection);
    refreshUnitLabels();
    buildVolumeDiscountPanel(result.tier && result.tier.id);
    updateVolumeNudge(result);

    if (totalEl) {
      totalEl.textContent = result.lines.length
        ? result.formattedTotal + ' / mes'
        : '—';
    }
    if (breakdownEl) {
      if (!result.lines.length) {
        breakdownEl.innerHTML =
          '<p class="pricing-calc__hint">Activá al menos un tipo de atención e indicá el volumen mensual.</p>';
      } else {
        let meta = '';
        if (result.discountPercent > 0) {
          meta =
            '<div class="pricing-calc__line pricing-calc__line--savings">' +
            '<span>Incluye <strong>' +
            Math.round(result.discountPercent) +
            '% de descuento</strong> por volumen</span>' +
            '<strong>−' +
            Pricing.formatMoney(result.listTotal - result.total, result.currency) +
            '</strong></div>';
        }
        breakdownEl.innerHTML =
          meta +
          result.lines
            .map((l) => {
              let note = '';
              if (l.code === 'AMB') {
                const bits = [];
                if (selection.addons.videollamada) bits.push('con videollamada');
                else if (selection.addons.audio) bits.push('con dictado');
                note = bits.length ? ' · ' + bits.join(' · ') : '';
              }
              return (
                '<div class="pricing-calc__line"><span>' +
                Pricing.formatAttentions(l.qty) +
                ' atenciones × ' +
                l.label +
                note +
                ' (' +
                Pricing.formatMoney(l.unit, result.currency) +
                '/atención)</span><strong>' +
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
          return Pricing.formatAttentions(l.qty) + ' atenciones ' + l.label + extra;
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
    const totalAtt = Pricing.totalAttentionsFromSelection(selection);
    rowsEl.querySelectorAll('[data-class]').forEach((row) => {
      const code = row.getAttribute('data-class');
      const unitEl = row.querySelector('.pricing-calc__unit');
      if (unitEl && code) {
        unitEl.textContent =
          Pricing.formatMoney(
            Pricing.unitPriceForClass(config, code, addons, totalAtt > 0 ? totalAtt : null),
            config.currency
          ) + ' / atención';
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

  function buildAttentionsSelectHtml(code, selected) {
    const scale = Pricing.attentionVolumeScale(config);
    const opts = scale
      .map((n) => {
        const sel = String(n) === String(selected) ? ' selected' : '';
        return (
          '<option value="' +
          n +
          '"' +
          sel +
          '>' +
          Pricing.formatAttentions(n) +
          ' / mes</option>'
        );
      })
      .join('');
    return (
      '<label class="pricing-calc__qty">Atenciones / mes' +
      '<select data-attentions aria-label="Atenciones mensuales ' +
      code +
      '">' +
      '<option value="">Elegí un volumen</option>' +
      opts +
      '</select></label>'
    );
  }

  function buildRows() {
    if (!rowsEl || !config) return;
    const classes = config.sellable_classes || {};
    const scale = Pricing.attentionVolumeScale(config);
    const defaultVol = scale[2] || scale[0] || 5000;
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
        ' / atención</span>' +
        '</span></label>' +
        optionsHtml +
        '</div>' +
        buildAttentionsSelectHtml(code, '');
      rowsEl.appendChild(row);
      const checkbox = row.querySelector('input[name="class_' + code + '"]');
      const select = row.querySelector('[data-attentions]');
      syncQtyDisabled(row);
      checkbox.addEventListener('change', () => {
        syncQtyDisabled(row);
        refreshUnitLabels();
        recalc();
      });
      select.addEventListener('change', () => {
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

    if (mode === 'signup') {
      const amb = rowsEl.querySelector('[data-class="AMB"]');
      if (amb) {
        const cb = amb.querySelector('input[name="class_AMB"]');
        const qty = amb.querySelector('[data-attentions]');
        if (cb) cb.checked = true;
        if (qty) qty.value = String(defaultVol);
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
    if (footnotes) {
      const items = Array.isArray(sim.footnotes) ? sim.footnotes.filter(Boolean) : [];
      if (!items.length) {
        footnotes.innerHTML = '';
        footnotes.hidden = true;
      } else {
        footnotes.hidden = false;
        footnotes.innerHTML = items.map((f) => '<li>' + f + '</li>').join('');
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
      return Pricing.toSignupPlan(currentSelection(), config);
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
