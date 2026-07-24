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
        this.initialTextOnListen = '';
        this.audioOnlyRecording = false;
        this.clientCaptureId = null;
        this.serverCaptureId = null;
        this.serverStage = null;

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

    EncounterCaptureForm.prototype.bind = function () {
        var self = this;
        this.statusEl = this.form.querySelector('#encounter-stt-status');
        var micBtn = this.form.querySelector('#encounter-dictate-btn');
        var serverBtn = this.form.querySelector('#encounter-stt-server-btn');

        if (micBtn) {
            micBtn.addEventListener('click', function () {
                self.toggleDictation();
            });
        }
        if (serverBtn) {
            serverBtn.addEventListener('click', function () {
                self.transcribeOnServer();
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
                var item = data.items[0];
                self.clientCaptureId = item.client_capture_id || self.clientCaptureId;
                self.serverCaptureId = item.id || null;
                self.serverStage = item.stage || null;
                var hint = 'Hay una captura pendiente (' + (item.stage || '') + ').';
                if (item.stage === 'READY_FOR_REVIEW' || item.stage === 'SAVE_FAILED') {
                    hint += ' Reanalizá o continuá desde el texto.';
                } else if (item.stage === 'UPLOADED' || item.stage === 'STT_FAILED') {
                    hint += ' Reintentá analizar para continuar la transcripción.';
                } else if (item.transcript) {
                    hint += ' Texto recuperado del servidor.';
                    if (self.textarea && !self.textarea.value.trim()) {
                        self.textarea.value = item.transcript;
                    }
                }
                self.setStatus(hint, 'info');
            })
            .catch(function () {
                /* sin red: ok */
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

    EncounterCaptureForm.prototype.updateConfirmState = function () {
        if (!this.confirmBtn) {
            return;
        }
        if (this.lastAnalysisPayload && this.lastAnalysisPayload.puede_confirmar === false) {
            this.confirmBtn.disabled = true;
            return;
        }
        if (!window.EncounterCaptureReview) {
            return;
        }
        var staged = window.EncounterCaptureReview.collectStagedIds(this.reviewRoot);
        var can =
            this.captureReview &&
            window.EncounterCaptureReview.canConfirm(this.captureReview, staged);
        this.confirmBtn.disabled = !can;
    };

    EncounterCaptureForm.prototype.renderCaptureReview = function (data) {
        var review = data.capture_review;
        if (!review || !window.EncounterCaptureReview || !this.reviewRoot) {
            return false;
        }

        this.captureReview = review;
        var rendered = window.EncounterCaptureReview.render(review, {
            textoFormateado: data.texto_formateado || null,
        });
        this.reviewRoot.innerHTML = rendered.html;
        window.EncounterCaptureReview.bindItemToggles(
            this.reviewRoot,
            this.updateConfirmState.bind(this)
        );

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

    EncounterCaptureForm.prototype.editDraft = function () {
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
        this.setStatus('Editá el texto y volvé a analizar.', 'info');
    };

    EncounterCaptureForm.prototype.applySttUiPolicy = function () {
        var micBtn = this.form.querySelector('#encounter-dictate-btn');
        var serverBtn = this.form.querySelector('#encounter-stt-server-btn');
        var deviceOn = !!this.sttConfig.device_enabled;
        var serverOn = !!this.sttConfig.server_enabled;

        if (micBtn) {
            if (!deviceOn && serverOn) {
                micBtn.disabled = false;
                micBtn.title = 'Grabar audio para transcribir en servidor';
            } else if (!deviceOn) {
                micBtn.disabled = true;
            } else if (!this.recognition) {
                micBtn.disabled = true;
            }
        }
        if (serverBtn) {
            serverBtn.style.display = serverOn ? '' : 'none';
            serverBtn.disabled = !serverOn;
        }
        if (!deviceOn && !serverOn) {
            this.setStatus('Dictado por voz deshabilitado. Escriba el texto manualmente.', 'warning');
        } else if (!deviceOn && serverOn && !this.recognition) {
            this.setStatus('Use el micrófono para grabar y «Transcribir en servidor».', 'muted');
        } else if (deviceOn && !this.recognition) {
            this.setStatus(
                'Dictado del navegador no disponible. Escriba el texto o use «Transcribir en servidor».',
                'warning'
            );
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

        this.recognition.onerror = function () {
            self.stopDictation();
            self.setStatus('Error en el dictado. Intente de nuevo o use transcripción en servidor.', 'danger');
        };

        this.recognition.onend = function () {
            if (self.listening) {
                try {
                    self.recognition.start();
                } catch (e) {
                    self.stopDictation();
                }
            }
        };
    };

    EncounterCaptureForm.prototype.startAudioCapture = function () {
        var self = this;
        if (!navigator.mediaDevices || !window.MediaRecorder) {
            return;
        }
        this.audioChunks = [];
        navigator.mediaDevices
            .getUserMedia({ audio: true })
            .then(function (stream) {
                self.mediaRecorder = new MediaRecorder(stream);
                self.mediaRecorder.ondataavailable = function (e) {
                    if (e.data && e.data.size > 0) {
                        self.audioChunks.push(e.data);
                    }
                };
                self.mediaRecorder.onstop = function () {
                    stream.getTracks().forEach(function (t) {
                        t.stop();
                    });
                    if (self.audioChunks.length) {
                        self.pendingAudioBlob = new Blob(self.audioChunks, { type: 'audio/webm' });
                    }
                };
                self.mediaRecorder.start();
            })
            .catch(function () {
                /* sin audio de respaldo */
            });
    };

    EncounterCaptureForm.prototype.stopAudioCapture = function () {
        if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
            this.mediaRecorder.stop();
        }
        this.mediaRecorder = null;
    };

    EncounterCaptureForm.prototype.toggleDictation = function () {
        if (this.listening || this.audioOnlyRecording) {
            this.stopDictation();
            return;
        }
        if (!this.sttConfig.device_enabled && this.sttConfig.server_enabled) {
            this.toggleAudioOnlyRecording();
            return;
        }
        if (!this.recognition) {
            return;
        }
        this.initialTextOnListen = this.textarea.value || '';
        this.dictationStartedAt = Date.now();
        this.pendingAudioBlob = null;
        this.startAudioCapture();
        try {
            this.recognition.start();
            this.listening = true;
            this.setStatus('Escuchando… Haga clic de nuevo para detener.', 'primary');
        } catch (e) {
            this.setStatus('No se pudo iniciar el dictado.', 'danger');
        }
    };

    EncounterCaptureForm.prototype.toggleAudioOnlyRecording = function () {
        var self = this;
        if (this.audioOnlyRecording) {
            this.stopAudioOnlyRecording();
            return;
        }
        this.pendingAudioBlob = null;
        this.dictationStartedAt = Date.now();
        this.startAudioCapture();
        this.audioOnlyRecording = true;
        this.setStatus('Grabando audio… pulse de nuevo para detener y transcribir en servidor.', 'primary');
    };

    EncounterCaptureForm.prototype.stopAudioOnlyRecording = function () {
        var self = this;
        this.audioOnlyRecording = false;
        this.stopAudioCapture();
        if (!this.pendingAudioBlob) {
            this.setStatus('No se capturó audio.', 'warning');
            return;
        }
        this.setStatus('Audio grabado. Pulse «Transcribir en servidor».', 'success');
    };

    EncounterCaptureForm.prototype.stopDictation = function () {
        if (this.audioOnlyRecording) {
            this.stopAudioOnlyRecording();
            return;
        }
        this.listening = false;
        if (this.recognition) {
            try {
                this.recognition.stop();
            } catch (e) {
                /* ignore */
            }
        }
        this.stopAudioCapture();
        if (this.lastSttMeta) {
            this.lastSttMeta.duration_ms = Date.now() - this.dictationStartedAt;
            var q = assessLocalQuality(this.textarea.value, {
                confidence: this.lastSttMeta.confidence || 0,
                durationMs: this.lastSttMeta.duration_ms,
            });
            this.lastSttMeta.local_quality = q;
            if (!q.ok) {
                this.setStatus(
                    'Transcripción preliminar con baja calidad. Use «Transcribir en servidor» si hace falta.',
                    'warning'
                );
            } else {
                this.setStatus('Dictado listo. Revise el texto y pulse Analizar.', 'success');
            }
        }
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

        this.setStatus('Subiendo / procesando captura…', 'primary');
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
                    self.confirmBtn.disabled =
                        payload.puede_confirmar === false || !!payload.tiene_datos_faltantes;
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
            self.setStatus('Análisis listo. Revise y confirme el guardado.', 'success');
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
                    self.setStatus('Transcribiendo en servidor…', 'primary');
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
                var payload = self.applyCaptureResponse(capture, capture.analysis || res.data);
                showAnalysis(payload);
            })
            .catch(function (err) {
                fail(err && err.message ? err.message : 'Error de conexión al analizar.');
            })
            .finally(function () {
                self.analyzeBtn.disabled = false;
            });
    };

    EncounterCaptureForm.prototype.transcribeOnServer = function () {
        var self = this;
        if (!this.sttConfig.server_enabled) {
            this.setStatus('Transcripción en servidor deshabilitada por configuración.', 'warning');
            return;
        }
        if (!this.pendingAudioBlob) {
            this.setStatus('Grabe con el micrófono primero para tener audio de respaldo.', 'warning');
            return;
        }
        this.setStatus('Transcribiendo en servidor…', 'primary');
        blobToBase64(this.pendingAudioBlob).then(function (b64) {
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
                    self.setStatus(data.error || 'No se pudo transcribir en servidor.', 'danger');
                    return;
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
                self.setStatus('Transcripción de servidor aplicada. Revise y analice.', 'success');
            })
            .catch(function () {
                self.setStatus('Error al transcribir en servidor.', 'danger');
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
            this.lastAnalysisPayload &&
            this.lastAnalysisPayload.puede_confirmar === false
        ) {
            this.setStatus('No se puede guardar: el análisis tiene errores.', 'warning');
            return;
        }
        if (
            (this.lastAnalysisPayload && this.lastAnalysisPayload.tiene_datos_faltantes) ||
            (this.captureReview && this.captureReview.tiene_datos_faltantes)
        ) {
            var msgFaltantes = '';
            var detalle =
                (this.captureReview && this.captureReview.datos_faltantes_detalle) ||
                (this.lastAnalysisPayload && this.lastAnalysisPayload.datos_faltantes_detalle) ||
                (this.lastAnalysisPayload &&
                    this.lastAnalysisPayload.capture_review &&
                    this.lastAnalysisPayload.capture_review.datos_faltantes_detalle);
            if (detalle && detalle.message) {
                msgFaltantes = String(detalle.message).trim();
            }
            this.setStatus(
                msgFaltantes ||
                    'Faltan categorías o campos obligatorios. Completá el texto y volvé a analizar.',
                'warning'
            );
            return;
        }
        if (
            this.captureReview &&
            window.EncounterCaptureReview &&
            !window.EncounterCaptureReview.canConfirm(
                this.captureReview,
                window.EncounterCaptureReview.collectStagedIds(this.reviewRoot)
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
            } else {
                this.setStatus('Faltan datos obligatorios en el análisis.', 'warning');
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

        var payload = mergeApiPayload({
            client_capture_id: this.ensureClientCaptureId(),
            capture_id: this.serverCaptureId || undefined,
            datosExtraidos: datos,
            analisis_datos_extraidos: analisisBackup || undefined,
            staged_item_ids: stagedIds,
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
        });

        this.confirmBtn.disabled = true;
        this.setStatus('Guardando consulta…', 'primary');
        fetch('/api/v1/clinical/encounter/captura/guardar', {
            method: 'POST',
            headers: this.apiHeadersJson(),
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        })
            .then(function (r) {
                return r.json();
            })
            .then(function (data) {
                var ok = data && data.success;
                var msg =
                    (data && data.guardar && data.guardar.message) ||
                    (data && data.message) ||
                    '';
                if (ok) {
                    self.clientCaptureId = null;
                    self.serverCaptureId = null;
                    self.serverStage = null;
                    self.lastAnalysisPayload = null;
                    self.captureReview = null;
                    self.draftText = '';
                    if (self.reviewRoot) {
                        self.reviewRoot.innerHTML = '';
                    }
                    if (self.responseContent) {
                        self.responseContent.innerHTML = '';
                        self.responseContent.classList.add('d-none');
                    }
                    if (self.responseEl) {
                        self.responseEl.style.display = 'none';
                    }
                    if (self.textarea) {
                        self.textarea.value = '';
                    }
                    self.setCaptureMode(false);
                    self.setStatus(msg || 'Consulta guardada.', 'success');
                } else {
                    self.setStatus(msg || 'Error al guardar.', 'danger');
                    self.updateConfirmState();
                }
            })
            .catch(function () {
                self.setStatus('Error de conexión al guardar.', 'danger');
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
