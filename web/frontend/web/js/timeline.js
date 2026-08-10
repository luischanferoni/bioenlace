/**
 * JavaScript para el timeline del paciente
 * Maneja la carga dinámica de contenido y formularios
 */
(function() {
    'use strict';
    
    // Variables globales (usar window para evitar conflictos en SPA)
    if (!window.timelineVars) {
        window.timelineVars = {
            pacienteId: null,
            endpoints: {}
        };
    }
    
    // Helper para obtener las variables actualizadas
    function getPacienteId() {
        return window.timelineVars.pacienteId;
    }
    
    function getEndpoints() {
        return window.timelineVars.endpoints;
    }

    function tlTpl(id) {
        var t = document.getElementById(id);
        if (!t || !t.content) return null;
        return document.importNode(t.content, true);
    }

    function tlClear(el) {
        if (el) el.replaceChildren();
    }

    function tlMountMuted(container, iconClass, message) {
        if (!container) return;
        var frag = tlTpl('tpl-tl-muted-icon');
        if (!frag) {
            container.textContent = message || '';
            return;
        }
        var icon = frag.querySelector('[data-field="icon"]');
        if (icon) icon.className = iconClass || 'bi bi-info-circle';
        var msg = frag.querySelector('[data-field="message"]');
        if (msg) msg.textContent = message || '';
        tlClear(container);
        container.appendChild(frag);
    }

    function tlMountAlert(container, variant, iconClass, message) {
        if (!container) return;
        var frag = tlTpl('tpl-tl-alert');
        if (!frag) {
            container.textContent = message || '';
            return;
        }
        var root = frag.firstElementChild;
        root.classList.add('alert-' + (variant || 'warning'));
        root.textContent = '';
        if (iconClass) {
            var i = document.createElement('i');
            i.className = iconClass;
            root.appendChild(i);
            root.appendChild(document.createTextNode(' '));
        }
        root.appendChild(document.createTextNode(message || ''));
        tlClear(container);
        container.appendChild(frag);
    }

    function tlMountSpinner(container, message) {
        if (!container) return;
        var frag = tlTpl('tpl-tl-modal-loading');
        if (!frag) return;
        var msg = frag.querySelector('[data-field="message"]');
        if (msg) msg.textContent = message || 'Cargando...';
        tlClear(container);
        container.appendChild(frag);
    }

    function tlMountSvLoading(container) {
        if (!container) return;
        var frag = tlTpl('tpl-tl-sv-loading');
        if (!frag) return;
        tlClear(container);
        container.appendChild(frag);
    }

// Función para cargar contenido desde endpoint (HTML SSR del servidor)
async function loadContent(url, containerId, title) {
    try {
        const response = await fetch(url);
        const container = document.getElementById(containerId);
        if (!container) return;
        if (response.ok) {
            const html = await response.text();
            var wrap = tlTpl('tpl-tl-content-wrap');
            if (wrap) {
                var titleEl = wrap.querySelector('[data-field="title"]');
                if (titleEl) titleEl.textContent = title || '';
                var body = wrap.querySelector('[data-slot="body"]');
                if (body) body.innerHTML = html;
                tlClear(container);
                container.appendChild(wrap);
            } else {
                container.innerHTML = html;
            }
            container.style.display = 'block';
        } else {
            console.error('Error cargando:', url, 'Status:', response.status);
            var errCard = tlTpl('tpl-tl-content-error-card');
            if (errCard) {
                var tEl = errCard.querySelector('[data-field="title"]');
                if (tEl) tEl.textContent = title || '';
                var alertEl = errCard.querySelector('[data-field="alert"]');
                if (alertEl) alertEl.classList.add('alert-warning');
                var icon = errCard.querySelector('[data-field="icon"]');
                if (icon) icon.className = 'bi bi-exclamation-triangle';
                var msg = errCard.querySelector('[data-field="message"]');
                if (msg) msg.textContent = ' No se pudo cargar el contenido. Intente nuevamente.';
                tlClear(container);
                container.appendChild(errCard);
            }
            container.style.display = 'block';
        }
    } catch (error) {
        console.error('Error en la petición:', error);
        const container = document.getElementById(containerId);
        if (container) {
            var errCard2 = tlTpl('tpl-tl-content-error-card');
            if (errCard2) {
                var tEl2 = errCard2.querySelector('[data-field="title"]');
                if (tEl2) tEl2.textContent = title || '';
                var alertEl2 = errCard2.querySelector('[data-field="alert"]');
                if (alertEl2) alertEl2.classList.add('alert-danger');
                var icon2 = errCard2.querySelector('[data-field="icon"]');
                if (icon2) icon2.className = 'bi bi-x-circle';
                var msg2 = errCard2.querySelector('[data-field="message"]');
                if (msg2) msg2.textContent = ' Error de conexión. Verifique su conexión a internet.';
                tlClear(container);
                container.appendChild(errCard2);
            }
            container.style.display = 'block';
        }
    }
}

// Función para cargar todo el contenido
async function loadAllContent() {
    const promises = [];
    const endpoints = getEndpoints();
    
    // Cargar curvas de crecimiento si aplica
    if (endpoints.curvasCrecimiento) {
        promises.push(loadContent(endpoints.curvasCrecimiento, 'curvas-crecimiento-content', 'Curvas de Crecimiento'));
    }
    
    try {
        // Esperar a que todas las cargas terminen
        await Promise.all(promises);
    } catch (error) {
        console.error('Error en la carga de contenido:', error);
    }
    // No ocultar loading aquí, se ocultará cuando todos los contenidos estén listos
}

// Función para mostrar el loading container
function mostrarLoadingContainer() {
    const loadingContainer = document.getElementById('loading-container');
    if (loadingContainer) {
        console.log('Mostrando loading container');
        loadingContainer.classList.remove('d-none');
        loadingContainer.style.display = 'flex';
        loadingContainer.style.visibility = 'visible';
        loadingContainer.style.opacity = '1';
    }
}

// Función para ocultar el loading container
function ocultarLoadingContainer() {
    const loadingContainer = document.getElementById('loading-container');
    if (loadingContainer) {
        console.log('Ocultando loading container');
        loadingContainer.classList.add('d-none');
        loadingContainer.style.display = 'none';
        loadingContainer.style.visibility = 'hidden';
        loadingContainer.style.opacity = '0';
    }
}

// Función para cargar la última vacuna
/*async function loadUltimaVacuna() {
    try {
        const url = `${endpoints.vacunas}&ultima=1`;
        const response = await fetch(url);
        
        if (response.ok) {
            const data = await response.json();
            const content = document.getElementById('ultima-vacuna-content');
            
            if (data.success) {
                content.innerHTML = data.html;
                
                // Mostrar link si hay más vacunas
                if (data.tieneMasVacunas) {
                    document.getElementById('vacunas-link').style.display = 'block';
                }
            } else {
                content.innerHTML = '<div class="text-muted"><i class="bi bi-info-circle"></i> No se pudieron cargar las vacunas</div>';
            }
        } else {
            throw new Error('Error en la respuesta');
        }
    } catch (error) {
        console.error('Error cargando última vacuna:', error);
        document.getElementById('ultima-vacuna-content').innerHTML = '<div class="text-muted"><i class="bi bi-exclamation-triangle"></i> Error cargando vacunas</div>';
    }
}*/

// Función para cargar todas las vacunas en el modal
async function loadTodasLasVacunas() {
    const modalContent = document.getElementById('modal-vacunas-content');
    tlMountSpinner(modalContent, 'Cargando historial de vacunas...');
    
    try {
        const endpoints = getEndpoints();
        const url = `${endpoints.vacunas}&modal=1`;
        const response = await fetch(url);
        
        if (response.ok) {
            const html = await response.text();
            modalContent.innerHTML = html;
        } else {
            throw new Error('Error en la respuesta');
        }
    } catch (error) {
        console.error('Error cargando todas las vacunas:', error);
        tlMountAlert(modalContent, 'danger', 'bi bi-exclamation-triangle', 'Error cargando el historial de vacunas');
    }
}

function tieneValorSigno(v) {
    return v !== null && v !== undefined && String(v).trim() !== '';
}

function mountSignosVitalesActuales(container, ultimosSv) {
    if (!container) return;
    if (!ultimosSv) {
        tlMountMuted(container, 'bi bi-info-circle', 'No se encontraron signos vitales registrados');
        return;
    }
    const peso = ultimosSv.peso && tieneValorSigno(ultimosSv.peso.value);
    const talla = ultimosSv.talla && tieneValorSigno(ultimosSv.talla.value);
    const imc = ultimosSv.imc && tieneValorSigno(ultimosSv.imc.value);
    const ta = ultimosSv.ta && tieneValorSigno(ultimosSv.ta.sistolica) && tieneValorSigno(ultimosSv.ta.diastolica);
    if (!peso && !talla && !imc && !ta) {
        tlMountMuted(container, 'bi bi-info-circle', 'No se encontraron signos vitales registrados');
        return;
    }
    var row = tlTpl('tpl-tl-sv-actuales-row');
    if (!row) return;
    var cards = row.querySelector('[data-slot="cards"]') || row.firstElementChild;
    function addCard(iconClass, label, valueText) {
        var card = tlTpl('tpl-tl-sv-card');
        if (!card) return;
        var icon = card.querySelector('[data-field="icon"]');
        if (icon) icon.className = iconClass + ' me-2';
        var lab = card.querySelector('[data-field="label"]');
        if (lab) lab.textContent = label;
        var val = card.querySelector('[data-field="value"]');
        if (val) val.textContent = valueText;
        cards.appendChild(card);
    }
    if (peso) addCard('bi bi-speedometer2 text-primary', 'Peso', String(ultimosSv.peso.value) + ' kg');
    if (talla) addCard('bi bi-rulers text-success', 'Altura', String(ultimosSv.talla.value) + ' cm');
    if (imc) addCard('bi bi-graph-up text-info', 'IMC', String(ultimosSv.imc.value));
    if (ta) {
        addCard(
            'bi bi-heart-pulse text-danger',
            'Tensión Arterial',
            String(ultimosSv.ta.sistolica) + '/' + String(ultimosSv.ta.diastolica) + ' mmHg'
        );
    }
    tlClear(container);
    container.appendChild(row);
}

function fillSvCell(slot, text, badgeClass) {
    if (!slot) return;
    tlClear(slot);
    if (text == null || text === '' || text === '-') {
        var dash = tlTpl('tpl-tl-sv-dash');
        if (dash) slot.appendChild(dash);
        else slot.textContent = '-';
        return;
    }
    if (badgeClass) {
        var badge = tlTpl('tpl-tl-sv-badge');
        if (badge) {
            var b = badge.firstElementChild || badge.querySelector('.badge');
            if (b) {
                b.classList.add(badgeClass);
                b.textContent = text;
            }
            slot.appendChild(badge);
            return;
        }
    }
    slot.textContent = text;
}

function mountFilaSignosVitalesModal(tbody, row) {
    var frag = tlTpl('tpl-tl-sv-modal-row');
    if (!frag) return;
    const fechaRaw = row.fecha_atencion != null && row.fecha_atencion !== '' ? row.fecha_atencion : row.fecha;
    let fechaCell = '-';
    if (fechaRaw) {
        const s = String(fechaRaw);
        if (/^\d{4}-\d{2}-\d{2}/.test(s) || s.indexOf('T') !== -1) {
            const d = new Date(s);
            fechaCell = !isNaN(d.getTime())
                ? d.toLocaleString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
                : s;
        } else {
            fechaCell = s;
        }
    }
    var fechaEl = frag.querySelector('[data-field="fecha"]');
    if (fechaEl) fechaEl.textContent = fechaCell;

    let paText = null;
    let paBadge = 'bg-primary';
    if (row.ta1_sistolica != null && row.ta1_sistolica !== '' && row.ta1_diastolica != null && row.ta1_diastolica !== '') {
        paText = String(row.ta1_sistolica) + '/' + String(row.ta1_diastolica) + ' mmHg';
    } else if (row.ta && String(row.ta).indexOf('/') !== -1) {
        paText = String(row.ta) + ' mmHg';
    }
    fillSvCell(frag.querySelector('[data-slot="pa"]'), paText, paBadge);

    let fcText = null;
    if (row.frecuencia_cardiaca != null && row.frecuencia_cardiaca !== '') {
        fcText = String(row.frecuencia_cardiaca) + ' lpm';
    } else if (row.fc != null && row.fc !== '') {
        fcText = String(row.fc) + ' lpm';
    }
    fillSvCell(frag.querySelector('[data-slot="fc"]'), fcText, 'bg-info');

    const tempText = row.temperatura != null && row.temperatura !== ''
        ? String(row.temperatura) + '°C'
        : null;
    fillSvCell(frag.querySelector('[data-slot="temp"]'), tempText, 'bg-warning');

    const spo2Text = row.saturacion_oxigeno != null && row.saturacion_oxigeno !== ''
        ? String(row.saturacion_oxigeno) + '%'
        : null;
    fillSvCell(frag.querySelector('[data-slot="spo2"]'), spo2Text, 'bg-success');

    const pesoText = row.peso != null && row.peso !== '' ? String(row.peso) + ' kg' : null;
    fillSvCell(frag.querySelector('[data-slot="peso"]'), pesoText, null);

    const altText = row.talla != null && row.talla !== '' ? String(row.talla) + ' cm' : null;
    fillSvCell(frag.querySelector('[data-slot="alt"]'), altText, null);

    const imcText = row.imc != null && row.imc !== '' ? Number(row.imc).toFixed(1) : null;
    fillSvCell(frag.querySelector('[data-slot="imc"]'), imcText, 'bg-secondary');

    tbody.appendChild(frag);
}

function mountSignosVitalesModal(container, datosSv) {
    if (!container) return;
    var frag = tlTpl('tpl-tl-sv-modal');
    if (!frag) return;
    var empty = frag.querySelector('[data-slot="empty"]');
    var tableWrap = frag.querySelector('[data-slot="table-wrap"]');
    var tbody = frag.querySelector('[data-slot="tbody"]');
    if (!datosSv || !datosSv.length) {
        if (empty) empty.classList.remove('d-none');
        if (tableWrap) tableWrap.classList.add('d-none');
    } else {
        if (empty) empty.classList.add('d-none');
        if (tableWrap) tableWrap.classList.remove('d-none');
        (datosSv || []).forEach(function (row) {
            mountFilaSignosVitalesModal(tbody, row);
        });
    }
    tlClear(container);
    container.appendChild(frag);
}

/** Extra fijo para fetch JSON de signos vitales (solo se pasa a {@link window.BioenlaceApiClient.mergeHeaders}). */
var TIMELINE_SIGNOS_VITALES_FETCH_EXTRA = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

/**
 * Aplica el bloque de signos vitales (misma forma que data en GET .../signos-vitales).
 * Guarda datos_sv en timelineVars para el modal sin segundo fetch.
 * @param {object|null} d
 */
function applySignosVitalesPayload(d) {
    const content = document.getElementById('signos-vitales-actuales-content');
    if (!window.timelineVars) {
        window.timelineVars = {};
    }
    if (!d) {
        window.timelineVars.signosVitalesDatosSv = [];
        if (content) {
            tlMountMuted(content, 'bi bi-info-circle', 'No se encontraron signos vitales registrados');
        }
        const tituloEmpty = document.getElementById('signos-vitales-titulo');
        if (tituloEmpty) {
            tituloEmpty.textContent = 'SIGNOS VITALES ACTUALES';
        }
        const linkEmpty = document.getElementById('signos-vitales-link');
        if (linkEmpty) {
            linkEmpty.style.display = 'none';
        }
        return;
    }
    window.timelineVars.signosVitalesDatosSv = Array.isArray(d.datos_sv) ? d.datos_sv : [];
    if (content) {
        mountSignosVitalesActuales(content, d.ultimos_sv);
    }
    const titulo = document.getElementById('signos-vitales-titulo');
    if (titulo) {
        titulo.textContent = d.fecha_titulo
            ? 'SIGNOS VITALES ACTUALES (' + d.fecha_titulo + ')'
            : 'SIGNOS VITALES ACTUALES';
    }
    const link = document.getElementById('signos-vitales-link');
    if (link) {
        link.style.display = d.tiene_mas_sv ? 'block' : 'none';
    }
}

// Función para cargar los signos vitales actuales (API v1 JSON)
async function loadSignosVitalesActuales() {
    const content = document.getElementById('signos-vitales-actuales-content');
    
    // Verificar que el elemento existe
    if (!content) {
        console.error('Elemento signos-vitales-actuales-content no encontrado');
        return;
    }
    
    // Verificar que el endpoint está configurado
    const endpoints = getEndpoints();
    if (!endpoints || !endpoints.signosVitales) {
        console.error('Endpoint de signos vitales no configurado');
        tlMountMuted(content, 'bi bi-exclamation-triangle', 'Error: Endpoint no configurado');
        return;
    }
    
    try {
        const url = endpoints.signosVitales;
        console.log('Cargando signos vitales desde:', url);
        
        const response = await fetch(url, {
            method: 'GET',
            headers: window.BioenlaceApiClient.mergeHeaders(TIMELINE_SIGNOS_VITALES_FETCH_EXTRA),
            credentials: 'same-origin'
        });
        
        console.log('Respuesta recibida:', response.status, response.statusText);
        
        if (response.ok) {
            const data = await response.json();
            console.log('Datos recibidos:', data);
            
            if (data && data.success && data.data) {
                applySignosVitalesPayload(data.data);
                console.log('Contenido de signos vitales actualizado');
            } else {
                tlMountMuted(content, 'bi bi-info-circle', 'No se pudieron cargar los signos vitales');
                console.warn('Respuesta de signos vitales sin success:', data);
            }
            
            // Ocultar loading después de actualizar el contenido
            ocultarLoadingContainer();
        } else {
            const errorText = await response.text();
            console.error('Error en la respuesta:', response.status, errorText);
            throw new Error(`Error ${response.status}: ${response.statusText}`);
        }
    } catch (error) {
        console.error('Error cargando signos vitales actuales:', error);
        if (content) {
            tlMountMuted(content, 'bi bi-exclamation-triangle', 'Error cargando signos vitales: ' + error.message);
        }
        // Ocultar loading incluso si hay error
        ocultarLoadingContainer();
    }
}

// Función para cargar todos los signos vitales en el modal (API v1 JSON)
async function loadTodosLosSignosVitales() {
    const modalContent = document.getElementById('modal-signos-vitales-content');
    tlMountSpinner(modalContent, 'Cargando historial de signos vitales...');
    
    try {
        const cached =
            window.timelineVars &&
            Object.prototype.hasOwnProperty.call(window.timelineVars, 'signosVitalesDatosSv') &&
            Array.isArray(window.timelineVars.signosVitalesDatosSv);
        if (cached) {
            mountSignosVitalesModal(modalContent, window.timelineVars.signosVitalesDatosSv);
            return;
        }

        const endpoints = getEndpoints();
        if (!endpoints || !endpoints.signosVitales) {
            tlMountAlert(modalContent, 'warning', 'bi bi-info-circle', 'No hay historial de signos vitales cargado.');
            return;
        }
        const url = endpoints.signosVitales;
        const response = await fetch(url, {
            method: 'GET',
            headers: window.BioenlaceApiClient.mergeHeaders(TIMELINE_SIGNOS_VITALES_FETCH_EXTRA),
            credentials: 'same-origin'
        });
        
        if (response.ok) {
            const data = await response.json();
            if (data && data.success && data.data && Array.isArray(data.data.datos_sv)) {
                mountSignosVitalesModal(modalContent, data.data.datos_sv);
            } else {
                tlMountAlert(modalContent, 'warning', 'bi bi-exclamation-triangle', 'No se pudo interpretar la respuesta del servidor.');
            }
        } else {
            throw new Error('Error en la respuesta');
        }
    } catch (error) {
        console.error('Error cargando todos los signos vitales:', error);
        tlMountAlert(modalContent, 'danger', 'bi bi-exclamation-triangle', 'Error cargando el historial de signos vitales');
    }
}

/**
 * Query string de la página HC para el fetch de formulario-consulta (sin duplicar `id` ni `fecha`).
 */
function buildFormularioConsultaQueryFromLocation() {
    if (typeof window === 'undefined' || !window.location || !window.location.search) {
        return '';
    }
    try {
        const params = new URLSearchParams(window.location.search);
        params.delete('fecha');
        params.delete('id');
        const s = params.toString();
        return s || '';
    } catch (e) {
        return '';
    }
}

// Función para cargar el estado del formulario via AJAX
async function cargarFormularioConsulta() {
    const container = document.getElementById('formulario-container');
    
    try {
        const endpoints = getEndpoints();
        let formularioUrl = endpoints.formularioConsulta || '';
        const qs = buildFormularioConsultaQueryFromLocation();
        if (qs) {
            formularioUrl += (formularioUrl.indexOf('?') >= 0 ? '&' : '?') + qs;
        }
        const response = await fetch(formularioUrl, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            let html = '';
            
            // Mostrar mensajes si existen
            if (data.mensajeCondicion) {
                html += data.mensajeCondicion;
            }
            if (data.mensajeCambioEfector) {
                html += data.mensajeCambioEfector;
            }
            
            // Mostrar formulario si está permitido
            if (data.mostrarFormulario && data.formularioHtml) {
                html += data.formularioHtml;
                
                // Almacenar id_configuracion en variable global para el chat inteligente
                if (data.id_configuracion) {
                    window.idConfiguracionActual = data.id_configuracion;
                }
            }
            
            container.innerHTML = html;
            
            // Re-inicializar eventos del formulario si está presente
            if (data.mostrarFormulario) {
                inicializarEventosFormulario();
            }
            
            // Ocultar loading después de cargar el formulario
            ocultarLoadingContainer();
        } else {
            tlMountAlert(container, 'danger', null, 'Error al cargar el formulario: ' + (data.error || 'Error desconocido'));
            // Ocultar loading incluso si hay error
            ocultarLoadingContainer();
        }
    } catch (error) {
        console.error('Error cargando estado del formulario:', error);
        tlMountAlert(container, 'danger', 'bi bi-exclamation-triangle', 'Error cargando el formulario de consulta');
        // Ocultar loading incluso si hay error
        ocultarLoadingContainer();
    }
}

// Función para inicializar eventos del formulario
function inicializarEventosFormulario() {
    console.log('Formulario cargado, inicializando eventos...');

    var form = document.getElementById('form-consulta-chat');
    if (form && window.EncounterCaptureForm) {
        window.encounterCaptureForm = window.EncounterCaptureForm.init(form);
    }
}

// Función de inicialización principal
function initTimeline(config) {
    console.log('Inicializando timeline con config:', config);
    
    // Verificar que la configuración sea válida
    if (!config || !config.pacienteId || !config.endpoints) {
        console.error('Configuración inválida para timeline:', config);
        return;
    }
    
    // Configurar variables globales
    window.timelineVars.pacienteId = config.pacienteId;
    window.timelineVars.endpoints = config.endpoints;
    
    console.log('Variables configuradas - pacienteId:', window.timelineVars.pacienteId, 'endpoints:', window.timelineVars.endpoints);
    
    // Limpiar event listeners anteriores si existen (para evitar duplicados en SPA)
    const modalVacunas = document.getElementById('modal-vacunas');
    const modalSignosVitales = document.getElementById('modal-signos-vitales');
    
    // Clonar y reemplazar modales para limpiar event listeners
    if (modalVacunas) {
        const newModalVacunas = modalVacunas.cloneNode(true);
        modalVacunas.parentNode.replaceChild(newModalVacunas, modalVacunas);
    }
    if (modalSignosVitales) {
        const newModalSignosVitales = modalSignosVitales.cloneNode(true);
        modalSignosVitales.parentNode.replaceChild(newModalSignosVitales, modalSignosVitales);
    }
    
    const loadingContainer = document.getElementById('loading-container');
    const curvasCrecimientoContent = document.getElementById('curvas-crecimiento-content');
    
    // Asegurarse de que el loading esté visible al iniciar
    mostrarLoadingContainer();
    
    // Iniciar carga automática
    loadAllContent();
    
    // Cargar última vacuna
    //loadUltimaVacuna();
    
    // Signos vitales: con historia-clinica se rellenan desde loadTimelineSummary en la vista; si no, endpoint dedicado.
    const endpointsCfg = config.endpoints || {};
    const signosDesdeHistoriaClinica = !!endpointsCfg.historiaClinica;
    const signosVitalesContent = document.getElementById('signos-vitales-actuales-content');
    if (signosVitalesContent) {
        console.log('Elemento signos-vitales-actuales-content encontrado, cargando...');
        tlMountSvLoading(signosVitalesContent);
        if (signosDesdeHistoriaClinica) {
            console.log('Signos vitales: pendiente de respuesta de historia-clínica');
        } else if (endpointsCfg.signosVitales) {
            loadSignosVitalesActuales();
        } else {
            tlMountMuted(signosVitalesContent, 'bi bi-info-circle', 'No hay fuente de signos vitales configurada');
        }
    } else {
        console.warn('Elemento signos-vitales-actuales-content no encontrado, reintentando en 500ms...');
        setTimeout(function() {
            const retryContent = document.getElementById('signos-vitales-actuales-content');
            if (retryContent) {
                console.log('Elemento encontrado en reintento, cargando signos vitales...');
                tlMountSvLoading(retryContent);
                const ep = (config.endpoints || {});
                if (ep.historiaClinica) {
                    /* se completa con historia-clínica */
                } else if (ep.signosVitales) {
                    loadSignosVitalesActuales();
                } else {
                    tlMountMuted(retryContent, 'bi bi-info-circle', 'No hay fuente de signos vitales configurada');
                }
            } else {
                console.error('Elemento signos-vitales-actuales-content no encontrado después del reintento');
            }
        }, 500);
    }
    
    // Formulario de captura: lo decide loadTimelineSummary según captura.permitida del API.
    // Si no hay endpoint de HC, intentar captura (flujos sin turno_id).
    if (!endpointsCfg.historiaClinica) {
        cargarFormularioConsulta();
    } 
    // Manejar modal de vacunas (después de clonar)
    const modalVacunasNew = document.getElementById('modal-vacunas');
    if (modalVacunasNew) {
        modalVacunasNew.addEventListener('show.bs.modal', function () {
            loadTodasLasVacunas();
        });
    }
    
    // Manejar modal de signos vitales (después de clonar)
    const modalSignosVitalesNew = document.getElementById('modal-signos-vitales');
    if (modalSignosVitalesNew) {
        modalSignosVitalesNew.addEventListener('show.bs.modal', function () {
            loadTodosLosSignosVitales();
        });
    }
}

// Exportar funciones para uso global (solo si no existe ya)
if (!window.TimelineJS) {
    window.TimelineJS = {
        init: initTimeline,
        //loadUltimaVacuna,
        loadTodasLasVacunas,
        loadSignosVitalesActuales,
        loadTodosLosSignosVitales,
        applySignosVitalesPayload,
        cargarFormularioConsulta,
        inicializarEventosFormulario
    };
}
})();
