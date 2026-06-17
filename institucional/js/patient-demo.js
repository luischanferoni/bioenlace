/**
 * Demo animado del asistente paciente (sitio institucional).
 * Mock estático — sin API.
 */

(function () {
    'use strict';

    var demo = document.getElementById('patient-demo');
    if (!demo) {
        return;
    }

    var messagesEl = demo.querySelector('.assistant-demo__messages');
    var emptyHintEl = document.getElementById('patient-demo-empty-hint');
    var composerInput = demo.querySelector('.assistant-demo__composer-input');
    var queryInputWrap = demo.querySelector('.spa-query-input');
    var tabs = demo.querySelectorAll('.assistant-demo__tab');
    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var COMPOSER_PLACEHOLDER = 'Escribí en lenguaje simple. Ejemplo: “Tengo dolor de cabeza” o “Necesito un turno”.';
    var SPEED = 1.5;
    var ROTATE_MS = reducedMotion ? Math.round(14000 * SPEED) : Math.round(22000 * SPEED);

    var activeFlow = 'asistencia';
    var runToken = 0;
    var rotateTimer = null;
    var paused = false;
    var started = false;

    var FLOW_ORDER = ['asistencia', 'turno', 'avisos'];

    var FLOWS = {
        asistencia: {
            userText: 'Tengo fiebre y dolor de cabeza desde ayer',
            botText: 'Retomo lo que tenés en tu historia. ¿La fiebre supera los 38 °C o apareció de golpe?',
            flowTitle: 'Asistencia con síntomas',
            cardHtml: buildAsistenciaCard()
        },
        turno: {
            userText: 'Quiero un turno con cardiología la semana que viene',
            botText: 'Estos son los turnos disponibles en tu centro. Elegí modalidad y horario.',
            flowTitle: 'Reservar turno',
            cardHtml: buildTurnoCard()
        },
        avisos: {
            userText: '¿Cuándo es mi próximo turno?',
            botText: 'Tenés un turno confirmado y un recordatorio de control pendiente.',
            flowTitle: 'Seguimiento y avisos',
            cardHtml: buildAvisosCard()
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

    function scrollMessages() {
        if (messagesEl) {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }
    }

    function setActiveTab(flowId) {
        tabs.forEach(function (tab) {
            var isActive = tab.getAttribute('data-flow') === flowId;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
    }

    function setEmptyHintVisible(visible) {
        if (emptyHintEl) {
            emptyHintEl.classList.toggle('is-hidden', !visible);
        }
    }

    function clearMessages() {
        if (messagesEl) {
            var hint = emptyHintEl;
            messagesEl.innerHTML = '';
            if (hint) {
                messagesEl.appendChild(hint);
            }
        }
        setEmptyHintVisible(false);
        if (composerInput) {
            composerInput.value = '';
            composerInput.placeholder = COMPOSER_PLACEHOLDER;
        }
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function appendUserBubble(text) {
        setEmptyHintVisible(false);
        var row = document.createElement('div');
        row.className = 'assistant-demo__row assistant-demo__row--user';
        row.innerHTML = '<div class="spa-chat-bubble spa-chat-bubble--user">'
            + '<p class="spa-chat-bubble-text spa-chat-bubble-text--user mb-0">' + escapeHtml(text) + '</p>'
            + '</div>';
        messagesEl.appendChild(row);
        scrollMessages();
    }

    function appendBotBubble(text) {
        var row = document.createElement('div');
        row.className = 'assistant-demo__row assistant-demo__row--bot';
        row.innerHTML = '<div class="spa-chat-bubble spa-chat-bubble--assistant">'
            + '<p class="spa-chat-bubble-text spa-chat-bubble-text--assistant mb-0">' + escapeHtml(text) + '</p>'
            + '</div>';
        messagesEl.appendChild(row);
        scrollMessages();
    }

    function appendFlowShell(title) {
        var row = document.createElement('div');
        row.className = 'spa-chat-flow-row';
        row.innerHTML = '<div class="spa-chat-flow-turn">'
            + '<div class="spa-flow-chat-header">'
            + '<h3 class="spa-flow-chat-title">' + escapeHtml(title) + '</h3>'
            + '<hr class="spa-flow-chat-rule" aria-hidden="true">'
            + '</div>'
            + '<div class="spa-chat-flow-ui"></div>'
            + '</div>';
        messagesEl.appendChild(row);
        scrollMessages();
        return row;
    }

    function buildAsistenciaCard() {
        return ''
            + '<div class="bio-ui-json-message">'
            + '<div class="bio-ui-json-message__title">Seguimiento de síntomas</div>'
            + '<div class="bio-ui-json-message__body">Fiebre desde ayer · Dolor de cabeza</div>'
            + '</div>'
            + '<div class="assistant-demo__pick-group">'
            + '<div class="assistant-demo__pick-title">¿Querés coordinar un turno ahora?</div>'
            + '<div class="assistant-demo__pick-list">'
            + '<button type="button" class="assistant-demo__pick-btn is-selected" tabindex="-1">Sí, buscar turno</button>'
            + '<button type="button" class="assistant-demo__pick-btn" tabindex="-1">Seguir conversando</button>'
            + '</div></div>';
    }

    function buildTurnoCard() {
        return ''
            + '<div class="assistant-demo__pick-group">'
            + '<div class="assistant-demo__pick-title">Modalidad</div>'
            + '<div class="assistant-demo__pick-list">'
            + '<button type="button" class="assistant-demo__pick-btn is-selected" tabindex="-1">Presencial</button>'
            + '<button type="button" class="assistant-demo__pick-btn" tabindex="-1">Videollamada</button>'
            + '</div></div>'
            + '<div class="assistant-demo__pick-group">'
            + '<div class="assistant-demo__pick-title">Cardiología — Centro Norte</div>'
            + '<div class="assistant-demo__pick-list assistant-demo__pick-list--cards">'
            + '<button type="button" class="assistant-demo__pick-btn assistant-demo__pick-btn--card is-selected" tabindex="-1">'
            + '<span class="assistant-demo__pick-card-label">Mié 14 · 10:30</span>'
            + '<span class="assistant-demo__pick-card-meta">Dr. García</span>'
            + '</button>'
            + '<button type="button" class="assistant-demo__pick-btn assistant-demo__pick-btn--card" tabindex="-1">'
            + '<span class="assistant-demo__pick-card-label">Jue 15 · 16:00</span>'
            + '<span class="assistant-demo__pick-card-meta">Dra. López</span>'
            + '</button>'
            + '</div></div>';
    }

    function buildAvisosCard() {
        return ''
            + '<div class="bio-ui-json-message">'
            + '<div class="bio-ui-json-message__title">Próximo turno</div>'
            + '<div class="bio-ui-json-message__body">Miércoles 14 · 10:30 — Cardiología, Centro Norte</div>'
            + '</div>'
            + '<div class="bio-ui-json-message">'
            + '<div class="bio-ui-json-message__title">Recordatorio</div>'
            + '<div class="bio-ui-json-message__body">Control de presión arterial — campaña de salud de tu centro</div>'
            + '</div>';
    }

    async function typeComposer(text, token) {
        if (!composerInput) {
            return;
        }
        demo.classList.add('is-typing');
        setEmptyHintVisible(false);
        if (queryInputWrap) {
            queryInputWrap.classList.add('is-typing');
        }
        composerInput.value = '';
        var step = reducedMotion ? text.length : 1;
        for (var i = 0; i < text.length; i += step) {
            if (token !== runToken) {
                return;
            }
            composerInput.value = text.substring(0, i + step);
            scrollMessages();
            await wait(reducedMotion ? 0 : 32);
        }
        composerInput.value = text;
    }

    function stopTypingState() {
        demo.classList.remove('is-typing');
        if (queryInputWrap) {
            queryInputWrap.classList.remove('is-typing');
        }
    }

    async function showFlowCard(flowRow, html, token) {
        var uiEl = flowRow.querySelector('.spa-chat-flow-ui');
        if (!uiEl || token !== runToken) {
            return;
        }
        uiEl.innerHTML = html;
        scrollMessages();
        requestAnimationFrame(function () {
            uiEl.classList.add('is-visible');
        });
        await wait(reducedMotion ? 120 : 380);
    }

    function runFlowInstant(flow) {
        appendUserBubble(flow.userText);
        appendBotBubble(flow.botText);
        var flowRow = appendFlowShell(flow.flowTitle);
        var uiEl = flowRow.querySelector('.spa-chat-flow-ui');
        if (uiEl) {
            uiEl.innerHTML = flow.cardHtml;
            uiEl.classList.add('is-visible');
        }
        scrollMessages();
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
        clearMessages();
        stopTypingState();

        if (reducedMotion) {
            runFlowInstant(flow);
            scheduleRotate();
            return;
        }

        await wait(500);
        await typeComposer(flow.userText, token);
        if (token !== runToken) {
            return;
        }

        stopTypingState();
        await wait(280);
        setEmptyHintVisible(false);
        appendUserBubble(flow.userText);
        if (composerInput) {
            composerInput.value = '';
            composerInput.placeholder = COMPOSER_PLACEHOLDER;
        }

        await wait(650);
        if (token !== runToken) {
            return;
        }

        appendBotBubble(flow.botText);
        await wait(500);
        if (token !== runToken) {
            return;
        }

        var flowRow = appendFlowShell(flow.flowTitle);
        await showFlowCard(flowRow, flow.cardHtml, token);

        if (token === runToken) {
            scheduleRotate();
        }
    }

    function nextFlowId() {
        var idx = FLOW_ORDER.indexOf(activeFlow);
        var next = idx >= 0 ? (idx + 1) % FLOW_ORDER.length : 0;
        return FLOW_ORDER[next];
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
        if (!paused) {
            return;
        }
        paused = false;
        scheduleRotate();
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
        runFlow('asistencia');
    }

    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    startWhenVisible();
                    observer.disconnect();
                }
            });
        }, { rootMargin: '0px 0px -10% 0px', threshold: 0.15 });
        observer.observe(demo);
    } else {
        startWhenVisible();
    }
})();
