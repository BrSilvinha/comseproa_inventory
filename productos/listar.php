<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

session_regenerate_id(true);
require_once "../config/database.php";

// Obtener información del usuario
$user_name = $_SESSION["user_name"] ?? "Usuario";
$usuario_rol = $_SESSION["user_role"] ?? "usuario";
$usuario_almacen_id = $_SESSION["almacen_id"] ?? null;

// NUEVO: Obtener filtros de la URL
$filtro_almacen_id = isset($_GET['almacen_id']) ? (int)$_GET['almacen_id'] : null;
$filtro_categoria_id = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : null;
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

// Verificar permisos si hay filtro de almacén
if ($filtro_almacen_id && $usuario_rol != 'admin' && $usuario_almacen_id != $filtro_almacen_id) {
    $_SESSION['error'] = "No tienes permiso para ver productos de este almacén";
    header("Location: ../almacenes/listar.php");
    exit();
}

// Obtener información del almacén (si hay filtro)
$almacen_info = null;
if ($filtro_almacen_id) {
    $sql_almacen = "SELECT * FROM almacenes WHERE id = ?";
    $stmt = $conn->prepare($sql_almacen);
    $stmt->bind_param("i", $filtro_almacen_id);
    $stmt->execute();
    $almacen_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Obtener información de la categoría (si hay filtro)
$categoria_info = null;
if ($filtro_categoria_id) {
    $sql_categoria = "SELECT * FROM categorias WHERE id = ?";
    $stmt = $conn->prepare($sql_categoria);
    $stmt->bind_param("i", $filtro_categoria_id);
    $stmt->execute();
    $categoria_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Construir consulta con filtros
$sql_base = "SELECT p.*, c.nombre as categoria_nombre, a.nombre as almacen_nombre 
             FROM productos p 
             JOIN categorias c ON p.categoria_id = c.id 
             JOIN almacenes a ON p.almacen_id = a.id";

$where_conditions = [];
$params = [];
$param_types = "";

// Aplicar filtro de almacén
if ($filtro_almacen_id) {
    $where_conditions[] = "p.almacen_id = ?";
    $params[] = $filtro_almacen_id;
    $param_types .= "i";
} elseif ($usuario_rol != 'admin' && $usuario_almacen_id) {
    // Si no es admin, solo mostrar productos de su almacén
    $where_conditions[] = "p.almacen_id = ?";
    $params[] = $usuario_almacen_id;
    $param_types .= "i";
}

// Aplicar filtro de categoría
if ($filtro_categoria_id) {
    $where_conditions[] = "p.categoria_id = ?";
    $params[] = $filtro_categoria_id;
    $param_types .= "i";
}

// Aplicar búsqueda
if (!empty($busqueda)) {
    $where_conditions[] = "(p.nombre LIKE ? OR p.modelo LIKE ? OR p.color LIKE ?)";
    $busqueda_param = "%$busqueda%";
    $params = array_merge($params, [$busqueda_param, $busqueda_param, $busqueda_param]);
    $param_types .= "sss";
}

// Construir consulta final
if (!empty($where_conditions)) {
    $sql_productos = $sql_base . " WHERE " . implode(" AND ", $where_conditions) . " ORDER BY p.nombre";
} else {
    $sql_productos = $sql_base . " ORDER BY p.nombre";
}

// Ejecutar consulta
if (!empty($params)) {
    $stmt = $conn->prepare($sql_productos);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result_productos = $stmt->get_result();
    $stmt->close();
} else {
    $result_productos = $conn->query($sql_productos);
}

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
    <title>
        <?php if ($almacen_info): ?>
            Inventario - <?php echo htmlspecialchars($almacen_info['nombre']); ?>
        <?php elseif ($categoria_info): ?>
            Productos - <?php echo htmlspecialchars($categoria_info['nombre']); ?>
        <?php else: ?>
            Lista de Productos
        <?php endif; ?>
        - COMSEPROA
    </title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Lista de productos del sistema COMSEPROA">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- CSS específico para listar productos -->
    <link rel="stylesheet" href="../assets/css/listar-usuarios.css">
    <link rel="stylesheet" href="../assets/css/productos-listar.css">
    
    <!-- Estilos adicionales para entrega múltiple -->
    <style>
        /* Estilos para controles de stock */
        .stock-display {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 5px;
        }
        
        .stock-btn {
            width: 28px;
            height: 28px;
            border: none;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 12px;
            position: relative;
        }
        
        .stock-btn:hover:not(:disabled) {
            background: var(--accent-color);
            transform: scale(1.1);
            box-shadow: 0 2px 8px rgba(10, 37, 60, 0.3);
        }
        
        .stock-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .stock-btn.loading {
            pointer-events: none;
        }
        
        .stock-btn.loading i {
            animation: spin 1s linear infinite;
        }
        
        .stock-value {
            font-weight: 600;
            font-size: 16px;
            min-width: 40px;
            text-align: center;
            padding: 4px 8px;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
        }
        
        .stock-critical {
            color: #dc3545;
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        
        .stock-warning {
            color: #ffc107;
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
        }
        
        .stock-good {
            color: #28a745;
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .stock-hint {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 5px;
            opacity: 0.7;
            font-size: 11px;
            color: var(--text-muted);
        }
        
        .stock-hint i {
            font-size: 10px;
        }
        
        .admin-hint {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 12px;
            background: rgba(10, 37, 60, 0.1);
            border-radius: 6px;
            color: var(--primary-color);
            border: 1px solid rgba(10, 37, 60, 0.2);
            margin-left: 10px;
        }
        
        .admin-hint i {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .admin-hint small {
            font-size: 11px;
            font-weight: 500;
        }

        /* ===== ESTILOS PARA ENTREGA MÚLTIPLE ===== */

        /* Botón de entrega múltiple */
        .btn-entregar-personal {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 10px rgba(40, 167, 69, 0.3);
        }

        .btn-entregar-personal:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
        }

        .btn-entregar-personal.active {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }

        /* Modo selección */
        .modo-seleccion .product-card {
            border: 2px dashed #dee2e6;
            transition: all 0.3s ease;
        }

        .modo-seleccion .product-card:hover {
            border-color: #28a745;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.2);
        }

        .modo-seleccion .product-card.selected {
            border: 2px solid #28a745;
            background: rgba(40, 167, 69, 0.05);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        /* Checkbox de selección */
        .product-selection {
            position: absolute;
            top: 15px;
            left: 15px;
            z-index: 10;
        }

        .selection-checkbox {
            width: 24px;
            height: 24px;
            border: 2px solid #28a745;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .selection-checkbox:hover {
            transform: scale(1.1);
            box-shadow: 0 3px 12px rgba(40, 167, 69, 0.3);
        }

        .selection-checkbox.checked {
            background: #28a745;
            color: white;
        }

        .selection-checkbox i {
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .selection-checkbox.checked i {
            opacity: 1;
        }

        /* Panel de productos seleccionados */
        .carrito-entrega {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            z-index: 1000;
            min-width: 350px;
            max-width: 400px;
            max-height: 500px;
            display: none;
            flex-direction: column;
            border: 2px solid #28a745;
        }

        .carrito-entrega.visible {
            display: flex;
            animation: slideInUp 0.4s ease;
        }

        .carrito-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 20px;
            border-radius: 15px 15px 0 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .carrito-title {
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .carrito-contador {
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .carrito-lista {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
            max-height: 300px;
        }

        .carrito-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 8px;
            border-left: 4px solid #28a745;
        }

        .carrito-item-info {
            flex: 1;
        }

        .carrito-item-nombre {
            font-weight: 600;
            font-size: 14px;
            color: #0a253c;
            margin-bottom: 4px;
        }

        .carrito-item-detalles {
            font-size: 12px;
            color: #666;
        }

        .carrito-item-cantidad {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: 15px;
        }

        .cantidad-control {
            display: flex;
            align-items: center;
            gap: 5px;
            background: white;
            border-radius: 6px;
            padding: 4px;
        }

        .cantidad-btn {
            width: 24px;
            height: 24px;
            border: none;
            background: #28a745;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            transition: background 0.2s ease;
        }

        .cantidad-btn:hover {
            background: #1e7e34;
        }

        .cantidad-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .cantidad-input {
            width: 40px;
            text-align: center;
            border: none;
            font-weight: 600;
            font-size: 12px;
            background: transparent;
        }

        .btn-remover {
            background: #dc3545;
            color: white;
            border: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            transition: background 0.2s ease;
            margin-left: 10px;
        }

        .btn-remover:hover {
            background: #c82333;
        }

        .carrito-footer {
            padding: 20px;
            border-top: 1px solid #eee;
            background: #f8f9fa;
            border-radius: 0 0 15px 15px;
        }

        .carrito-resumen {
            margin-bottom: 15px;
            text-align: center;
        }

        .carrito-total {
            font-size: 16px;
            font-weight: 600;
            color: #0a253c;
        }

        .carrito-acciones {
            display: flex;
            gap: 10px;
        }

        .btn-carrito {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-limpiar {
            background: #6c757d;
            color: white;
        }

        .btn-limpiar:hover {
            background: #5a6268;
        }

        .btn-proceder {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .btn-proceder:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        .carrito-vacio {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .carrito-vacio i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* Modal de datos del destinatario */
        .modal-entrega {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            backdrop-filter: blur(5px);
        }

        .modal-entrega.visible {
            display: flex;
        }

        .modal-entrega-content {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            animation: modalEnter 0.3s ease;
        }

        .modal-entrega-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 25px;
            text-align: center;
        }

        .modal-entrega-header h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }

        .modal-entrega-body {
            padding: 30px;
        }

        .resumen-entrega {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 4px solid #28a745;
        }

        .resumen-titulo {
            font-weight: 600;
            color: #0a253c;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .productos-resumen {
            max-height: 200px;
            overflow-y: auto;
        }

        .producto-resumen-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .producto-resumen-item:last-child {
            border-bottom: none;
        }

        .total-unidades {
            background: #28a745;
            color: white;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            margin-top: 15px;
        }

        /* Animaciones */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(100%);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes modalEnter {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Animación de actualización de stock */
        .stock-value.updating {
            animation: pulse 0.5s ease-in-out;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); background: var(--accent-color); color: white; }
            100% { transform: scale(1); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .carrito-entrega {
                bottom: 20px;
                right: 20px;
                left: 20px;
                min-width: auto;
                max-width: none;
            }

            .modal-entrega-content {
                width: 95%;
                margin: 20px;
            }

            .carrito-acciones {
                flex-direction: column;
            }
        }
    </style>
</head>
<body data-user-role="<?php echo htmlspecialchars($usuario_rol); ?>" data-almacen-id="<?php echo $filtro_almacen_id ?: $usuario_almacen_id; ?>">

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
                <li><a href="../entregas/historial.php" role="menuitem"><i class="fas fa-hand-holding"></i> Ver Historial de Entregas</a></li>
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

    <!-- Header dinámico según filtros -->
    <header class="page-header">
        <div class="header-content">
            <div class="header-info">
                <h1>
                    <?php if ($almacen_info && $categoria_info): ?>
                        <i class="fas fa-box-open"></i> <?php echo htmlspecialchars($categoria_info['nombre']); ?>
                        <small>en <?php echo htmlspecialchars($almacen_info['nombre']); ?></small>
                    <?php elseif ($almacen_info): ?>
                        <i class="fas fa-warehouse"></i> Inventario: <?php echo htmlspecialchars($almacen_info['nombre']); ?>
                    <?php elseif ($categoria_info): ?>
                        <i class="fas fa-tag"></i> Productos: <?php echo htmlspecialchars($categoria_info['nombre']); ?>
                    <?php else: ?>
                        <i class="fas fa-boxes"></i> Lista de Productos
                    <?php endif; ?>
                </h1>
                <p class="page-description">
                    <?php if ($almacen_info): ?>
                        Productos registrados en este almacén específico
                    <?php elseif ($categoria_info): ?>
                        Productos de la categoría seleccionada
                    <?php else: ?>
                        Gestiona todos los productos del sistema
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="header-actions">
                <!-- NUEVO: Botón de Entrega a Personal -->
                <button id="btnEntregarPersonal" class="btn-entregar-personal">
                    <i class="fas fa-hand-holding"></i>
                    <span>Entregar a Personal</span>
                </button>

                <?php if ($usuario_rol == 'admin'): ?>
                <a href="registrar.php<?php 
                    $params = [];
                    if ($filtro_almacen_id) $params[] = 'almacen_id=' . $filtro_almacen_id;
                    if ($filtro_categoria_id) $params[] = 'categoria_id=' . $filtro_categoria_id;
                    echo !empty($params) ? '?' . implode('&', $params) : '';
                ?>" class="btn-header btn-primary">
                    <i class="fas fa-plus"></i>
                    <span>Nuevo Producto</span>
                </a>
                
                <div class="admin-hint">
                    <i class="fas fa-info-circle"></i>
                    <small>Usa los botones + y - para ajustar stock</small>
                </div>
                <?php endif; ?>
                
                <?php if ($filtro_almacen_id): ?>
                <a href="../almacenes/ver-almacen.php?id=<?php echo $filtro_almacen_id; ?>" class="btn-header btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    <span>Volver al Almacén</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Breadcrumb dinámico -->
    <nav class="breadcrumb" aria-label="Ruta de navegación">
        <a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a>
        <span><i class="fas fa-chevron-right"></i></span>
        
        <?php if ($almacen_info): ?>
            <a href="../almacenes/listar.php">Almacenes</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <a href="../almacenes/ver-almacen.php?id=<?php echo $filtro_almacen_id; ?>"><?php echo htmlspecialchars($almacen_info['nombre']); ?></a>
            <span><i class="fas fa-chevron-right"></i></span>
        <?php endif; ?>
        
        <?php if ($categoria_info && !$almacen_info): ?>
            <a href="categorias.php">Categorías</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <span class="current"><?php echo htmlspecialchars($categoria_info['nombre']); ?></span>
        <?php else: ?>
            <span class="current">Productos</span>
        <?php endif; ?>
    </nav>

    <!-- Filtros y búsqueda -->
    <section class="filters-section">
        <div class="search-container">
            <form method="GET" class="search-form">
                <!-- Mantener filtros existentes -->
                <?php if ($filtro_almacen_id): ?>
                    <input type="hidden" name="almacen_id" value="<?php echo $filtro_almacen_id; ?>">
                <?php endif; ?>
                <?php if ($filtro_categoria_id): ?>
                    <input type="hidden" name="categoria_id" value="<?php echo $filtro_categoria_id; ?>">
                <?php endif; ?>
                
                <div class="search-input-group">
                    <div class="search-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <input 
                        type="text" 
                        name="busqueda" 
                        placeholder="Buscar productos por nombre, modelo o color..." 
                        value="<?php echo htmlspecialchars($busqueda); ?>"
                        class="search-input"
                    >
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                        Buscar
                    </button>
                </div>
            </form>
        </div>

        <!-- Filtros activos (si existen) -->
        <?php if ($filtro_almacen_id || $filtro_categoria_id || !empty($busqueda)): ?>
        <div class="active-filters">
            <div class="filters-header">
                <span class="filters-title">
                    <i class="fas fa-filter"></i> Filtros activos:
                </span>
                <a href="listar.php" class="clear-all-filters">
                    <i class="fas fa-times-circle"></i> Limpiar todos
                </a>
            </div>
            <div class="filter-tags">
                <?php if ($almacen_info): ?>
                    <span class="filter-tag almacen">
                        <i class="fas fa-warehouse"></i>
                        <?php echo htmlspecialchars($almacen_info['nombre']); ?>
                        <a href="listar.php<?php echo $filtro_categoria_id ? '?categoria_id=' . $filtro_categoria_id : ''; ?>" class="remove-filter">×</a>
                    </span>
                <?php endif; ?>
                
                <?php if ($categoria_info): ?>
                    <span class="filter-tag categoria">
                        <i class="fas fa-tag"></i>
                        <?php echo htmlspecialchars($categoria_info['nombre']); ?>
                        <a href="listar.php<?php echo $filtro_almacen_id ? '?almacen_id=' . $filtro_almacen_id : ''; ?>" class="remove-filter">×</a>
                    </span>
                <?php endif; ?>
                
                <?php if (!empty($busqueda)): ?>
                    <span class="filter-tag busqueda">
                        <i class="fas fa-search"></i>
                        "<?php echo htmlspecialchars($busqueda); ?>"
                        <a href="<?php 
                            $url_params = [];
                            if ($filtro_almacen_id) $url_params[] = 'almacen_id=' . $filtro_almacen_id;
                            if ($filtro_categoria_id) $url_params[] = 'categoria_id=' . $filtro_categoria_id;
                            echo 'listar.php' . (!empty($url_params) ? '?' . implode('&', $url_params) : '');
                        ?>" class="remove-filter">×</a>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <!-- Lista de productos -->
    <section class="products-section" id="productsSection">
        <?php if ($result_productos && $result_productos->num_rows > 0): ?>
            <div class="products-grid">
                <?php while ($producto = $result_productos->fetch_assoc()): ?>
                    <div class="product-card" data-producto-id="<?php echo $producto['id']; ?>">
                        <!-- NUEVO: Checkbox de selección para entrega múltiple -->
                        <div class="product-selection" style="display: none;">
                            <div class="selection-checkbox" data-id="<?php echo $producto['id']; ?>">
                                <i class="fas fa-check"></i>
                            </div>
                        </div>

                        <div class="card-header">
                            <div class="product-info">
                                <h3 class="product-name"><?php echo htmlspecialchars($producto['nombre']); ?></h3>
                                <div class="product-meta">
                                    <span class="categoria">
                                        <i class="fas fa-tag"></i>
                                        <?php echo htmlspecialchars($producto['categoria_nombre']); ?>
                                    </span>
                                    <?php if (!$filtro_almacen_id): ?>
                                    <span class="almacen">
                                        <i class="fas fa-warehouse"></i>
                                        <?php echo htmlspecialchars($producto['almacen_nombre']); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($usuario_rol == 'admin'): ?>
                            <div class="card-actions">
                                <button class="btn-action btn-edit" onclick="editarProducto(<?php echo $producto['id']; ?>)" title="Editar producto">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-action btn-delete" onclick="eliminarProducto(<?php echo $producto['id']; ?>, '<?php echo htmlspecialchars($producto['nombre']); ?>')" title="Eliminar producto">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-body">
                            <div class="product-details">
                                <?php if (!empty($producto['modelo'])): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Modelo:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($producto['modelo']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($producto['color'])): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Color:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($producto['color']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($producto['talla_dimensiones'])): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Talla:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($producto['talla_dimensiones']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Estado:</span>
                                    <span class="detail-value estado-<?php echo strtolower($producto['estado']); ?>">
                                        <?php echo htmlspecialchars($producto['estado']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="stock-section">
                                <div class="stock-info">
                                    <span class="stock-label">Stock disponible:</span>
                                    <div class="stock-display">
                                        <?php if ($usuario_rol == 'admin'): ?>
                                        <button class="stock-btn decrease" data-id="<?php echo $producto['id']; ?>" data-accion="restar" <?php echo $producto['cantidad'] <= 0 ? 'disabled' : ''; ?> title="Reducir stock">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <span class="stock-value <?php 
                                            if ($producto['cantidad'] < 5) echo 'stock-critical';
                                            elseif ($producto['cantidad'] < 10) echo 'stock-warning';
                                            else echo 'stock-good';
                                        ?>" id="cantidad-<?php echo $producto['id']; ?>">
                                            <?php echo number_format($producto['cantidad']); ?>
                                        </span>
                                        
                                        <?php if ($usuario_rol == 'admin'): ?>
                                        <button class="stock-btn increase" data-id="<?php echo $producto['id']; ?>" data-accion="sumar" title="Aumentar stock">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($usuario_rol == 'admin'): ?>
                                    <div class="stock-hint">
                                        <i class="fas fa-info-circle"></i>
                                        <small>Clic en + o - para ajustar</small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-footer">
                            <div class="card-actions-footer">
                                <button class="btn-card btn-view" onclick="verProducto(<?php echo $producto['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                    Ver Detalle
                                </button>
                                
                                <?php if ($producto['cantidad'] > 0): ?>
                                <button class="btn-card btn-transfer" 
                                    data-id="<?php echo $producto['id']; ?>"
                                    data-nombre="<?php echo htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-almacen="<?php echo $filtro_almacen_id ?: $usuario_almacen_id; ?>"
                                    data-cantidad="<?php echo $producto['cantidad']; ?>"
                                    onclick="abrirModalEnvio(this)">
                                    <i class="fas fa-paper-plane"></i>
                                    Transferir
                                </button>
                                <?php else: ?>
                                <button class="btn-card btn-transfer disabled" disabled>
                                    <i class="fas fa-times"></i>
                                    Sin Stock
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Datos para la entrega múltiple -->
                        <script type="application/json" class="product-data">
                        {
                            "id": <?php echo $producto['id']; ?>,
                            "nombre": "<?php echo htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8'); ?>",
                            "almacen": <?php echo $filtro_almacen_id ?: $usuario_almacen_id; ?>,
                            "almacen_nombre": "<?php echo htmlspecialchars($almacen_info ? $almacen_info['nombre'] : $producto['almacen_nombre'], ENT_QUOTES, 'UTF-8'); ?>",
                            "cantidad": <?php echo $producto['cantidad']; ?>,
                            "modelo": "<?php echo htmlspecialchars($producto['modelo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>",
                            "color": "<?php echo htmlspecialchars($producto['color'] ?? '', ENT_QUOTES, 'UTF-8'); ?>",
                            "talla": "<?php echo htmlspecialchars($producto['talla_dimensiones'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        }
                        </script>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-box-open"></i>
                </div>
                <h3>No hay productos registrados</h3>
                <p>
                    <?php if ($filtro_almacen_id && $filtro_categoria_id): ?>
                        No se encontraron productos de esta categoría en este almacén.
                    <?php elseif ($filtro_almacen_id): ?>
                        Este almacén aún no tiene productos registrados.
                    <?php elseif ($filtro_categoria_id): ?>
                        Esta categoría aún no tiene productos registrados.
                    <?php elseif (!empty($busqueda)): ?>
                        No se encontraron productos que coincidan con "<?php echo htmlspecialchars($busqueda); ?>".
                    <?php else: ?>
                        Aún no se han registrado productos en el sistema.
                    <?php endif; ?>
                </p>
                
                <?php if ($usuario_rol == 'admin'): ?>
                <a href="registrar.php<?php 
                    $params = [];
                    if ($filtro_almacen_id) $params[] = 'almacen_id=' . $filtro_almacen_id;
                    if ($filtro_categoria_id) $params[] = 'categoria_id=' . $filtro_categoria_id;
                    echo !empty($params) ? '?' . implode('&', $params) : '';
                ?>" class="btn-primary">
                    <i class="fas fa-plus"></i> Registrar Primer Producto
                </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<!-- NUEVO: Panel flotante del carrito de entrega -->
<div id="carritoEntrega" class="carrito-entrega">
    <div class="carrito-header">
        <div class="carrito-title">
            <i class="fas fa-hand-holding"></i>
            Productos para Entrega
            <span class="carrito-contador">0</span>
        </div>
    </div>
    
    <div class="carrito-lista" id="carritoLista">
        <div class="carrito-vacio">
            <i class="fas fa-hand-holding"></i>
            <p>Selecciona productos para entregar</p>
        </div>
    </div>
    
    <div class="carrito-footer">
        <div class="carrito-resumen">
            <div class="carrito-total">
                Total: <span id="totalUnidades">0</span> unidades
            </div>
        </div>
        
        <div class="carrito-acciones">
            <button class="btn-carrito btn-limpiar" onclick="limpiarCarrito()">
                <i class="fas fa-trash"></i>
                Limpiar
            </button>
            <button class="btn-carrito btn-proceder" onclick="procederEntrega()" disabled>
                <i class="fas fa-user"></i>
                Proceder
            </button>
        </div>
    </div>
</div>

<!-- NUEVO: Modal para datos del destinatario -->
<div id="modalEntrega" class="modal-entrega">
    <div class="modal-entrega-content">
        <div class="modal-entrega-header">
            <h2>
                <i class="fas fa-user"></i>
                Datos del Destinatario
            </h2>
        </div>
        
        <div class="modal-entrega-body">
            <div class="resumen-entrega">
                <div class="resumen-titulo">
                    <i class="fas fa-clipboard-list"></i>
                    Resumen de la Entrega
                </div>
                
                <div class="productos-resumen" id="productosResumen">
                    <!-- Se llena dinámicamente -->
                </div>
                
                <div class="total-unidades">
                    <i class="fas fa-boxes"></i>
                    Total: <span id="totalUnidadesModal">0</span> unidades de <span id="totalTiposModal">0</span> tipo(s) de productos
                </div>
            </div>
            
            <form id="formEntregaPersonal">
                <div class="form-group">
                    <label for="nombreDestinatario" class="form-label">
                        <i class="fas fa-user"></i>
                        Nombre Completo del Destinatario *
                    </label>
                    <input 
                        type="text" 
                        id="nombreDestinatario" 
                        name="nombre_destinatario" 
                        required 
                        class="form-control"
                        placeholder="Ingrese el nombre completo"
                        autocomplete="name"
                    >
                </div>
                
                <div class="form-group">
                    <label for="dniDestinatario" class="form-label">
                        <i class="fas fa-id-card"></i>
                        DNI del Destinatario *
                    </label>
                    <input 
                        type="text" 
                        id="dniDestinatario" 
                        name="dni_destinatario" 
                        required 
                        class="form-control"
                        placeholder="12345678"
                        pattern="[0-9]{8}"
                        maxlength="8"
                        title="Ingrese exactamente 8 dígitos"
                    >
                </div>
            </form>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn-modal btn-cancel" onclick="cerrarModalEntrega()">
                <i class="fas fa-times"></i>
                Cancelar
            </button>
            <button type="button" class="btn-modal btn-confirm" onclick="confirmarEntrega()">
                <i class="fas fa-hand-holding"></i>
                Confirmar Entrega
            </button>
        </div>
    </div>
</div>

<!-- Modal de Transferencia de Producto (existente) -->
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
                <input type="hidden" id="producto_id" name="producto_id">
                <input type="hidden" id="almacen_origen" name="almacen_origen">
                
                <div class="transfer-info">
                    <div class="product-summary">
                        <div class="product-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="product-details-modal">
                            <h3 id="producto_nombre"></h3>
                            <p>Stock disponible: <span id="stock_disponible" class="stock-highlight"></span></p>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="cantidad" class="form-label">
                        <i class="fas fa-sort-numeric-up"></i>
                        Cantidad a transferir
                    </label>
                    <div class="quantity-input">
                        <button type="button" class="qty-btn minus" onclick="adjustQuantity(-1)">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" id="cantidad" name="cantidad" min="1" value="1" class="qty-input">
                        <button type="button" class="qty-btn plus" onclick="adjustQuantity(1)">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="almacen_destino" class="form-label">
                        <i class="fas fa-warehouse"></i>
                        Almacén de destino
                    </label>
                    <select id="almacen_destino" name="almacen_destino" required class="form-select">
                        <option value="">Seleccione un almacén</option>
                        <?php
                        // Obtener lista de almacenes
                        $sql_almacenes = "SELECT id, nombre FROM almacenes ORDER BY nombre";
                        if ($filtro_almacen_id) {
                            $sql_almacenes = "SELECT id, nombre FROM almacenes WHERE id != ? ORDER BY nombre";
                            $stmt_almacenes = $conn->prepare($sql_almacenes);
                            $stmt_almacenes->bind_param("i", $filtro_almacen_id);
                            $stmt_almacenes->execute();
                            $result_almacenes = $stmt_almacenes->get_result();
                        } else {
                            $result_almacenes = $conn->query($sql_almacenes);
                        }
                        
                        while ($almacen_destino = $result_almacenes->fetch_assoc()) {
                            echo "<option value='{$almacen_destino['id']}'>{$almacen_destino['nombre']}</option>";
                        }
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
<script src="../assets/js/productos-listar.js"></script>

<!-- NUEVO: JavaScript para funcionalidad de entrega múltiple -->
<script>
// ===== SISTEMA DE ENTREGA MÚLTIPLE =====

class EntregaMultiple {
    constructor() {
        this.modoSeleccion = false;
        this.productosSeleccionados = new Map();
        this.inicializar();
    }

    inicializar() {
        this.btnEntregarPersonal = document.getElementById('btnEntregarPersonal');
        this.carritoEntrega = document.getElementById('carritoEntrega');
        this.modalEntrega = document.getElementById('modalEntrega');
        
        this.btnEntregarPersonal.addEventListener('click', () => this.toggleModoSeleccion());
        
        // Configurar validación de DNI
        const dniInput = document.getElementById('dniDestinatario');
        if (dniInput) {
            dniInput.addEventListener('input', this.validarDNI.bind(this));
        }
    }

    toggleModoSeleccion() {
        this.modoSeleccion = !this.modoSeleccion;
        
        if (this.modoSeleccion) {
            this.activarModoSeleccion();
        } else {
            this.desactivarModoSeleccion();
        }
    }

    activarModoSeleccion() {
        const productsSection = document.getElementById('productsSection');
        productsSection.classList.add('modo-seleccion');
        
        // Cambiar texto del botón
        this.btnEntregarPersonal.innerHTML = '<i class="fas fa-times"></i><span>Cancelar Selección</span>';
        this.btnEntregarPersonal.classList.add('active');
        
        // Mostrar checkboxes
        document.querySelectorAll('.product-selection').forEach(el => {
            el.style.display = 'block';
        });
        
        // Configurar click handlers
        document.querySelectorAll('.product-card').forEach(card => {
            card.addEventListener('click', this.handleProductClick.bind(this));
        });
        
        // Configurar checkboxes
        document.querySelectorAll('.selection-checkbox').forEach(checkbox => {
            checkbox.addEventListener('click', this.handleCheckboxClick.bind(this));
        });
        
        // Mostrar carrito si hay productos seleccionados
        if (this.productosSeleccionados.size > 0) {
            this.mostrarCarrito();
        }
    }

    desactivarModoSeleccion() {
        const productsSection = document.getElementById('productsSection');
        productsSection.classList.remove('modo-seleccion');
        
        // Restaurar texto del botón
        this.btnEntregarPersonal.innerHTML = '<i class="fas fa-hand-holding"></i><span>Entregar a Personal</span>';
        this.btnEntregarPersonal.classList.remove('active');
        
        // Ocultar checkboxes
        document.querySelectorAll('.product-selection').forEach(el => {
            el.style.display = 'none';
        });
        
        // Remover click handlers
        document.querySelectorAll('.product-card').forEach(card => {
            card.removeEventListener('click', this.handleProductClick);
            card.classList.remove('selected');
        });
        
        // Ocultar carrito
        this.ocultarCarrito();
        
        // Limpiar selecciones
        this.productosSeleccionados.clear();
        document.querySelectorAll('.selection-checkbox').forEach(checkbox => {
            checkbox.classList.remove('checked');
        });
    }

    handleProductClick(e) {
        if (!this.modoSeleccion) return;
        
        // Evitar que se active si se clickea en botones
        if (e.target.closest('.btn-action, .btn-card, .stock-btn')) {
            return;
        }
        
        e.preventDefault();
        e.stopPropagation();
        
        const card = e.currentTarget;
        const productId = card.dataset.productoId;
        const checkbox = card.querySelector('.selection-checkbox');
        
        this.toggleProducto(productId, card, checkbox);
    }

    handleCheckboxClick(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const checkbox = e.currentTarget;
        const productId = checkbox.dataset.id;
        const card = document.querySelector(`[data-producto-id="${productId}"]`);
        
        this.toggleProducto(productId, card, checkbox);
    }

    toggleProducto(productId, card, checkbox) {
        const stockValue = card.querySelector('.stock-value');
        const stock = parseInt(stockValue.textContent.replace(/,/g, ''));
        
        // Verificar si tiene stock
        if (stock <= 0) {
            this.mostrarNotificacion('Este producto no tiene stock disponible', 'warning');
            return;
        }
        
        if (this.productosSeleccionados.has(productId)) {
            // Deseleccionar
            this.productosSeleccionados.delete(productId);
            card.classList.remove('selected');
            checkbox.classList.remove('checked');
        } else {
            // Seleccionar
            const productData = this.extraerDatosProducto(card);
            this.productosSeleccionados.set(productId, {
                ...productData,
                cantidadSeleccionada: 1
            });
            card.classList.add('selected');
            checkbox.classList.add('checked');
        }
        
        this.actualizarCarrito();
    }

    extraerDatosProducto(card) {
        const scriptTag = card.querySelector('.product-data');
        if (scriptTag) {
            try {
                return JSON.parse(scriptTag.textContent);
            } catch (e) {
                console.error('Error parsing product data:', e);
            }
        }
        
        // Fallback manual
        return {
            id: card.dataset.productoId,
            nombre: card.querySelector('.product-name').textContent,
            cantidad: parseInt(card.querySelector('.stock-value').textContent.replace(/,/g, '')),
            almacen: document.body.dataset.almacenId
        };
    }

    actualizarCarrito() {
        if (this.productosSeleccionados.size > 0) {
            this.mostrarCarrito();
            this.renderizarCarrito();
        } else {
            this.ocultarCarrito();
        }
    }

    mostrarCarrito() {
        this.carritoEntrega.classList.add('visible');
    }

    ocultarCarrito() {
        this.carritoEntrega.classList.remove('visible');
    }

    renderizarCarrito() {
        const contador = document.querySelector('.carrito-contador');
        const lista = document.getElementById('carritoLista');
        const totalUnidades = document.getElementById('totalUnidades');
        const btnProceder = document.querySelector('.btn-proceder');
        
        contador.textContent = this.productosSeleccionados.size;
        
        if (this.productosSeleccionados.size === 0) {
            lista.innerHTML = `
                <div class="carrito-vacio">
                    <i class="fas fa-hand-holding"></i>
                    <p>Selecciona productos para entregar</p>
                </div>
            `;
            btnProceder.disabled = true;
            totalUnidades.textContent = '0';
            return;
        }
        
        let html = '';
        let total = 0;
        
        this.productosSeleccionados.forEach((producto, id) => {
            total += producto.cantidadSeleccionada;
            
            html += `
                <div class="carrito-item" data-id="${id}">
                    <div class="carrito-item-info">
                        <div class="carrito-item-nombre">${producto.nombre}</div>
                        <div class="carrito-item-detalles">
                            Stock: ${producto.cantidad.toLocaleString()} | Almacén: ${producto.almacen_nombre || 'N/A'}
                        </div>
                    </div>
                    <div class="carrito-item-cantidad">
                        <div class="cantidad-control">
                            <button class="cantidad-btn" onclick="entregaMultiple.ajustarCantidad('${id}', -1)">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" class="cantidad-input" value="${producto.cantidadSeleccionada}" 
                                   min="1" max="${producto.cantidad}"
                                   onchange="entregaMultiple.cambiarCantidad('${id}', this.value)">
                            <button class="cantidad-btn" onclick="entregaMultiple.ajustarCantidad('${id}', 1)">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <button class="btn-remover" onclick="entregaMultiple.removerProducto('${id}')" title="Remover producto">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        
        lista.innerHTML = html;
        totalUnidades.textContent = total.toLocaleString();
        btnProceder.disabled = false;
    }

    ajustarCantidad(productId, delta) {
        const producto = this.productosSeleccionados.get(productId);
        if (!producto) return;
        
        const nuevaCantidad = producto.cantidadSeleccionada + delta;
        
        if (nuevaCantidad < 1 || nuevaCantidad > producto.cantidad) {
            if (nuevaCantidad > producto.cantidad) {
                this.mostrarNotificacion('No puedes seleccionar más del stock disponible', 'warning');
            }
            return;
        }
        
        producto.cantidadSeleccionada = nuevaCantidad;
        this.renderizarCarrito();
    }

    cambiarCantidad(productId, nuevaCantidad) {
        const producto = this.productosSeleccionados.get(productId);
        if (!producto) return;
        
        nuevaCantidad = parseInt(nuevaCantidad);
        
        if (isNaN(nuevaCantidad) || nuevaCantidad < 1 || nuevaCantidad > producto.cantidad) {
            this.renderizarCarrito(); // Restaurar valor anterior
            return;
        }
        
        producto.cantidadSeleccionada = nuevaCantidad;
        this.renderizarCarrito();
    }

    removerProducto(productId) {
        this.productosSeleccionados.delete(productId);
        
        // Actualizar UI
        const card = document.querySelector(`[data-producto-id="${productId}"]`);
        const checkbox = card?.querySelector('.selection-checkbox');
        
        if (card) card.classList.remove('selected');
        if (checkbox) checkbox.classList.remove('checked');
        
        this.actualizarCarrito();
    }

    limpiarCarrito() {
        // Limpiar selecciones visuales
        document.querySelectorAll('.product-card.selected').forEach(card => {
            card.classList.remove('selected');
        });
        
        document.querySelectorAll('.selection-checkbox.checked').forEach(checkbox => {
            checkbox.classList.remove('checked');
        });
        
        // Limpiar datos
        this.productosSeleccionados.clear();
        this.actualizarCarrito();
    }

    procederEntrega() {
        if (this.productosSeleccionados.size === 0) {
            this.mostrarNotificacion('No hay productos seleccionados', 'warning');
            return;
        }
        
        this.mostrarModalEntrega();
    }

    mostrarModalEntrega() {
        // Preparar resumen
        const productosResumen = document.getElementById('productosResumen');
        const totalUnidadesModal = document.getElementById('totalUnidadesModal');
        const totalTiposModal = document.getElementById('totalTiposModal');
        
        let html = '';
        let totalUnidades = 0;
        
        this.productosSeleccionados.forEach(producto => {
            totalUnidades += producto.cantidadSeleccionada;
            
            html += `
                <div class="producto-resumen-item">
                    <div>
                        <strong>${producto.nombre}</strong>
                        ${producto.modelo ? `<br><small>Modelo: ${producto.modelo}</small>` : ''}
                        ${producto.color ? `<br><small>Color: ${producto.color}</small>` : ''}
                        ${producto.talla ? `<br><small>Talla: ${producto.talla}</small>` : ''}
                    </div>
                    <div>
                        <strong>${producto.cantidadSeleccionada} unidad${producto.cantidadSeleccionada !== 1 ? 'es' : ''}</strong>
                    </div>
                </div>
            `;
        });
        
        productosResumen.innerHTML = html;
        totalUnidadesModal.textContent = totalUnidades.toLocaleString();
        totalTiposModal.textContent = this.productosSeleccionados.size;
        
        // Limpiar formulario
        document.getElementById('formEntregaPersonal').reset();
        
        // Mostrar modal
        this.modalEntrega.classList.add('visible');
        document.body.style.overflow = 'hidden';
        
        // Focus en primer campo
        setTimeout(() => {
            document.getElementById('nombreDestinatario').focus();
        }, 300);
    }

    cerrarModalEntrega() {
        this.modalEntrega.classList.remove('visible');
        document.body.style.overflow = '';
    }

    validarDNI(e) {
        const valor = e.target.value;
        // Solo permitir números
        e.target.value = valor.replace(/[^0-9]/g, '');
        
        const btnConfirmar = document.querySelector('.modal-entrega .btn-confirm');
        if (btnConfirmar) {
            btnConfirmar.disabled = e.target.value.length !== 8;
        }
    }

    async confirmarEntrega() {
        const form = document.getElementById('formEntregaPersonal');
        const formData = new FormData(form);
        
        // Validaciones
        const nombre = formData.get('nombre_destinatario').trim();
        const dni = formData.get('dni_destinatario').trim();
        
        if (!nombre || nombre.length < 3) {
            this.mostrarNotificacion('El nombre debe tener al menos 3 caracteres', 'error');
            return;
        }
        
        if (!dni || dni.length !== 8 || !/^\d{8}$/.test(dni)) {
            this.mostrarNotificacion('El DNI debe tener exactamente 8 dígitos', 'error');
            return;
        }
        
        // Preparar datos de productos
        const productos = Array.from(this.productosSeleccionados.values()).map(p => ({
            id: p.id,
            cantidad: p.cantidadSeleccionada,
            almacen: p.almacen
        }));
        
        formData.append('productos', JSON.stringify(productos));
        
        // Mostrar loading
        const btnConfirmar = document.querySelector('.modal-entrega .btn-confirm');
        const textoOriginal = btnConfirmar.innerHTML;
        btnConfirmar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
        btnConfirmar.disabled = true;
        
        try {
            const response = await fetch('../entregas/Procesar_entrega.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.mostrarNotificacion(data.message, 'exito', 8000);
                this.cerrarModalEntrega();
                this.desactivarModoSeleccion();
                
                // Actualizar stock en la interfaz
                if (data.productos_actualizados) {
                    data.productos_actualizados.forEach(prod => {
                        const stockElement = document.getElementById(`cantidad-${prod.id}`);
                        if (stockElement) {
                            stockElement.textContent = prod.nuevo_stock.toLocaleString();
                            
                            // Actualizar clases de color
                            const stockValue = stockElement.closest('.stock-value') || stockElement;
                            stockValue.classList.remove('stock-critical', 'stock-warning', 'stock-good');
                            
                            if (prod.nuevo_stock < 5) {
                                stockValue.classList.add('stock-critical');
                            } else if (prod.nuevo_stock < 10) {
                                stockValue.classList.add('stock-warning');
                            } else {
                                stockValue.classList.add('stock-good');
                            }
                        }
                    });
                }
                
                // Recargar página después de un momento
                setTimeout(() => {
                    window.location.reload();
                }, 3000);
                
            } else {
                this.mostrarNotificacion(data.message || 'Error al procesar la entrega', 'error');
            }
            
        } catch (error) {
            console.error('Error:', error);
            this.mostrarNotificacion('Error de conexión al procesar la entrega', 'error');
        } finally {
            btnConfirmar.innerHTML = textoOriginal;
            btnConfirmar.disabled = false;
        }
    }

    mostrarNotificacion(mensaje, tipo = 'info', duracion = 5000) {
        // Usar el sistema existente de notificaciones
        if (window.productosListar && window.productosListar.mostrarNotificacion) {
            window.productosListar.mostrarNotificacion(mensaje, tipo, duracion);
        } else {
            // Fallback
            alert(mensaje);
        }
    }
}

// Inicializar sistema de entrega múltiple
let entregaMultiple;

document.addEventListener('DOMContentLoaded', () => {
    entregaMultiple = new EntregaMultiple();
});

// Funciones globales para el carrito
function limpiarCarrito() {
    entregaMultiple.limpiarCarrito();
}

function procederEntrega() {
    entregaMultiple.procederEntrega();
}

function cerrarModalEntrega() {
    entregaMultiple.cerrarModalEntrega();
}

function confirmarEntrega() {
    entregaMultiple.confirmarEntrega();
}

// Cerrar modal con Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        if (entregaMultiple.modalEntrega.classList.contains('visible')) {
            entregaMultiple.cerrarModalEntrega();
        }
    }
});
</script>

</body>
</html>