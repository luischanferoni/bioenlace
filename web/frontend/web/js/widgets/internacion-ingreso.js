/**
 * Ingreso a internación vía API v1.
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

  function val(id) {
    var el = document.getElementById(id);
    return el ? el.value : '';
  }

  function initIngreso(root) {
    var btn = document.getElementById('ingreso-api-submit');
    if (!btn) return;

    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var payload = {
        id_persona: root.getAttribute('data-id-persona') || '',
        id_cama: val('ingreso-id-cama-hidden') || val('ingreso-id-cama'),
        id_guardia: root.getAttribute('data-id-guardia') || '',
        id_profesional_efector_servicio: val('ingreso-id-pes'),
        fecha_inicio: val('ingreso-fecha-inicio'),
        hora_inicio: val('ingreso-hora-inicio'),
        id_tipo_ingreso: val('ingreso-id-tipo-ingreso'),
        id_efector_origen: val('ingreso-id-efector-origen'),
        ingresa_en: val('ingreso-ingresa-en'),
        ingresa_con: val('ingreso-ingresa-con'),
        datos_contacto_nombre: val('ingreso-contacto-nombre'),
        datos_contacto_tel: val('ingreso-contacto-tel'),
        situacion_al_ingresar: val('ingreso-situacion'),
        obra_social: val('ingreso-obra-social'),
        condiciones_derivacion: val('ingreso-condiciones-derivacion'),
      };
      apiFetch('/clinical/internacion/ingreso-formulario', {
        method: 'POST',
        body: JSON.stringify(payload),
      })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (json && json.success) {
            var id = json.data && json.data.internacion_id;
            if (id) {
              window.location.href = '/internacion/view?id=' + id;
              return;
            }
          }
          alert((json && json.message) || (json && json.errors && json.errors._error && json.errors._error[0]) || 'Error al registrar el ingreso.');
        });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('internacion-ingreso-api');
    if (root) initIngreso(root);
  });
})();
