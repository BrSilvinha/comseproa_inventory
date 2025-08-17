/**
 * Sistema de Notificaciones Toast Modernas
 * Notificaciones elegantes y no intrusivas
 */

class ToastNotifications {
    constructor() {
        this.container = null;
        this.toasts = new Map();
        this.defaultOptions = {
            duration: 5000,
            position: 'top-right',
            closable: true,
            progress: true,
            animation: 'slide',
            pauseOnHover: true,
            icon: true
        };
        
        this.init();
    }
    
    init() {
        this.createContainer();
        this.setupStyles();
        
        // Exponer métodos globalmente
        window.Toast = {
            success: (message, options) => this.success(message, options),
            error: (message, options) => this.error(message, options),
            warning: (message, options) => this.warning(message, options),
            info: (message, options) => this.info(message, options),
            custom: (message, options) => this.show(message, options),
            clear: () => this.clearAll(),
            remove: (id) => this.remove(id)
        };
    }
    
    createContainer() {
        this.container = document.createElement('div');
        this.container.className = 'toast-container';
        this.container.id = 'toast-container';
        document.body.appendChild(this.container);
    }
    
    success(message, options = {}) {
        return this.show(message, {
            ...options,
            type: 'success',
            icon: '✓'
        });
    }
    
    error(message, options = {}) {
        return this.show(message, {
            ...options,
            type: 'error',
            icon: '✕',
            duration: 7000 // Errores duran más
        });
    }
    
    warning(message, options = {}) {
        return this.show(message, {
            ...options,
            type: 'warning',
            icon: '⚠'
        });
    }
    
    info(message, options = {}) {
        return this.show(message, {
            ...options,
            type: 'info',
            icon: 'ℹ'
        });
    }
    
    show(message, options = {}) {
        const config = { ...this.defaultOptions, ...options };
        const id = this.generateId();
        
        const toast = this.createToast(id, message, config);
        this.toasts.set(id, { element: toast, config: config });
        
        this.container.appendChild(toast);
        
        // Trigger animation
        requestAnimationFrame(() => {
            toast.classList.add('toast-show');
        });
        
        // Auto remove
        if (config.duration > 0) {
            this.scheduleRemoval(id, config.duration);
        }
        
        // Setup event listeners
        this.setupToastEvents(toast, id, config);
        
        return id;
    }
    
    createToast(id, message, config) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${config.type} toast-${config.animation}`;
        toast.setAttribute('data-toast-id', id);
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'polite');
        
        const iconHtml = config.icon && config.icon !== true 
            ? `<div class="toast-icon">${config.icon}</div>` 
            : '';
            
        const closeHtml = config.closable 
            ? '<button type="button" class="toast-close" aria-label="Cerrar">&times;</button>'
            : '';
            
        const progressHtml = config.progress && config.duration > 0
            ? '<div class="toast-progress"><div class="toast-progress-bar"></div></div>'
            : '';
            
        toast.innerHTML = `
            <div class="toast-content">
                ${iconHtml}
                <div class="toast-message">${this.escapeHtml(message)}</div>
                ${closeHtml}
            </div>
            ${progressHtml}
        `;
        
        return toast;
    }
    
    setupToastEvents(toast, id, config) {
        let timer = null;
        let progressAnimation = null;
        
        // Close button
        const closeBtn = toast.querySelector('.toast-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                this.remove(id);
            });
        }
        
        // Progress bar animation
        if (config.progress && config.duration > 0) {
            const progressBar = toast.querySelector('.toast-progress-bar');
            if (progressBar) {
                progressAnimation = progressBar.animate([
                    { width: '100%' },
                    { width: '0%' }
                ], {
                    duration: config.duration,
                    easing: 'linear'
                });
            }
        }
        
        // Pause on hover
        if (config.pauseOnHover && config.duration > 0) {
            toast.addEventListener('mouseenter', () => {
                if (timer) {
                    clearTimeout(timer);
                }
                if (progressAnimation) {
                    progressAnimation.pause();
                }
            });
            
            toast.addEventListener('mouseleave', () => {
                const remainingTime = this.getRemainingTime(progressAnimation, config.duration);
                if (remainingTime > 0) {
                    this.scheduleRemoval(id, remainingTime);
                    if (progressAnimation) {
                        progressAnimation.play();
                    }
                }
            });
        }
        
        // Click to dismiss (optional)
        if (config.clickToDismiss) {
            toast.addEventListener('click', (e) => {
                if (!e.target.closest('.toast-close')) {
                    this.remove(id);
                }
            });
        }
        
        // Store references for cleanup
        this.toasts.get(id).timer = timer;
        this.toasts.get(id).progressAnimation = progressAnimation;
    }
    
    scheduleRemoval(id, duration) {
        const timer = setTimeout(() => {
            this.remove(id);
        }, duration);
        
        const toastData = this.toasts.get(id);
        if (toastData) {
            toastData.timer = timer;
        }
    }
    
    remove(id) {
        const toastData = this.toasts.get(id);
        if (!toastData) return;
        
        const { element, timer, progressAnimation } = toastData;
        
        // Clear timer and animation
        if (timer) clearTimeout(timer);
        if (progressAnimation) progressAnimation.cancel();
        
        // Remove with animation
        element.classList.add('toast-hide');
        
        element.addEventListener('animationend', () => {
            if (element.parentNode) {
                element.parentNode.removeChild(element);
            }
            this.toasts.delete(id);
        }, { once: true });
    }
    
    clearAll() {
        this.toasts.forEach((_, id) => {
            this.remove(id);
        });
    }
    
    getRemainingTime(animation, totalDuration) {
        if (!animation) return 0;
        
        const currentTime = animation.currentTime || 0;
        return Math.max(0, totalDuration - currentTime);
    }
    
    generateId() {
        return 'toast-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    setupStyles() {
        if (document.getElementById('toast-styles')) return;
        
        const styles = document.createElement('style');
        styles.id = 'toast-styles';
        styles.textContent = `
            .toast-container {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                display: flex;
                flex-direction: column;
                gap: 12px;
                pointer-events: none;
                max-width: 400px;
            }
            
