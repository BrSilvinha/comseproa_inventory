/**
 * Sistema de Navegación Activa
 * Detecta la página actual y marca los elementos de menú como activos
 */

class ActiveNavigation {
    constructor() {
        this.currentPath = window.location.pathname;
        this.currentParams = new URLSearchParams(window.location.search);
        
        this.init();
    }
    
    init() {
        this.markActiveMenuItems();
        this.expandActiveSubmenus();
        this.highlightActiveDashboardCards();
        
        // Agregar clase al body para CSS específico de página
        this.addPageClass();
    }
    
    /**
     * Marca los elementos del menú como activos
     */
    markActiveMenuItems() {
        const menuLinks = document.querySelectorAll('.sidebar a[role="menuitem"]');
        
        menuLinks.forEach(link => {
            if (this.isLinkActive(link)) {
                link.classList.add('active');
                
                // También marcar el contenedor padre
                const submenuContainer = link.closest('.submenu-container');
                if (submenuContainer) {
                    submenuContainer.classList.add('active');
                    
                    // Marcar el link principal del submenu
                    const mainLink = submenuContainer.querySelector('a[aria-expanded]');
                    if (mainLink) {
                        mainLink.classList.add('active');
                    }
                }
            }
        });
    }
    
    /**
     * Expande automáticamente los submenús que contienen la página activa
     */
    expandActiveSubmenus() {
        const activeContainer = document.querySelector('.submenu-container.active');
        if (activeContainer) {
            const submenu = activeContainer.querySelector('.submenu');
            const toggleLink = activeContainer.querySelector('a[aria-expanded]');
            
            if (submenu && toggleLink) {
                submenu.classList.add('activo');
                toggleLink.setAttribute('aria-expanded', 'true');
                
                // Agregar icono indicador
                const icon = toggleLink.querySelector('i:last-child');
                if (icon) {
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                }
            }
        }
    }
    
    /**
     * Resalta las tarjetas del dashboard relacionadas con la página actual
     */
    highlightActiveDashboardCards() {
        const dashboardCards = document.querySelectorAll('.dashboard-card');
        
        dashboardCards.forEach(card => {
            const cardLink = card.getAttribute('href');
            if (cardLink && this.isPathActive(cardLink)) {
                card.classList.add('active-card');
            }
        });
    }
    
    /**
     * Verifica si un enlace corresponde a la página actual
     */
    isLinkActive(link) {
        const href = link.getAttribute('href');
        if (!href) return false;
        
        return this.isPathActive(href);
    }
    
    /**
     * Verifica si una ruta corresponde a la página actual
     */
    isPathActive(path) {
        // Normalizar rutas
        const linkPath = this.normalizePath(path);
        const currentPath = this.normalizePath(this.currentPath);
        
        // Coincidencia exacta
        if (linkPath === currentPath) {
            return true;
        }
        
        // Coincidencia por módulo (ej: productos/listar.php coincide con productos/)
        const linkModule = this.getModuleFromPath(linkPath);
        const currentModule = this.getModuleFromPath(currentPath);
        
        if (linkModule && currentModule && linkModule === currentModule) {
            return true;
        }
        
        // Casos especiales
        return this.checkSpecialCases(linkPath, currentPath);
    }
    
    /**
     * Normaliza una ruta para comparación
     */
    normalizePath(path) {
        // Remover leading slash y query parameters
        return path.replace(/^\/+/, '').split('?')[0].toLowerCase();
    }
    
    /**
     * Extrae el módulo de una ruta
     */
    getModuleFromPath(path) {
        const parts = path.split('/');
        if (parts.length >= 2) {
            return parts[parts.length - 2]; // Penúltimo elemento (carpeta)
        }
        return null;
    }
    
