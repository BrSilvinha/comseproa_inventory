<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

// Prevent session hijacking
session_regenerate_id(true);

$user_name = $_SESSION["user_name"] ?? "Usuario";
$usuario_almacen_id = $_SESSION["almacen_id"] ?? null;
$usuario_rol = $_SESSION["user_role"] ?? "usuario";

require_once "../config/database.php";

// Obtener parámetros de filtro
$filtro_almacen_id = isset($_GET['almacen_id']) ? (int)$_GET['almacen_id'] : null;
$filtro_categoria_id = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : null;

// Verificar permisos
if ($filtro_almacen_id && $usuario_rol != 'admin' && $usuario_almacen_id != $filtro_almacen_id) {
    $_SESSION['error'] = "No tienes permiso para ver entregas de este almacén";
    header("Location: ../dashboard.php");
    exit();
}

// Obtener almacenes
if ($usuario_rol == 'admin') {
    $sql_almacenes = "SELECT id, nombre, ubicacion FROM almacenes ORDER BY id DESC";
    $result_almacenes = $conn->query($sql_almacenes);
} else {
    if ($usuario_almacen_id) {
        $sql_almacenes = "SELECT id, nombre, ubicacion FROM almacenes WHERE id = ?";
        $stmt_almacenes = $conn->prepare($sql_almacenes);
        $stmt_almacenes->bind_param("i", $usuario_almacen_id);
        $stmt_almacenes->execute();
        $result_almacenes = $stmt_almacenes->get_result();
    } else {
        $result_almacenes = false;
    }
}

// Determinar qué almacén mostrar
$almacen_id_mostrar = null;
if ($usuario_rol == 'admin') {
    $almacen_id_mostrar = $filtro_almacen_id;
} else {
    $almacen_id_mostrar = $usuario_almacen_id;
}

