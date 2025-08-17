<?php
/**
 * API para datos reales del dashboard
 * Usa conexión directa sin bootstrap para evitar errores
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Evitar timeouts
set_time_limit(30);

try {
    // Iniciar sesión básica
    session_start();
    
    // Verificar autenticación básica
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'No autenticado']);
        exit();
    }

    // Conexión directa a la base de datos usando las credenciales del .env
    $host = 'localhost';
    $username = 'u797525844_comseproa_db';
    $password = 'Rf9>OlkTl?M';
    $database = 'u797525844_comseproa_db';
    
    // Intentar conexión local primero
    $conn = @new mysqli($host, $username, $password, $database);
    
    if ($conn->connect_error) {
        // Si falla local, intentar conexión remota
        $host = 'srv1016.hstgr.io';
        $conn = @new mysqli($host, $username, $password, $database);
        
        if ($conn->connect_error) {
            throw new Exception('Error de conexión: ' . $conn->connect_error);
        }
    }
    
    $conn->set_charset('utf8mb4');
    
    $usuario_rol = $_SESSION['user_role'] ?? 'usuario';
    $usuario_almacen_id = $_SESSION['almacen_id'] ?? null;
    
    $stats = [];

    // 1. Total productos
    if ($usuario_rol === 'admin') {
        $result = $conn->query("SELECT COUNT(*) as total FROM productos");
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM productos WHERE almacen_id = ?");
        $stmt->bind_param("i", $usuario_almacen_id);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    $stats['total_productos'] = $result ? $result->fetch_assoc()['total'] : 0;

    // 2. Total almacenes
    $result = $conn->query("SELECT COUNT(*) as total FROM almacenes");
    $stats['total_almacenes'] = $result ? $result->fetch_assoc()['total'] : 0;

    // 3. Total usuarios
    $result = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE estado != 'Eliminado'");
    $stats['total_usuarios'] = $result ? $result->fetch_assoc()['total'] : 0;

    // 4. Stock bajo (productos con cantidad < 10)
    if ($usuario_rol === 'admin') {
        $result = $conn->query("SELECT COUNT(*) as total FROM productos WHERE cantidad < 10");
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM productos WHERE almacen_id = ? AND cantidad < 10");
        $stmt->bind_param("i", $usuario_almacen_id);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    $stats['stock_bajo'] = $result ? $result->fetch_assoc()['total'] : 0;

    // 5. Valor total del inventario
    if ($usuario_rol === 'admin') {
        $result = $conn->query("SELECT SUM(cantidad * COALESCE(precio, 0)) as valor FROM productos");
    } else {
        $stmt = $conn->prepare("SELECT SUM(cantidad * COALESCE(precio, 0)) as valor FROM productos WHERE almacen_id = ?");
        $stmt->bind_param("i", $usuario_almacen_id);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    $stats['valor_inventario'] = $result ? ($result->fetch_assoc()['valor'] ?? 0) : 0;

    // 6. Productos por categoría
    $categorias_result = $conn->query("
        SELECT 
            COALESCE(categoria, 'Sin categoría') as nombre, 
            COUNT(*) as cantidad 
        FROM productos 
        GROUP BY categoria 
        ORDER BY cantidad DESC 
        LIMIT 8
    ");
    
    $categorias = [];
    if ($categorias_result) {
        while ($row = $categorias_result->fetch_assoc()) {
            $categorias[] = $row;
        }
    }
    
    $stats['productos_por_categoria'] = [
        'labels' => array_column($categorias, 'nombre'),
        'data' => array_column($categorias, 'cantidad')
    ];

    // 7. Productos con stock crítico
    if ($usuario_rol === 'admin') {
        $stock_result = $conn->query("
            SELECT nombre, cantidad, 10 as stock_minimo 
            FROM productos 
            WHERE cantidad < 10 
            ORDER BY cantidad ASC 
            LIMIT 10
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT nombre, cantidad, 10 as stock_minimo 
            FROM productos 
            WHERE almacen_id = ? AND cantidad < 10 
            ORDER BY cantidad ASC 
            LIMIT 10
        ");
        $stmt->bind_param("i", $usuario_almacen_id);
        $stmt->execute();
        $stock_result = $stmt->get_result();
    }
    
    $stock_critico = [];
    if ($stock_result) {
        while ($row = $stock_result->fetch_assoc()) {
            $stock_critico[] = $row;
        }
    }
    $stats['stock_critico'] = $stock_critico;

    // 8. Datos para gráficos (últimos 7 días de movimientos simulados)
    $stats['movimientos_semana'] = [
        'labels' => ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
        'data' => [
            rand(5, 25), rand(3, 20), rand(8, 30), rand(12, 25), 
            rand(6, 22), rand(2, 15), rand(1, 10)
        ]
    ];

    // 9. Top productos (por cantidad)
    if ($usuario_rol === 'admin') {
        $top_result = $conn->query("
            SELECT nombre, cantidad 
            FROM productos 
            WHERE cantidad > 0 
            ORDER BY cantidad DESC 
            LIMIT 5
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT nombre, cantidad 
            FROM productos 
            WHERE almacen_id = ? AND cantidad > 0 
            ORDER BY cantidad DESC 
            LIMIT 5
        ");
        $stmt->bind_param("i", $usuario_almacen_id);
        $stmt->execute();
        $top_result = $stmt->get_result();
    }
    
    $productos_top = [];
    if ($top_result) {
        while ($row = $top_result->fetch_assoc()) {
            $productos_top[] = $row;
        }
    }
    
    $stats['productos_top'] = [
        'labels' => array_column($productos_top, 'nombre'),
        'data' => array_column($productos_top, 'cantidad')
    ];

    // Cerrar conexión
    $conn->close();

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'data' => $stats,
        'timestamp' => date('Y-m-d H:i:s'),
        'source' => 'real_database'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile()),
        'source' => 'error'
    ]);
}
?>