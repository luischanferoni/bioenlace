/**
 * Chat Inteligente para Asistente Médico
 * Versión simplificada - Solo análisis de consulta con endpoint
 */
(function() {
    'use strict';
    
    // Solo declarar la clase si no existe ya
    if (window.ChatInteligente) {
        // Ya está declarada, solo inicializar si es necesario
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                if (!window.chatInteligente) {
                    window.chatInteligente = new window.ChatInteligente();
                }
            });
        } else {
            if (!window.chatInteligente) {
                window.chatInteligente = new window.ChatInteligente();
            }
        }
        return; // Ya está declarada, no hacer nada más
    }
    
    class ChatInteligente {
        constructor() {
            this.isAnalyzing = false;
            this.init();
        }

    init() {
        this.bindEvents();
    }

    bindEvents() {
        // Usar event delegation para capturar eventos en elementos que se crean dinámicamente
        document.addEventListener('click', (e) => {
            // Analizar consulta
            if (e.target && e.target.id === 'analyze-consultation') {
                this.analyzeConsultation();
            }
        });
    }

    async analyzeConsultation() {
        const input = document.getElementById('chat-input');
        const consultationText = input.value.trim();
        
        if (!consultationText) {
            this.showAlert('Por favor, escriba los detalles de la consulta antes de analizar.', 'warning');
            return;
        }

        if (this.isAnalyzing) {
            this.showAlert('Ya se está analizando una consulta. Espere por favor.', 'info');
            return;
        }

        this.isAnalyzing = true;
        this.showAnalyzingState();

        try {
            // Llamada real al endpoint
            const response = await this.callAnalyzeEndpoint(consultationText);
            this.processAnalysisResponse(response);
        } catch (error) {
            console.error('Error al analizar consulta:', error);
            
            // Determinar mensaje de error según el código de estado HTTP
            let errorMessage = 'Error al analizar la consulta. Intente nuevamente.';
            let errorType = 'danger';
            
            if (error.status) {
                switch (error.status) {
                    case 401:
                        errorMessage = 'Su sesión ha expirado. Por favor, inicie sesión nuevamente.';
                        errorType = 'warning';
                        // Opcional: redirigir al login después de unos segundos
                        setTimeout(() => {
                            if (window.location.pathname !== '/site/login') {
                                window.location.href = '/site/login';
                            }
                        }, 3000);
                        break;
                    case 400:
                        errorMessage = error.message || 'Los datos enviados no son válidos. Por favor, verifique la información e intente nuevamente.';
                        errorType = 'warning';
                        break;
                    case 500:
                        errorMessage = error.message || 'Ocurrió un error en el servidor. Por favor, intente nuevamente en unos momentos. Si el problema persiste, contacte al soporte técnico.';
                        errorType = 'danger';
                        break;
                    case 0:
                        errorMessage = 'Error de conexión. Verifique su conexión a internet e intente nuevamente.';
                        errorType = 'warning';
                        break;
                    default:
                        errorMessage = error.message || `Error ${error.status}: ${errorMessage}`;
                }
            } else if (error.message) {
                errorMessage = error.message;
            }
            
            this.showAlert(errorMessage, errorType);
            
            // Limpiar el contenido de respuesta en caso de error
            const responseContent = document.getElementById('response-content');
            if (responseContent) {
                responseContent.innerHTML = '';
            }
        } finally {
            // Siempre restaurar el estado del botón y quitar el loading
            this.isAnalyzing = false;
            this.resetAnalyzeButton();
            
            // Ocultar spinner de carga si existe
            const responseContent = document.getElementById('response-content');
            if (responseContent && responseContent.querySelector('.spinner-border')) {
                responseContent.innerHTML = '';
            }
        }
    }

    showAnalyzingState() {
        const analyzeBtn = document.getElementById('analyze-consultation');
        this.originalButtonText = analyzeBtn.innerHTML;
        
        analyzeBtn.disabled = true;
        analyzeBtn.innerHTML = '<i class="bi bi-hourglass-split"></i>&nbsp;&nbsp;Analizando...';
        
        // Mostrar indicador de carga
        this.showAgentResponse();
        const responseContent = document.getElementById('response-content');
        if (responseContent) {
        responseContent.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Analizando...</span>
                </div>
                <p class="mt-2 text-muted">El asistente está analizando la consulta...</p>
            </div>
        `;
    }
    }

    resetAnalyzeButton() {
        const analyzeBtn = document.getElementById('analyze-consultation');
        if (analyzeBtn) {
            analyzeBtn.disabled = false;
            analyzeBtn.innerHTML = this.originalButtonText || '<i class="bi bi-search"></i>&nbsp;&nbsp;Analizar Consulta';
        }
    }

    async callAnalyzeEndpoint(consultationText) {
        // Obtener baseUrl desde window.spaConfig o usar URL relativa como fallback
        const baseUrl = (window.spaConfig && window.spaConfig.baseUrl) 
            ? window.spaConfig.baseUrl 
            : window.location.origin;
        const url = `${baseUrl}/api/v1/consulta/analizar`;
        
        // Usar el wrapper para enviar datos con userPerTabConfig
        const data = {
            consulta: consultationText
        };
        
        // Agregar id_configuracion si está disponible
        if (window.idConfiguracionActual) {
            data.id_configuracion = window.idConfiguracionActual;
        }
        
        // Generar tabId único para esta pestaña
        if (!data.tab_id) {
            data.tab_id = 'tab_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }
        
        try {
            // Usar VitaMindAjax.fetchPost que automáticamente añade userPerTabConfig
            const response = await window.VitaMindAjax.fetchPost(url, data);

            // Manejar diferentes códigos de estado HTTP
            if (!response.ok) {
                let errorMessage = 'Ocurrió un error al procesar la consulta.';
                let errorData = null;
                
                // Intentar obtener el mensaje de error del cuerpo de la respuesta
                try {
                    errorData = await response.json();
                    if (errorData && errorData.message) {
                        errorMessage = errorData.message;
                    }
                } catch (e) {
                    // Si no se puede parsear JSON, usar mensaje por defecto según código de estado
                }
                
                // Crear error con información del código de estado y mensaje
                const error = new Error(errorMessage);
                error.status = response.status;
                error.data = errorData;
                throw error;
            }

            return await response.json();
        } catch (error) {
            // Si es un error de red o timeout
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                const networkError = new Error('Error de conexión. Verifique su conexión a internet e intente nuevamente.');
                networkError.status = 0;
                throw networkError;
            }
            // Re-lanzar el error para que sea manejado por analyzeConsultation
            throw error;
        }
    }

    processAnalysisResponse(response) {
        if (response.success) {
            // Mostrar siempre el texto procesado si está disponible
            this.mostrarTextoProcesado(response.texto_procesado, response.texto_original);
            
            // Las correcciones se procesan automáticamente por el backend
            // Ya no se requiere validación manual del médico
            
            this.showAnalysisResults(response);
        } else {
            this.showAlert(response.msj || 'Error en el análisis', 'danger');
        }
        
        // Habilitar el botón nuevamente después de procesar la respuesta
        this.resetAnalyzeButton();
    }
    
    /**
     * Mostrar el texto procesado debajo del textarea
     * @param {string} textoProcesado Texto procesado por SymSpell/LLM
     * @param {string} textoOriginal Texto original del usuario
     */
    mostrarTextoProcesado(textoProcesado, textoOriginal) {
        const container = document.getElementById('texto-procesado-container');
        const content = document.getElementById('texto-procesado-content');
        
        if (!container || !content) {
            console.warn('No se encontró el contenedor para texto procesado');
            return;
        }
        
        // Si hay texto procesado y es diferente al original, mostrarlo
        if (textoProcesado && textoProcesado.trim()) {
            // Mostrar el contenedor
            container.style.display = 'block';
            
            // Mostrar el texto procesado
            content.textContent = textoProcesado;
            
            // Si el texto procesado es diferente al original, resaltarlo
            if (textoOriginal && textoProcesado !== textoOriginal) {
                content.classList.remove('text-muted');
                content.classList.add('text-success');
            } else {
                content.classList.remove('text-success');
                content.classList.add('text-muted');
            }
        } else {
            // Ocultar el contenedor si no hay texto procesado
            container.style.display = 'none';
        }
    }

    showAnalysisResults(response) {
        const responseContent = document.getElementById('response-content');
        if (responseContent) {
            let tieneDatosFaltantes = false;
            
            // Obtener datos faltantes desde la respuesta del backend
            if (response.tiene_datos_faltantes !== undefined) {
                tieneDatosFaltantes = response.tiene_datos_faltantes;
            }
            
            // Procesar la respuesta de la IA si está disponible (fallback)
            if (response.datos && response.datos.informacionFaltante) {
                const procesamiento = this.procesarInformacionIA(response.datos);
                // Solo usar como fallback si no se recibió desde el backend
                if (response.tiene_datos_faltantes === undefined) {
                    tieneDatosFaltantes = procesamiento.tieneDatosFaltantes;
                }
            }
            
            // Usar el HTML generado desde PHP (ya incluye sugerencias)
            let html = response.html || '<p class="text-muted">No se pudo generar el análisis</p>';
            
            // Agregar información de correcciones si están disponibles
            if (response.correcciones && response.correcciones.total_cambios > 0) {
                console.log('Datos de correcciones recibidos:', response.correcciones);
                console.log('Texto original:', response.texto_original);
                console.log('Texto procesado:', response.texto_procesado);
                html = this.agregarInfoCorrecciones(html, response.correcciones, response.texto_original, response.texto_procesado);
            }
            
            responseContent.innerHTML = html;
            
            // El resaltado de correcciones ya no es necesario
            
            // Inicializar los hidden inputs y eventos de sugerencias
            this.initializeSugerencias();
            
            // Verificar si se puede habilitar el botón de confirmar consulta
            this.verificarHabilitarConfirmacion(tieneDatosFaltantes, false);
        }
    }

    initializeSugerencias() {
        // Crear hidden inputs para cada categoría
        this.createHiddenInputs();
        
        // Agregar event listeners a los botones de sugerencias
        document.querySelectorAll('.sugerencia-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.toggleSugerencia(e.target);
            });
        });
    }

    createHiddenInputs() {
        const responseContent = document.getElementById('response-content');
        if (!responseContent) return;

        // Crear contenedor para hidden inputs si no existe
        let hiddenContainer = responseContent.querySelector('.sugerencias-hidden-inputs');
        if (!hiddenContainer) {
            hiddenContainer = document.createElement('div');
            hiddenContainer.className = 'sugerencias-hidden-inputs';
            hiddenContainer.style.display = 'none';
            responseContent.appendChild(hiddenContainer);
        }

        // Crear hidden inputs para cada categoría
        const categorias = ['diagnostico', 'practicas', 'seguimiento', 'alertas'];
        categorias.forEach(categoria => {
            let input = hiddenContainer.querySelector(`input[name="sugerencias_${categoria}"]`);
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = `sugerencias_${categoria}`;
                input.value = '';
                hiddenContainer.appendChild(input);
            }
        });
    }

    toggleSugerencia(button) {
        const categoria = button.getAttribute('data-categoria');
        const valor = button.getAttribute('data-valor');
        
        // Toggle visual del botón
        if (button.classList.contains('btn-outline-dark')) {
            button.classList.remove('btn-outline-dark');
            button.classList.add('btn-info');
            button.querySelector('i').className = 'bi bi-check-circle';
        } else if (button.classList.contains('btn-outline-danger')) {
            button.classList.remove('btn-outline-danger');
            button.classList.add('btn-danger');
            button.querySelector('i').className = 'bi bi-check-circle';
        } else {
            // Deseleccionar
            if (button.classList.contains('btn-info')) {
                button.classList.remove('btn-info');
                button.classList.add('btn-outline-dark');
                button.querySelector('i').className = 'bi bi-plus-circle';
            } else if (button.classList.contains('btn-danger')) {
                button.classList.remove('btn-danger');
                button.classList.add('btn-outline-danger');
                button.querySelector('i').className = 'bi bi-exclamation-triangle';
            }
        }
        
        // Actualizar hidden input
        this.updateHiddenInput(categoria);
    }

    updateHiddenInput(categoria) {
        const input = document.querySelector(`input[name="sugerencias_${categoria}"]`);
        if (!input) return;

        // Obtener todos los botones seleccionados de esta categoría
        const selectedButtons = document.querySelectorAll(`.sugerencia-btn[data-categoria="${categoria}"]`);
        const selectedValues = [];

        selectedButtons.forEach(btn => {
            if (btn.classList.contains('btn-warning') || btn.classList.contains('btn-danger')) {
                selectedValues.push(btn.getAttribute('data-valor'));
            }
        });

        // Actualizar el valor del hidden input
        input.value = JSON.stringify(selectedValues);
    }

    showAgentResponse() {
        const agentResponse = document.getElementById('agent-response');
        if (agentResponse) {
            agentResponse.style.display = 'block';
        }
    }

    showAlert(message, type = 'info') {
        // Crear alerta temporal
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        // Auto-remover después de 5 segundos
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }

    getNombreServicioActual() {
        // Obtener el servicio actual desde las variables globales definidas en main.php
        return nombreServicioActual || null;
    }

    getIdServicioActual() {
        // Obtener el servicio actual desde las variables globales definidas en main.php
        return idServicioActual || null;
    }

    showConfirmButton() {
        const confirmSection = document.getElementById('confirm-section');
        const confirmButton = document.getElementById('send-message');
        
        if (confirmSection) {
            confirmSection.style.display = 'block';
        }
        
        if (confirmButton) {
            confirmButton.disabled = false;
        }
    }

    /**
     * Verificar si se puede habilitar el botón de confirmar consulta
     * @param {boolean} tieneDatosFaltantes - Si faltan datos requeridos
     * @param {boolean} requiereValidacion - Si requiere validación de correcciones
     */
    verificarHabilitarConfirmacion(tieneDatosFaltantes, requiereValidacion = false) {
        const confirmSection = document.getElementById('confirm-section');
        const confirmButton = document.getElementById('send-message');
        
        if (confirmSection) {
            confirmSection.style.display = 'block';
        }
        
        if (confirmButton) {
            if (tieneDatosFaltantes || requiereValidacion) {
                // Deshabilitar botón si hay datos faltantes o requiere validación
                confirmButton.disabled = true;
                confirmButton.innerHTML = '<i class="bi bi-exclamation-triangle"></i>&nbsp;&nbsp;Revisar información antes de confirmar';
                confirmButton.className = 'btn btn-warning';
                
                // Mostrar mensaje explicativo
                this.mostrarMensajeConfirmacion(tieneDatosFaltantes, requiereValidacion);
            } else {
                // Habilitar botón si no hay datos faltantes ni requiere validación
                confirmButton.disabled = false;
                confirmButton.innerHTML = '<i class="bi bi-check-circle"></i>&nbsp;&nbsp;Confirmar Consulta';
                confirmButton.className = 'btn btn-primary';
                
                // Ocultar mensaje explicativo si existe
                this.ocultarMensajeConfirmacion();
            }
        }
    }

    /**
     * Mostrar mensaje explicativo sobre por qué no se puede confirmar
     * @param {boolean} tieneDatosFaltantes - Si faltan datos
     * @param {boolean} requiereValidacion - Si requiere validación de correcciones
     */
    mostrarMensajeConfirmacion(tieneDatosFaltantes, requiereValidacion = false) {
        const confirmSection = document.getElementById('confirm-section');
        if (!confirmSection) return;

        // Remover mensaje anterior si existe
        this.ocultarMensajeConfirmacion();

        let mensaje = '';
        const problemas = [];
        
        if (tieneDatosFaltantes) {
            problemas.push('faltan datos requeridos');
        }
        if (requiereValidacion) {
            problemas.push('se requiere validación de correcciones de texto');
        }

        if (problemas.length > 0) {
            if (problemas.length === 1) {
                mensaje = `No se puede confirmar la consulta: ${problemas[0]}.`;
            } else if (problemas.length === 2) {
                mensaje = `No se puede confirmar la consulta: ${problemas[0]} y ${problemas[1]}.`;
            } else {
                mensaje = `No se puede confirmar la consulta: ${problemas.slice(0, -1).join(', ')} y ${problemas[problemas.length - 1]}.`;
            }
        }

        if (mensaje) {
            const mensajeDiv = document.createElement('div');
            mensajeDiv.className = 'alert alert-warning mt-2 mb-0';
            mensajeDiv.id = 'mensaje-confirmacion';
            mensajeDiv.innerHTML = `<small><i class="bi bi-info-circle"></i> ${mensaje}</small>`;
            confirmSection.appendChild(mensajeDiv);
        }
    }

    /**
     * Ocultar mensaje explicativo
     */
    ocultarMensajeConfirmacion() {
        const mensajeDiv = document.getElementById('mensaje-confirmacion');
        if (mensajeDiv) {
            mensajeDiv.remove();
        }
    }

    /**
     * Procesar la información de la IA y mostrar alertas/notificaciones
     * @param {Object} datos - Datos procesados de la IA
     * @returns {Object} - Información sobre datos faltantes
     */
    procesarInformacionIA(datos) {
        console.log('Procesando información de IA:', datos);
        
        let tieneDatosFaltantes = false;
               
        this.mostrarDatosCompletos(datos.datosCompletos);
        
        return {
            tieneDatosFaltantes: tieneDatosFaltantes
        };
    }

    /**
     * Mostrar datos completos extraídos por la IA
     * @param {Object} datosCompletos - Datos extraídos por la IA
     */
    mostrarDatosCompletos(datosCompletos) {
        const responseContent = document.getElementById('response-content');
        if (!responseContent) return;

        // Crear sección de datos completos
        let datosSection = responseContent.querySelector('.datos-completos');
        if (!datosSection) {
            datosSection = document.createElement('div');
            datosSection.className = 'datos-completos alert alert-info mt-3';
            responseContent.appendChild(datosSection);
        }

        let html = '<h6 class="border-bottom border-2 border-info pb-2 text-info">Datos Extraídos por IA</h6>';
        
        Object.keys(datosCompletos).forEach(modelo => {
            html += `<div class="mb-2"><strong>${modelo}:</strong><ul>`;
            Object.keys(datosCompletos[modelo]).forEach(campo => {
                html += `<li><strong>${campo}:</strong> ${datosCompletos[modelo][campo]}</li>`;
            });
            html += '</ul></div>';
        });

        datosSection.innerHTML = html;
    }

    /**
     * Obtener clase CSS para el badge de completitud
     * @param {string} completitud - Porcentaje de completitud
     * @returns {string} - Clase CSS
     */
    getCompletitudBadgeClass(completitud) {
        const porcentaje = parseInt(completitud) || 0;
        if (porcentaje >= 80) return 'bg-success';
        if (porcentaje >= 60) return 'bg-warning';
        return 'bg-danger';
    }

    /**
     * Agregar texto formateado con palabras modificadas subrayadas
     * @param {string} html - HTML original
     * @param {Object} correcciones - Datos de correcciones
     * @param {string} textoOriginal - Texto original del usuario
     * @param {string} textoProcesado - Texto procesado con correcciones
     * @returns {string} - HTML con texto formateado
     */
    agregarInfoCorrecciones(html, correcciones, textoOriginal, textoProcesado) {
        // Validar parámetros de entrada
        if (!correcciones || 
            !correcciones.total_cambios || 
            correcciones.total_cambios === 0) {
            return html;
        }

        // Validar que tengamos texto procesado
        if (!textoProcesado || typeof textoProcesado !== 'string') {
            console.warn('Texto procesado no disponible, usando texto original');
            textoProcesado = textoOriginal || '';
        }

        try {
            // Generar texto formateado con palabras modificadas subrayadas
            const textoFormateado = this.generarTextoFormateado(textoOriginal, textoProcesado, correcciones);

            const correccionesHtml = `
                <div class="alert alert-light border mt-3">
                    <h6><i class="bi bi-file-text me-2"></i>Texto Formateado</h6>
                    <div class="mt-3">
                        <div class="bg-light p-3 rounded border">
                            <div class="texto-formateado">
                                ${textoFormateado}
                            </div>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Las palabras subrayadas en verde han sido corregidas automáticamente
                        </small>
                    </div>
                </div>
            `;

            return correccionesHtml + html;
        } catch (error) {
            console.error('Error generando texto formateado:', error);
            return html;
        }
    }

    /**
     * Generar texto formateado con palabras modificadas subrayadas
     * @param {string} textoOriginal - Texto original
     * @param {string} textoProcesado - Texto procesado
     * @param {Object} correcciones - Datos de correcciones
     * @returns {string} - HTML del texto formateado
     */
    generarTextoFormateado(textoOriginal, textoProcesado, correcciones) {
        // Validar parámetros de entrada
        if (!textoProcesado || typeof textoProcesado !== 'string') {
            console.warn('Texto procesado no válido:', textoProcesado);
            return textoOriginal || '';
        }

        // Escapar HTML y preservar saltos de línea
        let textoFormateado = textoProcesado
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\n/g, '<br>');

        // Aplicar subrayado amarillo a las palabras modificadas
        if (correcciones && 
            correcciones.cambios_automaticos && 
            Array.isArray(correcciones.cambios_automaticos) && 
            correcciones.cambios_automaticos.length > 0) {
            
            correcciones.cambios_automaticos.forEach(cambio => {
                // Validar que el cambio tenga las propiedades necesarias
                if (cambio && 
                    typeof cambio.original === 'string' && 
                    typeof cambio.corrected === 'string' && 
                    cambio.original !== cambio.corrected) {
                    
                    try {
                        // Escapar caracteres especiales para regex
                        const palabraEscapada = cambio.corrected.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                        // Crear regex para encontrar la palabra corregida (solo palabras completas)
                        const regex = new RegExp(`\\b${palabraEscapada}\\b`, 'gi');
                        
                        textoFormateado = textoFormateado.replace(regex, (match) => {
                            return `<span class="palabra-modificada" title="Corregido de '${cambio.original}' a '${cambio.corrected}'">${match}</span>`;
                        });
                    } catch (error) {
                        console.warn('Error procesando cambio:', cambio, error);
                    }
                }
            });
        }

        return textoFormateado;
    }
    } // Cierre de la clase ChatInteligente
    
    // Exponer la clase globalmente
    var ChatInteligenteClass = ChatInteligente;
    window.ChatInteligente = ChatInteligenteClass;
    
    // Inicializar cuando el DOM esté listo (solo si no está ya inicializado)
    function initializeChat() {
        if (!window.chatInteligente) {
            window.chatInteligente = new ChatInteligenteClass();
        }
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeChat);
    } else {
        initializeChat();
    }
})();
