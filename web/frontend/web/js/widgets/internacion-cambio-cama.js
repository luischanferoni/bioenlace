/**
 * Cambio de cama de internación vía API v1.
 */
(function () {
  'use strict';

  function apiUrl(path) {
    return (window.location.origin || '') + '/api/v1' + path;
  }

  function apiFetch(path, options) {
    var opts = options || {};
    var headers = window.BioenlaceApiClient
      ? window.BioenlaceApiClient.mergeHeaders(opts.headers || {})
      : {};
    headers['Content-Type'] = 'application/json';
    return fetch(apiUrl(path), Object.assign({}, opts, { headers: headers, credentials: 'same-origin' }));
  }

  function initCambioCama(root) {
    var internacionId = root.getAttribute('data-internacion-id');
    if (!internacionId) return;

    var btn = document.getElementById('cambio-cama-api-submit');
    if (!btn) return;

    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var payload = {
        id_cama: (document.getElementById('cambio-cama-id-cama') || {}).value || '',
        motivo: (document.getElementById('cambio-cama-motivo') || {}).value || '',
      };
      apiFetch('/clinical/internacion/' + internacionId + '/cambio-cama-formulario', {
        method: 'POST',
        body: JSON.stringify(payload),
      })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (json && json.success) {
            window.location.href = root.getAttribute('data-redirect-url') || window.location.href;
            return;
          }
          alert((json && json.message) || (json && json.errors && json.errors._error && json.errors._error[0]) || 'Error al cambiar de cama.');
        });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('internacion-cambio-cama-api');
    if (root) initCambioCama(root);
  });
})();
