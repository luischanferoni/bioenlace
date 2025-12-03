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

// Función para cargar los signos vitales actuales
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
        const url = `${endpoints.signosVitales}&actuales=1`;
        console.log('Cargando signos vitales desde:', url);
        
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });
        
        console.log('Respuesta recibida:', response.status, response.statusText);
        
        if (response.ok) {
            const data = await response.json();
            console.log('Datos recibidos:', data);
            
            if (data && data.success) {
                if (content) {
                    content.innerHTML = data.html || '<div class="text-muted"><i class="bi bi-info-circle"></i> No hay datos disponibles</div>';
                    console.log('Contenido de signos vitales actualizado');
                }
                
                // Actualizar título con fecha
                const titulo = document.getElementById('signos-vitales-titulo');
                if (titulo && data.fecha_titulo) {
                    titulo.textContent = `SIGNOS VITALES ACTUALES (${data.fecha_titulo})`;
                }
                
                // Mostrar link si hay más signos vitales
                const link = document.getElementById('signos-vitales-link');
                if (link && data.tiene_mas_sv) {
                    link.style.display = 'block';
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

// Función para cargar todos los signos vitales en el modal
async function loadTodosLosSignosVitales() {
    const modalContent = document.getElementById('modal-signos-vitales-content');
    modalContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div><p class="mt-2">Cargando historial de signos vitales...</p></div>';
    
    try {
        const endpoints = getEndpoints();
        const url = `${endpoints.signosVitales}&modal=1`;
        const response = await fetch(url);
        
        if (response.ok) {
            const html = await response.text();
            modalContent.innerHTML = html;
        } else {
            throw new Error('Error en la respuesta');
        }
    } catch (error) {
        console.error('Error cargando todos los signos vitales:', error);
        modalContent.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> Error cargando el historial de signos vitales</div>';
    }
}

// Función para cargar el estado del formulario via AJAX
async function cargarFormularioConsulta() {
    const container = document.getElementById('formulario-container');
    
    try {
        const endpoints = getEndpoints();
        const response = await fetch(`${endpoints.formularioConsulta}`, {
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
