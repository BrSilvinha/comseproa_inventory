<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

// Evitar secuestro de sesión
session_regenerate_id(true);

require_once "../config/database.php";

$user_name = isset($_SESSION["user_name"]) ? $_SESSION["user_name"] : "Usuario";
$usuario_rol = isset($_SESSION["user_role"]) ? $_SESSION["user_role"] : "usuario";
$usuario_almacen_id = isset($_SESSION["almacen_id"]) ? $_SESSION["almacen_id"] : null;

// Validar el ID del producto
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error'] = "ID de producto no válido";
    header("Location: listar.php");
    exit();
}

$producto_id = $_GET['id'];

// Obtener información completa del producto
$sql = "SELECT p.*, c.nombre as categoria_nombre, a.nombre as almacen_nombre 
        FROM productos p 
        JOIN categorias c ON p.categoria_id = c.id 
        JOIN almacenes a ON p.almacen_id = a.id 
        WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $producto_id);
$stmt->execute();
$result = $stmt->get_result();
$producto = $result->fetch_assoc();
$stmt->close();

if (!$producto) {
    $_SESSION['error'] = "Producto no encontrado";
    header("Location: listar.php");
    exit();
}

// Verificar permisos de acceso (si no es admin, solo puede ver productos de su almacén)
if ($usuario_rol != 'admin' && $usuario_almacen_id != $producto['almacen_id']) {
    $_SESSION['error'] = "No tiene permisos para ver este producto";
    header("Location: listar.php");
    exit();
}

// Obtener historial de movimientos del producto (CORREGIDO PARA TU BD)
$sql_movimientos = "SELECT m.*, 
                    CASE 
                        WHEN m.tipo = 'transferencia' THEN CONCAT(COALESCE(ao.nombre, 'N/A'), ' → ', COALESCE(ad.nombre, 'N/A'))
                        WHEN m.tipo = 'entrada' THEN CONCAT('Entrada a ', COALESCE(ao.nombre, 'N/A'))
                        WHEN m.tipo = 'salida' THEN CONCAT('Salida de ', COALESCE(ao.nombre, 'N/A'))
                        ELSE 'Movimiento'
                    END as descripcion_movimiento,
                    u.nombre as usuario_nombre,
                    ao.nombre as almacen_origen_nombre,
                    ad.nombre as almacen_destino_nombre
                    FROM movimientos m
                    LEFT JOIN usuarios u ON m.usuario_id = u.id
                    LEFT JOIN almacenes ao ON m.almacen_origen = ao.id
                    LEFT JOIN almacenes ad ON m.almacen_destino = ad.id
                    WHERE m.producto_id = ?
                    ORDER BY m.fecha DESC
                    LIMIT 10";
$stmt_movimientos = $conn->prepare($sql_movimientos);
$stmt_movimientos->bind_param("i", $producto_id);
$stmt_movimientos->execute();
$movimientos = $stmt_movimientos->get_result();
$stmt_movimientos->close();

// Obtener solicitudes de transferencia relacionadas
$sql_solicitudes = "SELECT s.*, 
                    ao.nombre as almacen_origen_nombre,
                    ad.nombre as almacen_destino_nombre,
                    u.nombre as usuario_nombre
                    FROM solicitudes_transferencia s
                    LEFT JOIN almacenes ao ON s.almacen_origen = ao.id
                    LEFT JOIN almacenes ad ON s.almacen_destino = ad.id
                    LEFT JOIN usuarios u ON s.usuario_id = u.id
                    WHERE s.producto_id = ?
                    ORDER BY s.fecha_solicitud DESC
                    LIMIT 5";
$stmt_solicitudes = $conn->prepare($sql_solicitudes);
$stmt_solicitudes->bind_param("i", $producto_id);
$stmt_solicitudes->execute();
$solicitudes = $stmt_solicitudes->get_result();
$stmt_solicitudes->close();

// Buscar productos similares (misma categoría, diferente almacén)
$sql_similares = "SELECT p.*, a.nombre as almacen_nombre
                  FROM productos p
                  JOIN almacenes a ON p.almacen_id = a.id
                  WHERE p.categoria_id = ? AND p.id != ? AND p.cantidad > 0
                  ORDER BY p.cantidad DESC
                  LIMIT 5";
