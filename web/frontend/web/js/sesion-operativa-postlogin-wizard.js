/**
 * Wizard post-login: efector → encounter → servicio → POST sesion-operativa/establecer.
 * Config: elemento #sesion-operativa-wizard-config con data-establecer-url (absoluta).
 * Headers API: {@see window.BioenlaceApiClient.mergeHeaders} (p. ej. mergeHeaders({ 'X-Debug': '1' })).
 */
(function ($, window) {
  'use strict';

  function getConfigEl() {
    return document.getElementById('sesion-operativa-wizard-config');
  }

  function getEstablecerUrl() {
    var el = getConfigEl();
    if (!el || !el.getAttribute) {
      return '';
    }
    return el.getAttribute('data-establecer-url') || '';
  }

  var wizardEfectorServicios = {};

  function escapeHtml(s) {
    if (s == null) {
      return '';
    }
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  function resetWizardLayoutConEfectores() {
    var fieldsets = document.querySelectorAll('#dynamic-form .formwizard_fieldset');
    fieldsets.forEach(function (fs, i) {
      fs.style.display = i === 0 ? 'block' : 'none';
    });
    var topTab = document.getElementById('top-tab-list');
    if (topTab) {
      topTab.classList.remove('d-none');
    }
    $('#formwizard_efectores .next').removeClass('d-none').prop('disabled', true);
  }

  function aplicarEstadoSinEfectoresOperables(apiMessage, problemas) {
    var box = document.getElementById('sesion-operativa-estado-vacio');
    var lead = document.getElementById('sesion-operativa-estado-vacio-lead');
    var body = document.getElementById('sesion-operativa-estado-vacio-body');
    if (lead) {
      lead.textContent = apiMessage ? String(apiMessage) : '';
    }
    if (body) {
      body.innerHTML = '';
      if (problemas && problemas.length) {
        var ul = document.createElement('ul');
        ul.className = 'mb-0 ps-3';
        problemas.forEach(function (p) {
          var li = document.createElement('li');
          li.className = 'mb-2';
          var msg = p.message != null ? String(p.message) : '';
          var nom = p.nombre != null ? String(p.nombre) : '';
          var line = document.createElement('span');
          line.innerHTML =
            escapeHtml(msg) +
            (nom ? ' <span class="text-muted">— ' + escapeHtml(nom) + '</span>' : '');
          li.appendChild(line);
          if (p.contact && p.contact.length) {
            var nombres = p.contact
              .map(function (c) {
                return c.nombre_completo || '';
              })
              .filter(Boolean);
            if (nombres.length) {
              var strong = document.createElement('div');
              strong.className = 'small mt-1';
              strong.innerHTML =
                '<strong>Contacto administración:</strong> ' +
                nombres.map(escapeHtml).join(', ');
              li.appendChild(strong);
            }
          }
          ul.appendChild(li);
        });
        body.appendChild(ul);
      }
    }
    if (box) {
      box.classList.remove('d-none');
    }
    var topTab = document.getElementById('top-tab-list');
    if (topTab) {
      topTab.classList.add('d-none');
    }
    var step2 = document.getElementById('formwizard_encounter');
    var step3 = document.getElementById('formwizard_servicios');
    if (step2) {
      step2.style.display = 'none';
    }
    if (step3) {
      step3.style.display = 'none';
    }
    var step1 = document.getElementById('formwizard_efectores');
    if (step1) {
      step1.style.display = 'block';
    }
    $('#formwizard_efectores .next').prop('disabled', true).addClass('d-none');
  }

  function ocultarEstadoSinEfectoresOperables() {
    var box = document.getElementById('sesion-operativa-estado-vacio');
    if (box) {
      box.classList.add('d-none');
    }
  }

  function renderEfectores(efectores) {
    var container = document.getElementById('grid_efectores');
    var tmpl = document.getElementById('tmpl_efector_radio');
    if (!container || !tmpl) {
      return;
    }
    container.innerHTML = '';
    (efectores || []).forEach(function (e) {
      var id = parseInt(e.id_efector || e.id, 10);
      var nombre = e.nombre == null ? '' : String(e.nombre);
      if (!id) {
        return;
      }
      var node = document.importNode(tmpl.content, true);
      var input = node.querySelector('input[name=nombre_efector]');
      var label = node.querySelector('label');
      var inputId = 'efector_' + id;
      input.id = inputId;
      input.value = String(id);
      label.setAttribute('for', inputId);
      label.textContent = nombre;
      container.appendChild(node);
    });
  }

  function renderEncounterClasses(list) {
    var container = document.getElementById('encounter_classes_container');
    var tmpl = document.getElementById('tmpl_encounter_class');
    if (!container || !tmpl) {
      return;
    }
    container.innerHTML = '';
    (list || []).forEach(function (c, idx) {
      var code = c.code == null ? '' : String(c.code);
      var labelTxt = c.label == null ? '' : String(c.label);
      if (!code) {
        return;
      }
      var node = document.importNode(tmpl.content, true);
      var input = node.querySelector('input[name=encounter_class]');
      var label = node.querySelector('label');
      var h3 = node.querySelector('h3');
      var inputId = 'encounter_class_' + idx + '_' + code;
      input.id = inputId;
      input.value = code;
      label.setAttribute('for', inputId);
      h3.textContent = labelTxt;
      container.appendChild(node);
    });
  }

  function mostrarAlerta(texto, esError) {
    var cls = esError ? 'alert-danger' : 'alert-info';
    var wrap = document.createElement('div');
    wrap.className =
      'alert ' + cls + ' alert-dismissible fade show sesion-op-toast-alert';
    wrap.setAttribute('role', 'alert');
    wrap.style.cssText =
      'position:fixed;top:1rem;right:1rem;z-index:1080;max-width:min(420px,92vw);';
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn-close';
    btn.setAttribute('data-bs-dismiss', 'alert');
    btn.setAttribute('aria-label', 'Close');
    wrap.appendChild(btn);
    var content = document.createElement('div');
    content.innerHTML = texto;
    wrap.appendChild(content);
    document.body.appendChild(wrap);
    window.setTimeout(function () {
      if (window.bootstrap && window.bootstrap.Alert) {
        try {
          window.bootstrap.Alert.getOrCreateInstance(wrap).close();
          return;
        } catch (e) {
          /* ignore */
        }
      }
      if (wrap.parentNode) {
        wrap.parentNode.removeChild(wrap);
      }
    }, 12000);
  }

  function mostrarEfectoresConProblemas(lista) {
    if (!lista || !lista.length) {
      return;
    }
    var lineas = [];
    lista.forEach(function (p) {
      var m = escapeHtml(p.message ? String(p.message) : '');
      var nom = p.nombre
        ? ' <span class="text-muted">— ' + escapeHtml(String(p.nombre)) + '</span>'
        : '';
      var cts = '';
      if (p.contact && p.contact.length) {
        var nombres = p.contact
          .map(function (c) {
            return c.nombre_completo || '';
          })
          .filter(Boolean);
        if (nombres.length) {
          cts =
            ' <strong>Contacto administración:</strong> ' +
            nombres.map(escapeHtml).join(', ');
        }
      }
      lineas.push('<li>' + m + nom + cts + '</li>');
    });
    mostrarAlerta(
      '<p><strong>Algunos efectores requieren configuración</strong></p><ul class="mb-0">' +
        lineas.join('') +
        '</ul>',
      false
    );
  }

  function cargarOpcionesSesionOperativa(url) {
    $.ajax({
      url: url,
      type: 'POST',
      headers: window.BioenlaceApiClient.mergeHeaders({}),
      contentType: 'application/json; charset=utf-8',
      data: JSON.stringify({}),
      dataType: 'json',
      success: function (res) {
        if (!res || !res.success || !res.data) {
          mostrarAlerta('No se pudieron cargar las opciones de sesión.', true);
          return;
        }
        var data = res.data;
        var efectores = data.efectores || [];
        wizardEfectorServicios = {};
        efectores.forEach(function (e) {
          var id = parseInt(e.id_efector || e.id, 10);
          if (id) {
            wizardEfectorServicios[id] = e.servicios || [];
          }
        });
        renderEfectores(efectores);
        renderEncounterClasses(data.encounter_classes || []);

        if (!efectores.length) {
          aplicarEstadoSinEfectoresOperables(
            res.message || '',
            data.efectores_con_problemas || []
          );
        } else {
          ocultarEstadoSinEfectoresOperables();
          resetWizardLayoutConEfectores();
          mostrarEfectoresConProblemas(data.efectores_con_problemas || []);
        }
      },
      error: function (xhr) {
        var msg = 'Error cargando opciones de sesión';
        try {
          if (xhr.responseJSON && xhr.responseJSON.message) {
            msg = xhr.responseJSON.message;
          }
        } catch (e) {
          /* ignore */
        }
        mostrarAlerta(msg, true);
      },
    });
  }

  function bindWizard(url) {
    cargarOpcionesSesionOperativa(url);

    $('.a-servicio').on('click', function () {
      var idEfRaw = $('input[name=nombre_efector]:checked').val();
      var idEf = parseInt(idEfRaw, 10);
      var servicios = wizardEfectorServicios[idEf] || [];
      var container = document.getElementById('div_servicios');
      var tmpl = document.getElementById('tmpl_servicio');
      if (!container || !tmpl) {
        return;
      }
      container.innerHTML = '';
      if (!servicios.length) {
        mostrarAlerta('No hay servicios disponibles para el efector seleccionado.', true);
        return;
      }
      servicios.forEach(function (s, idx) {
        var sid = parseInt(s.id_servicio, 10);
        var nombre = s.nombre == null ? '' : String(s.nombre);
        if (!sid) {
          return;
        }
        var node = document.importNode(tmpl.content, true);
        var input = node.querySelector('input[name=servicio]');
        var label = node.querySelector('label');
        var h3 = node.querySelector('h3');
        var inputId = 'btn-check-servicio-' + idx + '-' + sid;
        input.id = inputId;
        input.value = String(sid);
        label.setAttribute('for', inputId);
        h3.textContent = nombre;
        container.appendChild(node);
      });
    });

    $(document).on('click', 'input[name=nombre_efector]', function () {
      $('#formwizard_efectores .next').prop('disabled', false);
    });

    $(document).on('click', 'input[name=encounter_class]', function () {
      $('#formwizard_encounter .next').prop('disabled', false);
    });

    $(document).on('click', 'input[name=servicio]', function () {
      $('#formwizard_servicios .next').prop('disabled', false);
    });

    $('#formwizard_servicios .next').on('click', function (e) {
      e.preventDefault();

      var efectorIdRaw = $('input[name=nombre_efector]:checked').val();
      var servicioIdRaw = $('input[name=servicio]:checked').val();
      var encounterClass = $('input[name=encounter_class]:checked').val();

      var efectorId = parseInt(efectorIdRaw, 10);
      var servicioId = parseInt(servicioIdRaw, 10);

      if (!efectorId || isNaN(efectorId) || !servicioId || isNaN(servicioId) || !encounterClass) {
        mostrarAlerta(
          '<i class="fa fa-exclamation fa-1x"></i> Seleccione efector, área y servicio para continuar',
          true
        );
        return;
      }

      $.ajax({
        url: url,
        type: 'POST',
        headers: window.BioenlaceApiClient.mergeHeaders({}),
        contentType: 'application/json; charset=utf-8',
        dataType: 'json',
        data: JSON.stringify({
          efector_id: efectorId,
          encounter_class: encounterClass,
          servicio_id: servicioId,
        }),
        success: function (res) {
          if (!res || !res.success) {
            var m = res && res.message ? String(res.message) : 'No se pudo establecer la sesión';
            mostrarAlerta(m, true);
            return;
          }
          var redirectUrl = res.data && res.data.redirect_url ? res.data.redirect_url : null;
          if (redirectUrl) {
            window.location.replace(redirectUrl);
            return;
          }
          mostrarAlerta('No se pudo determinar URL de redirección', true);
        },
        error: function (xhr) {
          var msg = 'Error al establecer la sesión operativa';
          try {
            var j = xhr.responseJSON;
            if (j && j.message) {
              msg = j.message;
            }
            if (j && j.contact && j.contact.length) {
              var nombres = j.contact
                .map(function (c) {
                  return c.nombre_completo || '';
                })
                .filter(Boolean);
              if (nombres.length) {
                msg += ' Contacto administración: ' + nombres.join(', ');
              }
            }
          } catch (e) {
            /* ignore */
          }
          mostrarAlerta(msg, true);
        },
      });
    });
  }

  function init() {
    var url = getEstablecerUrl();
    if (!url) {
      return;
    }
    bindWizard(url);
  }

  $(init);
})(window.jQuery, window);
