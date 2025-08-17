<?php
/**
 * Autoloader simple para cargar clases automáticamente
 */
class Autoloader
{
    private static $directories = [];
    private static $registered = false;

    /**
     * Registrar autoloader
     */
    public static function register()
    {
        if (self::$registered) {
            return;
        }

        // Directorios donde buscar clases
        self::$directories = [
            __DIR__ . '/',           // core/
            __DIR__ . '/../models/', // models/
            __DIR__ . '/../controllers/', // controllers/
        ];

        spl_autoload_register([self::class, 'load']);
        self::$registered = true;
    }

    /**
     * Cargar clase automáticamente
     */
    public static function load($className)
    {
        foreach (self::$directories as $directory) {
            $file = $directory . $className . '.php';
            
            if (file_exists($file)) {
                require_once $file;
                return true;
            }
        }
        
        return false;
    }

    /**
     * Añadir directorio de búsqueda
     */
    public static function addDirectory($directory)
    {
        if (!in_array($directory, self::$directories)) {
            self::$directories[] = rtrim($directory, '/') . '/';
        }
    }
}