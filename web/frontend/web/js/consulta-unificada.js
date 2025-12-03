/**
 * Consulta Unificada - JavaScript
 * Maneja la lógica del formulario unificado con múltiples pasos
 */

class ConsultaUnificada {
    constructor(config) {
        this.config = config;
        this.pasoActual = 0;
        this.pasosCompletados = [];
        this.estadoGuardado = {};
        this.cargando = false;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.cargarPaso(0);
    }
    
    bindEvents() {
        // Botón siguiente
        document.getElementById('btn-next')?.addEventListener('click', () => this.siguientePaso());
        
        // Botón anterior
        document.getElementById('btn-prev')?.addEventListener('click', () => this.pasoAnterior());
        
        // Botón guardar progreso
        document.getElementById('btn-save-progress')?.addEventListener('click', () => this.guardarProgreso());
        
        // Formulario final
        document.getElementById('form-wizard-unified')?.addEventListener('submit', (e) => this.finalizarConsulta(e));
        
        // Navegación directa a pasos
        this.bindStepNavigation();
    }
    
    bindStepNavigation() {
        document.querySelectorAll('.step-item').forEach((item, index) => {
            item.addEventListener('click', () => {
                if (this.pasosCompletados.includes(index) || index <= this.pasoActual) {
                    this.navegarAPaso(index);
                }
            });
        });
    }
    
    mostrarAlerta(mensaje, tipo = 'info') {
        const alertContainer = document.getElementById('alert-container');
        if (!alertContainer) return;
        
        const alertId = 'alert-' + Date.now();
        
        const alertHtml = `
            <div id="${alertId}" class="alert alert-${tipo} alert-dismissible fade show" role="alert">
                ${mensaje}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        alertContainer.innerHTML = alertHtml;
        
        // Auto-dismiss después de 5 segundos
        setTimeout(() => {
            const alert = document.getElementById(alertId);
            if (alert) {
                alert.remove();
            }
        }, 5000);
    }
    
    actualizarIndicadorPasos() {
        const stepItems = document.querySelectorAll('.step-item');
        
        stepItems.forEach((item, index) => {
            item.classList.remove('active', 'completed', 'error');
            
            if (index < this.pasoActual) {
                item.classList.add('completed');
            } else if (index === this.pasoActual) {
                item.classList.add('active');
            }
        });
    }
    
    async cargarPaso(pasoIndex) {
        console.log('cargarPaso - paso:', pasoIndex, 'timestamp:', new Date().toISOString());
        //console.log('cargando:', this.cargando);
        if (this.cargando) return;
        
        this.cargando = true;
        const stepBody = document.getElementById(`step-body-${pasoIndex}`);
        
        if (!stepBody) {
            this.cargando = false;
            return;
        }
        
        // Mostrar loading
        stepBody.innerHTML = `
            <div class="loading">
                <div class="loading-spinner"></div>
                <p class="mt-2">Cargando formulario...</p>
            </div>
        `;
        
        try {
            const url = this.config.urls[pasoIndex];
            const params = new URLSearchParams({
                'id_consulta': this.getHiddenInputValue('id_consulta'),
                'id_persona': this.getHiddenInputValue('id_persona'),
                'paso': pasoIndex,
                'ajax': 1
            });
            
            const response = await fetch(`${url}?${params}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) {
                throw new Error('Error al cargar el paso');
            }
            
            const html = await response.text();
            stepBody.innerHTML = html;
            
