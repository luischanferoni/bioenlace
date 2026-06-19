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

  var webJwtRefreshInFlight = null;

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
   * Headers para endpoints que autentican solo con cookie de sesión (sin Bearer).
   * @param {Object<string,string>=} extra
   * @returns {Object<string,string>}
   */
  NS.mergeSessionHeaders = function (extra) {
    var headers = NS.mergeHeaders(extra || {});
    delete headers.Authorization;
    delete headers.authorization;
    return headers;
  };

  /**
   * @param {number} status HTTP status
   * @param {object|null|undefined} body JSON parseado
   * @returns {boolean}
   */
  NS.isUnauthorizedApi = function (status, body) {
    return status === 401;
  };

  NS.redirectToLoginOnUnauthorized = function () {
    if (window.__bioenlaceRedirectingToLogin) {
      return;
    }
    window.__bioenlaceRedirectingToLogin = true;
    window.location.replace(NS.logoutUrl());
  };

  /**
   * Obtiene el JWT vigente de la sesión web (cookie) y actualiza window.apiAuthToken.
   * @returns {Promise<boolean>}
   */
  NS.refreshWebJwtFromSession = function () {
    if (webJwtRefreshInFlight) {
      return webJwtRefreshInFlight;
    }

    var url = NS.logoutUrl().replace(/\/logout\/?$/, '/web-jwt');
    if (!/^https?:\/\//i.test(url)) {
      url = window.location.origin + url;
    }

    webJwtRefreshInFlight = window
      .fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: NS.mergeSessionHeaders({ Accept: 'application/json' }),
      })
      .then(function (response) {
        return response.json().then(function (json) {
          if (!response.ok || !json || json.success !== true) {
            return false;
          }
          var token = json.data && json.data.token ? String(json.data.token) : '';
          if (!token) {
            return false;
          }
          window.apiAuthToken = token;
          return true;
        });
      })
      .catch(function () {
        return false;
      })
      .finally(function () {
        webJwtRefreshInFlight = null;
      });

    return webJwtRefreshInFlight;
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
   * Extrae mensaje legible del cuerpo JSON de error API ({ message, error, errors._error }).
   * @param {string|object|null|undefined} body
   * @returns {string}
   */
  NS.extractApiErrorMessage = function (body) {
    if (body == null) {
      return '';
    }
    var j = body;
    if (typeof body === 'string') {
      var t = String(body).trim();
      if (t === '') {
        return '';
      }
      try {
        j = JSON.parse(t);
      } catch (e) {
        return '';
      }
    }
    if (!j || typeof j !== 'object') {
      return '';
    }
    if (j.message != null && String(j.message).trim() !== '') {
      return String(j.message).trim();
    }
    if (j.error != null && String(j.error).trim() !== '') {
      return String(j.error).trim();
    }
    if (j.errors && typeof j.errors === 'object') {
      var err = j.errors._error;
      if (Array.isArray(err) && err.length >= 1 && err[0] != null && String(err[0]).trim() !== '') {
        return String(err[0]).trim();
      }
    }
    return '';
  };

  /**
   * @param {Response} response
   * @returns {Promise<never>}
   */
  NS.errorFromFailedResponse = function (response) {
    return response.text().then(function (text) {
      var msg = NS.extractApiErrorMessage(text);
      if (msg) {
        throw new Error(msg);
      }
      throw new Error('HTTP ' + response.status);
    });
  };

  /**
   * @param {string} url
   * @param {RequestInit} opts
   * @param {boolean} jwtRetried
   * @returns {Promise<{response: Response, json: *}>}
   */
  function fetchJsonInternal(url, opts, jwtRetried) {
    return window.fetch(url, opts).then(function (response) {
      var ct = (response.headers.get('content-type') || '').toLowerCase();
      if (!ct.includes('application/json')) {
        if (NS.isUnauthorizedApi(response.status, null)) {
          if (!jwtRetried) {
            return NS.refreshWebJwtFromSession().then(function (ok) {
              if (!ok) {
                NS.handleUnauthorized(response.status, null);
                return { response: response, json: null };
              }
              var retryOpts = Object.assign({}, opts, {
                headers: NS.mergeHeaders(opts.headers || {}),
              });
              return fetchJsonInternal(url, retryOpts, true);
            });
          }
          NS.handleUnauthorized(response.status, null);
          return { response: response, json: null };
        }
        return response.text().then(function (text) {
          throw new Error('Se esperaba JSON. Recibido: ' + String(text || '').slice(0, 120));
        });
      }

      return response.json().then(function (json) {
        if (NS.isUnauthorizedApi(response.status, json)) {
          if (!jwtRetried) {
            return NS.refreshWebJwtFromSession().then(function (ok) {
              if (!ok) {
                NS.handleUnauthorized(response.status, json);
                return { response: response, json: json };
              }
              var retryOpts = Object.assign({}, opts, {
                headers: NS.mergeHeaders(opts.headers || {}),
              });
              return fetchJsonInternal(url, retryOpts, true);
            });
          }
          NS.handleUnauthorized(response.status, json);
          return { response: response, json: json };
        }
        if (!response.ok) {
          var msg = NS.extractApiErrorMessage(json);
          if (!msg) {
            msg = 'HTTP ' + response.status;
          }
          throw new Error(String(msg));
        }
        return { response: response, json: json };
      });
    });
  }

  /**
   * fetch + JSON con reintento de JWT vía sesión y redirección a login si persiste 401.
   * @param {string} url
   * @param {RequestInit=} options
   * @returns {Promise<{response: Response, json: *}>}
   */
  NS.fetchJson = function (url, options) {
    var opts = Object.assign({ credentials: 'same-origin' }, options || {});
    if (!opts.headers) {
      opts.headers = NS.mergeHeaders({ Accept: 'application/json' });
    }
    return fetchJsonInternal(url, opts, false);
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
