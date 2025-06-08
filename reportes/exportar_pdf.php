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

// Parámetro para limitar registros en PDF
$limite_registros = isset($_GET['limite']) ? (int)$_GET['limite'] : 100;
$limite_registros = min($limite_registros, 1000); // Máximo 1000 registros para evitar PDFs muy grandes

// Verificar permisos específicos
if ($tipo_reporte == 'usuarios' && $usuario_rol != 'admin') {
    http_response_code(403);
    exit('No tienes permisos para generar este reporte');
}

// Preparar datos según el tipo de reporte
$datos_reporte = [];
$titulo_reporte = '';

switch($tipo_reporte) {
    case 'inventario':
        $datos_reporte = obtenerDatosInventario($conn, $almacen_id, $usuario_rol, $usuario_almacen_id);
        $titulo_reporte = 'Reporte de Inventario';
        break;
    case 'movimientos':
        $datos_reporte = obtenerDatosMovimientos($conn, $usuario_rol, $usuario_almacen_id, $limite_registros);
        $titulo_reporte = 'Reporte de Movimientos';
        break;
    case 'usuarios':
        $datos_reporte = obtenerDatosUsuarios($conn, $limite_registros);
        $titulo_reporte = 'Reporte de Actividad de Usuarios';
        break;
    default:
        exit('Tipo de reporte no válido');
}

