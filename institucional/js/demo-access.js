/**
 * Acceso demo sandbox desde el sitio institucional.
 * POST /api/v1/licencia/demo-acceso → redirect a enter_url (app /site/demo-entrar).
 */
(function () {
  'use strict';

  var apiBase = 'http://localhost/api/v1';
  var root = document.getElementById('demo-access');
  if (!root) {
    return;
  }

  var form = root.querySelector('[data-demo-form]');
  var statusEl = root.querySelector('[data-demo-status]');
  var roleSelect = root.querySelector('[data-demo-role]');
  var submitBtn = root.querySelector('[data-demo-submit]');
  var openBtn = root.querySelector('[data-demo-open]');

  function showStatus(msg, ok) {
    if (!statusEl) return;
    statusEl.hidden = false;
    statusEl.textContent = msg;
    statusEl.className = 'demo-access__status ' + (ok ? 'demo-access__status--ok' : 'demo-access__status--err');
  }

  function loadApiConfig() {
    return fetch('js/api-config.json', { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (cfg) {
        if (cfg.apiBaseUrl) {
          apiBase = String(cfg.apiBaseUrl).replace(/\/$/, '');
        }
      })
      .catch(function () { /* defaults */ });
  }

  function api(path, opts) {
    opts = opts || {};
    var headers = Object.assign({ Accept: 'application/json' }, opts.headers || {});
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

  function fillProfiles() {
    return api('/licencia/demo-perfiles').then(function (r) {
      if (!roleSelect) return;
      roleSelect.innerHTML = '';
      if (!r.ok || !r.data || !r.data.data) {
        root.hidden = true;
        return;
      }
      var items = r.data.data.items || [];
      if (items.length === 0) {
        root.hidden = true;
        return;
      }
      root.hidden = false;
      items.forEach(function (it, idx) {
        var opt = document.createElement('option');
        opt.value = String(it.role);
        opt.textContent = it.label || it.role;
        if (idx === 0) opt.selected = true;
        roleSelect.appendChild(opt);
      });
    }).catch(function () {
      root.hidden = true;
    });
  }

  function openModal() {
    root.classList.add('is-open');
    root.setAttribute('aria-hidden', 'false');
    if (statusEl) {
      statusEl.hidden = true;
      statusEl.textContent = '';
    }
  }

  function closeModal() {
    root.classList.remove('is-open');
    root.setAttribute('aria-hidden', 'true');
  }

  function onSubmit(ev) {
    ev.preventDefault();
    if (!form) return;
    var email = (form.querySelector('[name="email"]') || {}).value || '';
    var website = (form.querySelector('[name="website"]') || {}).value || '';
    var role = roleSelect ? roleSelect.value : 'staff';
    if (submitBtn) {
      submitBtn.disabled = true;
    }
    showStatus('Generando acceso…', true);
    api('/licencia/demo-acceso', {
      method: 'POST',
      body: {
        role: role,
        email: String(email).trim(),
        website: String(website),
      },
    }).then(function (r) {
      if (!r.ok || !r.data || !r.data.data || !r.data.data.enter_url) {
        var msg = (r.data && (r.data.message || (r.data.data && r.data.data.message))) || 'No se pudo abrir la demo.';
        showStatus(msg, false);
        return;
      }
      showStatus('Redirigiendo a la app…', true);
      window.location.href = String(r.data.data.enter_url);
    }).catch(function () {
      showStatus('Error de red. Reintentá.', false);
    }).finally(function () {
      if (submitBtn) submitBtn.disabled = false;
    });
  }

  loadApiConfig().then(fillProfiles).then(function () {
    document.querySelectorAll('[data-demo-open]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        openModal();
      });
    });
    root.querySelectorAll('[data-demo-close]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        closeModal();
      });
    });
    if (form) {
      form.addEventListener('submit', onSubmit);
    }
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && root.classList.contains('is-open')) {
        closeModal();
      }
    });
  });
})();
