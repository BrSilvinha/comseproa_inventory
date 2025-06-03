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

// Obtener almacenes
if ($usuario_rol == 'admin') {
    $sql_almacenes = "SELECT id, nombre, ubicacion FROM almacenes ORDER BY id DESC";
    $result_almacenes = $conn->query($sql_almacenes);
} else {
    // Si no es admin, mostrar solo el almacén asignado
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

// Variable para almacenar entregas
$entregas = [];

// Configuración de paginación
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Función para obtener entregas por almacén con paginación
function obtenerEntregasPorAlmacen($conn, $almacen_id, $filtros = [], $limite = null, $offset = null) {
    // Verificar si la tabla existe
    $check_table = "SHOW TABLES LIKE 'entrega_uniformes'";
    $table_result = $conn->query($check_table);
    
    if ($table_result->num_rows == 0) {
        return [];
    }

    $query = '
        SELECT 
            eu.id,
            eu.nombre_destinatario,
            eu.dni_destinatario,
            eu.fecha_entrega,
            p.nombre as producto_nombre,
            eu.cantidad,
            a.nombre as almacen_nombre,
            u.nombre as usuario_responsable
        FROM 
            entrega_uniformes eu
        JOIN 
            productos p ON eu.producto_id = p.id
        JOIN 
            almacenes a ON eu.almacen_id = a.id
        LEFT JOIN
            usuarios u ON eu.usuario_responsable_id = u.id
        WHERE 
            eu.almacen_id = ?
    ';

    $params = [$almacen_id];
    $types = 'i';

    // Agregar filtros
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

    // Filtros de fecha
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

    // Agregar límite y offset para paginación
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
        $key = $row['fecha_entrega'] . '|' . $row['nombre_destinatario'] . '|' . $row['dni_destinatario'] . '|' . $row['almacen_nombre'];
        
        if (!isset($entregasAgrupadas[$key])) {
            $entregasAgrupadas[$key] = [
                'id' => $row['id'],
                'fecha_entrega' => $row['fecha_entrega'],
                'nombre_destinatario' => $row['nombre_destinatario'],
                'dni_destinatario' => $row['dni_destinatario'],
                'almacen_nombre' => $row['almacen_nombre'],
                'usuario_responsable' => $row['usuario_responsable'],
                'productos' => []
            ];
        }
        
        $productoExistente = false;
        foreach ($entregasAgrupadas[$key]['productos'] as &$producto) {
            if ($producto['nombre'] === $row['producto_nombre']) {
                $producto['cantidad'] += $row['cantidad'];
                $productoExistente = true;
                break;
            }
        }
        
        if (!$productoExistente) {
            $entregasAgrupadas[$key]['productos'][] = [
                'nombre' => $row['producto_nombre'],
                'cantidad' => $row['cantidad']
            ];
        }
    }

    return array_values($entregasAgrupadas);
}

// Función para obtener resumen de productos entregados por almacén
function obtenerResumenProductosPorAlmacen($conn, $almacen_id, $filtros = []) {
    $query = '
        SELECT 
            p.id as producto_id,
            p.nombre as producto_nombre,
            SUM(eu.cantidad) as total_entregado,
            COUNT(DISTINCT eu.dni_destinatario) as personas_atendidas,
            MIN(eu.fecha_entrega) as primera_entrega,
            MAX(eu.fecha_entrega) as ultima_entrega
        FROM 
            entrega_uniformes eu
        JOIN 
            productos p ON eu.producto_id = p.id
        WHERE 
            eu.almacen_id = ?
    ';

    $params = [$almacen_id];
    $types = 'i';

    // Agregar filtros de fecha si existen
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

    $query .= ' GROUP BY p.id, p.nombre ORDER BY total_entregado DESC';
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param($types, ...$params);
    
    if (!$stmt->execute()) {
        return [];
    }
    
    $result = $stmt->get_result();
    $resumen = [];
    
    while ($row = $result->fetch_assoc()) {
        // Agregar código simulado basado en el ID del producto
        $row['producto_codigo'] = 'PRD-' . str_pad($row['producto_id'], 4, '0', STR_PAD_LEFT);
        $resumen[] = $row;
    }

    return $resumen;
}

// Preparar filtros
$filtros = [
    'dni' => $_GET['dni'] ?? '',
    'nombre' => $_GET['nombre'] ?? '',
    'fecha_inicio' => $_GET['fecha_inicio'] ?? '',
    'fecha_fin' => $_GET['fecha_fin'] ?? ''
];

