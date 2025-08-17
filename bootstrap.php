<?php
/**
 * Bootstrap del sistema
 * Inicializa todas las clases y configuraciones necesarias
 */

// Reporte de errores basado en configuración
if (file_exists(__DIR__ . '/.env')) {
    // Cargar configuración temporal para verificar debug
    $envFile = __DIR__ . '/.env';
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $isDebug = false;
    
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, 'APP_DEBUG=true') !== false) {
            $isDebug = true;
            break;
        }
    }
    
    if ($isDebug) {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    } else {
        error_reporting(E_ALL);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
    }
} else {
    // Modo seguro por defecto
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Cargar autoloader
require_once __DIR__ . '/core/Autoloader.php';
Autoloader::register();

// Inicializar configuración
Config::load();

// Configurar zona horaria
date_default_timezone_set('America/Lima');

// Configurar manejo de errores personalizado
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    
    $errorType = 'ERROR';
    switch ($severity) {
        case E_WARNING:
            $errorType = 'WARNING';
            break;
        case E_NOTICE:
            $errorType = 'NOTICE';
            break;
    }
    
    Logger::error("PHP {$errorType}: {$message} in {$file}:{$line}");
    
    if (Config::isDebug()) {
        echo "<b>PHP {$errorType}</b>: {$message} in <b>{$file}</b> on line <b>{$line}</b><br>";
    }
});

// Configurar manejo de excepciones
set_exception_handler(function($exception) {
    Logger::critical("Uncaught Exception: " . $exception->getMessage(), [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
    
    if (Config::isDebug()) {
        echo "<h1>Uncaught Exception</h1>";
        echo "<p><strong>Message:</strong> " . $exception->getMessage() . "</p>";
        echo "<p><strong>File:</strong> " . $exception->getFile() . "</p>";
        echo "<p><strong>Line:</strong> " . $exception->getLine() . "</p>";
        echo "<pre>" . $exception->getTraceAsString() . "</pre>";
    } else {
        echo "<h1>Error del Sistema</h1>";
        echo "<p>Ha ocurrido un error interno. Por favor contacte al administrador.</p>";
    }
});

// Inicializar sesión
Session::start();

/**
 * Helper function para incluir templates de forma segura
 */
function render($template, $data = []) {
    // Extraer variables para el template
    extract($data);
    
    // Función helper para escapar datos en templates
    $h = function($string) {
        return Validator::sanitizeHtml($string);
    };
    
    $templatePath = __DIR__ . '/views/' . $template . '.php';
    
    if (file_exists($templatePath)) {
        include $templatePath;
    } else {
        throw new Exception("Template not found: {$template}");
    }
}

/**
 * Helper function para redireccionar
 */
function redirect($url, $statusCode = 302) {
    header("Location: {$url}", true, $statusCode);
    exit();
}

/**
 * Helper function para obtener URL base
 */
function baseUrl($path = '') {
    $baseUrl = Config::get('APP_URL', 'http://localhost/comseproa_inventory');
    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}

/**
 * Helper function para verificar autenticación
 */
function requireAuth() {
    if (!Session::isAuthenticated()) {
        redirect(baseUrl('views/login_form.php'));
    }
}

/**
 * Helper function para verificar rol admin
 */
function requireAdmin() {
    requireAuth();
    if (!Session::isAdmin()) {
        Session::setFlash('error', 'No tienes permisos para acceder a esta página');
        redirect(baseUrl('dashboard.php'));
    }
}