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

    // Agregar filtros
    if (!empty($filtros['dni'])) {
        $query .= ' AND eu.dni_destinatario LIKE ?';
        $params[] = '%' . $filtros['dni'] . '%';
    }

    if (!empty($filtros['nombre'])) {
        $query .= ' AND eu.nombre_destinatario LIKE ?';
        $params[] = '%' . $filtros['nombre'] . '%';
    }

    // Filtros de fecha
    if (!empty($filtros['fecha_inicio'])) {
        $query .= ' AND DATE(eu.fecha_entrega) >= ?';
        $params[] = $filtros['fecha_inicio'];
    }
    if (!empty($filtros['fecha_fin'])) {
        $query .= ' AND DATE(eu.fecha_entrega) <= ?';
        $params[] = $filtros['fecha_fin'];
    }

    $query .= ' ORDER BY eu.fecha_entrega DESC';

    // Agregar límite y offset para paginación
    if ($limite !== null && $offset !== null) {
        $query_with_limit = $query . ' LIMIT ? OFFSET ?';
        $params_with_limit = $params;
        $params_with_limit[] = $limite;
        $params_with_limit[] = $offset;
        
        $stmt = $conn->prepare($query_with_limit);
        $types = str_repeat('s', count($params)) . 'ii';
        $stmt->bind_param($types, ...$params_with_limit);
    } else {
        $stmt = $conn->prepare($query);
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
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

// Función para contar el total de entregas (para la paginación)
function contarEntregasPorAlmacen($conn, $almacen_id, $filtros = []) {
    $entregas = obtenerEntregasPorAlmacen($conn, $almacen_id, $filtros);
    return count($entregas);
}

// Preparar filtros
$filtros = [];
if (isset($_GET['dni']) || isset($_GET['nombre']) || isset($_GET['fecha_inicio']) || isset($_GET['fecha_fin']) || isset($_GET['almacen_id'])) {
    $filtros = [
        'dni' => $_GET['dni'] ?? '',
        'nombre' => $_GET['nombre'] ?? '',
        'fecha_inicio' => $_GET['fecha_inicio'] ?? '',
        'fecha_fin' => $_GET['fecha_fin'] ?? ''
    ];
    
    // Si es admin y se seleccionó un almacén específico
    $almacen_id_seleccionado = isset($_GET['almacen_id']) ? intval($_GET['almacen_id']) : null;
    if ($usuario_rol == 'admin' && $almacen_id_seleccionado) {
        $total_entregas = contarEntregasPorAlmacen($conn, $almacen_id_seleccionado, $filtros);
        $entregas = obtenerEntregasPorAlmacen($conn, $almacen_id_seleccionado, $filtros, $registros_por_pagina, $offset);
    } elseif ($usuario_rol != 'admin' && $usuario_almacen_id) {
        $total_entregas = contarEntregasPorAlmacen($conn, $usuario_almacen_id, $filtros);
        $entregas = obtenerEntregasPorAlmacen($conn, $usuario_almacen_id, $filtros, $registros_por_pagina, $offset);
    }
} elseif ($usuario_rol != 'admin' && $usuario_almacen_id) {
    // Si no es admin, obtener entregas del almacén asignado con paginación
    $total_entregas = contarEntregasPorAlmacen($conn, $usuario_almacen_id, $filtros);
    $entregas = obtenerEntregasPorAlmacen($conn, $usuario_almacen_id, $filtros, $registros_por_pagina, $offset);
}

// Calcular total de páginas
$total_paginas = isset($total_entregas) ? ceil($total_entregas / $registros_por_pagina) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Entregas - GRUPO SEAL</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Historial de entregas de productos - Sistema de gestión GRUPO SEAL">
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
    <link rel="stylesheet" href="../assets/css/dashboard-consistent.css">
    
    <style>
        .almacenes-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .almacen-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
        }

        .almacen-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .almacen-card h3 {
            color: #0a253c;
            margin-bottom: 10px;
            font-size: 18px;
        }

        .btn-ver {
            background: #0a253c;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s ease;
        }

        .btn-ver:hover {
            background: #ff6b35;
            color: white;
        }

        .filter-form {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            margin-bottom: 5px;
            font-weight: 600;
            color: #0a253c;
        }

        .form-control {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .btn-filter {
            background: #0a253c;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-filter:hover {
            background: #ff6b35;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s ease;
        }

        .btn-secondary:hover {
            background: #5a6268;
            color: white;
        }

        .table-responsive {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .table thead {
            background: #0a253c;
            color: white;
        }

        .table th,
        .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        .productos-lista {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .productos-lista li {
            background: #f8f9fa;
            padding: 5px 10px;
            margin: 2px 0;
            border-radius: 4px;
            font-size: 13px;
        }

        .no-results-message {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #0a253c;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: #0a253c;
            color: white;
        }

        .pagination .active {
            background: #0a253c;
            color: white;
            border-color: #0a253c;
        }

        .pagination .disabled {
            color: #6c757d;
            cursor: not-allowed;
        }

        .btn-volver {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-volver:hover {
            background: #5a6268;
        }

        @media (max-width: 768px) {
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .almacenes-container {
                grid-template-columns: 1fr;
            }
            
            .table-responsive {
                overflow-x: auto;
            }
            
            .pagination {
                gap: 5px;
            }
            
            .pagination a,
            .pagination span {
                padding: 6px 8px;
                font-size: 14px;
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
                <li><a href="historial.php" role="menuitem"><i class="fas fa-history"></i> Historial de Entregas</a></li>
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
    <h2><i class="fas fa-history"></i> Historial de Entregas</h2>

    <?php if ($usuario_rol == 'admin'): ?>
        <div class="almacenes-container">
            <?php if ($result_almacenes && $result_almacenes->num_rows > 0): ?>
                <?php while ($almacen = $result_almacenes->fetch_assoc()): ?>
                    <div class="almacen-card">
                        <h3><i class="fas fa-warehouse"></i> <?php echo htmlspecialchars($almacen["nombre"]); ?></h3>
                        <p><strong>Ubicación:</strong> <?php echo htmlspecialchars($almacen["ubicacion"]); ?></p>
                        <a href="#" class="btn-ver mostrar-entregas" data-almacen-id="<?php echo $almacen['id']; ?>">
                            <i class="fas fa-eye"></i> Ver Entregas
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No hay almacenes registrados.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div id="contenedor-historial-entregas" style="<?php echo $usuario_rol != 'admin' ? 'display:block;' : 'display:none;'; ?>">
        <form method="GET" class="filter-form" id="formulario-filtros">
            <div class="filter-row">
                <div class="form-group">
                    <label for="filtro-nombre" class="form-label">Filtrar por Nombre</label>
                    <input type="text" class="form-control" id="filtro-nombre" name="nombre" 
                           placeholder="Nombre del destinatario" 
                           value="<?php echo htmlspecialchars($_GET['nombre'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="filtro-dni" class="form-label">Filtrar por DNI</label>
                    <input type="text" class="form-control" id="filtro-dni" name="dni" 
                           placeholder="Número de DNI" 
                           value="<?php echo htmlspecialchars($_GET['dni'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="filtro-fecha-inicio" class="form-label">Fecha de Inicio</label>
                    <input type="date" class="form-control" id="filtro-fecha-inicio" 
                           name="fecha_inicio"
                           value="<?php echo htmlspecialchars($_GET['fecha_inicio'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="filtro-fecha-fin" class="form-label">Fecha de Fin</label>
                    <input type="date" class="form-control" id="filtro-fecha-fin" 
                           name="fecha_fin"
                           value="<?php echo htmlspecialchars($_GET['fecha_fin'] ?? ''); ?>">
                </div>
            </div>
            <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="submit" class="btn-filter">
                    <i class="fas fa-search"></i> Buscar
                </button>
                <a href="?" class="btn-secondary">
                    <i class="fas fa-times"></i> Limpiar Filtros
                </a>
            </div>
            <!-- Conservar el almacén seleccionado durante la paginación -->
            <?php if (isset($_GET['almacen_id'])): ?>
                <input type="hidden" name="almacen_id" value="<?php echo htmlspecialchars($_GET['almacen_id']); ?>">
            <?php endif; ?>
        </form>

        <div class="table-responsive">
            <table class="table" id="tabla-historial-entregas">
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
                            <td colspan="6" class="no-results-message">
                                <i class="fas fa-inbox"></i><br>
                                No hay entregas para mostrar
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($entregas as $entrega): ?>
                            <tr>
                                <td>
                                    <i class="fas fa-warehouse" style="color: #0a253c;"></i>
                                    <?php echo htmlspecialchars($entrega['almacen_nombre']); ?>
                                </td>
                                <td>
                                    <?php 
                                    $fecha = new DateTime($entrega['fecha_entrega']);
                                    echo $fecha->format('d/m/Y H:i'); 
                                    ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($entrega['nombre_destinatario']); ?></strong>
                                </td>
                                <td>
                                    <code><?php echo htmlspecialchars($entrega['dni_destinatario']); ?></code>
                                </td>
                                <td>
                                    <ul class="productos-lista">
                                        <?php foreach ($entrega['productos'] as $producto): ?>
                                            <li>
                                                <i class="fas fa-box" style="color: #0a253c;"></i>
                                                <?php 
                                                echo htmlspecialchars($producto['nombre']) . 
                                                     ' <strong>(' . 
                                                     htmlspecialchars($producto['cantidad']) . 
                                                     ' unidad' . ($producto['cantidad'] != 1 ? 'es' : '') . ')</strong>'; 
                                                ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </td>
                                <td>
                                    <i class="fas fa-user-shield" style="color: #0a253c;"></i>
                                    <?php echo htmlspecialchars($entrega['usuario_responsable'] ?? 'No registrado'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>
        <div class="pagination">
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
                <span class="disabled"><i class="fas fa-chevron-left"></i> Anterior</span>
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
                    <span>...</span>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Mostrar enlaces numerados -->
            <?php for ($i = $inicio_paginas; $i <= $fin_paginas; $i++): ?>
                <?php if ($i == $pagina_actual): ?>
                    <span class="active"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="<?php echo $url_params; ?>pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <!-- Mostrar última página si estamos muy lejos -->
            <?php if ($fin_paginas < $total_paginas): ?>
                <?php if ($fin_paginas < $total_paginas - 1): ?>
                    <span>...</span>
                <?php endif; ?>
                <a href="<?php echo $url_params; ?>pagina=<?php echo $total_paginas; ?>"><?php echo $total_paginas; ?></a>
            <?php endif; ?>
            
            <!-- Mostrar enlace "Siguiente" si no estamos en la última página -->
            <?php if ($pagina_actual < $total_paginas): ?>
                <a href="<?php echo $url_params; ?>pagina=<?php echo $pagina_actual + 1; ?>">
                    Siguiente <i class="fas fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <span class="disabled">Siguiente <i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
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

    // Manejar clic en "Ver Entregas" para admin
    const botonesVerEntregas = document.querySelectorAll('.mostrar-entregas');
    const contenedorHistorial = document.getElementById('contenedor-historial-entregas');
    const almacenesContainer = document.querySelector('.almacenes-container');

    botonesVerEntregas.forEach(boton => {
        boton.addEventListener('click', function(e) {
            e.preventDefault();
            const almacenId = this.dataset.almacenId;

            // Ocultar los cuadros de almacén
            if (almacenesContainer) {
                almacenesContainer.style.display = 'none';
            }

            // Redirigir con el almacen_id seleccionado
            window.location.href = `?almacen_id=${almacenId}`;
        });
    });

    // Agregar botón para volver a la lista de almacenes (para admin)
    <?php if ($usuario_rol == 'admin' && isset($_GET['almacen_id'])): ?>
    const mainContentElement = document.getElementById('main-content');
    const volverBtn = document.createElement('button');
    volverBtn.className = 'btn-volver';
    volverBtn.innerHTML = '<i class="fas fa-arrow-left"></i> Volver a la lista de almacenes';
    volverBtn.style.marginBottom = '20px';
    volverBtn.addEventListener('click', function() {
        window.location.href = 'historial.php';
    });
    mainContentElement.insertBefore(volverBtn, contenedorHistorial);
    <?php endif; ?>

    // Validación de fechas
    const form = document.querySelector('.filter-form');
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
});

// Función para cerrar sesión con confirmación
async function manejarCerrarSesion(event) {
    event.preventDefault();
    
    if (confirm('¿Está seguro de que desea cerrar sesión?')) {
        window.location.href = '../logout.php';
    }
}
</script>
</body>
</html>