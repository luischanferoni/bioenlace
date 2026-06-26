/**
 * Registro de paciente por personal (admin): lector DNI PDF417 y Didit (foto).
 */
(function () {
  'use strict';

  var cfg = window.BioenlaceRegistroPacienteStaff || {};
  var scanAttached = false;

  function el(id) {
    return document.getElementById(id);
  }

  function showAlert(type, message) {
    if (window.Swal) {
      Swal.fire({ icon: type, text: message });
      return;
    }
    window.alert(message);
  }

  function setLoading(on) {
    var spin = el('registro-paciente-cover-spin');
    if (spin) {
      spin.style.display = on ? 'block' : 'none';
    }
  }

  function postJson(url, body) {
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': cfg.csrfToken || '',
      },
      credentials: 'same-origin',
      body: JSON.stringify(body || {}),
    }).then(function (res) {
      return res.json().then(function (data) {
        return { ok: res.ok, status: res.status, data: data };
      });
    });
  }

  function parseDniBarcode(sCode) {
    var expEs =
      /^[0-9]{11}@[A-ZÁÉÍÓÚÑ ]+@[A-ZÁÉÍÓÚÑ ]+@[MF]@([MF]|[0-9])?[0-9]{7}@[A-Z]{1}@[0-9]{2}\/[0-9]{2}\/[0-9]{4}@[0-9]{2}\/[0-9]{2}\/[0-9]{4}(@[0-9]{3})?$/;
    var expEn =
      /^[0-9]{11}"[A-ZÁÉÍÓÚÑ ]+"[A-ZÁÉÍÓÚÑ ]+"[MF]"([MF]|[0-9])?[0-9]{7}"[A-Z]{1}"[0-9]{2}[0-9]{2}[0-9]{4}"[0-9]{2}[0-9]{2}[0-9]{4}("[0-9]{3})?$/;
    var expLib =
      /^"[0-9]?[0-9]{7}"[A-Z]{1}"[0-9]{1}"[A-ZÁÉÍÓÚÑ ]+"[A-ZÁÉÍÓÚÑ ]+"[A-ZÁÉÍÓÚÑ ]+"[0-9]{2}[0-9]{2}[0-9]{4}"[MF]"[0-9]{2}[0-9]{2}[0-9]{4}/;

    if (!expEs.test(sCode) && !expEn.test(sCode) && !expLib.test(sCode)) {
      return null;
    }

    var parts = sCode.indexOf('"') >= 0 ? sCode.split('"') : sCode.split('@');
    var sexoLetra = null;
    var documento = null;
    var sexoBiologico = null;

    if (parts[8] === 'M' || parts[8] === 'F') {
      sexoLetra = parts[8];
      documento = (parts[1] || '').trim();
      sexoBiologico = sexoLetra === 'F' ? 1 : 2;
    } else if (parts[3] === 'M' || parts[3] === 'F') {
      sexoLetra = parts[3];
      documento = (parts[4] || '').trim();
      sexoBiologico = sexoLetra === 'F' ? 1 : 2;
    }

    if (!documento) {
      return null;
    }

    return {
      codigo_barras: sCode,
      documento: documento,
      sexo_biologico: sexoBiologico,
      sexo_letra: sexoLetra,
    };
  }

  function renderPreview(data) {
    var box = el('registro-paciente-preview');
    if (!box) return;
    if (!data || !data.encontrado) {
      box.innerHTML =
        '<div class="alert alert-warning">No se encontró en RENAPER. Revisá el código o intentá de nuevo.</div>';
      return;
    }
    var r = data.renaper || {};
    var nombre = Array.isArray(r.nombres) ? r.nombres[0] : r.nombres || '';
    var apellido = Array.isArray(r.apellido) ? r.apellido[0] : r.apellido || '';
    box.innerHTML =
      '<div class="alert alert-success">' +
      '<strong>' +
      (apellido + ', ' + nombre).trim() +
      '</strong><br>' +
      'DNI: ' +
      (r.numeroDocumento || data.identity.documento || '') +
      '<br>Verificación de domicilio RENAPER se iniciará automáticamente tras el alta.' +
      '</div>';
    box.dataset.codigoBarras = data.identity && data.identity.codigo_barras ? data.identity.codigo_barras : '';
    if (data.identity && data.identity.codigo_barras) {
      el('registro-paciente-codigo').value = data.identity.codigo_barras;
    }
  }

  function attachScanner() {
    if (scanAttached || typeof onScan === 'undefined') return;
    scanAttached = true;
    onScan.attachTo(document, {
      reactToKeyDown: true,
      reactToPaste: true,
      timeBeforeScanTest: 200,
      avgTimeByChar: 30,
      onScan: function (sCode) {
        var parsed = parseDniBarcode(sCode);
        if (!parsed) {
          showAlert('error', 'Código de DNI no reconocido.');
          return;
        }
        el('registro-paciente-codigo').value = sCode;
        el('registro-paciente-documento').textContent = parsed.documento;
        el('registro-paciente-sexo').textContent = parsed.sexo_letra;
        setLoading(true);
        postJson(cfg.urls.previewRenaper, { codigo_barras: sCode })
          .then(function (res) {
            setLoading(false);
            if (!res.ok || res.data.success === false) {
              showAlert('error', (res.data && res.data.message) || 'Error consultando RENAPER');
              return;
            }
            var payload = res.data.data || res.data;
            if (payload.identity) {
              payload.identity.codigo_barras = sCode;
            }
            renderPreview(payload);
          })
          .catch(function () {
            setLoading(false);
            showAlert('error', 'Error de red al consultar RENAPER.');
          });
      },
    });
  }

  function registrarDesdeLector() {
    var codigo = (el('registro-paciente-codigo').value || '').trim();
    if (!codigo) {
      showAlert('warning', 'Escaneá el código del DNI primero.');
      return;
    }
    setLoading(true);
    postJson(cfg.urls.registrar, { modo: 'dni_lector', codigo_barras: codigo })
      .then(function (res) {
        setLoading(false);
        var data = res.data || {};
        if (!res.ok || data.success === false) {
          showAlert('error', data.message || 'No se pudo registrar al paciente.');
          return;
        }
        var persona = (data.data && data.data.persona) || data.persona;
        var id = persona && persona.id_persona;
        showAlert('success', 'Paciente registrado correctamente.');
        if (id && cfg.urls.verPersona) {
          window.location.href = cfg.urls.verPersona.replace('__ID__', String(id));
        }
      })
      .catch(function () {
        setLoading(false);
        showAlert('error', 'Error de red al registrar.');
      });
  }

  function iniciarDidit() {
    setLoading(true);
    postJson(cfg.urls.crearSesionDidit, {
      callback: cfg.urls.diditCallback || window.location.href,
    })
      .then(function (res) {
        setLoading(false);
        var data = res.data || {};
        if (!res.ok || data.success === false) {
          showAlert('error', data.message || 'No se pudo iniciar Didit.');
          return;
        }
        var url = (data.data && data.data.url) || data.url;
        if (!url) {
          showAlert('error', 'Didit no devolvió URL de verificación.');
          return;
        }
        window.location.href = url;
      })
      .catch(function () {
        setLoading(false);
        showAlert('error', 'Error de red al crear sesión Didit.');
      });
  }

  function registrarDesdeDidit() {
    var verificationId =
      (cfg.diditVerificationId || '').trim() ||
      (el('registro-paciente-verification-id') &&
        el('registro-paciente-verification-id').value.trim()) ||
      '';
    if (!verificationId) {
      showAlert('warning', 'Completá la verificación Didit primero.');
      return;
    }
    setLoading(true);
    postJson(cfg.urls.registrar, { modo: 'didit', verification_id: verificationId })
      .then(function (res) {
        setLoading(false);
        var data = res.data || {};
        if (!res.ok || data.success === false) {
          showAlert('error', data.message || 'No se pudo registrar al paciente.');
          return;
        }
        var persona = (data.data && data.data.persona) || data.persona;
        var id = persona && persona.id_persona;
        showAlert('success', 'Paciente registrado con Didit.');
        if (id && cfg.urls.verPersona) {
          window.location.href = cfg.urls.verPersona.replace('__ID__', String(id));
        }
      })
      .catch(function () {
        setLoading(false);
        showAlert('error', 'Error de red al registrar.');
      });
  }

  function init() {
    var lectorSwitch = el('registro-paciente-lector');
    if (lectorSwitch) {
      lectorSwitch.addEventListener('change', function () {
        if (lectorSwitch.checked) {
          attachScanner();
        } else if (typeof onScan !== 'undefined') {
          onScan.detachFrom(document);
          scanAttached = false;
        }
      });
      if (lectorSwitch.checked) {
        attachScanner();
      }
    }

    var btnLector = el('btn-registrar-desde-lector');
    if (btnLector) btnLector.addEventListener('click', registrarDesdeLector);

    var btnDidit = el('btn-iniciar-didit');
    if (btnDidit) btnDidit.addEventListener('click', iniciarDidit);

    var btnDiditReg = el('btn-registrar-desde-didit');
    if (btnDiditReg) btnDiditReg.addEventListener('click', registrarDesdeDidit);

    if (cfg.diditVerificationId) {
      var status = el('registro-paciente-didit-status');
      if (status) {
        status.textContent = 'Verificación Didit recibida. Confirmá el alta.';
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
