/**
 * Calculador: atenciones/mes × clase. Dictado incluido; videollamada opcional solo en AMB.
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
    const video = row.querySelector('input[data-addon="videollamada"]');
    if (video) {
      video.disabled = !on;
      if (!on) video.checked = false;
    }
  }

  function syncQtyDisabled(row) {
    const enabled = row.querySelector('input[type="checkbox"][name^="class_"]');
    const hidden = row.querySelector('input[data-attentions]');
    const chips = row.querySelectorAll('.pricing-calc__chip');
    if (!enabled || !hidden) return;
    const on = !!enabled.checked;
    chips.forEach((chip) => {
      chip.disabled = !on;
      chip.setAttribute('aria-disabled', on ? 'false' : 'true');
    });
    if (!on) {
      hidden.value = '';
      chips.forEach((chip) => chip.classList.remove('is-selected'));
    } else if (!hidden.value) {
      const def = Pricing.defaultAttentions(config, 'CLINICA');
      setVolume(row, def);
    }
    syncAmbOptions(row);
  }

  function setVolume(row, attentions) {
    const hidden = row.querySelector('input[data-attentions]');
    const chips = row.querySelectorAll('.pricing-calc__chip');
    if (!hidden) return;
    const n = String(attentions);
    hidden.value = n;
    chips.forEach((chip) => {
      chip.classList.toggle('is-selected', chip.getAttribute('data-value') === n);
    });
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
          '<p class="pricing-calc__hint">Activá al menos un tipo de atención y elegí un volumen.</p>';
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
                note = selection.addons.videollamada
                  ? ' · con videollamada'
                  : ' · dictado incluido';
              } else {
                note = ' · dictado incluido';
              }
              return (
                '<div class="pricing-calc__line"><span>' +
                Pricing.formatVolumeChoice(config, l.qty) +
                ' · ' +
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
          let extra = l.code === 'AMB' && selection.addons.videollamada ? ' con videollamada' : '';
          return Pricing.formatVolumeChoice(config, l.qty) + ' ' + l.label + extra;
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
    const video = (config.addons && config.addons.videollamada) || {};
    return (
      '<div class="pricing-calc__amb-options">' +
      '<span class="pricing-calc__policy">Dictado incluido</span>' +
      '<label class="pricing-calc__option">' +
      '<input type="checkbox" data-addon="videollamada" disabled />' +
      '<span><strong>' +
      (video.label || 'Videollamada') +
      '</strong> <em>(opcional)</em></span></label>' +
      '</div>'
    );
  }

  function buildFixedPolicyHtml(code) {
    if (Pricing.classIncludesAudio(config, code) && !Pricing.classAllowsVideollamada(config, code)) {
      return '<p class="pricing-calc__policy">Dictado incluido · Sin videollamada</p>';
    }
    return '<p class="pricing-calc__policy">Dictado incluido</p>';
  }

  function buildVolumeChipsHtml(code, selected) {
    const presets = Pricing.attentionVolumePresets(config);
    const chips = presets
      .map((p) => {
        const sel = String(p.attentions) === String(selected) ? ' is-selected' : '';
        const title = p.hint ? ' title="' + String(p.hint).replace(/"/g, '&quot;') + '"' : '';
        return (
          '<button type="button" class="pricing-calc__chip' +
          sel +
          '" data-value="' +
          p.attentions +
          '"' +
          title +
          ' disabled>' +
          '<strong>' +
          p.label +
          '</strong>' +
          '<span>' +
          Pricing.formatAttentions(p.attentions) +
          '/mes</span>' +
          '</button>'
        );
      })
      .join('');
    return (
      '<div class="pricing-calc__qty">' +
      '<span class="pricing-calc__qty-label">Volumen mensual</span>' +
      '<div class="pricing-calc__chips" role="group" aria-label="Volumen mensual ' +
      code +
      '">' +
      chips +
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
    const defaultVol = Pricing.defaultAttentions(config, 'CLINICA');
    rowsEl.innerHTML = '';
    Object.keys(classes).forEach((code) => {
      const cls = classes[code];
      const unit = Pricing.unitPriceForClass(config, code, { audio: true });
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
        includesListHtml(cls) +
        optionsHtml +
        '</div>' +
        buildVolumeChipsHtml(code, '');
      rowsEl.appendChild(row);
      const checkbox = row.querySelector('input[name="class_' + code + '"]');
      syncQtyDisabled(row);
      checkbox.addEventListener('change', () => {
        syncQtyDisabled(row);
        refreshUnitLabels();
        recalc();
      });
      row.querySelectorAll('.pricing-calc__chip').forEach((chip) => {
        chip.addEventListener('click', () => {
          if (chip.disabled) return;
          setVolume(row, chip.getAttribute('data-value'));
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
        setVolume(amb, defaultVol);
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

  function includesListHtml(cls) {
    // En precios.html el detalle ya está en las cards; no repetir en cada fila.
    if (document.getElementById('pricing-includes-grid')) return '';
    const items = Array.isArray(cls.includes) ? cls.includes.filter(Boolean) : [];
    if (!items.length) return '';
    return (
      '<ul class="pricing-calc__includes">' +
      items.map((item) => '<li>' + escapeHtml(item) + '</li>').join('') +
      '</ul>'
    );
  }

  function fillPlansPageCopy() {
    const page = config.plans_page || {};
    const setText = (id, value) => {
      const el = document.getElementById(id);
      if (el && value) el.textContent = value;
    };
    setText('plans-page-title', page.title);
    setText('plans-page-subtitle', page.subtitle);
    setText('plans-includes-heading', page.includes_heading);
    setText('plans-includes-lead', page.includes_lead);
    setText('plans-excludes-heading', page.excludes_heading);
    if (page.calc_heading) {
      const calcTitle = document.getElementById('pricing-title');
      if (calcTitle && document.body.classList.contains('pricing-page')) {
        calcTitle.textContent = page.calc_heading;
      }
    }

    const grid = document.getElementById('pricing-includes-grid');
    if (grid) {
      const classes = config.sellable_classes || {};
      grid.innerHTML = Object.keys(classes)
        .map((code) => {
          const cls = classes[code] || {};
          const unit = Pricing.unitPriceForClass(config, code, { audio: true });
          const items = Array.isArray(cls.includes) ? cls.includes.filter(Boolean) : [];
          return (
            '<article class="pricing-includes__card" data-class="' +
            escapeHtml(code) +
            '">' +
            '<header class="pricing-includes__card-head">' +
            '<h3>' +
            escapeHtml(cls.label || code) +
            '</h3>' +
            '<p class="pricing-includes__short">' +
            escapeHtml(cls.short || '') +
            '</p>' +
            '<p class="pricing-includes__unit">' +
            escapeHtml(Pricing.formatMoney(unit, config.currency)) +
            ' / atención</p>' +
            '</header>' +
            (items.length
              ? '<ul class="pricing-includes__list">' +
                items.map((item) => '<li>' + escapeHtml(item) + '</li>').join('') +
                '</ul>'
              : '') +
            '</article>'
          );
        })
        .join('');
    }

    const excludesSection = document.getElementById('no-incluye');
    const excludesList = document.getElementById('pricing-excludes-list');
    const excludes = Array.isArray(page.excludes) ? page.excludes.filter(Boolean) : [];
    if (excludesSection && excludesList) {
      if (!excludes.length) {
        excludesSection.hidden = true;
        excludesList.innerHTML = '';
      } else {
        excludesSection.hidden = false;
        excludesList.innerHTML = excludes.map((item) => '<li>' + escapeHtml(item) + '</li>').join('');
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