$stmt_similares = $conn->prepare($sql_similares);
$stmt_similares->bind_param("ii", $producto['categoria_id'], $producto_id);
$stmt_similares->execute();
$productos_similares = $stmt_similares->get_result();
$stmt_similares->close();

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Producto - <?php echo htmlspecialchars($producto['nombre']); ?> - COMSEPROA</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Detalle del producto <?php echo htmlspecialchars($producto['nombre']); ?> - Sistema COMSEPROA">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- CSS específico para ver producto -->
    <link rel="stylesheet" href="../assets/css/productos-ver.css">
</head>
<body data-producto-id="<?php echo $producto_id; ?>">

<!-- Botón de hamburguesa para dispositivos móviles -->
<button class="menu-toggle" id="menuToggle" aria-label="Abrir menú de navegación">
    <i class="fas fa-bars"></i>
</button>

<!-- Menú Lateral -->
<nav class="sidebar" id="sidebar" role="navigation" aria-label="Menú principal">
    <h2>COMSEPROA</h2>
    <ul>
        <li>
            <a href="../dashboard.php" aria-label="Ir a inicio">
                <span><i class="fas fa-home"></i> Inicio</span>
            </a>
        </li>

        <!-- Users - Only visible to administrators -->
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

        <!-- Warehouses -->
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
                <li><a href="registrar.php" role="menuitem"><i class="fas fa-plus"></i> Registrar Producto</a></li>
                <li><a href="listar.php" role="menuitem"><i class="fas fa-list"></i> Lista de Productos</a></li>
                <li><a href="categorias.php" role="menuitem"><i class="fas fa-tags"></i> Categorías</a></li>
            </ul>
        </li>
        
        <!-- Notifications -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Notificaciones" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-bell"></i> Notificaciones</span>
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
                <li><a href="../notificaciones/historial.php" role="menuitem"><i class="fas fa-history"></i> Historial de Solicitudes</a></li>
                <li><a href="../uniformes/historial_entregas_uniformes.php" role="menuitem"><i class="fas fa-tshirt"></i> Historial de Entregas</a></li>
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

