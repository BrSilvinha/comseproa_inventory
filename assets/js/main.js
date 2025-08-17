/**
 * COMSEPROA Inventory System - JavaScript Principal
 * Funciones y utilidades globales del sistema
 */

// Configuración global
const COMSEPROA = {
    config: {
        csrfToken: null,
        baseUrl: '',
        userId: null,
        userRole: null
    },
    
    // Cache para datos frecuentemente usados
    cache: new Map(),
    
    // Estado de la aplicación
    state: {
        loading: false,
        notifications: []
    }
};

/**
 * Inicialización del sistema
 */
document.addEventListener('DOMContentLoaded', function() {
    COMSEPROA.init();
});

/**
 * Función principal de inicialización
 */
COMSEPROA.init = function() {
    this.setupCSRF();
    this.setupAjaxDefaults();
    this.setupGlobalEventListeners();
    this.setupNotifications();
    this.setupFormValidation();
    this.setupTableEnhancements();
    console.log('COMSEPROA System initialized');
};

/**
 * Configuración de CSRF Token
 */
COMSEPROA.setupCSRF = function() {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
        this.config.csrfToken = csrfMeta.getAttribute('content');
    }
};

/**
 * Configuración por defecto para AJAX
 */
COMSEPROA.setupAjaxDefaults = function() {
    // Agregar CSRF token a todas las peticiones AJAX
    const originalFetch = window.fetch;
    window.fetch = function(url, options = {}) {
        if (options.method && options.method.toUpperCase() !== 'GET') {
            options.headers = options.headers || {};
            if (COMSEPROA.config.csrfToken) {
                options.headers['X-CSRF-Token'] = COMSEPROA.config.csrfToken;
            }
        }
        return originalFetch(url, options);
    };
};

/**
 * Event listeners globales
 */
COMSEPROA.setupGlobalEventListeners = function() {
    // Confirmación para enlaces de eliminación
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('delete-link') || 
            e.target.closest('.delete-link')) {
            e.preventDefault();
            const link = e.target.classList.contains('delete-link') ? 
                        e.target : e.target.closest('.delete-link');
            COMSEPROA.confirmDelete(link);
        }
    });

    // Auto-submit para filtros de búsqueda
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('auto-submit')) {
            e.target.form.submit();
        }
    });

    // Cerrar notificaciones
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('notification-close')) {
            e.preventDefault();
            COMSEPROA.closeNotification(e.target.closest('.notification'));
        }
    });
};

/**
 * Sistema de notificaciones
 */
COMSEPROA.setupNotifications = function() {
    // Auto-cerrar notificaciones después de 5 segundos
    const notifications = document.querySelectorAll('.alert');
    notifications.forEach(notification => {
        if (!notification.classList.contains('alert-permanent')) {
            setTimeout(() => {
                this.closeNotification(notification);
            }, 5000);
        }
    });
};

/**
 * Mostrar notificación
 */
COMSEPROA.showNotification = function(message, type = 'info', duration = 5000) {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} notification-toast`;
    notification.innerHTML = `
        <div class="d-flex justify-content-between align-items-center">
            <span>${this.escapeHtml(message)}</span>
            <button type="button" class="btn-close notification-close" aria-label="Cerrar"></button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-cerrar
    setTimeout(() => {
        this.closeNotification(notification);
    }, duration);
    
    return notification;
};

/**
 * Cerrar notificación
 */
COMSEPROA.closeNotification = function(notification) {
    if (notification) {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }
};

/**
 * Confirmación de eliminación
 */
COMSEPROA.confirmDelete = function(link) {
    const message = link.getAttribute('data-message') || 
                   '¿Está seguro de que desea eliminar este elemento?';
    const title = link.getAttribute('data-title') || 'Confirmar eliminación';
    
    if (confirm(`${title}\n\n${message}\n\nEsta acción no se puede deshacer.`)) {
        // Mostrar loading
        this.showLoading();
        window.location.href = link.href;
    }
};

/**
 * Mostrar/ocultar loading
 */
COMSEPROA.showLoading = function() {
    this.state.loading = true;
    let loader = document.getElementById('global-loader');
    
    if (!loader) {
        loader = document.createElement('div');
        loader.id = 'global-loader';
        loader.className = 'global-loader';
        loader.innerHTML = `
            <div class="loader-content">
                <div class="spinner"></div>
                <p>Cargando...</p>
            </div>
        `;
        document.body.appendChild(loader);
    }
    
    loader.style.display = 'flex';
};

COMSEPROA.hideLoading = function() {
    this.state.loading = false;
    const loader = document.getElementById('global-loader');
    if (loader) {
        loader.style.display = 'none';
    }
};

/**
 * Validación de formularios
 */
