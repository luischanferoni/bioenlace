/**
 * Headers unificados para llamadas a /api/v1 desde el frontend web (SPA y vistas Yii).
 *
 * - Base: {@see window.getBioenlaceApiClientHeaders} (layouts main / main_sinmenuizquierda: Bearer, X-App-*, X-Client).
 * - Extensión: pasar `extra` para añadir o sobrescribir cabeceras puntuales.
 *
 * Ejemplo:
 *   BioenlaceApiClient.mergeHeaders({ 'X-Requested-With': 'XMLHttpRequest' });
 */
(function (window) {
  'use strict';

  var NS = (window.BioenlaceApiClient = window.BioenlaceApiClient || {});

  var webJwtRefreshInFlight = null;

  NS.pdfDownloadButtonLabel = function () {
    return 'Descargar PDF';
  };

  NS.pdfDownloadUnavailableMessage = function () {
    return 'PDF no disponible.';
  };

  NS.pdfDownloadErrorMessage = function () {
    return 'No se pudo descargar el PDF. Intentá de nuevo.';
  };

  NS.flowCompletedSummaryFallback = function () {
    return 'Listo.';
  };

  NS.flowRequiredUiUnavailableMessage = function (actionId) {
    return 'No puedo abrir la mini-UI requerida (' + String(actionId) + ').';
  };

  NS.flowUiUndeterminedMessage = function () {
    return 'No puedo determinar la UI a abrir para este paso.';
  };

  NS.suggestedActionsHeading = function () {
    return 'Acciones sugeridas:';
  };

  NS.suggestedQueryLabel = function () {
    return 'Sugerencia:';
  };

  NS.crudDeleteConfirmLabel = function () {
    return 'Confirmar Eliminación';
  };

  NS.crudCancelLabel = function () {
    return 'Cancelar';
  };

  NS.crudContinueLabel = function () {
    return 'Continuar';
  };

  NS.crudFormHeading = function () {
    return 'Formulario';
  };

  NS.crudSaveLabel = function () {
    return 'Guardar';
  };

  NS.crudClearLabel = function () {
    return 'Limpiar';
  };

  NS.crudNoActionsMessage = function () {
    return 'No hay acciones disponibles';
  };

  NS.assistantNoSpecificActionsMessage = function () {
    return 'No se encontraron acciones específicas para esta consulta.';
  };

  NS.shortcutsHeading = function () {
    return 'Atajos';
  };

  NS.shortcutsEmptyMessage = function () {
    return 'No hay atajos disponibles.';
  };

  NS.shortcutsLoadErrorMessage = function () {
    return 'No se pudieron cargar los atajos.';
  };

  NS.autocompletePlaceholder = function () {
    return 'Seleccionar...';
  };

  NS.autocompleteChooseLabel = function () {
    return 'Elegir';
  };

  NS.selectPlaceholder = function () {
    return 'Seleccione...';
  };

  NS.composerMinimumLengthMessage = function (minLength) {
    return 'Contanos tu consulta con al menos ' + String(minLength) + ' caracteres.';
  };

  NS.composerMissingDataMessage = function (missing) {
    return 'Faltan datos: ' + missing.join(', ');
  };

  NS.composerSubmitError = function () {
    return 'No se pudo enviar la consulta.';
  };

  NS.flowDisplayError = function () {
    return 'No se pudo mostrar el flujo en el chat.';
  };

  NS.flowActiveStepUiError = function () {
    return 'No se pudo montar la UI del paso activo.';
  };

  NS.enterConsultationPrompt = function () {
    return 'Por favor, ingresa una consulta';
  };

  NS.crudConfirmedLabel = function () {
    return 'Confirmado';
  };

  NS.personSearchLabel = function () {
    return 'Buscar persona';
  };

  NS.noResultsMessage = function () {
    return 'Sin resultados';
  };

  NS.crudOperationSuccessMessage = function () {
    return 'Operación realizada exitosamente';
  };

  NS.crudSaveFallbackError = function () {
    return 'Error al guardar';
  };

  NS.crudSaveConnectionError = function () {
    return 'Error de conexión al guardar';
  };

  NS.crudDeleteConfirmationPrompt = function () {
    return '¿Estás seguro de que quieres eliminar este registro? Esta acción no se puede deshacer.';
  };

  NS.crudRecordDeletedMessage = function () {
    return 'Registro eliminado exitosamente';
  };

  NS.crudDeleteFallbackError = function () {
    return 'Error al eliminar';
  };

  NS.crudDeleteConnectionError = function () {
    return 'Error de conexión al eliminar';
  };

  NS.assistantInvalidActionIdMessage = function () {
    return 'Acción inválida: falta action_id';
  };

  NS.alertsEmptyMessage = function () {
    return 'No hay alertas.';
  };

  NS.alertsLoadErrorMessage = function () {
    return 'No se pudieron cargar las alertas.';
  };

  NS.openAttachmentLabel = function () {
    return 'Abrir';
  };

  NS.encounterMissingIdentifiersMessage = function () {
    return 'Falta turno_id o encounter_id para ver la consulta.';
  };

  NS.currentVitalsTitle = function (date) {
    return date ? 'SIGNOS VITALES ACTUALES (' + String(date) + ')' : 'SIGNOS VITALES ACTUALES';
  };

  NS.triageModalTitle = function () {
    return 'Triage';
  };

  NS.triageReasonRequiredMessage = function () {
    return 'Indicá el motivo de consulta.';
  };

  NS.patientLeftMessage = function () {
    return 'Paciente se retiró';
  };

  NS.personSearchRequiredFieldsMessage = function () {
    return 'Por favor, ingrese los campos obligatorios para poder buscar al paciente.';
  };

  NS.personIdentityManualEntryMessage = function () {
    return 'Debe ingresar los datos manualmente. No se ha podido leer correctamente el DNI';
  };

  /**
   * @param {Object<string,string>=} extra Se fusiona encima del base (Object.assign final).
   * @returns {Object<string,string>}
   */
  NS.mergeHeaders = function (extra) {
    var base = {};
    if (typeof window.getBioenlaceApiClientHeaders === 'function') {
      base = window.getBioenlaceApiClientHeaders({});
    } else {
      var v =
        window.spaConfig && window.spaConfig.appVersion
          ? String(window.spaConfig.appVersion)
          : '1.0.0';
      base = {
        'X-App-Client': 'web-frontend',
        'X-App-Version': v,
        'X-Client': 'web',
      };
    }
    return Object.assign({}, base, extra || {});
  };

  /**
   * Resuelve una ruta API o una URL externa sin alterar el resolver de la SPA.
   * @param {string} path Ruta o URL recibida del contrato.
   * @returns {string}
   */
  NS.resolveApiUrl = function (path) {
    path = String(path || '');
    if (typeof window.resolveSpaFetchUrl === 'function') {
      return window.resolveSpaFetchUrl(path);
    }
    if (/^https?:\/\//i.test(path)) {
      return path;
    }
    if (typeof NS.normalizeApiV1Path === 'function') {
      path = NS.normalizeApiV1Path(path);
    }
    if (path.indexOf('/api/') === 0) {
      return window.location.origin + path;
    }
    var base =
      window.spaConfig && window.spaConfig.apiBase
        ? String(window.spaConfig.apiBase)
        : '';
    if (base && base.slice(-1) === '/') {
      base = base.slice(0, -1);
    }
    return base + path;
  };

  function isSameOriginUrl(url) {
    var requestUrl;
    try {
      requestUrl = new URL(url, window.location.origin);
    } catch (error) {
      return false;
    }
    return requestUrl.origin === window.location.origin;
  }

  /**
   * @param {string} url URL absoluta o relativa de la solicitud.
   * @returns {Object<string,string>} Solo incluye cabeceras autenticadas same-origin.
   */
  NS.headersForUrl = function (url) {
    return isSameOriginUrl(url) ? NS.mergeHeaders({}) : {};
  };

  /**
   * @param {Response} response Respuesta de un endpoint de descarga PDF.
   * @returns {Promise<Blob>} Contenido PDF o rechazo si la respuesta no es PDF.
   */
  NS.pdfBlobFromResponse = function (response) {
    if (!response.ok) {
      return Promise.reject(new Error('HTTP ' + response.status));
    }
    var contentType = (response.headers.get('content-type') || '')
      .split(';', 1)[0]
      .trim()
      .toLowerCase();
    if (contentType !== 'application/pdf') {
      return Promise.reject(new Error('Unexpected PDF content type: ' + contentType));
    }
    return response.blob();
  };

  /**
   * Descarga un blob mediante un enlace temporal y libera su object URL.
   * @param {Blob} blob
   * @param {string} filename
   */
  NS.downloadBlob = function (blob, filename) {
    var objectUrl = window.URL.createObjectURL(blob);
    var link;
    try {
      link = window.document.createElement('a');
      link.href = objectUrl;
      link.download = filename || 'download.pdf';
      window.document.body.appendChild(link);
      link.click();
    } finally {
      try {
        if (link) {
          link.remove();
        }
      } finally {
        window.setTimeout(function () {
          window.URL.revokeObjectURL(objectUrl);
        }, 500);
      }
    }
  };

  /**
   * Solicita y descarga un PDF, aplicando la política común de origen y MIME.
   * @param {string} url URL ya resuelta.
   * @param {string} filename Nombre del archivo descargado.
   * @returns {Promise<void>}
   */
  NS.downloadPdf = function (url, filename) {
    function request(jwtRetried) {
      var headers = NS.headersForUrl(url);
      return window.fetch(url, {
        method: 'GET',
        credentials: isSameOriginUrl(url) ? 'same-origin' : 'omit',
        headers: headers,
      }).then(function (response) {
        if (response.status !== 401 || !isSameOriginUrl(url)) {
          return NS.pdfBlobFromResponse(response);
        }
        if (jwtRetried) {
          NS.handleUnauthorized(response.status, null);
          return NS.pdfBlobFromResponse(response);
        }
        return NS.refreshWebJwtFromSession().then(function (refreshed) {
          if (!refreshed) {
            NS.handleUnauthorized(response.status, null);
            return NS.pdfBlobFromResponse(response);
          }
          return request(true);
        });
      });
    }

    var ready = isSameOriginUrl(url) && !window.apiAuthToken
      ? NS.refreshWebJwtFromSession()
      : Promise.resolve(true);
    return ready.then(function () {
      return request(false);
    }).then(function (blob) {
      NS.downloadBlob(blob, filename);
    });
  };

  /** Alias de {@link NS.mergeHeaders} (mismo contrato). */
  NS.apiHeaders = NS.mergeHeaders;

  NS.logoutUrl = function () {
    return '/auth/logout';
  };

  /**
   * Headers para endpoints que autentican solo con cookie de sesión (sin Bearer).
   * @param {Object<string,string>=} extra
   * @returns {Object<string,string>}
   */
  NS.mergeSessionHeaders = function (extra) {
    var headers = NS.mergeHeaders(extra || {});
    delete headers.Authorization;
    delete headers.authorization;
    return headers;
  };

  /**
   * @param {number} status HTTP status
   * @param {object|null|undefined} body JSON parseado
   * @returns {boolean}
   */
  NS.isUnauthorizedApi = function (status, body) {
    return status === 401;
  };

  NS.redirectToLoginOnUnauthorized = function () {
    if (window.__bioenlaceRedirectingToLogin) {
      return;
    }
    window.__bioenlaceRedirectingToLogin = true;
    window.location.replace(NS.logoutUrl());
  };

  /**
   * Obtiene el JWT vigente de la sesión web (cookie) y actualiza window.apiAuthToken.
   * @returns {Promise<boolean>}
   */
  NS.refreshWebJwtFromSession = function () {
    if (webJwtRefreshInFlight) {
      return webJwtRefreshInFlight;
    }

    var url = NS.logoutUrl().replace(/\/logout\/?$/, '/web-jwt');
    if (!/^https?:\/\//i.test(url)) {
      url = window.location.origin + url;
    }

    webJwtRefreshInFlight = window
      .fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: NS.mergeSessionHeaders({ Accept: 'application/json' }),
      })
      .then(function (response) {
        return response.json().then(function (json) {
          if (!response.ok || !json || json.success !== true) {
            return false;
          }
          var token = json.data && json.data.token ? String(json.data.token) : '';
          if (!token) {
            return false;
          }
          window.apiAuthToken = token;
          return true;
        });
      })
      .catch(function () {
        return false;
      })
      .finally(function () {
        webJwtRefreshInFlight = null;
      });

    return webJwtRefreshInFlight;
  };

  /**
   * @param {number} status
   * @param {object|null|undefined} body
   * @returns {boolean} true si redirigió
   */
  NS.handleUnauthorized = function (status, body) {
    if (!NS.isUnauthorizedApi(status, body)) {
      return false;
    }
    NS.redirectToLoginOnUnauthorized();
    return true;
  };

  /**
   * Mensaje para UI: no exponer "Failed to fetch" / HTTP crudo.
   * Los mensajes de dominio (API en español) se dejan tal cual.
   *
   * @param {string|Error|null|undefined} err
   * @returns {string}
   */
  NS.friendlyErrorMessage = function (err) {
    if (err && err.name === 'AbortError') {
      return '';
    }
    var raw = '';
    if (typeof err === 'string') {
      raw = err;
    } else if (err && err.message != null) {
      raw = String(err.message);
    }
    var msg = raw.trim();
    var lower = msg.toLowerCase();
    if (
      msg === '' ||
      lower === 'failed to fetch' ||
      lower.indexOf('failed to fetch') !== -1 ||
      lower.indexOf('networkerror') === 0 ||
      lower === 'load failed' ||
      lower === 'network request failed' ||
      lower === 'the internet connection appears to be offline.'
    ) {
      return 'No hay conexión. Probá de nuevo en un momento.';
    }
    if (lower.indexOf('se esperaba json') === 0) {
      return 'No se pudo completar la consulta. Probá de nuevo.';
    }
    if (/^HTTP 5\d\d/.test(msg)) {
      return 'El servicio no está disponible. Probá de nuevo en un momento.';
    }
    if (/^HTTP \d+/.test(msg)) {
      return 'No se pudo completar la consulta. Probá de nuevo.';
    }
    return msg;
  };

  /**
   * Extrae mensaje legible del cuerpo JSON de error API ({ message, error, errors._error }).
   * @param {string|object|null|undefined} body
   * @returns {string}
   */
  NS.extractApiErrorMessage = function (body) {
    if (body == null) {
      return '';
    }
    var j = body;
    if (typeof body === 'string') {
      var t = String(body).trim();
      if (t === '') {
        return '';
      }
      try {
        j = JSON.parse(t);
      } catch (e) {
        return '';
      }
    }
    if (!j || typeof j !== 'object') {
      return '';
    }
    if (j.message != null && String(j.message).trim() !== '') {
      return String(j.message).trim();
    }
    if (j.error != null && String(j.error).trim() !== '') {
      return String(j.error).trim();
    }
    if (j.errors && typeof j.errors === 'object') {
      var err = j.errors._error;
      if (Array.isArray(err) && err.length >= 1 && err[0] != null && String(err[0]).trim() !== '') {
        return String(err[0]).trim();
      }
    }
    return '';
  };

  /**
   * @param {Response} response
   * @returns {Promise<never>}
   */
  NS.errorFromFailedResponse = function (response) {
    return response.text().then(function (text) {
      var msg = NS.extractApiErrorMessage(text);
      if (msg) {
        throw new Error(msg);
      }
      throw new Error('HTTP ' + response.status);
    });
  };

  /**
   * Reconstruye headers tras renovar JWT: no reutilizar Authorization viejo (pisaría el nuevo).
   * @param {Object<string,string>|Headers|undefined} prev
   * @returns {Object<string,string>}
   */
  function headersForJwtRetry(prev) {
    var plain = {};
    if (prev && typeof prev.forEach === 'function') {
      prev.forEach(function (value, key) {
        plain[key] = value;
      });
    } else if (prev && typeof prev === 'object') {
      plain = Object.assign({}, prev);
    }
    delete plain.Authorization;
    delete plain.authorization;
    return NS.mergeHeaders(plain);
  }

  /**
   * @param {string} url
   * @param {RequestInit} opts
   * @param {boolean} jwtRetried
   * @returns {Promise<{response: Response, json: *}>}
   */
  function fetchJsonInternal(url, opts, jwtRetried) {
    return window.fetch(url, opts).then(function (response) {
      var ct = (response.headers.get('content-type') || '').toLowerCase();
      if (!ct.includes('application/json')) {
        if (NS.isUnauthorizedApi(response.status, null)) {
          if (!jwtRetried) {
            return NS.refreshWebJwtFromSession().then(function (ok) {
              if (!ok) {
                NS.handleUnauthorized(response.status, null);
                return { response: response, json: null };
              }
              var retryOpts = Object.assign({}, opts, {
                headers: headersForJwtRetry(opts.headers),
              });
              return fetchJsonInternal(url, retryOpts, true);
            });
          }
          NS.handleUnauthorized(response.status, null);
          return { response: response, json: null };
        }
        return response.text().then(function (text) {
          throw new Error('Se esperaba JSON. Recibido: ' + String(text || '').slice(0, 120));
        });
      }

      return response.json().then(function (json) {
        if (NS.isUnauthorizedApi(response.status, json)) {
          if (!jwtRetried) {
            return NS.refreshWebJwtFromSession().then(function (ok) {
              if (!ok) {
                NS.handleUnauthorized(response.status, json);
                return { response: response, json: json };
              }
              var retryOpts = Object.assign({}, opts, {
                headers: headersForJwtRetry(opts.headers),
              });
              return fetchJsonInternal(url, retryOpts, true);
            });
          }
          NS.handleUnauthorized(response.status, json);
          return { response: response, json: json };
        }
        if (!response.ok) {
          var msg = NS.extractApiErrorMessage(json);
          if (!msg) {
            msg = 'HTTP ' + response.status;
          }
          throw new Error(String(msg));
        }
        return { response: response, json: json };
      });
    }).catch(function (e) {
      if (e && e.name === 'AbortError') {
        throw e;
      }
      throw new Error(NS.friendlyErrorMessage(e));
    });
  }

  /**
   * fetch + JSON con reintento de JWT vía sesión y redirección a login si persiste 401.
   * Siempre fusiona headers base (Bearer, X-Client, …) aunque el caller pase `headers`
   * parciales — si no, la 1.ª carga (p. ej. home/panel) sale sin Authorization y falla 401.
   *
   * @param {string} url
   * @param {RequestInit=} options
   * @returns {Promise<{response: Response, json: *}>}
   */
  NS.fetchJson = function (url, options) {
    var opts = Object.assign({ credentials: 'same-origin' }, options || {});
    var extra = Object.assign({ Accept: 'application/json' }, opts.headers || {});

    var run = function () {
      opts.headers = NS.mergeHeaders(extra);
      return fetchJsonInternal(url, opts, false);
    };

    // Primera pintura: layout puede tener apiAuthToken null; obtener JWT antes del 1.er API call.
    if (!window.apiAuthToken) {
      return NS.refreshWebJwtFromSession().then(function () {
        return run();
      });
    }

    return run();
  };

  /**
   * Path HTTP bajo `/api/v1/...` (p. ej. `/api/clinical/...` de RBAC → `/api/v1/clinical/...`).
   * @param {string} path
   * @returns {string}
   */
  NS.normalizeApiV1Path = function (path) {
    var p = String(path || '').trim();
    if (!p) return '';
    if (/^https?:\/\//i.test(p)) return p;
    if (p.charAt(0) !== '/') p = '/' + p;
    if (/^\/api\/v\d+\//i.test(p)) return p;
    if (p.indexOf('/api/') === 0) return '/api/v1/' + p.slice(5);
    return '/api/v1/' + p.replace(/^\//, '');
  };
})(window);