// Determinar qué almacén mostrar
$almacen_id_mostrar = null;

if ($usuario_rol == 'admin') {
    // Si es admin y se seleccionó un almacén específico
    $almacen_id_mostrar = isset($_GET['almacen_id']) ? intval($_GET['almacen_id']) : null;
} else {
    // Si no es admin, usar su almacén asignado
    $almacen_id_mostrar = $usuario_almacen_id;
}

// Función para contar el total de entregas (para la paginación)
function contarEntregasPorAlmacen($conn, $almacen_id, $filtros = []) {
    $entregas = obtenerEntregasPorAlmacen($conn, $almacen_id, $filtros);
    return count($entregas);
}

// Obtener entregas si hay un almacén seleccionado
$resumen_productos = [];
if ($almacen_id_mostrar) {
    $total_entregas = contarEntregasPorAlmacen($conn, $almacen_id_mostrar, $filtros);
    $entregas = obtenerEntregasPorAlmacen($conn, $almacen_id_mostrar, $filtros, $registros_por_pagina, $offset);
    $resumen_productos = obtenerResumenProductosPorAlmacen($conn, $almacen_id_mostrar, $filtros);
    
    // Obtener nombre del almacén seleccionado
    $nombre_almacen_seleccionado = '';
    if ($almacen_id_mostrar) {
        $sql_nombre = "SELECT nombre FROM almacenes WHERE id = ?";
        $stmt_nombre = $conn->prepare($sql_nombre);
        $stmt_nombre->bind_param("i", $almacen_id_mostrar);
        $stmt_nombre->execute();
        $result_nombre = $stmt_nombre->get_result();
        if ($row_nombre = $result_nombre->fetch_assoc()) {
            $nombre_almacen_seleccionado = $row_nombre['nombre'];
        }
    }
}

