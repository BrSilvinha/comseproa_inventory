<?php
/**
 * Controlador de autenticación
 * Maneja el proceso de login de forma segura
 */

require_once __DIR__ . '/../bootstrap.php';

// Verificar que no esté ya autenticado
if (Session::isAuthenticated()) {
    redirect(baseUrl('dashboard.php'));
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(baseUrl('views/login_form.php'));
}

try {
    // Verificar token CSRF
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Validator::verifyCsrfToken($csrfToken)) {
        Logger::warning("CSRF token verification failed", [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        Session::setFlash('error', 'Token de seguridad inválido');
        redirect(baseUrl('views/login_form.php'));
    }
    
    // Sanitizar y validar entrada
    $correo = Validator::sanitizeInput($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Verificar rate limiting
    $rateLimitCheck = Security::checkLoginAttempts($correo);
    if ($rateLimitCheck['blocked']) {
        $remainingMinutes = ceil($rateLimitCheck['remaining_time'] / 60);
        Session::setFlash('error', "Demasiados intentos fallidos. Intente nuevamente en {$remainingMinutes} minutos.");
        redirect(baseUrl('views/login_form.php'));
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
        redirect(baseUrl('views/login_form.php'));
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
        
        redirect(baseUrl('dashboard.php'));
    } else {
        // Login fallido - registrar intento
        Security::recordFailedLogin($correo);
        
        Logger::warning("Failed login attempt", [
            'email' => $correo,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        Session::setFlash('error', 'Credenciales inválidas');
        redirect(baseUrl('views/login_form.php'));
    }

} catch (Exception $e) {
    Logger::error("Login system error", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    Session::setFlash('error', 'Error del sistema. Intente nuevamente.');
    redirect(baseUrl('views/login_form.php'));
}