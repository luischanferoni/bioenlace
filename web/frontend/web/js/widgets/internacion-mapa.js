/**
 * Mapa de camas: marcar bloqueada/aislamiento/libre vía API v1.
 * Los KPIs viven en el panel de inicio (no duplicar resumen_texto aquí).
 */
(function () {
  'use strict';

  function apiUrl(path) {
    var origin = window.location.origin || '';
    return origin + '/api/v1' + path;
  }

  function apiFetch(path, options) {
    var opts = options || {};
    var headers = window.BioenlaceApiClient
      ? window.BioenlaceApiClient.mergeHeaders(opts.headers || {})
      : (opts.headers || {});
    if (!headers['Content-Type'] && opts.method === 'POST') {
      headers['Content-Type'] = 'application/json';
    }
    return fetch(apiUrl(path), Object.assign({}, opts, { headers: headers, credentials: 'same-origin' }));
  }

  function marcarCama(camaId, estado, motivo) {
    var body = { estado_mapa: estado };
    if (motivo) body.motivo = motivo;
    return apiFetch('/clinical/internacion/cama/' + camaId + '/marcar-estado', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    }).then(function (r) { return r.json(); });
  }

  function bindMapaActions() {
    document.querySelectorAll('[data-internacion-cama-action]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var camaId = btn.getAttribute('data-cama-id');
        var estado = btn.getAttribute('data-estado-mapa');
        if (!camaId || !estado) return;
        var motivo = null;
        if (estado === 'bloqueada' || estado === 'aislamiento') {
          motivo = window.prompt('Motivo (opcional):', '') || '';
        }
        marcarCama(camaId, estado, motivo).then(function (json) {
          if (json && json.success) {
            window.location.reload();
          } else {
            alert((json && json.message) || 'No se pudo actualizar la cama.');
          }
        }).catch(function () {
          alert('Error de red al actualizar la cama.');
        });
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (!document.getElementById('internacion-mapa-root')) return;
    bindMapaActions();
  });
})();
