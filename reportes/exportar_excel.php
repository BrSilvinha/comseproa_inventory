<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

require_once "../config/database.php";

// Verificar permisos
$usuario_rol = $_SESSION["user_role"] ?? "usuario";
$usuario_almacen_id = $_SESSION["almacen_id"] ?? null;
$user_name = $_SESSION["user_name"] ?? "Usuario";

// Obtener tipo de reporte
$tipo_reporte = $_GET['tipo'] ?? 'inventario';
$almacen_id = isset($_GET['almacen_id']) ? (int)$_GET['almacen_id'] : null;

// Verificar permisos específicos
if ($tipo_reporte == 'usuarios' && $usuario_rol != 'admin') {
    http_response_code(403);
    exit('No tienes permisos para generar este reporte');
}

// Configurar headers para descarga de Excel
$filename = 'reporte_' . $tipo_reporte . '_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);
header('Pragma: no-cache');
header('Expires: 0');

// Crear el output
$output = fopen('php://output', 'w');

// BOM para UTF-8 en Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

switch($tipo_reporte) {
    case 'inventario':
        generarExcelInventario($output, $conn, $almacen_id, $usuario_rol, $usuario_almacen_id);
        break;
    case 'movimientos':
        generarExcelMovimientos($output, $conn, $usuario_rol, $usuario_almacen_id);
        break;
    case 'usuarios':
        generarExcelUsuarios($output, $conn);
        break;
    default:
        fputcsv($output, ['Error: Tipo de reporte no válido']);
}

fclose($output);

