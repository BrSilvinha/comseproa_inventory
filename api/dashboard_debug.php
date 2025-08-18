<?php
/**
 * Dashboard Debug - Para encontrar el error exacto
 */

// Mostrar errores para debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
session_start();

$debug_info = [];
$debug_info['php_version'] = PHP_VERSION;
$debug_info['session_status'] = session_status();
$debug_info['session_data'] = $_SESSION ?? [];

try {
    // 1. Verificar sesión
    if (!isset($_SESSION['user_id'])) {
        $debug_info['error'] = 'No hay sesión de usuario';
        echo json_encode(['success' => false, 'debug' => $debug_info]);
        exit;
    }

    $debug_info['user_authenticated'] = true;
    $debug_info['user_id'] = $_SESSION['user_id'];
    $debug_info['user_role'] = $_SESSION['user_role'] ?? 'no_role';

    // 2. Verificar archivo de configuración
    $config_path = '../config/database.php';
    $debug_info['config_exists'] = file_exists($config_path);
    
    if (!file_exists($config_path)) {
        $debug_info['error'] = 'No existe archivo de configuración';
        echo json_encode(['success' => false, 'debug' => $debug_info]);
        exit;
    }

    // 3. Incluir configuración
    require_once $config_path;
    $debug_info['config_loaded'] = true;
    
    // 4. Verificar conexión
    if (!isset($conn)) {
        $debug_info['error'] = 'Variable $conn no existe';
        echo json_encode(['success' => false, 'debug' => $debug_info]);
        exit;
    }

    if (!$conn) {
        $debug_info['error'] = 'Conexión es null';
        $debug_info['mysqli_error'] = mysqli_connect_error();
        echo json_encode(['success' => false, 'debug' => $debug_info]);
        exit;
    }

    $debug_info['connection_status'] = 'OK';

    // 5. Probar consulta simple
    $test_query = "SELECT COUNT(*) as total FROM productos";
    $result = $conn->query($test_query);
    
    if (!$result) {
        $debug_info['error'] = 'Error en consulta test';
        $debug_info['mysql_error'] = $conn->error;
        echo json_encode(['success' => false, 'debug' => $debug_info]);
        exit;
    }

    $debug_info['test_query_result'] = $result->fetch_assoc();

    // 6. Verificar tablas
    $tables = ['productos', 'almacenes', 'usuarios', 'categorias'];
    foreach ($tables as $table) {
        $check_table = "SHOW TABLES LIKE '$table'";
        $table_result = $conn->query($check_table);
        $debug_info['tables'][$table] = $table_result && $table_result->num_rows > 0;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Debug completado sin errores',
        'debug' => $debug_info
    ]);

} catch (Exception $e) {
    $debug_info['exception'] = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => $debug_info
    ]);
} catch (Error $e) {
    $debug_info['fatal_error'] = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => $debug_info
    ]);
}
?>