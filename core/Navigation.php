<?php
/**
 * Clase Navigation - Sistema centralizado de navegación
 * Maneja todas las rutas y redirecciones del sistema
 */
class Navigation
{
    /**
     * Rutas principales del sistema
     */
    private static $routes = [
        // Autenticación
        'login' => 'views/login_form.php',
        'auth' => 'auth/login.php',
        'logout' => 'logout.php',
        
        // Dashboard
        'dashboard' => 'dashboard.php',
        'home' => 'dashboard.php',
        
        // Productos
        'productos' => 'productos/listar.php',
        'productos.crear' => 'productos/registrar.php',
        'productos.ver' => 'productos/ver-producto.php',
        'productos.editar' => 'productos/editar.php',
        
        // Almacenes
        'almacenes' => 'almacenes/listar.php',
        'almacenes.crear' => 'almacenes/registrar.php',
        'almacenes.ver' => 'almacenes/ver-almacen.php',
        'almacenes.editar' => 'almacenes/editar.php',
        
        // Usuarios
        'usuarios' => 'usuarios/listar.php',
        'usuarios.crear' => 'usuarios/registrar.php',
        'usuarios.editar' => 'usuarios/editar_usuario.php',
        
        // Reportes
        'reportes' => 'reportes/inventario.php',
        'reportes.inventario' => 'reportes/inventario.php',
        'reportes.movimientos' => 'reportes/movimientos.php',
        'reportes.usuarios' => 'reportes/usuarios.php',
        
        // Notificaciones
        'notificaciones' => 'notificaciones/pendientes.php',
        'notificaciones.pendientes' => 'notificaciones/pendientes.php',
        'notificaciones.historial' => 'notificaciones/historial.php',
        
        // Entregas
        'entregas' => 'entregas/historial.php',
        'entregas.historial' => 'entregas/historial.php',
        
        // Uniformes
        'uniformes' => 'uniformes/historial_entregas_uniformes.php',
        
        // Perfil
        'perfil' => 'perfil/cambiar-password.php',
        'perfil.password' => 'perfil/cambiar-password.php',
    ];
    
    /**
     * Obtener URL de una ruta
     */
    public static function route($name, $params = [])
    {
        if (!isset(self::$routes[$name])) {
            Logger::warning("Route not found: {$name}");
            return baseUrl('dashboard.php');
        }
        
        $url = baseUrl(self::$routes[$name]);
        
        // Agregar parámetros si se proporcionan
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        return $url;
    }
    
    /**
     * Redireccionar a una ruta nombrada
     */
    public static function redirectTo($name, $params = [])
    {
        $url = self::route($name, $params);
        redirect($url);
    }
    
    /**
     * Redireccionar después del login según el rol
     */
    public static function redirectAfterLogin($role = 'usuario')
    {
        switch ($role) {
            case 'admin':
            case 'administrador':
                self::redirectTo('dashboard');
                break;
            case 'almacenero':
            case 'usuario':
            default:
                self::redirectTo('dashboard');
                break;
        }
    }
    
    /**
     * Redireccionar al login
     */
    public static function requireLogin($message = null)
    {
        if ($message) {
            Session::setFlash('error', $message);
        }
        self::redirectTo('login');
    }
    
    /**
     * Verificar acceso y redireccionar si no autorizado
     */
    public static function requireAuth($requiredRole = null)
    {
        if (!Session::isAuthenticated()) {
            self::requireLogin('Debe iniciar sesión para acceder a esta página');
        }
        
        if ($requiredRole) {
            $userRole = Session::get('user_role');
            if ($userRole !== $requiredRole && $userRole !== 'admin') {
                Session::setFlash('error', 'No tiene permisos para acceder a esta página');
                self::redirectTo('dashboard');
            }
        }
    }
    
