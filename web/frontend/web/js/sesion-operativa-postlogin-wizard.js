/**
 * Wizard post-login: efector → servicio → área (solo clínico) → POST sesion-operativa/establecer.
 * Salta pasos y auto-envía cuando solo hay una opción.
 */
(function ($, window) {
  'use strict';

  var wizardEfectorServicios = {};
  var wizardEfectoresList = [];
  var wizardEncounterClasses = [];
  var currentWizardStep = 0;
  var wizardSubmitting = false;

  var STEP_EFECTOR = 0;
  var STEP_SERVICIO = 1;
  var STEP_ENCOUNTER = 2;

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

  function escapeHtml(s) {
    if (s == null) {
      return '';
    }
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  function setWizardLoading(on) {
    var el = document.getElementById('sesion-operativa-wizard-loading');
    var form = document.getElementById('dynamic-form');
    if (el) {
      el.classList.toggle('d-none', !on);
    }
    if (form) {
      form.classList.toggle('d-none', on);
    }
  }

  function updateWizardTabs(step) {
    var paso1 = document.getElementById('paso1');
    var paso2 = document.getElementById('paso2');
    var paso3 = document.getElementById('paso3');
    if (!paso1 || !paso2 || !paso3) {
      return;
    }
    [paso1, paso2, paso3].forEach(function (li) {
      li.classList.remove('active', 'done');
    });
    if (step >= STEP_EFECTOR) {
      paso1.classList.add(step === STEP_EFECTOR ? 'active' : 'done');
    }
    if (step >= STEP_SERVICIO) {
      paso2.classList.add(step === STEP_SERVICIO ? 'active' : 'done');
    }
    if (step >= STEP_ENCOUNTER) {
      paso3.classList.add(step === STEP_ENCOUNTER ? 'active' : 'done');
    }
  }

  function setEncounterStepVisible(visible) {
    var paso3 = document.getElementById('paso3');
    if (paso3) {
      paso3.classList.toggle('d-none', !visible);
    }
  }

  function showWizardStep(step) {
    currentWizardStep = step;
    var fieldsets = document.querySelectorAll('#dynamic-form .formwizard_fieldset');
    fieldsets.forEach(function (fs, i) {
      fs.style.display = i === step ? 'block' : 'none';
    });
    updateWizardTabs(step);
    var topTab = document.getElementById('top-tab-list');
    if (topTab) {
      topTab.classList.remove('d-none');
    }
  }

  function resetWizardLayoutConEfectores() {
    setEncounterStepVisible(true);
    showWizardStep(STEP_EFECTOR);
    $('[data-wizard-action="efector-next"]').removeClass('d-none').prop('disabled', true);
    $('[data-wizard-action="servicio-next"]').prop('disabled', true);
    $('[data-wizard-action="encounter-finish"]').prop('disabled', true);
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
    $('#formwizard_servicios, #formwizard_encounter').hide();
    $('#formwizard_efectores').show();
    $('[data-wizard-action="efector-next"]').prop('disabled', true).addClass('d-none');
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

  function getSelectedEfectorId() {
    var raw = $('input[name=nombre_efector]:checked').val();
    var id = parseInt(raw, 10);
    return id > 0 && !isNaN(id) ? id : 0;
  }

  function getSelectedServicioMeta() {
    var raw = $('input[name=servicio]:checked').val();
    var id = parseInt(raw, 10);
    if (!id || isNaN(id)) {
      return null;
    }
    var idEf = getSelectedEfectorId();
    var list = wizardEfectorServicios[idEf] || [];
    for (var i = 0; i < list.length; i++) {
      if (parseInt(list[i].id_servicio, 10) === id) {
        return list[i];
      }
    }
    return { id_servicio: id, requires_encounter_class: true };
  }

  function servicioRequiresEncounter(servicio) {
    if (!servicio) {
      return true;
    }
    return servicio.requires_encounter_class !== false;
  }

  function renderServiciosForEfector(idEfector) {
    var servicios = wizardEfectorServicios[idEfector] || [];
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
  }

  function selectEfectorById(idEfector) {
    var input = document.querySelector('input[name=nombre_efector][value="' + String(idEfector) + '"]');
    if (input) {
      input.checked = true;
      $('[data-wizard-action="efector-next"]').prop('disabled', false);
    }
  }

  function selectServicioById(idServicio) {
    var input = document.querySelector('input[name=servicio][value="' + String(idServicio) + '"]');
    if (input) {
      input.checked = true;
      $('[data-wizard-action="servicio-next"]').prop('disabled', false);
    }
  }

  function selectEncounterByCode(code) {
    var input = document.querySelector('input[name=encounter_class][value="' + String(code) + '"]');
    if (input) {
      input.checked = true;
      $('[data-wizard-action="encounter-finish"]').prop('disabled', false);
    }
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

  function establecerSesion(url) {
    if (wizardSubmitting) {
      return;
    }
    var efectorId = getSelectedEfectorId();
    var servicioMeta = getSelectedServicioMeta();
    var servicioId = servicioMeta ? parseInt(servicioMeta.id_servicio, 10) : 0;
    var encounterClass = $('input[name=encounter_class]:checked').val() || '';
    var needsEncounter = servicioRequiresEncounter(servicioMeta);

    if (!efectorId || !servicioId) {
      mostrarAlerta('Seleccioná efector y servicio para continuar.', true);
      return;
    }
    if (needsEncounter && !encounterClass) {
      mostrarAlerta('Seleccioná el área de trabajo para continuar.', true);
      return;
    }

    var payload = {
      efector_id: efectorId,
      servicio_id: servicioId,
    };
    if (needsEncounter) {
      payload.encounter_class = encounterClass;
    }

    wizardSubmitting = true;
    setWizardLoading(true);

    $.ajax({
      url: url,
      type: 'POST',
      headers: window.BioenlaceApiClient.mergeHeaders({}),
      contentType: 'application/json; charset=utf-8',
      dataType: 'json',
      data: JSON.stringify(payload),
      success: function (res) {
        if (!res || !res.success) {
          wizardSubmitting = false;
          setWizardLoading(false);
          var m = res && res.message ? String(res.message) : 'No se pudo establecer la sesión';
          mostrarAlerta(m, true);
          return;
        }
        if (res.data && res.data.context_token) {
          window.apiAuthToken = String(res.data.context_token);
        }
        var redirectUrl = res.data && res.data.redirect_url ? res.data.redirect_url : null;
        if (redirectUrl) {
          window.location.replace(redirectUrl);
          return;
        }
        wizardSubmitting = false;
        setWizardLoading(false);
        mostrarAlerta('No se pudo determinar URL de redirección', true);
      },
      error: function (xhr) {
        wizardSubmitting = false;
        setWizardLoading(false);
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
  }

  function afterServicioSelected(url) {
    var servicio = getSelectedServicioMeta();
    if (!servicioRequiresEncounter(servicio)) {
      setEncounterStepVisible(false);
      establecerSesion(url);
      return;
    }
    setEncounterStepVisible(true);
    var ecs = wizardEncounterClasses || [];
    if (ecs.length === 1) {
      selectEncounterByCode(ecs[0].code);
      establecerSesion(url);
      return;
    }
    showWizardStep(STEP_ENCOUNTER);
  }

  function goToServicioStep() {
    var idEf = getSelectedEfectorId();
    if (!idEf) {
      mostrarAlerta('Seleccioná un efector.', true);
      return;
    }
    renderServiciosForEfector(idEf);
    showWizardStep(STEP_SERVICIO);
    var servicios = wizardEfectorServicios[idEf] || [];
    if (servicios.length === 1) {
      selectServicioById(parseInt(servicios[0].id_servicio, 10));
      afterServicioSelected(getEstablecerUrl());
    }
  }

  function tryAutoResolveOnLoad(url) {
    var efectores = wizardEfectoresList || [];
    if (efectores.length !== 1) {
      return false;
    }
    var idEf = parseInt(efectores[0].id_efector || efectores[0].id, 10);
    if (!idEf) {
      return false;
    }
    selectEfectorById(idEf);
    var servicios = wizardEfectorServicios[idEf] || [];
    if (servicios.length !== 1) {
      goToServicioStep();
      return true;
    }
    selectServicioById(parseInt(servicios[0].id_servicio, 10));
    if (!servicioRequiresEncounter(servicios[0])) {
      establecerSesion(url);
      return true;
    }
    var ecs = wizardEncounterClasses || [];
    if (ecs.length === 1) {
      selectEncounterByCode(ecs[0].code);
      establecerSesion(url);
      return true;
    }
    setEncounterStepVisible(true);
    showWizardStep(STEP_ENCOUNTER);
    return true;
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
        wizardEfectoresList = efectores;
        wizardEncounterClasses = data.encounter_classes || [];
        wizardEfectorServicios = {};
        efectores.forEach(function (e) {
          var id = parseInt(e.id_efector || e.id, 10);
          if (id) {
            wizardEfectorServicios[id] = e.servicios || [];
          }
        });
        renderEfectores(efectores);
        renderEncounterClasses(wizardEncounterClasses);

        if (!efectores.length) {
          aplicarEstadoSinEfectoresOperables(
            res.message || '',
            data.efectores_con_problemas || []
          );
        } else {
          ocultarEstadoSinEfectoresOperables();
          resetWizardLayoutConEfectores();
          mostrarEfectoresConProblemas(data.efectores_con_problemas || []);
          if (!tryAutoResolveOnLoad(url)) {
            setWizardLoading(false);
          }
        }
      },
      error: function (xhr) {
        setWizardLoading(false);
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
    $(document).on('click', 'input[name=nombre_efector]', function () {
      $('[data-wizard-action="efector-next"]').prop('disabled', false);
    });

    $(document).on('click', 'input[name=servicio]', function () {
      $('[data-wizard-action="servicio-next"]').prop('disabled', false);
    });

    $(document).on('click', 'input[name=encounter_class]', function () {
      $('[data-wizard-action="encounter-finish"]').prop('disabled', false);
    });

    $('[data-wizard-action="efector-next"]').on('click', function (e) {
      e.preventDefault();
      goToServicioStep();
    });

    $('[data-wizard-action="servicio-prev"]').on('click', function (e) {
      e.preventDefault();
      showWizardStep(STEP_EFECTOR);
    });

    $('[data-wizard-action="servicio-next"]').on('click', function (e) {
      e.preventDefault();
      if (!getSelectedServicioMeta()) {
        mostrarAlerta('Seleccioná un servicio.', true);
        return;
      }
      afterServicioSelected(url);
    });

    $('[data-wizard-action="encounter-prev"]').on('click', function (e) {
      e.preventDefault();
      showWizardStep(STEP_SERVICIO);
    });

    $('[data-wizard-action="encounter-finish"]').on('click', function (e) {
      e.preventDefault();
      establecerSesion(url);
    });

    cargarOpcionesSesionOperativa(url);
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
