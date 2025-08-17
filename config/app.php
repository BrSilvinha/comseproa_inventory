<?php
/**
 * Configuración principal de la aplicación
 * Centraliza todas las configuraciones del sistema
 */

return [
    // Información de la aplicación
    'name' => Config::get('APP_NAME', 'COMSEPROA Inventory System'),
    'version' => '2.0.0',
    'environment' => Config::get('APP_ENV', 'production'),
    'debug' => Config::get('APP_DEBUG', 'false') === 'true',
    'url' => Config::get('APP_URL', 'http://localhost'),
    
    // Configuración de base de datos
    'database' => [
        'host' => Config::get('DB_HOST', 'localhost'),
        'username' => Config::get('DB_USERNAME'),
        'password' => Config::get('DB_PASSWORD'),
        'database' => Config::get('DB_NAME'),
        'charset' => Config::get('DB_CHARSET', 'utf8mb4'),
        'port' => Config::get('DB_PORT', 3306),
    ],
    
    // Configuración de sesiones
    'session' => [
        'lifetime' => (int)Config::get('SESSION_LIFETIME', 7200),
        'secure' => Config::get('SESSION_SECURE', 'false') === 'true',
        'httponly' => Config::get('SESSION_HTTPONLY', 'true') === 'true',
        'name' => 'COMSEPROA_SESSION',
        'domain' => Config::get('SESSION_DOMAIN', ''),
    ],
    
    // Configuración de logging
    'logging' => [
        'level' => Config::get('LOG_LEVEL', 'error'),
        'path' => Config::get('LOG_PATH', 'logs/'),
        'max_files' => (int)Config::get('LOG_MAX_FILES', 10),
        'max_size' => Config::get('LOG_MAX_SIZE', '10MB'),
    ],
    
    // Configuración de seguridad
    'security' => [
        'bcrypt_rounds' => (int)Config::get('BCRYPT_ROUNDS', 12),
        'max_login_attempts' => (int)Config::get('MAX_LOGIN_ATTEMPTS', 5),
        'lockout_time' => (int)Config::get('LOGIN_LOCKOUT_TIME', 900),
        'csrf_lifetime' => 3600,
        'password_reset_lifetime' => 3600,
    ],
    
    // Configuración de paginación
    'pagination' => [
        'default_per_page' => 20,
        'max_per_page' => 100,
    ],
    
    // Configuración de archivos
    'uploads' => [
        'max_size' => '10MB',
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
        'path' => 'uploads/',
    ],
    
    // Configuración de email (para futuras funcionalidades)
    'mail' => [
        'driver' => Config::get('MAIL_DRIVER', 'smtp'),
        'host' => Config::get('MAIL_HOST'),
        'port' => Config::get('MAIL_PORT', 587),
        'username' => Config::get('MAIL_USERNAME'),
        'password' => Config::get('MAIL_PASSWORD'),
        'encryption' => Config::get('MAIL_ENCRYPTION', 'tls'),
        'from_email' => Config::get('MAIL_FROM_EMAIL'),
        'from_name' => Config::get('MAIL_FROM_NAME'),
    ],
    
    // Configuración de cache
    'cache' => [
        'driver' => 'file',
        'lifetime' => 3600,
        'path' => 'cache/',
    ],
    
    // Configuración específica del inventario
    'inventory' => [
        'stock_alert_threshold' => 10,
        'low_stock_percentage' => 20,
        'categories_per_page' => 50,
        'products_per_page' => 20,
        'auto_logout_minutes' => 30,
    ],
    
    // Configuración de reportes
    'reports' => [
        'formats' => ['pdf', 'excel', 'csv'],
        'max_records' => 10000,
        'cache_time' => 300, // 5 minutos
    ],
    
    // Timezone
    'timezone' => 'America/Lima',
    
    // Configuración de API (para futuras integraciones)
    'api' => [
        'rate_limit' => 100, // requests per minute
        'version' => 'v1',
        'prefix' => 'api',
    ]
];