function obtenerDatosInventario($conn, $almacen_id, $usuario_rol, $usuario_almacen_id) {
    // Verificar permisos
    if ($usuario_rol != 'admin' && $almacen_id && $usuario_almacen_id != $almacen_id) {
        return ['error' => 'No tienes permiso para ver este reporte'];
    }

    $datos = [
        'almacen_info' => null, 
        'stats' => [], 
        'categorias' => [], 
        'productos_criticos' => [], 
        'productos_alto_stock' => []
    ];

    // Información del almacén
    if ($almacen_id) {
        $stmt = $conn->prepare("SELECT nombre, ubicacion FROM almacenes WHERE id = ?");
        $stmt->bind_param("i", $almacen_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $datos['almacen_info'] = $result->fetch_assoc();
        $stmt->close();
    }

    // Determinar qué almacén(es) consultar
    $consultar_almacen_id = null;
    if ($almacen_id) {
        $consultar_almacen_id = $almacen_id;
    } else if ($usuario_rol != 'admin' && $usuario_almacen_id) {
        $consultar_almacen_id = $usuario_almacen_id;
    }

    // Estadísticas generales
    if ($consultar_almacen_id) {
        $sql_stats = "SELECT 
            COUNT(DISTINCT p.categoria_id) as total_categorias, 
            COUNT(p.id) as total_productos, 
            COALESCE(SUM(p.cantidad), 0) as total_stock, 
            COALESCE(AVG(p.cantidad), 0) as promedio_stock,
            COALESCE(MIN(p.cantidad), 0) as stock_minimo, 
            COALESCE(MAX(p.cantidad), 0) as stock_maximo
            FROM productos p 
            WHERE p.almacen_id = ?";
        $stmt = $conn->prepare($sql_stats);
        $stmt->bind_param("i", $consultar_almacen_id);
    } else {
        $sql_stats = "SELECT 
            COUNT(DISTINCT p.categoria_id) as total_categorias, 
            COUNT(p.id) as total_productos, 
            COALESCE(SUM(p.cantidad), 0) as total_stock, 
            COALESCE(AVG(p.cantidad), 0) as promedio_stock,
            COALESCE(MIN(p.cantidad), 0) as stock_minimo, 
            COALESCE(MAX(p.cantidad), 0) as stock_maximo
            FROM productos p";
        $stmt = $conn->prepare($sql_stats);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $datos['stats'] = $result->fetch_assoc();
    $stmt->close();

    // Productos por categoría
    if ($consultar_almacen_id) {
        $sql_categorias = "SELECT c.nombre, 
            COUNT(p.id) as total_productos,
            COALESCE(SUM(p.cantidad), 0) as total_stock
            FROM categorias c
            LEFT JOIN productos p ON c.id = p.categoria_id AND p.almacen_id = ?
            GROUP BY c.id, c.nombre 
            ORDER BY total_stock DESC";
        $stmt = $conn->prepare($sql_categorias);
        $stmt->bind_param("i", $consultar_almacen_id);
    } else {
        $sql_categorias = "SELECT c.nombre, 
            COUNT(p.id) as total_productos,
            COALESCE(SUM(p.cantidad), 0) as total_stock
            FROM categorias c
            LEFT JOIN productos p ON c.id = p.categoria_id
            GROUP BY c.id, c.nombre 
            ORDER BY total_stock DESC";
        $stmt = $conn->prepare($sql_categorias);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $datos['categorias'] = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Productos con stock crítico
    if ($consultar_almacen_id) {
        $sql_critico = "SELECT p.nombre, p.cantidad, c.nombre as categoria 
                        FROM productos p 
                        JOIN categorias c ON p.categoria_id = c.id 
                        WHERE p.almacen_id = ? AND p.cantidad < 10 
                        ORDER BY p.cantidad ASC";
        $stmt = $conn->prepare($sql_critico);
        $stmt->bind_param("i", $consultar_almacen_id);
    } else {
        $sql_critico = "SELECT p.nombre, p.cantidad, c.nombre as categoria, a.nombre as almacen 
                        FROM productos p 
                        JOIN categorias c ON p.categoria_id = c.id 
                        JOIN almacenes a ON p.almacen_id = a.id 
                        WHERE p.cantidad < 10 
                        ORDER BY p.cantidad ASC";
        $stmt = $conn->prepare($sql_critico);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $datos['productos_criticos'] = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Top 10 productos con mayor stock
    if ($consultar_almacen_id) {
        $sql_alto = "SELECT p.nombre, p.cantidad, c.nombre as categoria
                     FROM productos p 
                     JOIN categorias c ON p.categoria_id = c.id
                     WHERE p.almacen_id = ? 
                     ORDER BY p.cantidad DESC LIMIT 10";
        $stmt = $conn->prepare($sql_alto);
        $stmt->bind_param("i", $consultar_almacen_id);
    } else {
        $sql_alto = "SELECT p.nombre, p.cantidad, c.nombre as categoria, a.nombre as almacen
                     FROM productos p 
                     JOIN categorias c ON p.categoria_id = c.id
                     JOIN almacenes a ON p.almacen_id = a.id
                     ORDER BY p.cantidad DESC LIMIT 10";
        $stmt = $conn->prepare($sql_alto);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $datos['productos_alto_stock'] = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $datos;
}

function obtenerDatosMovimientos($conn, $usuario_rol, $usuario_almacen_id, $limite_registros = 100) {
    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
    $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
    $filtro_almacen = $_GET['almacen'] ?? '';
    $filtro_tipo = $_GET['tipo'] ?? '';
    
    $datos = [
        'fecha_inicio' => $fecha_inicio, 
        'fecha_fin' => $fecha_fin, 
        'stats' => [], 
        'movimientos' => [],
        'limite_aplicado' => $limite_registros,
        'filtros_aplicados' => []
    ];

    // Registrar filtros aplicados
    if ($filtro_almacen) $datos['filtros_aplicados'][] = "Almacén ID: $filtro_almacen";
    if ($filtro_tipo) $datos['filtros_aplicados'][] = "Tipo: $filtro_tipo";

    // Estadísticas
    $param_fecha_inicio = $fecha_inicio . ' 00:00:00';
    $param_fecha_fin = $fecha_fin . ' 23:59:59';
    
    $sql_stats = "SELECT COUNT(*) as total_movimientos,
                  COALESCE(SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END), 0) as completados,
                  COALESCE(SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END), 0) as pendientes,
                  COALESCE(SUM(CASE WHEN estado = 'rechazado' THEN 1 ELSE 0 END), 0) as rechazados
                  FROM movimientos WHERE fecha BETWEEN ? AND ?";
    
    $where_conditions = "";
    $params_stats = [$param_fecha_inicio, $param_fecha_fin];
    $types_stats = "ss";

    // Aplicar filtros a estadísticas
    if ($usuario_rol != 'admin' && $usuario_almacen_id) {
        $where_conditions .= " AND (almacen_origen = ? OR almacen_destino = ?)";
        $params_stats[] = $usuario_almacen_id;
        $params_stats[] = $usuario_almacen_id;
        $types_stats .= "ii";
    }

    if (!empty($filtro_almacen) && $usuario_rol == 'admin') {
        $where_conditions .= " AND (almacen_origen = ? OR almacen_destino = ?)";
        $params_stats[] = $filtro_almacen;
        $params_stats[] = $filtro_almacen;
        $types_stats .= "ii";
    }

    if (!empty($filtro_tipo) && in_array($filtro_tipo, ['entrada', 'salida', 'transferencia', 'ajuste'])) {
        $where_conditions .= " AND tipo = ?";
        $params_stats[] = $filtro_tipo;
        $types_stats .= "s";
    }

    $sql_stats .= $where_conditions;
    $stmt = $conn->prepare($sql_stats);
    $stmt->bind_param($types_stats, ...$params_stats);
    $stmt->execute();
    $datos['stats'] = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Detalle de movimientos con los mismos filtros
    $sql_movimientos = "SELECT m.id, m.fecha, m.cantidad, m.estado, m.tipo as tipo_movimiento,
                        p.nombre as producto_nombre, CONCAT('PROD-', LPAD(p.id, 4, '0')) as producto_codigo,
                        ao.nombre as almacen_origen, ad.nombre as almacen_destino, u.nombre as usuario_nombre
                        FROM movimientos m
                        LEFT JOIN productos p ON m.producto_id = p.id
                        LEFT JOIN almacenes ao ON m.almacen_origen = ao.id
                        LEFT JOIN almacenes ad ON m.almacen_destino = ad.id
                        LEFT JOIN usuarios u ON m.usuario_id = u.id
                        WHERE m.fecha BETWEEN ? AND ?";

    $sql_movimientos .= $where_conditions;
    $sql_movimientos .= " ORDER BY m.fecha DESC LIMIT ?";
    
    $params_mov = $params_stats;
    $params_mov[] = $limite_registros;
    $types_mov = $types_stats . "i";
    
    $stmt = $conn->prepare($sql_movimientos);
    $stmt->bind_param($types_mov, ...$params_mov);
    $stmt->execute();
    $datos['movimientos'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $datos;
}

function obtenerDatosUsuarios($conn, $limite_registros = 50) {
    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
    $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
    $filtro_usuario = $_GET['usuario'] ?? '';
    
    $datos = [
        'fecha_inicio' => $fecha_inicio, 
        'fecha_fin' => $fecha_fin, 
        'stats' => [], 
        'usuarios' => [],
        'limite_aplicado' => $limite_registros,
        'filtros_aplicados' => []
    ];

    if ($filtro_usuario) $datos['filtros_aplicados'][] = "Usuario ID: $filtro_usuario";

    $param_fecha_inicio = $fecha_inicio . ' 00:00:00';
    $param_fecha_fin = $fecha_fin . ' 23:59:59';

    // Estadísticas generales
    $sql_stats = "SELECT COUNT(DISTINCT u.id) as usuarios_activos,
                  (SELECT COUNT(*) FROM movimientos m WHERE m.fecha BETWEEN ? AND ?) + 
                  (SELECT COUNT(*) FROM solicitudes_transferencia s WHERE s.fecha_solicitud BETWEEN ? AND ?) as total_actividades
                  FROM usuarios u WHERE u.estado = 'activo'";
    
    if (!empty($filtro_usuario)) {
        $sql_stats .= " AND u.id = ?";
        $stmt = $conn->prepare($sql_stats);
        $stmt->bind_param("ssssi", $param_fecha_inicio, $param_fecha_fin, $param_fecha_inicio, $param_fecha_fin, $filtro_usuario);
    } else {
        $stmt = $conn->prepare($sql_stats);
        $stmt->bind_param("ssss", $param_fecha_inicio, $param_fecha_fin, $param_fecha_inicio, $param_fecha_fin);
    }
    
    $stmt->execute();
    $datos['stats'] = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Actividad por usuario
    $sql_actividad = "SELECT u.id as usuario_id, u.nombre as usuario_nombre, u.correo as usuario_email, u.rol,
                      (SELECT COUNT(*) FROM movimientos m WHERE m.usuario_id = u.id AND m.fecha BETWEEN ? AND ?) +
                      (SELECT COUNT(*) FROM solicitudes_transferencia s WHERE s.usuario_id = u.id AND s.fecha_solicitud BETWEEN ? AND ?) as total_actividades,
                      a.nombre as almacen_nombre
                      FROM usuarios u
                      LEFT JOIN almacenes a ON u.almacen_id = a.id
                      WHERE u.estado = 'activo'";
    
    if (!empty($filtro_usuario)) {
        $sql_actividad .= " AND u.id = ?";
        $sql_actividad .= " ORDER BY total_actividades DESC LIMIT ?";
        $stmt = $conn->prepare($sql_actividad);
        $stmt->bind_param("ssssii", $param_fecha_inicio, $param_fecha_fin, $param_fecha_inicio, $param_fecha_fin, $filtro_usuario, $limite_registros);
    } else {
        $sql_actividad .= " ORDER BY total_actividades DESC LIMIT ?";
        $stmt = $conn->prepare($sql_actividad);
        $stmt->bind_param("ssssi", $param_fecha_inicio, $param_fecha_fin, $param_fecha_inicio, $param_fecha_fin, $limite_registros);
    }
    
    $stmt->execute();
    $datos['usuarios'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $datos;
}

// Verificar errores antes de mostrar el reporte
if (isset($datos_reporte['error'])) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Error en Reporte - GRUPO SEAL</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                padding: 50px; 
                text-align: center; 
                background: #f8f9fa;
            }
            .error { 
                color: #dc3545; 
                background: #f8d7da; 
                padding: 30px; 
                border-radius: 10px;
                border: 1px solid #f5c6cb;
                max-width: 600px;
                margin: 0 auto;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            .error h2 { margin-top: 0; }
            .btn {
                display: inline-block;
                background: #007bff;
                color: white;
                padding: 10px 20px;
                text-decoration: none;
                border-radius: 5px;
                margin: 10px;
            }
            .btn:hover { background: #0056b3; }
        </style>
    </head>
    <body>
        <div class="error">
            <h2>❌ Error al generar el reporte</h2>
            <p><?php echo htmlspecialchars($datos_reporte['error']); ?></p>
            <div>
                <a href="javascript:history.back()" class="btn">« Volver</a>
                <a href="../dashboard.php" class="btn">🏠 Dashboard</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Verificar si hay datos para mostrar
$hay_datos = false;
if ($tipo_reporte == 'inventario') {
    $hay_datos = !empty($datos_reporte['stats']) && $datos_reporte['stats']['total_productos'] > 0;
} else {
    $hay_datos = !empty($datos_reporte) && !empty($datos_reporte['stats']);
}

if (!$hay_datos) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Sin datos - GRUPO SEAL</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                padding: 50px; 
                text-align: center;
                background: #f8f9fa;
            }
            .warning { 
                color: #856404; 
                background: #fff3cd; 
                padding: 30px; 
                border-radius: 10px;
                border: 1px solid #ffeaa7;
                max-width: 600px;
                margin: 0 auto;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            .warning h2 { margin-top: 0; }
            .btn {
                display: inline-block;
                background: #007bff;
                color: white;
                padding: 10px 20px;
                text-decoration: none;
                border-radius: 5px;
                margin: 10px;
            }
            .btn:hover { background: #0056b3; }
            ul { text-align: left; display: inline-block; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class="warning">
            <h2>⚠️ Sin datos para mostrar</h2>
            <p>No se encontraron datos para generar el reporte <strong><?php echo htmlspecialchars($titulo_reporte); ?></strong>.</p>
            <p>Esto puede deberse a:</p>
            <ul>
                <li>🔒 Permisos insuficientes</li>
                <li>📦 No hay productos registrados</li>
                <li>🏪 No tienes almacén asignado</li>
                <li>📅 Rango de fechas sin actividad</li>
                <li>🔍 Filtros muy restrictivos</li>
            </ul>
            <div>
                <a href="javascript:history.back()" class="btn">« Volver</a>
                <a href="../dashboard.php" class="btn">🏠 Dashboard</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo_reporte; ?> - GRUPO SEAL</title>
    <style>
        @page {
            size: A4;
            margin: 1cm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #0a253c;
            padding-bottom: 20px;
        }
        
        .header h1 {
            color: #0a253c;
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .header h2 {
            color: #666;
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .header-info {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            font-size: 10px;
        }
        
        .filters-info {
            background: #f8f9fa;
            padding: 10px;
            margin: 15px 0;
            border-left: 4px solid #007bff;
            font-size: 10px;
        }
        
        .filters-info h4 {
            margin-bottom: 5px;
            font-size: 11px;
            color: #0a253c;
        }
        
        .section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        
        .section-title {
            background: #f8f9fa;
            padding: 8px 15px;
            border-left: 4px solid #007bff;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        th, td {
            border: 1px solid #dee2e6;
            padding: 8px;
            text-align: left;
            font-size: 11px;
        }
        
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            border: 1px solid #dee2e6;
            padding: 15px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #007bff;
        }
        
        .stat-label {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
        }
        
        .no-print {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .btn-print {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-print:hover {
            background: #0056b3;
        }
        
        @media print {
            .no-print { display: none !important; }
            body { 
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact; 
            }
        }
        
        .status-completado { color: #28a745; }
        .status-pendiente { color: #ffc107; }
        .status-rechazado { color: #dc3545; }
        
        .stock-critical { color: #dc3545; font-weight: bold; }
        .stock-warning { color: #ffc107; font-weight: bold; }
        .stock-good { color: #28a745; font-weight: bold; }

        .limite-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 8px 12px;
            margin: 10px 0;
            border-radius: 4px;
            font-size: 10px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">🖨️ Imprimir PDF</button>
    </div>

    <div class="header">
        <h1>GRUPO SEAL - Sistema de Inventario</h1>
        <h2><?php echo $titulo_reporte; ?></h2>
        
        <?php if ($tipo_reporte == 'inventario' && isset($datos_reporte['almacen_info'])): ?>
            <p><strong>Almacén:</strong> <?php echo htmlspecialchars($datos_reporte['almacen_info']['nombre']); ?></p>
            <p><strong>Ubicación:</strong> <?php echo htmlspecialchars($datos_reporte['almacen_info']['ubicacion']); ?></p>
        <?php endif; ?>
        
        <div class="header-info">
            <span><strong>Generado por:</strong> <?php echo htmlspecialchars($user_name); ?></span>
            <span><strong>Fecha:</strong> <?php echo date('d/m/Y H:i:s'); ?></span>
            <?php if (isset($datos_reporte['fecha_inicio'])): ?>
            <span><strong>Período:</strong> <?php echo $datos_reporte['fecha_inicio']; ?> al <?php echo $datos_reporte['fecha_fin']; ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Información de filtros aplicados -->
    <?php if (isset($datos_reporte['filtros_aplicados']) && !empty($datos_reporte['filtros_aplicados'])): ?>
    <div class="filters-info">
        <h4>🔍 Filtros Aplicados:</h4>
        <p><?php echo implode(' • ', $datos_reporte['filtros_aplicados']); ?></p>
    </div>
    <?php endif; ?>

    <!-- Información de límite de registros -->
    <?php if (isset($datos_reporte['limite_aplicado'])): ?>
    <div class="limite-info">
        <strong>📄 Nota:</strong> Este reporte muestra un máximo de <?php echo number_format($datos_reporte['limite_aplicado']); ?> registros. 
        Para ver todos los registros, utilice la vista web con paginación.
    </div>
    <?php endif; ?>

    <?php if ($tipo_reporte == 'inventario'): ?>
        <!-- Reporte de Inventario -->
        <div class="section">
            <div class="section-title">📊 Estadísticas Generales</div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($datos_reporte['stats']['total_productos']); ?></div>
                    <div class="stat-label">Total Productos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($datos_reporte['stats']['total_categorias']); ?></div>
                    <div class="stat-label">Categorías</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($datos_reporte['stats']['total_stock']); ?></div>
                    <div class="stat-label">Stock Total</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($datos_reporte['stats']['promedio_stock'], 1); ?></div>
                    <div class="stat-label">Promedio por Producto</div>
                </div>
            </div>
        </div>

        <!-- Distribución por Categorías -->
        <div class="section">
            <div class="section-title">📈 Distribución por Categorías</div>
            <table>
                <thead>
                    <tr>
                        <th>Categoría</th>
                        <th>Productos</th>
                        <th>Stock Total</th>
                        <th>% del Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_general = $datos_reporte['stats']['total_stock'];
                    foreach ($datos_reporte['categorias'] as $cat): 
                        $porcentaje = $total_general > 0 ? ($cat['total_stock'] / $total_general) * 100 : 0;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cat['nombre']); ?></td>
                        <td><?php echo number_format($cat['total_productos']); ?></td>
                        <td><?php echo number_format($cat['total_stock']); ?></td>
                        <td><?php echo number_format($porcentaje, 1); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Productos con Stock Crítico -->
        <?php if (!empty($datos_reporte['productos_criticos'])): ?>
        <div class="section">
            <div class="section-title">⚠️ Productos con Stock Crítico (< 10 unidades)</div>
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <?php if (!$almacen_id): ?><th>Almacén</th><?php endif; ?>
                        <th>Stock Actual</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($datos_reporte['productos_criticos'] as $prod): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($prod['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($prod['categoria']); ?></td>
                        <?php if (!$almacen_id): ?><td><?php echo htmlspecialchars($prod['almacen']); ?></td><?php endif; ?>
                        <td class="<?php echo $prod['cantidad'] < 5 ? 'stock-critical' : 'stock-warning'; ?>">
                            <?php echo $prod['cantidad']; ?> unidades
                        </td>
                        <td><?php echo $prod['cantidad'] < 5 ? 'CRÍTICO' : 'BAJO'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    <?php elseif ($tipo_reporte == 'movimientos'): ?>
        <!-- Reporte de Movimientos -->
        <div class="section">
            <div class="section-title">📊 Estadísticas del Período</div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($datos_reporte['stats']['total_movimientos']); ?></div>
                    <div class="stat-label">Total Movimientos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($datos_reporte['stats']['completados']); ?></div>
                    <div class="stat-label">Completados</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($datos_reporte['stats']['pendientes']); ?></div>
                    <div class="stat-label">Pendientes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($datos_reporte['stats']['rechazados']); ?></div>
                    <div class="stat-label">Rechazados</div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">📋 Detalle de Movimientos (Últimos <?php echo count($datos_reporte['movimientos']); ?> registros)</div>
            <?php if (!empty($datos_reporte['movimientos'])): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Origen</th>
                        <th>Destino</th>
                        <th>Usuario</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($datos_reporte['movimientos'] as $mov): ?>
                    <tr>
                        <td>#<?php echo str_pad($mov['id'], 4, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($mov['fecha'])); ?></td>
                        <td><?php echo htmlspecialchars($mov['producto_nombre']); ?></td>
                        <td><?php echo number_format($mov['cantidad']); ?></td>
                        <td><?php echo htmlspecialchars($mov['almacen_origen'] ?? 'Sistema'); ?></td>
                        <td><?php echo htmlspecialchars($mov['almacen_destino'] ?? 'Sistema'); ?></td>
                        <td><?php echo htmlspecialchars($mov['usuario_nombre']); ?></td>
                        <td><?php echo ucfirst($mov['tipo_movimiento']); ?></td>
                        <td class="status-<?php echo $mov['estado']; ?>"><?php echo ucfirst($mov['estado']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="text-align: center; padding: 20px; color: #666;">No se encontraron movimientos en el período seleccionado.</p>
            <?php endif; ?>
        </div>

    <?php elseif ($tipo_reporte == 'usuarios'): ?>
        <!-- Reporte de Usuarios -->
        <div class="section">
            <div class="section-title">📊 Estadísticas Generales</div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($datos_reporte['stats']['usuarios_activos']); ?></div>
                    <div class="stat-label">Usuarios Activos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($datos_reporte['stats']['total_actividades']); ?></div>
                    <div class="stat-label">Total Actividades</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($datos_reporte['stats']['total_actividades'] / max($datos_reporte['stats']['usuarios_activos'], 1), 2); ?></div>
                    <div class="stat-label">Promedio por Usuario</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($datos_reporte['usuarios']); ?></div>
                    <div class="stat-label">En este reporte</div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">👥 Actividad por Usuario (Top <?php echo count($datos_reporte['usuarios']); ?>)</div>
            <table>
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Almacén</th>
                        <th>Total Actividades</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($datos_reporte['usuarios'] as $usuario): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($usuario['usuario_nombre']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['usuario_email']); ?></td>
                        <td><?php echo ucfirst($usuario['rol']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['almacen_nombre'] ?? 'N/A'); ?></td>
                        <td><?php echo number_format($usuario['total_actividades']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <script>
        // Auto-abrir ventana de impresión si viene de un enlace directo
        if (window.location.search.includes('auto_print=1')) {
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            };
        }
    </script>
</body>
</html>