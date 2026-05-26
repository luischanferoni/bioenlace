/**
 * Alta estructurada de internación vía API (plantillas + checklist).
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

  function initAlta(root) {
    var internacionId = root.getAttribute('data-internacion-id');
    if (!internacionId) return;

    var selPlantilla = document.getElementById('alta-plantilla-id');
    var taEpicrisis = document.getElementById('alta-epicrisis');
    if (selPlantilla && taEpicrisis) {
      selPlantilla.addEventListener('change', function () {
        var pid = selPlantilla.value;
        if (!pid) return;
        apiFetch(
          '/clinical/internacion/' + internacionId + '/preview-plantilla-epicrisis?plantilla_id=' + encodeURIComponent(pid)
        )
          .then(function (r) { return r.json(); })
          .then(function (json) {
            if (json && json.success && json.data && json.data.epicrisis) {
              taEpicrisis.value = json.data.epicrisis;
            }
          });
      });
    }

    var btn = document.getElementById('alta-api-submit');
    if (!btn) return;
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var payload = {
        fecha_fin: (document.getElementById('alta-fecha-fin') || {}).value || '',
        hora_fin: (document.getElementById('alta-hora-fin') || {}).value || '',
        id_tipo_alta: (document.getElementById('alta-tipo-alta') || {}).value || '',
        plantilla_id: selPlantilla ? selPlantilla.value : '',
        epicrisis: taEpicrisis ? taEpicrisis.value : '',
        checklist_medicacion: document.getElementById('alta-chk-med') && document.getElementById('alta-chk-med').checked ? '1' : '',
        checklist_indicaciones: document.getElementById('alta-chk-ind') && document.getElementById('alta-chk-ind').checked ? '1' : '',
        checklist_pedidos: document.getElementById('alta-chk-ped') && document.getElementById('alta-chk-ped').checked ? '1' : '',
      };
      apiFetch('/clinical/internacion/' + internacionId + '/alta-formulario', {
        method: 'POST',
        body: JSON.stringify(payload),
      })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (json && json.success) {
            window.location.href = root.getAttribute('data-redirect-url') || window.location.href;
            return;
          }
          alert((json && json.message) || (json && json.errors && json.errors._error && json.errors._error[0]) || 'Error al registrar alta.');
        });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('internacion-alta-api');
    if (root) initAlta(root);
  });
})();
