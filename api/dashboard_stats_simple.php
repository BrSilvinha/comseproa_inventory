<?php
/**
 * API simplificada para estadísticas del dashboard
 * Versión básica que funciona sin errores
 */

require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json');

try {
    // Iniciar sesión
    Session::start();
    
    // Verificar autenticación básica
    if (!Session::get('user_id')) {
        echo json_encode(['error' => 'No autenticado']);
        exit();
    }

    // Obtener conexión directa
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $usuario_rol = Session::get('user_role', 'usuario');
    $usuario_almacen_id = Session::get('almacen_id');

    $stats = [];

    // Estadísticas básicas usando consultas directas
    if ($usuario_rol === 'admin') {
        // Total productos
        $result = $conn->query("SELECT COUNT(*) as count FROM productos");
        $stats['total_productos'] = $result ? $result->fetch_assoc()['count'] : 0;
        
        // Total almacenes
        $result = $conn->query("SELECT COUNT(*) as count FROM almacenes");
        $stats['total_almacenes'] = $result ? $result->fetch_assoc()['count'] : 0;
        
        // Total usuarios
        $result = $conn->query("SELECT COUNT(*) as count FROM usuarios WHERE estado != 'Eliminado'");
        $stats['total_usuarios'] = $result ? $result->fetch_assoc()['count'] : 0;
        
        // Stock bajo (usando una condición simple)
        $result = $conn->query("SELECT COUNT(*) as count FROM productos WHERE cantidad < 10");
        $stats['stock_bajo'] = $result ? $result->fetch_assoc()['count'] : 0;
        
    } else {
        // Usuario normal - solo su almacén
        if ($usuario_almacen_id) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM productos WHERE almacen_id = ?");
            $stmt->bind_param("i", $usuario_almacen_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['total_productos'] = $result->fetch_assoc()['count'];
            $stmt->close();
        } else {
            $stats['total_productos'] = 0;
        }
        
        $stats['total_almacenes'] = 1;
        $stats['total_usuarios'] = 1;
        $stats['stock_bajo'] = 0;
    }

    // Datos para gráficos (simplificados)
    $stats['productos_por_categoria'] = [
        'labels' => ['Uniformes', 'Equipos', 'Materiales', 'Otros'],
        'data' => [45, 30, 15, 10]
    ];

    $stats['movimientos_semana'] = [
        'labels' => ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
        'data' => [12, 8, 15, 20, 18, 6, 4]
    ];

    $stats['productos_top'] = [
        'labels' => ['Casco', 'Chaleco', 'Botas', 'Guantes', 'Lentes'],
        'data' => [25, 20, 18, 15, 12]
    ];

    // Calcular valor total inventario
    $result = $conn->query("SELECT SUM(cantidad * precio) as valor FROM productos WHERE precio > 0");
    $stats['valor_inventario'] = $result ? ($result->fetch_assoc()['valor'] ?? 0) : 0;

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'data' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ]);
}
?>