/**
 * Sistema de Navegación Stack para SPA
 * Maneja la navegación tipo app móvil con historial de páginas
 */

(function() {
    'use strict';

    // Estado de navegación
    const navigationState = {
        stack: [], // Array de páginas visitadas
        currentPageId: 'home',
        isTransitioning: false
    };

    // Referencias a elementos DOM
    const homePage = document.getElementById('spa-home');
    const pagesContainer = document.getElementById('spa-pages-container');
    
    // Verificar que los elementos existan
    if (!pagesContainer) {
        console.warn('spa-pages-container no encontrado. La navegación SPA puede no funcionar correctamente.');
    }

    /**
     * Navegar a una nueva página
     * @param {string} pageId - ID único de la página
     * @param {string} title - Título de la página
     * @param {string|HTMLElement} content - Contenido HTML o elemento DOM
     * @param {object} data - Datos adicionales para la página
     */
    window.navigateTo = function(pageId, title, content, data = {}) {
        if (navigationState.isTransitioning) return;

        navigationState.isTransitioning = true;

        // Verificar si la página ya existe en el stack
        const existingPageIndex = navigationState.stack.findIndex(p => p.id === pageId);
        
        if (existingPageIndex !== -1) {
            // La página ya existe, reemplazar su contenido y moverla al final del stack
            const existingPage = navigationState.stack[existingPageIndex];
            existingPage.title = title;
            existingPage.content = typeof content === 'string' ? content : content.outerHTML;
            existingPage.data = { ...existingPage.data, ...data };
            existingPage.timestamp = Date.now();
            
            // Remover del stack y agregarlo al final
            navigationState.stack.splice(existingPageIndex, 1);
            navigationState.stack.push(existingPage);
            
            // Actualizar el contenido del elemento DOM si existe
            const pageElement = document.getElementById(`spa-page-${pageId}`);
            if (pageElement) {
                const contentElement = pageElement.querySelector('.spa-page-content');
                if (contentElement) {
                    contentElement.innerHTML = existingPage.content;
                }
                const titleElement = pageElement.querySelector('.spa-page-header h2');
                if (titleElement) {
                    titleElement.textContent = title;
                }
            } else {
                // Si no existe el elemento DOM, crearlo
                createPageElement(existingPage);
            }
        } else {
            // Crear nueva página
            const page = {
                id: pageId,
                title: title,
                content: typeof content === 'string' ? content : content.outerHTML,
                data: data,
                timestamp: Date.now()
            };

            // Agregar al stack
            navigationState.stack.push(page);
            
            // Crear elemento DOM
            createPageElement(page);
        }

        navigationState.currentPageId = pageId;

        // Ocultar página actual
        hideCurrentPage();

        // Mostrar nueva página
        setTimeout(() => {
            showPage(pageId);
            navigationState.isTransitioning = false;
        }, 150);
    };

    /**
     * Volver a la página anterior
     */
    window.goBack = function() {
        if (navigationState.isTransitioning) return;
        if (navigationState.stack.length === 0) return;

        navigationState.isTransitioning = true;

        // Remover página actual del stack
        const currentPage = navigationState.stack.pop();
        const previousPageId = navigationState.stack.length > 0 
            ? navigationState.stack[navigationState.stack.length - 1].id 
            : 'home';

        // Ocultar página actual
        hidePage(currentPage.id);

        // Mostrar página anterior
        setTimeout(() => {
            if (previousPageId === 'home' && homePage) {
                showHomePage();
            } else {
                showPage(previousPageId);
            }
            navigationState.currentPageId = previousPageId;
            navigationState.isTransitioning = false;
        }, 150);
    };

    /**
     * Crear elemento DOM para una página
     */
    function createPageElement(page) {
        // Verificar si ya existe
        let pageElement = document.getElementById(`spa-page-${page.id}`);
        if (pageElement) {
            // Actualizar contenido y título si la página ya existe
            const contentElement = pageElement.querySelector('.spa-page-content');
            if (contentElement) {
                contentElement.innerHTML = page.content;
            }
            const titleElement = pageElement.querySelector('.spa-page-header h2');
            if (titleElement) {
                titleElement.textContent = page.title;
            }
            return pageElement;
        }

        // Crear nuevo elemento
        pageElement = document.createElement('div');
        pageElement.id = `spa-page-${page.id}`;
        pageElement.className = 'spa-page spa-page-hidden';
        
        // Header con título y botón volver
        const header = document.createElement('div');
        header.className = 'spa-page-header bg-white border-bottom shadow-sm';
        header.innerHTML = `
            <div class="container-fluid">
                <div class="d-flex align-items-center gap-3 py-3 px-3">
                    <button class="btn btn-outline-primary btn-sm d-flex align-items-center gap-2" onclick="goBack()" aria-label="Volver">
                        <span>←</span>
                        <span>Volver</span>
                    </button>
                    <h2 class="h5 mb-0 fw-semibold text-dark flex-grow-1">${escapeHtml(page.title)}</h2>
                </div>
            </div>
        `;

        // Contenido
        const content = document.createElement('div');
        content.className = 'spa-page-content';
        content.innerHTML = page.content;

        pageElement.appendChild(header);
        pageElement.appendChild(content);
        pagesContainer.appendChild(pageElement);

        return pageElement;
    }

    /**
     * Mostrar página específica
     */
    function showPage(pageId) {
        const pageElement = document.getElementById(`spa-page-${pageId}`);
        if (pageElement) {
            pageElement.classList.remove('spa-page-hidden');
            pageElement.classList.add('spa-page-active');
        }
    }

    /**
     * Ocultar página específica
     */
    function hidePage(pageId) {
        const pageElement = document.getElementById(`spa-page-${pageId}`);
        if (pageElement) {
            pageElement.classList.remove('spa-page-active');
            pageElement.classList.add('spa-page-hidden');
        }
    }

    /**
     * Ocultar página actual
     */
    function hideCurrentPage() {
        if (navigationState.currentPageId === 'home' && homePage) {
            hideHomePage();
        } else {
            hidePage(navigationState.currentPageId);
        }
    }

    /**
     * Mostrar página home
     */
    function showHomePage() {
        if (homePage) {
            homePage.classList.remove('spa-page-hidden');
            homePage.classList.add('spa-page-active');
        }
    }

    /**
     * Ocultar página home
     */
    function hideHomePage() {
        if (homePage) {
            homePage.classList.remove('spa-page-active');
            homePage.classList.add('spa-page-hidden');
        }
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

    /**
     * Obtener página actual
     */
    window.getCurrentPage = function() {
        if (navigationState.stack.length === 0) {
            return { id: 'home', title: 'Inicio' };
        }
        return navigationState.stack[navigationState.stack.length - 1];
    };

    /**
     * Limpiar historial (volver al inicio)
     */
    window.resetNavigation = function() {
        // Ocultar todas las páginas del stack
        navigationState.stack.forEach(page => {
            hidePage(page.id);
        });
        
        // Limpiar stack
        navigationState.stack = [];
        navigationState.currentPageId = 'home';
        
        // Mostrar home solo si existe
        if (homePage) {
            showHomePage();
        }
    };

    // Manejar botón de retroceso del navegador (opcional)
    // window.addEventListener('popstate', function(event) {
    //     if (navigationState.stack.length > 0) {
    //         goBack();
    //     }
    // });

})();

