/**
 * Lógica de la Página Inicial de la SPA
 * Maneja el textarea, consultas a IA, y renderizado de cards
 */

(function() {
    'use strict';

    // Referencias a elementos DOM
    const queryInput = document.getElementById('spa-query-input');
    const sendBtn = document.getElementById('spa-send-btn');
    const responseSection = document.getElementById('spa-response-section');
    const explanationDiv = document.getElementById('spa-explanation');
    const actionsDiv = document.getElementById('spa-actions');
    const commonActionsDiv = document.getElementById('spa-common-actions-grid');
    const commonActionsSection = document.getElementById('spa-common-actions');

    // Estado de cards expandidos
    const expandedCards = new Map();

    /**
     * Inicialización
     */
    function init() {
        // Cargar acciones comunes al inicio solo si existe el contenedor
        if (commonActionsDiv) {
            loadCommonActions();
        }

        // Event listeners solo si los elementos existen
        if (sendBtn && queryInput) {
            sendBtn.addEventListener('click', handleSendQuery);
            queryInput.addEventListener('keydown', handleKeyDown);
            queryInput.addEventListener('input', handleInput);

            // Focus en textarea al cargar
            queryInput.focus();
        }
    }

    /**
     * Manejar envío de consulta
     */
    function handleSendQuery() {
        const query = queryInput.value.trim();
        
        if (!query) {
            showError('Por favor, ingresa una consulta');
            return;
        }

        // Deshabilitar botón y mostrar loading
        setLoadingState(true);
        hideResponse();

        // Usar endpoint de la API
        const crudUrl = window.spaConfig.baseUrl + '/api/v1/crud/process-query';
        fetch(crudUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin', // Incluir cookies de sesión
            body: JSON.stringify({
                query: query
            })
        })
        .then(response => {
            // Si la respuesta no es exitosa, manejar el error
            if (!response.ok) {
                return response.text().then(text => {
                    // Intentar parsear como JSON si es posible
                    try {
                        const jsonData = JSON.parse(text);
                        throw new Error(jsonData.message || jsonData.error || `Error ${response.status}: ${response.statusText}`);
                    } catch (e) {
                        // Si no es JSON, es probablemente un error de validación
                        if (response.status === 400) {
                            throw new Error('Error de validación. Por favor, verifica tu consulta e intenta nuevamente.');
                        } else if (response.status === 401) {
                            throw new Error('Debes estar autenticado para usar esta funcionalidad.');
                        }
                        throw new Error(`Error ${response.status}: ${response.statusText}`);
                    }
                });
            }
            
            // Verificar que la respuesta sea JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    console.warn('El servidor devolvió HTML en lugar de JSON:', text.substring(0, 200));
                    throw new Error('Respuesta no válida del servidor');
                });
            }
            return response.json();
        })
        .then(data => {
            // La respuesta puede venir de dos formas:
            // 1. Directamente: {success: true, explanation: "...", action: {...}, data: {...}}
            // 2. Envuelta: {success: true, data: {success: true, explanation: "...", ...}}
            // 
            // Si data.data existe Y tiene success/explanation/action, entonces data.data es el resultado real
            // Si no, entonces data es el resultado directamente
            let result = data;
            if (data.data && typeof data.data === 'object' && (data.data.success !== undefined || data.data.explanation !== undefined || data.data.action !== undefined)) {
                // data.data contiene el resultado real
                result = data.data;
            }
            
            // Si tiene explicación, mostrar respuesta (incluso si success es false)
            if (result.explanation !== undefined) {
                // Verificar si es respuesta CRUD con formulario
                if (result.form) {
                    displayCrudResponse(result);
                } else if (result.intention) {
                    // Es respuesta CRUD pero sin formulario (read, delete, etc)
                    displayCrudResponse(result);
                } else {
                    // Respuesta normal - manejar tanto 'action' (singular) como 'actions' (plural)
                    let actionsToDisplay = result.actions || [];
                    
                    // Si hay 'action' singular (ej: búsqueda por DNI), convertirla a array
                    if (result.action && !result.actions) {
                        actionsToDisplay = [result.action];
                        
                        // Si hay alternative_actions, agregarlas también
                        if (result.alternative_actions && Array.isArray(result.alternative_actions) && result.alternative_actions.length > 0) {
                            actionsToDisplay = actionsToDisplay.concat(result.alternative_actions);
                        }
                    }
                    
                    // Mostrar datos adicionales si existen (ej: datos de persona encontrada)
                    let explanation = result.explanation || '';
                    if (result.data && typeof result.data === 'object') {
                        const dataInfo = [];
                        if (result.data.nombre) {
                            dataInfo.push(`<strong>Nombre:</strong> ${escapeHtml(result.data.nombre)}`);
                        }
                        if (result.data.dni) {
                            dataInfo.push(`<strong>DNI:</strong> ${escapeHtml(result.data.dni)}`);
                        }
                        if (dataInfo.length > 0) {
                            explanation += '<div class="mt-2 p-2 bg-light rounded"><small>' + dataInfo.join(' | ') + '</small></div>';
                        }
                    }
                    
                    // Si success es false pero hay explicación, mostrar como información (no error)
                    if (result.success === false) {
                        displayInfoResponse(explanation, actionsToDisplay, result.suggested_query);
                    } else {
                        displayResponse(explanation, actionsToDisplay);
                    }
                }
            } else if (result.success !== false) {
                // Sin explicación pero success es true - mostrar error genérico
                showError(result.error || result.message || 'Error al procesar la consulta');
            } else {
                // Error sin explicación
                showError(result.error || result.message || 'Error al procesar la consulta');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError(error.message || 'Error de conexión. Por favor, intente nuevamente.');
        })
        .finally(() => {
            setLoadingState(false);
        });
    }


    /**
     * Mostrar respuesta CRUD
     */
    function displayCrudResponse(data) {
        // Mostrar explicación
        explanationDiv.innerHTML = '<p class="mb-0">' + escapeHtml(data.explanation) + '</p>';
        
        // Si hay formulario, renderizarlo
        if (data.form && data.form.success) {
            actionsDiv.innerHTML = renderCrudForm(data.form, data.intention, data.entity_id);
        } else if (data.action) {
            // Acción directa (navegación, eliminación, etc)
            actionsDiv.innerHTML = renderCrudAction(data);
        } else if (data.suggested_actions && data.suggested_actions.length > 0) {
            // Acciones sugeridas cuando la entidad no existe
            actionsDiv.innerHTML = renderActionCards(data.suggested_actions);
            attachCardListeners();
        } else {
            actionsDiv.innerHTML = '<div class="col-12"><p class="text-muted mb-0">' + escapeHtml(data.message || 'No hay acciones disponibles') + '</p></div>';
        }

        // Mostrar sección de respuesta
        responseSection.classList.remove('d-none');
        
        // Scroll suave a la respuesta
        setTimeout(() => {
            responseSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 100);
    }

    /**
     * Renderizar formulario CRUD dinámico
     */
    function renderCrudForm(formData, intention, entityId) {
        let html = '<div class="col-12"><div class="card"><div class="card-body">';
        html += '<h6 class="card-title mb-4">Formulario</h6>';
        html += '<form id="crud-dynamic-form" data-intention="' + intention + '" data-entity-id="' + (entityId || '') + '" data-form-action="' + escapeHtml(formData.form_action) + '">';
        
        formData.fields.forEach(field => {
            html += renderFormField(field);
        });
        
        html += '<div class="mt-4 d-flex gap-2">';
        html += '<button type="submit" class="btn btn-primary">Guardar</button>';
        html += '<button type="button" class="btn btn-secondary" onclick="document.getElementById(\'crud-dynamic-form\').reset()">Limpiar</button>';
        html += '</div>';
        html += '</form></div></div></div>';
        
        // Adjuntar listeners al formulario
        setTimeout(() => {
            attachFormListeners();
        }, 100);
        
        return html;
    }

    /**
     * Renderizar campo de formulario
     */
    function renderFormField(field) {
        let html = '<div class="mb-3">';
        html += '<label class="form-label">' + escapeHtml(field.label);
        if (field.required) {
            html += ' <span class="text-danger">*</span>';
        }
        html += '</label>';
        
        switch (field.type) {
            case 'select':
                html += renderSelectField(field);
                break;
            case 'radio':
                html += renderRadioField(field);
                break;
            case 'number':
                html += renderNumberField(field);
                break;
            case 'date':
                html += renderDateField(field);
                break;
            case 'textarea':
                html += renderTextareaField(field);
                break;
            default:
                html += renderTextField(field);
        }
        
        html += '</div>';
        return html;
    }

    /**
     * Renderizar campo select
     */
    function renderSelectField(field) {
        let html = '<select class="form-select" name="' + escapeHtml(field.name) + '"' + (field.required ? ' required' : '') + '>';
        html += '<option value="">Seleccione...</option>';
        if (field.options) {
            field.options.forEach(option => {
                const value = typeof option === 'object' ? option.value : option;
                const label = typeof option === 'object' ? option.label : option;
                html += '<option value="' + escapeHtml(value) + '">' + escapeHtml(label) + '</option>';
            });
        }
        html += '</select>';
        return html;
    }

    /**
     * Renderizar campo radio (opciones seleccionables)
     */
    function renderRadioField(field) {
        let html = '<div class="d-flex flex-wrap gap-2">';
        if (field.options) {
            field.options.forEach(option => {
                const value = typeof option === 'object' ? option.value : option;
                const label = typeof option === 'object' ? option.label : option;
                const id = field.name + '_' + value;
                html += '<div class="form-check">';
                html += '<input class="form-check-input" type="radio" name="' + escapeHtml(field.name) + '" id="' + id + '" value="' + escapeHtml(value) + '"' + (field.required ? ' required' : '') + '>';
                html += '<label class="form-check-label" for="' + id + '">' + escapeHtml(label) + '</label>';
                html += '</div>';
            });
        }
        html += '</div>';
        return html;
    }

    /**
     * Renderizar campo numérico con opciones rápidas y botones +/-
     */
    function renderNumberField(field) {
        let html = '';
        
        // Opciones rápidas
        if (field.quick_options && field.quick_options.length > 0) {
            html += '<div class="mb-2 d-flex gap-2 flex-wrap">';
            field.quick_options.forEach(option => {
                html += '<button type="button" class="btn btn-outline-primary btn-sm quick-option-btn" data-field="' + escapeHtml(field.name) + '" data-value="' + option + '">' + option + '</button>';
            });
            html += '</div>';
        }
        
        // Input numérico con botones +/-
        html += '<div class="input-group">';
        html += '<button type="button" class="btn btn-outline-secondary number-decrement" data-field="' + escapeHtml(field.name) + '">-</button>';
        html += '<input type="number" class="form-control text-center" name="' + escapeHtml(field.name) + '" value="' + (field.value || '') + '"';
        if (field.min !== null) html += ' min="' + field.min + '"';
        if (field.max !== null) html += ' max="' + field.max + '"';
        if (field.step) html += ' step="' + field.step + '"';
        if (field.required) html += ' required';
        html += '>';
        html += '<button type="button" class="btn btn-outline-secondary number-increment" data-field="' + escapeHtml(field.name) + '">+</button>';
        html += '</div>';
        
        return html;
    }

    /**
     * Renderizar campo de fecha
     */
    function renderDateField(field) {
        let html = '<input type="date" class="form-control" name="' + escapeHtml(field.name) + '" value="' + (field.value || '') + '"' + (field.required ? ' required' : '') + '>';
        return html;
    }

    /**
     * Renderizar campo de texto
     */
    function renderTextField(field) {
        let html = '<input type="text" class="form-control" name="' + escapeHtml(field.name) + '" value="' + (field.value || '') + '"' + (field.required ? ' required' : '') + '>';
        return html;
    }

    /**
     * Renderizar campo textarea
     */
    function renderTextareaField(field) {
        let html = '<textarea class="form-control" name="' + escapeHtml(field.name) + '" rows="3"' + (field.required ? ' required' : '') + '>' + (field.value || '') + '</textarea>';
        return html;
    }

    /**
     * Renderizar acción CRUD (navegación, eliminación, etc)
     */
    function renderCrudAction(data) {
        let html = '<div class="col-12">';
        
        if (data.action.type === 'delete' && data.action.requires_confirmation) {
            html += '<div class="alert alert-warning">';
            html += '<p>' + escapeHtml(data.explanation) + '</p>';
            html += '<button class="btn btn-danger" onclick="confirmDelete(\'' + escapeHtml(data.action.route) + '\', ' + (data.entity_id || 'null') + ')">Confirmar Eliminación</button>';
            html += '<button class="btn btn-secondary ms-2" onclick="cancelDelete()">Cancelar</button>';
            html += '</div>';
        } else {
            html += '<div class="card"><div class="card-body">';
            html += '<p>' + escapeHtml(data.explanation) + '</p>';
            html += '<a href="' + escapeHtml(data.action.route) + '" class="btn btn-primary">Continuar</a>';
            html += '</div></div>';
        }
        
        html += '</div>';
        return html;
    }

    /**
     * Adjuntar listeners a formulario dinámico
     */
    function attachFormListeners() {
        const form = document.getElementById('crud-dynamic-form');
        if (!form) return;
        
        // Submit del formulario
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            submitCrudForm(this);
        });
        
        // Opciones rápidas para números
        document.querySelectorAll('.quick-option-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const fieldName = this.dataset.field;
                const value = this.dataset.value;
                const input = form.querySelector('input[name="' + fieldName + '"]');
                if (input) {
                    input.value = value;
                    // Resaltar botón seleccionado
                    document.querySelectorAll('.quick-option-btn[data-field="' + fieldName + '"]').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                }
            });
        });
        
        // Botones incremento/decremento
        document.querySelectorAll('.number-increment').forEach(btn => {
            btn.addEventListener('click', function() {
                const fieldName = this.dataset.field;
                const input = form.querySelector('input[name="' + fieldName + '"]');
                if (input) {
                    const current = parseInt(input.value) || 0;
                    const step = parseFloat(input.step) || 1;
                    input.value = current + step;
                }
            });
        });
        
        document.querySelectorAll('.number-decrement').forEach(btn => {
            btn.addEventListener('click', function() {
                const fieldName = this.dataset.field;
                const input = form.querySelector('input[name="' + fieldName + '"]');
                if (input) {
                    const current = parseInt(input.value) || 0;
                    const step = parseFloat(input.step) || 1;
                    const min = input.min ? parseFloat(input.min) : null;
                    const newValue = current - step;
                    if (min === null || newValue >= min) {
                        input.value = newValue;
                    }
                }
            });
        });
    }

    /**
     * Enviar formulario CRUD
     */
    function submitCrudForm(form) {
        const formData = new FormData(form);
        const action = form.dataset.formAction;
        const intention = form.dataset.intention;
        const entityId = form.dataset.entityId;
        
        // Agregar entity_id si es update
        if (intention === 'update' && entityId) {
            formData.append('id', entityId);
        }
        
        // Agregar CSRF
        formData.append('_csrf', window.spaConfig.csrfToken);
        
        // Mostrar loading
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';
        
        fetch(action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showError('Operación realizada exitosamente');
                form.reset();
                // Opcional: recargar o redirigir
                if (data.url_siguiente) {
                    window.location.href = data.url_siguiente;
                }
            } else {
                showError(data.msg || data.error || 'Error al guardar');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Error de conexión al guardar');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }

    /**
     * Confirmar eliminación
     */
    window.confirmDelete = function(route, entityId) {
        if (!confirm('¿Estás seguro de que quieres eliminar este registro? Esta acción no se puede deshacer.')) {
            return;
        }
        
        const formData = new URLSearchParams({
            id: entityId,
            _csrf: window.spaConfig.csrfToken
        });
        
        fetch(route, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showError('Registro eliminado exitosamente');
            } else {
                showError(data.error || 'Error al eliminar');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Error de conexión al eliminar');
        });
    };

    /**
     * Cancelar eliminación
     */
    window.cancelDelete = function() {
        hideResponse();
    };

    /**
     * Manejar teclado en textarea
     */
    function handleKeyDown(e) {
        // Enter para enviar, Shift+Enter para nueva línea
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSendQuery();
        }
    }

    /**
     * Manejar input en textarea
     */
    function handleInput() {
        // Auto-resize textarea
        queryInput.style.height = 'auto';
        queryInput.style.height = queryInput.scrollHeight + 'px';
    }

    /**
     * Mostrar respuesta de la IA
     */
    function displayResponse(explanation, actions) {
        // Mostrar explicación - si contiene HTML, no escapar, solo escapar el texto plano
        // Detectar si explanation ya contiene HTML (tags)
        let explanationHtml = explanation;
        if (explanation && !/<[a-z][\s\S]*>/i.test(explanation)) {
            // No contiene HTML, escapar el texto
            explanationHtml = escapeHtml(explanation);
        }
        explanationDiv.innerHTML = '<p class="mb-0">' + explanationHtml + '</p>';
        
        // Mostrar acciones
        if (actions && actions.length > 0) {
            actionsDiv.innerHTML = renderActionCards(actions);
            attachCardListeners();
        } else {
            actionsDiv.innerHTML = '<div class="col-12"><p class="text-muted mb-0">No se encontraron acciones específicas para esta consulta.</p></div>';
        }

        // Mostrar sección de respuesta
        responseSection.classList.remove('d-none');
        
        // Scroll suave a la respuesta
        setTimeout(() => {
            responseSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 100);
    }

    /**
     * Mostrar respuesta informativa (cuando success es false pero hay explicación útil)
     */
    function displayInfoResponse(explanation, actions, suggestedQuery) {
        // Mostrar explicación - si contiene HTML, no escapar
        let explanationHtml = explanation;
        if (explanation && !/<[a-z][\s\S]*>/i.test(explanation)) {
            explanationHtml = escapeHtml(explanation);
        }
        
        // Mostrar como información (no error)
        explanationDiv.innerHTML = '<div class="alert alert-info mb-0">' + explanationHtml + '</div>';
        
        // Si hay consulta sugerida, agregarla
        if (suggestedQuery) {
            explanationDiv.innerHTML += '<div class="mt-2"><small class="text-muted">Sugerencia: <a href="#" class="text-primary" onclick="document.getElementById(\'spa-query-input\').value=\'' + escapeHtml(suggestedQuery) + '\'; handleSendQuery(); return false;">' + escapeHtml(suggestedQuery) + '</a></small></div>';
        }
        
        // Mostrar acciones si existen
        if (actions && actions.length > 0) {
            actionsDiv.innerHTML = renderActionCards(actions);
            attachCardListeners();
        } else {
            actionsDiv.innerHTML = '<div class="col-12"><p class="text-muted mb-0">No se encontraron acciones específicas para esta consulta.</p></div>';
        }

        // Mostrar sección de respuesta
        responseSection.classList.remove('d-none');
        
        // Scroll suave a la respuesta
        setTimeout(() => {
            responseSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 100);
    }

    /**
     * Mostrar error
     */
    function showError(message) {
        explanationDiv.innerHTML = '<div class="alert alert-danger mb-0">' + escapeHtml(message) + '</div>';
        actionsDiv.innerHTML = '';
        responseSection.classList.remove('d-none');
    }

    /**
     * Ocultar respuesta
     */
    function hideResponse() {
        responseSection.classList.add('d-none');
    }

    /**
     * Renderizar tarjetas de acciones
     * @param {Array} actions - Array de acciones
     * @param {boolean} includeHeader - Si incluir el header "Acciones sugeridas"
     */
    function renderActionCards(actions, includeHeader = true) {
        if (!actions || actions.length === 0) {
            return '';
        }

        let html = '';
        if (includeHeader) {
            html = '<div class="col-12"><h6 class="mb-3 fw-semibold">Acciones sugeridas:</h6></div>';
        }

        actions.forEach((action, index) => {
            const cardId = `action-card-${Date.now()}-${index}`;
            const route = action.route || action.url || '';
            
            // Generar nombre y descripción si no existen
            const actionName = action.name || action.display_name || 'Ver detalles';
            const actionDescription = action.description || '';
            
            // Si hay params, construir la ruta completa
            let fullRoute = route;
            if (action.params && Object.keys(action.params).length > 0) {
                const params = new URLSearchParams(action.params);
                fullRoute = route + '?' + params.toString();
            }
            
            // Determinar si debe ser expandable o fullPage
            // Por defecto, las acciones son expandables a menos que se especifique lo contrario
            const fullPage = action.fullPage === true || shouldBeFullPage(route);
            const expandable = fullPage ? false : (action.expandable !== false);
            const actionType = action.type || determineActionType(route);
            
            html += `
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card h-100 spa-card shadow-sm" data-card-id="${cardId}" data-expandable="${expandable}" data-full-page="${fullPage}" data-action-type="${actionType}" data-action-url="${escapeHtml(fullRoute)}">
                        <div class="card-body">
                            <h6 class="card-title text-primary fw-semibold mb-2">${escapeHtml(actionName)}</h6>
                            <p class="card-text text-muted small mb-0">${escapeHtml(actionDescription)}</p>
                            <div class="spa-card-expand-content d-none mt-3"></div>
                        </div>
                    </div>
                </div>
            `;
        });

        return html;
    }

    /**
     * Adjuntar listeners a los cards
     */
    function attachCardListeners() {
        const cards = document.querySelectorAll('.spa-card');
        cards.forEach(card => {
            card.addEventListener('click', function(e) {
                // No hacer nada si se hace click en el contenido expandido
                if (e.target.closest('.spa-card-expand-content')) {
                    return;
                }

                const cardId = this.dataset.cardId;
                const expandable = this.dataset.expandable === 'true';
                const fullPage = this.dataset.fullPage === 'true';
                const actionUrl = this.dataset.actionUrl;
                const actionType = this.dataset.actionType;

                if (fullPage) {
                    // Abrir nueva página
                    openFullPage(actionUrl, this.querySelector('.card-title').textContent, actionType);
                } else if (expandable) {
                    // Expandir in-place
                    toggleCardExpansion(cardId, actionUrl, actionType);
                } else {
                    // Comportamiento por defecto: navegar directamente
                    if (actionUrl) {
                        if (actionUrl.startsWith('http') || actionUrl.startsWith('/')) {
                            // Generar pageId basado en la URL para reutilizar páginas
                            const pageId = generatePageId(actionUrl);
                            const cardTitle = this.querySelector('.card-title') ? this.querySelector('.card-title').textContent : 'Cargando...';
                            navigateTo(pageId, cardTitle, '<p>Cargando...</p>', { url: actionUrl });
                            loadPageContent(actionUrl, pageId);
                        } else {
                            const pageId = generatePageId(actionUrl);
                            const cardTitle = this.querySelector('.card-title') ? this.querySelector('.card-title').textContent : 'Cargando...';
                            navigateTo(pageId, cardTitle, '<p>Cargando...</p>', { url: actionUrl });
                            loadPageContent(actionUrl, pageId);
                        }
                    }
                }
            });
        });
    }

    /**
     * Alternar expansión de card
     */
    function toggleCardExpansion(cardId, actionUrl, actionType) {
        const card = document.querySelector(`[data-card-id="${cardId}"]`);
        if (!card) return;

        const expandContent = card.querySelector('.spa-card-expand-content');
        const isExpanded = expandedCards.has(cardId);

        if (isExpanded) {
            // Colapsar
            expandContent.classList.add('d-none');
            card.classList.remove('spa-card-expanded');
            expandedCards.delete(cardId);
        } else {
            // Expandir
            card.classList.add('spa-card-expanding');
            expandContent.classList.remove('d-none');
            
            // Si no tiene contenido, cargarlo
            if (!expandContent.innerHTML || expandContent.innerHTML.trim() === '') {
                expandContent.innerHTML = '<div class="d-flex align-items-center justify-content-center gap-2 py-3 text-muted"><div class="spinner-border spinner-border-sm"></div> Cargando...</div>';
                loadCardContent(actionUrl, actionType, expandContent, cardId);
            }

            // Animación
            setTimeout(() => {
                card.classList.remove('spa-card-expanding');
                card.classList.add('spa-card-expanded');
                expandedCards.set(cardId, true);
            }, 50);
        }
    }

    /**
     * Cargar contenido de card vía AJAX
     */
    function loadCardContent(url, type, container, cardId) {
        if (!url) {
            container.innerHTML = '<div class="alert alert-warning">No hay contenido disponible</div>';
            return;
        }

        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (response.ok) {
                return response.text();
            }
            throw new Error('Error al cargar contenido');
        })
        .then(html => {
            container.innerHTML = html;
            
            // Re-inicializar scripts si es necesario
            initializeCardContent(container, type);
        })
        .catch(error => {
            console.error('Error cargando contenido:', error);
            container.innerHTML = '<div class="alert alert-danger">Error al cargar el contenido</div>';
        });
    }

    /**
     * Inicializar contenido del card (para formularios, etc.)
     */
    function initializeCardContent(container, type) {
        // Aquí se pueden inicializar componentes específicos según el tipo
        // Por ejemplo, inicializar date pickers, select2, etc.
        
        // Re-ejecutar scripts inline si existen
        const scripts = container.querySelectorAll('script');
        scripts.forEach(oldScript => {
            const newScript = document.createElement('script');
            Array.from(oldScript.attributes).forEach(attr => {
                newScript.setAttribute(attr.name, attr.value);
            });
            newScript.appendChild(document.createTextNode(oldScript.innerHTML));
            oldScript.parentNode.replaceChild(newScript, oldScript);
        });
    }

    /**
     * Generar un ID único basado en la URL
     */
    function generatePageId(url) {
        // Crear un hash simple de la URL para usar como ID estable
        let hash = 0;
        for (let i = 0; i < url.length; i++) {
            const char = url.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convertir a entero de 32 bits
        }
        return 'page-' + Math.abs(hash).toString(36);
    }

    /**
     * Abrir página completa
     */
    function openFullPage(url, title, type) {
        const pageId = generatePageId(url);
        navigateTo(pageId, title, '<div class="d-flex align-items-center justify-content-center py-5"><div class="spinner-border text-primary"></div></div>', { url: url });
        loadPageContent(url, pageId, type);
    }

    /**
     * Cargar contenido de página vía AJAX
     */
    function loadPageContent(url, pageId, type) {
        if (!url) {
            const pageElement = document.getElementById(`spa-page-${pageId}`);
            if (pageElement) {
                const content = pageElement.querySelector('.spa-page-content');
                if (content) {
                    content.innerHTML = '<div class="alert alert-warning">No hay contenido disponible</div>';
                }
            }
            return;
        }

        // Construir URL completa si es relativa
        // Si la URL ya es absoluta (empieza con http), usarla tal cual
        // Si es relativa y empieza con /, concatenar con baseUrl
        // Si es relativa sin /, también concatenar con baseUrl
        let fullUrl;
        if (url.startsWith('http://') || url.startsWith('https://')) {
            fullUrl = url;
        } else if (url.startsWith('/')) {
            // URL relativa que empieza con / - usar baseUrl + url
            fullUrl = window.spaConfig.baseUrl + url;
        } else {
            // URL relativa sin / - usar baseUrl + / + url
            fullUrl = window.spaConfig.baseUrl + '/' + url;
        }
        
        fetch(fullUrl, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (response.ok) {
                return response.text();
            }
            throw new Error(`Error HTTP ${response.status}: ${response.statusText}`);
        })
        .then(html => {
            const pageElement = document.getElementById(`spa-page-${pageId}`);
            if (pageElement) {
                const content = pageElement.querySelector('.spa-page-content');
                if (content) {
                    // Filtrar scripts y CSS externos del HTML para evitar duplicados
                    const filteredHtml = filtrarAssetsDuplicados(html);
                    content.innerHTML = filteredHtml;
                    initializePageContent(content, type);
                } else {
                    console.error('No se encontró el contenedor .spa-page-content');
                }
            } else {
                console.error(`No se encontró el elemento de página: spa-page-${pageId}`);
            }
        })
        .catch(error => {
            console.error('Error cargando página:', error);
            const pageElement = document.getElementById(`spa-page-${pageId}`);
            if (pageElement) {
                const content = pageElement.querySelector('.spa-page-content');
                if (content) {
                    content.innerHTML = `<div class="alert alert-danger">
                        <strong>Error al cargar el contenido</strong><br>
                        ${error.message}<br>
                        <small>URL: ${fullUrl}</small>
                    </div>`;
                }
            }
        });
    }

    /**
     * Filtrar assets duplicados (CSS y JS externos) del HTML
     */
    function filtrarAssetsDuplicados(html) {
        // Crear un elemento temporal para parsear el HTML
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        
        // Lista de assets que ya están cargados globalmente
        const assetsCargados = [
            'bootstrap',
            'jquery',
            'yii.js',
            'bootstrap.bundle',
            'bootstrap.min',
            'ajax-wrapper.js',
            'turnos.js',
            'chat-inteligente.js',
            'timeline.js'
        ];
        
        // Eliminar <link> tags de CSS que ya están cargados
        const links = tempDiv.querySelectorAll('link[rel="stylesheet"]');
        links.forEach(link => {
            const href = link.getAttribute('href') || '';
            const deberiaEliminar = assetsCargados.some(asset => 
                href.toLowerCase().includes(asset.toLowerCase())
            );
            if (deberiaEliminar) {
                console.log('Eliminando CSS duplicado:', href);
                link.remove();
            }
        });
        
        // Eliminar <script> tags externos que ya están cargados
        const scripts = tempDiv.querySelectorAll('script[src]');
        scripts.forEach(script => {
            const src = script.getAttribute('src') || '';
            const deberiaEliminar = assetsCargados.some(asset => 
                src.toLowerCase().includes(asset.toLowerCase())
            );
            if (deberiaEliminar) {
                console.log('Eliminando JS duplicado:', src);
                script.remove();
            }
        });
        
        return tempDiv.innerHTML;
    }

    /**
     * Inicializar contenido de página
     */
    function initializePageContent(container, type) {
        // Re-ejecutar scripts inline (solo los que quedaron después del filtrado)
        const scripts = container.querySelectorAll('script:not([src])');
        scripts.forEach(oldScript => {
            // Verificar que el script no esté vacío
            if (oldScript.innerHTML.trim()) {
                const newScript = document.createElement('script');
                Array.from(oldScript.attributes).forEach(attr => {
                    newScript.setAttribute(attr.name, attr.value);
                });
                newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                oldScript.parentNode.replaceChild(newScript, oldScript);
            }
        });
    }

    /**
     * Cargar acciones comunes
     */
    function loadCommonActions() {
        if (!commonActionsDiv) {
            return; // No hay contenedor para acciones comunes
        }
        
        const url = window.spaConfig.baseUrl + '/site/get-common-actions';
        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => {
            // Verificar que la respuesta sea JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                // Si no es JSON, probablemente es un error HTML
                return response.text().then(text => {
                    console.warn('El servidor devolvió HTML en lugar de JSON:', text.substring(0, 200));
                    throw new Error('Respuesta no válida del servidor');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.actions) {
                // Agregar las acciones al contenido existente (preservar el card de prueba)
                const existingContent = commonActionsDiv.innerHTML;
                // No incluir header porque ya hay contenido existente
                const newActions = renderActionCards(data.actions, false);
                commonActionsDiv.innerHTML = existingContent + newActions;
                attachCardListeners();
            } else {
                // Si no hay acciones, mantener el contenido existente (card de prueba)
                attachCardListeners();
            }
        })
        .catch(error => {
            console.warn('No se pudieron cargar las acciones comunes:', error);
            // Mantener el contenido existente (card de prueba) aunque falle la carga
            attachCardListeners();
        });
    }

    /**
     * Estado de carga
     */
    function setLoadingState(loading) {
        sendBtn.disabled = loading;
        const spinner = sendBtn.querySelector('.spa-spinner');
        const sendIcon = sendBtn.querySelector('.spa-send-icon');
        const sendText = sendBtn.querySelector('.spa-send-text');
        
        if (loading) {
            spinner.classList.remove('d-none');
            sendIcon.classList.add('d-none');
            sendText.textContent = 'Enviando...';
        } else {
            spinner.classList.add('d-none');
            sendIcon.classList.remove('d-none');
            sendText.textContent = 'Enviar';
        }
    }

    /**
     * Determinar si una ruta debe abrirse en página completa
     * Rutas que típicamente requieren página completa: listados grandes, vistas complejas
     */
    function shouldBeFullPage(route) {
        if (!route) return false;
        
        // Patrones que indican que debe ser página completa
        const fullPagePatterns = [
            /\/index$/i,           // Listados
            /\/view\//i,           // Vistas de detalle
            /\/create$/i,          // Formularios de creación
            /\/update\//i,         // Formularios de edición
            /\/reporte/i,          // Reportes
            /\/timeline/i,         // Timelines
            /\/historial/i         // Historiales
        ];
        
        return fullPagePatterns.some(pattern => pattern.test(route));
    }

    /**
     * Determinar el tipo de acción basándose en la ruta
     */
    function determineActionType(route) {
        if (!route) return 'default';
        
        if (/\/index$/i.test(route)) return 'list';
        if (/\/create$/i.test(route)) return 'form';
        if (/\/view\//i.test(route)) return 'detail';
        if (/\/update\//i.test(route)) return 'form';
        if (/\/reporte/i.test(route)) return 'report';
        
        return 'default';
    }

    /**
     * Escapar HTML para prevenir XSS
     */
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            init();
            // Adjuntar listeners a cards que ya están en el DOM (como el card de prueba)
            attachCardListeners();
        });
    } else {
        init();
        // Adjuntar listeners a cards que ya están en el DOM (como el card de prueba)
        attachCardListeners();
    }

})();


