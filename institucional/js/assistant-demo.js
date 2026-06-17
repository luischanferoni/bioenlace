/**
 * Demo animado del asistente web (sitio institucional).
 * Mock estático — sin API ni spa-home.js.
 * Editar: intents por contexto (propio / staff) → sujeto → campos del YAML.
 */

(function () {

    'use strict';



    var demo = document.querySelector('.assistant-demo');

    if (!demo) {

        return;

    }



    var messagesEl = demo.querySelector('.assistant-demo__messages');
    var emptyHintEl = document.getElementById('assistant-demo-empty-hint');
    var composerInput = demo.querySelector('.assistant-demo__composer-input');
    var queryInputWrap = demo.querySelector('.spa-query-input');
    var tabs = demo.querySelectorAll('.assistant-demo__tab');
    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var COMPOSER_PLACEHOLDER = 'Escribí una consulta para comenzar. Ejemplo: “Necesito buscar una persona” o “Quiero ver los reportes disponibles”.';



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
            userText: 'Mostrame los profesionales del centro',
            botText: 'Este es el listado de profesionales en tu efector.',
            flowTitle: 'Listar profesionales',
            showConfirm: false,
            buildUi: buildListUi,
            animate: animateListFlow
        },
        editar: {
            userText: 'Necesito modificar una condición laboral',
            botText: '¿Querés modificar tu condición laboral o la de un profesional del centro?',
            flowTitle: 'Condición laboral',
            successText: 'La condición laboral se actualizó correctamente.',
            animate: animateEditIntentFlow
        },
        crear: {
            userText: 'Necesito crear un turno para un paciente',
            botText: 'Elegí servicio, centro de salud, profesional y horario.',
            flowTitle: 'Crear turno',
            successText: 'Turno reservado correctamente.',
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



    function setEmptyHintVisible(visible) {
        if (!emptyHintEl) {
            return;
        }
        emptyHintEl.classList.toggle('is-hidden', !visible);
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

    function buildPickCardGroup(options, selectedValue) {
        var html = '<div class="assistant-demo__pick-group">';
        html += '<div class="assistant-demo__pick-list assistant-demo__pick-list--cards">';
        options.forEach(function (opt) {
            var sel = opt.value === selectedValue ? ' is-selected' : '';
            html += '<button type="button" class="assistant-demo__pick-btn assistant-demo__pick-btn--card' + sel + '" data-demo-pick="'
                + escapeHtml(opt.value) + '" tabindex="-1">';
            html += '<span class="assistant-demo__pick-card-label">' + escapeHtml(opt.label) + '</span>';
            if (opt.meta) {
                html += '<span class="assistant-demo__pick-card-meta">' + escapeHtml(opt.meta) + '</span>';
            }
            html += '</button>';
        });
        html += '</div></div>';
        return html;
    }



    function buildListUi() {
        return ''
            + '<div class="bio-ui-json-list bio-ui-json-list--layout-table">'
            + '<table class="bio-ui-json-list-table">'
            + '<thead><tr><th>Profesional</th><th>Servicio</th><th>Condición</th></tr></thead>'
            + '<tbody>'
            + '<tr data-demo-row="1"><td>Dra. Lorem Ipsum</td><td>Clínica médica</td><td>Planta permanente</td></tr>'
            + '<tr data-demo-row="2"><td>Dr. Amet Consect</td><td>Pediatría</td><td>Contrato</td></tr>'
            + '<tr data-demo-row="3"><td>Dra. Sed Eiusmod</td><td>Ginecología</td><td>Planta permanente</td></tr>'
            + '</tbody></table></div>';
    }

    /** Paso 1 — contexto (intent propio vs staff) */
    function buildEditContextUi(selectedId) {
        return buildIntroMessage(
            '¿Sobre quién querés actuar?',
            'Elegí si modificás tu condición laboral o la de otro profesional del centro.'
        ) + buildPickGroup('', [
            { value: 'propio', label: 'Mi condición laboral' },
            { value: 'staff', label: 'De un profesional del centro' }
        ], selectedId || '');
    }

    /** Paso 2 — elegir PES (staff) */
    function buildEditPesPickUi(selectedId) {
        return buildIntroMessage(
            'Elegí el profesional',
            'Tocá la asignación cuya condición laboral querés modificar.'
        ) + buildPickCardGroup([
            { value: '1', label: 'Dra. Lorem Ipsum', meta: 'Clínica médica · vigente desde 01/2024' },
            { value: '2', label: 'Dr. Amet Consect', meta: 'Pediatría · vigente desde 03/2025' },
            { value: '3', label: 'Dra. Sed Eiusmod', meta: 'Ginecología · vigente desde 06/2023' }
        ], selectedId || '');
    }

    /** Paso 3 — grupos de campos (presentación YAML) */
    function buildEditFieldGroupsUi(selectedId) {
        return buildIntroMessage(
            '¿Qué querés modificar?',
            'Elegí un grupo de campos o continuá si ya lo indicaste en tu mensaje.'
        ) + buildPickGroup('Grupos', [
            { value: 'condicion', label: 'Condición y tipo' },
            { value: 'vigencia', label: 'Vigencia (fechas)' }
        ], selectedId || '');
    }

    /** Paso 4 — formulario escalar */
    function buildEditCondicionLaboralFormUi() {
        return ''
            + buildIntroMessage(
                'Dr. Amet Consect — vigencia',
                'Modificá solo los campos que necesites.'
            )
            + '<div class="bio-ui-json-fields">'
            + '<div class="assistant-demo__field"><label>Condición laboral</label>'
            + '<input type="text" readonly value="Contrato" data-demo-field="condicion"></div>'
            + '<div class="assistant-demo__field"><label>Fecha inicio</label>'
            + '<input type="text" readonly value="01/03/2025" data-demo-field="inicio"></div>'
            + '<div class="assistant-demo__field"><label>Fecha fin</label>'
            + '<input type="text" readonly value="" data-demo-field="fin" placeholder="31/12/2026"></div>'
            + '</div>';
    }

    /** Paso 5 — confirmar */
    function buildEditCondicionLaboralConfirmUi() {
        return ''
            + buildIntroMessage(
                'Confirmar cambios',
                'Dr. Amet Consect — se actualizará la vigencia de la condición laboral.'
            )
            + '<div class="assistant-demo__diff">'
            + '<div class="assistant-demo__diff-row">'
            + '<span class="assistant-demo__diff-label">Fecha fin</span>'
            + '<span class="assistant-demo__diff-value">— → 31/12/2026</span>'
            + '</div>'
            + '<div class="assistant-demo__diff-row">'
            + '<span class="assistant-demo__diff-label">Condición</span>'
            + '<span class="assistant-demo__diff-value">Contrato (sin cambios)</span>'
            + '</div>'
            + '</div>';
    }



    var CREATE_STEPS = [
        { num: 1, label: 'Servicio' },
        { num: 2, label: 'Centro de salud' },
        { num: 3, label: 'Profesional y horario' }
    ];

    var EDIT_STEPS = [
        { num: 1, label: 'Ámbito' },
        { num: 2, label: 'Profesional' },
        { num: 3, label: 'Campos' },
        { num: 4, label: 'Confirmar' }
    ];



    function buildFlowTimelineShell(steps, stepAttr) {
        var html = '<ol class="spa-flow-steps-list assistant-demo__timeline">';
        steps.forEach(function (step, idx) {
            html += '<li class="spa-flow-step-item spa-flow-step-item--pending" ' + stepAttr + '="' + idx + '">';
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

    function buildCreateTimelineShell() {
        return buildFlowTimelineShell(CREATE_STEPS, 'data-demo-create-step');
    }

    function buildEditTimelineShell() {
        return buildFlowTimelineShell(EDIT_STEPS, 'data-demo-edit-step');
    }



    function setFlowStepState(container, activeIdx, stepAttr) {
        var items = container.querySelectorAll('[' + stepAttr + ']');
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

    function flowStepUiMount(container, stepIdx, stepAttr) {
        var item = container.querySelector('[' + stepAttr + '="' + stepIdx + '"]');
        return item ? item.querySelector('.spa-flow-step-ui') : null;
    }



    function setCreateStepState(container, activeIdx) {
        setFlowStepState(container, activeIdx, 'data-demo-create-step');
    }



    function createStepUiMount(container, stepIdx) {
        return flowStepUiMount(container, stepIdx, 'data-demo-create-step');
    }

    function setEditStepState(container, activeIdx) {
        setFlowStepState(container, activeIdx, 'data-demo-edit-step');
    }

    function editStepUiMount(container, stepIdx) {
        return flowStepUiMount(container, stepIdx, 'data-demo-edit-step');
    }

    function mountEditStep(timeline, stepIdx, html) {
        setEditStepState(timeline, stepIdx);
        var mount = editStepUiMount(timeline, stepIdx);
        if (mount) {
            mount.innerHTML = html;
        }
        scrollMessages();
        return mount;
    }

    function mountCreateStep(timeline, stepIdx, html) {
        setCreateStepState(timeline, stepIdx);
        var mount = createStepUiMount(timeline, stepIdx);
        if (mount) {
            mount.innerHTML = html;
        }
        scrollMessages();
        return mount;
    }



    function buildCreateServicioUi() {
        return buildPickGroup('', [
            { value: 'clinica', label: 'Clínica médica' },
            { value: 'pediatria', label: 'Pediatría' }
        ], '');
    }

    function buildCreateEfectorUi() {
        return buildPickGroup('', [
            { value: 'norte', label: 'Centro de salud Norte' },
            { value: 'sur', label: 'Centro de salud Sur' }
        ], '');
    }

    function buildCreateTurnoUi() {
        return ''
            + '<div class="bio-ui-json-fields">'
            + '<div class="assistant-demo__field"><label>Profesional</label>'
            + '<input type="text" readonly value="" data-demo-field="profesional" placeholder="Dr. Amet Consect"></div>'
            + '<div class="assistant-demo__field"><label>Horario</label>'
            + '<input type="text" readonly value="" data-demo-field="horario" placeholder="Martes 10:30"></div>'
            + '<div class="assistant-demo__field"><label>Paciente</label>'
            + '<input type="text" readonly value="" data-demo-field="paciente" placeholder="Beneficiario del turno"></div>'
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
     * Edición: timeline acumulativo — los pasos previos quedan visibles con la selección.
     */
    async function animateEditIntentFlow(flowRow, token) {
        var uiEl = flowRow.querySelector('.spa-chat-flow-ui');
        if (!uiEl) {
            return;
        }

        setConfirmLabel(flowRow, 'Continuar');
        uiEl.innerHTML = buildEditTimelineShell();
        requestAnimationFrame(function () {
            flowRow.classList.add('is-visible');
            uiEl.classList.add('is-visible');
        });

        var timeline = uiEl;
        var mount0 = mountEditStep(timeline, 0, buildEditContextUi());
        await wait(reducedMotion ? 300 : 600);
        if (mount0) {
            await selectPick(mount0, 'staff', token);
        }

        if (token !== runToken) {
            return;
        }
        var mount1 = mountEditStep(timeline, 1, buildEditPesPickUi());
        await wait(reducedMotion ? 250 : 500);
        if (mount1) {
            await selectPick(mount1, '2', token);
        }

        if (token !== runToken) {
            return;
        }
        var mount2 = mountEditStep(timeline, 2, buildEditFieldGroupsUi());
        await wait(reducedMotion ? 250 : 450);
        if (mount2) {
            await selectPick(mount2, 'vigencia', token);
        }

        if (token !== runToken) {
            return;
        }
        var mount3 = mountEditStep(timeline, 3, buildEditCondicionLaboralFormUi());
        await wait(reducedMotion ? 300 : 550);
        var finField = mount3 ? mount3.querySelector('[data-demo-field="fin"]') : null;
        if (finField) {
            finField.value = '31/12/2026';
            finField.classList.add('is-changed');
            await wait(reducedMotion ? 250 : 500);
        }

        if (mount3) {
            await swapUi(mount3, buildEditCondicionLaboralConfirmUi(), token);
        }

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



        /* Paso 1 — Servicio */
        var mount0 = mountCreateStep(timeline, 0, buildCreateServicioUi());
        await wait(reducedMotion ? 300 : 550);
        if (mount0) {
            await selectPick(mount0, 'pediatria', token);
        }

        /* Paso 2 — Centro */
        if (token !== runToken) {
            return;
        }
        var mount1 = mountCreateStep(timeline, 1, buildCreateEfectorUi());
        await wait(reducedMotion ? 250 : 450);
        if (mount1) {
            await selectPick(mount1, 'norte', token);
        }

        /* Paso 3 — Profesional y horario */
        if (token !== runToken) {
            return;
        }
        var mount2 = mountCreateStep(timeline, 2, buildCreateTurnoUi());
        await wait(reducedMotion ? 300 : 500);

        var fields = mount2 ? mount2.querySelectorAll('[data-demo-field]') : [];
        var values = {
            profesional: 'Dr. Amet Consect',
            horario: 'Martes 10:30',
            paciente: 'Beneficiario del turno'
        };

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
        setEmptyHintVisible(false);
        composerInput.value = flow.userText;
        appendUserBubble(flow.userText);
        appendBotBubble(flow.botText);
        var staticRow = appendFlowShell(flow.flowTitle);
        staticRow.classList.add('is-visible');
        var staticUi = staticRow.querySelector('.spa-chat-flow-ui');
        var staticSubmit = staticRow.querySelector('.spa-flow-submit-inline');

        if (staticUi) {
            if (flowId === 'editar') {
                staticUi.innerHTML = buildEditTimelineShell();
                setEditStepState(staticUi, 3);
                var e0 = editStepUiMount(staticUi, 0);
                var e1 = editStepUiMount(staticUi, 1);
                var e2 = editStepUiMount(staticUi, 2);
                var e3 = editStepUiMount(staticUi, 3);
                if (e0) {
                    e0.innerHTML = buildEditContextUi('staff');
                }
                if (e1) {
                    e1.innerHTML = buildEditPesPickUi('2');
                }
                if (e2) {
                    e2.innerHTML = buildEditFieldGroupsUi('vigencia');
                }
                if (e3) {
                    e3.innerHTML = buildEditCondicionLaboralConfirmUi();
                }
                setConfirmLabel(staticRow, 'Confirmar');
            } else if (flowId === 'crear') {
                staticUi.innerHTML = buildCreateTimelineShell();
                setCreateStepState(staticUi, 2);
                var c0 = createStepUiMount(staticUi, 0);
                var c1 = createStepUiMount(staticUi, 1);
                var m2 = createStepUiMount(staticUi, 2);
                if (c0) {
                    c0.innerHTML = buildCreateServicioUi();
                    var ped = c0.querySelector('[data-demo-pick="pediatria"]');
                    if (ped) {
                        ped.classList.add('is-selected');
                    }
                }
                if (c1) {
                    c1.innerHTML = buildCreateEfectorUi();
                    var norte = c1.querySelector('[data-demo-pick="norte"]');
                    if (norte) {
                        norte.classList.add('is-selected');
                    }
                }
                if (m2) {
                    m2.innerHTML = buildCreateTurnoUi();
                    m2.querySelectorAll('[data-demo-field]').forEach(function (el) {
                        var key = el.getAttribute('data-demo-field');
                        var vals = {
                            profesional: 'Dr. Amet Consect',
                            horario: 'Martes 10:30',
                            paciente: 'Beneficiario del turno'
                        };
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


