/**
 * Theme Manager - Sistema de gestión de temas
 * Maneja tema claro/oscuro con detección automática del sistema
 */

class ThemeManager {
    constructor() {
        this.themes = ['light', 'dark', 'auto'];
        this.currentTheme = this.loadSavedTheme();
        this.init();
    }

    init() {
        // Aplicar tema inicial
        this.applyTheme();
        
        // Crear botón de toggle si no existe
        this.createThemeToggle();
        
        // Escuchar cambios en preferencias del sistema
        this.listenToSystemChanges();
        
        // Configurar eventos
        this.setupEventListeners();
        
        console.log('✅ Theme Manager initialized with theme:', this.currentTheme);
    }

    loadSavedTheme() {
        return localStorage.getItem('theme') || 'auto';
    }

    saveTheme(theme) {
        localStorage.setItem('theme', theme);
        this.currentTheme = theme;
    }

    getSystemTheme() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    getCurrentEffectiveTheme() {
        if (this.currentTheme === 'auto') {
            return this.getSystemTheme();
        }
        return this.currentTheme;
    }

    applyTheme() {
        const effectiveTheme = this.getCurrentEffectiveTheme();
        
        // Aplicar al documento
        document.documentElement.setAttribute('data-theme', effectiveTheme);
        document.body.classList.remove('light-theme', 'dark-theme');
        document.body.classList.add(`${effectiveTheme}-theme`);
        
        // Actualizar meta theme-color
        const metaThemeColor = document.querySelector('meta[name="theme-color"]');
        if (metaThemeColor) {
            metaThemeColor.content = effectiveTheme === 'dark' ? '#1a1a1a' : '#ffffff';
        }
        
        // Actualizar favicon si es necesario
        this.updateFavicon(effectiveTheme);
        
        // Trigger evento personalizado
        document.dispatchEvent(new CustomEvent('themeChanged', {
            detail: { theme: effectiveTheme }
        }));
    }

    updateFavicon(theme) {
        const favicon = document.querySelector('link[rel="icon"]');
        if (favicon) {
            const currentHref = favicon.href;
            if (theme === 'dark' && !currentHref.includes('-dark')) {
                favicon.href = currentHref.replace('.ico', '-dark.ico');
            } else if (theme === 'light' && currentHref.includes('-dark')) {
                favicon.href = currentHref.replace('-dark.ico', '.ico');
            }
        }
    }

    createThemeToggle() {
        if (document.getElementById('theme-toggle')) {
            return; // Ya existe
        }

        const toggle = document.createElement('button');
        toggle.id = 'theme-toggle';
        toggle.className = 'theme-toggle';
        toggle.setAttribute('aria-label', 'Cambiar tema');
        toggle.innerHTML = this.getToggleIcon();

        // Agregar al header o crear contenedor
        const header = document.querySelector('header') || document.querySelector('.content header');
        if (header) {
            header.appendChild(toggle);
        } else {
            // Crear contenedor flotante
            toggle.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1000;
                background: var(--bg-primary, #fff);
                border: 1px solid var(--border-color, #ddd);
                border-radius: 50%;
                width: 48px;
                height: 48px;
                cursor: pointer;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                transition: all 0.3s ease;
            `;
            document.body.appendChild(toggle);
        }
    }

    getToggleIcon() {
        const theme = this.getCurrentEffectiveTheme();
        if (theme === 'dark') {
            return '<i class="fas fa-sun" title="Cambiar a tema claro"></i>';
        } else {
            return '<i class="fas fa-moon" title="Cambiar a tema oscuro"></i>';
        }
    }

    setupEventListeners() {
        const toggle = document.getElementById('theme-toggle');
        if (toggle) {
            toggle.addEventListener('click', () => this.toggleTheme());
        }

        // Keyboard shortcut (Ctrl/Cmd + Shift + T)
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'T') {
                e.preventDefault();
                this.toggleTheme();
            }
        });
    }

    listenToSystemChanges() {
        if (window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            mediaQuery.addEventListener('change', () => {
                if (this.currentTheme === 'auto') {
                    this.applyTheme();
                    this.updateToggleIcon();
                }
            });
        }
    }

    toggleTheme() {
        const currentEffective = this.getCurrentEffectiveTheme();
        const newTheme = currentEffective === 'dark' ? 'light' : 'dark';
        
        this.setTheme(newTheme);
        
        // Animación suave
        document.body.style.transition = 'background-color 0.3s ease, color 0.3s ease';
        setTimeout(() => {
            document.body.style.transition = '';
        }, 300);
    }

    setTheme(theme) {
        if (this.themes.includes(theme)) {
            this.saveTheme(theme);
            this.applyTheme();
            this.updateToggleIcon();
            
            console.log('🎨 Theme changed to:', theme);
        }
    }

    updateToggleIcon() {
        const toggle = document.getElementById('theme-toggle');
        if (toggle) {
            toggle.innerHTML = this.getToggleIcon();
        }
    }

    // Métodos públicos para integración
    getCurrentTheme() {
        return this.currentTheme;
    }

    isDarkMode() {
        return this.getCurrentEffectiveTheme() === 'dark';
    }

    // Método para otros scripts
    onThemeChange(callback) {
        document.addEventListener('themeChanged', callback);
    }
}

// CSS para el toggle
const themeToggleStyles = `
    <style id="theme-toggle-styles">
        .theme-toggle {
            background: var(--bg-primary, #ffffff);
            border: 1px solid var(--border-color, #e0e0e0);
            border-radius: 6px;
            padding: 8px 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--text-primary, #333);
        }
        
        .theme-toggle:hover {
            background: var(--bg-hover, #f5f5f5);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .theme-toggle i {
            font-size: 16px;
        }
        
        /* Dark theme styles */
        [data-theme="dark"] .theme-toggle {
            background: var(--bg-primary, #2d3748);
            border-color: var(--border-color, #4a5568);
            color: var(--text-primary, #e2e8f0);
        }
        
        [data-theme="dark"] .theme-toggle:hover {
            background: var(--bg-hover, #4a5568);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .theme-toggle {
                padding: 6px 10px;
            }
            .theme-toggle i {
                font-size: 14px;
            }
        }
    </style>
`;

// Insertar estilos
document.head.insertAdjacentHTML('beforeend', themeToggleStyles);

// Inicializar automáticamente
document.addEventListener('DOMContentLoaded', () => {
    window.themeManager = new ThemeManager();
});

// Exportar para uso global
window.ThemeManager = ThemeManager;