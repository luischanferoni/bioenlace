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
  var roleWrap = root.querySelector('[data-demo-role-wrap]');
  var submitBtn = root.querySelector('[data-demo-submit]');
  var captchaWrap = root.querySelector('[data-demo-captcha-wrap]');
  var captchaImg = root.querySelector('[data-demo-captcha-img]');
  var captchaInput = root.querySelector('[data-demo-captcha-input]');
  var captchaIdInput = root.querySelector('[data-demo-captcha-id]');
  var captchaRefresh = root.querySelector('[data-demo-captcha-refresh]');
  var requireCaptcha = false;
  var demoReady = false;
  var openButtons = [];

  function showStatus(msg, ok) {
    if (!statusEl) return;
    statusEl.hidden = false;
    statusEl.textContent = msg;
    statusEl.className = 'demo-access__status ' + (ok ? 'demo-access__status--ok' : 'demo-access__status--err');
  }

  function setOpenButtonsVisible(visible) {
    openButtons.forEach(function (btn) {
      btn.hidden = !visible;
      if (visible) {
        btn.removeAttribute('aria-hidden');
      } else {
        btn.setAttribute('aria-hidden', 'true');
      }
    });
  }

  function loadApiConfig() {
    return fetch('js/api-config.json', { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (cfg) {
        var host = window.location.hostname;
        var isLocal = host === 'localhost'
          || host === '127.0.0.1'
          || host === '[::1]'
          || host.endsWith('.local')
          || host.endsWith('.test')
          || host.endsWith('.localhost');
        var base = isLocal && cfg.apiBaseUrlLocal ? cfg.apiBaseUrlLocal : cfg.apiBaseUrl;
        if (base) {
          apiBase = String(base).replace(/\/$/, '');
        }
        if (window.console && console.info) {
          console.info('[demo-access] API', apiBase, '(host=' + host + ', local=' + isLocal + ')');
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
      return res.text().then(function (text) {
        var data = null;
        if (text) {
          try {
            data = JSON.parse(text);
          } catch (e) {
            return { ok: false, status: res.status, data: { message: 'Respuesta inválida del servidor.' } };
          }
        }
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

  function applyProfiles(items) {
    if (!roleSelect) {
      return false;
    }
    roleSelect.innerHTML = '';
    if (!items || items.length === 0) {
      return false;
    }
    items.forEach(function (it, idx) {
      var opt = document.createElement('option');
      opt.value = String(it.role);
      opt.textContent = it.label || it.role;
      if (idx === 0) {
        opt.selected = true;
      }
      roleSelect.appendChild(opt);
    });
    // Un solo perfil (staff): no hace falta elegir.
    if (roleWrap) {
      if (items.length === 1) {
        roleWrap.hidden = true;
        roleSelect.required = false;
      } else {
        roleWrap.hidden = false;
        roleSelect.required = true;
      }
    }
    return true;
  }

  function fillProfiles() {
    return api('/licencia/demo-perfiles').then(function (r) {
      demoReady = false;
      if (!r.ok || !r.data || !r.data.data || r.data.data.enabled === false) {
        setOpenButtonsVisible(false);
        if (roleSelect) {
          roleSelect.innerHTML = '';
          var empty = document.createElement('option');
          empty.value = '';
          empty.textContent = 'Demo no disponible';
          roleSelect.appendChild(empty);
        }
        return false;
      }
      var payload = r.data.data;
      requireCaptcha = !!payload.require_captcha;
      var items = payload.items || [];
      if (!applyProfiles(items)) {
        setOpenButtonsVisible(false);
        return false;
      }
      demoReady = true;
      setOpenButtonsVisible(true);
      if (captchaWrap && !requireCaptcha) {
        captchaWrap.hidden = true;
        if (captchaInput) {
          captchaInput.required = false;
        }
      }
      return true;
    }).catch(function () {
      demoReady = false;
      setOpenButtonsVisible(false);
      return false;
    });
  }

  function openModal() {
    root.classList.add('is-open');
    root.setAttribute('aria-hidden', 'false');
    if (statusEl) {
      statusEl.hidden = true;
      statusEl.textContent = '';
    }
    if (!demoReady) {
      showStatus('Cargando perfiles…', true);
      fillProfiles().then(function (ok) {
        if (!ok) {
          showStatus('La demo no está habilitada o no hay perfiles. Revisá demo_sandbox_habilitado y la API.', false);
          return;
        }
        if (statusEl) {
          statusEl.hidden = true;
        }
        loadCaptcha();
      });
      return;
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
    if (!demoReady) {
      showStatus('La demo no está disponible.', false);
      return;
    }
    var email = (form.querySelector('[name="email"]') || {}).value || '';
    var website = (form.querySelector('[name="website"]') || {}).value || '';
    var role = roleSelect ? roleSelect.value : 'staff';
    if (!role) {
      showStatus('Elegí un perfil.', false);
      return;
    }
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

  openButtons = Array.prototype.slice.call(document.querySelectorAll('[data-demo-open]'));
  setOpenButtonsVisible(false);

  loadApiConfig().then(fillProfiles).then(function () {
    openButtons.forEach(function (btn) {
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
