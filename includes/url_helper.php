<?php
/**
 * ===================================================================
 * URL HELPER - SISTEMA DE URLs SIN IDs VISIBLES
 * ===================================================================
 */

class UrlHelper {
    
    /**
     * Obtener la URL base del proyecto
     */
    public static function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        
        // Obtener el directorio base
        $scriptName = dirname($_SERVER['SCRIPT_NAME']);
        $scriptName = rtrim($scriptName, '/');
        
        if ($scriptName === '.' || $scriptName === '/') {
            $scriptName = '';
        }
        
        return $protocol . '://' . $host . $scriptName;
    }
    
    /**
     * Generar URL para almacenes (SIN IDs)
     */
    public static function almacen($action, $id = null) {
        $baseUrl = self::getBaseUrl();
        
        // Si se proporciona un ID, guardarlo en sesión para uso interno
        if ($id !== null) {
            self::setContextId('almacen', $id);
        }
        
        switch ($action) {
            case 'listar':
                return $baseUrl . '/almacenes';
                
            case 'ver':
                return $baseUrl . '/almacenes/ver';
                
            case 'editar':
                return $baseUrl . '/almacenes/editar';
                
            case 'eliminar':
                return $baseUrl . '/almacenes/eliminar';
                
            case 'registrar':
                return $baseUrl . '/almacenes/registrar';
                
            default:
                return $baseUrl . '/almacenes';
        }
    }
    
    /**
     * Generar URL para productos (SIN IDs)
     */
    public static function producto($action, $id = null, $extra = null) {
        $baseUrl = self::getBaseUrl();
        
        if ($id !== null) {
            self::setContextId('producto', $id);
        }
        
        switch ($action) {
            case 'listar':
                $url = $baseUrl . '/productos';
                if ($extra && is_array($extra)) {
                    $url .= '?' . http_build_query($extra);
                }
                return $url;
                
            case 'ver':
                return $baseUrl . '/productos/ver';
                
            case 'editar':
                return $baseUrl . '/productos/editar';
                
            case 'eliminar':
                return $baseUrl . '/productos/eliminar';
                
            case 'registrar':
                $url = $baseUrl . '/productos/registrar';
                if ($extra && is_array($extra)) {
                    $url .= '?' . http_build_query($extra);
                }
                return $url;
                
            default:
                return $baseUrl . '/productos';
        }
    }
    
    /**
     * Generar URL para usuarios (SIN IDs)
     */
    public static function usuario($action, $id = null) {
        $baseUrl = self::getBaseUrl();
        
        if ($id !== null) {
            self::setContextId('usuario', $id);
        }
        
        switch ($action) {
            case 'listar':
                return $baseUrl . '/usuarios';
                
            case 'ver':
                return $baseUrl . '/usuarios/ver';
                
            case 'editar':
                return $baseUrl . '/usuarios/editar';
                
            case 'eliminar':
                return $baseUrl . '/usuarios/eliminar';
                
            case 'registrar':
                return $baseUrl . '/usuarios/registrar';
                
            default:
                return $baseUrl . '/usuarios';
        }
    }
    
    /**
     * Generar URL para reportes
     */
    public static function reporte($tipo, $params = null) {
        $baseUrl = self::getBaseUrl();
        $url = $baseUrl . '/reportes/' . $tipo;
        
        if ($params && is_array($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        return $url;
    }
    
    /**
     * Generar URL para notificaciones
     */
    public static function notificacion($tipo) {
        $baseUrl = self::getBaseUrl();
        return $baseUrl . '/notificaciones/' . $tipo;
    }
    
    /**
     * Generar URL para entregas
     */
    public static function entrega($tipo) {
        $baseUrl = self::getBaseUrl();
        return $baseUrl . '/entregas/' . $tipo;
    }
    
    /**
     * URL del dashboard
     */
    public static function inicio() {
        return self::getBaseUrl() . '/inicio';
    }
    
    /**
     * Redireccionar a una URL limpia
     */
    public static function redirect($url) {
        header('Location: ' . $url);
        exit();
    }
    
    /**
     * ===== SISTEMA DE CONTEXTO PARA MANEJAR IDs INTERNAMENTE =====
     */
    
    /**
     * Establecer ID de contexto en sesión (uso interno)
     */
    public static function setContextId($type, $id) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['context_' . $type . '_id'] = (int)$id;
        $_SESSION['context_' . $type . '_timestamp'] = time();
    }
    
    /**
     * Obtener ID de contexto desde sesión
     */
    public static function getContextId($type) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Verificar que el contexto no sea muy antiguo (máximo 1 hora)
        $timestampKey = 'context_' . $type . '_timestamp';
        if (isset($_SESSION[$timestampKey])) {
            $edad = time() - $_SESSION[$timestampKey];
            if ($edad > 3600) { // 1 hora
                self::clearContext($type);
                return null;
            }
        }
        
        $contextKey = 'context_' . $type . '_id';
        return isset($_SESSION[$contextKey]) ? (int)$_SESSION[$contextKey] : null;
    }
    
    /**
     * Limpiar contexto específico
     */
    public static function clearContext($type) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['context_' . $type . '_id']);
        unset($_SESSION['context_' . $type . '_timestamp']);
    }
    
    /**
     * Limpiar todos los contextos
     */
    public static function clearAllContexts() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $keys = array_keys($_SESSION);
        foreach ($keys as $key) {
            if (strpos($key, 'context_') === 0) {
                unset($_SESSION[$key]);
            }
        }
    }
    
    /**
     * Validar que existe contexto para una acción
     */
    public static function requireContext($type, $redirectUrl = null) {
        $id = self::getContextId($type);
        if ($id === null) {
            if ($redirectUrl) {
                self::redirect($redirectUrl);
            } else {
                // Redirección por defecto según el tipo
                switch ($type) {
                    case 'almacen':
                        self::redirect(self::almacen('listar'));
                        break;
                    case 'producto':
                        self::redirect(self::producto('listar'));
                        break;
                    case 'usuario':
                        self::redirect(self::usuario('listar'));
                        break;
                    default:
                        self::redirect(self::inicio());
                }
            }
        }
        return $id;
    }
}