<!-- Contenido Principal -->
<main class="content" id="main-content" role="main">
    <!-- Mensajes de éxito o error -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert success">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; ?>
            <?php unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; ?>
            <?php unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Header del Producto -->
    <header class="product-header">
        <div class="header-content">
            <div class="product-info">
                <div class="product-icon">
                    <i class="fas fa-box"></i>
                </div>
                <div class="product-details">
                    <h1><?php echo htmlspecialchars($producto['nombre']); ?></h1>
                    <div class="product-meta">
                        <span class="categoria">
                            <i class="fas fa-tag"></i>
                            <?php echo htmlspecialchars($producto['categoria_nombre']); ?>
                        </span>
                        <span class="almacen">
                            <i class="fas fa-warehouse"></i>
                            <?php echo htmlspecialchars($producto['almacen_nombre']); ?>
                        </span>
                        <span class="estado estado-<?php echo strtolower($producto['estado']); ?>">
                            <i class="fas fa-info-circle"></i>
                            <?php echo htmlspecialchars($producto['estado']); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="header-actions">
                <?php if ($usuario_rol == 'admin'): ?>
                <button class="btn-action btn-edit" onclick="editarProducto(<?php echo $producto_id; ?>)" title="Editar producto">
                    <i class="fas fa-edit"></i>
                    <span>Editar</span>
                </button>
                <button class="btn-action btn-delete" onclick="eliminarProducto(<?php echo $producto_id; ?>, '<?php echo htmlspecialchars($producto['nombre']); ?>')" title="Eliminar producto">
                    <i class="fas fa-trash"></i>
                    <span>Eliminar</span>
                </button>
                <?php endif; ?>
                
                <?php if ($producto['cantidad'] > 0): ?>
                <button class="btn-action btn-transfer" 
                    data-id="<?php echo $producto_id; ?>"
                    data-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>"
                    data-almacen="<?php echo $producto['almacen_id']; ?>"
                    data-cantidad="<?php echo $producto['cantidad']; ?>"
                    onclick="abrirModalTransferencia(this)"
                    title="Transferir producto">
                    <i class="fas fa-paper-plane"></i>
                    <span>Transferir</span>
                </button>
                <?php endif; ?>
                
                <a href="listar.php?almacen_id=<?php echo $producto['almacen_id']; ?>" class="btn-action btn-back">
                    <i class="fas fa-arrow-left"></i>
                    <span>Volver</span>
                </a>
            </div>
        </div>
        
        <!-- Breadcrumb -->
        <nav class="breadcrumb" aria-label="Ruta de navegación">
            <a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <a href="listar.php">Productos</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <a href="listar.php?almacen_id=<?php echo $producto['almacen_id']; ?>"><?php echo htmlspecialchars($producto['almacen_nombre']); ?></a>
            <span><i class="fas fa-chevron-right"></i></span>
            <span class="current"><?php echo htmlspecialchars($producto['nombre']); ?></span>
        </nav>
    </header>

    <!-- Información del Producto -->
    <div class="main-content-grid">
        <!-- Panel Principal del Producto -->
        <section class="product-details-section">
            <div class="details-card">
                <div class="card-header">
                    <h2>
                        <i class="fas fa-info-circle"></i>
                        Información del Producto
                    </h2>
                </div>
                
                <div class="card-body">
                    <div class="details-grid">
                        <div class="detail-group">
                            <label>Nombre del Producto</label>
                            <value><?php echo htmlspecialchars($producto['nombre']); ?></value>
                        </div>

                        <?php if (!empty($producto['modelo'])): ?>
                        <div class="detail-group">
                            <label>Modelo</label>
                            <value><?php echo htmlspecialchars($producto['modelo']); ?></value>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($producto['color'])): ?>
                        <div class="detail-group">
                            <label>Color</label>
                            <value><?php echo htmlspecialchars($producto['color']); ?></value>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($producto['talla_dimensiones'])): ?>
                        <div class="detail-group">
                            <label>Talla / Dimensiones</label>
                            <value><?php echo htmlspecialchars($producto['talla_dimensiones']); ?></value>
                        </div>
                        <?php endif; ?>

                        <div class="detail-group">
                            <label>Cantidad en Stock</label>
                            <value class="stock-value <?php 
                                if ($producto['cantidad'] < 5) echo 'stock-critical';
                                elseif ($producto['cantidad'] < 10) echo 'stock-warning';
                                else echo 'stock-good';
                            ?>">
                                <span id="cantidad-actual"><?php echo number_format($producto['cantidad']); ?></span> 
                                <?php echo htmlspecialchars($producto['unidad_medida']); ?>
                                <?php if ($usuario_rol == 'admin'): ?>
                                <div class="stock-controls">
                                    <button class="stock-btn decrease" data-id="<?php echo $producto_id; ?>" data-accion="restar" onclick="actualizarStock(<?php echo $producto_id; ?>, 'restar')" <?php echo $producto['cantidad'] <= 0 ? 'disabled' : ''; ?>>
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <button class="stock-btn increase" data-id="<?php echo $producto_id; ?>" data-accion="sumar" onclick="actualizarStock(<?php echo $producto_id; ?>, 'sumar')">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <?php endif; ?>
                            </value>
                        </div>

                        <div class="detail-group">
                            <label>Estado</label>
                            <value class="estado estado-<?php echo strtolower($producto['estado']); ?>">
                                <?php echo htmlspecialchars($producto['estado']); ?>
                            </value>
                        </div>

                        <div class="detail-group">
                            <label>Categoría</label>
                            <value>
                                <a href="listar.php?categoria_id=<?php echo $producto['categoria_id']; ?>" class="link-categoria">
                                    <i class="fas fa-tag"></i>
                                    <?php echo htmlspecialchars($producto['categoria_nombre']); ?>
                                </a>
                            </value>
                        </div>

                        <div class="detail-group">
                            <label>Almacén</label>
                            <value>
                                <a href="../almacenes/ver-almacen.php?id=<?php echo $producto['almacen_id']; ?>" class="link-almacen">
                                    <i class="fas fa-warehouse"></i>
                                    <?php echo htmlspecialchars($producto['almacen_nombre']); ?>
                                </a>
                            </value>
                        </div>

                        <?php if (!empty($producto['observaciones'])): ?>
                        <div class="detail-group full-width">
                            <label>Observaciones</label>
                            <value><?php echo nl2br(htmlspecialchars($producto['observaciones'])); ?></value>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Historial de Movimientos -->
            <div class="movements-card">
                <div class="card-header">
                    <h2>
                        <i class="fas fa-history"></i>
                        Historial de Movimientos
                    </h2>
                </div>
                
                <div class="card-body">
                    <?php if ($movimientos && $movimientos->num_rows > 0): ?>
                        <div class="movements-list">
                            <?php while ($movimiento = $movimientos->fetch_assoc()): ?>
                            <div class="movement-item">
                                <div class="movement-icon">
                                    <i class="fas fa-<?php 
                                        echo $movimiento['tipo'] == 'entrada' ? 'plus-circle' : 
                                             ($movimiento['tipo'] == 'salida' ? 'minus-circle' : 'exchange-alt'); 
                                    ?>"></i>
                                </div>
                                <div class="movement-details">
                                    <div class="movement-description">
                                        <?php echo htmlspecialchars($movimiento['descripcion_movimiento']); ?>
                                    </div>
                                    <div class="movement-meta">
                                        <span class="movement-quantity">
                                            <?php echo $movimiento['cantidad']; ?> unidades
                                        </span>
                                        <span class="movement-user">
                                            por <?php echo htmlspecialchars($movimiento['usuario_nombre'] ?: 'Sistema'); ?>
                                        </span>
                                        <span class="movement-date">
                                            <?php echo date('d/m/Y H:i', strtotime($movimiento['fecha'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="movement-status">
                                    <span class="status-<?php echo $movimiento['estado']; ?>">
                                        <?php echo ucfirst($movimiento['estado']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-message">
                            <i class="fas fa-history"></i>
                            <p>No hay movimientos registrados para este producto.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Panel Lateral -->
        <aside class="sidebar-panel">
            <!-- Solicitudes de Transferencia -->
            <div class="requests-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-paper-plane"></i>
                        Solicitudes de Transferencia
                    </h3>
                </div>
                
                <div class="card-body">
                    <?php if ($solicitudes && $solicitudes->num_rows > 0): ?>
                        <div class="requests-list">
                            <?php while ($solicitud = $solicitudes->fetch_assoc()): ?>
                            <div class="request-item">
                                <div class="request-header">
                                    <span class="request-status status-<?php echo $solicitud['estado']; ?>">
                                        <?php echo ucfirst($solicitud['estado']); ?>
                                    </span>
                                    <span class="request-date">
                                        <?php echo date('d/m', strtotime($solicitud['fecha_solicitud'])); ?>
                                    </span>
                                </div>
                                <div class="request-details">
                                    <div class="transfer-route">
                                        <?php echo htmlspecialchars($solicitud['almacen_origen_nombre']); ?>
                                        <i class="fas fa-arrow-right"></i>
                                        <?php echo htmlspecialchars($solicitud['almacen_destino_nombre']); ?>
                                    </div>
                                    <div class="request-quantity">
                                        <?php echo $solicitud['cantidad']; ?> unidades
                                    </div>
                                    <div class="request-user">
                                        por <?php echo htmlspecialchars($solicitud['usuario_nombre']); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-message">
                            <i class="fas fa-paper-plane"></i>
                            <p>No hay solicitudes de transferencia.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Productos Similares -->
            <div class="similar-products-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-box-open"></i>
                        Productos Similares
                    </h3>
                </div>
                
                <div class="card-body">
                    <?php if ($productos_similares && $productos_similares->num_rows > 0): ?>
                        <div class="similar-list">
                            <?php while ($similar = $productos_similares->fetch_assoc()): ?>
                            <div class="similar-item">
                                <a href="ver-producto.php?id=<?php echo $similar['id']; ?>" class="similar-link">
                                    <div class="similar-info">
                                        <div class="similar-name"><?php echo htmlspecialchars($similar['nombre']); ?></div>
                                        <div class="similar-meta">
                                            <span class="similar-almacen">
                                                <i class="fas fa-warehouse"></i>
                                                <?php echo htmlspecialchars($similar['almacen_nombre']); ?>
                                            </span>
                                            <span class="similar-stock">
                                                <?php echo $similar['cantidad']; ?> unidades
                                            </span>
                                        </div>
                                    </div>
                                    <div class="similar-action">
                                        <i class="fas fa-eye"></i>
                                    </div>
                                </a>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-message">
                            <i class="fas fa-box-open"></i>
                            <p>No hay productos similares disponibles.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Acciones Rápidas -->
            <div class="quick-actions-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-bolt"></i>
                        Acciones Rápidas
                    </h3>
                </div>
                
                <div class="card-body">
                    <div class="quick-actions-list">
                        <a href="listar.php?categoria_id=<?php echo $producto['categoria_id']; ?>" class="quick-action">
                            <i class="fas fa-list"></i>
                            <span>Ver Categoría Completa</span>
                        </a>
                        
                        <a href="../almacenes/ver-almacen.php?id=<?php echo $producto['almacen_id']; ?>" class="quick-action">
                            <i class="fas fa-warehouse"></i>
                            <span>Ver Almacén Completo</span>
                        </a>
                        
                        <?php if ($usuario_rol == 'admin'): ?>
                        <a href="registrar.php?categoria_id=<?php echo $producto['categoria_id']; ?>&almacen_id=<?php echo $producto['almacen_id']; ?>" class="quick-action">
                            <i class="fas fa-plus"></i>
                            <span>Producto Similar</span>
                        </a>
                        <?php endif; ?>
                        
                        <a href="../notificaciones/pendientes.php" class="quick-action">
                            <i class="fas fa-bell"></i>
                            <span>Ver Solicitudes</span>
                            <?php if ($total_pendientes > 0): ?>
                            <span class="action-badge"><?php echo $total_pendientes; ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</main>

<!-- Modal de Transferencia -->
<div id="modalTransferencia" class="modal" role="dialog" aria-labelledby="modalTitle" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">
                <i class="fas fa-paper-plane"></i>
                Transferir Producto
            </h2>
            <button class="modal-close" onclick="cerrarModal()" aria-label="Cerrar modal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="formTransferencia" method="POST" action="procesar_formulario.php">
            <div class="modal-body">
                <input type="hidden" id="producto_id_modal" name="producto_id">
                <input type="hidden" id="almacen_origen_modal" name="almacen_origen">
                
                <div class="transfer-info">
                    <div class="product-summary">
                        <div class="product-icon-modal">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="product-details-modal">
                            <h3 id="producto_nombre_modal"></h3>
                            <p>Stock disponible: <span id="stock_disponible_modal" class="stock-highlight"></span></p>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="cantidad_modal" class="form-label">
                        <i class="fas fa-sort-numeric-up"></i>
                        Cantidad a transferir
                    </label>
                    <div class="quantity-input">
                        <button type="button" class="qty-btn minus" onclick="adjustQuantity(-1)">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" id="cantidad_modal" name="cantidad" min="1" value="1" class="qty-input">
                        <button type="button" class="qty-btn plus" onclick="adjustQuantity(1)">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="almacen_destino_modal" class="form-label">
                        <i class="fas fa-warehouse"></i>
                        Almacén de destino
                    </label>
                    <select id="almacen_destino_modal" name="almacen_destino" required class="form-select">
                        <option value="">Seleccione un almacén</option>
                        <?php
                        // Obtener lista de almacenes para el modal
                        $sql_almacenes_modal = "SELECT id, nombre FROM almacenes WHERE id != ? ORDER BY nombre";
                        $stmt_almacenes_modal = $conn->prepare($sql_almacenes_modal);
                        $stmt_almacenes_modal->bind_param("i", $producto['almacen_id']);
                        $stmt_almacenes_modal->execute();
                        $result_almacenes_modal = $stmt_almacenes_modal->get_result();
                        
                        while ($almacen_modal = $result_almacenes_modal->fetch_assoc()) {
                            echo "<option value='{$almacen_modal['id']}'>{$almacen_modal['nombre']}</option>";
                        }
                        $stmt_almacenes_modal->close();
                        ?>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-modal btn-cancel" onclick="cerrarModal()">
                    <i class="fas fa-times"></i>
                    Cancelar
                </button>
                <button type="submit" class="btn-modal btn-confirm">
                    <i class="fas fa-paper-plane"></i>
                    Transferir Producto
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Container for dynamic notifications -->
<div id="notificaciones-container" role="alert" aria-live="polite"></div>

<!-- JavaScript -->
<script src="../assets/js/universal-confirmation-system.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Elementos principales
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const submenuContainers = document.querySelectorAll('.submenu-container');
    
    // Variables para el modal
    let maxStock = 0;
    
    // Toggle del menú móvil
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            if (mainContent) {
                mainContent.classList.toggle('with-sidebar');
            }
            
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
        const chevron = link?.querySelector('.fa-chevron-down');
        
        if (link && submenu) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
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
                
                submenu.classList.toggle('activo');
                const isExpanded = submenu.classList.contains('activo');
                
                if (chevron) {
                    chevron.style.transform = isExpanded ? 'rotate(180deg)' : 'rotate(0deg)';
                }
                
                link.setAttribute('aria-expanded', isExpanded.toString());
            });
        }
    });
    
    // Mostrar submenú de productos activo por defecto
    const productosSubmenu = submenuContainers[2]?.querySelector('.submenu');
    const productosChevron = submenuContainers[2]?.querySelector('.fa-chevron-down');
    const productosLink = submenuContainers[2]?.querySelector('a');
    
    if (productosSubmenu) {
        productosSubmenu.classList.add('activo');
        if (productosChevron) {
            productosChevron.style.transform = 'rotate(180deg)';
        }
        if (productosLink) {
            productosLink.setAttribute('aria-expanded', 'true');
        }
    }
    
    // Auto-cerrar alertas
    const alertas = document.querySelectorAll('.alert');
    alertas.forEach(alerta => {
        setTimeout(() => {
            alerta.style.animation = 'slideOutUp 0.5s ease-in-out';
            setTimeout(() => {
                alerta.remove();
            }, 500);
        }, 5000);
    });
    
    // Configurar modal de transferencia
    const modal = document.getElementById('modalTransferencia');
    const form = document.getElementById('formTransferencia');
    
    if (modal && form) {
        // Configurar botones de cerrar
        const closeButtons = modal.querySelectorAll('.modal-close, .btn-cancel');
        closeButtons.forEach(button => {
            button.addEventListener('click', () => cerrarModal());
        });

        // Cerrar modal al hacer clic fuera
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                cerrarModal();
            }
        });

        // Configurar formulario
        form.addEventListener('submit', (e) => enviarFormulario(e));
    }
});

