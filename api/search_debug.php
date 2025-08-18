<?php
/**
 * API de búsqueda con debug para ver qué está pasando
 */

header('Content-Type: application/json');
session_start();

$debug = [];
$debug['timestamp'] = date('Y-m-d H:i:s');

try {
    // 1. Verificar sesión
    $debug['session_check'] = isset($_SESSION['user_id']) ? 'OK' : 'FAIL';
    $debug['user_id'] = $_SESSION['user_id'] ?? 'NO_SESSION';
    $debug['user_role'] = $_SESSION['user_role'] ?? 'NO_ROLE';
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'debug' => $debug, 'error' => 'No session']);
        exit();
    }

    // 2. Obtener parámetros
    $query = trim($_GET['q'] ?? '');
    $debug['search_query'] = $query;
    
    if (strlen($query) < 2) {
        echo json_encode(['success' => true, 'results' => [], 'debug' => $debug]);
        exit();
    }
    
    // 3. Intentar conexión
    $debug['config_path'] = '../config/database.php';
    $debug['config_exists'] = file_exists('../config/database.php') ? 'YES' : 'NO';
    
    require_once '../config/database.php';
    
    $debug['connection_status'] = isset($conn) ? 'CONNECTED' : 'NO_CONNECTION';
    
    if (!$conn) {
        echo json_encode(['success' => false, 'debug' => $debug, 'error' => 'No database connection']);
        exit();
    }
    
    $debug['mysql_error'] = $conn->error ?: 'NO_ERROR';
    
    // 4. Consulta simple
    $search_term = "%$query%";
    $sql = "SELECT id, nombre, cantidad FROM productos WHERE nombre LIKE ? LIMIT 3";
    $debug['sql'] = $sql;
    $debug['search_term'] = $search_term;
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $debug['prepare_error'] = $conn->error;
        echo json_encode(['success' => false, 'debug' => $debug, 'error' => 'Prepare failed']);
        exit();
    }
    
    $stmt->bind_param("s", $search_term);
    $result = $stmt->execute();
    
    if (!$result) {
        $debug['execute_error'] = $stmt->error;
        echo json_encode(['success' => false, 'debug' => $debug, 'error' => 'Execute failed']);
        exit();
    }
    
    $result = $stmt->get_result();
    $debug['num_rows'] = $result->num_rows;
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'results' => $products,
        'debug' => $debug
    ]);

} catch (Exception $e) {
    $debug['exception'] = $e->getMessage();
    $debug['exception_line'] = $e->getLine();
    $debug['exception_file'] = $e->getFile();
    
    echo json_encode([
        'success' => false,
        'debug' => $debug,
        'error' => $e->getMessage()
    ]);
}
?>