/**
 * ===================================================================
 * FUNCIONES DE CONVENIENCIA
 * ===================================================================
 */

function url_almacen($action, $id = null) {
    return UrlHelper::almacen($action, $id);
}

function url_producto($action, $id = null, $extra = null) {
    return UrlHelper::producto($action, $id, $extra);
}

function url_usuario($action, $id = null) {
    return UrlHelper::usuario($action, $id);
}

function url_reporte($tipo, $params = null) {
    return UrlHelper::reporte($tipo, $params);
}

function url_notificacion($tipo) {
    return UrlHelper::notificacion($tipo);
}

function url_entrega($tipo) {
    return UrlHelper::entrega($tipo);
}

function url_inicio() {
    return UrlHelper::inicio();
}

/**
 * Función para redireccionar de forma limpia
 */
function redirect_to($url) {
    UrlHelper::redirect($url);
}

/**
 * Generar breadcrumb automático
 */
function generar_breadcrumb($items = []) {
    $html = '<nav class="breadcrumb" aria-label="Ruta de navegación">';
    $html .= '<a href="' . url_inicio() . '"><i class="fas fa-home"></i> Inicio</a>';
    
    foreach ($items as $item) {
        $html .= '<span><i class="fas fa-chevron-right"></i></span>';
        
        if (isset($item['url'])) {
            $html .= '<a href="' . $item['url'] . '">' . htmlspecialchars($item['text']) . '</a>';
        } else {
            $html .= '<span class="current">' . htmlspecialchars($item['text']) . '</span>';
        }
    }
    
    $html .= '</nav>';
    return $html;
}

/**
 * ===== FUNCIONES DE CONTEXTO =====
 */

function set_context($type, $id) {
    UrlHelper::setContextId($type, $id);
}

function get_context($type) {
    return UrlHelper::getContextId($type);
}

function require_context($type, $redirectUrl = null) {
    return UrlHelper::requireContext($type, $redirectUrl);
}

function clear_context($type) {
    UrlHelper::clearContext($type);
}

function clear_all_contexts() {
    UrlHelper::clearAllContexts();
}
?>