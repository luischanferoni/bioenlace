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
            this.lastAnalysisResponse = null; // Guardar la última respuesta del análisis
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
            // Confirmar consulta
            if (e.target && e.target.id === 'send-message') {
                e.preventDefault();
                this.confirmarConsulta();
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
            // Las correcciones se procesan automáticamente por el backend
            // Ya no se requiere validación manual del médico
            
            this.showAnalysisResults(response);
        } else {
            this.showAlert(response.msj || 'Error en el análisis', 'danger');
        }
        
        // Habilitar el botón nuevamente después de procesar la respuesta
        this.resetAnalyzeButton();
    }

    showAnalysisResults(response) {
        const responseContent = document.getElementById('response-content');
        if (responseContent) {
            // Guardar la respuesta del análisis para usarla al confirmar
            this.lastAnalysisResponse = response;
            
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
            
            // Usar el HTML generado desde PHP (ya incluye sugerencias y texto formateado con subrayado)
            let html = response.html || '<p class="text-muted">No se pudo generar el análisis</p>';
            
            responseContent.innerHTML = html;
            
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
            // Los botones seleccionados tienen btn-info o btn-danger (no btn-outline-*)
            if (btn.classList.contains('btn-info') || btn.classList.contains('btn-danger')) {
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
     * Confirmar consulta - Guarda la consulta y cierra el modal solo si fue exitoso
     */
    async confirmarConsulta() {
        const confirmButton = document.getElementById('send-message');
        
        // Verificar que el botón no esté deshabilitado
        if (confirmButton && confirmButton.disabled) {
            this.showAlert('No se puede confirmar la consulta. Revise la información antes de continuar.', 'warning');
            return;
        }

        // Verificar que haya una respuesta de análisis previa
        if (!this.lastAnalysisResponse) {
            this.showAlert('Debe analizar la consulta antes de confirmarla.', 'warning');
            return;
        }

        // Deshabilitar botón mientras se procesa
        if (confirmButton) {
            confirmButton.disabled = true;
            const originalText = confirmButton.innerHTML;
            confirmButton.innerHTML = '<i class="bi bi-hourglass-split"></i>&nbsp;&nbsp;Guardando...';
        }

        try {
            // Buscar el formulario de consulta en el modal
            const modalConsulta = document.getElementById('modal-consulta');
            if (!modalConsulta) {
                throw new Error('No se encontró el modal de consulta.');
            }

            // Buscar el formulario directamente por su ID
            const form = document.getElementById('form-consulta-chat');
            if (!form) {
                throw new Error('No se encontró el formulario de la consulta (ID: form-consulta-chat). Asegúrese de que el formulario esté cargado correctamente.');
            }

            // Buscar el campo de detalle directamente por su ID
            const detalleField = document.getElementById('chat-input');
            if (!detalleField) {
                throw new Error('No se encontró el campo de detalle de la consulta (ID: chat-input). Asegúrese de que el formulario esté cargado correctamente.');
            }

            // NO reemplazar el contenido del textarea - mantener lo que el usuario escribió
            // Pero sí agregar campos hidden con ambos textos para guardarlos en BD
            
            // Obtener textos original y procesado de la respuesta
            const textoOriginal = this.lastAnalysisResponse.texto_original || detalleField.value || '';
            const textoProcesado = this.lastAnalysisResponse.texto_procesado || textoOriginal || '';
            
            if (!textoOriginal && !textoProcesado) {
                throw new Error('No hay texto de consulta para guardar.');
            }
            
            // Crear o actualizar campos hidden para texto original y procesado
            let hiddenOriginal = form.querySelector('input[name="texto_original"]');
            if (!hiddenOriginal) {
                hiddenOriginal = document.createElement('input');
                hiddenOriginal.type = 'hidden';
                hiddenOriginal.name = 'texto_original';
                form.appendChild(hiddenOriginal);
            }
            hiddenOriginal.value = textoOriginal;
            
            let hiddenProcesado = form.querySelector('input[name="texto_procesado"]');
            if (!hiddenProcesado) {
                hiddenProcesado = document.createElement('input');
                hiddenProcesado.type = 'hidden';
                hiddenProcesado.name = 'texto_procesado';
                form.appendChild(hiddenProcesado);
            }
            hiddenProcesado.value = textoProcesado;

            // Reestructurar datosExtraidos usando nombres de modelos como claves
            // Esto hace más fiable la asociación con los modelos al guardar
            if (this.lastAnalysisResponse.datos && this.lastAnalysisResponse.datos.datosExtraidos) {
                const datosExtraidosOriginales = this.lastAnalysisResponse.datos.datosExtraidos;
                const categorias = this.lastAnalysisResponse.categorias || [];
                
                // Crear mapa de título a nombre de modelo
                const mapaTituloAModelo = {};
                categorias.forEach(categoria => {
                    if (categoria.titulo && categoria.modelo) {
                        mapaTituloAModelo[categoria.titulo] = categoria.modelo;
                    }
                });
                
                // Reestructurar datosExtraidos usando nombres de modelos como claves
                const datosExtraidosPorModelo = {};
                Object.keys(datosExtraidosOriginales).forEach(titulo => {
                    const nombreModelo = mapaTituloAModelo[titulo];
                    if (nombreModelo) {
                        // Usar nombre del modelo como clave
                        datosExtraidosPorModelo[nombreModelo] = datosExtraidosOriginales[titulo];
                    } else {
                        // Si no hay mapeo, mantener el título original como fallback
                        datosExtraidosPorModelo[titulo] = datosExtraidosOriginales[titulo];
                    }
                });
                
                // Agregar campo hidden con datosExtraidos reestructurados
                let hiddenDatosExtraidos = form.querySelector('input[name="datosExtraidos"]');
                if (!hiddenDatosExtraidos) {
                    hiddenDatosExtraidos = document.createElement('input');
                    hiddenDatosExtraidos.type = 'hidden';
                    hiddenDatosExtraidos.name = 'datosExtraidos';
                    form.appendChild(hiddenDatosExtraidos);
                }
                hiddenDatosExtraidos.value = JSON.stringify(datosExtraidosPorModelo);
            }

            // Agregar id_configuracion al formulario si no está presente
            // Se obtiene de window.idConfiguracionActual (establecido cuando se carga el formulario)
            let hiddenIdConfiguracion = form.querySelector('input[name="id_configuracion"]');
            if (!hiddenIdConfiguracion) {
                hiddenIdConfiguracion = document.createElement('input');
                hiddenIdConfiguracion.type = 'hidden';
                hiddenIdConfiguracion.name = 'id_configuracion';
                form.appendChild(hiddenIdConfiguracion);
            }
            // Obtener id_configuracion de window.idConfiguracionActual
            if (!hiddenIdConfiguracion.value && window.idConfiguracionActual) {
                hiddenIdConfiguracion.value = window.idConfiguracionActual;
            }

            // Obtener sugerencias seleccionadas y guardarlas si hay campos para ellas
            const sugerencias = this.obtenerSugerenciasSeleccionadas();
            
            // Buscar y llenar campos de sugerencias si existen
            Object.keys(sugerencias).forEach(categoria => {
                const sugerenciasArray = sugerencias[categoria];
                if (sugerenciasArray && sugerenciasArray.length > 0) {
                    // Buscar campo hidden para esta categoría
                    const hiddenInput = form.querySelector(`input[name="sugerencias_${categoria}"]`);
                    if (hiddenInput) {
                        hiddenInput.value = JSON.stringify(sugerenciasArray);
                    }
                }
            });

            // Enviar el formulario usando fetch para manejar la respuesta
            const formData = new FormData(form);
            const formAction = form.action
            const formMethod = form.method;

            const response = await fetch(formAction, {
                method: formMethod,
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            let result;
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                result = await response.json();
            } else {
                const text = await response.text();
                // Intentar parsear como JSON si es posible
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    // Si no es JSON, asumir que es HTML (éxito) o error
                    if (response.ok) {
                        result = { success: true, msg: 'Consulta guardada correctamente' };
                    } else {
                        result = { success: false, msg: 'Error al guardar la consulta' };
                    }
                }
            }

            if (result.success !== false && response.ok) {
                // Éxito: mostrar mensaje y cerrar el modal
                this.showAlert(result.msg || 'Consulta guardada correctamente.', 'success');
                
                // Cerrar el modal después de un breve delay
                setTimeout(() => {
                    this.cerrarModalConsulta();
                    // Recargar la página después de cerrar el modal
                    setTimeout(() => {
                        location.reload();
                    }, 300);
                }, 1000);
            } else {
                // Error: mostrar mensaje pero NO cerrar el modal ni redirigir
                const errorMsg = result.msg || result.message || 'Error al guardar la consulta. Por favor, intente nuevamente.';
                this.showAlert(errorMsg, 'danger');                
            }

        } catch (error) {
            console.error('Error al confirmar consulta:', error);
            
            // Mostrar error pero NO cerrar el modal ni redirigir
            this.showAlert(error.message || 'Error al guardar la consulta. Por favor, intente nuevamente.', 'danger');            
        }

        confirmButton.disabled = false;
        confirmButton.innerHTML = '<i class="bi bi-check-circle"></i>&nbsp;&nbsp;Confirmar Consulta';
    }

    /**
     * Cerrar el modal de consulta de forma segura
     */
    cerrarModalConsulta() {
        const modalConsulta = document.getElementById('modal-consulta');
        if (!modalConsulta) return;

        // Usar Bootstrap modal para cerrar
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modalInstance = bootstrap.Modal.getInstance(modalConsulta);
            if (modalInstance) {
                modalInstance.hide();
            } else {
                // Fallback: usar jQuery si está disponible
                if (typeof $ !== 'undefined' && $.fn.modal) {
                    $('#modal-consulta').modal('hide');
                } else {
                    // Fallback final: ocultar manualmente
                    modalConsulta.style.display = 'none';
                    modalConsulta.classList.remove('show');
                    document.body.classList.remove('modal-open');
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) {
                        backdrop.remove();
                    }
                }
            }
        } else if (typeof $ !== 'undefined' && $.fn.modal) {
            $('#modal-consulta').modal('hide');
        }
    }

    /**
     * Obtener sugerencias seleccionadas de los hidden inputs
     * @returns {Object} - Objeto con las sugerencias seleccionadas por categoría
     */
    obtenerSugerenciasSeleccionadas() {
        const sugerencias = {
            diagnostico: [],
            practicas: [],
            seguimiento: [],
            alertas: []
        };

        const categorias = ['diagnostico', 'practicas', 'seguimiento', 'alertas'];
        categorias.forEach(categoria => {
            const input = document.querySelector(`input[name="sugerencias_${categoria}"]`);
            if (input && input.value) {
                try {
                    sugerencias[categoria] = JSON.parse(input.value);
                } catch (e) {
                    console.error(`Error al parsear sugerencias de ${categoria}:`, e);
                }
            }
        });

        return sugerencias;
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