    /**
     * Verifica casos especiales de navegación
     */
    checkSpecialCases(linkPath, currentPath) {
        // Dashboard principal
        if ((linkPath.includes('dashboard') || linkPath === '') && 
            (currentPath.includes('dashboard') || currentPath === '')) {
            return true;
        }
        
        // Diferentes vistas del mismo módulo
        const specialMappings = {
            'productos': ['productos/listar.php', 'productos/registrar.php', 'productos/ver-producto.php', 'productos/editar.php'],
            'almacenes': ['almacenes/listar.php', 'almacenes/registrar.php', 'almacenes/ver-almacen.php', 'almacenes/editar.php'],
            'usuarios': ['usuarios/listar.php', 'usuarios/registrar.php', 'usuarios/editar_usuario.php'],
            'reportes': ['reportes/inventario.php', 'reportes/movimientos.php', 'reportes/usuarios.php'],
            'notificaciones': ['notificaciones/pendientes.php', 'notificaciones/historial.php'],
            'entregas': ['entregas/historial.php'],
            'uniformes': ['uniformes/historial_entregas_uniformes.php']
        };
        
        for (const [module, paths] of Object.entries(specialMappings)) {
            if (paths.some(p => linkPath.includes(p)) && paths.some(p => currentPath.includes(p))) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Agrega clase CSS específica de página al body
     */
    addPageClass() {
        const module = this.getModuleFromPath(this.currentPath);
        const fileName = this.currentPath.split('/').pop()?.split('.')[0];
        
        if (module) {
            document.body.classList.add(`page-${module}`);
        }
        
        if (fileName) {
            document.body.classList.add(`page-${fileName}`);
        }
        
        // Clase general para páginas internas
        if (this.currentPath !== '' && !this.currentPath.includes('login')) {
            document.body.classList.add('internal-page');
        }
    }
    
    /**
     * Método público para marcar manualmente un elemento como activo
     */
    setActive(selector) {
        // Limpiar activos actuales
        document.querySelectorAll('.sidebar .active').forEach(el => {
            el.classList.remove('active');
        });
        
        // Marcar nuevo activo
        const element = document.querySelector(selector);
        if (element) {
            element.classList.add('active');
            
            // Si es un elemento de submenu, expandir el contenedor
            const submenuContainer = element.closest('.submenu-container');
            if (submenuContainer) {
                submenuContainer.classList.add('active');
                this.expandActiveSubmenus();
            }
        }
    }
    
    /**
     * Detectar cambios de página (para SPAs futuras)
     */
    onPageChange(callback) {
        // Observer para detectar cambios en el DOM
        const observer = new MutationObserver(() => {
            if (window.location.pathname !== this.currentPath) {
                this.currentPath = window.location.pathname;
                this.currentParams = new URLSearchParams(window.location.search);
                this.init();
                
                if (callback) {
                    callback(this.currentPath);
                }
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        // También escuchar eventos de popstate
        window.addEventListener('popstate', () => {
            this.currentPath = window.location.pathname;
            this.currentParams = new URLSearchParams(window.location.search);
            this.init();
            
            if (callback) {
                callback(this.currentPath);
            }
        });
    }
}

// CSS adicional para elementos activos
const activeNavigationStyles = `
    <style id="active-navigation-styles">
        /* Estilos para enlaces activos del menú */
        .sidebar a[role="menuitem"].active {
            background-color: var(--primary-color, #007bff);
            color: white;
            font-weight: 600;
            border-radius: 4px;
            position: relative;
        }
        
        .sidebar a[role="menuitem"].active::before {
            content: '';
            position: absolute;
            left: -10px;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 100%;
            background-color: var(--primary-color, #007bff);
            border-radius: 0 2px 2px 0;
        }
        
        .sidebar a[role="menuitem"].active i {
            color: white;
        }
        
        /* Estilos para contenedores de submenu activos */
        .submenu-container.active > a {
            background-color: var(--bg-hover, #f8f9fa);
            font-weight: 600;
        }
        
        /* Estilos para tarjetas del dashboard activas */
        .dashboard-card.active-card {
            border: 2px solid var(--primary-color, #007bff);
            background-color: var(--bg-primary-light, #f8f9ff);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15);
        }
        
        .dashboard-card.active-card h3 {
            color: var(--primary-color, #007bff);
        }
        
        /* Animaciones suaves */
        .sidebar a[role="menuitem"],
        .dashboard-card {
            transition: all 0.2s ease;
        }
        
        /* Estados hover mejorados */
        .sidebar a[role="menuitem"]:hover:not(.active) {
            background-color: var(--bg-hover, #f8f9fa);
            transform: translateX(2px);
        }
        
        /* Indicador visual para páginas específicas */
        .page-productos .submenu-container:nth-child(3) > a,
        .page-almacenes .submenu-container:nth-child(2) > a,
        .page-usuarios .submenu-container:nth-child(1) > a {
            border-left: 3px solid var(--primary-color, #007bff);
        }
    </style>
`;

// Estilos de navegación activa deshabilitados
// document.head.insertAdjacentHTML('beforeend', activeNavigationStyles);

// Navegación activa deshabilitada por petición del usuario
// document.addEventListener('DOMContentLoaded', () => {
//     const activeNav = new ActiveNavigation();
//     
//     // Exponer globalmente para uso manual
//     window.ActiveNavigation = activeNav;
//     
//     // Log para debugging (remover en producción)
//     if (console && typeof console.log === 'function') {
//         console.log('🧭 Active Navigation initialized for:', window.location.pathname);
//     }
// });