<?php
/**
 * API de Búsqueda Simple - Solo datos reales
 */

header('Content-Type: application/json');
session_start();

try {
    // Verificar autenticación básica
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'results' => []]);
        exit();
    }

    // Conexión directa
    require_once '../config/database.php';
    
    if (!$conn) {
        throw new Exception('Sin conexión a BD');
    }
    
    $usuario_rol = $_SESSION['user_role'] ?? 'usuario';
    $usuario_almacen_id = $_SESSION['almacen_id'] ?? null;
    
    // Obtener término de búsqueda
    $query = trim($_GET['q'] ?? '');
    
    if (strlen($query) < 2) {
        echo json_encode(['success' => true, 'results' => []]);
        exit();
    }
    
    // Búsqueda en productos usando la estructura real
    $search_term = "%$query%";
    $sql = "SELECT p.id, p.nombre, p.descripcion, p.cantidad, p.estado, a.nombre as almacen_nombre
            FROM productos p 
            LEFT JOIN almacenes a ON p.almacen_id = a.id 
            WHERE (p.nombre LIKE ? OR p.descripcion LIKE ? OR p.modelo LIKE ?)";
    
    $params = [$search_term, $search_term, $search_term];
    
    // Filtrar por almacén si no es admin
    if ($usuario_rol !== 'admin' && $usuario_almacen_id) {
        $sql .= " AND p.almacen_id = ?";
        $params[] = $usuario_almacen_id;
    }
    
    $sql .= " ORDER BY p.nombre LIMIT 10";
    
    $stmt = $conn->prepare($sql);
    if ($usuario_rol !== 'admin' && $usuario_almacen_id) {
        $stmt->bind_param("sssi", ...$params);
    } else {
        $stmt->bind_param("sss", ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => $row['id'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'] ?: 'Sin descripción',
            'cantidad' => (int)$row['cantidad'],
            'estado' => $row['estado'],
            'almacen' => $row['almacen_nombre'] ?: 'Sin almacén',
            'tipo' => 'producto'
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'results' => $products,
        'count' => count($products)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'results' => [],
        'error' => $e->getMessage()
    ]);
}
?>