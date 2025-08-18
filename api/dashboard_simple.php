<?php
/**
 * Dashboard API - Datos simples sin dependencias complejas
 */
header('Content-Type: application/json');
session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

try {
    // Conexión directa a la base de datos
    $host = 'localhost';
    $username = 'u797525844_comseproa_db';
    $password = 'Rf9>OlkTl?M';
    $database = 'u797525844_comseproa_db';
    
    $conn = new mysqli($host, $username, $password, $database);
    
    if ($conn->connect_error) {
        throw new Exception('Connection failed: ' . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    $usuario_rol = $_SESSION['user_role'] ?? 'usuario';
    $usuario_almacen_id = $_SESSION['almacen_id'] ?? null;
    
    $stats = [];

    // 1. Total productos
    if ($usuario_rol !== 'admin' && $usuario_almacen_id) {
        $sql = "SELECT COUNT(*) as total FROM productos WHERE almacen_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $usuario_almacen_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_productos'] = $result ? $result->fetch_assoc()['total'] : 0;
        $stmt->close();
    } else {
        $result = $conn->query("SELECT COUNT(*) as total FROM productos");
        $stats['total_productos'] = $result ? $result->fetch_assoc()['total'] : 0;
    }

    // 2. Total almacenes
    $result = $conn->query("SELECT COUNT(*) as total FROM almacenes");
    $stats['total_almacenes'] = $result ? $result->fetch_assoc()['total'] : 0;

    // 3. Total usuarios activos
    $result = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE estado != 'Eliminado'");
    $stats['total_usuarios'] = $result ? $result->fetch_assoc()['total'] : 0;

    // 4. Stock bajo (menos de 5 unidades)
    if ($usuario_rol !== 'admin' && $usuario_almacen_id) {
        $sql = "SELECT COUNT(*) as total FROM productos WHERE cantidad < 5 AND almacen_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $usuario_almacen_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['stock_bajo'] = $result ? $result->fetch_assoc()['total'] : 0;
        $stmt->close();
    } else {
        $result = $conn->query("SELECT COUNT(*) as total FROM productos WHERE cantidad < 5");
        $stats['stock_bajo'] = $result ? $result->fetch_assoc()['total'] : 0;
    }

    // 5. Valor estimado del inventario
    if ($usuario_rol !== 'admin' && $usuario_almacen_id) {
        $sql = "SELECT SUM(cantidad) as total_productos FROM productos WHERE almacen_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $usuario_almacen_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $total_items = $result ? ($result->fetch_assoc()['total_productos'] ?? 0) : 0;
        $stmt->close();
    } else {
        $result = $conn->query("SELECT SUM(cantidad) as total_productos FROM productos");
        $total_items = $result ? ($result->fetch_assoc()['total_productos'] ?? 0) : 0;
    }
    $stats['valor_inventario'] = round($total_items * 50, 2);

    // 6. Productos por categoría
    if ($usuario_rol !== 'admin' && $usuario_almacen_id) {
        $sql = "SELECT c.nombre, COUNT(p.id) as cantidad 
                FROM categorias c 
                LEFT JOIN productos p ON c.id = p.categoria_id AND p.almacen_id = ?
                GROUP BY c.id, c.nombre 
                ORDER BY cantidad DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $usuario_almacen_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $sql = "SELECT c.nombre, COUNT(p.id) as cantidad 
                FROM categorias c 
                LEFT JOIN productos p ON c.id = p.categoria_id
                GROUP BY c.id, c.nombre 
                ORDER BY cantidad DESC";
        $result = $conn->query($sql);
    }
    
    $categorias_labels = [];
    $categorias_data = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categorias_labels[] = $row['nombre'];
            $categorias_data[] = (int)$row['cantidad'];
        }
        if (isset($stmt)) $stmt->close();
    }
    
    // Si no hay categorías, usar distribución por almacenes
    if (empty($categorias_labels)) {
        $sql = "SELECT a.nombre, COUNT(p.id) as cantidad 
                FROM almacenes a 
                LEFT JOIN productos p ON a.id = p.almacen_id 
                GROUP BY a.id 
                ORDER BY cantidad DESC";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $categorias_labels[] = $row['nombre'];
                $categorias_data[] = (int)$row['cantidad'];
            }
        } else {
            $categorias_labels = ['Sin datos'];
            $categorias_data = [0];
        }
    }
    
    $stats['productos_por_categoria'] = [
        'labels' => $categorias_labels,
        'data' => $categorias_data
    ];

    // 7. Stock crítico
    if ($usuario_rol !== 'admin' && $usuario_almacen_id) {
        $sql = "SELECT nombre, cantidad, 5 as stock_minimo FROM productos WHERE almacen_id = ? AND cantidad < 5 ORDER BY cantidad ASC LIMIT 10";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $usuario_almacen_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $sql = "SELECT nombre, cantidad, 5 as stock_minimo FROM productos WHERE cantidad < 5 ORDER BY cantidad ASC LIMIT 10";
        $result = $conn->query($sql);
    }
    
    $stock_critico = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $stock_critico[] = [
                'nombre' => $row['nombre'],
                'cantidad' => (int)$row['cantidad'],
                'stock_minimo' => 5
            ];
        }
        if (isset($stmt)) $stmt->close();
    }
    
    $stats['stock_critico'] = $stock_critico;

    // 8. Movimientos últimos 7 días (verificar si tabla existe)
    $tables_result = $conn->query("SHOW TABLES LIKE 'movimientos'");
    if ($tables_result && $tables_result->num_rows > 0) {
        $sql = "SELECT DATE(fecha) as fecha_mov, COUNT(*) as movimientos 
                FROM movimientos 
                WHERE fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                GROUP BY DATE(fecha) 
                ORDER BY fecha";
        
        $result = $conn->query($sql);
        $movimientos_labels = [];
        $movimientos_data = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $fecha = date('D', strtotime($row['fecha_mov']));
                $movimientos_labels[] = $fecha;
                $movimientos_data[] = (int)$row['movimientos'];
            }
        }
        
        if (empty($movimientos_labels)) {
            $movimientos_labels = ['Sin movimientos'];
            $movimientos_data = [0];
        }
    } else {
        $movimientos_labels = ['Sin datos'];
        $movimientos_data = [0];
    }
    
    $stats['movimientos_semana'] = [
        'labels' => $movimientos_labels,
        'data' => $movimientos_data
    ];

    // 9. Top 5 productos por cantidad
    if ($usuario_rol !== 'admin' && $usuario_almacen_id) {
        $sql = "SELECT nombre, cantidad FROM productos WHERE almacen_id = ? AND cantidad > 0 ORDER BY cantidad DESC LIMIT 5";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $usuario_almacen_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $sql = "SELECT nombre, cantidad FROM productos WHERE cantidad > 0 ORDER BY cantidad DESC LIMIT 5";
        $result = $conn->query($sql);
    }
    
    $productos_labels = [];
    $productos_data = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $productos_labels[] = $row['nombre'];
            $productos_data[] = (int)$row['cantidad'];
        }
        if (isset($stmt)) $stmt->close();
    }
    
    if (empty($productos_labels)) {
        $productos_labels = ['Sin productos'];
        $productos_data = [0];
    }
    
    $stats['productos_top'] = [
        'labels' => $productos_labels,
        'data' => $productos_data
    ];

    $conn->close();
    
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