            // El JavaScript se ejecuta automáticamente al insertar el HTML en el DOM
            
        } catch (error) {
            console.error('Error cargando paso:', error);
            stepBody.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i>
                    Error al cargar el formulario. Intente nuevamente.
                </div>
            `;
        } finally {
            this.cargando = false;
        }
    }
    
    async guardarPasoActual() {
        const pasoIndex = this.pasoActual;
        const stepBody = document.getElementById(`step-body-${pasoIndex}`);
        const form = stepBody?.querySelector('form');
        
        if (!form) {
            this.mostrarAlerta('No se encontró formulario para guardar', 'warning');
            return false;
        }
        
        const formData = new FormData(form);
        formData.append('guardar_progreso', '1');
        formData.append('paso', pasoIndex);
        formData.append('id_consulta', this.getHiddenInputValue('id_consulta'));
        formData.append('id_persona', this.getHiddenInputValue('id_persona'));
        formData.append('parent', this.getHiddenInputValue('parent'));
        formData.append('parent_id', this.getHiddenInputValue('parent_id'));
        
        try {
            const url = this.config.urls[pasoIndex];
            const response = await fetch(url, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Guardar estado del paso
                this.estadoGuardado[pasoIndex] = true;
                if (!this.pasosCompletados.includes(pasoIndex)) {
                    this.pasosCompletados.push(pasoIndex);
                }
                
                this.mostrarAlerta('Paso guardado correctamente', 'success');
                return true;
            } else {
                this.mostrarAlerta(result.msg || 'Error al guardar el paso', 'danger');
                return false;
            }
            
        } catch (error) {
            console.error('Error guardando paso:', error);
            this.mostrarAlerta('Error de conexión al guardar', 'danger');
            return false;
        }
    }
    
    async siguientePaso() {
        // Guardar el paso actual
        const guardado = await this.guardarPasoActual();
        if (!guardado) {
            return;
        }
        
        // Verificar si es el último paso
        if (this.pasoActual >= this.config.totalPasos - 1) {
            // Mostrar botón de finalizar
            this.mostrarBotonFinalizar();
            return;
        }
        
        // Ir al siguiente paso
        const siguientePaso = this.pasoActual + 1;
        this.navegarAPaso(siguientePaso);
    }
    
    pasoAnterior() {
        if (this.pasoActual <= 0) return;
        
        const pasoAnterior = this.pasoActual - 1;
        this.navegarAPaso(pasoAnterior);
    }
    
    navegarAPaso(pasoIndex) {
        // Ocultar paso actual
        const pasoActual = document.getElementById(`step-${this.pasoActual}`);
        if (pasoActual) {
            pasoActual.classList.remove('active');
        }
        
        // Mostrar paso seleccionado
        const pasoSeleccionado = document.getElementById(`step-${pasoIndex}`);
        if (pasoSeleccionado) {
            pasoSeleccionado.classList.add('active');
        }
        
        this.pasoActual = pasoIndex;
        this.actualizarIndicadorPasos();
        this.actualizarBotones();
        
        // Cargar contenido del paso si no está cargado
        const stepBody = document.getElementById(`step-body-${pasoIndex}`);
        if (stepBody && stepBody.querySelector('.text-muted')) {
            this.cargarPaso(pasoIndex);
        }
    }
    
    actualizarBotones() {
        const btnPrev = document.getElementById('btn-prev');
        const btnNext = document.getElementById('btn-next');
        const btnFinal = document.getElementById('btn-final-submit');
        
        if (btnPrev) {
            btnPrev.style.display = this.pasoActual > 0 ? 'inline-block' : 'none';
        }
        
        if (btnNext) {
            btnNext.style.display = this.pasoActual < this.config.totalPasos - 1 ? 'inline-block' : 'none';
        }
        
        if (btnFinal) {
            btnFinal.style.display = this.pasoActual >= this.config.totalPasos - 1 ? 'inline-block' : 'none';
        }
    }
    
    mostrarBotonFinalizar() {
        const btnNext = document.getElementById('btn-next');
        const btnFinal = document.getElementById('btn-final-submit');
        
        if (btnNext) btnNext.style.display = 'none';
        if (btnFinal) btnFinal.style.display = 'inline-block';
    }
    
    async guardarProgreso() {
        const guardado = await this.guardarPasoActual();
        if (guardado) {
            this.mostrarAlerta('Progreso guardado correctamente', 'success');
        }
    }
    
    async finalizarConsulta(e) {
        e.preventDefault();
        
        // Guardar el paso actual antes de finalizar
        const guardado = await this.guardarPasoActual();
        if (!guardado) {
            return;
        }
        
        // Mostrar confirmación
        if (confirm('¿Está seguro que desea finalizar la consulta? Esta acción no se puede deshacer.')) {
            // Enviar formulario final
            e.target.submit();
        }
    }
    
    getHiddenInputValue(name) {
        const input = document.querySelector(`input[name="${name}"]`);
        return input ? input.value : '';
    }
    
}

// Las funciones globales han sido eliminadas - todo se maneja a través de la clase ConsultaUnificada

// Función para inicializar el wizard
function inicializarWizard() {
    if (window.FormWizardConfig && !window.consultaUnificada) {
        console.log('Inicializando ConsultaUnificada...');
        
        // Usar la configuración global FormWizardConfig
        window.consultaUnificada = new ConsultaUnificada(window.FormWizardConfig);
        
        // El primer paso ya se carga en el constructor de ConsultaUnificada
        
        // Event listeners - usar métodos de la clase
        document.getElementById('btn-next')?.addEventListener('click', () => window.consultaUnificada.siguientePaso());
        document.getElementById('btn-prev')?.addEventListener('click', () => window.consultaUnificada.pasoAnterior());
        
        // Formulario final - usar método de la clase
        document.getElementById('form-wizard-unified')?.addEventListener('submit', (e) => window.consultaUnificada.finalizarConsulta(e), true);
        
        // Permitir navegación directa a pasos completados - usar método de la clase
        document.querySelectorAll('.step-item').forEach((item, index) => {
            item.addEventListener('click', function() {
                if (window.FormWizardConfig.pasosCompletados.includes(index) || index <= window.FormWizardConfig.pasoActual) {
                    window.consultaUnificada.navegarAPaso(index);
                }
            });
        });
    }
}

// Función para detectar cuando el wizard se carga en el modal
function detectarWizardEnModal() {
    // Verificar si el wizard está presente en el modal
    const wizardForm = document.querySelector('#form-wizard-unified');
    const modalBody = document.querySelector('#modal-consulta .modal-body');
    
    if (wizardForm && !window.consultaUnificada && modalBody && modalBody.contains(wizardForm)) {
        console.log('Wizard detectado en modal, inicializando...');
        // Pequeño delay para asegurar que el DOM esté completamente renderizado
        setTimeout(() => {
            inicializarWizard();
        }, 50);
    }
}

// Observador para detectar cambios en el modal
const modalObserver = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.type === 'childList') {
            // Verificar si se agregó contenido al modal
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === 1) { // Element node
                    // Verificar si el contenido contiene el wizard
                    if (node.querySelector && node.querySelector('#form-wizard-unified')) {
                        detectarWizardEnModal();
                    }
                    // También verificar si el nodo mismo es el wizard
                    if (node.id === 'form-wizard-unified') {
                        detectarWizardEnModal();
                    }
                }
            });
        }
    });
});

// Observar cambios en el modal de consulta
const modalConsulta = document.getElementById('modal-consulta');
if (modalConsulta) {
    modalObserver.observe(modalConsulta, {
        childList: true,
        subtree: true
    });
    
    // Limpiar wizard cuando se cierre el modal
    modalConsulta.addEventListener('hidden.bs.modal', function() {
        if (window.consultaUnificada) {
            console.log('Modal cerrado, limpiando wizard...');
            window.consultaUnificada = null;
        }
    });
}

// Inicializar cuando el DOM esté listo (para páginas normales)
document.addEventListener('DOMContentLoaded', function() {
    // Verificar si el wizard ya está presente
    detectarWizardEnModal();
});

// Inicializar inmediatamente si ya está disponible
if (document.readyState !== 'loading') {
    setTimeout(detectarWizardEnModal, 100);
}