            .toast {
                background: white;
                border-radius: 12px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
                border: 1px solid #e2e8f0;
                overflow: hidden;
                pointer-events: auto;
                position: relative;
                transform: translateX(100%);
                transition: transform 0.3s ease-out, opacity 0.3s ease-out;
                opacity: 0;
                max-width: 100%;
                min-width: 300px;
            }
            
            .toast-show {
                transform: translateX(0);
                opacity: 1;
            }
            
            .toast-hide {
                animation: toastHide 0.3s ease-in forwards;
            }
            
            @keyframes toastHide {
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
            
            .toast-content {
                display: flex;
                align-items: flex-start;
                padding: 16px;
                gap: 12px;
            }
            
            .toast-icon {
                font-size: 20px;
                line-height: 1;
                margin-top: 2px;
                flex-shrink: 0;
            }
            
            .toast-message {
                flex: 1;
                font-size: 14px;
                line-height: 1.4;
                color: #374151;
                word-wrap: break-word;
            }
            
            .toast-close {
                background: none;
                border: none;
                font-size: 20px;
                line-height: 1;
                color: #6b7280;
                cursor: pointer;
                padding: 0;
                margin-left: 8px;
                transition: color 0.2s ease;
                flex-shrink: 0;
            }
            
            .toast-close:hover {
                color: #374151;
            }
            
            .toast-progress {
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                height: 3px;
                background: rgba(0, 0, 0, 0.1);
            }
            
            .toast-progress-bar {
                height: 100%;
                background: currentColor;
                width: 100%;
            }
            
            /* Toast Types */
            .toast-success {
                border-left: 4px solid #10b981;
            }
            
            .toast-success .toast-icon {
                color: #10b981;
            }
            
            .toast-success .toast-progress-bar {
                background: #10b981;
            }
            
            .toast-error {
                border-left: 4px solid #ef4444;
            }
            
            .toast-error .toast-icon {
                color: #ef4444;
            }
            
            .toast-error .toast-progress-bar {
                background: #ef4444;
            }
            
            .toast-warning {
                border-left: 4px solid #f59e0b;
            }
            
            .toast-warning .toast-icon {
                color: #f59e0b;
            }
            
            .toast-warning .toast-progress-bar {
                background: #f59e0b;
            }
            
            .toast-info {
                border-left: 4px solid #3b82f6;
            }
            
            .toast-info .toast-icon {
                color: #3b82f6;
            }
            
            .toast-info .toast-progress-bar {
                background: #3b82f6;
            }
            
            /* Dark mode */
            [data-theme="dark"] .toast {
                background: #374151;
                border-color: #4b5563;
            }
            
            [data-theme="dark"] .toast-message {
                color: #f3f4f6;
            }
            
            [data-theme="dark"] .toast-close {
                color: #9ca3af;
            }
            
            [data-theme="dark"] .toast-close:hover {
                color: #f3f4f6;
            }
            
            /* Responsive */
            @media (max-width: 480px) {
                .toast-container {
                    top: 10px;
                    right: 10px;
                    left: 10px;
                    max-width: none;
                }
                
                .toast {
                    min-width: auto;
                }
            }
        `;
        
        document.head.appendChild(styles);
    }
}

// Inicializar automáticamente
document.addEventListener('DOMContentLoaded', () => {
    new ToastNotifications();
});