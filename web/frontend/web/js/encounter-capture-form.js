/**
 * Captura clínica: dictado en dispositivo (Web Speech) + análisis encounter/analizar + fallback STT servidor.
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
        if (!consulta) {
            this.setStatus('Escriba o dicte la consulta antes de analizar.', 'warning');
            return;
        }

        this.setStatus('Analizando consulta…', 'primary');
        this.analyzeBtn.disabled = true;

        var send = function (payload) {
            return fetch('/api/v1/clinical/encounter/analizar', {
                method: 'POST',
                headers: window.BioenlaceApiClient.mergeHeaders({
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                }),
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            }).then(function (r) {
                return r.json().then(function (data) {
                    return { ok: r.ok, data: data, status: r.status };
                });
            });
        };

        var payload = this.buildAnalyzePayload();
        var maybeAudio = Promise.resolve(payload);

        if (
            this.lastSttMeta &&
            this.lastSttMeta.local_quality &&
            !this.lastSttMeta.local_quality.ok &&
            this.pendingAudioBlob
        ) {
            maybeAudio = blobToBase64(this.pendingAudioBlob).then(function (b64) {
                payload.audio = b64;
                return payload;
            });
        }

        maybeAudio
            .then(send)
            .then(function (res) {
                if (!res.ok || !res.data || !res.data.success) {
                    var msg =
                        (res.data && (res.data.message || res.data.error)) ||
                        'No se pudo analizar la consulta.';
                    self.setStatus(msg, 'danger');
                    return;
                }
                self.lastAnalysisPayload = res.data;
                self.draftText = consulta;
                if (self.responseEl) {
                    self.responseEl.style.display = 'block';
                }

                var usedReview = self.renderCaptureReview(res.data);
                if (!usedReview) {
                    self.renderLegacyHtml(res.data.html || '');
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
                            res.data.puede_confirmar === false || !!res.data.tiene_datos_faltantes;
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
            })
            .catch(function () {
                self.setStatus('Error de conexión al analizar.', 'danger');
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
        formData.set('datosExtraidos', JSON.stringify(datos));
        if (this.captureReview && window.EncounterCaptureReview) {
            formData.set(
                'analisis_datos_extraidos',
                JSON.stringify(
                    window.EncounterCaptureReview.buildFullAnalisisExtraidos(this.captureReview)
                )
            );
        } else if (
            this.lastAnalysisPayload &&
            this.lastAnalysisPayload.datos &&
            this.lastAnalysisPayload.datos.datosExtraidos
        ) {
            formData.set(
                'analisis_datos_extraidos',
                JSON.stringify(this.lastAnalysisPayload.datos.datosExtraidos)
            );
        }
        formData.set(
            'texto_original',
            this.lastAnalysisPayload.texto_original || this.draftText || this.textarea.value
        );
        formData.set(
            'texto_procesado',
            this.lastAnalysisPayload.texto_procesado ||
                (this.captureReview && this.captureReview.texto_procesado) ||
                this.draftText ||
                this.textarea.value
        );
        if (typeof window.appendPerTabToForm === 'function') {
            window.appendPerTabToForm(this.form);
        }

        this.confirmBtn.disabled = true;
        this.setStatus('Guardando consulta…', 'primary');
        fetch(this.form.action, {
            method: 'POST',
            headers: window.BioenlaceApiClient.mergeHeaders({
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            }),
            credentials: 'same-origin',
            body: formData,
        })
            .then(function (r) {
                return r.json();
            })
            .then(function (data) {
                if (data.success) {
                    self.setStatus(data.message || 'Consulta guardada.', 'success');
                    self.discardDraft();
                } else {
                    self.setStatus(data.message || 'Error al guardar.', 'danger');
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
