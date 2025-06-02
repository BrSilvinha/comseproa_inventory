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

// Obtener historial de movimientos del producto
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
    
    <!-- CSS específico para ver producto - USAR ESTE EN LUGAR DEL ORIGINAL -->
    <style>
        /* CSS EMBEBIDO PARA EVITAR CONFLICTOS */
        .productos-ver-page .content { margin-left: 0; padding: 20px; background-color: #f7fafc; }
        @media (min-width: 768px) { .productos-ver-page .content { margin-left: 250px; padding: 30px; } }
        
        .productos-ver-page .product-header { background: linear-gradient(135deg, #0a253c 0%, #164463 100%); color: white; border-radius: 8px; margin-bottom: 30px; padding: 30px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .productos-ver-page .header-content { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px; }
        .productos-ver-page .product-info { display: flex; align-items: flex-start; gap: 20px; flex: 1; }
        .productos-ver-page .product-icon { background: rgba(255, 255, 255, 0.2); width: 80px; height: 80px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 2rem; }
        .productos-ver-page .product-details h1 { font-size: 2rem; font-weight: 700; margin-bottom: 10px; }
        .productos-ver-page .product-meta { display: flex; flex-wrap: wrap; gap: 15px; margin-top: 10px; }
        .productos-ver-page .product-meta span { background: rgba(255, 255, 255, 0.2); padding: 6px 12px; border-radius: 20px; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; }
        
        .productos-ver-page .header-actions { display: flex; flex-wrap: wrap; gap: 10px; }
        .productos-ver-page .btn-action { padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease; font-size: 0.9rem; }
        .productos-ver-page .btn-edit { background: #ffc107; color: #0a253c; }
        .productos-ver-page .btn-edit:hover { background: #e0a800; transform: translateY(-2px); }
        .productos-ver-page .btn-delete { background: #dc3545; color: white; }
        .productos-ver-page .btn-delete:hover { background: #c82333; transform: translateY(-2px); }
        .productos-ver-page .btn-transfer { background: #28a745; color: white; }
        .productos-ver-page .btn-transfer:hover { background: #218838; transform: translateY(-2px); }
        .productos-ver-page .btn-back { background: rgba(255, 255, 255, 0.2); color: white; border: 1px solid rgba(255, 255, 255, 0.3); }
        
        .productos-ver-page .breadcrumb { background: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); margin-top: 20px; font-size: 0.9rem; }
        .productos-ver-page .breadcrumb a { color: #2c5282; text-decoration: none; }
        .productos-ver-page .breadcrumb span { margin: 0 8px; color: #718096; }
        .productos-ver-page .breadcrumb .current { color: #2d3748; font-weight: 600; }
        
        .productos-ver-page .main-content-grid { display: grid; grid-template-columns: 1fr; gap: 30px; }
        @media (min-width: 1200px) { .productos-ver-page .main-content-grid { grid-template-columns: 2fr 1fr; } }
        
        .productos-ver-page .details-card, .productos-ver-page .movements-card { background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); margin-bottom: 20px; }
        .productos-ver-page .card-header { background: #f8f9fa; border-bottom: 1px solid #e2e8f0; padding: 20px; }
        .productos-ver-page .card-header h2, .productos-ver-page .card-header h3 { margin: 0; color: #2d3748; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .productos-ver-page .card-body { padding: 25px; }
        
        .productos-ver-page .details-grid { display: grid; grid-template-columns: 1fr; gap: 20px; }
        @media (min-width: 768px) { .productos-ver-page .details-grid { grid-template-columns: repeat(2, 1fr); } }
        .productos-ver-page .detail-group { display: flex; flex-direction: column; gap: 8px; }
        .productos-ver-page .detail-group.full-width { grid-column: 1 / -1; }
        .productos-ver-page .detail-group label { font-weight: 600; color: #4a5568; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .productos-ver-page .detail-group value { color: #2d3748; font-size: 1rem; font-weight: 500; padding: 10px 0; border-bottom: 1px solid #e2e8f0; display: block; }
        
        /* STOCK CONTROLS - MUY IMPORTANTE */
        .productos-ver-page .stock-value { display: flex !important; align-items: center !important; gap: 15px !important; font-size: 1.2rem !important; font-weight: 700 !important; }
        .productos-ver-page .stock-critical { color: #dc3545 !important; }
        .productos-ver-page .stock-warning { color: #ffc107 !important; }
        .productos-ver-page .stock-good { color: #28a745 !important; }
        .productos-ver-page .stock-controls { display: flex !important; gap: 8px !important; align-items: center !important; margin-top: 10px !important; }
        .productos-ver-page .stock-btn { width: 35px !important; height: 35px !important; border: none !important; border-radius: 50% !important; background: #0a253c !important; color: white !important; cursor: pointer !important; display: flex !important; align-items: center !important; justify-content: center !important; transition: all 0.3s ease !important; font-size: 0.9rem !important; }
        .productos-ver-page .stock-btn:hover:not(:disabled) { background: #164463 !important; transform: scale(1.1) !important; }
        .productos-ver-page .stock-btn:disabled { background: #718096 !important; cursor: not-allowed !important; opacity: 0.5 !important; }
        .productos-ver-page .stock-btn.increase { background: #28a745 !important; }
        .productos-ver-page .stock-btn.increase:hover:not(:disabled) { background: #218838 !important; }
        .productos-ver-page .stock-btn.decrease { background: #dc3545 !important; }
        .productos-ver-page .stock-btn.decrease:hover:not(:disabled) { background: #c82333 !important; }
        
        .productos-ver-page .estado { padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; display: inline-flex; align-items: center; gap: 6px; }
        .productos-ver-page .estado-nuevo { background: rgba(40, 167, 69, 0.1); color: #28a745; }
        .productos-ver-page .estado-usado { background: rgba(255, 193, 7, 0.1); color: #d39e00; }
        .productos-ver-page .estado-dañado { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
        
        .productos-ver-page .link-categoria, .productos-ver-page .link-almacen { color: #2c5282; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; padding: 8px 12px; border-radius: 8px; background: rgba(44, 82, 130, 0.1); transition: all 0.3s ease; }
        .productos-ver-page .link-categoria:hover, .productos-ver-page .link-almacen:hover { background: rgba(44, 82, 130, 0.2); }
        
        .productos-ver-page .movements-list { max-height: 500px; overflow-y: auto; }
        .productos-ver-page .movement-item { display: flex; align-items: center; gap: 15px; padding: 15px 20px; border-bottom: 1px solid #e2e8f0; }
        .productos-ver-page .movement-item:last-child { border-bottom: none; }
        .productos-ver-page .movement-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
        .productos-ver-page .movement-details { flex: 1; }
        .productos-ver-page .movement-description { font-weight: 600; color: #2d3748; margin-bottom: 5px; }
        .productos-ver-page .movement-meta { display: flex; gap: 15px; font-size: 0.85rem; color: #718096; }
        .productos-ver-page .movement-status span { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .productos-ver-page .status-completado { background: #28a745; color: white; }
        .productos-ver-page .status-pendiente { background: #ffc107; color: #0a253c; }
        
        .productos-ver-page .sidebar-panel { display: flex; flex-direction: column; gap: 20px; }
        .productos-ver-page .requests-card, .productos-ver-page .similar-products-card, .productos-ver-page .quick-actions-card { background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); }
        
        /* MODAL */
        .productos-ver-page .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(10, 37, 60, 0.8); display: none; justify-content: center; align-items: center; z-index: 10000; }
        .productos-ver-page .modal-content { background: white; border-radius: 8px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3); max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; }
        .productos-ver-page .modal-header { background: #0a253c; color: white; padding: 20px 25px; display: flex; justify-content: space-between; align-items: center; }
        .productos-ver-page .modal-header h2 { margin: 0; font-size: 1.3rem; display: flex; align-items: center; gap: 10px; }
        .productos-ver-page .modal-close { background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; padding: 5px; border-radius: 50%; }
        .productos-ver-page .modal-body { padding: 25px; }
        .productos-ver-page .modal-footer { background: #f8f9fa; padding: 20px 25px; display: flex; gap: 12px; justify-content: flex-end; }
        
        .productos-ver-page .form-group { margin-bottom: 20px; }
        .productos-ver-page .form-label { display: block; margin-bottom: 8px; font-weight: 600; color: #4a5568; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
        .productos-ver-page .form-select, .productos-ver-page .qty-input { width: 100%; padding: 12px 15px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem; transition: all 0.3s ease; }
        .productos-ver-page .form-select:focus, .productos-ver-page .qty-input:focus { outline: none; border-color: #2c5282; }
        .productos-ver-page .quantity-input { display: flex; align-items: center; gap: 12px; background: #f8f9fa; padding: 8px; border-radius: 8px; }
        .productos-ver-page .qty-btn { width: 40px; height: 40px; border: none; border-radius: 50%; background: #2c5282; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; }
        .productos-ver-page .qty-btn:hover { background: #0a253c; }
        .productos-ver-page .qty-btn.minus { background: #dc3545; }
        .productos-ver-page .qty-btn.plus { background: #28a745; }
        .productos-ver-page .qty-input { text-align: center; font-weight: 600; font-size: 1.1rem; min-width: 80px; }
        
        .productos-ver-page .btn-modal { padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px; }
        .productos-ver-page .btn-cancel { background: #f8f9fa; color: #2d3748; border: 1px solid #e2e8f0; }
        .productos-ver-page .btn-confirm { background: #28a745; color: white; }
        
        .productos-ver-page .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; font-weight: 500; }
        .productos-ver-page .alert.success { background: rgba(40, 167, 69, 0.1); color: #28a745; border-left: 4px solid #28a745; }
        .productos-ver-page .alert.error { background: rgba(220, 53, 69, 0.1); color: #dc3545; border-left: 4px solid #dc3545; }
        
        .productos-ver-page .empty-message { text-align: center; padding: 40px 20px; color: #718096; }
        .productos-ver-page .empty-message i { font-size: 3rem; margin-bottom: 15px; opacity: 0.5; }
        
        @media (max-width: 768px) {
            .productos-ver-page .content { margin-left: 0; padding: 15px; }
            .productos-ver-page .header-content { flex-direction: column; text-align: center; }
            .productos-ver-page .product-info { flex-direction: column; align-items: center; }
            .productos-ver-page .main-content-grid { grid-template-columns: 1fr; }
            .productos-ver-page .details-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body data-producto-id="<?php echo $producto_id; ?>" class="productos-ver-page">

<!-- Botón de hamburguesa para dispositivos móviles -->
<button class="menu-toggle" id="menuToggle" aria-label="Abrir menú de navegación">
    <i class="fas fa-bars"></i>
</button>

<!-- Menú Lateral -->
<nav class="sidebar" id="sidebar" role="navigation" aria-label="Menú principal">
    <h2>GRUPO SEAL</h2>
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
                                    <button class="stock-btn decrease" 
                                            data-id="<?php echo $producto_id; ?>" 
                                            data-accion="restar" 
                                            <?php echo $producto['cantidad'] <= 0 ? 'disabled' : ''; ?>
                                            title="Reducir stock">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <button class="stock-btn increase" 
                                            data-id="<?php echo $producto_id; ?>" 
                                            data-accion="sumar"
                                            title="Aumentar stock">
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
<script src="../assets/js/productos-ver.js"></script>
</body>
</html>