// Función para actualizar stock
function actualizarStock(productoId, accion) {
    const formData = new FormData();
    formData.append('producto_id', productoId);
    formData.append('accion', accion);
    
    const buttons = document.querySelectorAll('.stock-btn');
    buttons.forEach(btn => btn.disabled = true);
    
    fetch('actualizar_cantidad.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const stockElement = document.getElementById('cantidad-actual');
            if (stockElement) {
                stockElement.textContent = parseInt(data.nueva_cantidad).toLocaleString();
                
                const stockValue = stockElement.closest('.stock-value');
                if (stockValue) {
                    stockValue.classList.remove('stock-critical', 'stock-warning', 'stock-good');
                    if (data.nueva_cantidad < 5) {
                        stockValue.classList.add('stock-critical');
                    } else if (data.nueva_cantidad < 10) {
                        stockValue.classList.add('stock-warning');
                    } else {
                        stockValue.classList.add('stock-good');
                    }
                }
                
                stockElement.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    stockElement.style.transform = 'scale(1)';
                }, 200);
            }
            
            mostrarNotificacion(`Stock actualizado: ${data.nueva_cantidad} unidades`, 'exito');
            
        } else {
            mostrarNotificacion(data.message || 'Error al actualizar el stock', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('Error de conexión al actualizar el stock', 'error');
    })
    .finally(() => {
        buttons.forEach(btn => {
            btn.disabled = false;
            if (btn.dataset.accion === 'restar') {
                const stockElement = document.getElementById('cantidad-actual');
                if (stockElement) {
                    const currentStock = parseInt(stockElement.textContent.replace(/,/g, ''));
                    btn.disabled = currentStock <= 0;
                }
            }
        });
    });
}

