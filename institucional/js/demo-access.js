/**
 * Acceso demo sandbox desde el sitio institucional.
 * POST /api/v1/licencia/demo-acceso → redirect a enter_url (app /site/demo-entrar).
 * Captcha: GET /api/v1/licencia/demo-captcha (challenge en cache, sin cookie de sesión).
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
  var captchaWrap = root.querySelector('[data-demo-captcha-wrap]');
  var captchaImg = root.querySelector('[data-demo-captcha-img]');
  var captchaInput = root.querySelector('[data-demo-captcha-input]');
  var captchaIdInput = root.querySelector('[data-demo-captcha-id]');
  var captchaRefresh = root.querySelector('[data-demo-captcha-refresh]');
  var requireCaptcha = false;

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
        var host = window.location.hostname;
        var isLocal = host === 'localhost' || host === '127.0.0.1';
        var base = isLocal && cfg.apiBaseUrlLocal ? cfg.apiBaseUrlLocal : cfg.apiBaseUrl;
        if (base) {
          apiBase = String(base).replace(/\/$/, '');
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

  function loadCaptcha() {
    if (!requireCaptcha || !captchaWrap) {
      return Promise.resolve();
    }
    captchaWrap.hidden = false;
    if (captchaInput) {
      captchaInput.required = true;
      captchaInput.value = '';
    }
    return api('/licencia/demo-captcha').then(function (r) {
      if (!r.ok || !r.data || !r.data.data) {
        showStatus('No se pudo cargar el captcha.', false);
        return;
      }
      var d = r.data.data;
      if (captchaImg && d.image_data_url) {
        captchaImg.src = String(d.image_data_url);
      }
      if (captchaIdInput) {
        captchaIdInput.value = String(d.challenge_id || '');
      }
    }).catch(function () {
      showStatus('No se pudo cargar el captcha.', false);
    });
  }

  function fillProfiles() {
    return api('/licencia/demo-perfiles').then(function (r) {
      if (!roleSelect) return;
      roleSelect.innerHTML = '';
      if (!r.ok || !r.data || !r.data.data || r.data.data.enabled === false) {
        root.hidden = true;
        return;
      }
      var payload = r.data.data;
      requireCaptcha = !!payload.require_captcha;
      var items = payload.items || [];
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
      if (captchaWrap && !requireCaptcha) {
        captchaWrap.hidden = true;
        if (captchaInput) captchaInput.required = false;
      }
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
    loadCaptcha();
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
    var captcha = captchaInput ? String(captchaInput.value || '').trim() : '';
    var captchaChallengeId = captchaIdInput ? String(captchaIdInput.value || '') : '';
    if (requireCaptcha && (!captcha || !captchaChallengeId)) {
      showStatus('Completá el captcha.', false);
      return;
    }
    if (submitBtn) {
      submitBtn.disabled = true;
    }
    showStatus('Generando acceso…', true);
    var body = {
      role: role,
      email: String(email).trim(),
      website: String(website),
    };
    if (requireCaptcha) {
      body.captcha = captcha;
      body.captcha_challenge_id = captchaChallengeId;
    }
    api('/licencia/demo-acceso', {
      method: 'POST',
      body: body,
    }).then(function (r) {
      if (!r.ok || !r.data || !r.data.data || !r.data.data.enter_url) {
        var msg = (r.data && (r.data.message || (r.data.data && r.data.data.message))) || 'No se pudo abrir la demo.';
        showStatus(msg, false);
        loadCaptcha();
        return;
      }
      showStatus('Redirigiendo a la app…', true);
      window.location.href = String(r.data.data.enter_url);
    }).catch(function () {
      showStatus('Error de red. Reintentá.', false);
      loadCaptcha();
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
    if (captchaRefresh) {
      captchaRefresh.addEventListener('click', function (e) {
        e.preventDefault();
        loadCaptcha();
      });
    }
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
