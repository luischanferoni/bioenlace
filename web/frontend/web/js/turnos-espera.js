/**
 * Lista de espera: botón «No se presentó».
 */
(function () {
  'use strict';

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('#no_se_presento');
    if (!btn) {
      return;
    }
    e.preventDefault();
    if (!window.confirm('Seguro?')) {
      return;
    }
    var idTurno = btn.getAttribute('href');
    if (!idTurno) {
      return;
    }
    var headers =
      typeof window.getBioenlaceApiClientHeaders === 'function'
        ? window.getBioenlaceApiClientHeaders()
        : {};
    fetch('/api/v1/turnos/' + encodeURIComponent(idTurno) + '/no-se-presento', {
      method: 'POST',
      headers: headers,
      credentials: 'same-origin',
      body: '',
    }).then(function () {
      btn.setAttribute('disabled', 'disabled');
      var cargar = document.getElementById('cargar_consulta');
      if (cargar) {
        cargar.setAttribute('disabled', 'disabled');
      }
      window.location.reload();
    });
  });
})();
