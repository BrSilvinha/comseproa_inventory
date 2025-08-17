<?php
/**
 * Manejo seguro de sesiones
 * Previene session hijacking y maneja autenticación
 */
class Session
{
    private static $started = false;

    /**
     * Iniciar sesión segura
     */
    public static function start()
    {
        if (self::$started) {
            return;
        }

        // Configuración segura de sesiones
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        if (Config::get('SESSION_SECURE', 'false') === 'true') {
            ini_set('session.cookie_secure', 1);
        }

        session_start();
        
        // Regenerar ID por seguridad
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
        }

        // Verificar timeout de sesión
        self::checkTimeout();
        
        self::$started = true;
    }

    /**
     * Verificar timeout de sesión
     */
    private static function checkTimeout()
    {
        $lifetime = (int)Config::get('SESSION_LIFETIME', 7200);
        
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > $lifetime) {
                self::destroy();
                return;
            }
        }
        
        $_SESSION['last_activity'] = time();
    }

    /**
     * Establecer valor en sesión
     */
    public static function set($key, $value)
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Obtener valor de sesión
     */
    public static function get($key, $default = null)
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Verificar si existe clave en sesión
     */
    public static function has($key)
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    /**
     * Eliminar valor de sesión
     */
    public static function remove($key)
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Destruir sesión completamente
     */
    public static function destroy()
    {
        self::start();
        
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        self::$started = false;
    }

    /**
     * Verificar si usuario está autenticado
     */
    public static function isAuthenticated()
    {
        return self::has('user_id') && !empty(self::get('user_id'));
    }

    /**
     * Obtener usuario actual
     */
    public static function getUser()
    {
        if (!self::isAuthenticated()) {
            return null;
        }

        return [
            'id' => self::get('user_id'),
            'name' => self::get('user_name'),
            'role' => self::get('user_role'),
            'almacen_id' => self::get('almacen_id')
        ];
    }

    /**
     * Verificar si usuario tiene rol específico
     */
    public static function hasRole($role)
    {
        return self::get('user_role') === $role;
    }

    /**
     * Verificar si usuario es admin
     */
    public static function isAdmin()
    {
        return self::hasRole('admin');
    }

    /**
     * Login de usuario
     */
    public static function login($user)
    {
        self::start();
        session_regenerate_id(true);
        
        self::set('user_id', $user['id']);
        self::set('user_name', $user['nombre'] . ' ' . $user['apellidos']);
        self::set('user_role', $user['rol']);
        self::set('almacen_id', $user['almacen_id']);
        self::set('last_activity', time());
        
        Logger::info("User logged in", ['user_id' => $user['id']]);
    }

    /**
     * Logout de usuario
     */
    public static function logout()
    {
        $userId = self::get('user_id');
        Logger::info("User logged out", ['user_id' => $userId]);
        
        self::destroy();
    }

    /**
     * Mensajes flash
     */
    public static function setFlash($type, $message)
    {
        self::start();
        $_SESSION['flash'][$type] = $message;
    }

    /**
     * Obtener y limpiar mensaje flash
     */
    public static function getFlash($type)
    {
        self::start();
        $message = $_SESSION['flash'][$type] ?? null;
        unset($_SESSION['flash'][$type]);
        return $message;
    }

    /**
     * Verificar si hay mensajes flash
     */
    public static function hasFlash($type)
    {
        self::start();
        return isset($_SESSION['flash'][$type]);
    }
}