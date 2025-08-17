/**
 * Theme Manager - Dark Mode y gestión de temas
 * Persistencia en localStorage y transiciones suaves
 */

class ThemeManager {
    constructor() {
        this.currentTheme = 'light';
        this.storageKey = 'comseproa-theme';
        this.themes = {
            light: {
                name: 'Claro',
                icon: '☀️'
            },
            dark: {
                name: 'Oscuro', 
                icon: '🌙'
            },
            auto: {
                name: 'Automático',
                icon: '🌓'
            }
        };
        
        this.init();
    }
    
    init() {
        this.loadTheme();
        this.createThemeToggle();
        this.setupSystemThemeListener();
        this.setupThemeTransitions();
        
        // Exposer globalmente
        window.ThemeManager = this;
    }
    
    loadTheme() {
        // Cargar tema guardado o usar preferencia del sistema
        const savedTheme = localStorage.getItem(this.storageKey);
        const systemTheme = this.getSystemTheme();
        
        if (savedTheme && this.themes[savedTheme]) {
            this.currentTheme = savedTheme;
        } else {
            this.currentTheme = 'auto';
        }
        
        this.applyTheme();
    }
    
    getSystemTheme() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    
    getEffectiveTheme() {
        if (this.currentTheme === 'auto') {
            return this.getSystemTheme();
        }
        return this.currentTheme;
    }
    
    applyTheme() {
        const effectiveTheme = this.getEffectiveTheme();
        
        // Aplicar al documento
        document.documentElement.setAttribute('data-theme', effectiveTheme);
        document.body.className = document.body.className.replace(/theme-\w+/g, '');
        document.body.classList.add(`theme-${effectiveTheme}`);
        
        // Actualizar meta theme-color para móviles
        this.updateMetaThemeColor(effectiveTheme);
        
        // Emitir evento para componentes que necesiten reaccionar
        const event = new CustomEvent('themeChanged', {
            detail: {
                theme: effectiveTheme,
                previousTheme: this.previousTheme
            }
        });
        document.dispatchEvent(event);
        
        this.previousTheme = effectiveTheme;
        
        // Actualizar toggle
        this.updateThemeToggle();
    }
    
    updateMetaThemeColor(theme) {
        const colors = {
            light: '#ffffff',
            dark: '#0f172a'
        };
        
        let metaThemeColor = document.querySelector('meta[name="theme-color"]');
        if (!metaThemeColor) {
            metaThemeColor = document.createElement('meta');
            metaThemeColor.name = 'theme-color';
            document.head.appendChild(metaThemeColor);
        }
        
        metaThemeColor.content = colors[theme] || colors.light;
    }
    
    setTheme(theme) {
        if (!this.themes[theme]) return;
        
        this.currentTheme = theme;
        localStorage.setItem(this.storageKey, theme);
        this.applyTheme();
        
        // Feedback visual
        if (window.Toast) {
            const themeName = this.themes[theme].name;
            const icon = this.themes[theme].icon;
            Toast.info(`${icon} Tema cambiado a ${themeName}`, { duration: 2000 });
        }
    }
    
    toggleTheme() {
        const themes = Object.keys(this.themes);
        const currentIndex = themes.indexOf(this.currentTheme);
        const nextIndex = (currentIndex + 1) % themes.length;
        const nextTheme = themes[nextIndex];
        
        this.setTheme(nextTheme);
    }
    
    createThemeToggle() {
        // Buscar container existente o crear uno
        let toggleContainer = document.querySelector('.theme-toggle-container');
        
        if (!toggleContainer) {
            toggleContainer = document.createElement('div');
            toggleContainer.className = 'theme-toggle-container';
            
            // Añadir al header del dashboard si existe
            const dashboardHeader = document.querySelector('.dashboard-header');
            if (dashboardHeader) {
                dashboardHeader.appendChild(toggleContainer);
            } else {
                // Añadir a la parte superior derecha
                toggleContainer.style.position = 'fixed';
                toggleContainer.style.top = '20px';
                toggleContainer.style.right = '20px';
                toggleContainer.style.zIndex = '1000';
                document.body.appendChild(toggleContainer);
            }
        }
        
        toggleContainer.innerHTML = `
            <div class="theme-toggle" role="button" tabindex="0" aria-label="Cambiar tema">
                <span class="theme-toggle-icon">${this.themes[this.currentTheme].icon}</span>
                <span class="theme-toggle-text">${this.themes[this.currentTheme].name}</span>
                <div class="theme-dropdown">
                    <div class="theme-options">
                        ${Object.entries(this.themes).map(([key, theme]) => `
                            <button type="button" class="theme-option ${key === this.currentTheme ? 'active' : ''}" 
                                    data-theme="${key}">
                                <span class="theme-option-icon">${theme.icon}</span>
                                <span class="theme-option-text">${theme.name}</span>
                                ${key === this.currentTheme ? '<i class="fas fa-check"></i>' : ''}
                            </button>
                        `).join('')}
                    </div>
                </div>
            </div>
        `;
        
        this.setupToggleEvents(toggleContainer);
    }
    
