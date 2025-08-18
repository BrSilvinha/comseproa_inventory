<?php
/**
 * API para datos reales ÚNICAMENTE - Sin datos de prueba
 */

// Configuración básica
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');
session_start();

try {
    // Verificar autenticación
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('No autenticado');
    }

    // Conexión usando config existente
    require_once '../config/database.php';
    
    if (!$conn) {
        throw new Exception('No hay conexión a la base de datos');
    }
    
    $usuario_rol = $_SESSION['user_role'] ?? 'usuario';
    $usuario_almacen_id = $_SESSION['almacen_id'] ?? null;
    
    $stats = [];

    // 1. DATOS REALES: Total productos
    $sql = "SELECT COUNT(*) as total FROM productos";
    if ($usuario_rol !== 'admin' && $usuario_almacen_id) {
        $sql .= " WHERE almacen_id = $usuario_almacen_id";
    }
    $result = $conn->query($sql);
    $stats['total_productos'] = $result ? $result->fetch_assoc()['total'] : 0;

    // 2. DATOS REALES: Total almacenes
    $result = $conn->query("SELECT COUNT(*) as total FROM almacenes");
    $stats['total_almacenes'] = $result ? $result->fetch_assoc()['total'] : 0;

    // 3. DATOS REALES: Total usuarios activos
    $result = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE estado != 'Eliminado'");
    $stats['total_usuarios'] = $result ? $result->fetch_assoc()['total'] : 0;

    // 4. DATOS REALES: Stock bajo (menos de 5 unidades)
    $sql = "SELECT COUNT(*) as total FROM productos WHERE cantidad < 5";
    if ($usuario_rol !== 'admin' && $usuario_almacen_id) {
        $sql .= " AND almacen_id = $usuario_almacen_id";
    }
    $result = $conn->query($sql);
    $stats['stock_bajo'] = $result ? $result->fetch_assoc()['total'] : 0;

    // 5. DATOS REALES: Valor total inventario
    $sql = "SELECT SUM(cantidad * COALESCE(precio, 0)) as valor FROM productos";
    if ($usuario_rol !== 'admin' && $usuario_almacen_id) {
        $sql .= " WHERE almacen_id = $usuario_almacen_id";
    }
    $result = $conn->query($sql);
    $stats['valor_inventario'] = $result ? round($result->fetch_assoc()['valor'] ?? 0, 2) : 0;

    // 6. DATOS REALES: Productos por categoría
    $sql = "SELECT categoria as nombre, COUNT(*) as cantidad FROM productos WHERE categoria IS NOT NULL AND categoria != '' GROUP BY categoria ORDER BY cantidad DESC";
    if ($usuario_rol !== 'admin' && $usuario_almacen_id) {
        $sql = "SELECT categoria as nombre, COUNT(*) as cantidad FROM productos WHERE almacen_id = $usuario_almacen_id AND categoria IS NOT NULL AND categoria != '' GROUP BY categoria ORDER BY cantidad DESC";
    }
    
    $result = $conn->query($sql);
    $categorias_labels = [];
    $categorias_data = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categorias_labels[] = $row['nombre'];
            $categorias_data[] = (int)$row['cantidad'];
        }
    }
    
    // Si no hay categorías, mostrar mensaje
    if (empty($categorias_labels)) {
        $categorias_labels = ['Sin datos'];
        $categorias_data = [0];
    }
    
    $stats['productos_por_categoria'] = [
        'labels' => $categorias_labels,
        'data' => $categorias_data
    ];

    // 7. DATOS REALES: Stock crítico (productos con menos de 5 unidades)
    $sql = "SELECT nombre, cantidad, 5 as stock_minimo FROM productos WHERE cantidad < 5 ORDER BY cantidad ASC LIMIT 10";
    if ($usuario_rol !== 'admin' && $usuario_almacen_id) {
        $sql = "SELECT nombre, cantidad, 5 as stock_minimo FROM productos WHERE almacen_id = $usuario_almacen_id AND cantidad < 5 ORDER BY cantidad ASC LIMIT 10";
    }
    
    $result = $conn->query($sql);
    $stock_critico = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $stock_critico[] = [
                'nombre' => $row['nombre'],
                'cantidad' => (int)$row['cantidad'],
                'stock_minimo' => 5
            ];
        }
    }
    
    $stats['stock_critico'] = $stock_critico;

    // 8. DATOS REALES: Movimientos de la semana (si existe tabla movimientos)
    $movimientos_result = $conn->query("SHOW TABLES LIKE 'movimientos'");
    if ($movimientos_result && $movimientos_result->num_rows > 0) {
        // Tabla existe, obtener datos reales
        $sql = "SELECT DAYNAME(fecha_movimiento) as dia, COUNT(*) as movimientos 
                FROM movimientos 
                WHERE fecha_movimiento >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                GROUP BY DATE(fecha_movimiento), DAYNAME(fecha_movimiento) 
                ORDER BY fecha_movimiento";
        
        $result = $conn->query($sql);
        $movimientos_labels = [];
        $movimientos_data = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $movimientos_labels[] = $row['dia'];
                $movimientos_data[] = (int)$row['movimientos'];
            }
        }
        
        if (empty($movimientos_labels)) {
            $movimientos_labels = ['Sin datos'];
            $movimientos_data = [0];
        }
    } else {
        // No hay tabla movimientos, datos vacíos
        $movimientos_labels = ['Sin datos'];
        $movimientos_data = [0];
    }
    
    $stats['movimientos_semana'] = [
        'labels' => $movimientos_labels,
        'data' => $movimientos_data
    ];

    // 9. DATOS REALES: Top 5 productos por cantidad
    $sql = "SELECT nombre, cantidad FROM productos WHERE cantidad > 0 ORDER BY cantidad DESC LIMIT 5";
    if ($usuario_rol !== 'admin' && $usuario_almacen_id) {
        $sql = "SELECT nombre, cantidad FROM productos WHERE almacen_id = $usuario_almacen_id AND cantidad > 0 ORDER BY cantidad DESC LIMIT 5";
    }
    
    $result = $conn->query($sql);
    $productos_labels = [];
    $productos_data = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $productos_labels[] = $row['nombre'];
            $productos_data[] = (int)$row['cantidad'];
        }
    }
    
    if (empty($productos_labels)) {
        $productos_labels = ['Sin productos'];
        $productos_data = [0];
    }
    
    $stats['productos_top'] = [
        'labels' => $productos_labels,
        'data' => $productos_data
    ];

    // Respuesta con datos REALES únicamente
    echo json_encode([
        'success' => true,
        'data' => $stats,
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => 'Datos reales de la base de datos'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'data' => [
            'total_productos' => 0,
            'total_almacenes' => 0,
            'total_usuarios' => 0,
            'stock_bajo' => 0,
            'valor_inventario' => 0,
            'productos_por_categoria' => ['labels' => ['Sin datos'], 'data' => [0]],
            'movimientos_semana' => ['labels' => ['Sin datos'], 'data' => [0]],
            'productos_top' => ['labels' => ['Sin datos'], 'data' => [0]],
            'stock_critico' => []
        ]
    ]);
}
?>