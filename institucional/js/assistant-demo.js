/**

 * Demo animado del asistente web (sitio institucional).

 * Mock estático — sin API ni spa-home.js.

 * Editar: alineado a data-access.editar (edición dispersa).

 */

(function () {

    'use strict';



    var demo = document.querySelector('.assistant-demo');

    if (!demo) {

        return;

    }



    var messagesEl = demo.querySelector('.assistant-demo__messages');

    var composerInput = demo.querySelector('.assistant-demo__composer-input');

    var queryInputWrap = demo.querySelector('.spa-query-input');

    var tabs = demo.querySelectorAll('.assistant-demo__tab');

    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;



    /** >1 = animación más lenta (pausas y rotación entre flujos). */
    var SPEED = 1.5;
    var ROTATE_MS = reducedMotion ? Math.round(14000 * SPEED) : Math.round(22000 * SPEED);

    var activeFlow = 'listar';

    var runToken = 0;

    var rotateTimer = null;

    var paused = false;

    var started = false;



    var FLOW_ORDER = ['listar', 'editar', 'crear'];



    var FLOWS = {

        listar: {

            userText: 'Mostrame el listado de registros',

            botText: 'Estos son los registros que encontré.',

            flowTitle: 'Listado',

            showConfirm: false,

            buildUi: buildListUi,

            animate: animateListFlow

        },

        editar: {

            userText: 'Quiero modificar datos del personal',

            botText: 'Elegí qué editar, el registro y los aspectos a modificar.',

            flowTitle: 'Edición',

            successText: 'Los cambios se guardaron correctamente.',

            animate: animateEditSparseFlow

        },

        crear: {

            userText: 'Necesito dar de alta un registro',

            botText: 'Seguí los pasos: persona, servicio y confirmá el alta.',

            flowTitle: 'Crear',

            successText: 'Registro creado correctamente.',

            animate: animateCreateFlow

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

        if (!messagesEl) {

            return;

        }

        messagesEl.scrollTop = messagesEl.scrollHeight;

    }



    function setActiveTab(flowId) {

        tabs.forEach(function (tab) {

            var isActive = tab.getAttribute('data-flow') === flowId;

            tab.classList.toggle('is-active', isActive);

            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');

        });

    }



    function clearMessages() {

        if (messagesEl) {

            messagesEl.innerHTML = '';

        }

        if (composerInput) {

            composerInput.value = '';

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

        var row = document.createElement('div');

        row.className = 'assistant-demo__row assistant-demo__row--user';

        row.innerHTML =

            '<div class="spa-chat-bubble spa-chat-bubble--user">'

            + '<p class="spa-chat-bubble-text spa-chat-bubble-text--user mb-0">' + escapeHtml(text) + '</p>'

            + '</div>';

        messagesEl.appendChild(row);

        scrollMessages();

    }



    function appendBotBubble(text) {

        var row = document.createElement('div');

        row.className = 'assistant-demo__row assistant-demo__row--bot';

        row.innerHTML =

            '<div class="spa-chat-bubble spa-chat-bubble--assistant">'

            + '<p class="spa-chat-bubble-text spa-chat-bubble-text--assistant mb-0">' + escapeHtml(text) + '</p>'

            + '</div>';

        messagesEl.appendChild(row);

        scrollMessages();

    }



    function appendFlowShell(title) {

        var row = document.createElement('div');

        row.className = 'spa-chat-flow-row';

        row.innerHTML =

            '<div class="spa-chat-flow-turn">'

            + '<div class="spa-flow-chat-header">'

            + '<h3 class="spa-flow-chat-title">' + escapeHtml(title) + '</h3>'

            + '<hr class="spa-flow-chat-rule" aria-hidden="true">'

            + '</div>'

            + '<div class="spa-chat-flow-ui"></div>'

            + '<div class="spa-flow-submit-inline">'

            + '<button type="button" class="assistant-demo__btn-confirm" tabindex="-1">Continuar</button>'

            + '</div>'

            + '</div>';

        messagesEl.appendChild(row);

        scrollMessages();

        return row;

    }



    function setConfirmLabel(flowRow, label) {

        var btn = flowRow.querySelector('.assistant-demo__btn-confirm');

        if (btn) {

            btn.textContent = label;

        }

    }



    function buildIntroMessage(title, text) {

        return ''

            + '<div class="bio-ui-json-message">'

            + '<div class="bio-ui-json-message__title">' + escapeHtml(title) + '</div>'

            + '<div class="bio-ui-json-message__body">' + escapeHtml(text) + '</div>'

            + '</div>';

    }



    function buildPickGroup(title, options, selectedValue) {

        var html = '<div class="assistant-demo__pick-group">';

        if (title) {

            html += '<div class="assistant-demo__pick-title">' + escapeHtml(title) + '</div>';

        }

        html += '<div class="assistant-demo__pick-list">';

        options.forEach(function (opt) {

            var sel = opt.value === selectedValue ? ' is-selected' : '';

            html += '<button type="button" class="assistant-demo__pick-btn' + sel + '" data-demo-pick="' + escapeHtml(opt.value) + '" tabindex="-1">'

                + escapeHtml(opt.label)

                + '</button>';

        });

        html += '</div></div>';

        return html;

    }



    function buildListUi() {

        return ''

            + '<div class="bio-ui-json-list bio-ui-json-list--layout-table">'

            + '<table class="bio-ui-json-list-table">'

            + '<thead><tr><th>Nombre</th><th>Área</th><th>Estado</th></tr></thead>'

            + '<tbody>'

            + '<tr data-demo-row="1"><td>Lorem Ipsum</td><td>Dolor sit</td><td>Activo</td></tr>'

            + '<tr data-demo-row="2"><td>Amet Consect</td><td>Adipiscing elit</td><td>Activo</td></tr>'

            + '<tr data-demo-row="3"><td>Sed Eiusmod</td><td>Tempor incid</td><td>Activo</td></tr>'

            + '</tbody></table></div>';

    }



    /** Paso surfaces — ¿Qué querés editar? */

    function buildEditSurfacesUi(selectedId) {

        return buildIntroMessage(

            '¿Qué querés editar?',

            'Elegí el tipo de datos que necesitás modificar.'

        ) + buildPickGroup('', [

            { value: 'profesional_en_efector', label: 'Personal del centro' }

        ], selectedId || '');

    }



    /** Paso subjects — elegir registro (métrica listar) */

    function buildEditSubjectsUi(highlightRow) {

        var html = buildIntroMessage(

            'Elegí el registro',

            'Primero elegí el registro; después marcá qué aspectos querés modificar.'

        );

        html += '<div class="bio-ui-json-list bio-ui-json-list--layout-table">';

        html += '<table class="bio-ui-json-list-table"><thead><tr>';

        html += '<th>Profesional</th><th>Servicio</th><th>Estado</th>';

        html += '</tr></thead><tbody>';

        var rows = [

            ['1', 'Lorem Ipsum', 'Dolor sit', 'Activo'],

            ['2', 'Amet Consect', 'Adipiscing elit', 'Activo'],

            ['3', 'Sed Eiusmod', 'Tempor incid', 'Activo']

        ];

        rows.forEach(function (r) {

            var hi = highlightRow === r[0] ? ' class="is-highlight"' : '';

            html += '<tr data-demo-row="' + r[0] + '"' + hi + '>';

            html += '<td>' + escapeHtml(r[1]) + '</td><td>' + escapeHtml(r[2]) + '</td><td>' + escapeHtml(r[3]) + '</td>';

            html += '</tr>';

        });

        html += '</tbody></table></div>';

        return html;

    }



    /** Paso aspects — aspectos editables */

    function buildEditAspectsUi(selectedId) {

        return buildIntroMessage(

            'Editar: Personal del centro',

            'Elegí qué aspectos querés modificar y continuá al formulario.'

        ) + buildPickGroup('Aspectos', [

            { value: 'identidad', label: 'Identidad (nombre y apellido)' },

            { value: 'agenda_horarios', label: 'Agenda y horarios' }

        ], selectedId || '');

    }



    /** Paso form — aspecto open_ui (agenda_horarios) */

    function buildEditFormUi() {

        return ''

            + buildIntroMessage(

                'Editar: Amet Consect',

                'Revisá los valores actuales y modificá solo lo necesario.'

            )

            + '<div class="bio-ui-json-message bio-ui-json-message--warning">'

            + '<div class="bio-ui-json-message__title">Agenda y horarios</div>'

            + '<div class="bio-ui-json-message__body">Este aspecto se configura en una pantalla dedicada. Podés continuar con los demás campos o volver más adelante.</div>'

            + '</div>';

    }



    /** Paso confirm — diff antes de persistir */

    function buildEditConfirmUi() {

        return ''

            + buildIntroMessage(

                'Confirmar cambios',

                'Amet Consect — aspecto Agenda y horarios: se abrirá la pantalla dedicada para configurar horarios.'

            )

            + '<div class="assistant-demo__diff">'

            + '<div class="assistant-demo__diff-row">'

            + '<span class="assistant-demo__diff-label">Aspecto</span>'

            + '<span class="assistant-demo__diff-value">Agenda y horarios</span>'

            + '</div>'

            + '<div class="assistant-demo__diff-row">'

            + '<span class="assistant-demo__diff-label">Acción</span>'

            + '<span class="assistant-demo__diff-value">Abrir pantalla dedicada</span>'

            + '</div>'

            + '</div>';

    }



    var CREATE_STEPS = [

        { num: 1, label: 'Persona' },

        { num: 2, label: 'Servicio' },

        { num: 3, label: 'Alta' }

    ];



    function buildCreateTimelineShell() {

        var html = '<ol class="spa-flow-steps-list assistant-demo__timeline">';

        CREATE_STEPS.forEach(function (step, idx) {

            html += '<li class="spa-flow-step-item spa-flow-step-item--pending" data-demo-create-step="' + idx + '">';

            html += '<div class="spa-flow-step-track">';

            html += '<span class="spa-flow-step-num" aria-hidden="true">' + step.num + '</span>';

            html += '<div class="spa-flow-step-body">';

            html += '<div class="spa-flow-step-text">' + escapeHtml(step.label) + '</div>';

            html += '<div class="spa-flow-step-ui"></div>';

            html += '</div></div></li>';

        });

        html += '</ol>';

        return html;

    }



    function setCreateStepState(container, activeIdx) {

        var items = container.querySelectorAll('[data-demo-create-step]');

        items.forEach(function (li, idx) {

            li.classList.remove('spa-flow-step-item--active', 'spa-flow-step-item--done', 'spa-flow-step-item--pending');

            if (idx < activeIdx) {

                li.classList.add('spa-flow-step-item--done');

            } else if (idx === activeIdx) {

                li.classList.add('spa-flow-step-item--active');

            } else {

                li.classList.add('spa-flow-step-item--pending');

            }

        });

    }



    function createStepUiMount(container, stepIdx) {

        var item = container.querySelector('[data-demo-create-step="' + stepIdx + '"]');

        return item ? item.querySelector('.spa-flow-step-ui') : null;

    }



    function buildCreatePersonaUi() {

        return buildPickGroup('', [

            { value: 'lorem', label: 'Lorem Ipsum' },

            { value: 'amet', label: 'Amet Consect' }

        ], '');

    }



    function buildCreateServicioUi() {

        return buildPickGroup('', [

            { value: 'dolor', label: 'Dolor sit' },

            { value: 'magna', label: 'Magna aliqua' }

        ], '');

    }



    function buildCreateAltaUi() {

        return ''

            + '<div class="bio-ui-json-fields">'

            + '<div class="assistant-demo__field"><label>Resumen</label>'

            + '<input type="text" readonly value="" data-demo-field="resumen" placeholder="Persona y servicio"></div>'

            + '<div class="assistant-demo__field"><label>Estado</label>'

            + '<input type="text" readonly value="" data-demo-field="estado" placeholder="Activo"></div>'

            + '</div>';

    }



    async function swapUi(uiEl, html, token) {

        if (!uiEl || token !== runToken) {

            return;

        }

        uiEl.classList.remove('is-visible');

        await wait(reducedMotion ? 80 : 220);

        if (token !== runToken) {

            return;

        }

        uiEl.innerHTML = html;

        scrollMessages();

        requestAnimationFrame(function () {

            uiEl.classList.add('is-visible');

        });

        await wait(reducedMotion ? 120 : 380);

    }



    async function selectPick(uiEl, value, token) {

        if (!uiEl || token !== runToken) {

            return;

        }

        var btn = uiEl.querySelector('[data-demo-pick="' + value + '"]');

        if (btn) {

            btn.classList.add('is-selected');

        }

        await wait(reducedMotion ? 200 : 500);

    }



    async function typeComposer(text, token) {

        if (!composerInput) {

            return;

        }

        demo.classList.add('is-typing');

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



    async function animateListFlow(flowRow, token) {

        var uiEl = flowRow.querySelector('.spa-chat-flow-ui');

        if (uiEl) {

            uiEl.innerHTML = buildListUi();

        }

        requestAnimationFrame(function () {

            flowRow.classList.add('is-visible');

            if (uiEl) {

                uiEl.classList.add('is-visible');

            }

        });

        await wait(500);

        var rows = flowRow.querySelectorAll('[data-demo-row]');

        for (var i = 0; i < rows.length; i++) {

            if (token !== runToken) {

                return;

            }

            rows[i].classList.add('is-highlight');

            await wait(380);

            if (i < rows.length - 1) {

                rows[i].classList.remove('is-highlight');

            }

        }

    }



    /**

     * Edición dispersa: surfaces → subjects → aspects → form → confirm

     * (data-access.editar.yaml)

     */

    async function animateEditSparseFlow(flowRow, token) {

        var uiEl = flowRow.querySelector('.spa-chat-flow-ui');

        if (!uiEl) {

            return;

        }



        setConfirmLabel(flowRow, 'Continuar');

        requestAnimationFrame(function () {

            flowRow.classList.add('is-visible');

        });



        uiEl.innerHTML = buildEditSurfacesUi();

        uiEl.classList.add('is-visible');

        await wait(reducedMotion ? 300 : 600);

        await selectPick(uiEl, 'profesional_en_efector', token);



        await swapUi(uiEl, buildEditSubjectsUi(), token);

        await wait(reducedMotion ? 200 : 400);

        var subjectRow = uiEl.querySelector('[data-demo-row="2"]');

        if (subjectRow) {

            subjectRow.classList.add('is-highlight');

        }

        await wait(reducedMotion ? 400 : 700);



        await swapUi(uiEl, buildEditAspectsUi(), token);

        await selectPick(uiEl, 'agenda_horarios', token);



        await swapUi(uiEl, buildEditFormUi(), token);

        await wait(reducedMotion ? 300 : 550);



        await swapUi(uiEl, buildEditConfirmUi(), token);

        setConfirmLabel(flowRow, 'Confirmar');

    }



    async function animateCreateFlow(flowRow, token) {

        var uiEl = flowRow.querySelector('.spa-chat-flow-ui');

        if (!uiEl) {

            return;

        }



        uiEl.innerHTML = buildCreateTimelineShell();

        requestAnimationFrame(function () {

            flowRow.classList.add('is-visible');

            uiEl.classList.add('is-visible');

        });



        var timeline = uiEl;



        /* Paso 1 — Persona */

        setCreateStepState(timeline, 0);

        var mount0 = createStepUiMount(timeline, 0);

        if (mount0) {

            mount0.innerHTML = buildCreatePersonaUi();

        }

        scrollMessages();

        await wait(reducedMotion ? 300 : 550);

        if (mount0) {

            await selectPick(mount0, 'amet', token);

        }



        /* Paso 2 — Servicio */

        if (token !== runToken) {

            return;

        }

        setCreateStepState(timeline, 1);

        if (mount0) {

            mount0.innerHTML = '';

        }

        var mount1 = createStepUiMount(timeline, 1);

        if (mount1) {

            mount1.innerHTML = buildCreateServicioUi();

        }

        scrollMessages();

        await wait(reducedMotion ? 250 : 450);

        if (mount1) {

            await selectPick(mount1, 'magna', token);

        }



        /* Paso 3 — Alta */

        if (token !== runToken) {

            return;

        }

        setCreateStepState(timeline, 2);

        if (mount1) {

            mount1.innerHTML = '';

        }

        var mount2 = createStepUiMount(timeline, 2);

        if (mount2) {

            mount2.innerHTML = buildCreateAltaUi();

        }

        scrollMessages();

        await wait(reducedMotion ? 300 : 500);



        var fields = mount2 ? mount2.querySelectorAll('[data-demo-field]') : [];

        var values = { resumen: 'Amet Consect — Magna aliqua', estado: 'Activo' };

        for (var i = 0; i < fields.length; i++) {

            if (token !== runToken) {

                return;

            }

            var field = fields[i];

            var key = field.getAttribute('data-demo-field');

            field.value = values[key] || '';

            field.classList.add('is-filled');

            await wait(450);

        }



        setConfirmLabel(flowRow, 'Confirmar');

    }



    function appendFlowSuccess(flowRow, message) {

        var turn = flowRow.querySelector('.spa-chat-flow-turn');

        if (!turn) {

            appendBotBubble(message);

            return;

        }

        var summary = document.createElement('div');

        summary.className = 'spa-flow-completed-summary';

        summary.innerHTML = '<p class="spa-flow-completed-summary__alert">' + escapeHtml(message) + '</p>';

        turn.appendChild(summary);

        requestAnimationFrame(function () {

            summary.classList.add('is-visible');

        });

        scrollMessages();

    }



    async function showFlowUi(flowRow, flow, token) {

        var submitEl = flowRow.querySelector('.spa-flow-submit-inline');

        var confirmBtn = flowRow.querySelector('.assistant-demo__btn-confirm');



        if (flow.animate) {

            await flow.animate(flowRow, token);

        }



        if (token !== runToken) {

            return;

        }



        if (flow.showConfirm === false) {

            scrollMessages();

            await wait(reducedMotion ? 500 : 1400);

            return;

        }



        if (submitEl) {

            submitEl.classList.add('is-visible');

        }

        scrollMessages();

        await wait(400);



        if (confirmBtn && token === runToken) {

            confirmBtn.classList.add('is-highlight');

        }



        await wait(reducedMotion ? 400 : 900);

        if (token !== runToken) {

            return;

        }



        if (confirmBtn) {

            confirmBtn.classList.remove('is-highlight');

        }

        appendFlowSuccess(flowRow, flow.successText);

    }



    async function runFlowInstant(flow, flowId) {

        composerInput.value = flow.userText;

        appendUserBubble(flow.userText);

        appendBotBubble(flow.botText);

        var staticRow = appendFlowShell(flow.flowTitle);

        staticRow.classList.add('is-visible');

        var staticUi = staticRow.querySelector('.spa-chat-flow-ui');

        var staticSubmit = staticRow.querySelector('.spa-flow-submit-inline');



        if (staticUi) {

            if (flowId === 'editar') {

                staticUi.innerHTML = buildEditConfirmUi();

                setConfirmLabel(staticRow, 'Confirmar');

            } else if (flowId === 'crear') {

                staticUi.innerHTML = buildCreateTimelineShell();

                setCreateStepState(staticUi, 2);

                var m2 = createStepUiMount(staticUi, 2);

                if (m2) {

                    m2.innerHTML = buildCreateAltaUi();

                    m2.querySelectorAll('[data-demo-field]').forEach(function (el) {

                        var key = el.getAttribute('data-demo-field');

                        var vals = { resumen: 'Amet Consect — Magna aliqua', estado: 'Activo' };

                        el.value = vals[key] || '';

                        el.classList.add('is-filled');

                    });

                }

                setConfirmLabel(staticRow, 'Confirmar');

            } else if (flow.buildUi) {

                staticUi.innerHTML = flow.buildUi();

                if (flowId === 'listar') {

                    var highlightRow = staticUi.querySelector('[data-demo-row="2"]');

                    if (highlightRow) {

                        highlightRow.classList.add('is-highlight');

                    }

                }

            }

            staticUi.classList.add('is-visible');

        }



        if (staticSubmit && flow.showConfirm !== false) {

            staticSubmit.classList.add('is-visible');

        }

        if (flow.showConfirm !== false) {

            appendFlowSuccess(staticRow, flow.successText);

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

            runFlowInstant(flow, flowId);

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

        appendUserBubble(flow.userText);

        if (composerInput) {

            composerInput.value = '';

        }



        await wait(650);

        if (token !== runToken) {

            return;

        }

        appendBotBubble(flow.botText);



        await wait(550);

        if (token !== runToken) {

            return;

        }



        var flowRow = appendFlowShell(flow.flowTitle);

        await showFlowUi(flowRow, flow, token);



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

        runFlow('listar');

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