COMSEPROA.setupFormValidation = function() {
    // Validación en tiempo real
    document.addEventListener('input', function(e) {
        if (e.target.hasAttribute('data-validate')) {
            COMSEPROA.validateField(e.target);
        }
    });

    // Validación al enviar formulario
    document.addEventListener('submit', function(e) {
        if (e.target.hasAttribute('data-validate-form')) {
            if (!COMSEPROA.validateForm(e.target)) {
                e.preventDefault();
            }
        }
    });
};

/**
 * Validar campo individual
 */
COMSEPROA.validateField = function(field) {
    const rules = field.getAttribute('data-validate').split('|');
    let isValid = true;
    let message = '';

    for (let rule of rules) {
        const [ruleName, ruleValue] = rule.split(':');
        
        switch (ruleName) {
            case 'required':
                if (!field.value.trim()) {
                    isValid = false;
                    message = 'Este campo es requerido';
                }
                break;
                
            case 'email':
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (field.value && !emailRegex.test(field.value)) {
                    isValid = false;
                    message = 'Ingrese un email válido';
                }
                break;
                
            case 'min':
                if (field.value.length < parseInt(ruleValue)) {
                    isValid = false;
                    message = `Mínimo ${ruleValue} caracteres`;
                }
                break;
                
            case 'max':
                if (field.value.length > parseInt(ruleValue)) {
                    isValid = false;
                    message = `Máximo ${ruleValue} caracteres`;
                }
                break;
                
            case 'numeric':
                if (field.value && isNaN(field.value)) {
                    isValid = false;
                    message = 'Debe ser un número';
                }
                break;
        }
        
        if (!isValid) break;
    }

    this.setFieldValidation(field, isValid, message);
    return isValid;
};

/**
 * Establecer estado de validación de campo
 */
COMSEPROA.setFieldValidation = function(field, isValid, message) {
    const formGroup = field.closest('.form-group');
    let feedback = formGroup ? formGroup.querySelector('.invalid-feedback') : null;

    // Remover clases existentes
    field.classList.remove('is-valid', 'is-invalid');

    if (!isValid) {
        field.classList.add('is-invalid');
        
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            field.parentNode.appendChild(feedback);
        }
        feedback.textContent = message;
    } else {
        field.classList.add('is-valid');
        if (feedback) {
            feedback.remove();
        }
    }
};

/**
 * Validar formulario completo
 */
COMSEPROA.validateForm = function(form) {
    const fields = form.querySelectorAll('[data-validate]');
    let isValid = true;

    fields.forEach(field => {
        if (!this.validateField(field)) {
            isValid = false;
        }
    });

    return isValid;
};

/**
 * Mejoras para tablas
 */
COMSEPROA.setupTableEnhancements = function() {
    // Hacer tablas responsivas
    const tables = document.querySelectorAll('.table');
    tables.forEach(table => {
        if (!table.closest('.table-responsive')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'table-responsive';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        }
    });

    // Ordenamiento de columnas
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('sortable')) {
            COMSEPROA.sortTable(e.target);
        }
    });
};

/**
 * Ordenar tabla por columna
 */
COMSEPROA.sortTable = function(header) {
    const table = header.closest('table');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const columnIndex = Array.from(header.parentNode.children).indexOf(header);
    const isAscending = header.classList.contains('sort-asc');

    // Ordenar filas
    rows.sort((a, b) => {
        const aValue = a.children[columnIndex].textContent.trim();
        const bValue = b.children[columnIndex].textContent.trim();
        
        // Detectar si son números
        const aNum = parseFloat(aValue);
        const bNum = parseFloat(bValue);
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return isAscending ? bNum - aNum : aNum - bNum;
        } else {
            return isAscending ? 
                   bValue.localeCompare(aValue) : 
                   aValue.localeCompare(bValue);
        }
    });

    // Actualizar tabla
    rows.forEach(row => tbody.appendChild(row));

    // Actualizar indicadores de orden
    table.querySelectorAll('.sortable').forEach(th => {
        th.classList.remove('sort-asc', 'sort-desc');
    });
    
    header.classList.add(isAscending ? 'sort-desc' : 'sort-asc');
};

/**
 * Utilidades generales
 */
COMSEPROA.escapeHtml = function(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
};

COMSEPROA.formatCurrency = function(amount) {
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN'
    }).format(amount);
};

COMSEPROA.formatDate = function(date) {
    return new Intl.DateTimeFormat('es-PE', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    }).format(new Date(date));
};

COMSEPROA.debounce = function(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
};

// Exponer funciones globalmente para compatibilidad
window.showNotification = COMSEPROA.showNotification.bind(COMSEPROA);
window.confirmDelete = COMSEPROA.confirmDelete.bind(COMSEPROA);
window.showLoading = COMSEPROA.showLoading.bind(COMSEPROA);
window.hideLoading = COMSEPROA.hideLoading.bind(COMSEPROA);