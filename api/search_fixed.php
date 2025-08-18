<?php
/**
 * API de búsqueda ultra simple - Solo funcionalidad básica
 */

// Configuración básica
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');
session_start();

try {
    // Verificar autenticación mínima
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => true, 'results' => []]);
        exit();
    }

    // Obtener término de búsqueda
    $query = trim($_GET['q'] ?? '');
    
    if (strlen($query) < 2) {
        echo json_encode(['success' => true, 'results' => []]);
        exit();
    }
    
    // Conexión usando config existente
    require_once '../config/database.php';
    
    if (!$conn) {
        echo json_encode(['success' => true, 'results' => []]);
        exit();
    }
    
    $usuario_rol = $_SESSION['user_role'] ?? 'usuario';
    $usuario_almacen_id = $_SESSION['almacen_id'] ?? null;
    
    // Búsqueda simple en productos
    $search_term = "%$query%";
    
    if ($usuario_rol === 'admin') {
        // Admin ve todos los productos
        $sql = "SELECT p.id, p.nombre, p.descripcion, p.cantidad, p.estado, a.nombre as almacen_nombre
                FROM productos p 
                LEFT JOIN almacenes a ON p.almacen_id = a.id 
                WHERE p.nombre LIKE ? 
                ORDER BY p.nombre 
                LIMIT 8";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $search_term);
    } else {
        // Usuario normal - solo su almacén
        if ($usuario_almacen_id) {
            $sql = "SELECT p.id, p.nombre, p.descripcion, p.cantidad, p.estado, a.nombre as almacen_nombre
                    FROM productos p 
                    LEFT JOIN almacenes a ON p.almacen_id = a.id 
                    WHERE p.nombre LIKE ? AND p.almacen_id = ?
                    ORDER BY p.nombre 
                    LIMIT 8";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $search_term, $usuario_almacen_id);
        } else {
            echo json_encode(['success' => true, 'results' => []]);
            exit();
        }
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => (int)$row['id'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'] ?: 'Sin descripción',
            'cantidad' => (int)$row['cantidad'],
            'estado' => $row['estado'],
            'almacen' => $row['almacen_nombre'] ?: 'Sin almacén'
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'results' => $products
    ]);

} catch (Exception $e) {
    // En caso de error, devolver array vacío
    echo json_encode([
        'success' => true,
        'results' => []
    ]);
}
?>