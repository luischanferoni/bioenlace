/**
 * Modal Consulta - JavaScript
 * Maneja la apertura y gestión del modal de consulta para crear/editar/visualizar
 */

$(document).ready(function() {
    
    // Manejo del cierre de modales
    $(function() {
        $('.btn-close').on('click', function() {
            // Recargar para que se vea la última consulta cargada si se cierra el modal
            if (!$(this).closest('#modal_detail_consulta').length) {
                // Si no lo tiene, recargar la página
                location.reload();
            }
        });
    });
    
    /**
     * Lanza el modal de consulta con el contenido cargado vía AJAX
     * @param {string} url - URL para cargar el contenido del modal
     * @param {boolean} isUnified - Si es una consulta unificada
     */
    function lanzarModalConsulta(url, isUnified = false) {
        $('#modal-consulta .modal-body').html('<div class="iq-loader-box"><div class="iq-loader-8"></div></div>');
        
        if (url == 'error') {
            alertaFlotante('Hubo un error al intentar crear esta consulta', 'danger');
            return;
        }

        $.ajax({
            url: url,
            type: 'GET',
            success: function (data) {           
                if(typeof(data.success) == 'undefined') {                        
                    $('#modal-consulta .modal-body').html(data);
                    // Inicializar chat después de cargar contenido
                    setTimeout(initChatIfPresent, 100);
                } else {
                    if(data.success) {
                        $('#modal-consulta .modal-body').html(data);
                        // Inicializar chat después de cargar contenido
                        setTimeout(initChatIfPresent, 100);
                    } else {
                        $('#modal-consulta .modal-body').html(
                            '<div class="alert alert-warning d-flex align-items-center" role="alert">'+data.msg+'</div>');
                    }
                }
            },
            error: function () {
                const mensaje = isUnified 
                    ? 'Ocurrió un error al cargar la consulta unificada' 
                    : 'Ocurrió un error';
                alertaFlotante(mensaje, 'danger');
            }
        });

        if (!$('#modal-consulta').hasClass('show')) {
            $('#modal-consulta').modal('show');
        }            
    }

    /**
     * Lanza el modal de consulta unificada
     * @param {string} url - URL para cargar el contenido del modal
     */
    function lanzarModalConsultaUnificada(url) {
        lanzarModalConsulta(url, true);
    }

    // Manejo de botones "atender" (crear nueva consulta)
    $(document).on('click', '.atender', function(e) {
        e.preventDefault();
        if($(this).attr('href') != '#'){
            let url = yii.getBaseCurrentUrl() + $(this).attr('href');
            // Usar el sistema unificado
            lanzarModalConsultaUnificada(url);
        }           
    });

    // Manejo de edición de consultas existentes
    $('.editar_consulta').click(function(e) {
        e.preventDefault();
        let url = yii.getBaseCurrentUrl() + $(this).attr('href');

        $.ajax({
            url: url,
            type: 'GET',
            success: function (data) {
                lanzarModalConsulta(data.url_siguiente);
            },
            error: function () {
                alertaFlotante('Ocurrió un error al intentar editar la consulta', 'danger');
            }
        });
    });

    // Inicializar chat si está presente
    function initChatIfPresent() {
        const chatContainer = document.querySelector('#chat-container');
        if (chatContainer && !window.chatInstance) {
            const consultaId = chatContainer.dataset.consultaId;
            const userId = chatContainer.dataset.userId;
            const userRole = chatContainer.dataset.userRole;
            
            if (consultaId && userId && userRole) {
                window.chatInstance = window.initChat('chat-container', consultaId, userId, userRole);
            }
        }
    }
    
    // Manejo del modal de detalle de consulta (ya existente en timeline.php)
    var viewing_detail_id = undefined;
    var modal_consulta_detail = document.getElementById('modal_detail_consulta');

    if (modal_consulta_detail) {
        modal_consulta_detail.addEventListener('show.bs.modal', function (event) {
            // Button that triggered the modal
            var button = event.relatedTarget;
            // Extract info from data-bs-* attributes
            var consulta_id = button.getAttribute('data-bs-consulta_id');
            var consulta_url = button.getAttribute('data-bs-consulta_detalle_url');
            
            // Update the modal's content.
            var modalTitle = modal_consulta_detail.querySelector('.modal-title');
            var modalBody = modal_consulta_detail.querySelector('.modal-body');

            modalTitle.textContent = 'Detalle de la Consulta ID ' + consulta_id;
                    
            if(viewing_detail_id == consulta_id) {
                // consulta already loaded.
                return;
            }
            
            $('#modal_detail_consulta .modal-body').html('');
            const template = document.querySelector("#loader_template");
            const clone = template.content.cloneNode(true);
            $('#modal_detail_consulta .modal-body').append(clone);
                    
            $.get(consulta_url, { 'id_consulta': consulta_id })
                .done(function(data) {
                    viewing_detail_id = consulta_id;
                    $('#modal_detail_consulta .modal-body').html(data);
                })
                .fail(function() {
                    viewing_detail_id = -1;
                    var msg = "<p>Error al cargar datos de la consulta.</p>";
                    $('#modal_detail_consulta .modal-body').html(msg);
                });
        });
    }
});