// Obtener información del almacén seleccionado
$almacen_info = null;
if ($almacen_id_mostrar) {
    $sql_almacen = "SELECT * FROM almacenes WHERE id = ?";
    $stmt = $conn->prepare($sql_almacen);
    $stmt->bind_param("i", $almacen_id_mostrar);
    $stmt->execute();
    $almacen_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Obtener categorías que tienen entregas en este almacén
$categorias_con_entregas = [];
if ($almacen_id_mostrar) {
    $sql_categorias = "SELECT DISTINCT c.id, c.nombre, COUNT(eu.id) as total_entregas,
                       SUM(eu.cantidad) as total_productos_entregados
                       FROM categorias c
                       INNER JOIN productos p ON c.id = p.categoria_id
                       INNER JOIN entrega_uniformes eu ON p.id = eu.producto_id
                       WHERE eu.almacen_id = ?
                       GROUP BY c.id, c.nombre
                       ORDER BY c.nombre";
    $stmt_categorias = $conn->prepare($sql_categorias);
    $stmt_categorias->bind_param("i", $almacen_id_mostrar);
    $stmt_categorias->execute();
    $categorias_con_entregas = $stmt_categorias->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_categorias->close();
}

// Obtener información de la categoría seleccionada
$categoria_info = null;
if ($filtro_categoria_id) {
    $sql_categoria = "SELECT * FROM categorias WHERE id = ?";
    $stmt = $conn->prepare($sql_categoria);
    $stmt->bind_param("i", $filtro_categoria_id);
    $stmt->execute();
    $categoria_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Función mejorada para obtener entregas con filtro de categoría
function obtenerEntregasPorAlmacenYCategoria($conn, $almacen_id, $categoria_id = null, $filtros = [], $limite = null, $offset = null) {
    $query = '
        SELECT 
            eu.id,
            eu.nombre_destinatario,
            eu.dni_destinatario,
            eu.fecha_entrega,
            p.nombre as producto_nombre,
            eu.cantidad,
            a.nombre as almacen_nombre,
            u.nombre as usuario_responsable,
            c.nombre as categoria_nombre
        FROM 
            entrega_uniformes eu
        JOIN 
            productos p ON eu.producto_id = p.id
        JOIN 
            almacenes a ON eu.almacen_id = a.id
        JOIN
            categorias c ON p.categoria_id = c.id
        LEFT JOIN
            usuarios u ON eu.usuario_responsable_id = u.id
        WHERE 
            eu.almacen_id = ?
    ';

    $params = [$almacen_id];
    $types = 'i';

    // Filtro por categoría
    if ($categoria_id) {
        $query .= ' AND p.categoria_id = ?';
        $params[] = $categoria_id;
        $types .= 'i';
    }

    // Otros filtros
    if (!empty($filtros['dni'])) {
        $query .= ' AND eu.dni_destinatario LIKE ?';
        $params[] = '%' . $filtros['dni'] . '%';
        $types .= 's';
    }

    if (!empty($filtros['nombre'])) {
        $query .= ' AND eu.nombre_destinatario LIKE ?';
        $params[] = '%' . $filtros['nombre'] . '%';
        $types .= 's';
    }

    if (!empty($filtros['fecha_inicio'])) {
        $query .= ' AND DATE(eu.fecha_entrega) >= ?';
        $params[] = $filtros['fecha_inicio'];
        $types .= 's';
    }
    if (!empty($filtros['fecha_fin'])) {
        $query .= ' AND DATE(eu.fecha_entrega) <= ?';
        $params[] = $filtros['fecha_fin'];
        $types .= 's';
    }

    $query .= ' ORDER BY eu.fecha_entrega DESC';

    if ($limite !== null && $offset !== null) {
        $query .= ' LIMIT ? OFFSET ?';
        $params[] = $limite;
        $params[] = $offset;
        $types .= 'ii';
    }
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param($types, ...$params);
    
    if (!$stmt->execute()) {
        return [];
    }
    
    $result = $stmt->get_result();
    $entregasAgrupadas = [];
    
    while ($row = $result->fetch_assoc()) {
        $key = $row['fecha_entrega'] . '|' . $row['nombre_destinatario'] . '|' . $row['dni_destinatario'];
        
        if (!isset($entregasAgrupadas[$key])) {
            $entregasAgrupadas[$key] = [
                'id' => $row['id'],
                'fecha_entrega' => $row['fecha_entrega'],
                'nombre_destinatario' => $row['nombre_destinatario'],
                'dni_destinatario' => $row['dni_destinatario'],
                'almacen_nombre' => $row['almacen_nombre'],
                'usuario_responsable' => $row['usuario_responsable'],
                'categoria_nombre' => $row['categoria_nombre'],
                'productos' => []
            ];
        }
        
        $entregasAgrupadas[$key]['productos'][] = [
            'nombre' => $row['producto_nombre'],
            'cantidad' => $row['cantidad']
        ];
    }

    return array_values($entregasAgrupadas);
}

// Preparar filtros
$filtros = [
    'dni' => $_GET['dni'] ?? '',
    'nombre' => $_GET['nombre'] ?? '',
    'fecha_inicio' => $_GET['fecha_inicio'] ?? '',
    'fecha_fin' => $_GET['fecha_fin'] ?? ''
];

// Configuración de paginación
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Obtener entregas si hay almacén y categoría seleccionados
$entregas = [];
$total_entregas = 0;
if ($almacen_id_mostrar && $filtro_categoria_id) {
    $entregas = obtenerEntregasPorAlmacenYCategoria($conn, $almacen_id_mostrar, $filtro_categoria_id, $filtros, $registros_por_pagina, $offset);
    $total_entregas_temp = obtenerEntregasPorAlmacenYCategoria($conn, $almacen_id_mostrar, $filtro_categoria_id, $filtros);
    $total_entregas = count($total_entregas_temp);
}

$total_paginas = ceil($total_entregas / $registros_por_pagina);

// Contar solicitudes pendientes para el badge
$sql_pendientes = "SELECT COUNT(*) as total FROM solicitudes_transferencia WHERE estado = 'pendiente'";
if ($usuario_rol != 'admin') {
    $sql_pendientes .= " AND almacen_destino = ?";
    $stmt_pendientes = $conn->prepare($sql_pendientes);
    $stmt_pendientes->bind_param("i", $usuario_almacen_id);
    $stmt_pendientes->execute();
    $result_pendientes = $stmt_pendientes->get_result();
} else {
    $result_pendientes = $conn->query($sql_pendientes);
}

$total_pendientes = 0;
if ($result_pendientes && $row_pendientes = $result_pendientes->fetch_assoc()) {
    $total_pendientes = $row_pendientes['total'];
}

// Función para generar URL de descarga con filtros actuales
function generarUrlDescarga($formato, $almacen_id, $categoria_id = null, $filtros = []) {
    $params = [
        'formato' => $formato,
        'almacen_id' => $almacen_id
    ];
    
    if ($categoria_id) {
        $params['categoria_id'] = $categoria_id;
    }
    
    foreach ($filtros as $key => $value) {
        if (!empty($value)) {
            $params[$key] = $value;
        }
    }
    
    return 'generar_reporte.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Entregas por Categoría - GRUPO SEAL</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Historial de entregas organizadas por categoría - Sistema GRUPO SEAL">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- CSS específico -->
    <link rel="stylesheet" href="../assets/css/listar-usuarios.css">
    <link rel="stylesheet" href="../assets/css/historial-entregas.css">
    
    <style>
        /* Estilos para los botones de descarga */
        .download-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .download-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            color: #0a253c;
        }

        .download-header i {
            background: linear-gradient(135deg, #0a253c, #1e4a72);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 20px;
        }

        .download-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
        }

        .download-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .download-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 14px;
            position: relative;
            overflow: hidden;
        }

        .download-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .download-btn:hover::before {
            left: 100%;
        }

        .download-btn-pdf {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border: 2px solid #dc3545;
        }

        .download-btn-pdf:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
            color: white;
        }

        .download-btn-excel {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: 2px solid #28a745;
        }

        .download-btn-excel:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
            color: white;
        }

        .download-btn-csv {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            border: 2px solid #17a2b8;
        }

        .download-btn-csv:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(23, 162, 184, 0.3);
            color: white;
        }

        .download-info {
            background: rgba(10, 37, 60, 0.05);
            border-left: 4px solid #0a253c;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .download-info p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }

        .download-info strong {
            color: #0a253c;
        }

        /* Modal de confirmación */
        .download-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        .download-modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            text-align: center;
            animation: slideIn 0.3s ease;
        }

        .download-modal h4 {
            color: #0a253c;
            margin-bottom: 20px;
            font-size: 22px;
        }

        .download-modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 25px;
        }

        .modal-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 120px;
        }

        .modal-btn-confirm {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .modal-btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        .modal-btn-cancel {
            background: #6c757d;
            color: white;
        }

        .modal-btn-cancel:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Indicador de descarga */
        .download-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #0a253c, #1e4a72);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            z-index: 9999;
            display: none;
            animation: slideInRight 0.3s ease;
        }

        @keyframes slideInRight {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }

        /* Estilos específicos para categorías (mantenidos del código original) */
        .categorias-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .categoria-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 2px solid transparent;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .categoria-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #0a253c, #1e4a72);
        }

        .categoria-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border-color: #0a253c;
        }

        .categoria-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .categoria-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #0a253c, #1e4a72);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            margin-right: 15px;
        }

        .categoria-info h3 {
            color: #0a253c;
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 5px 0;
        }

        .categoria-info p {
            color: #666;
            font-size: 14px;
            margin: 0;
        }

        .categoria-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 15px;
            background: rgba(10, 37, 60, 0.05);
            border-radius: 10px;
        }

        .stat-value {
            display: block;
            font-size: 24px;
            font-weight: 700;
            color: #0a253c;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .categoria-actions {
            display: flex;
            gap: 10px;
        }

        .btn-categoria {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-ver-entregas {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .btn-ver-entregas:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .categoria-breadcrumb {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #0a253c;
        }

        .categoria-breadcrumb h4 {
            margin: 0 0 5px 0;
            color: #0a253c;
            font-size: 18px;
        }

        .categoria-breadcrumb p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }

        .filtros-categoria {
            background: linear-gradient(135deg, #0a253c, #1e4a72);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
        }

        .filtros-categoria h3 {
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        .back-button:hover {
            background: #5a6268;
            transform: translateX(-3px);
        }

        /* Iconos específicos por categoría */
        .categoria-uniformes .categoria-icon { background: linear-gradient(135deg, #007bff, #0056b3); }
        .categoria-armas .categoria-icon { background: linear-gradient(135deg, #dc3545, #c82333); }
        .categoria-equipos .categoria-icon { background: linear-gradient(135deg, #ffc107, #e0a800); }
        .categoria-vehiculos .categoria-icon { background: linear-gradient(135deg, #28a745, #1e7e34); }
        .categoria-comunicaciones .categoria-icon { background: linear-gradient(135deg, #17a2b8, #138496); }

        @media (max-width: 768px) {
            .categorias-grid {
                grid-template-columns: 1fr;
            }
            
            .categoria-stats {
                grid-template-columns: 1fr;
            }

            .download-buttons {
                grid-template-columns: 1fr;
            }

            .download-modal-content {
                margin: 20% auto;
                width: 95%;
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<!-- Mobile hamburger menu button -->
<button class="menu-toggle" id="menuToggle" aria-label="Abrir menú de navegación">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar Navigation -->
<nav class="sidebar" id="sidebar" role="navigation" aria-label="Menú principal">
    <h2>GRUPO SEAL</h2>
    <ul>
        <li>
            <a href="../dashboard.php" aria-label="Ir a inicio">
                <span><i class="fas fa-home"></i> Inicio</span>
            </a>
        </li>

        <!-- Users Section - Only visible to administrators -->
        <?php if ($usuario_rol == 'admin'): ?>
        <li class="submenu-container">
            <a href="#" aria-label="Menú Usuarios" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-users"></i> Usuarios</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li><a href="../usuarios/registrar.php" role="menuitem"><i class="fas fa-user-plus"></i> Registrar Usuario</a></li>
                <li><a href="../usuarios/listar.php" role="menuitem"><i class="fas fa-list"></i> Lista de Usuarios</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- Warehouses Section - Adjusted according to permissions -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Almacenes" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-warehouse"></i> Almacenes</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <?php if ($usuario_rol == 'admin'): ?>
                <li><a href="../almacenes/registrar.php" role="menuitem"><i class="fas fa-plus"></i> Registrar Almacén</a></li>
                <?php endif; ?>
                <li><a href="../almacenes/listar.php" role="menuitem"><i class="fas fa-list"></i> Lista de Almacenes</a></li>
            </ul>
        </li>
        
        <!-- Historial Section - Reemplaza la sección de Entregas -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Historial" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-history"></i> Historial</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li><a href="historial.php" role="menuitem"><i class="fas fa-hand-holding"></i> Historial de Entregas</a></li>
                <li><a href="../notificaciones/historial.php" role="menuitem"><i class="fas fa-exchange-alt"></i> Historial de Solicitudes</a></li>
                <li><a href="../uniformes/historial_entregas_uniformes.php" role="menuitem"><i class="fas fa-tshirt"></i> Historial de Uniformes</a></li>
            </ul>
        </li>
        
        <!-- Notifications Section - Con badge rojo de notificaciones -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Notificaciones" aria-expanded="false" role="button" tabindex="0">
                <span>
                    <i class="fas fa-bell"></i> Notificaciones
                </span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li>
                    <a href="../notificaciones/pendientes.php" role="menuitem">
                        <i class="fas fa-clock"></i> Solicitudes Pendientes
                        <?php if ($total_pendientes > 0): ?>
                        <span class="badge-small"><?php echo $total_pendientes; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </li>

        <!-- Reports Section (Admin only) -->
        <?php if ($usuario_rol == 'admin'): ?>
        <li class="submenu-container">
            <a href="#" aria-label="Menú Reportes" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-chart-bar"></i> Reportes</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li><a href="../reportes/inventario.php" role="menuitem"><i class="fas fa-warehouse"></i> Inventario General</a></li>
                <li><a href="../reportes/movimientos.php" role="menuitem"><i class="fas fa-exchange-alt"></i> Movimientos</a></li>
                <li><a href="../reportes/usuarios.php" role="menuitem"><i class="fas fa-users"></i> Actividad de Usuarios</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- User Profile -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Perfil" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-user-circle"></i> Mi Perfil</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li><a href="../perfil/configuracion.php" role="menuitem"><i class="fas fa-cog"></i> Configuración</a></li>
                <li><a href="../perfil/cambiar-password.php" role="menuitem"><i class="fas fa-key"></i> Cambiar Contraseña</a></li>
            </ul>
        </li>

        <!-- Logout -->
        <li>
            <a href="#" onclick="manejarCerrarSesion(event)" aria-label="Cerrar sesión">
                <span><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</span>
            </a>
        </li>
    </ul>
</nav>

<!-- Main Content -->
<main class="content" id="main-content" role="main">
    <div class="historial-header-section">
        <div class="historial-title-container">
            <h2 class="historial-title">
                <i class="fas fa-history"></i> 
                <?php if ($almacen_info && $categoria_info): ?>
                    Historial de Entregas - <?php echo htmlspecialchars($categoria_info['nombre']); ?>
                <?php elseif ($almacen_info): ?>
                    Historial de Entregas - <?php echo htmlspecialchars($almacen_info['nombre']); ?>
                <?php elseif ($usuario_rol == 'admin' && !$filtro_almacen_id): ?>
                    Seleccionar Almacén para Ver Entregas
                <?php else: ?>
                    Historial de Entregas por Categoría
                <?php endif; ?>
            </h2>
        </div>
    </div>

    <!-- Navegación por niveles -->
    <?php if ($almacen_info && $categoria_info): ?>
        <!-- Viendo entregas de una categoría específica -->
        <a href="?almacen_id=<?php echo $almacen_info['id']; ?>" class="back-button">
            <i class="fas fa-arrow-left"></i>
            Volver a Categorías de <?php echo htmlspecialchars($almacen_info['nombre']); ?>
        </a>

        <div class="categoria-breadcrumb">
            <h4>
                <i class="fas fa-tag"></i>
                <?php echo htmlspecialchars($categoria_info['nombre']); ?>
            </h4>
            <p>Entregas realizadas en <?php echo htmlspecialchars($almacen_info['nombre']); ?></p>
        </div>

        <!-- Sección de Descarga de Reportes -->
        <div class="download-section">
            <div class="download-header">
                <i class="fas fa-download"></i>
                <div>
                    <h3>Descargar Reporte</h3>
                    <p style="margin: 0; color: #666; font-size: 14px;">
                        Generar reporte de entregas para la categoría "<?php echo htmlspecialchars($categoria_info['nombre']); ?>"
                    </p>
                </div>
            </div>

            <div class="download-buttons">
                <button class="download-btn download-btn-pdf" onclick="confirmarDescarga('pdf')">
                    <i class="fas fa-file-pdf"></i>
                    Descargar PDF
                </button>
                <button class="download-btn download-btn-excel" onclick="confirmarDescarga('excel')">
                    <i class="fas fa-file-excel"></i>
                    Descargar Excel
                </button>
                <button class="download-btn download-btn-csv" onclick="confirmarDescarga('csv')">
                    <i class="fas fa-file-csv"></i>
                    Descargar CSV
                </button>
            </div>

            <div class="download-info">
                <p><strong>Información:</strong> El reporte incluirá todas las entregas de esta categoría según los filtros aplicados. 
                Los filtros de fecha, nombre y DNI se conservarán en el reporte.</p>
            </div>
        </div>

        <!-- Filtros para la categoría específica -->
        <div class="filtros-categoria">
            <h3>
                <i class="fas fa-filter"></i>
                Filtros de Búsqueda
            </h3>
            <form method="GET" class="historial-filter-form" id="formulario-filtros">
                <input type="hidden" name="almacen_id" value="<?php echo $filtro_almacen_id; ?>">
                <input type="hidden" name="categoria_id" value="<?php echo $filtro_categoria_id; ?>">
                
                <div class="historial-filter-row">
                    <div class="historial-form-group">
                        <label for="filtro-nombre" class="historial-form-label">Filtrar por Nombre</label>
                        <input type="text" class="historial-form-control" id="filtro-nombre" name="nombre" 
                               placeholder="Nombre del destinatario" 
                               value="<?php echo htmlspecialchars($_GET['nombre'] ?? ''); ?>">
                    </div>
                    <div class="historial-form-group">
                        <label for="filtro-dni" class="historial-form-label">Filtrar por DNI</label>
                        <input type="text" class="historial-form-control" id="filtro-dni" name="dni" 
                               placeholder="Número de DNI" 
                               value="<?php echo htmlspecialchars($_GET['dni'] ?? ''); ?>">
                    </div>
                    <div class="historial-form-group">
                        <label for="filtro-fecha-inicio" class="historial-form-label">Fecha de Inicio</label>
                        <input type="date" class="historial-form-control" id="filtro-fecha-inicio" 
                               name="fecha_inicio"
                               value="<?php echo htmlspecialchars($_GET['fecha_inicio'] ?? ''); ?>">
                    </div>
                    <div class="historial-form-group">
                        <label for="filtro-fecha-fin" class="historial-form-label">Fecha de Fin</label>
                        <input type="date" class="historial-form-control" id="filtro-fecha-fin" 
                               name="fecha_fin"
                               value="<?php echo htmlspecialchars($_GET['fecha_fin'] ?? ''); ?>">
                    </div>
                </div>
                <div class="historial-filter-actions">
                    <button type="submit" class="historial-btn historial-btn-primary">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <a href="?almacen_id=<?php echo $filtro_almacen_id; ?>&categoria_id=<?php echo $filtro_categoria_id; ?>" class="historial-btn historial-btn-secondary">
                        <i class="fas fa-times"></i> Limpiar Filtros
                    </a>
                </div>
            </form>
        </div>

        <!-- Tabla de entregas de la categoría -->
        <div class="historial-table-container">
            <div class="historial-table-responsive">
                <table class="historial-table" id="tabla-historial-entregas">
                    <thead>
                        <tr>
                            <th><i class="fas fa-calendar"></i> Fecha</th>
                            <th><i class="fas fa-user"></i> Destinatario</th>
                            <th><i class="fas fa-id-card"></i> DNI</th>
                            <th><i class="fas fa-boxes"></i> Productos Entregados</th>
                            <th><i class="fas fa-user-shield"></i> Responsable</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($entregas)): ?>
                            <tr>
                                <td colspan="5" class="historial-no-results">
                                    <i class="fas fa-inbox"></i>
                                    No hay entregas registradas para esta categoría
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($entregas as $entrega): ?>
                                <tr>
                                    <td class="historial-fecha-cell">
                                        <?php 
                                        $fecha = new DateTime($entrega['fecha_entrega']);
                                        echo $fecha->format('d/m/Y H:i'); 
                                        ?>
                                    </td>
                                    <td class="historial-destinatario-cell">
                                        <?php echo htmlspecialchars($entrega['nombre_destinatario']); ?>
                                    </td>
                                    <td>
                                        <span class="historial-dni-cell">
                                            <?php echo htmlspecialchars($entrega['dni_destinatario']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <ul class="historial-productos-lista">
                                            <?php foreach ($entrega['productos'] as $producto): ?>
                                                <li class="historial-producto-item">
                                                    <i class="fas fa-box"></i>
                                                    <?php echo htmlspecialchars($producto['nombre']); ?>
                                                    <span class="historial-producto-cantidad">
                                                        (<?php echo htmlspecialchars($producto['cantidad']); ?> unidad<?php echo ($producto['cantidad'] != 1) ? 'es' : ''; ?>)
                                                    </span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </td>
                                    <td class="historial-responsable-cell">
                                        <i class="fas fa-user-shield"></i>
                                        <?php echo htmlspecialchars($entrega['usuario_responsable'] ?? 'No registrado'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>
        <div class="historial-pagination">
            <?php
            $params = [];
            foreach ($_GET as $key => $value) {
                if ($key != 'pagina') {
                    $params[] = $key . '=' . urlencode($value);
                }
            }
            $url_params = !empty($params) ? '?' . implode('&', $params) . '&' : '?';
            
            if ($pagina_actual > 1): ?>
                <a href="<?php echo $url_params; ?>pagina=<?php echo $pagina_actual - 1; ?>">
                    <i class="fas fa-chevron-left"></i> Anterior
                </a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                <?php if ($i == $pagina_actual): ?>
                    <span class="historial-pagination-active"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="<?php echo $url_params; ?>pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($pagina_actual < $total_paginas): ?>
                <a href="<?php echo $url_params; ?>pagina=<?php echo $pagina_actual + 1; ?>">
                    Siguiente <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php elseif ($almacen_info): ?>
        <!-- Mostrando categorías del almacén -->
        <?php if ($usuario_rol == 'admin'): ?>
        <a href="historial.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
            Volver a Lista de Almacenes
        </a>
        <?php endif; ?>

        <div class="categoria-breadcrumb">
            <h4>
                <i class="fas fa-warehouse"></i>
                <?php echo htmlspecialchars($almacen_info['nombre']); ?>
            </h4>
            <p>Selecciona una categoría para ver las entregas realizadas</p>
        </div>

        <?php if (!empty($categorias_con_entregas)): ?>
            <div class="categorias-grid">
                <?php foreach ($categorias_con_entregas as $categoria): ?>
                    <div class="categoria-card categoria-<?php echo strtolower(str_replace(' ', '-', $categoria['nombre'])); ?>">
                        <div class="categoria-header">
                            <div class="categoria-icon">
                                <?php
                                // Iconos específicos por categoría
                                $iconos = [
                                    'uniforme' => 'fas fa-tshirt',
                                    'arma' => 'fas fa-crosshairs',
                                    'equipo' => 'fas fa-tools',
                                    'vehiculo' => 'fas fa-car',
                                    'comunicacion' => 'fas fa-radio',
                                    'ropa' => 'fas fa-tshirt',
                                    'accesorio' => 'fas fa-shield-alt',
                                    'kebra' => 'fas fa-vest',
                                    'walkie' => 'fas fa-radio',
                                    'default' => 'fas fa-box'
                                ];
                                
                                $icono = 'fas fa-box';
                                foreach ($iconos as $key => $value) {
                                    if (stripos($categoria['nombre'], $key) !== false) {
                                        $icono = $value;
                                        break;
                                    }
                                }
                                ?>
                                <i class="<?php echo $icono; ?>"></i>
                            </div>
                            <div class="categoria-info">
                                <h3><?php echo htmlspecialchars($categoria['nombre']); ?></h3>
                                <p>Entregas registradas en esta categoría</p>
                            </div>
                        </div>

                        <div class="categoria-stats">
                            <div class="stat-item">
                                <span class="stat-value"><?php echo number_format($categoria['total_entregas']); ?></span>
                                <span class="stat-label">Entregas</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?php echo number_format($categoria['total_productos_entregados']); ?></span>
                                <span class="stat-label">Productos</span>
                            </div>
                        </div>

                        <div class="categoria-actions">
                            <a href="?almacen_id=<?php echo $almacen_info['id']; ?>&categoria_id=<?php echo $categoria['id']; ?>" 
                               class="btn-categoria btn-ver-entregas">
                                <i class="fas fa-history"></i>
                                Ver Entregas
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="historial-no-results">
                <i class="fas fa-inbox"></i>
                <h3>No hay entregas registradas</h3>
                <p>Este almacén aún no tiene entregas registradas en ninguna categoría.</p>
            </div>
        <?php endif; ?>

    <?php elseif ($usuario_rol == 'admin' && !$filtro_almacen_id): ?>
        <!-- Lista de almacenes para admin -->
        <div class="historial-almacenes-container">
            <?php if ($result_almacenes && $result_almacenes->num_rows > 0): ?>
                <?php while ($almacen = $result_almacenes->fetch_assoc()): ?>
                    <div class="historial-almacen-card">
                        <h3><i class="fas fa-warehouse"></i> <?php echo htmlspecialchars($almacen["nombre"]); ?></h3>
                        <p><strong>Ubicación:</strong> <?php echo htmlspecialchars($almacen["ubicacion"]); ?></p>
                        <a href="?almacen_id=<?php echo $almacen['id']; ?>" class="historial-btn historial-btn-primary">
                            <i class="fas fa-eye"></i> Ver Entregas por Categoría
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No hay almacenes registrados.</p>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- Usuario no admin - mostrar directamente las categorías de su almacén -->
        <?php if (!empty($categorias_con_entregas)): ?>
            <div class="categorias-grid">
                <?php foreach ($categorias_con_entregas as $categoria): ?>
                    <div class="categoria-card categoria-<?php echo strtolower(str_replace(' ', '-', $categoria['nombre'])); ?>">
                        <div class="categoria-header">
                            <div class="categoria-icon">
                                <?php
                                $iconos = [
                                    'uniforme' => 'fas fa-tshirt',
                                    'arma' => 'fas fa-crosshairs',
                                    'equipo' => 'fas fa-tools',
                                    'vehiculo' => 'fas fa-car',
                                    'comunicacion' => 'fas fa-radio',
                                    'ropa' => 'fas fa-tshirt',
                                    'accesorio' => 'fas fa-shield-alt',
                                    'kebra' => 'fas fa-vest',
                                    'walkie' => 'fas fa-radio',
                                    'default' => 'fas fa-box'
                                ];
                                
                                $icono = 'fas fa-box';
                                foreach ($iconos as $key => $value) {
                                    if (stripos($categoria['nombre'], $key) !== false) {
                                        $icono = $value;
                                        break;
                                    }
                                }
                                ?>
                                <i class="<?php echo $icono; ?>"></i>
                            </div>
                            <div class="categoria-info">
                                <h3><?php echo htmlspecialchars($categoria['nombre']); ?></h3>
                                <p>Entregas registradas en esta categoría</p>
                            </div>
                        </div>

                        <div class="categoria-stats">
                            <div class="stat-item">
                                <span class="stat-value"><?php echo number_format($categoria['total_entregas']); ?></span>
                                <span class="stat-label">Entregas</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?php echo number_format($categoria['total_productos_entregados']); ?></span>
                                <span class="stat-label">Productos</span>
                            </div>
                        </div>

                        <div class="categoria-actions">
                            <a href="?almacen_id=<?php echo $almacen_id_mostrar; ?>&categoria_id=<?php echo $categoria['id']; ?>" 
                               class="btn-categoria btn-ver-entregas">
                                <i class="fas fa-history"></i>
                                Ver Entregas
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="historial-no-results">
                <i class="fas fa-inbox"></i>
                <h3>No hay entregas registradas</h3>
                <p>Tu almacén aún no tiene entregas registradas en ninguna categoría.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<!-- Modal de Confirmación de Descarga -->
<div id="downloadModal" class="download-modal">
    <div class="download-modal-content">
        <h4><i class="fas fa-download"></i> Confirmar Descarga</h4>
        <p id="modalMessage">¿Desea descargar el reporte en formato <span id="formatoSeleccionado">PDF</span>?</p>
        <div class="download-modal-buttons">
            <button class="modal-btn modal-btn-confirm" onclick="procederDescarga()">
                <i class="fas fa-check"></i> Confirmar
            </button>
            <button class="modal-btn modal-btn-cancel" onclick="cerrarModal()">
                <i class="fas fa-times"></i> Cancelar
            </button>
        </div>
    </div>
</div>

<!-- Indicador de Descarga -->
<div id="downloadIndicator" class="download-indicator">
    <i class="fas fa-download"></i>
    <span>Preparando descarga...</span>
</div>

<!-- Container for dynamic notifications -->
<div id="notificaciones-container" role="alert" aria-live="polite"></div>

<!-- JavaScript del Dashboard con funcionalidad de descarga -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Elementos principales (igual que el dashboard)
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const submenuContainers = document.querySelectorAll('.submenu-container');
    
    // Toggle del menú móvil
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            if (mainContent) {
                mainContent.classList.toggle('with-sidebar');
            }
            
            // Cambiar icono del botón
            const icon = this.querySelector('i');
            if (sidebar.classList.contains('active')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
                this.setAttribute('aria-label', 'Cerrar menú de navegación');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
                this.setAttribute('aria-label', 'Abrir menú de navegación');
            }
        });
    }
    
    // Funcionalidad de submenús
    submenuContainers.forEach(container => {
        const link = container.querySelector('a');
        const submenu = container.querySelector('.submenu');
        const chevron = link.querySelector('.fa-chevron-down');
        
        if (link && submenu) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Cerrar otros submenús
                submenuContainers.forEach(otherContainer => {
                    if (otherContainer !== container) {
                        const otherSubmenu = otherContainer.querySelector('.submenu');
                        const otherChevron = otherContainer.querySelector('.fa-chevron-down');
                        const otherLink = otherContainer.querySelector('a');
                        
                        if (otherSubmenu && otherSubmenu.classList.contains('activo')) {
                            otherSubmenu.classList.remove('activo');
                            if (otherChevron) {
                                otherChevron.style.transform = 'rotate(0deg)';
                            }
                            if (otherLink) {
                                otherLink.setAttribute('aria-expanded', 'false');
                            }
                        }
                    }
                });
                
                // Toggle del submenú actual
                submenu.classList.toggle('activo');
                const isExpanded = submenu.classList.contains('activo');
                
                if (chevron) {
                    chevron.style.transform = isExpanded ? 'rotate(180deg)' : 'rotate(0deg)';
                }
                
                link.setAttribute('aria-expanded', isExpanded.toString());
            });
        }
    });

    // Validación de fechas en formularios
    const form = document.querySelector('.historial-filter-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const fechaInicio = document.getElementById('filtro-fecha-inicio');
            const fechaFin = document.getElementById('filtro-fecha-fin');
            
            if (fechaInicio && fechaFin && fechaInicio.value && fechaFin.value) {
                if (new Date(fechaInicio.value) > new Date(fechaFin.value)) {
                    e.preventDefault();
                    mostrarNotificacion('La fecha de inicio no puede ser mayor que la fecha de fin', 'error');
                }
            }
        });
    }

    // Animaciones para las tarjetas de categoría
    const categoriaCards = document.querySelectorAll('.categoria-card');
    
    // Intersection Observer para animaciones al scroll
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, { threshold: 0.1 });
    
    categoriaCards.forEach((card, index) => {
        // Configurar estado inicial para animación
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = `all 0.6s ease ${index * 0.1}s`;
        
        // Observar para animación
        observer.observe(card);
        
        // Efecto hover mejorado
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });

    // Cerrar menú móvil al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
                if (mainContent) {
                    mainContent.classList.remove('with-sidebar');
                }
                
                const icon = menuToggle.querySelector('i');
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
                menuToggle.setAttribute('aria-label', 'Abrir menú de navegación');
            }
        }
    });
    
    // Navegación por teclado
    document.addEventListener('keydown', function(e) {
        // Cerrar menú móvil con Escape
        if (e.key === 'Escape' && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            if (mainContent) {
                mainContent.classList.remove('with-sidebar');
            }
            menuToggle.focus();
        }
        
        // Cerrar modal con Escape
        if (e.key === 'Escape') {
            cerrarModal();
        }
        
        // Indicador visual para navegación por teclado
        if (e.key === 'Tab') {
            document.body.classList.add('keyboard-navigation');
        }
    });
    
    document.addEventListener('mousedown', function() {
        document.body.classList.remove('keyboard-navigation');
    });

    // Efecto de carga para la tabla (si existe)
    const tabla = document.getElementById('tabla-historial-entregas');
    if (tabla) {
        tabla.style.opacity = '0';
        tabla.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            tabla.style.transition = 'all 0.6s ease';
            tabla.style.opacity = '1';
            tabla.style.transform = 'translateY(0)';
        }, 200);
    }

    // Animación de entrada para la sección de descarga
    const downloadSection = document.querySelector('.download-section');
    if (downloadSection) {
        downloadSection.style.opacity = '0';
        downloadSection.style.transform = 'translateY(30px)';
        
        setTimeout(() => {
            downloadSection.style.transition = 'all 0.8s ease';
            downloadSection.style.opacity = '1';
            downloadSection.style.transform = 'translateY(0)';
        }, 400);
    }
});