    setupToggleEvents(container) {
        const toggle = container.querySelector('.theme-toggle');
        const dropdown = container.querySelector('.theme-dropdown');
        const options = container.querySelectorAll('.theme-option');
        
        // Toggle dropdown
        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('show');
        });
        
        // Keyboard support
        toggle.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                dropdown.classList.toggle('show');
            }
        });
        
        // Option selection
        options.forEach(option => {
            option.addEventListener('click', (e) => {
                e.stopPropagation();
                const theme = option.dataset.theme;
                this.setTheme(theme);
                dropdown.classList.remove('show');
            });
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!container.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });
        
        // Close dropdown on escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                dropdown.classList.remove('show');
            }
        });
    }
    
    updateThemeToggle() {
        const toggleIcon = document.querySelector('.theme-toggle-icon');
        const toggleText = document.querySelector('.theme-toggle-text');
        const options = document.querySelectorAll('.theme-option');
        
        if (toggleIcon) {
            toggleIcon.textContent = this.themes[this.currentTheme].icon;
        }
        
        if (toggleText) {
            toggleText.textContent = this.themes[this.currentTheme].name;
        }
        
        options.forEach(option => {
            const theme = option.dataset.theme;
            option.classList.toggle('active', theme === this.currentTheme);
            
            const checkIcon = option.querySelector('.fas');
            if (checkIcon) {
                checkIcon.style.display = theme === this.currentTheme ? 'inline' : 'none';
            }
        });
    }
    
    setupSystemThemeListener() {
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        
        mediaQuery.addEventListener('change', () => {
            if (this.currentTheme === 'auto') {
                this.applyTheme();
            }
        });
    }
    
    setupThemeTransitions() {
        // Añadir estilos de transición
        const style = document.createElement('style');
        style.textContent = `
            * {
                transition: background-color 0.3s ease, 
                           color 0.3s ease, 
                           border-color 0.3s ease,
                           box-shadow 0.3s ease !important;
            }
            
            /* Theme Toggle Styles */
            .theme-toggle-container {
                position: relative;
                display: inline-block;
            }
            
            .theme-toggle {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.5rem 1rem;
                background: var(--bg-card, #ffffff);
                border: 1px solid var(--border-color, #e2e8f0);
                border-radius: 8px;
                cursor: pointer;
                transition: all 0.2s ease;
                user-select: none;
                min-width: 120px;
            }
            
            .theme-toggle:hover {
                background: var(--bg-hover, #f1f5f9);
                border-color: var(--primary-color, #3b82f6);
            }
            
            .theme-toggle:focus {
                outline: 2px solid var(--primary-color, #3b82f6);
                outline-offset: 2px;
            }
            
            .theme-toggle-icon {
                font-size: 1.2em;
                line-height: 1;
            }
            
            .theme-toggle-text {
                font-size: 0.875rem;
                font-weight: 500;
                color: var(--text-primary, #1e293b);
            }
            
            .theme-dropdown {
                position: absolute;
                top: 100%;
                right: 0;
                margin-top: 0.5rem;
                background: var(--bg-card, #ffffff);
                border: 1px solid var(--border-color, #e2e8f0);
                border-radius: 8px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
                z-index: 1000;
                opacity: 0;
                visibility: hidden;
                transform: translateY(-10px);
                transition: all 0.2s ease;
                min-width: 150px;
            }
            
            .theme-dropdown.show {
                opacity: 1;
                visibility: visible;
                transform: translateY(0);
            }
            
            .theme-options {
                padding: 0.5rem;
                display: flex;
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .theme-option {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 0.5rem 0.75rem;
                background: none;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                transition: all 0.2s ease;
                font-size: 0.875rem;
                text-align: left;
                width: 100%;
            }
            
            .theme-option:hover {
                background: var(--bg-hover, #f1f5f9);
            }
            
            .theme-option.active {
                background: var(--primary-color, #3b82f6);
                color: white;
            }
            
            .theme-option-icon {
                font-size: 1.1em;
                line-height: 1;
            }
            
            .theme-option-text {
                flex: 1;
                font-weight: 500;
            }
            
            .theme-option .fas {
                font-size: 0.8em;
                margin-left: auto;
            }
            
            /* Dark theme variables */
            [data-theme="dark"] {
                --bg-primary: #0f172a;
                --bg-secondary: #1e293b;
                --bg-card: #334155;
                --bg-hover: #475569;
                --text-primary: #f1f5f9;
                --text-secondary: #cbd5e1;
                --text-muted: #94a3b8;
                --border-color: #475569;
                --primary-color: #60a5fa;
                --success-color: #34d399;
                --warning-color: #fbbf24;
                --danger-color: #f87171;
                --info-color: #38bdf8;
            }
            
            /* Mobile adjustments */
            @media (max-width: 768px) {
                .theme-toggle {
                    min-width: auto;
                    padding: 0.375rem 0.75rem;
                }
                
                .theme-toggle-text {
                    display: none;
                }
                
                .theme-dropdown {
                    right: auto;
                    left: 0;
                }
            }
        `;
        
        document.head.appendChild(style);
    }
    
    // Métodos públicos para integración
    getCurrentTheme() {
        return this.currentTheme;
    }
    
    getEffectiveTheme() {
        return this.getEffectiveTheme();
    }
    
    isDarkMode() {
        return this.getEffectiveTheme() === 'dark';
    }
}

// Inicializar Theme Manager
document.addEventListener('DOMContentLoaded', () => {
    new ThemeManager();
});