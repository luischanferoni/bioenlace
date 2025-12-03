/**
 * JavaScript para la página de inicio administrativo
 * Maneja las consultas a IA y el renderizado de respuestas
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        const queryInput = $('#admin-query-input');
        const sendBtn = $('#send-query-btn');
        const responseSection = $('#ia-response-section');
        const explanationDiv = $('#ia-explanation');
        const actionsDiv = $('#ia-actions');
        const commonActionsDiv = $('#common-actions-grid');

        // Cargar acciones comunes al inicio
        loadCommonActions();

        // Manejar envío de consulta
        sendBtn.on('click', function() {
            sendQuery();
        });

        // Enviar con Enter (Ctrl+Enter o Shift+Enter para nueva línea)
        queryInput.on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey) {
                e.preventDefault();
                sendQuery();
            }
        });

        /**
         * Enviar consulta a la IA
         */
        function sendQuery() {
            const query = queryInput.val().trim();
            
            if (!query) {
                alert('Por favor, ingresa una consulta');
                return;
            }

            // Deshabilitar botón y mostrar loading
            sendBtn.prop('disabled', true);
            sendBtn.find('.spinner-border').removeClass('d-none');
            responseSection.addClass('d-none');

            // Realizar petición AJAX
            $.ajax({
                url: '/site/process-admin-query',
                method: 'POST',
                data: {
                    query: query,
                    _csrf: $('meta[name=csrf-token]').attr('content')
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        displayResponse(response.explanation, response.actions);
                    } else {
                        displayError(response.error || 'Error al procesar la consulta');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    displayError('Error de conexión. Por favor, intente nuevamente.');
                },
                complete: function() {
                    // Rehabilitar botón
                    sendBtn.prop('disabled', false);
                    sendBtn.find('.spinner-border').addClass('d-none');
                }
            });
        }

        /**
         * Mostrar respuesta de la IA
         */
        function displayResponse(explanation, actions) {
            // Mostrar explicación
            explanationDiv.html('<p class="mb-0">' + escapeHtml(explanation) + '</p>');
            
            // Mostrar acciones
            if (actions && actions.length > 0) {
                actionsDiv.html(renderActionCards(actions));
            } else {
                actionsDiv.html('<div class="col-12"><p class="text-muted mb-0">No se encontraron acciones específicas para esta consulta.</p></div>');
            }

            // Mostrar sección de respuesta
            responseSection.removeClass('d-none');
            
            // Scroll suave a la respuesta
            $('html, body').animate({
                scrollTop: responseSection.offset().top - 20
            }, 500);
        }

        /**
         * Mostrar error
         */
        function displayError(message) {
            explanationDiv.html('<div class="alert alert-danger mb-0">' + escapeHtml(message) + '</div>');
            actionsDiv.html('');
            responseSection.removeClass('d-none');
        }

        /**
         * Renderizar tarjetas de acciones
         */
        function renderActionCards(actions) {
            if (!actions || actions.length === 0) {
                return '';
            }

            let html = '<div class="col-12"><h6 class="mb-3">Acciones sugeridas:</h6></div>';

            actions.forEach(function(action) {
                html += `
                    <div class="col-md-6 col-lg-4">
                        <a href="${escapeHtml(action.route)}" class="card text-decoration-none h-100">
                            <div class="card-body">
                                <h6 class="card-title text-primary">${escapeHtml(action.name)}</h6>
                                <p class="card-text text-muted small mb-0">${escapeHtml(action.description || '')}</p>
                            </div>
                        </a>
                    </div>
                `;
            });

            return html;
        }

        /**
         * Cargar acciones comunes
         */
        function loadCommonActions() {
            $.ajax({
                url: '/site/get-common-actions',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.actions) {
                        commonActionsDiv.html(renderActionCards(response.actions));
                    }
                },
                error: function() {
                    // Si falla, no mostrar nada (no crítico)
                    console.warn('No se pudieron cargar las acciones comunes');
                }
            });
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
    });
})(jQuery);

