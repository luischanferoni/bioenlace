/**
 * Headers unificados para llamadas a /api/v1 desde el frontend web (SPA y vistas Yii).
 *
 * - Base: {@see window.getBioenlaceApiClientHeaders} (layouts main / main_sinmenuizquierda: Bearer, X-App-*, X-Client).
 * - Extensión: pasar `extra` para añadir o sobrescribir cabeceras puntuales.
 *
 * Ejemplo:
 *   BioenlaceApiClient.mergeHeaders({ 'X-Requested-With': 'XMLHttpRequest' });
 */
(function (window) {
  'use strict';

  var NS = (window.BioenlaceApiClient = window.BioenlaceApiClient || {});

  /**
   * @param {Object<string,string>=} extra Se fusiona encima del base (Object.assign final).
   * @returns {Object<string,string>}
   */
  NS.mergeHeaders = function (extra) {
    var base = {};
    if (typeof window.getBioenlaceApiClientHeaders === 'function') {
      base = window.getBioenlaceApiClientHeaders({});
    } else {
      var v =
        window.spaConfig && window.spaConfig.appVersion
          ? String(window.spaConfig.appVersion)
          : '1.0.0';
      base = {
        'X-App-Client': 'web-frontend',
        'X-App-Version': v,
        'X-Client': 'web',
      };
    }
    return Object.assign({}, base, extra || {});
  };

  /** Alias de {@link NS.mergeHeaders} (mismo contrato). */
  NS.apiHeaders = NS.mergeHeaders;

  NS.logoutUrl = function () {
    return '/auth/logout';
  };

  /**
   * @param {number} status HTTP status
   * @param {object|null|undefined} body JSON parseado
   * @returns {boolean}
   */
  NS.isUnauthorizedApi = function (status, body) {
    if (status === 401) {
      return true;
    }
    if (!body || typeof body !== 'object' || body.success !== false) {
      return false;
    }
    var msg = String(body.message || body.error || '').toLowerCase();
    if (!msg) {
      return false;
    }
    if (msg.indexOf('autenticado') !== -1) {
      return true;
    }
    if (msg.indexOf('credenciales') !== -1) {
      return true;
    }
    if (msg.indexOf('token') !== -1 && (msg.indexOf('inválido') !== -1 || msg.indexOf('invalido') !== -1 || msg.indexOf('expirado') !== -1)) {
      return true;
    }
    return false;
  };

  NS.redirectToLoginOnUnauthorized = function () {
    if (window.__bioenlaceRedirectingToLogin) {
      return;
    }
    window.__bioenlaceRedirectingToLogin = true;
    window.location.replace(NS.logoutUrl());
  };

  /**
   * @param {number} status
   * @param {object|null|undefined} body
   * @returns {boolean} true si redirigió
   */
  NS.handleUnauthorized = function (status, body) {
    if (!NS.isUnauthorizedApi(status, body)) {
      return false;
    }
    NS.redirectToLoginOnUnauthorized();
    return true;
  };

  /**
   * fetch + JSON con redirección a login en 401.
   * @param {string} url
   * @param {RequestInit=} options
   * @returns {Promise<{response: Response, json: *}>}
   */
  NS.fetchJson = function (url, options) {
    var opts = Object.assign({ credentials: 'same-origin' }, options || {});
    if (!opts.headers) {
      opts.headers = NS.mergeHeaders({ Accept: 'application/json' });
    }
    return window.fetch(url, opts).then(function (response) {
      var ct = (response.headers.get('content-type') || '').toLowerCase();
      if (!ct.includes('application/json')) {
        if (NS.handleUnauthorized(response.status, null)) {
          return { response: response, json: null };
        }
        return response.text().then(function (text) {
          throw new Error('Se esperaba JSON. Recibido: ' + String(text || '').slice(0, 120));
        });
      }
      return response.json().then(function (json) {
        if (NS.handleUnauthorized(response.status, json)) {
          return { response: response, json: json };
        }
        if (!response.ok) {
          var msg = json && (json.message || json.error) ? (json.message || json.error) : ('HTTP ' + response.status);
          throw new Error(String(msg));
        }
        return { response: response, json: json };
      });
    });
  };

  /**
   * Path HTTP bajo `/api/v1/...` (p. ej. `/api/clinical/...` de RBAC → `/api/v1/clinical/...`).
   * @param {string} path
   * @returns {string}
   */
  NS.normalizeApiV1Path = function (path) {
    var p = String(path || '').trim();
    if (!p) return '';
    if (/^https?:\/\//i.test(p)) return p;
    if (p.charAt(0) !== '/') p = '/' + p;
    if (/^\/api\/v\d+\//i.test(p)) return p;
    if (p.indexOf('/api/') === 0) return '/api/v1/' + p.slice(5);
    return '/api/v1/' + p.replace(/^\//, '');
  };
})(window);