function generarExcelInventario($output, $conn, $almacen_id, $usuario_rol, $usuario_almacen_id) {
    // Verificar permisos
    if ($usuario_rol != 'admin' && $almacen_id && $usuario_almacen_id != $almacen_id) {
        fputcsv($output, ['Error: No tienes permiso para ver este reporte']);
        return;
    }

    // Encabezado del reporte
    fputcsv($output, ['REPORTE DE INVENTARIO - GRUPO SEAL']);
    fputcsv($output, ['Generado por: ' . $_SESSION["user_name"]]);
    fputcsv($output, ['Fecha: ' . date('d/m/Y H:i:s')]);
    
    if ($almacen_id) {
        $stmt = $conn->prepare("SELECT nombre, ubicacion FROM almacenes WHERE id = ?");
        $stmt->bind_param("i", $almacen_id);
        $stmt->execute();
        $almacen_info = $stmt->get_result()->fetch_assoc();
        fputcsv($output, ['Almacén: ' . $almacen_info['nombre']]);
        fputcsv($output, ['Ubicación: ' . $almacen_info['ubicacion']]);
    } else {
        fputcsv($output, ['Tipo: Inventario General']);
    }
    
    fputcsv($output, []); // Línea en blanco

    // Estadísticas generales
    if ($almacen_id) {
        $sql_stats = "SELECT COUNT(DISTINCT p.categoria_id) as total_categorias, COUNT(p.id) as total_productos, 
                      COALESCE(SUM(p.cantidad), 0) as total_stock, COALESCE(AVG(p.cantidad), 0) as promedio_stock,
                      COALESCE(MIN(p.cantidad), 0) as stock_minimo, COALESCE(MAX(p.cantidad), 0) as stock_maximo
                      FROM productos p WHERE p.almacen_id = ?";
        $stmt = $conn->prepare($sql_stats);
        $stmt->bind_param("i", $almacen_id);
    } else {
        $sql_stats = "SELECT COUNT(DISTINCT p.categoria_id) as total_categorias, COUNT(p.id) as total_productos, 
                      COALESCE(SUM(p.cantidad), 0) as total_stock, COALESCE(AVG(p.cantidad), 0) as promedio_stock,
                      COALESCE(MIN(p.cantidad), 0) as stock_minimo, COALESCE(MAX(p.cantidad), 0) as stock_maximo
                      FROM productos p";
        $stmt = $conn->prepare($sql_stats);
    }
    
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();

    fputcsv($output, ['ESTADÍSTICAS GENERALES']);
    fputcsv($output, ['Métrica', 'Valor']);
    fputcsv($output, ['Total Productos', number_format($stats['total_productos'])]);
    fputcsv($output, ['Total Categorías', number_format($stats['total_categorias'])]);
    fputcsv($output, ['Stock Total', number_format($stats['total_stock']) . ' unidades']);
    fputcsv($output, ['Promedio por Producto', number_format($stats['promedio_stock'], 2) . ' unidades']);
    fputcsv($output, ['Stock Mínimo', number_format($stats['stock_minimo']) . ' unidades']);
    fputcsv($output, ['Stock Máximo', number_format($stats['stock_maximo']) . ' unidades']);
    fputcsv($output, []); // Línea en blanco

    // Distribución por categorías
    fputcsv($output, ['DISTRIBUCIÓN POR CATEGORÍAS']);
    if ($almacen_id) {
        $sql_categorias = "SELECT c.nombre, COUNT(p.id) as total_productos, COALESCE(SUM(p.cantidad), 0) as total_stock
                          FROM categorias c LEFT JOIN productos p ON c.id = p.categoria_id AND p.almacen_id = ?
                          GROUP BY c.id, c.nombre ORDER BY total_stock DESC";
        $stmt = $conn->prepare($sql_categorias);
        $stmt->bind_param("i", $almacen_id);
    } else {
        $sql_categorias = "SELECT c.nombre, COUNT(p.id) as total_productos, COALESCE(SUM(p.cantidad), 0) as total_stock
                          FROM categorias c LEFT JOIN productos p ON c.id = p.categoria_id
                          GROUP BY c.id, c.nombre ORDER BY total_stock DESC";
        $stmt = $conn->prepare($sql_categorias);
    }
    
    $stmt->execute();
    $categorias = $stmt->get_result();

    fputcsv($output, ['Categoría', 'Productos', 'Stock Total', 'Porcentaje']);
    $total_general = $stats['total_stock'];
    while ($cat = $categorias->fetch_assoc()) {
        $porcentaje = $total_general > 0 ? ($cat['total_stock'] / $total_general) * 100 : 0;
        fputcsv($output, [
            $cat['nombre'],
            number_format($cat['total_productos']),
            number_format($cat['total_stock']),
            number_format($porcentaje, 2) . '%'
        ]);
    }
    fputcsv($output, []); // Línea en blanco

    // Productos con stock crítico
    fputcsv($output, ['PRODUCTOS CON STOCK CRÍTICO (< 10 unidades)']);
    if ($almacen_id) {
        $sql_critico = "SELECT p.nombre, p.cantidad, c.nombre as categoria 
                        FROM productos p JOIN categorias c ON p.categoria_id = c.id 
                        WHERE p.almacen_id = ? AND p.cantidad < 10 ORDER BY p.cantidad ASC";
        $stmt = $conn->prepare($sql_critico);
        $stmt->bind_param("i", $almacen_id);
        fputcsv($output, ['Producto', 'Categoría', 'Stock Actual', 'Estado']);
    } else {
        $sql_critico = "SELECT p.nombre, p.cantidad, c.nombre as categoria, a.nombre as almacen 
                        FROM productos p JOIN categorias c ON p.categoria_id = c.id 
                        JOIN almacenes a ON p.almacen_id = a.id 
                        WHERE p.cantidad < 10 ORDER BY p.cantidad ASC";
        $stmt = $conn->prepare($sql_critico);
        fputcsv($output, ['Producto', 'Categoría', 'Almacén', 'Stock Actual', 'Estado']);
    }
    
    $stmt->execute();
    $productos_criticos = $stmt->get_result();

    while ($prod = $productos_criticos->fetch_assoc()) {
        $estado = $prod['cantidad'] < 5 ? 'CRÍTICO' : 'BAJO';
        $row = [$prod['nombre'], $prod['categoria']];
        if (!$almacen_id) {
            $row[] = $prod['almacen'];
        }
        $row[] = $prod['cantidad'];
        $row[] = $estado;
        fputcsv($output, $row);
    }

    if ($productos_criticos->num_rows == 0) {
        fputcsv($output, ['No hay productos con stock crítico']);
    }

    fputcsv($output, []); // Línea en blanco

    // Top 10 productos con mayor stock
    fputcsv($output, ['TOP 10 PRODUCTOS CON MAYOR STOCK']);
    if ($almacen_id) {
        $sql_alto = "SELECT p.nombre, p.cantidad, c.nombre as categoria
                     FROM productos p JOIN categorias c ON p.categoria_id = c.id
                     WHERE p.almacen_id = ? ORDER BY p.cantidad DESC LIMIT 10";
        $stmt = $conn->prepare($sql_alto);
        $stmt->bind_param("i", $almacen_id);
        fputcsv($output, ['Posición', 'Producto', 'Categoría', 'Stock']);
    } else {
        $sql_alto = "SELECT p.nombre, p.cantidad, c.nombre as categoria, a.nombre as almacen
                     FROM productos p JOIN categorias c ON p.categoria_id = c.id
                     JOIN almacenes a ON p.almacen_id = a.id
                     ORDER BY p.cantidad DESC LIMIT 10";
        $stmt = $conn->prepare($sql_alto);
        fputcsv($output, ['Posición', 'Producto', 'Categoría', 'Almacén', 'Stock']);
    }
    
    $stmt->execute();
    $productos_alto = $stmt->get_result();

    $posicion = 1;
    while ($prod = $productos_alto->fetch_assoc()) {
        $row = [$posicion, $prod['nombre'], $prod['categoria']];
        if (!$almacen_id) {
            $row[] = $prod['almacen'];
        }
        $row[] = number_format($prod['cantidad']);
        fputcsv($output, $row);
        $posicion++;
    }
}

