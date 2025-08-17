<?php
/**
 * Controlador de autenticación
 * Maneja el proceso de login de forma segura
 */

require_once __DIR__ . '/../bootstrap.php';

// Verificar que no esté ya autenticado
if (Session::isAuthenticated()) {
    Navigation::redirectAfterLogin(Session::get('user_role'));
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('login');
}

try {
    // Verificar token CSRF
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Validator::verifyCsrfToken($csrfToken)) {
        Logger::warning("CSRF token verification failed", [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        Session::setFlash('error', 'Token de seguridad inválido');
        redirectTo('login');
    }
    
    // Sanitizar y validar entrada
    $correo = Validator::sanitizeInput($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Verificar rate limiting
    $rateLimitCheck = Security::checkLoginAttempts($correo);
    if ($rateLimitCheck['blocked']) {
        $remainingMinutes = ceil($rateLimitCheck['remaining_time'] / 60);
        Session::setFlash('error', "Demasiados intentos fallidos. Intente nuevamente en {$remainingMinutes} minutos.");
        redirectTo('login');
    }
    
    // Validar datos de entrada
    $errors = Validator::validate([
        'correo' => $correo,
        'password' => $password
    ], [
        'correo' => ['required' => true, 'email' => true],
        'password' => ['required' => true, 'min_length' => 6]
    ]);
    
    if (!empty($errors)) {
        Session::setFlash('error', 'Datos de entrada inválidos');
        redirectTo('login');
    }

    // Obtener instancia de base de datos
    $db = Database::getInstance();

    // Buscar usuario en base de datos
    $sql = "SELECT id, nombre, apellidos, contrasena, rol, almacen_id 
            FROM usuarios 
            WHERE correo = ? AND estado = 'activo' LIMIT 1";
    
    $user = $db->fetchOne($sql, [$correo], 's');
    
    if ($user && password_verify($password, $user['contrasena'])) {
        // Login exitoso - limpiar intentos fallidos
        Security::clearLoginAttempts($correo);
        Session::login($user);
        
        Logger::info("Successful login", [
            'user_id' => $user['id'],
            'email' => $correo,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        Navigation::redirectAfterLogin($user['rol']);
    } else {
        // Login fallido - registrar intento
        Security::recordFailedLogin($correo);
        
        Logger::warning("Failed login attempt", [
            'email' => $correo,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        Session::setFlash('error', 'Credenciales inválidas');
        redirectTo('login');
    }

} catch (Exception $e) {
    Logger::error("Login system error", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    Session::setFlash('error', 'Error del sistema. Intente nuevamente.');
    redirectTo('login');
}