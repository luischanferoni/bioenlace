/**
 * Utilidades para código PDF417 / QR del DNI argentino (lector hardware o cámara).
 */
(function (global) {
  'use strict';

  function parseDniBarcode(sCode) {
    if (!sCode || typeof sCode !== 'string') {
      return null;
    }
    var code = sCode.trim();
    var expEs =
      /^[0-9]{11}@[A-ZÁÉÍÓÚÑ ]+@[A-ZÁÉÍÓÚÑ ]+@[MF]@([MF]|[0-9])?[0-9]{7}@[A-Z]{1}@[0-9]{2}\/[0-9]{2}\/[0-9]{4}@[0-9]{2}\/[0-9]{2}\/[0-9]{4}(@[0-9]{3})?$/;
    var expEn =
      /^[0-9]{11}"[A-ZÁÉÍÓÚÑ ]+"[A-ZÁÉÍÓÚÑ ]+"[MF]"([MF]|[0-9])?[0-9]{7}"[A-Z]{1}"[0-9]{2}[0-9]{2}[0-9]{4}"[0-9]{2}[0-9]{2}[0-9]{4}("[0-9]{3})?$/;
    var expLib =
      /^"[0-9]?[0-9]{7}"[A-Z]{1}"[0-9]{1}"[A-ZÁÉÍÓÚÑ ]+"[A-ZÁÉÍÓÚÑ ]+"[A-ZÁÉÍÓÚÑ ]+"[0-9]{2}[0-9]{2}[0-9]{4}"[MF]"[0-9]{2}[0-9]{2}[0-9]{4}/;

    if (!expEs.test(code) && !expEn.test(code) && !expLib.test(code)) {
      return null;
    }

    var parts = code.indexOf('"') >= 0 ? code.split('"') : code.split('@');
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
      codigo_barras: code,
      documento: documento,
      sexo_biologico: sexoBiologico,
      sexo_letra: sexoLetra,
    };
  }

  function formatRenaperPreviewLabel(data) {
    if (!data || data.encontrado !== true) {
      return '';
    }
    var identity = data.identity || {};
    var renaper = data.renaper || {};
    var nombre = Array.isArray(renaper.nombres)
      ? renaper.nombres[0]
      : renaper.nombres || identity.nombre || '';
    var apellido = Array.isArray(renaper.apellido)
      ? renaper.apellido[0]
      : renaper.apellido || identity.apellido || '';
    var doc = identity.documento || renaper.numeroDocumento || '';
    var fecha =
      renaper.fechaNacimiento ||
      renaper.fecha_nacimiento ||
      identity.fecha_nacimiento ||
      '';
    var label = (String(apellido) + ' ' + String(nombre)).trim();
    if (doc) {
      label += (label ? ' · ' : '') + 'DNI ' + doc;
    }
    if (fecha) {
      label += ' · ' + fecha;
    }
    return label;
  }

  global.BioenlaceDniBarcode = {
    parse: parseDniBarcode,
    formatPreviewLabel: formatRenaperPreviewLabel,
  };
})(typeof window !== 'undefined' ? window : this);
