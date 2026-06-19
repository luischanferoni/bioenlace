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
        this.responseEl = formEl.querySelector('#agent-response');
        this.responseContent = formEl.querySelector('#response-content');
        this.confirmBtn = formEl.querySelector('#send-message');
        this.confirmSection = formEl.querySelector('#confirm-section');
        this.statusEl = formEl.querySelector('#encounter-stt-status');

        this.recognition = null;
        this.mediaRecorder = null;
        this.audioChunks = [];
        this.listening = false;
        this.dictationStartedAt = 0;
        this.lastSttMeta = null;
        this.pendingAudioBlob = null;
        this.lastAnalysisPayload = null;
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
                if (self.responseEl) {
                    self.responseEl.style.display = 'block';
                }
                if (self.responseContent) {
                    self.responseContent.innerHTML = res.data.html || '';
                }
                if (self.confirmSection) {
                    self.confirmSection.style.display = 'block';
                }
                if (self.confirmBtn) {
                    self.confirmBtn.disabled = !!res.data.tiene_datos_faltantes;
                }
                var prov = res.data.stt_provenance || 'text_only';
                self.setStatus(
                    'Análisis listo (transcripción: ' + prov + '). Revise y confirme.',
                    'success'
                );
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

    EncounterCaptureForm.prototype.save = function () {
        var self = this;
        if (!this.lastAnalysisPayload || !this.lastAnalysisPayload.datos) {
            this.setStatus('Analice la consulta antes de confirmar.', 'warning');
            return;
        }
        var formData = new FormData(this.form);
        var datos = this.lastAnalysisPayload.datos.datosExtraidos || this.lastAnalysisPayload.datos;
        formData.set('datosExtraidos', JSON.stringify(datos));
        formData.set('texto_original', this.lastAnalysisPayload.texto_original || this.textarea.value);
        formData.set('texto_procesado', this.lastAnalysisPayload.texto_procesado || this.textarea.value);
        if (typeof window.appendPerTabToForm === 'function') {
            window.appendPerTabToForm(this.form);
        }

        this.confirmBtn.disabled = true;
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
                } else {
                    self.setStatus(data.message || 'Error al guardar.', 'danger');
                    self.confirmBtn.disabled = false;
                }
            })
            .catch(function () {
                self.setStatus('Error de conexión al guardar.', 'danger');
                self.confirmBtn.disabled = false;
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
