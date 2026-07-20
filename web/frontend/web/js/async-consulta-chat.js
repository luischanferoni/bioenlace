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
        var label = String(resRaw[code] || '').trim();
        if (label) resolutions.push({ code: code, label: label });
      });
    }
    return {
      composerEnabled: composer.enabled === true,
      uploadEnabled: composer.upload_enabled === true && uploadTypes.length > 0,
      uploadTypes: uploadTypes,
      canUploadAudio: composer.upload_enabled === true && uploadTypes.indexOf('audio') >= 0,
      canUploadDocument: composer.upload_enabled === true && uploadTypes.indexOf('documento') >= 0,
      hint: composer.hint ? String(composer.hint).trim() : '',
      canCancel: acciones.cancelar === true,
      canClose: acciones.cerrar === true,
      resolutions: resolutions,
      suggestTurno: raw.suggest_turno === true,
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
    return kind === 'solicitud' || type.indexOf('solicitud_') === 0;
  }

  function renderAttachmentBody(m, openHandler) {
    var type = String(m.message_type || '');
    var content = m.content ? String(m.content) : '';
    var wrap = document.createElement('div');
    wrap.className = 'd-flex align-items-center gap-2';

    var icon = document.createElement('span');
    icon.className = 'text-muted';
    icon.textContent = type === 'documento' ? '📄' : '🎤';
    wrap.appendChild(icon);

    var label = document.createElement('span');
    label.textContent = attachmentLabel(type);
    wrap.appendChild(label);

    if (content && typeof openHandler === 'function') {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-link btn-sm p-0 align-baseline';
      btn.textContent = 'Abrir';
      btn.addEventListener('click', function () { openHandler(content, type); });
      wrap.appendChild(btn);
    }

    return wrap;
  }

  function renderMessage(m, openHandler) {
    var row = document.createElement('div');
    row.className = 'mb-2 small';

    if (isSystemMessage(m)) {
      row.className += ' text-center text-muted fst-italic px-2';
      row.textContent = m.content || '';
      return row;
    }

    if (isSolicitudMessage(m)) {
      row.className += ' border-start border-3 border-primary ps-2 py-1';
    }

    var who = document.createElement('div');
    who.className = 'fw-semibold text-muted';
    who.textContent = (m.user_name || m.user_role || 'Usuario')
      + (m.created_at ? (' · ' + formatCreatedAt(m.created_at)) : '');
    row.appendChild(who);

    var type = String(m.message_type || 'texto');
    var body = document.createElement('div');
    body.className = isSolicitudMessage(m) ? 'fw-semibold' : '';
    if (type === 'audio' || type === 'documento') {
      body.appendChild(renderAttachmentBody(m, openHandler));
    } else if (type === 'texto' || type.indexOf('solicitud_') === 0) {
      body.textContent = m.content || '';
    } else {
      body.textContent = attachmentLabel(type);
    }
    row.appendChild(body);

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
