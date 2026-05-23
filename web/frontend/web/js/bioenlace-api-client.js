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