function generarExcelMovimientos($output, $conn, $usuario_rol, $usuario_almacen_id) {
    // Obtener filtros
    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
    $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
    $filtro_almacen = $_GET['almacen'] ?? '';
    $filtro_tipo = $_GET['tipo'] ?? '';

    // Encabezado
    fputcsv($output, ['REPORTE DE MOVIMIENTOS - GRUPO SEAL']);
    fputcsv($output, ['Generado por: ' . $_SESSION["user_name"]]);
    fputcsv($output, ['Fecha: ' . date('d/m/Y H:i:s')]);
    fputcsv($output, ['Período: ' . $fecha_inicio . ' al ' . $fecha_fin]);
    if ($filtro_tipo) fputcsv($output, ['Tipo de movimiento: ' . ucfirst($filtro_tipo)]);
    fputcsv($output, []); // Línea en blanco

    // Estadísticas
    $param_fecha_inicio = $fecha_inicio . ' 00:00:00';
    $param_fecha_fin = $fecha_fin . ' 23:59:59';
    
    $sql_stats = "SELECT COUNT(*) as total_movimientos,
                  SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completados,
                  SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                  SUM(CASE WHEN estado = 'rechazado' THEN 1 ELSE 0 END) as rechazados
                  FROM movimientos WHERE fecha BETWEEN ? AND ?";
    
    $params = [$param_fecha_inicio, $param_fecha_fin];
    $types = "ss";
    
    if ($usuario_rol != 'admin') {
        $sql_stats .= " AND (almacen_origen = ? OR almacen_destino = ?)";
        $params[] = $usuario_almacen_id;
        $params[] = $usuario_almacen_id;
        $types .= "ii";
    }
    
    $stmt = $conn->prepare($sql_stats);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();

    fputcsv($output, ['ESTADÍSTICAS DEL PERÍODO']);
    fputcsv($output, ['Métrica', 'Cantidad']);
    fputcsv($output, ['Total Movimientos', number_format($stats['total_movimientos'])]);
    fputcsv($output, ['Completados', number_format($stats['completados'])]);
    fputcsv($output, ['Pendientes', number_format($stats['pendientes'])]);
    fputcsv($output, ['Rechazados', number_format($stats['rechazados'])]);
    fputcsv($output, []); // Línea en blanco

    // Detalle de movimientos
    fputcsv($output, ['DETALLE DE MOVIMIENTOS']);
    
    $sql_movimientos = "SELECT m.id, m.fecha, m.cantidad, m.estado, m.tipo as tipo_movimiento,
                        p.nombre as producto_nombre, CONCAT('PROD-', LPAD(p.id, 4, '0')) as producto_codigo,
                        ao.nombre as almacen_origen, ad.nombre as almacen_destino, u.nombre as usuario_nombre
                        FROM movimientos m
                        LEFT JOIN productos p ON m.producto_id = p.id
                        LEFT JOIN almacenes ao ON m.almacen_origen = ao.id
                        LEFT JOIN almacenes ad ON m.almacen_destino = ad.id
                        LEFT JOIN usuarios u ON m.usuario_id = u.id
                        WHERE m.fecha BETWEEN ? AND ?";

    $params = [$param_fecha_inicio, $param_fecha_fin];
    $types = "ss";

    if (!empty($filtro_almacen) && $usuario_rol == 'admin') {
        $sql_movimientos .= " AND (m.almacen_origen = ? OR m.almacen_destino = ?)";
        $params[] = $filtro_almacen;
        $params[] = $filtro_almacen;
        $types .= "ii";
    } elseif ($usuario_rol != 'admin') {
        $sql_movimientos .= " AND (m.almacen_origen = ? OR m.almacen_destino = ?)";
        $params[] = $usuario_almacen_id;
        $params[] = $usuario_almacen_id;
        $types .= "ii";
    }

    if (!empty($filtro_tipo)) {
        $sql_movimientos .= " AND m.tipo = ?";
        $params[] = $filtro_tipo;
        $types .= "s";
    }

    $sql_movimientos .= " ORDER BY m.fecha DESC LIMIT 1000";
    
    $stmt = $conn->prepare($sql_movimientos);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $movimientos = $stmt->get_result();

    fputcsv($output, ['ID', 'Fecha', 'Producto', 'Código', 'Cantidad', 'Origen', 'Destino', 'Usuario', 'Estado', 'Tipo']);

    while ($mov = $movimientos->fetch_assoc()) {
        fputcsv($output, [
            str_pad($mov['id'], 4, '0', STR_PAD_LEFT),
            date('d/m/Y H:i', strtotime($mov['fecha'])),
            $mov['producto_nombre'],
            $mov['producto_codigo'],
            number_format($mov['cantidad']),
            $mov['almacen_origen'] ?? 'Sistema',
            $mov['almacen_destino'] ?? 'Sistema',
            $mov['usuario_nombre'],
            ucfirst($mov['estado']),
            ucfirst($mov['tipo_movimiento'])
        ]);
    }
}

