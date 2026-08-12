/**
 * Renderizador liviano de UI JSON para páginas nativas (modales, embeds).
 * Paridad con UiJsonScreen (Flutter) sin cargar spa-home.js.
 */
(function () {
  'use strict';

  function escapeHtml(text) {
    if (text == null) return '';
    var s = String(text);
    return s.replace(/[&<>"']/g, function (m) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[m];
    });
  }

  function resolveApiUrl(path) {
    if (!path) return '';
    var p = String(path);
    if (p.startsWith('http://') || p.startsWith('https://')) return p;
    if (window.BioenlaceApiClient && typeof window.BioenlaceApiClient.normalizeApiV1Path === 'function') {
      p = window.BioenlaceApiClient.normalizeApiV1Path(p);
    } else if (p && !p.startsWith('/')) {
      p = '/' + p;
    }
    if (p.startsWith('/api/')) return window.location.origin + p;
    if (!p.startsWith('/')) p = '/' + p;
    return window.location.origin + '/api/v1' + p;
  }

  function mergeHeaders(extra) {
    if (window.BioenlaceApiClient && typeof window.BioenlaceApiClient.mergeHeaders === 'function') {
      return window.BioenlaceApiClient.mergeHeaders(extra || {});
    }
    return Object.assign({ Accept: 'application/json' }, extra || {});
  }

  function unwrapPayload(json) {
    if (!json || typeof json !== 'object') return null;
    if (json.kind === 'ui_definition') return json;
    if (json.data && json.data.kind === 'ui_definition') return json.data;
    if (json.ui_type === 'ui_json') return json;
    return json;
  }

  function firstUiErrorMessage(errors) {
    if (!errors || typeof errors !== 'object') return '';
    if (errors._error && Array.isArray(errors._error) && errors._error[0]) {
      return String(errors._error[0]);
    }
    var keys = Object.keys(errors);
    for (var i = 0; i < keys.length; i++) {
      var v = errors[keys[i]];
      if (Array.isArray(v) && v[0]) return String(v[0]);
      if (typeof v === 'string' && v.trim()) return v.trim();
    }
    return '';
  }

  function normalizeOptions(field) {
    return Array.isArray(field && field.options) ? field.options : [];
  }

  function renderSelectField(field) {
    var fv = field.value != null ? String(field.value) : '';
    var html = '<select class="form-select" name="' + escapeHtml(field.name) + '"' + (field.required ? ' required' : '') + '>';
    html += '<option value="">Seleccione…</option>';
    normalizeOptions(field).forEach(function (option) {
      var value = typeof option === 'object' ? option.value : option;
      var label = typeof option === 'object' ? option.label : option;
      var sel = String(value) === fv ? ' selected' : '';
      html += '<option value="' + escapeHtml(value) + '"' + sel + '>' + escapeHtml(label) + '</option>';
    });
    html += '</select>';
    return html;
  }

  function renderTextField(field) {
    var v = field.value != null ? String(field.value) : '';
    var html = '<input type="text" class="form-control" name="' + escapeHtml(field.name) + '" value="' + escapeHtml(v) + '"';
    if (field.max_length) html += ' maxlength="' + parseInt(field.max_length, 10) + '"';
    if (field.required) html += ' required';
    html += '>';
    if (field.hint) html += '<div class="form-text">' + escapeHtml(field.hint) + '</div>';
    return html;
  }

  function renderTextareaField(field) {
    var v = field.value != null ? String(field.value) : '';
    var rows = field.rows ? parseInt(field.rows, 10) : 3;
    var html = '<textarea class="form-control" name="' + escapeHtml(field.name) + '" rows="' + rows + '"';
    if (field.max_length) html += ' maxlength="' + parseInt(field.max_length, 10) + '"';
    if (field.required) html += ' required';
    html += '>' + escapeHtml(v) + '</textarea>';
    if (field.hint) html += '<div class="form-text">' + escapeHtml(field.hint) + '</div>';
    return html;
  }

  function renderDateField(field) {
    var v = field.value != null ? String(field.value) : '';
    var html = '<input type="date" class="form-control" name="' + escapeHtml(field.name) + '" value="' + escapeHtml(v) + '"';
    if (field.required) html += ' required';
    html += '>';
    return html;
  }

  function renderInlineAutocomplete(field) {
    var id = 'uiac_' + String(field.name || 'field').replace(/[^a-z0-9_]/gi, '_') + '_' + Math.floor(Math.random() * 100000);
    var selectedValue = field.value != null ? String(field.value) : '';
    var selectedLabel = '';
    normalizeOptions(field).forEach(function (opt) {
      var val = typeof opt === 'object' ? opt.value : opt;
      if (String(val) === selectedValue) {
        selectedLabel = typeof opt === 'object' ? (opt.label || '') : String(opt);
      }
    });
    var html = '';
    html += '<input type="hidden" name="' + escapeHtml(field.name) + '" id="' + id + '_value" value="' + escapeHtml(selectedValue) + '">';
    html += '<input type="search" class="form-control" id="' + id + '_q" placeholder="Buscar por apellido o documento" autocomplete="off" value="' + escapeHtml(selectedLabel) + '">';
    html += '<div class="list-group mt-2 small" id="' + id + '_list"></div>';
    html += '<div class="d-none" data-ui-ac-inline="' + id + '" data-endpoint="' + escapeHtml(field.endpoint || '') + '"></div>';
    if (field.hint) html += '<div class="form-text">' + escapeHtml(field.hint) + '</div>';
    return html;
  }

  function renderFormField(field) {
    if (!field || field.type === 'hidden') {
      var hv = field && field.value != null ? String(field.value) : '';
      return '<input type="hidden" name="' + escapeHtml(field.name) + '" value="' + escapeHtml(hv) + '">';
    }
    var html = '<div class="mb-3">';
    if (field.label) {
      html += '<label class="form-label">' + escapeHtml(field.label);
      if (field.required) html += ' <span class="text-danger">*</span>';
      html += '</label>';
    }
    switch (field.type) {
      case 'autocomplete':
        html += renderInlineAutocomplete(field);
        break;
      case 'select':
        html += renderSelectField(field);
        break;
      case 'date':
        html += renderDateField(field);
        break;
      case 'textarea':
        html += renderTextareaField(field);
        break;
      default:
        html += renderTextField(field);
    }
    html += '</div>';
    return html;
  }

  function parseAcItems(payload) {
    var raw = payload;
    if (payload && payload.data && typeof payload.data === 'object') raw = payload.data;
    var arr = Array.isArray(raw.results) ? raw.results
      : (Array.isArray(payload.results) ? payload.results
        : (Array.isArray(payload.items) ? payload.items : []));
    return arr.map(function (it) {
      if (!it || typeof it !== 'object') return { value: String(it), label: String(it) };
      return {
        value: String(it.id != null ? it.id : (it.value != null ? it.value : '')),
        label: String(it.text || it.label || it.name || it.id || ''),
      };
    });
  }

  function attachInlineAutocompleteHandlers(root) {
    var metas = root.querySelectorAll('[data-ui-ac-inline]');
    metas.forEach(function (meta) {
      if (meta.getAttribute('data-bound') === '1') return;
      meta.setAttribute('data-bound', '1');
      var id = meta.getAttribute('data-ui-ac-inline');
      var endpoint = meta.getAttribute('data-endpoint') || '';
      var qEl = document.getElementById(id + '_q');
      var listEl = document.getElementById(id + '_list');
      var valueEl = document.getElementById(id + '_value');
      if (!qEl || !listEl || !valueEl || !endpoint) return;

      var timer = null;

      function pick(value, label) {
        valueEl.value = value;
        qEl.value = label;
        listEl.innerHTML = '';
      }

      function renderList(items) {
        listEl.innerHTML = '';
        if (!items.length) {
          var empty = document.createElement('div');
          empty.className = 'text-muted';
          empty.textContent = 'Sin resultados.';
          listEl.appendChild(empty);
          return;
        }
        items.forEach(function (it) {
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'list-group-item list-group-item-action';
          btn.textContent = it.label;
          btn.addEventListener('click', function () {
            pick(it.value, it.label);
          });
          listEl.appendChild(btn);
        });
      }

      async function runSearch(q) {
        try {
          var url = resolveApiUrl(endpoint);
          var u = new URL(url, window.location.origin);
          if (q && String(q).trim()) u.searchParams.set('q', String(q).trim());
          var res = await fetch(u.toString(), { headers: mergeHeaders(), credentials: 'same-origin' });
          var data = await res.json();
          renderList(parseAcItems(data));
        } catch (e) {
          listEl.innerHTML = '<div class="text-danger small">Error al buscar.</div>';
        }
      }

      qEl.addEventListener('input', function () {
        valueEl.value = '';
        if (timer) clearTimeout(timer);
        timer = setTimeout(function () {
          runSearch(qEl.value);
        }, 280);
      });

      if (valueEl.value && qEl.value) {
        return;
      }
      runSearch('');
    });
  }

  function renderMessageBlock(block, container) {
    var text = block.text ? String(block.text) : '';
    if (!text) return;
    container.innerHTML = '<p class="text-muted small mb-0">' + escapeHtml(text) + '</p>';
  }

  function renderFieldsBlock(block, container, options) {
    var title = block.title ? String(block.title) : '';
    var fields = Array.isArray(block.fields) ? block.fields : [];
    var html = '<div class="bio-ui-json-fields"><form data-ui-json-form="1">';
    if (title) html += '<div class="fw-semibold mb-2">' + escapeHtml(title) + '</div>';
    fields.forEach(function (fd) {
      html += renderFormField(fd);
    });
    if (!options.hideInlineSubmit) {
      var label = options.submitLabel || 'Guardar';
      html += '<div class="d-flex justify-content-end mt-3"><button type="button" class="btn btn-primary" data-ui-json-submit="1">' + escapeHtml(label) + '</button></div>';
    }
    html += '</form></div>';
    container.innerHTML = html;
    attachInlineAutocompleteHandlers(container);
  }

  function renderBlocks(json, container, options) {
    options = options || {};
    var blocks = Array.isArray(json.blocks) ? json.blocks : [];
    if (!blocks.length) {
      container.innerHTML = '<div class="alert alert-warning mb-0">Formulario sin contenido.</div>';
      return null;
    }
    var wrap = document.createElement('div');
    wrap.className = 'bio-ui-json-blocks d-flex flex-column gap-3';
    container.innerHTML = '';
    container.appendChild(wrap);

    blocks.forEach(function (block, idx) {
      if (!block || typeof block !== 'object') return;
      var mount = document.createElement('div');
      mount.className = 'bio-ui-json-block';
      wrap.appendChild(mount);
      var kind = String(block.kind || '');
      if (kind === 'message') {
        renderMessageBlock(block, mount);
      } else if (kind === 'fields') {
        renderFieldsBlock(block, mount, options);
      } else {
        mount.innerHTML = '<div class="alert alert-warning mb-0">Bloque no soportado: ' + escapeHtml(kind) + '</div>';
      }
    });
    return container.querySelector('form[data-ui-json-form="1"]');
  }

  function collectJsonPayload(form) {
    var out = {};
    if (!form) return out;
    var fd = new FormData(form);
    fd.forEach(function (v, k) {
      if (v != null && String(v).trim() !== '') out[k] = String(v);
    });
    return out;
  }

  function render(container, json, options) {
    options = options || {};
    if (!container) return null;
    var ui = unwrapPayload(json);
    if (!ui) {
      container.innerHTML = '<div class="alert alert-danger mb-0">Respuesta inválida.</div>';
      return null;
    }
    var submitLabel = options.submitLabel || 'Guardar';
    if (Array.isArray(ui.actions)) {
      ui.actions.forEach(function (a) {
        if (a && a.type === 'submit' && a.label) submitLabel = String(a.label);
      });
    }
    options.submitLabel = submitLabel;
    var form = renderBlocks(ui, container, options);
    if (form && options.hideInlineSubmit && options.externalSubmitBtn) {
      form.setAttribute('data-ui-json-external-submit', '1');
    }
    if (form && !options.hideInlineSubmit) {
      var btn = container.querySelector('[data-ui-json-submit="1"]');
      if (btn) {
        btn.addEventListener('click', function () {
          submit(container, options);
        });
      }
    }
    return form;
  }

  async function submit(container, options) {
    options = options || {};
    var form = container ? container.querySelector('form[data-ui-json-form="1"]') : null;
    if (!form || !options.submitUrl) return { ok: false, message: 'Formulario no disponible.' };

    var payload = collectJsonPayload(form);
    var headers = mergeHeaders({
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    });

    var res = await fetch(options.submitUrl, {
      method: 'POST',
      headers: headers,
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    });
    var json = await res.json();

    if (json && json.kind === 'ui_definition') {
      render(container, json, options);
      return { ok: false, message: firstUiErrorMessage(json.errors) || 'Revise los datos.' };
    }

    if (json && json.success === false) {
      var msg = json.message || firstUiErrorMessage(json.errors) || 'No se pudo guardar.';
      if (typeof options.onError === 'function') options.onError(msg, json);
      return { ok: false, message: msg, json: json };
    }

    if (typeof options.onSuccess === 'function') options.onSuccess(json);
    return { ok: true, json: json };
  }

  window.BioenlaceUiJsonEmbed = {
    render: render,
    submit: submit,
    unwrapPayload: unwrapPayload,
    resolveApiUrl: resolveApiUrl,
  };
})();
