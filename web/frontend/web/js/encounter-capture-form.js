/**
 * Captura clínica: pipeline por etapas (subir → STT → analizar → guardar), sync sin jobs.
 */
(function (window) {
    'use strict';

    var SpeechRecognitionCtor =
        window.SpeechRecognition || window.webkitSpeechRecognition || null;

    function mergeApiPayload(data) {
        if (typeof window.mergeData === 'function') {
            return window.mergeData(data);
        }
        var out = Object.assign({}, data || {});
        out.userPerTabConfig = window.userPerTabConfig || {};
        return out;
    }

    function assessLocalQuality(text, meta) {
        var t = (text || '').trim();
        var reasons = [];
        if (t.length < 3) {
            reasons.push('texto_muy_corto');
        }
        if (meta.confidence > 0 && meta.confidence < 0.85) {
            reasons.push('confianza_baja');
        }
        if (meta.durationMs > 0) {
            var words = t.split(/\s+/).filter(Boolean).length;
            var wpm = words / (meta.durationMs / 60000);
            if (wpm < 20) {
                reasons.push('pocas_palabras_para_duracion');
            }
        }
        return { ok: reasons.length === 0, reasons: reasons };
    }

    function blobToBase64(blob) {
        return new Promise(function (resolve, reject) {
            var reader = new FileReader();
            reader.onloadend = function () {
                resolve(reader.result);
            };
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        });
    }

    function parseSttConfig(formEl) {
        var defaults = {
            device_enabled: true,
            server_enabled: true,
            proveedor_servidor: 'groq',
            server_configured: false,
        };
        if (!formEl || !formEl.dataset || !formEl.dataset.sttConfig) {
            return defaults;
        }
        try {
            return Object.assign(defaults, JSON.parse(formEl.dataset.sttConfig));
        } catch (e) {
            return defaults;
        }
    }

    function EncounterCaptureForm(formEl) {
        this.form = formEl;
        this.sttConfig = parseSttConfig(formEl);
        this.textarea = formEl.querySelector('#chat-input');
        this.analyzeBtn = formEl.querySelector('#analyze-consultation');
        this.chatFormSection = formEl.querySelector('#chat-form');
        this.analyzeSection = formEl.querySelector('#analyze-btn');
        this.responseEl = formEl.querySelector('#agent-response');
        this.reviewRoot = formEl.querySelector('#capture-review-root');
        this.responseContent = formEl.querySelector('#response-content');
        this.reviewActions = formEl.querySelector('#capture-review-actions');
        this.editBtn = formEl.querySelector('#capture-edit-btn');
        this.cancelEditBtn = formEl.querySelector('#capture-cancel-edit-btn');
        this.discardBtn = formEl.querySelector('#capture-discard-btn');
        this.confirmBtn = formEl.querySelector('#send-message');

        this.recognition = null;
        this.mediaRecorder = null;
        this.audioChunks = [];
        this.listening = false;
        this.dictationStartedAt = 0;
        this.lastSttMeta = null;
        this.pendingAudioBlob = null;
        this.lastAnalysisPayload = null;
        this.captureReview = null;
        this.draftText = '';
        this.inReview = false;
        this.editingDraft = false;
        this.editSnapshot = null;
        this.initialTextOnListen = '';
        this.audioOnlyRecording = false;
        this._deviceSttFailed = false;
        this._micStream = null;
        this.clientCaptureId = null;
        this.serverCaptureId = null;
        this.serverStage = null;
        /** @type {Object.<string, string|number>} */
        this.resolutionAccum = {};

        if (SpeechRecognitionCtor && this.textarea && this.sttConfig.device_enabled) {
            this.recognition = new SpeechRecognitionCtor();
            this.recognition.continuous = true;
            this.recognition.interimResults = true;
            this.recognition.lang = this.textarea.lang || document.documentElement.lang || 'es-AR';
        }
    }

    EncounterCaptureForm.prototype.setStatus = function (message, level) {
        if (!this.statusEl) {
            return;
        }
        this.statusEl.className = 'small mt-1 text-' + (level || 'muted');
        this.statusEl.textContent = message || '';
    };

    EncounterCaptureForm.prototype.clearSaveAlert = function () {
        if (!this.saveAlertEl) {
            return;
        }
        this.saveAlertEl.classList.add('d-none');
        this.saveAlertEl.textContent = '';
    };

    /**
     * Alerta visible junto a Guardar (el status STT queda arriba y no se ve en review).
     */
    EncounterCaptureForm.prototype.showSaveAlert = function (message, level) {
        var text = String(message || '').trim();
        if (!text) {
            text = 'No se pudo guardar la captura.';
        }
        this.setStatus(text, level || 'danger');
        if (!this.saveAlertEl) {
            return;
        }
        var tone = level || 'danger';
        this.saveAlertEl.className = 'alert alert-' + tone + ' mb-3';
        this.saveAlertEl.textContent = text;
        this.saveAlertEl.classList.remove('d-none');
        if (typeof this.saveAlertEl.scrollIntoView === 'function') {
            this.saveAlertEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    };

    EncounterCaptureForm.prototype.bind = function () {
        var self = this;
        this.statusEl = this.form.querySelector('#encounter-stt-status');
        this.saveAlertEl = this.form.querySelector('#capture-save-alert');
        var micBtn = this.form.querySelector('#encounter-dictate-btn');

        if (micBtn) {
            micBtn.addEventListener('click', function () {
                self.toggleDictation();
            });
        }
        if (this.analyzeBtn) {
            this.analyzeBtn.addEventListener('click', function () {
                self.analyze();
            });
        }
        if (this.confirmBtn) {
            this.confirmBtn.addEventListener('click', function () {
                self.save();
            });
        }
        if (this.editBtn) {
            this.editBtn.addEventListener('click', function () {
                self.editDraft();
            });
        }
        if (this.cancelEditBtn) {
            this.cancelEditBtn.addEventListener('click', function () {
                self.cancelEditDraft();
            });
        }
        if (this.discardBtn) {
            this.discardBtn.addEventListener('click', function () {
                self.discardDraft();
            });
        }
        if (this.textarea) {
            this.textarea.addEventListener('input', function () {
                self.onTextEdited();
            });
        }
        if (this.recognition) {
            this.setupRecognition();
        }
        this.applySttUiPolicy();
        this.loadOpenCaptures();
    };

    EncounterCaptureForm.prototype.newClientCaptureId = function () {
        return 'cap_web_' + Date.now() + '_' + Math.floor(Math.random() * 1e6);
    };

    EncounterCaptureForm.prototype.ensureClientCaptureId = function () {
        if (!this.clientCaptureId) {
            this.clientCaptureId = this.newClientCaptureId();
        }
        return this.clientCaptureId;
    };

    EncounterCaptureForm.prototype.apiHeadersJson = function () {
        return window.BioenlaceApiClient.mergeHeaders({
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        });
    };

    EncounterCaptureForm.prototype.apiHeadersMultipart = function () {
        return window.BioenlaceApiClient.mergeHeaders({
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        });
    };

    EncounterCaptureForm.prototype.readFormContext = function () {
        var formData = new FormData(this.form);
        return {
            id_persona: formData.get('id_persona'),
            parent: formData.get('parent'),
            parent_id: formData.get('parent_id'),
        };
    };

    EncounterCaptureForm.prototype.loadOpenCaptures = function () {
        var self = this;
        var ctx = this.readFormContext();
        if (!ctx.id_persona) {
            return;
        }
        var qs = new URLSearchParams();
        qs.set('id_persona', String(ctx.id_persona));
        if (ctx.parent) qs.set('parent', String(ctx.parent));
        if (ctx.parent_id) qs.set('parent_id', String(ctx.parent_id));
        fetch('/api/v1/clinical/encounter/captura/listar?' + qs.toString(), {
            method: 'GET',
            headers: this.apiHeadersJson(),
            credentials: 'same-origin',
        })
            .then(function (r) {
                return r.json();
            })
            .then(function (data) {
                if (!data || !data.success || !Array.isArray(data.items) || !data.items.length) {
                    return;
                }
                var items = data.items.slice();
                items.sort(function (a, b) {
                    var rank = function (stage) {
                        if (stage === 'READY_FOR_REVIEW' || stage === 'SAVE_FAILED') return 0;
                        if (stage === 'ANALYSIS_FAILED' || stage === 'TRANSCRIBED') return 1;
                        return 2;
                    };
                    return rank(a.stage) - rank(b.stage);
                });
                var item = items[0];
                self.clientCaptureId = item.client_capture_id || self.clientCaptureId;
                self.serverCaptureId = item.id || null;
                self.serverStage = item.stage || null;

                if (item.stage === 'READY_FOR_REVIEW' || item.stage === 'SAVE_FAILED') {
                    return self.openCaptureReview(item);
                }

                if (item.transcript && self.textarea && !self.textarea.value.trim()) {
                    self.textarea.value = item.transcript;
                }
                if (item.stage === 'UPLOADED' || item.stage === 'STT_FAILED') {
                    self.setStatus('Sin transcribir', 'warning');
                } else if (item.stage === 'TRANSCRIBED' || item.stage === 'ANALYSIS_FAILED') {
                    self.setStatus('Sin analizar', 'warning');
                }
            })
            .catch(function () {
                /* sin red: ok */
            });
    };

    /**
     * Abre la revisión de una captura ya analizada (cross-device / reload).
     */
    EncounterCaptureForm.prototype.openCaptureReview = function (item) {
        var self = this;
        var applyPayload = function (payload) {
            if (!payload) {
                return;
            }
            self.lastAnalysisPayload = payload;
            self.draftText =
                payload.texto_original ||
                payload.transcript ||
                (item && item.transcript) ||
                '';
            if (self.responseEl) {
                self.responseEl.style.display = 'block';
            }
            var usedReview = self.renderCaptureReview(payload);
            if (!usedReview) {
                self.renderLegacyHtml(payload.html || '');
                if (self.reviewActions) {
                    self.reviewActions.style.display = '';
                }
            } else {
                if (self.editBtn) {
                    self.editBtn.style.display = '';
                }
                if (self.discardBtn) {
                    self.discardBtn.style.display = '';
                }
                self.setCaptureMode(true);
            }
            self.setStatus('', 'muted');
        };

        var payloadFromItem = null;
        if (item) {
            if (item.analysis && typeof item.analysis === 'object') {
                payloadFromItem = item.analysis;
            } else if (item.capture_review || item.datosExtraidos) {
                payloadFromItem = item;
            }
        }
        if (payloadFromItem && payloadFromItem.capture_review) {
            self.applyCaptureResponse(item, payloadFromItem);
            applyPayload(payloadFromItem);
            return Promise.resolve();
        }

        var qs = new URLSearchParams();
        if (item && item.client_capture_id) {
            qs.set('client_capture_id', String(item.client_capture_id));
        }
        if (item && item.id) {
            qs.set('capture_id', String(item.id));
        }
        return fetch('/api/v1/clinical/encounter/captura/ver?' + qs.toString(), {
            method: 'GET',
            headers: this.apiHeadersJson(),
            credentials: 'same-origin',
        })
            .then(function (r) {
                return r.json();
            })
            .then(function (data) {
                if (!data || !data.success || !data.capture) {
                    self.setStatus((data && data.message) || 'No se pudo cargar la captura.', 'danger');
                    return;
                }
                var capture = data.capture;
                var payload = self.applyCaptureResponse(capture, capture.analysis || capture);
                applyPayload(payload);
            })
            .catch(function () {
                self.setStatus('No se pudo cargar la captura.', 'danger');
            });
    };

    EncounterCaptureForm.prototype.applyCaptureResponse = function (capture, analysisPayload) {
        if (!capture) {
            return analysisPayload || null;
        }
        this.clientCaptureId = capture.client_capture_id || this.clientCaptureId;
        this.serverCaptureId = capture.id || this.serverCaptureId;
        this.serverStage = capture.stage || this.serverStage;
        if (analysisPayload) {
            return analysisPayload;
        }
        if (capture.analysis && typeof capture.analysis === 'object') {
            return capture.analysis;
        }
        return capture;
    };

    EncounterCaptureForm.prototype.setCaptureMode = function (reviewing) {
        this.inReview = !!reviewing;
        if (this.chatFormSection) {
            this.chatFormSection.style.display = reviewing ? 'none' : '';
        }
        if (this.analyzeSection) {
            this.analyzeSection.style.display = reviewing ? 'none' : '';
        }
        if (this.reviewActions) {
            this.reviewActions.style.display = reviewing ? '' : 'none';
        }
    };

    EncounterCaptureForm.prototype.syncResolutionAccum = function () {
        if (!window.EncounterCaptureReview || !this.reviewRoot) {
            return this.resolutionAccum || {};
        }
        var live = window.EncounterCaptureReview.collectResolutions(this.reviewRoot);
        var staged = window.EncounterCaptureReview.collectStagedIds(this.reviewRoot);
        var merged = Object.assign({}, this.resolutionAccum || {}, live);
        Object.keys(merged).forEach(function (issueId) {
            var m = String(issueId).match(/^(.*)::(\d+):/);
            if (!m) {
                return;
            }
            var itemId = m[1] + '::' + m[2];
            if (!staged.has(itemId)) {
                delete merged[issueId];
            }
        });
        this.resolutionAccum = merged;
        return merged;
    };

    EncounterCaptureForm.prototype.updateConfirmState = function () {
        if (!this.confirmBtn) {
            return;
        }
        if (
            this.lastAnalysisPayload &&
            this.lastAnalysisPayload.capture_review &&
            this.lastAnalysisPayload.capture_review.system_error
        ) {
            this.confirmBtn.disabled = true;
            return;
        }
        if (this.captureReview && this.captureReview.system_error) {
            this.confirmBtn.disabled = true;
            return;
        }
        if (!window.EncounterCaptureReview) {
            this.confirmBtn.disabled = false;
            return;
        }
        var staged = window.EncounterCaptureReview.collectStagedIds(this.reviewRoot);
        var resolutions = this.syncResolutionAccum();
        var can =
            this.captureReview &&
            window.EncounterCaptureReview.canConfirm(this.captureReview, staged, resolutions);
        this.confirmBtn.disabled = !can;
    };

    EncounterCaptureForm.prototype.renderCaptureReview = function (data) {
        var review = data.capture_review;
        if (!review || !window.EncounterCaptureReview || !this.reviewRoot) {
            return false;
        }

        this.captureReview = review;
        this.clearSaveAlert();
        var rendered = window.EncounterCaptureReview.render(review, {
            textoFormateado: data.texto_formateado || null,
        });
        this.reviewRoot.replaceChildren();
        if (rendered && rendered.node) {
            this.reviewRoot.appendChild(rendered.node);
        }
        window.EncounterCaptureReview.bindItemToggles(
            this.reviewRoot,
            this.updateConfirmState.bind(this)
        );
        window.EncounterCaptureReview.bindIssueResolutions(
            this.reviewRoot,
            this.updateConfirmState.bind(this)
        );
        if (this.resolutionAccum && Object.keys(this.resolutionAccum).length) {
            window.EncounterCaptureReview.applyResolutionsToDom(
                this.reviewRoot,
                this.resolutionAccum
            );
        }

        if (this.responseContent) {
            this.responseContent.innerHTML = '';
            this.responseContent.classList.add('d-none');
            this.responseContent.setAttribute('aria-hidden', 'true');
        }

        this.updateConfirmState();
        return true;
    };

    EncounterCaptureForm.prototype.renderLegacyHtml = function (html) {
        if (!this.responseContent) {
            return;
        }
        if (!html) {
            this.setStatus('No se pudo mostrar el análisis.', 'danger');
        }
        if (this.reviewRoot) {
            this.reviewRoot.innerHTML = '';
        }
        this.captureReview = null;
        this.responseContent.innerHTML = html || '';
        this.responseContent.classList.remove('d-none');
        this.responseContent.removeAttribute('aria-hidden');
        if (this.confirmBtn) {
            this.confirmBtn.disabled = false;
        }
    };

    EncounterCaptureForm.prototype.discardDraft = function () {
        var self = this;
        this.clearSaveAlert();
        var clientId = this.clientCaptureId;
        if (clientId) {
            fetch('/api/v1/clinical/encounter/captura/descartar', {
                method: 'POST',
                headers: this.apiHeadersJson(),
                credentials: 'same-origin',
                body: JSON.stringify({
                    client_capture_id: clientId,
                    capture_id: this.serverCaptureId || undefined,
                }),
            }).catch(function () {});
        }
        this.clientCaptureId = null;
        this.serverCaptureId = null;
        this.serverStage = null;
        this.lastAnalysisPayload = null;
        this.captureReview = null;
        this.draftText = '';
        this.editSnapshot = null;
        this.setEditingMode(false);
        if (this.reviewRoot) {
            this.reviewRoot.innerHTML = '';
        }
        if (this.responseContent) {
            this.responseContent.innerHTML = '';
            this.responseContent.classList.add('d-none');
        }
        if (this.responseEl) {
            this.responseEl.style.display = 'none';
        }
        if (this.reviewActions) {
            this.reviewActions.style.display = 'none';
        }
        if (this.editBtn) {
            this.editBtn.style.display = '';
        }
        if (this.discardBtn) {
            this.discardBtn.style.display = '';
        }
        if (this.textarea) {
            this.textarea.value = '';
        }
        this.setCaptureMode(false);
        this.setStatus('', 'muted');
    };

    EncounterCaptureForm.prototype.setEditingMode = function (editing) {
        this.editingDraft = !!editing;
        if (this.cancelEditBtn) {
            this.cancelEditBtn.style.display = editing ? '' : 'none';
        }
    };

    EncounterCaptureForm.prototype.editDraft = function () {
        var stagedIds = [];
        if (window.EncounterCaptureReview && this.reviewRoot) {
            stagedIds = Array.from(
                window.EncounterCaptureReview.collectStagedIds(this.reviewRoot)
            );
        }
        this.editSnapshot = {
            lastAnalysisPayload: this.lastAnalysisPayload,
            captureReview: this.captureReview,
            draftText: this.draftText,
            stagedIds: stagedIds,
        };
        if (this.textarea) {
            this.textarea.value = this.draftText || '';
            this.textarea.focus();
        }
        this.lastAnalysisPayload = null;
        this.captureReview = null;
        if (this.reviewRoot) {
            this.reviewRoot.innerHTML = '';
        }
        if (this.responseEl) {
            this.responseEl.style.display = 'none';
        }
        this.setCaptureMode(false);
        this.setEditingMode(true);
        this.setStatus('', 'muted');
    };

    EncounterCaptureForm.prototype.cancelEditDraft = function () {
        var snap = this.editSnapshot;
        if (!snap) {
            this.setEditingMode(false);
            return;
        }
        this.editSnapshot = null;
        this.lastAnalysisPayload = snap.lastAnalysisPayload || null;
        this.captureReview = snap.captureReview || null;
        this.draftText = snap.draftText || '';
        if (this.textarea) {
            this.textarea.value = this.draftText;
        }
        this.setEditingMode(false);
        this.setStatus('', 'muted');
        if (this.lastAnalysisPayload) {
            if (
                this.lastAnalysisPayload.capture_review &&
                Array.isArray(snap.stagedIds)
            ) {
                this.lastAnalysisPayload.capture_review.default_staged_item_ids =
                    snap.stagedIds.slice();
            }
            if (this.responseEl) {
                this.responseEl.style.display = 'block';
            }
            var usedReview = this.renderCaptureReview(this.lastAnalysisPayload);
            if (!usedReview && this.lastAnalysisPayload.html) {
                this.renderLegacyHtml(this.lastAnalysisPayload.html);
                if (this.reviewActions) {
                    this.reviewActions.style.display = '';
                }
            } else {
                this.setCaptureMode(true);
            }
        } else {
            this.setCaptureMode(false);
        }
    };

    EncounterCaptureForm.prototype.applySttUiPolicy = function () {
        var micBtn = this.form.querySelector('#encounter-dictate-btn');
        var deviceOn = !!this.sttConfig.device_enabled;
        var serverOn = !!this.sttConfig.server_enabled;
        var canMic =
            !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);

        if (micBtn) {
            micBtn.disabled = !(canMic && (deviceOn || serverOn));
            micBtn.title = 'Dictar';
        }
        if (!deviceOn && !serverOn) {
            this.setStatus('Dictado por voz deshabilitado. Escribí el texto manualmente.', 'warning');
        }
    };

    EncounterCaptureForm.prototype.onTextEdited = function () {
        if (!this.lastSttMeta || !this.initialTextOnListen) {
            return;
        }
        var current = this.textarea.value || '';
        var base = this.initialTextOnListen;
        var added = current.length > base.length ? current.slice(base.length) : current;
        if (added.length > 0) {
            var maxLen = Math.max(base.length, current.length);
            var dist = Math.abs(current.length - (base.length + (this.lastSttMeta.rawDeviceText || '').length));
            this.lastSttMeta.client_edit_ratio = maxLen > 0 ? Math.min(1, dist / maxLen) : 0;
        }
    };

    EncounterCaptureForm.prototype.setupRecognition = function () {
        var self = this;
        var finalTranscript = '';

        this.recognition.onresult = function (event) {
            var interim = '';
            finalTranscript = '';
            for (var i = event.resultIndex; i < event.results.length; i++) {
                var res = event.results[i];
                var piece = res[0].transcript;
                if (res.isFinal) {
                    finalTranscript += piece;
                } else {
                    interim += piece;
                }
            }
            var prefix = self.initialTextOnListen;
            var combined = (prefix + ' ' + finalTranscript + interim).replace(/\s+/g, ' ').trim();
            self.textarea.value = combined;
            var conf = 0;
            if (event.results.length > 0 && event.results[event.results.length - 1][0].confidence) {
                conf = event.results[event.results.length - 1][0].confidence;
            }
            self.lastSttMeta = {
                provenance: 'device',
                engine: 'web_speech',
                locale: self.recognition.lang,
                confidence: conf,
                duration_ms: Date.now() - self.dictationStartedAt,
                text: combined,
                rawDeviceText: (finalTranscript + interim).trim(),
            };
        };

        this.recognition.onerror = function (event) {
            var err = event && event.error ? String(event.error) : '';
            // Al detener o sin habla: no es un fallo de producto.
            if (err === 'aborted' || err === 'no-speech') {
                return;
            }
            if (err === 'not-allowed' || err === 'service-not-allowed') {
                self.listening = false;
                self.audioOnlyRecording = false;
                self.stopAudioCapture();
                self.setStatus(
                    'Necesitamos permiso del micrófono para dictar. Activá el micrófono en el navegador y volvé a intentar.',
                    'warning'
                );
                return;
            }
            // STT del navegador falló: seguimos con el audio (silencioso).
            self._deviceSttFailed = true;
            try {
                self.recognition.stop();
            } catch (e) {
                /* ignore */
            }
        };

        this.recognition.onend = function () {
            if (self.listening && !self._deviceSttFailed) {
                try {
                    self.recognition.start();
                } catch (e) {
                    self._deviceSttFailed = true;
                }
            }
        };
    };

    EncounterCaptureForm.prototype.queryMicrophonePermission = function () {
        if (!navigator.permissions || !navigator.permissions.query) {
            return Promise.resolve(null);
        }
        try {
            return navigator.permissions
                .query({ name: 'microphone' })
                .then(function (status) {
                    return status && status.state ? String(status.state) : null;
                })
                .catch(function () {
                    return null;
                });
        } catch (e) {
            return Promise.resolve(null);
        }
    };

    EncounterCaptureForm.prototype.requestMicrophone = function () {
        if (!window.isSecureContext) {
            return Promise.reject(Object.assign(new Error('insecure'), { name: 'SecurityError' }));
        }
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            return Promise.reject(Object.assign(new Error('unsupported'), { name: 'NotSupportedError' }));
        }
        return navigator.mediaDevices.getUserMedia({ audio: true });
    };

    /**
     * Mensaje accionable: si el sitio ya bloqueó el mic, Chrome no vuelve a mostrar el diálogo.
     */
    EncounterCaptureForm.prototype.microphoneErrorMessage = function (err, permState) {
        var name = err && err.name ? String(err.name) : '';
        var msg = err && err.message ? String(err.message) : '';

        if (!window.isSecureContext || name === 'SecurityError' || msg === 'insecure') {
            return 'El dictado requiere una conexión segura (HTTPS o localhost).';
        }
        if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
            return 'No se encontró un micrófono en este equipo.';
        }
        if (name === 'NotReadableError' || name === 'TrackStartError') {
            return 'El micrófono está en uso por otra aplicación. Cerrala y volvé a intentar.';
        }
        if (name === 'NotSupportedError' || msg === 'unsupported') {
            return 'Este navegador no permite grabar audio. Escribí el texto.';
        }
        // Bloqueo previo: Chrome no muestra el popup otra vez.
        if (
            permState === 'denied' ||
            name === 'NotAllowedError' ||
            name === 'PermissionDeniedError' ||
            name === 'PermissionDismissedError'
        ) {
            return (
                'El micrófono está bloqueado para este sitio (por eso no aparece el pedido de permiso). ' +
                'En la barra de direcciones, hacé clic en el ícono del candado o del micrófono → ' +
                'Configuración del sitio / Permisos → Micrófono → Permitir. ' +
                'Después tocá Dictar de nuevo.'
            );
        }
        return 'No se pudo usar el micrófono. Revisá los permisos del sitio en la barra de direcciones y volvé a intentar.';
    };

    EncounterCaptureForm.prototype.startDictationSession = function () {
        var self = this;
        this.initialTextOnListen = this.textarea.value || '';
        this.dictationStartedAt = Date.now();
        this.pendingAudioBlob = null;
        this.lastSttMeta = null;
        this._deviceSttFailed = false;

        if (!window.isSecureContext) {
            this.setStatus(this.microphoneErrorMessage({ name: 'SecurityError' }, null), 'warning');
            return;
        }

        this.queryMicrophonePermission().then(function (permState) {
            // Si ya está denegado, getUserMedia falla al instante y Chrome no muestra diálogo.
            if (permState === 'denied') {
                self.setStatus(self.microphoneErrorMessage({ name: 'NotAllowedError' }, permState), 'warning');
                return;
            }

            self.setStatus(
                permState === 'granted' ? 'Escuchando…' : 'Esperando permiso del micrófono…',
                'muted'
            );

            self.requestMicrophone()
                .then(function (stream) {
                    if (!window.MediaRecorder) {
                        stream.getTracks().forEach(function (t) {
                            t.stop();
                        });
                        throw Object.assign(new Error('unsupported'), { name: 'NotSupportedError' });
                    }
                    try {
                        self.beginAudioRecorder(stream);
                    } catch (recErr) {
                        stream.getTracks().forEach(function (t) {
                            t.stop();
                        });
                        throw recErr;
                    }
                    var useRecognition =
                        !!self.sttConfig.device_enabled && !!self.recognition;
                    if (useRecognition) {
                        try {
                            self.recognition.start();
                            self.listening = true;
                            self.audioOnlyRecording = false;
                        } catch (e) {
                            self.listening = false;
                            self.audioOnlyRecording = true;
                            self._deviceSttFailed = true;
                        }
                    } else {
                        self.listening = false;
                        self.audioOnlyRecording = true;
                    }
                    self.setStatus('Escuchando… Tocá Dictar de nuevo para detener.', 'primary');
                })
                .catch(function (err) {
                    self.listening = false;
                    self.audioOnlyRecording = false;
                    self.queryMicrophonePermission().then(function (stateAfter) {
                        self.setStatus(
                            self.microphoneErrorMessage(err, stateAfter || permState),
                            'warning'
                        );
                    });
                });
        });
    };

    EncounterCaptureForm.prototype.beginAudioRecorder = function (stream) {
        var self = this;
        this.audioChunks = [];
        this._audioStopResolve = null;
        this._micStream = stream;
        this.mediaRecorder = new MediaRecorder(stream);
        this.mediaRecorder.ondataavailable = function (e) {
            if (e.data && e.data.size > 0) {
                self.audioChunks.push(e.data);
            }
        };
        this.mediaRecorder.onstop = function () {
            if (self._micStream) {
                self._micStream.getTracks().forEach(function (t) {
                    t.stop();
                });
                self._micStream = null;
            }
            if (self.audioChunks.length) {
                self.pendingAudioBlob = new Blob(self.audioChunks, { type: 'audio/webm' });
            }
            if (typeof self._audioStopResolve === 'function') {
                var resolve = self._audioStopResolve;
                self._audioStopResolve = null;
                resolve(self.pendingAudioBlob || null);
            }
        };
        this.mediaRecorder.start();
    };

    EncounterCaptureForm.prototype.stopAudioCapture = function () {
        var self = this;
        return new Promise(function (resolve) {
            if (!self.mediaRecorder || self.mediaRecorder.state === 'inactive') {
                if (self._micStream) {
                    self._micStream.getTracks().forEach(function (t) {
                        t.stop();
                    });
                    self._micStream = null;
                }
                resolve(self.pendingAudioBlob || null);
                return;
            }
            self._audioStopResolve = resolve;
            try {
                self.mediaRecorder.stop();
            } catch (e) {
                self._audioStopResolve = null;
                if (self._micStream) {
                    self._micStream.getTracks().forEach(function (t) {
                        t.stop();
                    });
                    self._micStream = null;
                }
                resolve(self.pendingAudioBlob || null);
            }
            self.mediaRecorder = null;
        });
    };

    EncounterCaptureForm.prototype.toggleDictation = function () {
        if (this.listening || this.audioOnlyRecording) {
            this.stopDictation();
            return;
        }
        if (!this.sttConfig.device_enabled && !this.sttConfig.server_enabled) {
            this.setStatus('Dictado por voz deshabilitado.', 'warning');
            return;
        }
        this.startDictationSession();
    };

    EncounterCaptureForm.prototype.stopDictation = function () {
        var self = this;
        var wasListening = this.listening || this.audioOnlyRecording;
        this.listening = false;
        this.audioOnlyRecording = false;
        if (this.recognition) {
            try {
                this.recognition.stop();
            } catch (e) {
                /* ignore */
            }
        }
        if (!wasListening) {
            return;
        }
        this.stopAudioCapture().then(function (blob) {
            if (self.lastSttMeta) {
                self.lastSttMeta.duration_ms = Date.now() - self.dictationStartedAt;
                var q = assessLocalQuality(self.textarea.value, {
                    confidence: self.lastSttMeta.confidence || 0,
                    durationMs: self.lastSttMeta.duration_ms,
                });
                self.lastSttMeta.local_quality = q;
            }
            var hasText = !!(self.textarea && String(self.textarea.value || '').trim());
            var needServer =
                !!self.sttConfig.server_enabled &&
                !!blob &&
                (!hasText ||
                    self._deviceSttFailed ||
                    (self.lastSttMeta &&
                        self.lastSttMeta.local_quality &&
                        !self.lastSttMeta.local_quality.ok));

            if (!blob && !hasText) {
                self.setStatus('No se capturó audio. Volvé a dictar.', 'warning');
                return;
            }

            // Al usuario: solo “listo”. STT servidor (si hace falta) es silencioso.
            self.setStatus('Listo.', 'success');
            if (needServer) {
                self.transcribeOnServer({ silent: true });
            }
        });
    };

    EncounterCaptureForm.prototype.buildAnalyzePayload = function (extra) {
        var idConfig = window.idConfiguracionActual || null;
        var formData = new FormData(this.form);
        var consulta = (this.textarea && this.textarea.value) ? this.textarea.value.trim() : '';
        var payload = {
            consulta: consulta,
            id_configuracion: idConfig || formData.get('id_configuracion') || null,
            id_persona: formData.get('id_persona'),
            id_consulta: formData.get('id_consulta'),
            parent: formData.get('parent'),
            parent_id: formData.get('parent_id'),
        };
        if (this.lastSttMeta) {
            payload.stt = Object.assign({}, this.lastSttMeta);
        }
        if (extra) {
            Object.assign(payload, extra);
        }
        return mergeApiPayload(payload);
    };

    EncounterCaptureForm.prototype.analyze = function () {
        var self = this;
        if (this.inReview) {
            return;
        }
        var consulta = (this.textarea && this.textarea.value) ? this.textarea.value.trim() : '';
        if (!consulta && !this.pendingAudioBlob) {
            this.setStatus('Escriba, dicte o grabe la consulta antes de analizar.', 'warning');
            return;
        }

        this.setStatus('Procesando…', 'primary');
        this.clearSaveAlert();
        this.analyzeBtn.disabled = true;
        var clientId = this.ensureClientCaptureId();
        var ctx = this.readFormContext();
        var needsServerStt =
            !consulta ||
            (this.lastSttMeta &&
                this.lastSttMeta.local_quality &&
                !this.lastSttMeta.local_quality.ok);

        var showAnalysis = function (payload) {
            self.lastAnalysisPayload = payload;
            self.draftText = consulta || (payload.texto_original || payload.transcript || '');
            self.editSnapshot = null;
            self.resolutionAccum = {};
            self.setEditingMode(false);
            if (self.responseEl) {
                self.responseEl.style.display = 'block';
            }
            var usedReview = self.renderCaptureReview(payload);
            if (!usedReview) {
                self.renderLegacyHtml(payload.html || '');
                if (self.reviewActions) {
                    self.reviewActions.style.display = '';
                }
                if (self.editBtn) {
                    self.editBtn.style.display = 'none';
                }
                if (self.discardBtn) {
                    self.discardBtn.style.display = 'none';
                }
                if (self.confirmBtn) {
                    self.confirmBtn.disabled = !!payload.system_error ||
                        !!(payload.capture_review && payload.capture_review.system_error);
                }
            } else {
                if (self.editBtn) {
                    self.editBtn.style.display = '';
                }
                if (self.discardBtn) {
                    self.discardBtn.style.display = '';
                }
                self.setCaptureMode(true);
            }
            self.setStatus('', 'muted');
        };

        var fail = function (msg) {
            self.setStatus(msg || 'No se pudo completar la captura.', 'danger');
        };

        var crearFd = new FormData();
        crearFd.set('client_capture_id', clientId);
        if (ctx.id_persona) crearFd.set('id_persona', String(ctx.id_persona));
        if (ctx.parent) crearFd.set('parent', String(ctx.parent));
        if (ctx.parent_id) crearFd.set('parent_id', String(ctx.parent_id));
        if (consulta) crearFd.set('consulta', consulta);
        if (needsServerStt) crearFd.set('stt_force_server', '1');
        if (this.lastSttMeta) {
            crearFd.set('stt', JSON.stringify(this.lastSttMeta));
        }
        if (typeof window.userPerTabConfig === 'object' && window.userPerTabConfig) {
            crearFd.set('userPerTabConfig', JSON.stringify(window.userPerTabConfig));
        }

        var uploadPromise = Promise.resolve();
        if (this.pendingAudioBlob) {
            uploadPromise = Promise.resolve(
                new File([this.pendingAudioBlob], 'encounter-capture.webm', {
                    type: this.pendingAudioBlob.type || 'audio/webm',
                })
            ).then(function (file) {
                crearFd.set('file', file);
            });
        }

        uploadPromise
            .then(function () {
                return fetch('/api/v1/clinical/encounter/captura/crear-o-subir', {
                    method: 'POST',
                    headers: self.apiHeadersMultipart(),
                    credentials: 'same-origin',
                    body: crearFd,
                });
            })
            .then(function (r) {
                return r.json().then(function (data) {
                    return { ok: r.ok, data: data };
                });
            })
            .then(function (res) {
                if (!res.ok || !res.data || !res.data.success) {
                    throw new Error(
                        (res.data && res.data.message) || 'Error al subir la captura.'
                    );
                }
                var capture = res.data.capture || {};
                self.applyCaptureResponse(capture);
                var stage = capture.stage || '';
                if (stage === 'UPLOADED' || stage === 'STT_FAILED') {
                    self.setStatus('Procesando…', 'primary');
                    return fetch('/api/v1/clinical/encounter/captura/transcribir', {
                        method: 'POST',
                        headers: self.apiHeadersJson(),
                        credentials: 'same-origin',
                        body: JSON.stringify(
                            mergeApiPayload({
                                client_capture_id: clientId,
                                capture_id: self.serverCaptureId,
                                force: stage === 'STT_FAILED',
                            })
                        ),
                    }).then(function (r) {
                        return r.json().then(function (data) {
                            if (!r.ok || !data.success) {
                                throw new Error(
                                    (data && data.message) || 'Error al transcribir.'
                                );
                            }
                            var c = data.capture || {};
                            self.applyCaptureResponse(c);
                            if (c.transcript && self.textarea) {
                                self.textarea.value = c.transcript;
                                consulta = c.transcript;
                            }
                            return c;
                        });
                    });
                }
                if (capture.transcript) {
                    consulta = capture.transcript;
                }
                return capture;
            })
            .then(function () {
                self.setStatus('Analizando consulta…', 'primary');
                return fetch('/api/v1/clinical/encounter/captura/analizar', {
                    method: 'POST',
                    headers: self.apiHeadersJson(),
                    credentials: 'same-origin',
                    body: JSON.stringify(
                        mergeApiPayload({
                            client_capture_id: clientId,
                            capture_id: self.serverCaptureId,
                            consulta: consulta || undefined,
                        })
                    ),
                });
            })
            .then(function (r) {
                return r.json().then(function (data) {
                    return { ok: r.ok, data: data };
                });
            })
            .then(function (res) {
                if (!res.ok || !res.data || !res.data.success) {
                    throw new Error(
                        (res.data && res.data.message) || 'No se pudo analizar la consulta.'
                    );
                }
                var capture = res.data.capture || {};
                var payload = self.applyCaptureResponse(capture, capture.analysis || capture);
                showAnalysis(payload);
            })
            .catch(function (err) {
                fail(err && err.message ? err.message : 'Error de conexión al analizar.');
            })
            .finally(function () {
                self.analyzeBtn.disabled = false;
            });
    };

    EncounterCaptureForm.prototype.transcribeOnServer = function (opts) {
        var self = this;
        opts = opts || {};
        var silent = !!opts.silent;
        if (!this.sttConfig.server_enabled) {
            return Promise.reject(new Error('server_stt_disabled'));
        }
        if (!this.pendingAudioBlob) {
            return Promise.reject(new Error('no_audio'));
        }
        if (!silent) {
            this.setStatus('Procesando…', 'primary');
        }
        return blobToBase64(this.pendingAudioBlob)
            .then(function (b64) {
                var payload = mergeApiPayload({
                    audio: b64,
                    stt: { force_server: true, provenance: 'device' },
                });
                return fetch('/api/v1/audio/transcribir', {
                    method: 'POST',
                    headers: window.BioenlaceApiClient.mergeHeaders({
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    }),
                    credentials: 'same-origin',
                    body: JSON.stringify(payload),
                });
            })
            .then(function (r) {
                return r.json();
            })
            .then(function (data) {
                if (!data.success || !data.texto_transcrito) {
                    if (!silent) {
                        self.setStatus('No se pudo procesar el audio. Podés escribir el texto.', 'warning');
                    }
                    return Promise.reject(new Error(data.error || 'stt_failed'));
                }
                self.textarea.value = data.texto_transcrito;
                self.lastSttMeta = {
                    provenance: 'server',
                    engine: data.modelo_usado || 'server',
                    confidence: data.confidence || 0.9,
                    duration_ms: self.lastSttMeta ? self.lastSttMeta.duration_ms : 0,
                    text: data.texto_transcrito,
                    force_server: true,
                };
                if (!silent) {
                    self.setStatus('Listo.', 'success');
                }
                return data;
            })
            .catch(function (err) {
                if (!silent) {
                    self.setStatus('No se pudo procesar el audio. Podés escribir el texto.', 'warning');
                }
                return Promise.reject(err);
            });
    };

    EncounterCaptureForm.prototype.resolveDatosExtraidos = function () {
        if (this.captureReview && window.EncounterCaptureReview && this.reviewRoot) {
            var staged = window.EncounterCaptureReview.collectStagedIds(this.reviewRoot);
            return window.EncounterCaptureReview.buildDatosExtraidos(this.captureReview, staged);
        }
        if (!this.lastAnalysisPayload || !this.lastAnalysisPayload.datos) {
            return null;
        }
        return (
            this.lastAnalysisPayload.datos.datosExtraidos || this.lastAnalysisPayload.datos
        );
    };

    EncounterCaptureForm.prototype.save = function () {
        var self = this;
        var datos = this.resolveDatosExtraidos();
        if (!this.lastAnalysisPayload || datos == null) {
            this.setStatus('Analice la consulta antes de confirmar.', 'warning');
            return;
        }
        if (
            (this.captureReview && this.captureReview.system_error) ||
            (this.lastAnalysisPayload.capture_review &&
                this.lastAnalysisPayload.capture_review.system_error)
        ) {
            this.setStatus('No se puede guardar: el análisis tiene errores de sistema.', 'warning');
            return;
        }
        if (
            this.captureReview &&
            window.EncounterCaptureReview &&
            !window.EncounterCaptureReview.canConfirm(
                this.captureReview,
                window.EncounterCaptureReview.collectStagedIds(this.reviewRoot),
                this.syncResolutionAccum()
            )
        ) {
            var stagedNow = window.EncounterCaptureReview.collectStagedIds(this.reviewRoot);
            if (
                window.EncounterCaptureReview.hasExtractedContent(this.captureReview) &&
                stagedNow.size === 0
            ) {
                this.setStatus(
                    'Seleccioná al menos un ítem del análisis antes de confirmar.',
                    'warning'
                );
            } else if (
                Array.isArray(this.captureReview.issues) &&
                this.captureReview.issues.length
            ) {
                this.setStatus(
                    'Completá los datos faltantes marcados en rojo antes de confirmar.',
                    'warning'
                );
                this.showSaveAlert(
                    'Completá los datos faltantes marcados en rojo antes de confirmar.',
                    'warning'
                );
            } else {
                this.setStatus('No se puede guardar la captura en este estado.', 'warning');
                this.showSaveAlert('No se puede guardar la captura en este estado.', 'warning');
            }
            return;
        }

        var formData = new FormData(this.form);
        var stagedIds = [];
        if (this.captureReview && window.EncounterCaptureReview && this.reviewRoot) {
            stagedIds = Array.from(
                window.EncounterCaptureReview.collectStagedIds(this.reviewRoot)
            );
        }

        var analisisBackup = null;
        // Con capture en servidor el pipeline recupera el análisis del draft; no reenviar copia.
        if (!this.serverCaptureId) {
            if (
                this.lastAnalysisPayload &&
                this.lastAnalysisPayload.datos &&
                this.lastAnalysisPayload.datos.datosExtraidos
            ) {
                analisisBackup = this.lastAnalysisPayload.datos.datosExtraidos;
            } else if (this.captureReview && window.EncounterCaptureReview) {
                analisisBackup = window.EncounterCaptureReview.buildFullAnalisisExtraidos(
                    this.captureReview
                );
            }
        }

        var openProblemResolutions = { condition_resolutions: {}, care_plan_resolutions: {} };
        if (this.captureReview && window.EncounterCaptureReview && this.reviewRoot) {
            openProblemResolutions = window.EncounterCaptureReview.collectOpenProblemResolutions(
                this.reviewRoot
            );
        }

        var resolutions = this.syncResolutionAccum();

        var payload = mergeApiPayload({
            client_capture_id: this.ensureClientCaptureId(),
            capture_id: this.serverCaptureId || undefined,
            datosExtraidos: datos,
            analisis_datos_extraidos: analisisBackup || undefined,
            staged_item_ids: stagedIds,
            resolutions: Object.keys(resolutions).length ? resolutions : undefined,
            analysis_cache_token:
                (this.lastAnalysisPayload && this.lastAnalysisPayload.analysis_cache_token) ||
                undefined,
            texto_original:
                this.lastAnalysisPayload.texto_original || this.draftText || this.textarea.value,
            texto_procesado:
                this.lastAnalysisPayload.texto_procesado ||
                (this.captureReview && this.captureReview.texto_procesado) ||
                this.draftText ||
                this.textarea.value,
            id_persona: formData.get('id_persona'),
            parent: formData.get('parent'),
            parent_id: formData.get('parent_id'),
            condition_resolutions: openProblemResolutions.condition_resolutions,
            care_plan_resolutions: openProblemResolutions.care_plan_resolutions,
            complete_acute: true,
        });

        this.confirmBtn.disabled = true;
        this.clearSaveAlert();
        this.setStatus('Guardando consulta…', 'primary');
        fetch('/api/v1/clinical/encounter/captura/guardar', {
            method: 'POST',
            headers: this.apiHeadersJson(),
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        })
            .then(function (r) {
                return r.json().then(function (data) {
                    return { httpOk: r.ok, status: r.status, data: data };
                });
            })
            .then(function (result) {
                var data = result.data || {};
                var ok = result.httpOk && data.success;
                var msg =
                    (data.guardar && data.guardar.message) ||
                    data.message ||
                    (data.capture && data.capture.last_error) ||
                    '';
                if (ok) {
                    self.clearSaveAlert();
                    var flashMsg = msg || 'Consulta guardada.';
                    try {
                        sessionStorage.setItem(
                            'bioenlace_home_flash',
                            JSON.stringify({ level: 'success', message: flashMsg })
                        );
                    } catch (e) {
                        /* ignore */
                    }
                    var urlInicio =
                        (self.form && self.form.getAttribute('data-url-inicio')) || '/site/index';
                    window.location.href = urlInicio;
                    return;
                } else {
                    self.showSaveAlert(msg || 'Error al guardar.', 'danger');
                    var detalle =
                        (data.guardar &&
                            data.guardar.errors &&
                            data.guardar.errors.datos_faltantes_detalle) ||
                        null;
                    if (
                        detalle &&
                        self.captureReview &&
                        window.EncounterCaptureReview &&
                        self.reviewRoot
                    ) {
                        var stagedKeep = window.EncounterCaptureReview.collectStagedIds(
                            self.reviewRoot
                        );
                        if (Array.isArray(detalle.issues) && detalle.issues.length) {
                            self.captureReview.issues = detalle.issues;
                        }
                        if (detalle.incomplete_items) {
                            self.captureReview.datos_faltantes_detalle = detalle;
                            self.captureReview.tiene_datos_faltantes = true;
                        }
                        self.captureReview.default_staged_item_ids = Array.from(stagedKeep);
                        var rendered = window.EncounterCaptureReview.render(self.captureReview, {});
                        self.reviewRoot.replaceChildren();
                        if (rendered && rendered.node) {
                            self.reviewRoot.appendChild(rendered.node);
                        }
                        window.EncounterCaptureReview.bindItemToggles(
                            self.reviewRoot,
                            self.updateConfirmState.bind(self)
                        );
                        window.EncounterCaptureReview.bindIssueResolutions(
                            self.reviewRoot,
                            self.updateConfirmState.bind(self)
                        );
                        window.EncounterCaptureReview.applyResolutionsToDom(
                            self.reviewRoot,
                            self.resolutionAccum || {}
                        );
                    }
                    self.updateConfirmState();
                }
            })
            .catch(function () {
                self.showSaveAlert('Error de conexión al guardar.', 'danger');
                self.updateConfirmState();
            });
    };

    EncounterCaptureForm.init = function (root) {
        if (!root) {
            return null;
        }
        var instance = new EncounterCaptureForm(root);
        instance.bind();
        return instance;
    };

    window.EncounterCaptureForm = {
        init: EncounterCaptureForm.init,
    };
})(window);