// Variables globales para el sistema de descarga
let formatoSeleccionado = '';
let urlDescarga = '';

// Función para confirmar descarga
function confirmarDescarga(formato) {
    formatoSeleccionado = formato;
    
    // Construir URL de descarga con filtros actuales
    const params = new URLSearchParams(window.location.search);
    const almacenId = params.get('almacen_id');
    const categoriaId = params.get('categoria_id');
    
    if (!almacenId || !categoriaId) {
        mostrarNotificacion('Error: No se puede generar el reporte sin seleccionar una categoría', 'error');
        return;
    }
    
    // Construir parámetros para el reporte
    const reportParams = new URLSearchParams({
        formato: formato,
        almacen_id: almacenId,
        categoria_id: categoriaId
    });
    
    // Agregar filtros actuales si existen
    const filtros = ['dni', 'nombre', 'fecha_inicio', 'fecha_fin'];
    filtros.forEach(filtro => {
        const valor = params.get(filtro);
        if (valor) {
            reportParams.append(filtro, valor);
        }
    });
    
    urlDescarga = 'generar_reporte.php?' + reportParams.toString();
    
    // Mostrar modal de confirmación
    document.getElementById('formatoSeleccionado').textContent = formato.toUpperCase();
    document.getElementById('downloadModal').style.display = 'block';
    
    // Animar entrada del modal
    setTimeout(() => {
        document.querySelector('.download-modal-content').style.transform = 'scale(1)';
    }, 10);
}

