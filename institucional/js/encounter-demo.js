/**
 * Demo animado — captura clínica en historia del paciente (formulario encounter).
 * Simula dictado por voz → analizar → confirmar. Alineado a encounter-capture-form.js.
 */
(function () {
    'use strict';

    var demo = document.getElementById('encounter-demo');
    if (!demo) {
        return;
    }

    var headerEl = document.getElementById('encounter-demo-header');
    var patientNameEl = document.getElementById('encounter-demo-patient-name');
    var patientMetaEl = document.getElementById('encounter-demo-patient-meta');
    var contextBadgeEl = document.getElementById('encounter-demo-context-badge');
    var stageEl = document.getElementById('encounter-demo-stage');
    var tabs = demo.querySelectorAll('.assistant-demo__tab');
    var motionToggle = demo.querySelector('[data-demo-motion-toggle]');
    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var SPEED = 1.5;
    var ROTATE_MS = reducedMotion ? Math.round(14000 * SPEED) : Math.round(24000 * SPEED);
    var activeFlow = 'consulta';
    var runToken = 0;
    var rotateTimer = null;
    var paused = false;
    var userPaused = false;
    var started = false;

    var FLOW_ORDER = ['consulta', 'evolucion', 'guardia'];

    var TEXTAREA_PLACEHOLDER = 'Escriba o dicte los detalles de la consulta. La IA verificará motivos, evolución, diagnóstico, prácticas, etc.';

    var CAPTURE_TEXT = 'Paciente refiere cefalea holocraneana de 3 días, intensidad moderada, sin fiebre ni vómitos. '
        + 'Al examen: normotensa, fondo de ojo sin papiledema. Impresión: cefalea tensional. '
        + 'Plan: analgesia, hidratación y control en 48 h si persiste.';

    var EVOLUCION_TEXT = 'Control: cefalea en mejoría con analgesia. Sin signos de alarma. Continúa plan habitual.';

    var GUARDIA_CAPTURE = 'Dolor torácico opresivo de 40 minutos, sudoración asociada. TA 150/95. '
        + 'Solicito ECG y troponinas. Inicio AAS y monitorización.';

    var FLOWS = {
        consulta: {
            patientName: 'Ana Lorem',
            patientMeta: '42 años',
            contextBadge: 'Turno ambulatorio',
            formLabel: 'Formulario de consulta',
            analyzeLabel: 'Analizar consulta',
            confirmLabel: 'Confirmar consulta',
            contextAlert: {
                variant: 'info',
                title: 'Preconsulta completada por el paciente',
                body: 'Desde la app: cefalea de varios días, sin fiebre. Hipertensión en tratamiento.'
            },
            captureText: CAPTURE_TEXT,
            analysisFields: [
                { label: 'Motivo de consulta', value: 'Cefalea holocraneana 3 días' },
                { label: 'Impresión diagnóstica', value: 'Cefalea tensional' },
                { label: 'Plan', value: 'Analgesia, hidratación, control 48 h' }
            ],
            successText: 'Consulta guardada en la historia clínica del paciente.'
        },
        evolucion: {
            patientName: 'Ana Lorem',
            patientMeta: '42 años · encounter en curso',
            contextBadge: 'Evolución',
            formLabel: 'Formulario de evolución',
            analyzeLabel: 'Analizar evolución',
            confirmLabel: 'Confirmar evolución',
            contextAlert: null,
            captureText: EVOLUCION_TEXT,
            analysisFields: [
                { label: 'Evolución', value: 'Cefalea en mejoría, sin alarmas' },
                { label: 'Plan', value: 'Continúa tratamiento y control' }
            ],
            successText: 'Evolución registrada correctamente.'
        },
        guardia: {
            patientName: 'Carlos Ipsum',
            patientMeta: '58 años',
            contextBadge: 'Guardia',
            formLabel: 'Formulario de guardia',
            analyzeLabel: 'Analizar nota de guardia',
            confirmLabel: 'Confirmar nota de guardia',
            contextAlert: {
                variant: 'triage',
                title: 'Triage amarillo · ingreso hace 25 min',
                body: 'Motivo: dolor torácico opresivo. Pendiente ECG. Sin alergias conocidas en el resumen.'
            },
            captureText: GUARDIA_CAPTURE,
            analysisFields: [
                { label: 'Motivo', value: 'Dolor torácico opresivo' },
                { label: 'Conducta', value: 'ECG, troponinas, AAS, monitorización' }
            ],
            successText: 'Nota de guardia guardada en el encounter.'
        }
    };

    function wait(ms) {
        if (reducedMotion) {
            return Promise.resolve();
        }
        return new Promise(function (resolve) {
            setTimeout(resolve, Math.round(ms * SPEED));
        });
    }

    function scrollStage() {
        if (stageEl) {
            stageEl.scrollTop = stageEl.scrollHeight;
        }
    }

    function setActiveTab(flowId) {
        tabs.forEach(function (tab) {
            var isActive = tab.getAttribute('data-flow') === flowId;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function showHeader(flow) {
        if (!headerEl) {
            return;
        }
        if (patientNameEl) {
            patientNameEl.textContent = flow.patientName;
        }
        if (patientMetaEl) {
            patientMetaEl.textContent = flow.patientMeta;
        }
        if (contextBadgeEl) {
            contextBadgeEl.textContent = flow.contextBadge;
        }
        headerEl.setAttribute('aria-hidden', 'false');
        headerEl.classList.add('is-visible');
    }

    function buildContextAlert(alert) {
        if (!alert) {
            return '';
        }
        var variantClass = alert.variant === 'triage' ? ' encounter-demo__alert--triage' : '';
        return '<div class="encounter-demo__alert' + variantClass + '">'
            + '<strong>' + escapeHtml(alert.title) + '</strong>'
            + '<span>' + escapeHtml(alert.body) + '</span>'
            + '</div>';
    }

    function buildFormShell(flow) {
        return buildContextAlert(flow.contextAlert)
            + '<div class="encounter-demo__form-block" data-part="capture">'
            + '<label class="encounter-demo__label">' + escapeHtml(flow.formLabel) + '</label>'
            + '<textarea class="encounter-demo__textarea" rows="4" readonly placeholder="' + escapeHtml(TEXTAREA_PLACEHOLDER) + '"></textarea>'
            + '<div class="encounter-demo__tools">'
            + '<button type="button" class="encounter-demo__tool-btn encounter-demo__tool-btn--dictate" data-part="dictate-btn" tabindex="-1">'
            + '<i class="fas fa-microphone" aria-hidden="true"></i> Dictar'
            + '</button>'
            + '<button type="button" class="encounter-demo__tool-btn" tabindex="-1">'
            + '<i class="fas fa-cloud-arrow-up" aria-hidden="true"></i> Transcribir en servidor'
            + '</button>'
            + '</div>'
            + '<div class="encounter-demo__waveform" data-part="waveform" aria-hidden="true">'
            + '<span></span><span></span><span></span><span></span><span></span>'
            + '</div>'
            + '<div class="encounter-demo__stt-status" data-part="stt-status" aria-live="polite"></div>'
            + '</div>'
            + '<div class="encounter-demo__actions" data-part="analyze-wrap">'
            + '<button type="button" class="encounter-demo__btn encounter-demo__btn--analyze" data-part="analyze-btn" disabled>'
            + escapeHtml(flow.analyzeLabel || 'Analizar')
            + '</button>'
            + '</div>'
            + '<div class="encounter-demo__analysis" data-part="analysis"></div>'
            + '<div class="encounter-demo__actions" data-part="confirm-wrap" style="display:none">'
            + '<button type="button" class="encounter-demo__btn encounter-demo__btn--confirm" data-part="confirm-btn" disabled>'
            + escapeHtml(flow.confirmLabel || 'Confirmar')
            + '</button>'
            + '</div>'
            + '<div class="encounter-demo__success" data-part="success"></div>';
    }

    function buildAnalysisHtml(fields) {
        var html = '<p class="encounter-demo__analysis-title">Borrador estructurado — revisá antes de guardar</p>'
            + '<div class="encounter-demo__fields">';
        fields.forEach(function (field) {
            html += '<div class="encounter-demo__field">'
                + '<label>' + escapeHtml(field.label) + '</label>'
                + '<input type="text" readonly value="' + escapeHtml(field.value) + '">'
                + '</div>';
        });
        html += '</div>';
        return html;
    }

    function reveal(el) {
        if (!el) {
            return;
        }
        requestAnimationFrame(function () {
            el.classList.add('is-visible');
        });
    }

    function highlightBtn(btn) {
        if (!btn) {
            return Promise.resolve();
        }
        btn.disabled = false;
        btn.classList.add('is-highlight');
        return wait(reducedMotion ? 300 : 700).then(function () {
            btn.classList.remove('is-highlight');
        });
    }

    function setSttStatus(statusEl, message, state) {
        if (!statusEl) {
            return;
        }
        statusEl.textContent = message;
        statusEl.classList.remove('is-listening', 'is-ready');
        if (state) {
            statusEl.classList.add(state);
        }
    }

    function chunkWords(text) {
        var words = text.split(/\s+/).filter(Boolean);
        var chunks = [];
        var i = 0;
        while (i < words.length) {
            var size = 2 + (i % 3);
            chunks.push(words.slice(i, i + size).join(' '));
            i += size;
        }
        return chunks;
    }

    async function simulateDictation(textarea, dictateBtn, statusEl, waveformEl, fullText, token) {
        if (!textarea || token !== runToken) {
            return;
        }

        await highlightBtn(dictateBtn);
        if (token !== runToken) {
            return;
        }

        demo.classList.add('is-dictating');
        if (dictateBtn) {
            dictateBtn.classList.add('is-recording');
            dictateBtn.innerHTML = '<i class="fas fa-stop" aria-hidden="true"></i> Detener';
        }
        if (waveformEl) {
            waveformEl.classList.add('is-active');
        }
        setSttStatus(statusEl, 'Escuchando… Haga clic de nuevo para detener.', 'is-listening');
        textarea.value = '';
        scrollStage();

        var chunks = chunkWords(fullText);
        for (var c = 0; c < chunks.length; c++) {
            if (token !== runToken) {
                return;
            }
            textarea.value = textarea.value + (textarea.value ? ' ' : '') + chunks[c];
            scrollStage();
            await wait(reducedMotion ? 0 : 180 + (c % 3) * 40);
        }

        if (token !== runToken) {
            return;
        }

        demo.classList.remove('is-dictating');
        if (dictateBtn) {
            dictateBtn.classList.remove('is-recording');
            dictateBtn.innerHTML = '<i class="fas fa-microphone" aria-hidden="true"></i> Dictar';
        }
        if (waveformEl) {
            waveformEl.classList.remove('is-active');
        }
        setSttStatus(statusEl, 'Dictado listo. Revise el texto y pulse Analizar.', 'is-ready');
        await wait(reducedMotion ? 150 : 400);
    }

    async function animateFlow(flow, token) {
        if (!stageEl || token !== runToken) {
            return;
        }

        stageEl.innerHTML = buildFormShell(flow);
        showHeader(flow);
        scrollStage();

        var alertEl = stageEl.querySelector('.encounter-demo__alert');
        var captureBlock = stageEl.querySelector('[data-part="capture"]');
        var textarea = stageEl.querySelector('.encounter-demo__textarea');
        var dictateBtn = stageEl.querySelector('[data-part="dictate-btn"]');
        var statusEl = stageEl.querySelector('[data-part="stt-status"]');
        var waveformEl = stageEl.querySelector('[data-part="waveform"]');
        var analyzeWrap = stageEl.querySelector('[data-part="analyze-wrap"]');
        var analyzeBtn = stageEl.querySelector('[data-part="analyze-btn"]');
        var analysisEl = stageEl.querySelector('[data-part="analysis"]');
        var confirmWrap = stageEl.querySelector('[data-part="confirm-wrap"]');
        var confirmBtn = stageEl.querySelector('[data-part="confirm-btn"]');
        var successEl = stageEl.querySelector('[data-part="success"]');

        if (alertEl) {
            reveal(alertEl);
            await wait(reducedMotion ? 200 : 500);
        }
        if (token !== runToken) {
            return;
        }

        if (captureBlock) {
            reveal(captureBlock);
            await wait(reducedMotion ? 150 : 400);
        }
        if (token !== runToken) {
            return;
        }

        await simulateDictation(textarea, dictateBtn, statusEl, waveformEl, flow.captureText, token);
        if (token !== runToken) {
            return;
        }

        if (analyzeWrap) {
            analyzeWrap.classList.add('is-visible');
        }
        if (analyzeBtn) {
            analyzeBtn.disabled = false;
        }
        scrollStage();
        await highlightBtn(analyzeBtn);
        if (token !== runToken) {
            return;
        }

        if (analyzeBtn) {
            analyzeBtn.innerHTML = '<span class="encounter-demo__spinner" aria-hidden="true"></span>Analizando…';
            analyzeBtn.disabled = true;
        }
        setSttStatus(
            statusEl,
            (flow.analyzeLabel || 'Analizar').replace(/^Analizar/, 'Analizando') + '…',
            'is-listening'
        );
        await wait(reducedMotion ? 200 : 900);
        if (token !== runToken) {
            return;
        }

        if (analyzeBtn) {
            analyzeBtn.textContent = flow.analyzeLabel || 'Analizar';
            analyzeBtn.disabled = true;
        }
        setSttStatus(statusEl, '', '');
        if (analysisEl) {
            analysisEl.innerHTML = buildAnalysisHtml(flow.analysisFields);
            reveal(analysisEl);
        }
        if (confirmWrap) {
            confirmWrap.style.display = '';
            confirmWrap.classList.add('is-visible');
        }
        if (confirmBtn) {
            confirmBtn.disabled = false;
        }
        scrollStage();
        await wait(reducedMotion ? 200 : 450);
        if (token !== runToken) {
            return;
        }

        await highlightBtn(confirmBtn);
        if (token !== runToken) {
            return;
        }

        if (successEl) {
            successEl.textContent = flow.successText;
            reveal(successEl);
        }
        scrollStage();
    }

    function runFlowInstant(flow) {
        if (!stageEl) {
            return;
        }
        stageEl.innerHTML = buildFormShell(flow);
        showHeader(flow);

        var alertEl = stageEl.querySelector('.encounter-demo__alert');
        var captureBlock = stageEl.querySelector('[data-part="capture"]');
        var textarea = stageEl.querySelector('.encounter-demo__textarea');
        var statusEl = stageEl.querySelector('[data-part="stt-status"]');
        var analyzeWrap = stageEl.querySelector('[data-part="analyze-wrap"]');
        var analysisEl = stageEl.querySelector('[data-part="analysis"]');
        var confirmWrap = stageEl.querySelector('[data-part="confirm-wrap"]');
        var confirmBtn = stageEl.querySelector('[data-part="confirm-btn"]');
        var successEl = stageEl.querySelector('[data-part="success"]');

        if (alertEl) {
            alertEl.classList.add('is-visible');
        }
        if (captureBlock) {
            captureBlock.classList.add('is-visible');
        }
        if (textarea) {
            textarea.value = flow.captureText;
        }
        setSttStatus(statusEl, 'Dictado listo. Revise el texto y pulse Analizar.', 'is-ready');
        if (analyzeWrap) {
            analyzeWrap.classList.add('is-visible');
        }
        if (analysisEl) {
            analysisEl.innerHTML = buildAnalysisHtml(flow.analysisFields);
            analysisEl.classList.add('is-visible');
        }
        if (confirmWrap) {
            confirmWrap.style.display = '';
            confirmWrap.classList.add('is-visible');
        }
        if (confirmBtn) {
            confirmBtn.disabled = false;
        }
        if (successEl) {
            successEl.textContent = flow.successText;
            successEl.classList.add('is-visible');
        }
        scrollStage();
    }

    async function runFlow(flowId) {
        runToken++;
        var token = runToken;
        var flow = FLOWS[flowId];
        if (!flow) {
            return;
        }

        activeFlow = flowId;
        setActiveTab(flowId);

        if (headerEl) {
            headerEl.classList.remove('is-visible');
            headerEl.setAttribute('aria-hidden', 'true');
        }
        if (stageEl) {
            stageEl.innerHTML = '';
        }
        demo.classList.remove('is-dictating');

        if (reducedMotion) {
            runFlowInstant(flow);
            scheduleRotate();
            return;
        }

        await wait(400);
        if (token !== runToken) {
            return;
        }

        await animateFlow(flow, token);

        if (token === runToken) {
            scheduleRotate();
        }
    }

    function nextFlowId() {
        var idx = FLOW_ORDER.indexOf(activeFlow);
        return FLOW_ORDER[idx >= 0 ? (idx + 1) % FLOW_ORDER.length : 0];
    }

    function scheduleRotate() {
        clearRotate();
        if (paused) {
            return;
        }
        rotateTimer = setTimeout(function () {
            runFlow(nextFlowId());
        }, ROTATE_MS);
    }

    function clearRotate() {
        if (rotateTimer) {
            clearTimeout(rotateTimer);
            rotateTimer = null;
        }
    }

    function pauseDemo() {
        paused = true;
        clearRotate();
    }

    function resumeDemo() {
        if (!paused || userPaused) {
            return;
        }
        paused = false;
        scheduleRotate();
    }

    function renderMotionToggle() {
        if (!motionToggle) {
            return;
        }
        motionToggle.setAttribute('aria-pressed', userPaused ? 'true' : 'false');
        motionToggle.innerHTML = userPaused
            ? '<i class="fas fa-play" aria-hidden="true"></i> Reanudar animación'
            : '<i class="fas fa-pause" aria-hidden="true"></i> Pausar animación';
    }

    if (motionToggle) {
        motionToggle.addEventListener('click', function () {
            userPaused = !userPaused;
            if (userPaused) {
                pauseDemo();
            } else {
                paused = false;
                scheduleRotate();
            }
            renderMotionToggle();
        });
        renderMotionToggle();
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            pauseDemo();
            runFlow(tab.getAttribute('data-flow'));
        });
    });

    demo.addEventListener('mouseenter', pauseDemo);
    demo.addEventListener('mouseleave', resumeDemo);
    demo.addEventListener('focusin', pauseDemo);
    demo.addEventListener('focusout', function (e) {
        if (!demo.contains(e.relatedTarget)) {
            resumeDemo();
        }
    });

    function startWhenVisible() {
        if (started) {
            return;
        }
        started = true;
        runFlow('consulta');
    }

    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    startWhenVisible();
                    observer.disconnect();
                }
            });
        }, { threshold: 0.2 });
        observer.observe(demo);
    } else {
        startWhenVisible();
    }
})();
