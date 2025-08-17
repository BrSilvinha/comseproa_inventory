<?php
/**
 * API para estadísticas del dashboard
 * Retorna datos en formato JSON para gráficos
 */

require_once __DIR__ . '/../bootstrap.php';

// Configurar cabeceras para JSON
header('Content-Type: application/json');

try {
    // Iniciar sesión explícitamente
    Session::start();
    
    // Verificar autenticación
    if (!Session::isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado']);
        exit();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de sesión: ' . $e->getMessage()]);
    exit();
}

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

try {
    $db = Database::getInstance();
    $usuario_rol = Session::get('user_role', 'usuario');
    $usuario_almacen_id = Session::get('almacen_id');

    $stats = [];

    // 1. Estadísticas generales
    if ($usuario_rol === 'admin') {
        // Admin ve todo
        $stats['total_productos'] = $db->fetchOne("SELECT COUNT(*) as count FROM productos")['count'];
        $stats['total_almacenes'] = $db->fetchOne("SELECT COUNT(*) as count FROM almacenes WHERE estado = 'activo'")['count'];
        $stats['total_usuarios'] = $db->fetchOne("SELECT COUNT(*) as count FROM usuarios WHERE estado = 'activo'")['count'];
        $stats['stock_bajo'] = $db->fetchOne("SELECT COUNT(*) as count FROM productos WHERE cantidad <= stock_minimo")['count'];
    } else {
        // Usuario normal ve solo su almacén
        $stats['total_productos'] = $db->fetchOne("SELECT COUNT(*) as count FROM productos WHERE almacen_id = ?", [$usuario_almacen_id])['count'];
        $stats['total_almacenes'] = 1; // Solo su almacén
        $stats['total_usuarios'] = $db->fetchOne("SELECT COUNT(*) as count FROM usuarios WHERE almacen_id = ? AND estado = 'activo'", [$usuario_almacen_id])['count'];
        $stats['stock_bajo'] = $db->fetchOne("SELECT COUNT(*) as count FROM productos WHERE almacen_id = ? AND cantidad <= stock_minimo", [$usuario_almacen_id])['count'];
    }

    // 2. Productos por categoría (para gráfico de dona)
    $sql_categorias = "SELECT c.nombre, COUNT(p.id) as cantidad 
                       FROM categorias c 
                       LEFT JOIN productos p ON c.id = p.categoria_id";
    
    if ($usuario_rol !== 'admin') {
        $sql_categorias .= " AND p.almacen_id = ?";
        $categorias_result = $db->fetchAll($sql_categorias . " GROUP BY c.id ORDER BY cantidad DESC", [$usuario_almacen_id]);
    } else {
        $categorias_result = $db->fetchAll($sql_categorias . " GROUP BY c.id ORDER BY cantidad DESC");
    }

    $stats['productos_por_categoria'] = [
        'labels' => array_column($categorias_result, 'nombre'),
        'data' => array_column($categorias_result, 'cantidad')
    ];

    // 3. Movimientos últimos 7 días (para gráfico de línea)
    $sql_movimientos = "SELECT DATE(fecha_movimiento) as fecha, COUNT(*) as movimientos 
                        FROM movimientos 
                        WHERE fecha_movimiento >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    
    if ($usuario_rol !== 'admin') {
        $sql_movimientos .= " AND almacen_id = ?";
        $movimientos_result = $db->fetchAll($sql_movimientos . " GROUP BY DATE(fecha_movimiento) ORDER BY fecha", [$usuario_almacen_id]);
    } else {
        $movimientos_result = $db->fetchAll($sql_movimientos . " GROUP BY DATE(fecha_movimiento) ORDER BY fecha");
    }

    // Generar fechas de los últimos 7 días
    $fechas = [];
    $movimientos_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $fecha = date('Y-m-d', strtotime("-{$i} days"));
        $fechas[] = date('d/m', strtotime($fecha));
        
        $movimientos_del_dia = 0;
        foreach ($movimientos_result as $mov) {
            if ($mov['fecha'] === $fecha) {
                $movimientos_del_dia = (int)$mov['movimientos'];
                break;
            }
        }
        $movimientos_data[] = $movimientos_del_dia;
    }

    $stats['movimientos_semana'] = [
        'labels' => $fechas,
        'data' => $movimientos_data
    ];

    // 4. Top 5 productos con más movimientos
    $sql_top_productos = "SELECT p.nombre, COUNT(m.id) as movimientos 
                          FROM productos p 
                          INNER JOIN movimientos m ON p.id = m.producto_id 
                          WHERE m.fecha_movimiento >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    
    if ($usuario_rol !== 'admin') {
        $sql_top_productos .= " AND p.almacen_id = ?";
        $top_productos = $db->fetchAll($sql_top_productos . " GROUP BY p.id ORDER BY movimientos DESC LIMIT 5", [$usuario_almacen_id]);
    } else {
        $top_productos = $db->fetchAll($sql_top_productos . " GROUP BY p.id ORDER BY movimientos DESC LIMIT 5");
    }

    $stats['top_productos'] = [
        'labels' => array_column($top_productos, 'nombre'),
        'data' => array_column($top_productos, 'movimientos')
    ];

    // 5. Stock crítico (productos con stock bajo)
    $sql_stock_critico = "SELECT nombre, cantidad, stock_minimo 
                          FROM productos 
                          WHERE cantidad <= stock_minimo";
    
    if ($usuario_rol !== 'admin') {
        $sql_stock_critico .= " AND almacen_id = ?";
        $stock_critico = $db->fetchAll($sql_stock_critico . " ORDER BY (cantidad/stock_minimo) ASC LIMIT 10", [$usuario_almacen_id]);
    } else {
        $stock_critico = $db->fetchAll($sql_stock_critico . " ORDER BY (cantidad/stock_minimo) ASC LIMIT 10");
    }

    $stats['stock_critico'] = $stock_critico;

    // 6. Valor total del inventario
    $sql_valor_inventario = "SELECT SUM(cantidad * precio_unitario) as valor_total FROM productos WHERE cantidad > 0";
    
    if ($usuario_rol !== 'admin') {
        $valor_inventario = $db->fetchOne($sql_valor_inventario . " AND almacen_id = ?", [$usuario_almacen_id]);
    } else {
        $valor_inventario = $db->fetchOne($sql_valor_inventario);
    }

    $stats['valor_inventario'] = (float)($valor_inventario['valor_total'] ?? 0);

    // Retornar JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $stats,
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    Logger::error("Dashboard stats API error", [
        'message' => $e->getMessage(),
        'user_id' => Session::get('user_id')
    ]);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
}
?>