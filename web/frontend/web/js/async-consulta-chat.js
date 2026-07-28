/**
 * Helpers compartidos para chat de consulta async (policy, estilos, acciones).
 * Consumen la misma forma que Flutter: data.chat_policy + message_kind por mensaje.
 */
(function (global) {
  'use strict';

  function parsePolicy(raw) {
    if (!raw || typeof raw !== 'object') {
      return {
        composerEnabled: true,
        uploadEnabled: false,
        uploadTypes: [],
        hint: '',
        canCancel: false,
        canClose: false,
        resolutions: [],
        suggestTurno: false,
      };
    }
    var composer = raw.composer && typeof raw.composer === 'object' ? raw.composer : {};
    var uploadTypes = [];
    if (composer.upload_types && Array.isArray(composer.upload_types)) {
      composer.upload_types.forEach(function (t) {
        var s = String(t || '').trim();
        if (s) uploadTypes.push(s);
      });
    }
    var acciones = raw.acciones && typeof raw.acciones === 'object' ? raw.acciones : {};
    var resolutions = [];
    var resRaw = raw.resoluciones_disponibles;
    if (resRaw && typeof resRaw === 'object') {
      Object.keys(resRaw).forEach(function (code) {
        var entry = resRaw[code];
        var label = '';
        var requireNote = false;
        if (entry && typeof entry === 'object') {
          label = String(entry.label || '').trim();
          requireNote = entry.require_note === true;
        } else {
          label = String(entry || '').trim();
        }
        if (label) {
          resolutions.push({ code: code, label: label, requireNote: requireNote });
        }
      });
    }
    return {
      composerEnabled: composer.enabled === true,
      uploadEnabled: composer.upload_enabled === true && uploadTypes.length > 0,
      uploadTypes: uploadTypes,
      canUploadAudio: composer.upload_enabled === true && uploadTypes.indexOf('audio') >= 0,
      canUploadDocument: composer.upload_enabled === true && uploadTypes.indexOf('documento') >= 0,
      canUploadImage: composer.upload_enabled === true && uploadTypes.indexOf('imagen') >= 0,
      hint: composer.hint ? String(composer.hint).trim() : '',
      canCancel: acciones.cancelar === true,
      canClose: acciones.cerrar === true,
      resolutions: resolutions,
      suggestTurno: raw.suggest_turno === true,
      conversationMode: raw.conversation_mode ? String(raw.conversation_mode).trim() : 'conversational',
      showResolutionActions: acciones.cerrar === true && resolutions.length > 0,
    };
  }

  function attachmentLabel(messageType) {
    if (messageType === 'audio') return 'Mensaje de audio';
    if (messageType === 'documento') return 'Documento PDF';
    if (messageType === 'imagen') return 'Imagen';
    if (messageType === 'video') return 'Video';
    return 'Adjunto';
  }

  function messageKind(m) {
    if (!m || typeof m !== 'object') return '';
    return String(m.message_kind || m.message_type || '').trim();
  }

  function isSystemMessage(m) {
    var kind = messageKind(m);
    return kind === 'sistema' || m.message_type === 'sistema';
  }

  function isSolicitudMessage(m) {
    var kind = messageKind(m);
    var type = String(m.message_type || '');
    var categoria = m.solicitud_categoria ? String(m.solicitud_categoria).trim() : '';
    return kind === 'solicitud' || categoria !== '' || type.indexOf('solicitud_') === 0;
  }

  function isStaffMessage(m) {
    var role = String(m.user_role || '').toLowerCase();
    return role === 'medico' || role === 'enfermeria' || role === 'staff' || role === 'profesional';
  }

  function renderAttachmentBody(m, openHandler, linkClass) {
    var type = String(m.message_type || '');
    var content = m.content ? String(m.content) : '';
    var wrap = document.createElement('div');
    wrap.className = 'd-flex align-items-center gap-2 flex-wrap';

    var label = document.createElement('span');
    label.textContent = attachmentLabel(type);
    wrap.appendChild(label);

    if (content && typeof openHandler === 'function') {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = linkClass || 'btn btn-link btn-sm p-0 align-baseline';
      btn.textContent = 'Abrir';
      btn.addEventListener('click', function () { openHandler(content, type); });
      wrap.appendChild(btn);
    }

    return wrap;
  }

  function renderMessage(m, openHandler) {
    if (isSystemMessage(m)) {
      var sys = document.createElement('div');
      sys.className = 'text-center text-muted fst-italic small px-3 py-2';
      sys.textContent = m.content || '';
      return sys;
    }

    var isStaff = isStaffMessage(m);
    var row = document.createElement('div');
    row.className = 'd-flex mb-2 ' + (isStaff ? 'justify-content-end' : 'justify-content-start');

    var bubble = document.createElement('div');
    bubble.className =
      'rounded-3 px-3 py-2 small ' +
      (isStaff
        ? 'text-white bg-primary'
        : isSolicitudMessage(m)
          ? 'bg-light border border-primary border-opacity-25'
          : 'bg-light border');
    bubble.style.maxWidth = '85%';

    var type = String(m.message_type || 'texto');
    var body = document.createElement('div');
    if (isSolicitudMessage(m) && !isStaff) {
      body.className = 'fw-semibold';
    }
    if (type === 'audio' || type === 'documento' || type === 'imagen') {
      body.appendChild(
        renderAttachmentBody(
          m,
          openHandler,
          isStaff ? 'btn btn-link btn-sm p-0 align-baseline text-white' : undefined
        )
      );
    } else if (
      type === 'texto' ||
      type.indexOf('solicitud_') === 0 ||
      (m.solicitud_categoria && String(m.solicitud_categoria).trim())
    ) {
      body.textContent = m.content || '';
    } else {
      body.textContent = attachmentLabel(type);
    }
    bubble.appendChild(body);

    var when = formatCreatedAt(m.created_at);
    if (when) {
      var time = document.createElement('div');
      time.className = 'mt-1 ' + (isStaff ? 'text-white-50' : 'text-muted');
      time.style.fontSize = '0.7rem';
      time.textContent = when;
      bubble.appendChild(time);
    }

    row.appendChild(bubble);
    return row;
  }

  function formatCreatedAt(iso) {
    if (!iso) return '';
    try {
      var d = new Date(iso);
      if (Number.isNaN(d.getTime())) return String(iso);
      return d.toLocaleString(undefined, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    } catch (e) {
      return String(iso);
    }
  }

  global.BioenlaceAsyncConsultaChat = {
    parsePolicy: parsePolicy,
    renderMessage: renderMessage,
    attachmentLabel: attachmentLabel,
    isSystemMessage: isSystemMessage,
    isSolicitudMessage: isSolicitudMessage,
    formatCreatedAt: formatCreatedAt,
  };
})(typeof window !== 'undefined' ? window : this);