// Función para abrir modal de transferencia
function abrirModalTransferencia(button) {
    const modal = document.getElementById('modalTransferencia');
    if (!modal) return;

    const datos = {
        id: button.dataset.id,
        nombre: button.dataset.nombre,
        almacen: button.dataset.almacen,
        cantidad: button.dataset.cantidad
    };

    document.getElementById('producto_id_modal').value = datos.id;
    document.getElementById('almacen_origen_modal').value = datos.almacen;
    document.getElementById('producto_nombre_modal').textContent = datos.nombre;
    document.getElementById('stock_disponible_modal').textContent = `${datos.cantidad} unidades`;
    
    const quantityInput = document.getElementById('cantidad_modal');
    quantityInput.value = 1;
    quantityInput.max = datos.cantidad;
    
    document.getElementById('almacen_destino_modal').value = '';
    
    maxStock = parseInt(datos.cantidad);
    
    modal.style.display = 'block';
    modal.setAttribute('aria-hidden', 'false');
    
    setTimeout(() => {
        quantityInput.focus();
    }, 100);
    
    document.body.style.overflow = 'hidden';
}

// Función para cerrar modal
function cerrarModal() {
    const modal = document.getElementById('modalTransferencia');
    if (!modal) return;

    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
    
    document.body.style.overflow = '';
    
    document.getElementById('formTransferencia').reset();
}

