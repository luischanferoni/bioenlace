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

// Función para cargar contenido desde endpoint
async function loadContent(url, containerId, title) {
    try {
        const response = await fetch(url);
        if (response.ok) {
            const html = await response.text();
            const container = document.getElementById(containerId);
            if (container) {
                container.innerHTML = `                        
                            <h6 class="mb-1 text-decoration-underline">${title}</h6>
                            ${html}
                `;
                container.style.display = 'block';
            }
        } else {
            console.error('Error cargando:', url, 'Status:', response.status);
            // Mostrar mensaje de error en el contenedor
            const container = document.getElementById(containerId);
            if (container) {
                container.innerHTML = `
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">${title}</h6>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                No se pudo cargar el contenido. Intente nuevamente.
                            </div>
                        </div>
                    </div>
                `;
                container.style.display = 'block';
            }
        }
    } catch (error) {
        console.error('Error en la petición:', error);
        // Mostrar mensaje de error en el contenedor
        const container = document.getElementById(containerId);
        if (container) {
            container.innerHTML = `
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">${title}</h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-danger">
                            <i class="bi bi-x-circle"></i>
                            Error de conexión. Verifique su conexión a internet.
                        </div>
                    </div>
                </div>
            `;
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
    modalContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div><p class="mt-2">Cargando historial de vacunas...</p></div>';
    
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
        modalContent.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> Error cargando el historial de vacunas</div>';
    }
}

function escapeHtmlSignos(text) {
    if (text === null || text === undefined || text === '') {
        return '';
    }
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

function tieneValorSigno(v) {
    return v !== null && v !== undefined && String(v).trim() !== '';
}

function buildSignosVitalesActualesHtml(ultimosSv) {
    if (!ultimosSv) {
        return '<div class="text-muted"><i class="bi bi-info-circle"></i> No se encontraron signos vitales registrados</div>';
    }
    const peso = ultimosSv.peso && tieneValorSigno(ultimosSv.peso.value);
    const talla = ultimosSv.talla && tieneValorSigno(ultimosSv.talla.value);
    const imc = ultimosSv.imc && tieneValorSigno(ultimosSv.imc.value);
    const ta = ultimosSv.ta && tieneValorSigno(ultimosSv.ta.sistolica) && tieneValorSigno(ultimosSv.ta.diastolica);
    if (!peso && !talla && !imc && !ta) {
        return '<div class="text-muted"><i class="bi bi-info-circle"></i> No se encontraron signos vitales registrados</div>';
    }
    const parts = [];
    parts.push('<div class="row g-3 mb-3">');
    if (peso) {
        parts.push(
            '<div class="col-md-3 col-sm-6"><div class="card h-100 border-0"><div class="card-body p-1">',
            '<h6 class="card-title mb-2 d-flex align-items-center"><i class="bi bi-speedometer2 text-primary me-2"></i><span>Peso</span></h6>',
            '<p class="card-text fw-bold mb-1 fs-6">', escapeHtmlSignos(ultimosSv.peso.value), ' kg</p>',
            '</div></div></div>'
        );
    }
    if (talla) {
        parts.push(
            '<div class="col-md-3 col-sm-6"><div class="card h-100 border-0"><div class="card-body p-1">',
            '<h6 class="card-title mb-2 d-flex align-items-center"><i class="bi bi-rulers text-success me-2"></i><span>Altura</span></h6>',
            '<p class="card-text fw-bold mb-1 fs-6">', escapeHtmlSignos(ultimosSv.talla.value), ' cm</p>',
            '</div></div></div>'
        );
    }
    if (imc) {
        parts.push(
            '<div class="col-md-3 col-sm-6"><div class="card h-100 border-0"><div class="card-body p-1">',
            '<h6 class="card-title mb-2 d-flex align-items-center"><i class="bi bi-graph-up text-info me-2"></i><span>IMC</span></h6>',
            '<p class="card-text fw-bold mb-1 fs-6">', escapeHtmlSignos(ultimosSv.imc.value), '</p>',
            '</div></div></div>'
        );
    }
    if (ta) {
        parts.push(
            '<div class="col-md-3 col-sm-6"><div class="card h-100 border-0"><div class="card-body p-1">',
            '<h6 class="card-title mb-2 d-flex align-items-center"><i class="bi bi-heart-pulse text-danger me-2"></i><span>Tensión Arterial</span></h6>',
            '<p class="card-text fw-bold mb-1 fs-6">',
            escapeHtmlSignos(ultimosSv.ta.sistolica), '/', escapeHtmlSignos(ultimosSv.ta.diastolica), ' mmHg</p>',
            '</div></div></div>'
        );
    }
    parts.push('</div>');
    return parts.join('');
}

function formatFilaSignosVitalesModal(row) {
    const fechaRaw = row.fecha_atencion != null && row.fecha_atencion !== '' ? row.fecha_atencion : row.fecha;
    let fechaCell = '-';
    if (fechaRaw) {
        const s = String(fechaRaw);
        if (/^\d{4}-\d{2}-\d{2}/.test(s) || s.indexOf('T') !== -1) {
            const d = new Date(s);
            fechaCell = !isNaN(d.getTime())
                ? d.toLocaleString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
                : escapeHtmlSignos(s);
        } else {
            fechaCell = escapeHtmlSignos(s);
        }
    }
    let pa = '-';
    if (row.ta1_sistolica != null && row.ta1_sistolica !== '' && row.ta1_diastolica != null && row.ta1_diastolica !== '') {
        pa = '<span class="badge bg-primary">' + escapeHtmlSignos(row.ta1_sistolica) + '/' + escapeHtmlSignos(row.ta1_diastolica) + ' mmHg</span>';
    } else if (row.ta && String(row.ta).indexOf('/') !== -1) {
        pa = '<span class="badge bg-primary">' + escapeHtmlSignos(row.ta) + ' mmHg</span>';
    }
    const fc = row.frecuencia_cardiaca != null && row.frecuencia_cardiaca !== ''
        ? '<span class="badge bg-info">' + escapeHtmlSignos(row.frecuencia_cardiaca) + ' lpm</span>'
        : (row.fc != null && row.fc !== '' ? '<span class="badge bg-info">' + escapeHtmlSignos(row.fc) + ' lpm</span>' : '<span class="text-muted">-</span>');
    const temp = row.temperatura != null && row.temperatura !== ''
        ? '<span class="badge bg-warning">' + escapeHtmlSignos(row.temperatura) + '°C</span>'
        : '<span class="text-muted">-</span>';
    const spo2 = row.saturacion_oxigeno != null && row.saturacion_oxigeno !== ''
        ? '<span class="badge bg-success">' + escapeHtmlSignos(row.saturacion_oxigeno) + '%</span>'
        : '<span class="text-muted">-</span>';
    const peso = row.peso != null && row.peso !== '' ? escapeHtmlSignos(row.peso) + ' kg' : '<span class="text-muted">-</span>';
    const alt = row.talla != null && row.talla !== '' ? escapeHtmlSignos(row.talla) + ' cm' : '<span class="text-muted">-</span>';
    const imc = row.imc != null && row.imc !== ''
        ? '<span class="badge bg-secondary">' + escapeHtmlSignos(Number(row.imc).toFixed(1)) + '</span>'
        : '<span class="text-muted">-</span>';
    return (
        '<tr><td>' + fechaCell + '</td><td>' + pa + '</td><td>' + fc + '</td><td>' + temp + '</td><td>' + spo2 + '</td><td>' + peso + '</td><td>' + alt + '</td><td>' + imc + '</td></tr>'
    );
}

function buildSignosVitalesModalHtml(datosSv) {
    if (!datosSv || !datosSv.length) {
        return (
            '<div class="signos-vitales-modal"><div class="card"><div class="card-header"><h5 class="card-title mb-0">' +
            '<i class="bi bi-heart-pulse"></i> Historial de Signos Vitales</h5></div><div class="card-body">' +
            '<div class="alert alert-info"><i class="bi bi-info-circle"></i> No se encontraron registros de signos vitales para este paciente.</div>' +
            '</div></div></div>'
        );
    }
    const rows = datosSv.map(formatFilaSignosVitalesModal).join('');
    return (
        '<div class="signos-vitales-modal"><div class="card"><div class="card-header"><h5 class="card-title mb-0">' +
        '<i class="bi bi-heart-pulse"></i> Historial de Signos Vitales</h5></div><div class="card-body">' +
        '<div class="table-responsive"><table class="table table-striped table-hover">' +
        '<thead class="table-dark"><tr><th>Fecha</th><th>Presión Arterial</th><th>Frecuencia Cardíaca</th><th>Temperatura</th><th>Saturación O₂</th><th>Peso</th><th>Altura</th><th>IMC</th></tr></thead>' +
        '<tbody>' + rows + '</tbody></table></div></div></div></div>'
    );
}

function getSignosVitalesFetchHeaders() {
    if (typeof window.getBioenlaceApiClientHeaders === 'function') {
        return window.getBioenlaceApiClientHeaders({ Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' });
    }
    return { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
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
        content.innerHTML = '<div class="text-muted"><i class="bi bi-exclamation-triangle"></i> Error: Endpoint no configurado</div>';
        return;
    }
    
    try {
        const url = endpoints.signosVitales;
        console.log('Cargando signos vitales desde:', url);
        
        const response = await fetch(url, {
            method: 'GET',
            headers: getSignosVitalesFetchHeaders(),
            credentials: 'same-origin'
        });
        
        console.log('Respuesta recibida:', response.status, response.statusText);
        
        if (response.ok) {
            const data = await response.json();
            console.log('Datos recibidos:', data);
            
            if (data && data.success && data.data) {
                const d = data.data;
                if (content) {
                    content.innerHTML = buildSignosVitalesActualesHtml(d.ultimos_sv);
                    console.log('Contenido de signos vitales actualizado');
                }
                
                // Actualizar título con fecha
                const titulo = document.getElementById('signos-vitales-titulo');
                if (titulo && d.fecha_titulo) {
                    titulo.textContent = 'SIGNOS VITALES ACTUALES (' + d.fecha_titulo + ')';
                }
                
                // Mostrar link si hay más signos vitales
                const link = document.getElementById('signos-vitales-link');
                if (link) {
                    link.style.display = d.tiene_mas_sv ? 'block' : 'none';
                }
            } else {
                if (content) {
                    content.innerHTML = '<div class="text-muted"><i class="bi bi-info-circle"></i> No se pudieron cargar los signos vitales</div>';
                }
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
            content.innerHTML = '<div class="text-muted"><i class="bi bi-exclamation-triangle"></i> Error cargando signos vitales: ' + error.message + '</div>';
        }
        // Ocultar loading incluso si hay error
        ocultarLoadingContainer();
    }
}

// Función para cargar todos los signos vitales en el modal (API v1 JSON)
async function loadTodosLosSignosVitales() {
    const modalContent = document.getElementById('modal-signos-vitales-content');
    modalContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div><p class="mt-2">Cargando historial de signos vitales...</p></div>';
    
    try {
        const endpoints = getEndpoints();
        const url = endpoints.signosVitales;
        const response = await fetch(url, {
            method: 'GET',
            headers: getSignosVitalesFetchHeaders(),
            credentials: 'same-origin'
        });
        
        if (response.ok) {
            const data = await response.json();
            if (data && data.success && data.data && Array.isArray(data.data.datos_sv)) {
                modalContent.innerHTML = buildSignosVitalesModalHtml(data.data.datos_sv);
            } else {
                modalContent.innerHTML = '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> No se pudo interpretar la respuesta del servidor.</div>';
            }
        } else {
            throw new Error('Error en la respuesta');
        }
    } catch (error) {
        console.error('Error cargando todos los signos vitales:', error);
        modalContent.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> Error cargando el historial de signos vitales</div>';
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
            container.innerHTML = '<div class="alert alert-danger">Error al cargar el formulario: ' + (data.error || 'Error desconocido') + '</div>';
            // Ocultar loading incluso si hay error
            ocultarLoadingContainer();
        }
    } catch (error) {
        console.error('Error cargando estado del formulario:', error);
        container.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> Error cargando el formulario de consulta</div>';
        // Ocultar loading incluso si hay error
        ocultarLoadingContainer();
    }
}

// Función para inicializar eventos del formulario
function inicializarEventosFormulario() {
    console.log('Formulario cargado, inicializando eventos...');
    
    // Verificar si el chat inteligente está disponible y inicializarlo
    if (window.chatInteligente) {
        console.log('Chat inteligente ya inicializado');
    } else {
        // Si no está disponible, esperar un poco y verificar nuevamente
        setTimeout(() => {
            if (window.chatInteligente) {
                console.log('Chat inteligente inicializado después del timeout');
            } else {
                console.warn('Chat inteligente no está disponible');
            }
        }, 100);
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
    
    // Cargar signos vitales actuales (verificar que el elemento existe)
    const signosVitalesContent = document.getElementById('signos-vitales-actuales-content');
    if (signosVitalesContent) {
        console.log('Elemento signos-vitales-actuales-content encontrado, cargando...');
        // Resetear el contenido a loading antes de cargar
        signosVitalesContent.innerHTML = '<div class="text-center py-2"><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Cargando...</span></div><span class="ms-2 text-muted">Cargando signos vitales...</span></div>';
        loadSignosVitalesActuales();
    } else {
        console.warn('Elemento signos-vitales-actuales-content no encontrado, reintentando en 500ms...');
        setTimeout(function() {
            const retryContent = document.getElementById('signos-vitales-actuales-content');
            if (retryContent) {
                console.log('Elemento encontrado en reintento, cargando signos vitales...');
                retryContent.innerHTML = '<div class="text-center py-2"><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Cargando...</span></div><span class="ms-2 text-muted">Cargando signos vitales...</span></div>';
                loadSignosVitalesActuales();
            } else {
                console.error('Elemento signos-vitales-actuales-content no encontrado después del reintento');
            }
        }, 500);
    }
    
    // Cargar formulario
    cargarFormularioConsulta();
    
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
        cargarFormularioConsulta,
        inicializarEventosFormulario
    };
}
})();
