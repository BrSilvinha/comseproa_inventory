<?php
/**
 * NavigationHelper - Helper para generar navegación en templates
 * Facilita la inclusión de navegación activa en todas las páginas
 */
class NavigationHelper
{
    /**
     * Incluir los scripts de navegación activa
     */
    public static function includeNavigationScripts()
    {
        $baseUrl = rtrim(Config::get('APP_URL', 'http://localhost'), '/');
        
        echo <<<HTML
<!-- Navigation Active Scripts -->
<script src="{$baseUrl}/assets/js/active-navigation.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar navegación activa
    if (window.ActiveNavigation) {
        console.log('✅ Navegación activa inicializada');
    }
});
</script>
HTML;
    }
    
    /**
     * Generar breadcrumbs
     */
    public static function renderBreadcrumbs($currentPage = null, $customBreadcrumbs = null)
    {
        if ($customBreadcrumbs) {
            $breadcrumbs = $customBreadcrumbs;
        } else {
            $breadcrumbs = Navigation::getBreadcrumbs($currentPage);
        }
        
        if (empty($breadcrumbs)) {
            return '';
        }
        
        echo '<nav aria-label="breadcrumb" class="breadcrumb-nav">';
        echo '<ol class="breadcrumb">';
        
        foreach ($breadcrumbs as $index => $breadcrumb) {
            $isLast = ($index === count($breadcrumbs) - 1);
            
            if ($isLast || empty($breadcrumb['url'])) {
                echo '<li class="breadcrumb-item active" aria-current="page">';
                echo TemplateHelper::h($breadcrumb['name']);
                echo '</li>';
            } else {
                echo '<li class="breadcrumb-item">';
                echo '<a href="' . TemplateHelper::attr($breadcrumb['url']) . '">';
                echo TemplateHelper::h($breadcrumb['name']);
                echo '</a>';
                echo '</li>';
            }
        }
        
        echo '</ol>';
        echo '</nav>';
    }
    
    /**
     * Generar menú de navegación
     */
    public static function renderNavigationMenu($userRole = 'usuario', $currentPath = null)
    {
        $menu = Navigation::getNavigationMenu($userRole);
        
        if (empty($menu)) {
            return '';
        }
        
        echo '<nav class="main-navigation" role="navigation">';
        echo '<ul class="nav-menu">';
        
        foreach ($menu as $item) {
            $isActive = self::isMenuItemActive($item['url'], $currentPath);
            $activeClass = $isActive ? ' active' : '';
            
            echo '<li class="nav-item' . $activeClass . '">';
            echo '<a href="' . TemplateHelper::attr($item['url']) . '" class="nav-link">';
            echo '<i class="' . TemplateHelper::attr($item['icon']) . '"></i>';
            echo '<span>' . TemplateHelper::h($item['name']) . '</span>';
            echo '</a>';
            echo '</li>';
        }
        
        echo '</ul>';
        echo '</nav>';
    }
    
    /**
     * Verificar si un elemento del menú está activo
     */
    private static function isMenuItemActive($menuUrl, $currentPath = null)
    {
        if (!$currentPath) {
            $currentPath = $_SERVER['REQUEST_URI'] ?? '';
        }
        
        // Normalizar URLs
        $menuPath = parse_url($menuUrl, PHP_URL_PATH);
        $currentPath = parse_url($currentPath, PHP_URL_PATH);
        
        // Comparación exacta
        if ($menuPath === $currentPath) {
            return true;
        }
        
        // Comparación por directorio
        $menuDir = dirname($menuPath);
        $currentDir = dirname($currentPath);
        
        return $menuDir === $currentDir && $menuDir !== '.';
    }
    
    /**
     * Generar CSS específico para la página actual
     */
    public static function generatePageCSS()
    {
        $currentPath = $_SERVER['REQUEST_URI'] ?? '';
        $pathParts = explode('/', trim($currentPath, '/'));
        
        $cssClasses = [];
        
        // Clase por módulo
        if (!empty($pathParts[0])) {
            $cssClasses[] = 'page-' . $pathParts[0];
        }
        
        // Clase por archivo
        if (!empty($pathParts[1])) {
            $fileName = pathinfo($pathParts[1], PATHINFO_FILENAME);
            $cssClasses[] = 'page-' . $fileName;
        }
        
        if (!empty($cssClasses)) {
            echo '<script>document.body.classList.add("' . implode('", "', $cssClasses) . '");</script>';
        }
    }
    
    /**
     * Incluir CSS de navegación activa
     */
    public static function includeNavigationCSS()
    {
        echo <<<CSS
<style>
/* Navegación Activa - Estilos mejorados */
.sidebar a[role="menuitem"].active {
    background: linear-gradient(135deg, var(--primary-color, #007bff) 0%, var(--primary-dark, #0056b3) 100%);
    color: white;
    font-weight: 600;
    border-radius: 6px;
    box-shadow: 0 2px 4px rgba(0, 123, 255, 0.3);
    transform: translateX(2px);
    position: relative;
}

.sidebar a[role="menuitem"].active::before {
    content: '';
    position: absolute;
    left: -8px;
    top: 50%;
    transform: translateY(-50%);
    width: 4px;
    height: 70%;
    background: var(--primary-color, #007bff);
    border-radius: 0 2px 2px 0;
    box-shadow: 0 0 8px rgba(0, 123, 255, 0.5);
}

.sidebar a[role="menuitem"].active i {
    color: white;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

/* Submenu container activo */
.submenu-container.active > a {
    background-color: rgba(0, 123, 255, 0.1);
    border-left: 3px solid var(--primary-color, #007bff);
    font-weight: 600;
    color: var(--primary-color, #007bff);
}

/* Dashboard cards activas */
.dashboard-card.active-card {
    border: 2px solid var(--primary-color, #007bff);
    background: linear-gradient(135deg, rgba(0, 123, 255, 0.05) 0%, rgba(0, 123, 255, 0.1) 100%);
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 123, 255, 0.15);
}

.dashboard-card.active-card::before {
    content: '●';
    position: absolute;
    top: 10px;
    right: 15px;
    color: var(--primary-color, #007bff);
    font-size: 12px;
}

.dashboard-card.active-card h3 {
    color: var(--primary-color, #007bff);
}

/* Breadcrumbs */
.breadcrumb-nav {
    margin-bottom: 1rem;
    padding: 0.75rem 1rem;
    background-color: var(--bg-secondary, #f8f9fa);
    border-radius: 6px;
}

.breadcrumb {
    margin: 0;
    padding: 0;
    list-style: none;
    display: flex;
    align-items: center;
}

.breadcrumb-item {
    display: flex;
    align-items: center;
}

.breadcrumb-item + .breadcrumb-item::before {
    content: '›';
    margin: 0 0.5rem;
    color: var(--text-muted, #6c757d);
}

.breadcrumb-item a {
    color: var(--primary-color, #007bff);
    text-decoration: none;
}

.breadcrumb-item a:hover {
    text-decoration: underline;
}

.breadcrumb-item.active {
    color: var(--text-secondary, #6c757d);
}

/* Animaciones suaves */
.sidebar a[role="menuitem"],
.dashboard-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Estados hover mejorados */
.sidebar a[role="menuitem"]:hover:not(.active) {
    background-color: rgba(0, 123, 255, 0.1);
    transform: translateX(3px);
    color: var(--primary-color, #007bff);
}

/* Indicadores por página */
body.page-productos .submenu-container:nth-child(3) > a,
body.page-almacenes .submenu-container:nth-child(2) > a,
body.page-usuarios .submenu-container:nth-child(1) > a {
    border-left: 3px solid var(--primary-color, #007bff);
    background-color: rgba(0, 123, 255, 0.05);
}

/* Dark mode support */
[data-theme="dark"] .sidebar a[role="menuitem"].active {
    background: linear-gradient(135deg, var(--primary-color, #60a5fa) 0%, var(--primary-dark, #3b82f6) 100%);
}

[data-theme="dark"] .breadcrumb-nav {
    background-color: var(--bg-secondary, #374151);
}

/* Mobile específico */
@media (max-width: 768px) {
    .sidebar a[role="menuitem"].active::before {
        display: none;
    }
    
    .sidebar a[role="menuitem"].active {
        transform: none;
        margin: 0 -10px;
        border-radius: 0;
    }
}
</style>
CSS;
    }
    
    /**
     * Función completa para incluir todo lo necesario para navegación activa
     */
    public static function includeAll($currentPage = null, $userRole = 'usuario')
    {
        self::includeNavigationCSS();
        self::includeNavigationScripts();
        self::generatePageCSS();
        
        // Si hay breadcrumbs que mostrar
        if ($currentPage) {
            self::renderBreadcrumbs($currentPage);
        }
    }
    
    /**
     * Helper para marcar manualmente un elemento como activo
     */
    public static function setActiveMenuItem($selector)
    {
        echo <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.ActiveNavigation) {
        window.ActiveNavigation.setActive('{$selector}');
    }
});
</script>
JS;
    }
    
    /**
     * Helper para debug de navegación
     */
    public static function debugNavigation()
    {
        if (Config::get('APP_DEBUG') === 'true') {
            $currentPath = $_SERVER['REQUEST_URI'] ?? '';
            $userRole = $_SESSION['user_role'] ?? 'guest';
            echo <<<JS
<script>
console.log('🐛 Navigation Debug:', {
    currentPath: '{$currentPath}',
    userRole: '{$userRole}',
    timestamp: new Date().toISOString()
});
</script>
JS;
        }
    }
}
?>