function generarExcelUsuarios($output, $conn) {
    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
    $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');

    // Encabezado
    fputcsv($output, ['REPORTE DE ACTIVIDAD DE USUARIOS - GRUPO SEAL']);
    fputcsv($output, ['Generado por: ' . $_SESSION["user_name"]]);
    fputcsv($output, ['Fecha: ' . date('d/m/Y H:i:s')]);
    fputcsv($output, ['Período: ' . $fecha_inicio . ' al ' . $fecha_fin]);
    fputcsv($output, []); // Línea en blanco

    $param_fecha_inicio = $fecha_inicio . ' 00:00:00';
    $param_fecha_fin = $fecha_fin . ' 23:59:59';

    // Estadísticas generales
    $sql_stats = "SELECT COUNT(DISTINCT u.id) as usuarios_activos,
                  (SELECT COUNT(*) FROM movimientos m WHERE m.fecha BETWEEN ? AND ?) + 
                  (SELECT COUNT(*) FROM solicitudes_transferencia s WHERE s.fecha_solicitud BETWEEN ? AND ?) as total_actividades
                  FROM usuarios u WHERE u.estado = 'activo'";
    
    $stmt = $conn->prepare($sql_stats);
    $stmt->bind_param("ssss", $param_fecha_inicio, $param_fecha_fin, $param_fecha_inicio, $param_fecha_fin);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();

    fputcsv($output, ['ESTADÍSTICAS GENERALES']);
    fputcsv($output, ['Métrica', 'Valor']);
    fputcsv($output, ['Usuarios Activos', number_format($stats['usuarios_activos'])]);
    fputcsv($output, ['Total Actividades', number_format($stats['total_actividades'])]);
    fputcsv($output, ['Promedio por Usuario', number_format($stats['total_actividades'] / max($stats['usuarios_activos'], 1), 2)]);
    fputcsv($output, []); // Línea en blanco

    // Actividad por usuario
    fputcsv($output, ['ACTIVIDAD POR USUARIO']);
    
    $sql_actividad = "SELECT u.id as usuario_id, u.nombre as usuario_nombre, u.correo as usuario_email, u.rol,
                      (SELECT COUNT(*) FROM movimientos m WHERE m.usuario_id = u.id AND m.fecha BETWEEN ? AND ?) +
                      (SELECT COUNT(*) FROM solicitudes_transferencia s WHERE s.usuario_id = u.id AND s.fecha_solicitud BETWEEN ? AND ?) as total_actividades,
                      (SELECT COUNT(*) FROM movimientos m WHERE m.usuario_id = u.id AND m.estado = 'completado' AND m.fecha BETWEEN ? AND ?) +
                      (SELECT COUNT(*) FROM solicitudes_transferencia s WHERE s.usuario_id = u.id AND s.estado = 'aprobada' AND s.fecha_solicitud BETWEEN ? AND ?) as completadas,
                      (SELECT COUNT(*) FROM movimientos m WHERE m.usuario_id = u.id AND m.estado = 'pendiente' AND m.fecha BETWEEN ? AND ?) +
                      (SELECT COUNT(*) FROM solicitudes_transferencia s WHERE s.usuario_id = u.id AND s.estado = 'pendiente' AND s.fecha_solicitud BETWEEN ? AND ?) as pendientes,
                      (SELECT COUNT(*) FROM movimientos m WHERE m.usuario_id = u.id AND m.estado = 'rechazado' AND m.fecha BETWEEN ? AND ?) +
                      (SELECT COUNT(*) FROM solicitudes_transferencia s WHERE s.usuario_id = u.id AND s.estado = 'rechazada' AND s.fecha_solicitud BETWEEN ? AND ?) as rechazadas,
                      GREATEST(
                          COALESCE((SELECT MAX(m.fecha) FROM movimientos m WHERE m.usuario_id = u.id), '1970-01-01'),
                          COALESCE((SELECT MAX(s.fecha_solicitud) FROM solicitudes_transferencia s WHERE s.usuario_id = u.id), '1970-01-01')
                      ) as ultima_actividad,
                      a.nombre as almacen_nombre
                      FROM usuarios u
                      LEFT JOIN almacenes a ON u.almacen_id = a.id
                      WHERE u.estado = 'activo'
                      ORDER BY total_actividades DESC";
    
    $stmt = $conn->prepare($sql_actividad);
    $stmt->bind_param("ssssssssssssssss", 
        $param_fecha_inicio, $param_fecha_fin,  // total actividades movimientos
        $param_fecha_inicio, $param_fecha_fin,  // total actividades solicitudes
        $param_fecha_inicio, $param_fecha_fin,  // completadas movimientos
        $param_fecha_inicio, $param_fecha_fin,  // completadas solicitudes
        $param_fecha_inicio, $param_fecha_fin,  // pendientes movimientos
        $param_fecha_inicio, $param_fecha_fin,  // pendientes solicitudes
        $param_fecha_inicio, $param_fecha_fin,  // rechazadas movimientos
        $param_fecha_inicio, $param_fecha_fin   // rechazadas solicitudes
    );
    $stmt->execute();
    $usuarios = $stmt->get_result();

    fputcsv($output, ['Usuario', 'Email', 'Rol', 'Almacén', 'Total Actividades', 'Completadas', 'Pendientes', 'Rechazadas', 'Última Actividad']);

    while ($usuario = $usuarios->fetch_assoc()) {
        $ultima_actividad = ($usuario['ultima_actividad'] && $usuario['ultima_actividad'] != '1970-01-01 00:00:00') 
            ? date('d/m/Y H:i', strtotime($usuario['ultima_actividad'])) 
            : 'Sin actividad';
            
        fputcsv($output, [
            $usuario['usuario_nombre'],
            $usuario['usuario_email'],
            ucfirst($usuario['rol']),
            $usuario['almacen_nombre'] ?? 'N/A',
            number_format($usuario['total_actividades']),
            number_format($usuario['completadas']),
            number_format($usuario['pendientes']),
            number_format($usuario['rechazadas']),
            $ultima_actividad
        ]);
    }
}
?>