// Función para ajustar cantidad
function adjustQuantity(increment) {
    const quantityInput = document.getElementById('cantidad_modal');
    if (!quantityInput) return;

    let currentValue = parseInt(quantityInput.value) || 1;
    let newValue = currentValue + increment;
    
    newValue = Math.max(1, Math.min(newValue, maxStock));
    
    quantityInput.value = newValue;
}

// Función para enviar formulario
async function enviarFormulario(e) {
    e.preventDefault();
    
    const submitButton = e.target.querySelector('.btn-confirm');
    const originalText = submitButton.innerHTML;
    
    const cantidad = parseInt(document.getElementById('cantidad_modal').value);
    const almacenDestino = document.getElementById('almacen_destino_modal').value;
    
    if (!almacenDestino) {
        mostrarNotificacion('Debe seleccionar un almacén de destino', 'error');
        return;
    }
    
    if (cantidad < 1 || cantidad > maxStock) {
        mostrarNotificacion('La cantidad no es válida', 'error');
        return;
    }

    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Transfiriendo...';
    submitButton.disabled = true;

    try {
        const formData = new FormData(e.target);
        
        const response = await fetch('procesar_formulario.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            mostrarNotificacion(data.message, 'exito');
            cerrarModal();
            
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            mostrarNotificacion(data.message || 'Error al solicitar transferencia', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarNotificacion('Error de conexión al solicitar transferencia', 'error');
    } finally {
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
    }
}

// Función para editar producto
function editarProducto(id) {
    window.location.href = `editar.php?id=${id}`;
}

// Función para eliminar producto
async function eliminarProducto(id, nombre) {
    const confirmado = await confirmarEliminacion('Producto', nombre);
    
    if (confirmado) {
        mostrarNotificacion('Eliminando producto...', 'info');
        
        try {
            const response = await fetch('eliminar_producto.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: id })
            });

            const data = await response.json();

            if (data.success) {
                mostrarNotificacion('Producto eliminado correctamente', 'exito');
                
                setTimeout(() => {
                    window.location.href = 'listar.php';
                }, 2000);
            } else {
                mostrarNotificacion(data.message || 'Error al eliminar el producto', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            mostrarNotificacion('Error de conexión al eliminar el producto', 'error');
        }
    }
}

// Función para cerrar sesión
async function manejarCerrarSesion(event) {
    event.preventDefault();
    
    const confirmado = await confirmarCerrarSesion();
    
    if (confirmado) {
        mostrarNotificacion('Cerrando sesión...', 'info', 2000);
        setTimeout(() => {
            window.location.href = '../logout.php';
        }, 1000);
    }
}

// Función para mostrar notificaciones
function mostrarNotificacion(mensaje, tipo = 'info', duracion = 5000) {
    let container = document.getElementById('notificaciones-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notificaciones-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            max-width: 400px;
        `;
        document.body.appendChild(container);
    }

    const iconos = {
        exito: 'fa-check-circle',
        error: 'fa-exclamation-triangle', 
        warning: 'fa-exclamation-circle',
        info: 'fa-info-circle'
    };

    const colores = {
        exito: '#28a745',
        error: '#dc3545',
        warning: '#ffc107', 
        info: '#0a253c'
    };

    const notificacion = document.createElement('div');
    notificacion.className = `notificacion ${tipo}`;
    notificacion.style.cssText = `
        background: white;
        border-left: 5px solid ${colores[tipo] || colores.info};
        padding: 15px 20px;
        margin-bottom: 10px;
        border-radius: 0 8px 8px 0;
        box-shadow: 0 4px 12px rgba(10, 37, 60, 0.15);
        position: relative;
        animation: slideInRight 0.4s ease;
        display: flex;
        align-items: center;
        gap: 12px;
    `;

    notificacion.innerHTML = `
        <i class="fas ${iconos[tipo] || iconos.info}" style="font-size: 20px; color: ${colores[tipo] || colores.info};"></i>
        <span style="flex: 1; color: #0a253c; font-weight: 500;">${mensaje}</span>
        <button class="cerrar" aria-label="Cerrar notificación" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #666; padding: 0;">&times;</button>
    `;

    container.appendChild(notificacion);

    const cerrarBtn = notificacion.querySelector('.cerrar');
    cerrarBtn.addEventListener('click', () => {
        notificacion.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notificacion.remove(), 300);
    });

    if (duracion > 0) {
        setTimeout(() => {
            if (notificacion.parentNode) {
                notificacion.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => notificacion.remove(), 300);
            }
        }, duracion);
    }

    // Agregar animaciones CSS si no existen
    if (!document.getElementById('notification-animations')) {
        const animationStyles = document.createElement('style');
        animationStyles.id = 'notification-animations';
        animationStyles.textContent = `
            @keyframes slideInRight {
                from { opacity: 0; transform: translateX(30px); }
                to { opacity: 1; transform: translateX(0); }
            }
            @keyframes slideOutRight {
                from { opacity: 1; transform: translateX(0); }
                to { opacity: 0; transform: translateX(30px); }
            }
            @keyframes slideOutUp {
                from { opacity: 1; transform: translateY(0); }
                to { opacity: 0; transform: translateY(-20px); }
            }
        `;
        document.head.appendChild(animationStyles);
    }
}
</script>

<style>
@keyframes slideOutUp {
    from { opacity: 1; transform: translateY(0); }
    to { opacity: 0; transform: translateY(-20px); }
}

.estado-nuevo { color: #28a745; }
.estado-usado { color: #ffc107; }
.estado-dañado { color: #dc3545; }

.stock-critical { color: #dc3545; font-weight: bold; }
.stock-warning { color: #ffc107; font-weight: bold; }
.stock-good { color: #28a745; font-weight: bold; }

.status-pendiente { background: #ffc107; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; }
.status-completado { background: #28a745; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; }
.status-rechazado { background: #dc3545; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; }
.status-aprobada { background: #28a745; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; }
.status-rechazada { background: #dc3545; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; }

.stock-controls {
    display: flex;
    gap: 5px;
    margin-top: 10px;
}

.stock-btn {
    width: 30px;
    height: 30px;
    border: none;
    border-radius: 50%;
    background: #0a253c;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.stock-btn:hover:not(:disabled) {
    background: #164463;
    transform: scale(1.1);
}

.stock-btn:disabled {
    background: #ccc;
    cursor: not-allowed;
}
</style>
</body>
</html>