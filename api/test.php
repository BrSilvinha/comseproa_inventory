<?php
/**
 * Endpoint de prueba para verificar que la API funciona
 */

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

try {
    Session::start();
    
    $response = [
        'success' => true,
        'message' => 'API funcionando correctamente',
        'data' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'authenticated' => Session::isAuthenticated(),
            'user_id' => Session::get('user_id'),
            'user_role' => Session::get('user_role'),
            'php_version' => PHP_VERSION
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}
?>