// Función para proceder con la descarga
function procederDescarga() {
    cerrarModal();
    
    // Mostrar indicador de descarga
    const indicator = document.getElementById('downloadIndicator');
    indicator.style.display = 'block';
    
    // Crear enlace temporal para descarga
    const link = document.createElement('a');
    link.href = urlDescarga;
    link.target = '_blank';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Simular tiempo de preparación y ocultar indicador
    setTimeout(() => {
        indicator.style.display = 'none';
        mostrarNotificacion('¡Descarga iniciada correctamente!', 'success');
    }, 2000);
}

// Función para cerrar modal
function cerrarModal() {
    const modal = document.getElementById('downloadModal');
    const modalContent = document.querySelector('.download-modal-content');
    
    modalContent.style.transform = 'scale(0.95)';
    
    setTimeout(() => {
        modal.style.display = 'none';
        modalContent.style.transform = 'scale(1)';
    }, 200);
}

// Cerrar modal al hacer clic fuera
document.getElementById('downloadModal').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModal();
    }
});

// Función para cerrar sesión con confirmación
async function manejarCerrarSesion(event) {
    event.preventDefault();
    
    if (confirm('¿Está seguro de que desea cerrar sesión?')) {
        window.location.href = '../logout.php';
    }
}

// Función para mostrar notificaciones
function mostrarNotificacion(mensaje, tipo = 'info', duracion = 4000) {
    const container = document.getElementById('notificaciones-container');
    if (!container) return;
    
    const notificacion = document.createElement('div');
    notificacion.className = `historial-notificacion historial-${tipo}`;
    
    // Configurar colores según el tipo
    let color = '#0a253c';
    let icono = 'fas fa-info-circle';
    
    switch(tipo) {
        case 'success':
            color = '#28a745';
            icono = 'fas fa-check-circle';
            break;
        case 'error':
            color = '#dc3545';
            icono = 'fas fa-exclamation-circle';
            break;
        case 'warning':
            color = '#ffc107';
            icono = 'fas fa-exclamation-triangle';
            break;
    }
    
    notificacion.innerHTML = `
        <i class="${icono}"></i>
        <span>${mensaje}</span>
    `;
    
    // Estilos para la notificación
    notificacion.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${color};
        color: white;
        border-radius: 10px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        z-index: 10001;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 500;
        max-width: 400px;
    `;
    
    container.appendChild(notificacion);
    
    // Mostrar notificación
    setTimeout(() => {
        notificacion.style.opacity = '1';
        notificacion.style.transform = 'translateX(0)';
    }, 100);
    
    // Ocultar y eliminar notificación
    setTimeout(() => {
        notificacion.style.opacity = '0';
        notificacion.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (container.contains(notificacion)) {
                container.removeChild(notificacion);
            }
        }, 300);
    }, duracion);
}

// Manejo de errores globales
window.addEventListener('error', function(e) {
    console.error('Error detectado:', e.error);
    mostrarNotificacion('Se ha producido un error. Por favor, recarga la página.', 'error');
});

// Optimización de rendimiento
if ('IntersectionObserver' in window) {
    const downloadObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, { threshold: 0.1 });
    
    const downloadElements = document.querySelectorAll('.download-btn');
    downloadElements.forEach(btn => {
        downloadObserver.observe(btn);
    });
}
</script>
</body>
</html>