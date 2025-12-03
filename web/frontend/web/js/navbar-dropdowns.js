/**
 * Manejo unificado de dropdowns del navbar
 * Cierra otros dropdowns cuando se abre uno y maneja posicionamiento
 */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar todos los dropdowns de Bootstrap
        var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
        var dropdownList = dropdownElementList.map(function(dropdownToggleEl) {
            return new bootstrap.Dropdown(dropdownToggleEl);
        });

        // Cerrar otros dropdowns cuando se abre uno
        dropdownElementList.forEach(function(dropdownToggle) {
            dropdownToggle.addEventListener('show.bs.dropdown', function(event) {
                // Cerrar todos los otros dropdowns
                dropdownElementList.forEach(function(otherDropdown) {
                    if (otherDropdown !== dropdownToggle) {
                        var otherDropdownInstance = bootstrap.Dropdown.getInstance(otherDropdown);
                        if (otherDropdownInstance && otherDropdownInstance._isShown()) {
                            otherDropdownInstance.hide();
                        }
                    }
                });
            });
        });

        // Ajustar posicionamiento del dropdown de usuario si se sale de la pantalla
        var userDropdown = document.getElementById('dropdownUser');
        if (userDropdown) {
            userDropdown.addEventListener('show.bs.dropdown', function() {
                // Prevenir el posicionamiento automático de Bootstrap para este dropdown
                var dropdownMenu = this.nextElementSibling;
                if (dropdownMenu && dropdownMenu.classList.contains('dropdown-menu')) {
                    // Configurar posicionamiento estático antes de mostrar
                    dropdownMenu.setAttribute('data-bs-popper', 'static');
                }
            });
            
            userDropdown.addEventListener('shown.bs.dropdown', function() {
                var dropdownMenu = this.nextElementSibling;
                if (dropdownMenu && dropdownMenu.classList.contains('dropdown-menu')) {
                    // Ajustar posicionamiento después de mostrar
                    setTimeout(function() {
                        var rect = dropdownMenu.getBoundingClientRect();
                        var buttonRect = userDropdown.getBoundingClientRect();
                        var windowWidth = window.innerWidth;
                        var windowHeight = window.innerHeight;
                        var padding = 10; // Padding de seguridad
                        
                        // Resetear estilos primero
                        dropdownMenu.style.left = '';
                        dropdownMenu.style.right = '';
                        dropdownMenu.style.transform = '';
                        dropdownMenu.style.maxWidth = '';
                        dropdownMenu.style.maxHeight = '';
                        dropdownMenu.style.overflowY = '';
                        
                        // Si el menú se sale por la derecha, alinearlo al borde derecho del botón
                        if (rect.right > windowWidth - padding) {
                            dropdownMenu.style.position = 'fixed';
                            dropdownMenu.style.right = (windowWidth - buttonRect.right) + 'px';
                            dropdownMenu.style.left = 'auto';
                            dropdownMenu.style.top = buttonRect.bottom + 'px';
                        }
                        
                        // Si el menú se sale por la izquierda
                        if (rect.left < padding) {
                            dropdownMenu.style.position = 'fixed';
                            dropdownMenu.style.left = padding + 'px';
                            dropdownMenu.style.right = 'auto';
                            dropdownMenu.style.top = buttonRect.bottom + 'px';
                        }
                        
                        // Si el menú se sale por abajo
                        if (rect.bottom > windowHeight - padding) {
                            dropdownMenu.style.maxHeight = (windowHeight - buttonRect.bottom - padding) + 'px';
                            dropdownMenu.style.overflowY = 'auto';
                        }
                    }, 10);
                }
            });
        }
    });
})();

