<?php
/**
 * Clase de configuración centralizada
 * Maneja la carga de variables de entorno y configuración del sistema
 */
class Config
{
    private static $config = [];
    private static $loaded = false;

    /**
     * Cargar configuración desde archivo .env
     */
    public static function load()
    {
        if (self::$loaded) {
            return;
        }

        $envFile = __DIR__ . '/../.env';
        
        if (!file_exists($envFile)) {
            throw new Exception('Archivo .env no encontrado');
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue; // Skip comments
            }
            
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '"\'');
            
            self::$config[$key] = $value;
            $_ENV[$key] = $value;
        }

        self::$loaded = true;
    }

    /**
     * Obtener valor de configuración
     */
    public static function get($key, $default = null)
    {
        self::load();
        return self::$config[$key] ?? $default;
    }

    /**
     * Verificar si estamos en modo debug
     */
    public static function isDebug()
    {
        return self::get('APP_DEBUG', 'false') === 'true';
    }

    /**
     * Obtener configuración de base de datos
     */
    public static function database()
    {
        self::load();
        return [
            'host' => self::get('DB_HOST', 'localhost'),
            'username' => self::get('DB_USERNAME'),
            'password' => self::get('DB_PASSWORD'),
            'database' => self::get('DB_NAME'),
            'charset' => self::get('DB_CHARSET', 'utf8mb4')
        ];
    }
}