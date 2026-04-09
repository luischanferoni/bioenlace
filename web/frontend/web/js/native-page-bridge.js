/**
 * Bridge para páginas nativas (tipo 1): helpers reutilizables para:
 * - URLs de /api/v1 sin duplicar "/api"
 * - fetch JSON con headers vía {@link window.BioenlaceApiClient.mergeHeaders}
 * - navegación opcional dentro del shell SPA vía data-spa-nav
 */
(function () {
  'use strict';

  /**
   * Construye URL absoluta a /api/v1/<path> evitando duplicación "/api/api".
   * @param {string} path e.g. "pacientes" o "/pacientes"
   */
  function apiV1Url(path) {
    var p = String(path || '');
    if (!p) return window.location.origin + '/api/v1';
    if (!p.startsWith('/')) p = '/' + p;
    if (p.startsWith('/api/v1/')) return window.location.origin + p;
    return window.location.origin + '/api/v1' + p;
  }

  async function fetchJson(url, options) {
    var opts = Object.assign({}, options || {});
    opts.headers = window.BioenlaceApiClient.mergeHeaders(
      Object.assign({ Accept: 'application/json' }, opts.headers || {})
    );
    opts.credentials = opts.credentials || 'same-origin';
    var res = await fetch(url, opts);
    var ct = (res.headers.get('content-type') || '').toLowerCase();
    if (!ct.includes('application/json')) {
      var text = await res.text();
      throw new Error('Se esperaba JSON. Recibido: ' + text.slice(0, 120));
    }
    var json = await res.json();
    if (!res.ok) {
      throw new Error((json && (json.message || json.error)) ? (json.message || json.error) : ('HTTP ' + res.status));
    }
    return json;
  }

  function bindSpaNavLinks(root) {
    var base = root && typeof root.addEventListener === 'function' ? root : document;
    base.addEventListener('click', function (e) {
      var a = e.target && e.target.closest ? e.target.closest('a[data-spa-nav="1"]') : null;
      if (!a) return;
      if (a.hasAttribute('download') || a.getAttribute('target') === '_blank') return;
      var href = a.getAttribute('href') || '';
      if (!href || href === '#') return;

      if (typeof window.spaNavigateToUrl === 'function') {
        e.preventDefault();
        var title = a.getAttribute('data-spa-title') || a.textContent || 'Cargando...';
        window.spaNavigateToUrl(href, title);
      }
    }, true);
  }

  window.BioenlaceNativePage = window.BioenlaceNativePage || {};
  window.BioenlaceNativePage.mergeHeaders = window.BioenlaceApiClient.mergeHeaders;
  window.BioenlaceNativePage.apiHeaders = window.BioenlaceApiClient.mergeHeaders;
  window.BioenlaceNativePage.apiV1Url = apiV1Url;
  window.BioenlaceNativePage.fetchJson = fetchJson;
  window.BioenlaceNativePage.bindSpaNavLinks = bindSpaNavLinks;
})();
