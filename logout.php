<?php
/**
 * Logout seguro del sistema
 * Destruye la sesión y redirige al login
 */

require_once __DIR__ . '/bootstrap.php';

// Verificar que hay una sesión activa
if (Session::isAuthenticated()) {
    // Log del logout
    Logger::info("User logout", [
        'user_id' => Session::get('user_id'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
}

// Destruir sesión de forma segura
Session::destroy();

// Redirigir al login
redirect(baseUrl('views/login_form.php'));
?>
