/**
 * Alta self-service de clínica / solicitud ministerio (sitio institucional).
 * Depende de js/api-config.json y endpoints /api/v1/licencia/*.
 */
(function () {
  'use strict';

  var apiBase = 'http://localhost/api/v1';
  var loginUrl = 'http://localhost/site/login';

  function $(sel, root) {
    return (root || document).querySelector(sel);
  }

  function showStatus(el, msg, ok) {
    if (!el) return;
    el.hidden = false;
    el.textContent = msg;
    el.className = 'signup-status ' + (ok ? 'signup-status--ok' : 'signup-status--err');
  }

  function loadConfig() {
    return fetch('js/api-config.json', { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (cfg) {
        if (cfg.apiBaseUrl) apiBase = String(cfg.apiBaseUrl).replace(/\/$/, '');
        if (cfg.loginUrl) loginUrl = String(cfg.loginUrl);
      })
      .catch(function () { /* defaults */ });
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
    var sector = ($('input[name="sector"]:checked') || {}).value;
    var wrap = $('#ministerio-wrap');
    var pagoMin = $('#pago-ministerio-wrap');
    if (!wrap) return;
    var isPublic = sector === 'PUBLICO';
    wrap.hidden = !isPublic;
    if (pagoMin) pagoMin.hidden = !isPublic;
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
    if (form.card_number) form.card_number.required = !covered;
    if (form.card_holder) form.card_holder.required = !covered;
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

  function buildPlanFromForm(form) {
    var maxAmb = Math.max(1, parseInt(form.max_pes_amb.value, 10) || 5);
    var classes = {
      AMB: {
        max_pes: maxAmb,
        dictado_incluido: !!form.audio.checked,
        videollamada_permitida: !!form.videollamada.checked,
      },
    };
    if (form.incluir_emer && form.incluir_emer.checked) {
      classes.EMER = {
        max_pes: Math.max(1, parseInt(form.max_pes_emer.value, 10) || 2),
        dictado_incluido: true,
        videollamada_permitida: false,
      };
    }
    if (form.incluir_imp && form.incluir_imp.checked) {
      classes.IMP = {
        max_pes: Math.max(1, parseInt(form.max_pes_imp.value, 10) || 2),
        dictado_incluido: true,
        videollamada_permitida: false,
      };
    }
    return { classes: classes };
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
    if (minSelect) fillMinisterios(minSelect);

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var sector = (form.querySelector('input[name="sector"]:checked') || {}).value;
      var body = {
        sector: sector,
        id_billing_account_ministerio: sector === 'PUBLICO'
          ? parseInt(form.id_billing_account_ministerio.value, 10) || 0
          : null,
        pago_cubierto_por_ministerio: !!(form.pago_cubierto_por_ministerio && form.pago_cubierto_por_ministerio.checked),
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
        plan: buildPlanFromForm(form),
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

      showStatus(status, 'Procesando alta y pago simulado…', true);
      form.querySelector('[type="submit"]').disabled = true;

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
        tabs.forEach(function (t) {
          t.classList.toggle('is-active', t === tab);
          t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
        });
        panels.forEach(function (p) {
          p.hidden = p.getAttribute('data-signup-panel') !== id;
        });
      });
    });
  }

  loadConfig().then(function () {
    initTabs();
    initEfectorForm();
    initMinisterioForm();
  });
})();