// Calcular total de páginas
$total_paginas = isset($total_entregas) ? ceil($total_entregas / $registros_por_pagina) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Historial de Entregas - GRUPO SEAL</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Ver Historial de Entregas de productos - Sistema de gestión GRUPO SEAL">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Preconnect para optimizar carga de fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- CSS consistente con el dashboard -->
    <link rel="stylesheet" href="../assets/css/listar-usuarios.css">
    
    <!-- CSS específico del historial -->
    <link rel="stylesheet" href="../assets/css/historial-entregas.css">
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

        <!-- Warehouses Section -->
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

        <!-- Products Section -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Productos" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-boxes"></i> Productos</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <?php if ($usuario_rol == 'admin'): ?>
                <li><a href="../productos/registrar.php" role="menuitem"><i class="fas fa-plus"></i> Registrar Producto</a></li>
                <?php endif; ?>
                <li><a href="../productos/listar.php" role="menuitem"><i class="fas fa-list"></i> Lista de Productos</a></li>
                <li><a href="../productos/categorias.php" role="menuitem"><i class="fas fa-tags"></i> Categorías</a></li>
            </ul>
        </li>

        <!-- Entregas Section -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Entregas" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-hand-holding"></i> Entregas</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li><a href="historial.php" role="menuitem"><i class="fas fa-history"></i> Ver Historial de Entregas</a></li>
            </ul>
        </li>
        
        <!-- Notifications Section -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Notificaciones" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-bell"></i> Notificaciones</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li>
                    <a href="../notificaciones/pendientes.php" role="menuitem">
                        <i class="fas fa-clock"></i> Solicitudes Pendientes
                        <?php 
                        // Count pending requests to show in badge
                        $sql_pendientes = "SELECT COUNT(*) as total FROM solicitudes_transferencia WHERE estado = 'pendiente'";
                        
                        // If user is not admin, filter by their warehouse
                        if ($usuario_rol != 'admin') {
                            $sql_pendientes .= " AND almacen_destino = ?";
                            $stmt_pendientes = $conn->prepare($sql_pendientes);
                            $stmt_pendientes->bind_param("i", $usuario_almacen_id);
                            $stmt_pendientes->execute();
                            $result_pendientes = $stmt_pendientes->get_result();
                        } else {
                            $result_pendientes = $conn->query($sql_pendientes);
                        }
                        
                        if ($result_pendientes && $row_pendientes = $result_pendientes->fetch_assoc()) {
                            $total_pendientes = $row_pendientes['total'];
                            if ($total_pendientes > 0) {
                                echo '<span class="badge-small" aria-label="' . $total_pendientes . ' solicitudes pendientes">' . $total_pendientes . '</span>';
                            }
                        }
                        ?>
                    </a>
                </li>
                <li><a href="../notificaciones/historial.php" role="menuitem"><i class="fas fa-history"></i> Historial de Solicitudes</a></li>
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
                <?php if ($usuario_rol == 'admin' && !isset($_GET['almacen_id'])): ?>
                    Ver Historial de Entregas - Seleccionar Almacén
                <?php else: ?>
                    Ver Historial de Entregas
                <?php endif; ?>
            </h2>
            
            <?php if ($usuario_rol == 'admin' && isset($_GET['almacen_id'])): ?>
                <a href="historial.php" class="historial-btn historial-btn-back">
                    <i class="fas fa-arrow-left"></i> Volver a la lista de almacenes
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($usuario_rol == 'admin' && !isset($_GET['almacen_id'])): ?>
        <div class="historial-almacenes-container">
            <?php if ($result_almacenes && $result_almacenes->num_rows > 0): ?>
                <?php while ($almacen = $result_almacenes->fetch_assoc()): ?>
                    <div class="historial-almacen-card">
                        <h3><i class="fas fa-warehouse"></i> <?php echo htmlspecialchars($almacen["nombre"]); ?></h3>
                        <p><strong>Ubicación:</strong> <?php echo htmlspecialchars($almacen["ubicacion"]); ?></p>
                        <a href="#" class="historial-btn historial-btn-primary mostrar-entregas" data-almacen-id="<?php echo $almacen['id']; ?>" data-almacen-nombre="<?php echo htmlspecialchars($almacen['nombre']); ?>">
                            <i class="fas fa-eye"></i> Ver Historial de Entregas
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No hay almacenes registrados.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (($usuario_rol != 'admin') || ($usuario_rol == 'admin' && isset($_GET['almacen_id']))): ?>
    
    <div id="contenedor-historial-entregas">
        
        <?php if (isset($nombre_almacen_seleccionado) && $nombre_almacen_seleccionado): ?>
        <div class="historial-almacen-seleccionado">
            <h3><i class="fas fa-warehouse"></i> <?php echo htmlspecialchars($nombre_almacen_seleccionado); ?></h3>
            <p>Ver Historial de Entregas y resumen de productos</p>
        </div>
        <?php endif; ?>

        <!-- Sistema de Pestañas -->
        <div class="historial-tabs-container">
            <div class="historial-tabs-nav">
                <button class="historial-tab-btn active" data-tab="historial">
                    <i class="fas fa-history"></i> Ver Historial de Entregas
                </button>
                <button class="historial-tab-btn" data-tab="resumen">
                    <i class="fas fa-chart-pie"></i> Resumen de Productos
                </button>
            </div>

            <!-- Pestaña: Ver Historial de Entregas -->
            <div class="historial-tab-content active" id="tab-historial">
                <form method="GET" class="historial-filter-form" id="formulario-filtros">
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
                        <a href="?" class="historial-btn historial-btn-secondary">
                            <i class="fas fa-times"></i> Limpiar Filtros
                        </a>
                    </div>
                    <!-- Conservar el almacén seleccionado durante la paginación -->
                    <?php if (isset($_GET['almacen_id'])): ?>
                        <input type="hidden" name="almacen_id" value="<?php echo htmlspecialchars($_GET['almacen_id']); ?>">
                    <?php endif; ?>
                </form>

                <div class="historial-table-container">
                    <div class="historial-table-responsive">
                        <table class="historial-table" id="tabla-historial-entregas">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-warehouse"></i> Almacén</th>
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
                                        <td colspan="6" class="historial-no-results">
                                            <i class="fas fa-inbox"></i>
                                            <?php if ($almacen_id_mostrar): ?>
                                                No hay entregas registradas para este almacén
                                                <?php if ($usuario_rol == 'admin'): ?>
                                                    <br><small>Almacén ID: <?php echo $almacen_id_mostrar; ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                Seleccione un almacén para ver las entregas
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($entregas as $entrega): ?>
                                        <tr>
                                            <td class="historial-almacen-cell">
                                                <i class="fas fa-warehouse"></i>
                                                <?php echo htmlspecialchars($entrega['almacen_nombre']); ?>
                                            </td>
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
                    // Parámetros actuales de URL para mantener en los enlaces de paginación
                    $params = [];
                    foreach ($_GET as $key => $value) {
                        if ($key != 'pagina') {
                            $params[] = $key . '=' . urlencode($value);
                        }
                    }
                    $url_params = !empty($params) ? '?' . implode('&', $params) . '&' : '?';
                    
                    // Mostrar enlace "Anterior" si no estamos en la primera página
                    if ($pagina_actual > 1): ?>
                        <a href="<?php echo $url_params; ?>pagina=<?php echo $pagina_actual - 1; ?>">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </a>
                    <?php else: ?>
                        <span class="historial-pagination-disabled">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </span>
                    <?php endif; ?>
                    
                    <?php
                    // Definir cuántas páginas mostrar a cada lado de la página actual
                    $paginas_mostrar = 2;
                    $inicio_paginas = max(1, $pagina_actual - $paginas_mostrar);
                    $fin_paginas = min($total_paginas, $pagina_actual + $paginas_mostrar);
                    
                    // Mostrar página 1 si estamos muy lejos
                    if ($inicio_paginas > 1): ?>
                        <a href="<?php echo $url_params; ?>pagina=1">1</a>
                        <?php if ($inicio_paginas > 2): ?>
                            <span class="historial-pagination-ellipsis">...</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- Mostrar enlaces numerados -->
                    <?php for ($i = $inicio_paginas; $i <= $fin_paginas; $i++): ?>
                        <?php if ($i == $pagina_actual): ?>
                            <span class="historial-pagination-active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="<?php echo $url_params; ?>pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <!-- Mostrar última página si estamos muy lejos -->
                    <?php if ($fin_paginas < $total_paginas): ?>
                        <?php if ($fin_paginas < $total_paginas - 1): ?>
                            <span class="historial-pagination-ellipsis">...</span>
                        <?php endif; ?>
                        <a href="<?php echo $url_params; ?>pagina=<?php echo $total_paginas; ?>"><?php echo $total_paginas; ?></a>
                    <?php endif; ?>
                    
                    <!-- Mostrar enlace "Siguiente" si no estamos en la última página -->
                    <?php if ($pagina_actual < $total_paginas): ?>
                        <a href="<?php echo $url_params; ?>pagina=<?php echo $pagina_actual + 1; ?>">
                            Siguiente <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="historial-pagination-disabled">
                            Siguiente <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Pestaña: Resumen de Productos -->
            <div class="historial-tab-content" id="tab-resumen">
                <div class="historial-resumen-stats">
                    <div class="historial-stat-card">
                        <div class="historial-stat-icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="historial-stat-info">
                            <h4><?php echo count($resumen_productos); ?></h4>
                            <p>Productos Diferentes</p>
                        </div>
                    </div>
                    <div class="historial-stat-card">
                        <div class="historial-stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="historial-stat-info">
                            <h4><?php 
                            // Calcular personas únicas totales (no sumar por producto)
                            if ($almacen_id_mostrar && !empty($entregas)) {
                                $personas_unicas = [];
                                foreach ($entregas as $entrega) {
                                    $personas_unicas[$entrega['dni_destinatario']] = true;
                                }
                                echo count($personas_unicas);
                            } else {
                                echo "0";
                            }
                            ?></h4>
                            <p>Personas Atendidas</p>
                        </div>
                    </div>
                    <div class="historial-stat-card">
                        <div class="historial-stat-icon">
                            <i class="fas fa-hand-holding"></i>
                        </div>
                        <div class="historial-stat-info">
                            <h4><?php echo array_sum(array_column($resumen_productos, 'total_entregado')); ?></h4>
                            <p>Total Entregado</p>
                        </div>
                    </div>
                </div>

                <div class="historial-table-container">
                    <div class="historial-table-responsive">
                        <table class="historial-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-barcode"></i> Código</th>
                                    <th><i class="fas fa-box"></i> Producto</th>
                                    <th><i class="fas fa-calculator"></i> Total Entregado</th>
                                    <th><i class="fas fa-users"></i> Personas Atendidas</th>
                                    <th><i class="fas fa-calendar-alt"></i> Primera Entrega</th>
                                    <th><i class="fas fa-calendar-check"></i> Última Entrega</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($resumen_productos)): ?>
                                    <tr>
                                        <td colspan="6" class="historial-no-results">
                                            <i class="fas fa-chart-pie"></i>
                                            No hay datos de productos para mostrar
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($resumen_productos as $producto): ?>
                                        <tr>
                                            <td class="historial-codigo-cell">
                                                <span class="historial-codigo-badge">
                                                    <?php echo htmlspecialchars($producto['producto_codigo'] ?? 'Sin código'); ?>
                                                </span>
                                            </td>
                                            <td class="historial-producto-nombre">
                                                <strong><?php echo htmlspecialchars($producto['producto_nombre']); ?></strong>
                                            </td>
                                            <td class="historial-cantidad-total">
                                                <span class="historial-badge-cantidad">
                                                    <?php echo number_format($producto['total_entregado']); ?>
                                                </span>
                                            </td>
                                            <td class="historial-personas-atendidas">
                                                <?php echo number_format($producto['personas_atendidas']); ?>
                                            </td>
                                            <td class="historial-fecha-cell">
                                                <?php 
                                                if ($producto['primera_entrega']) {
                                                    $fecha_primera = new DateTime($producto['primera_entrega']);
                                                    echo $fecha_primera->format('d/m/Y');
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </td>
                                            <td class="historial-fecha-cell">
                                                <?php 
                                                if ($producto['ultima_entrega']) {
                                                    $fecha_ultima = new DateTime($producto['ultima_entrega']);
                                                    echo $fecha_ultima->format('d/m/Y');
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
</main>

<!-- Container for dynamic notifications -->
<div id="notificaciones-container" role="alert" aria-live="polite"></div>

<!-- JavaScript del Dashboard -->
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

    // Manejar clic en "Ver Historial de Entregas" para admin
    const botonesVerEntregas = document.querySelectorAll('.mostrar-entregas');
    const contenedorHistorial = document.getElementById('contenedor-historial-entregas');
    const almacenesContainer = document.querySelector('.historial-almacenes-container');

    botonesVerEntregas.forEach(boton => {
        boton.addEventListener('click', function(e) {
            e.preventDefault();
            const almacenId = this.dataset.almacenId;
            const almacenNombre = this.dataset.almacenNombre;

            // Cambiar el texto del botón
            this.innerHTML = '<i class="fas fa-history"></i> Ver Ver Historial de Entregas';

            // Redirigir con el almacen_id seleccionado
            window.location.href = `?almacen_id=${almacenId}`;
        });
    });

    // Sistema de pestañas
    const tabButtons = document.querySelectorAll('.historial-tab-btn');
    const tabContents = document.querySelectorAll('.historial-tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetTab = this.dataset.tab;

            // Remover clase active de todos los botones y contenidos
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));

            // Agregar clase active al botón y contenido seleccionado
            this.classList.add('active');
            document.getElementById('tab-' + targetTab).classList.add('active');

            // Efectos de transición
            const activeContent = document.getElementById('tab-' + targetTab);
            activeContent.style.opacity = '0';
            activeContent.style.transform = 'translateY(10px)';
            
            setTimeout(() => {
                activeContent.style.transition = 'all 0.3s ease';
                activeContent.style.opacity = '1';
                activeContent.style.transform = 'translateY(0)';
            }, 50);
        });
    });

    // Agregar botón para volver a la lista de almacenes (para admin)
    // Ya no es necesario, ahora está manejado por PHP

    // Validación de fechas
    const form = document.querySelector('.historial-filter-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const fechaInicio = document.getElementById('filtro-fecha-inicio').value;
            const fechaFin = document.getElementById('filtro-fecha-fin').value;

            if (fechaInicio && fechaFin && new Date(fechaInicio) > new Date(fechaFin)) {
                e.preventDefault();
                alert('La fecha de inicio no puede ser mayor que la fecha de fin');
            }
        });
    }

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
        
        // Indicador visual para navegación por teclado
        if (e.key === 'Tab') {
            document.body.classList.add('keyboard-navigation');
        }
    });
    
    document.addEventListener('mousedown', function() {
        document.body.classList.remove('keyboard-navigation');
    });

    // Efecto de carga para la tabla
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
});

// Función para cerrar sesión con confirmación
async function manejarCerrarSesion(event) {
    event.preventDefault();
    
    if (confirm('¿Está seguro de que desea cerrar sesión?')) {
        window.location.href = '../logout.php';
    }
}

// Función para mostrar notificaciones (si se necesita)
function mostrarNotificacion(mensaje, tipo = 'info', duracion = 3000) {
    const container = document.getElementById('notificaciones-container');
    if (!container) return;
    
    const notificacion = document.createElement('div');
    notificacion.className = `historial-notificacion historial-${tipo}`;
    notificacion.textContent = mensaje;
    
    // Estilos básicos para la notificación
    notificacion.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: var(--historial-primary);
        color: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 9999;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
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
</script>
</body>
</html>