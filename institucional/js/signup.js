/**
 * Alta self-service de clínica / solicitud ministerio (sitio institucional).
 * Depende de js/api-config.json, pricing-core.js y endpoints /api/v1/licencia/*.
 */
(function () {
  'use strict';

  var apiBase = 'http://localhost/api/v1';
  var loginUrl = 'http://localhost/site/login';
  var pricingConfig = null;

  function $(sel, root) {
    return (root || document).querySelector(sel);
  }

  function showStatus(el, msg, ok) {
    if (!el) return;
    el.hidden = false;
    el.textContent = msg;
    el.className = 'signup-status ' + (ok ? 'signup-status--ok' : 'signup-status--err');
  }

  function loadApiConfig() {
    return fetch('js/api-config.json', { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (cfg) {
        if (cfg.apiBaseUrl) apiBase = String(cfg.apiBaseUrl).replace(/\/$/, '');
        if (cfg.loginUrl) loginUrl = String(cfg.loginUrl);
      })
      .catch(function () { /* defaults */ });
  }

  function loadPricingConfig() {
    return fetch('js/pricing-config.json', { cache: 'no-cache' })
      .then(function (r) {
        if (!r.ok) throw new Error('pricing');
        return r.json();
      })
      .then(function (cfg) {
        pricingConfig = cfg;
      })
      .catch(function () {
        pricingConfig = null;
      });
  }

  function api(path, opts) {
    opts = opts || {};
    var headers = Object.assign({ 'Accept': 'application/json' }, opts.headers || {});
    if (opts.body && !headers['Content-Type']) {
      headers['Content-Type'] = 'application/json';
    }
    return fetch(apiBase + path, {
      method: opts.method || 'GET',
      headers: headers,
      body: opts.body ? JSON.stringify(opts.body) : undefined,
      credentials: 'omit',
    }).then(function (res) {
      return res.json().then(function (data) {
        return { ok: res.ok && data && data.success !== false, status: res.status, data: data };
      });
    });
  }

  function fillMinisterios(select) {
    return api('/licencia/catalogo-ministerios').then(function (r) {
      if (!r.ok || !r.data || !r.data.data) return;
      var items = r.data.data.items || [];
      select.innerHTML = '<option value="">Elegí un ministerio…</option>';
      items.forEach(function (it) {
        var opt = document.createElement('option');
        opt.value = String(it.id);
        opt.textContent = it.nombre;
        select.appendChild(opt);
      });
    });
  }

  function syncSectorUi() {
    var form = $('#form-efector');
    var perfil = (form && form.perfil && form.perfil.value) || 'CLINICA';
    var isConsultorio = perfil === 'CONSULTORIO';
    var sectorPrivado = form && form.querySelector('input[name="sector"][value="PRIVADO"]');
    var publicoLabel = $('#sector-publico-label');
    var consultorioHint = $('#sector-consultorio-hint');
    if (publicoLabel) publicoLabel.hidden = isConsultorio;
    if (consultorioHint) consultorioHint.hidden = !isConsultorio;
    if (isConsultorio && sectorPrivado) {
      sectorPrivado.checked = true;
    }

    var sector = ($('input[name="sector"]:checked') || {}).value;
    var wrap = $('#ministerio-wrap');
    var pagoMin = $('#pago-ministerio-wrap');
    if (!wrap) return;
    var isPublic = !isConsultorio && sector === 'PUBLICO';
    wrap.hidden = !isPublic;
    if (pagoMin) {
      pagoMin.hidden = !isPublic;
      if (!isPublic && form && form.pago_cubierto_por_ministerio) {
        form.pago_cubierto_por_ministerio.checked = false;
      }
    }
    var sel = $('#id_billing_account_ministerio');
    if (sel) sel.required = isPublic;
    syncPaymentUi();
  }

  function syncPaymentUi() {
    var form = $('#form-efector');
    if (!form) return;
    var covered = !!(form.pago_cubierto_por_ministerio && form.pago_cubierto_por_ministerio.checked);
    var payFs = $('#payment-fieldset');
    if (payFs) payFs.hidden = covered;
    if (form.sim_token) form.sim_token.required = !covered;
    if (form.sim_titular) form.sim_titular.required = !covered;
  }

  function resolveSimPan(token) {
    var t = String(token || '').trim().toUpperCase().replace(/\s+/g, '');
    if (t === '' || t === 'SIM-OK' || t === 'OK') {
      return '4242424242424242';
    }
    if (t === 'SIM-FAIL' || t === 'FAIL') {
      return '4000000000000002';
    }
    return String(token || '').replace(/\D+/g, '') || '4242424242424242';
  }

  function currentPerfil(form) {
    return (form && form.perfil && form.perfil.value) || 'CLINICA';
  }

  function planInput(form, name) {
    return form ? form.querySelector('[name="' + name + '"]') : null;
  }

  function setClinicaOnlyRowsVisible(visible) {
    ['row-emer', 'row-imp'].forEach(function (id) {
      var row = document.getElementById(id);
      if (!row) return;
      row.classList.toggle('is-off', !visible);
      row.hidden = !visible;
      row.style.display = visible ? '' : 'none';
      row.setAttribute('aria-hidden', visible ? 'false' : 'true');
      row.querySelectorAll('input').forEach(function (input) {
        if (!visible) {
          if (input.type === 'checkbox') input.checked = false;
          input.disabled = true;
        }
      });
    });
  }

  function syncPlanRowUi(form) {
    if (!form) return;
    var isConsultorio = currentPerfil(form) === 'CONSULTORIO';
    var ambCb = planInput(form, 'incluir_amb');
    var ambQty = planInput(form, 'max_pes_amb');
    var ambOn = isConsultorio || !!(ambCb && ambCb.checked);

    setClinicaOnlyRowsVisible(!isConsultorio);

    if (ambCb) {
      ambCb.disabled = isConsultorio;
      if (isConsultorio) ambCb.checked = true;
    }
    if (ambQty) {
      if (isConsultorio) {
        ambQty.value = '1';
        ambQty.disabled = true;
        ambQty.readOnly = true;
      } else {
        ambQty.readOnly = false;
        ambQty.disabled = !ambOn;
        if (ambOn && (parseInt(ambQty.value, 10) || 0) < 1) {
          ambQty.value = '1';
        }
      }
    }
    var addonsWrap = document.getElementById('amb-addons');
    if (addonsWrap) {
      addonsWrap.classList.toggle('is-disabled', !ambOn);
      addonsWrap.setAttribute('aria-disabled', ambOn ? 'false' : 'true');
    }
    [planInput(form, 'audio'), planInput(form, 'videollamada')].forEach(function (input) {
      if (!input) return;
      input.disabled = !ambOn;
      if (!ambOn) input.checked = false;
    });

    if (isConsultorio) return;

    function syncOptional(cbName, qtyName) {
      var cb = planInput(form, cbName);
      var qty = planInput(form, qtyName);
      if (!cb || !qty) return;
      cb.disabled = false;
      var on = !!cb.checked;
      qty.disabled = !on;
      if (on && (parseInt(qty.value, 10) || 0) < 1) qty.value = '1';
    }
    syncOptional('incluir_emer', 'max_pes_emer');
    syncOptional('incluir_imp', 'max_pes_imp');
  }

  function readSelectionFromForm(form) {
    var classes = {};
    var isConsultorio = currentPerfil(form) === 'CONSULTORIO';
    var ambCb = planInput(form, 'incluir_amb');
    var ambQty = planInput(form, 'max_pes_amb');
    var ambOn = isConsultorio || !!(ambCb && ambCb.checked);
    if (ambOn && ambQty) {
      var amb = isConsultorio ? 1 : Math.max(0, parseInt(ambQty.value, 10) || 0);
      if (amb > 0) classes.AMB = amb;
    }
    var emerCb = planInput(form, 'incluir_emer');
    var emerQty = planInput(form, 'max_pes_emer');
    if (!isConsultorio && emerCb && emerCb.checked && emerQty) {
      classes.EMER = Math.max(1, parseInt(emerQty.value, 10) || 1);
    }
    var impCb = planInput(form, 'incluir_imp');
    var impQty = planInput(form, 'max_pes_imp');
    if (!isConsultorio && impCb && impCb.checked && impQty) {
      classes.IMP = Math.max(1, parseInt(impQty.value, 10) || 1);
    }
    var audio = planInput(form, 'audio');
    var video = planInput(form, 'videollamada');
    return {
      classes: classes,
      addons: {
        audio: !!(ambOn && audio && audio.checked),
        videollamada: !!(ambOn && video && video.checked),
      },
    };
  }

  function buildPlanFromForm(form) {
    var selection = readSelectionFromForm(form);
    var isConsultorio = currentPerfil(form) === 'CONSULTORIO';
    if (isConsultorio && !selection.classes.AMB) {
      throw new Error('El consultorio profesional requiere ambulatorio.');
    }
    if (!selection.classes.AMB && !selection.classes.EMER && !selection.classes.IMP) {
      throw new Error('Elegí al menos un tipo de atención (ambulatorio, urgencia o internación).');
    }
    if (window.BioenlacePricing && window.BioenlacePricing.toSignupPlan) {
      return window.BioenlacePricing.toSignupPlan(selection);
    }
    // Fallback sin pricing-core
    var plan = { classes: {} };
    Object.keys(selection.classes).forEach(function (code) {
      plan.classes[code] = {
        max_pes: selection.classes[code],
        dictado_incluido: code === 'AMB' ? selection.addons.audio : true,
        videollamada_permitida: code === 'AMB' ? selection.addons.videollamada : false,
      };
    });
    return plan;
  }

  function updatePriceIndicator(form) {
    var totalEl = $('#signup-price-total');
    var linesEl = $('#signup-price-lines');
    var noteEl = $('#signup-price-note');
    if (!totalEl || !linesEl) return;

    if (!pricingConfig || !window.BioenlacePricing) {
      totalEl.textContent = '—';
      linesEl.innerHTML = '';
      if (noteEl) noteEl.textContent = 'No se pudo cargar el estimado de precios.';
      return;
    }

    var selection = readSelectionFromForm(form);
    var result = window.BioenlacePricing.estimate(pricingConfig, selection);
    if (!result.lines.length) {
      totalEl.textContent = '—';
      linesEl.innerHTML = '<div class="signup-price__line"><span>Activá al menos un tipo de atención.</span></div>';
      if (noteEl) noteEl.textContent = pricingConfig.tax_note || '';
      return;
    }

    totalEl.textContent = result.formattedTotal + ' / mes';
    linesEl.innerHTML = result.lines.map(function (l) {
      var note = '';
      if (l.code === 'AMB') {
        var bits = [];
        if (selection.addons.audio) bits.push('dictado');
        if (selection.addons.videollamada) bits.push('videollamada');
        if (bits.length) note = ' · ' + bits.join(' · ');
      }
      return (
        '<div class="signup-price__line"><span>' +
        l.qty + ' × ' + l.label + note +
        '</span><strong>' +
        window.BioenlacePricing.formatMoney(l.line, result.currency) +
        '</strong></div>'
      );
    }).join('');
    if (noteEl) noteEl.textContent = pricingConfig.tax_note || 'Orientativo; no incluye IVA ni IIBB.';
  }

  function initPriceIndicator(form) {
    form.querySelectorAll('[data-plan-input]').forEach(function (el) {
      el.addEventListener('input', function () {
        syncPlanRowUi(form);
        updatePriceIndicator(form);
      });
      el.addEventListener('change', function () {
        syncPlanRowUi(form);
        updatePriceIndicator(form);
      });
    });
    syncPlanRowUi(form);
    updatePriceIndicator(form);
  }

  function initEfectorForm() {
    var form = $('#form-efector');
    if (!form) return;
    var status = $('#efector-status');
    var minSelect = $('#id_billing_account_ministerio');
    document.querySelectorAll('input[name="sector"]').forEach(function (el) {
      el.addEventListener('change', syncSectorUi);
    });
    if (form.pago_cubierto_por_ministerio) {
      form.pago_cubierto_por_ministerio.addEventListener('change', syncPaymentUi);
    }
    syncSectorUi();
    initPriceIndicator(form);
    if (minSelect) fillMinisterios(minSelect);

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var perfil = (form.perfil && form.perfil.value) || 'CLINICA';
      var sector = (form.querySelector('input[name="sector"]:checked') || {}).value;
      if (perfil === 'CONSULTORIO') {
        sector = 'PRIVADO';
      }
      var body = {
        perfil: perfil,
        sector: sector,
        id_billing_account_ministerio: sector === 'PUBLICO'
          ? parseInt(form.id_billing_account_ministerio.value, 10) || 0
          : null,
        pago_cubierto_por_ministerio: sector === 'PUBLICO'
          && !!(form.pago_cubierto_por_ministerio && form.pago_cubierto_por_ministerio.checked),
        admin: {
          nombre: form.admin_nombre.value.trim(),
          apellido: form.admin_apellido.value.trim(),
          email: form.admin_email.value.trim(),
          password: form.admin_password.value,
          documento: form.admin_documento.value.trim(),
          telefono: form.admin_telefono.value.trim(),
          fecha_nacimiento: form.admin_fecha_nacimiento.value || '1980-01-01',
        },
        efector: {
          nombre: form.efector_nombre.value.trim(),
          domicilio: form.efector_domicilio.value.trim(),
          telefono: form.efector_telefono.value.trim(),
        },
        payment: {
          card_number: resolveSimPan(
            (form.sim_token && form.sim_token.value)
              || (form.card_number && form.card_number.value)
              || ''
          ),
          card_holder: (form.sim_titular && form.sim_titular.value.trim())
            || (form.card_holder && form.card_holder.value.trim())
            || '',
        },
      };

      try {
        body.plan = buildPlanFromForm(form);
      } catch (err) {
        showStatus(status, err.message || 'Revisá el plan elegido.', false);
        return;
      }

      showStatus(status, 'Procesando alta y pago simulado…', true);
      form.querySelector('[type="submit"]').disabled = true;
      var nextBox = $('#efector-next-steps');
      if (nextBox) nextBox.hidden = true;

      api('/licencia/registrar-efector', { method: 'POST', body: body })
        .then(function (r) {
          form.querySelector('[type="submit"]').disabled = false;
          if (!r.ok) {
            showStatus(status, (r.data && r.data.message) || 'No se pudo completar el alta.', false);
            return;
          }
          var d = r.data.data || {};
          var msg = 'Listo. Usuario: ' + (d.username || d.email) + '. ';
          if (d.payment_reference) {
            msg += 'Pago simulado ref. ' + d.payment_reference + '. ';
          }
          if (d.pago_cubierto_por_ministerio) {
            msg += 'Pediste cobertura ministerial (pendiente de aprobación). ';
          }
          msg += 'Podés ingresar en la plataforma.';
          showStatus(status, msg, true);
          renderNextSteps(d.next_steps || []);
          var link = $('#login-link');
          if (link) {
            link.href = loginUrl;
            link.hidden = false;
          }
        })
        .catch(function () {
          form.querySelector('[type="submit"]').disabled = false;
          showStatus(status, 'Error de red. Verificá apiBaseUrl en js/api-config.json.', false);
        });
    });
  }

  function renderNextSteps(steps) {
    var box = $('#efector-next-steps');
    if (!box) return;
    if (!steps || !steps.length) {
      box.hidden = true;
      box.innerHTML = '';
      return;
    }
    var items = steps.map(function (s) {
      return '<li>' + String(s) + '</li>';
    }).join('');
    box.innerHTML = '<h3>Próximos pasos</h3><ol>' + items + '</ol>';
    box.hidden = false;
  }

  function applyPerfilUi(perfil) {
    var form = $('#form-efector');
    var perfilInput = $('#signup-perfil');
    if (perfilInput) perfilInput.value = perfil;
    var isConsultorio = perfil === 'CONSULTORIO';

    var title = $('#efector-panel-title');
    var hint = $('#efector-panel-hint');
    var adminLegend = $('#admin-legend');
    var centroLegend = $('#centro-legend');
    var nombreLabel = $('#efector_nombre_label');
    var planHint = $('#plan-hint');
    var ambLabel = $('#max_pes_amb_label');

    if (title) {
      title.textContent = isConsultorio
        ? 'Registrar mi consultorio'
        : 'Registrar mi clínica o centro';
    }
    if (hint) {
      hint.textContent = isConsultorio
        ? 'Creás tu usuario, tu consultorio privado y la licencia (1 profesional ambulatorio). Si trabajás en un hospital o clínica pública que ya usa Bioenlace, no uses este alta: pedí que administración del centro te sume. Después te guiamos para asignarte a un servicio clínico.'
        : 'Vas a crear tu usuario administrador, el centro y la licencia. El cobro de esta demo es simulado (no se debita una tarjeta real).';
    }
    if (adminLegend) adminLegend.textContent = isConsultorio ? 'Tus datos' : 'Administrador';
    if (centroLegend) centroLegend.textContent = isConsultorio ? 'Consultorio' : 'Centro de salud';
    if (nombreLabel) nombreLabel.textContent = isConsultorio ? 'Nombre del consultorio' : 'Nombre del centro';
    if (planHint) {
      planHint.textContent = isConsultorio
        ? 'Licencia unipersonal: 1 profesional en ambulatorio. Podés sumar dictado y/o videollamada. Si necesitás más profesionales u otros tipos de atención, usá el alta de clínica / centro.'
        : 'Elegí ambulatorio, urgencia y/o internación (al menos uno). En ambulatorio podés sumar dictado y/o videollamada.';
    }
    if (ambLabel) {
      ambLabel.textContent = isConsultorio
        ? 'Ambulatorio (1 profesional)'
        : 'Incluir ambulatorio';
    }

    if (form) {
      if (isConsultorio) {
        var incluirAmb = planInput(form, 'incluir_amb');
        var maxAmb = planInput(form, 'max_pes_amb');
        if (incluirAmb) incluirAmb.checked = true;
        if (maxAmb) maxAmb.value = '1';
        var privado = form.querySelector('input[name="sector"][value="PRIVADO"]');
        if (privado) privado.checked = true;
        if (form.pago_cubierto_por_ministerio) form.pago_cubierto_por_ministerio.checked = false;
      } else {
        var ambCb = planInput(form, 'incluir_amb');
        var emerCb = planInput(form, 'incluir_emer');
        var impCb = planInput(form, 'incluir_imp');
        var maxAmbClin = planInput(form, 'max_pes_amb');
        if (ambCb && !ambCb.checked
            && !(emerCb && emerCb.checked) && !(impCb && impCb.checked)) {
          ambCb.checked = true;
        }
        if (ambCb && ambCb.checked && maxAmbClin
            && parseInt(maxAmbClin.value, 10) === 1) {
          maxAmbClin.value = '5';
        }
      }
      syncSectorUi();
      syncPlanRowUi(form);
      updatePriceIndicator(form);
    }
  }

  function initMinisterioForm() {
    var form = $('#form-ministerio');
    if (!form) return;
    var status = $('#ministerio-status');
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var body = {
        nombre_organizacion: form.nombre_organizacion.value.trim(),
        contacto_nombre: form.contacto_nombre.value.trim(),
        contacto_apellido: form.contacto_apellido.value.trim(),
        contacto_email: form.contacto_email.value.trim(),
        contacto_telefono: form.contacto_telefono.value.trim(),
        contacto_documento: form.contacto_documento.value.trim(),
        notas: form.notas.value.trim(),
      };
      showStatus(status, 'Enviando solicitud…', true);
      api('/licencia/solicitar-ministerio', { method: 'POST', body: body })
        .then(function (r) {
          if (!r.ok) {
            showStatus(status, (r.data && r.data.message) || 'No se pudo enviar.', false);
            return;
          }
          showStatus(status, 'Solicitud recibida. Te contactaremos para verificar la cuenta.', true);
          form.reset();
        })
        .catch(function () {
          showStatus(status, 'Error de red. Verificá apiBaseUrl en js/api-config.json.', false);
        });
    });
  }

  function initTabs() {
    var tabs = document.querySelectorAll('[data-signup-tab]');
    var panels = document.querySelectorAll('[data-signup-panel]');
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var id = tab.getAttribute('data-signup-tab');
        var perfil = tab.getAttribute('data-perfil') || 'CLINICA';
        tabs.forEach(function (t) {
          t.classList.toggle('is-active', t === tab);
          t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
        });
        panels.forEach(function (p) {
          p.hidden = p.getAttribute('data-signup-panel') !== id;
        });
        if (id === 'efector') {
          applyPerfilUi(perfil);
        }
      });
    });

    // Deep-link: alta.html?perfil=consultorio
    var params = new URLSearchParams(window.location.search || '');
    var q = String(params.get('perfil') || '').toUpperCase();
    if (q === 'CONSULTORIO' || q === 'PROFESIONAL') {
      var consultorioTab = document.querySelector('[data-signup-tab="efector"][data-perfil="CONSULTORIO"]');
      if (consultorioTab) consultorioTab.click();
    } else {
      applyPerfilUi('CLINICA');
    }
  }

  Promise.all([loadApiConfig(), loadPricingConfig()]).then(function () {
    initTabs();
    initEfectorForm();
    initMinisterioForm();
  });
})();