    /**
     * Obtener breadcrumbs para la página actual
     */
    public static function getBreadcrumbs($currentPage = null)
    {
        $breadcrumbs = [
            ['name' => 'Dashboard', 'url' => self::route('dashboard')]
        ];
        
        if (!$currentPage) {
            return $breadcrumbs;
        }
        
        // Mapeo de páginas a breadcrumbs
        $breadcrumbsMap = [
            'productos' => [
                ['name' => 'Productos', 'url' => self::route('productos')]
            ],
            'productos.crear' => [
                ['name' => 'Productos', 'url' => self::route('productos')],
                ['name' => 'Registrar Producto', 'url' => null]
            ],
            'almacenes' => [
                ['name' => 'Almacenes', 'url' => self::route('almacenes')]
            ],
            'usuarios' => [
                ['name' => 'Usuarios', 'url' => self::route('usuarios')]
            ],
            'reportes' => [
                ['name' => 'Reportes', 'url' => self::route('reportes')]
            ]
        ];
        
        if (isset($breadcrumbsMap[$currentPage])) {
            $breadcrumbs = array_merge($breadcrumbs, $breadcrumbsMap[$currentPage]);
        }
        
        return $breadcrumbs;
    }
    
    /**
     * Generar menú de navegación
     */
    public static function getNavigationMenu($userRole = 'usuario')
    {
        $menu = [];
        
        // Dashboard (todos)
        $menu[] = [
            'name' => 'Dashboard',
            'url' => self::route('dashboard'),
            'icon' => 'fas fa-tachometer-alt',
            'active' => false
        ];
        
        // Productos (todos)
        $menu[] = [
            'name' => 'Productos',
            'url' => self::route('productos'),
            'icon' => 'fas fa-boxes',
            'active' => false
        ];
        
        // Almacenes (admin)
        if ($userRole === 'admin') {
            $menu[] = [
                'name' => 'Almacenes',
                'url' => self::route('almacenes'),
                'icon' => 'fas fa-warehouse',
                'active' => false
            ];
        }
        
        // Usuarios (admin)
        if ($userRole === 'admin') {
            $menu[] = [
                'name' => 'Usuarios',
                'url' => self::route('usuarios'),
                'icon' => 'fas fa-users',
                'active' => false
            ];
        }
        
        // Notificaciones (todos)
        $menu[] = [
            'name' => 'Notificaciones',
            'url' => self::route('notificaciones'),
            'icon' => 'fas fa-bell',
            'active' => false
        ];
        
        // Reportes (todos)
        $menu[] = [
            'name' => 'Reportes',
            'url' => self::route('reportes'),
            'icon' => 'fas fa-chart-bar',
            'active' => false
        ];
        
        // Entregas (todos)
        $menu[] = [
            'name' => 'Entregas',
            'url' => self::route('entregas'),
            'icon' => 'fas fa-truck',
            'active' => false
        ];
        
        return $menu;
    }
    
    /**
     * Verificar si una ruta existe
     */
    public static function routeExists($name)
    {
        return isset(self::$routes[$name]);
    }
    
    /**
     * Obtener todas las rutas disponibles
     */
    public static function getAllRoutes()
    {
        return self::$routes;
    }
    
    /**
     * Registrar nueva ruta dinámicamente
     */
    public static function addRoute($name, $path)
    {
        self::$routes[$name] = $path;
    }
    
    /**
     * Obtener URL de retorno segura
     */
    public static function getSafeReturnUrl($default = 'dashboard')
    {
        $returnUrl = $_GET['return'] ?? $_POST['return'] ?? null;
        
        if (!$returnUrl) {
            return self::route($default);
        }
        
        // Lista blanca de URLs seguras
        $safeRoutes = array_keys(self::$routes);
        
        // Si es una ruta nombrada válida
        if (in_array($returnUrl, $safeRoutes)) {
            return self::route($returnUrl);
        }
        
        // Si es una URL relativa del sistema
        if (strpos($returnUrl, '/') === 0 || strpos($returnUrl, baseUrl()) === 0) {
            return $returnUrl;
        }
        
        // Por defecto, ir al dashboard
        return self::route($default);
    }
}
?>