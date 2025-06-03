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

// Obtener filtros de la URL
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

        /* NUEVO: Checkbox de selección de productos */
        .product-selector {
            position: relative;
            margin-bottom: 10px;
        }

        .product-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .product-card.selected {
            border: 2px solid #28a745;
            background: rgba(40, 167, 69, 0.05);
        }

        /* Botón de entregar productos seleccionados */
        .delivery-controls {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border: 1px solid #ddd;
            display: none;
            z-index: 1000;
        }

        .delivery-controls.show {
            display: block;
        }

        .btn-deliver-selected {
            background: #28a745;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-deliver-selected:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);
        }

        .selected-count {
            background: rgba(255,255,255,0.2);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 14px;
        }

        /* Modal de entrega múltiple */
        .modal-entrega-multiple {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
        }

        .modal-content-entrega {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 12px;
            padding: 0;
            max-width: 800px;
            max-height: 90vh;
            overflow: hidden;
            width: 90%;
        }

        .modal-header-entrega {
            background: #0a253c;
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body-entrega {
            padding: 20px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .modal-footer-entrega {
            padding: 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .producto-seleccionado {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 10px;
            background: #f8f9fa;
        }

        .producto-info {
            flex-grow: 1;
        }

        .producto-nombre {
            font-weight: 600;
            color: #0a253c;
        }

        .producto-detalles {
            font-size: 14px;
            color: #666;
            margin-top: 4px;
        }

        .cantidad-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cantidad-input {
            width: 60px;
            text-align: center;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .btn-cantidad {
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
        }

        .btn-cantidad:hover {
            background: #ff6b35;
        }

        .btn-remove-producto {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-remove-producto:hover {
            background: #c82333;
        }

        .form-destinatario {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #0a253c;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .warning-message {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 14px;
            display: none;
        }

        .warning-message.show {
            display: block;
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

        <!-- Products -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Productos" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-boxes"></i> Productos</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <?php if ($usuario_rol == 'admin'): ?>
                <li><a href="registrar.php" role="menuitem"><i class="fas fa-plus"></i> Registrar Producto</a></li>
                <?php endif; ?>
                <li><a href="listar.php" role="menuitem"><i class="fas fa-list"></i> Lista de Productos</a></li>
                <li><a href="categorias.php" role="menuitem"><i class="fas fa-tags"></i> Categorías</a></li>
            </ul>
        </li>

        <!-- Entregas Section -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Entregas" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-hand-holding"></i> Entregas</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li><a href="../entregas/historial.php" role="menuitem"><i class="fas fa-history"></i> Historial de Entregas</a></li>
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
                    Selecciona productos para realizar entregas a usuarios
                </p>
            </div>
            
            <div class="header-actions">
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
        <span class="current">Productos</span>
    </nav>

    <!-- Lista de productos -->
    <section class="products-section">
        <?php if ($result_productos && $result_productos->num_rows > 0): ?>
            <div class="products-grid">
                <?php while ($producto = $result_productos->fetch_assoc()): ?>
                    <div class="product-card" data-producto-id="<?php echo $producto['id']; ?>" 
                         data-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>"
                         data-stock="<?php echo $producto['cantidad']; ?>"
                         data-almacen="<?php echo $producto['almacen_id']; ?>">
                        
                        <!-- Checkbox de selección -->
                        <div class="product-selector">
                            <input type="checkbox" 
                                   class="product-checkbox" 
                                   data-id="<?php echo $producto['id']; ?>"
                                   <?php echo $producto['cantidad'] <= 0 ? 'disabled' : ''; ?>>
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
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-box-open"></i>
                </div>
                <h3>No hay productos registrados</h3>
                <p>No se encontraron productos para mostrar.</p>
            </div>
        <?php endif; ?>
    </section>
</main>

<!-- Controles de entrega flotantes -->
<div class="delivery-controls" id="deliveryControls">
    <div style="margin-bottom: 10px; text-align: center;">
        <small><strong>Productos seleccionados:</strong></small>
        <div class="selected-count" id="selectedCount">0</div>
    </div>
    <button class="btn-deliver-selected" id="btnDeliverSelected">
        <i class="fas fa-hand-holding"></i>
        <span>Entregar Productos</span>
    </button>
</div>

<!-- Modal de Entrega Múltiple -->
<div class="modal-entrega-multiple" id="modalEntregaMultiple">
    <div class="modal-content-entrega">
        <div class="modal-header-entrega">
            <h2>
                <i class="fas fa-hand-holding"></i>
                Entrega de Productos
            </h2>
            <button class="modal-close" onclick="cerrarModalEntrega()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="formEntregaMultiple">
            <div class="modal-body-entrega">
                <h3>Productos a entregar:</h3>
                <div id="productosSeleccionados">
                    <!-- Los productos seleccionados se cargarán aquí -->
                </div>

                <div class="warning-message" id="warningCantidad">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Recomendación:</strong> Para entregas a usuarios, lo recomendado es una unidad por producto seleccionado.
                </div>

                <div class="form-destinatario">
                    <h4><i class="fas fa-user"></i> Datos del Destinatario</h4>
                    
                    <div class="form-group">
                        <label class="form-label" for="nombreDestinatario">
                            <i class="fas fa-user"></i> Nombre Completo *
                        </label>
                        <input type="text" 
                               id="nombreDestinatario" 
                               name="nombre_destinatario" 
                               class="form-control" 
                               placeholder="Ingrese el nombre completo del destinatario"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="dniDestinatario">
                            <i class="fas fa-id-card"></i> DNI *
                        </label>
                        <input type="text" 
                               id="dniDestinatario" 
                               name="dni_destinatario" 
                               class="form-control" 
                               placeholder="12345678"
                               pattern="[0-9]{8}"
                               maxlength="8"
                               required>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer-entrega">
                <button type="button" class="btn-cancel" onclick="cerrarModalEntrega()">
                    <i class="fas fa-times"></i>
                    Cancelar
                </button>
                <button type="submit" class="btn-deliver-selected">
                    <i class="fas fa-check"></i>
                    Confirmar Entrega
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Container for dynamic notifications -->
<div id="notificaciones-container" role="alert" aria-live="polite"></div>

<!-- JavaScript -->
<script>
let productosSeleccionados = [];

document.addEventListener('DOMContentLoaded', function() {
    // Elementos principales del menú
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
            
            const icon = this.querySelector('i');
            if (sidebar.classList.contains('active')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
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
                
                submenuContainers.forEach(otherContainer => {
                    if (otherContainer !== container) {
                        const otherSubmenu = otherContainer.querySelector('.submenu');
                        const otherChevron = otherContainer.querySelector('.fa-chevron-down');
                        
                        if (otherSubmenu && otherSubmenu.classList.contains('activo')) {
                            otherSubmenu.classList.remove('activo');
                            if (otherChevron) {
                                otherChevron.style.transform = 'rotate(0deg)';
                            }
                        }
                    }
                });
                
                submenu.classList.toggle('activo');
                const isExpanded = submenu.classList.contains('activo');
                
                if (chevron) {
                    chevron.style.transform = isExpanded ? 'rotate(180deg)' : 'rotate(0deg)';
                }
            });
        }
    });

    // Event listeners para checkboxes de productos
    document.querySelectorAll('.product-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const productCard = this.closest('.product-card');
            const productId = this.dataset.id;
            
            if (this.checked) {
                productCard.classList.add('selected');
                agregarProductoSeleccionado(productCard);
            } else {
                productCard.classList.remove('selected');
                removerProductoSeleccionado(productId);
            }
            
            actualizarControlsEntrega();
        });
    });

    // Event listener para botón de entregar
    document.getElementById('btnDeliverSelected').addEventListener('click', function() {
        if (productosSeleccionados.length > 0) {
            abrirModalEntrega();
        }
    });

    // Event listener para formulario de entrega
    document.getElementById('formEntregaMultiple').addEventListener('submit', function(e) {
        e.preventDefault();
        procesarEntrega();
    });

    // Validación de DNI en tiempo real
    document.getElementById('dniDestinatario').addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value.length > 8) {
            this.value = this.value.slice(0, 8);
        }
    });

    // Cerrar menú móvil al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
                if (mainContent) {
                    mainContent.classList.remove('with-sidebar');
                }
            }
        }
    });
});

function agregarProductoSeleccionado(productCard) {
    const id = productCard.dataset.productoId;
    const nombre = productCard.dataset.nombre;
    const stock = parseInt(productCard.dataset.stock);
    const almacen = productCard.dataset.almacen;
    
    // Obtener detalles adicionales del producto
    const modelo = productCard.querySelector('.detail-value') ? 
        productCard.querySelector('.detail-item:has(.detail-label:contains("Modelo")) .detail-value')?.textContent || '' : '';
    const color = productCard.querySelector('.detail-item:has(.detail-label:contains("Color")) .detail-value')?.textContent || '';
    const talla = productCard.querySelector('.detail-item:has(.detail-label:contains("Talla")) .detail-value')?.textContent || '';
    
    const producto = {
        id: id,
        nombre: nombre,
        modelo: modelo,
        color: color,
        talla: talla,
        stock: stock,
        almacen: almacen,
        cantidad: 1 // Cantidad por defecto
    };
    
    productosSeleccionados.push(producto);
}

function removerProductoSeleccionado(productId) {
    productosSeleccionados = productosSeleccionados.filter(p => p.id !== productId);
}

function actualizarControlsEntrega() {
    const controls = document.getElementById('deliveryControls');
    const count = document.getElementById('selectedCount');
    
    if (productosSeleccionados.length > 0) {
        controls.classList.add('show');
        count.textContent = productosSeleccionados.length;
    } else {
        controls.classList.remove('show');
    }
}

function abrirModalEntrega() {
    const modal = document.getElementById('modalEntregaMultiple');
    const container = document.getElementById('productosSeleccionados');
    
    // Limpiar container
    container.innerHTML = '';
    
    // Agregar cada producto seleccionado
    productosSeleccionados.forEach(producto => {
        const productoDiv = document.createElement('div');
        productoDiv.className = 'producto-seleccionado';
        productoDiv.innerHTML = `
            <div class="producto-info">
                <div class="producto-nombre">${producto.nombre}</div>
                <div class="producto-detalles">
                    ${producto.modelo ? 'Modelo: ' + producto.modelo + ' | ' : ''}
                    ${producto.color ? 'Color: ' + producto.color + ' | ' : ''}
                    ${producto.talla ? 'Talla: ' + producto.talla + ' | ' : ''}
                    Stock: ${producto.stock}
                </div>
            </div>
            <div class="cantidad-control">
                <button type="button" class="btn-cantidad" onclick="cambiarCantidad('${producto.id}', -1)">
                    <i class="fas fa-minus"></i>
                </button>
                <input type="number" 
                       class="cantidad-input" 
                       value="${producto.cantidad}" 
                       min="1" 
                       max="${producto.stock}"
                       data-id="${producto.id}"
                       onchange="actualizarCantidad('${producto.id}', this.value)">
                <button type="button" class="btn-cantidad" onclick="cambiarCantidad('${producto.id}', 1)">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            <button type="button" class="btn-remove-producto" onclick="removerDelModal('${producto.id}')">
                <i class="fas fa-trash"></i>
            </button>
        `;
        container.appendChild(productoDiv);
    });
    
    modal.style.display = 'block';
}

function cerrarModalEntrega() {
    const modal = document.getElementById('modalEntregaMultiple');
    modal.style.display = 'none';
    
    // Limpiar formulario
    document.getElementById('nombreDestinatario').value = '';
    document.getElementById('dniDestinatario').value = '';
    document.getElementById('warningCantidad').classList.remove('show');
}

function cambiarCantidad(productId, cambio) {
    const producto = productosSeleccionados.find(p => p.id === productId);
    if (!producto) return;
    
    const nuevaCantidad = producto.cantidad + cambio;
    
    if (nuevaCantidad >= 1 && nuevaCantidad <= producto.stock) {
        producto.cantidad = nuevaCantidad;
        
        // Actualizar input
        const input = document.querySelector(`input[data-id="${productId}"]`);
        if (input) input.value = nuevaCantidad;
        
        // Mostrar/ocultar advertencia
        verificarAdvertencia();
    }
}

function actualizarCantidad(productId, nuevaCantidad) {
    const producto = productosSeleccionados.find(p => p.id === productId);
    if (!producto) return;
    
    nuevaCantidad = parseInt(nuevaCantidad);
    
    if (nuevaCantidad >= 1 && nuevaCantidad <= producto.stock) {
        producto.cantidad = nuevaCantidad;
        verificarAdvertencia();
    }
}

function removerDelModal(productId) {
    // Remover del array
    productosSeleccionados = productosSeleccionados.filter(p => p.id !== productId);
    
    // Desmarcar checkbox
    const checkbox = document.querySelector(`input[data-id="${productId}"]`);
    if (checkbox) {
        checkbox.checked = false;
        checkbox.closest('.product-card').classList.remove('selected');
    }
    
    // Si no quedan productos, cerrar modal
    if (productosSeleccionados.length === 0) {
        cerrarModalEntrega();
        actualizarControlsEntrega();
        return;
    }
    
    // Recargar modal
    abrirModalEntrega();
    actualizarControlsEntrega();
}

function verificarAdvertencia() {
    const warning = document.getElementById('warningCantidad');
    const hayMasDeUno = productosSeleccionados.some(p => p.cantidad > 1);
    
    if (hayMasDeUno) {
        warning.classList.add('show');
    } else {
        warning.classList.remove('show');
    }
}

function procesarEntrega() {
    const nombre = document.getElementById('nombreDestinatario').value.trim();
    const dni = document.getElementById('dniDestinatario').value.trim();
    
    if (!nombre || !dni) {
        mostrarNotificacion('Complete todos los campos obligatorios', 'error');
        return;
    }
    
    if (dni.length !== 8) {
        mostrarNotificacion('El DNI debe tener exactamente 8 dígitos', 'error');
        return;
    }
    
    if (productosSeleccionados.length === 0) {
        mostrarNotificacion('Debe seleccionar al menos un producto', 'error');
        return;
    }
    
    // Preparar datos para envío
    const formData = new FormData();
    formData.append('nombre_destinatario', nombre);
    formData.append('dni_destinatario', dni);
    formData.append('productos', JSON.stringify(productosSeleccionados));
    
    // Mostrar indicador de carga
    const btnSubmit = document.querySelector('#formEntregaMultiple button[type="submit"]');
    const originalText = btnSubmit.innerHTML;
    btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    btnSubmit.disabled = true;
    
    // Enviar datos
    fetch('../entregas/procesar_entrega.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarNotificacion(data.message, 'exito');
            
            // Limpiar selección
            productosSeleccionados = [];
            document.querySelectorAll('.product-checkbox:checked').forEach(cb => {
                cb.checked = false;
                cb.closest('.product-card').classList.remove('selected');
            });
            
            actualizarControlsEntrega();
            cerrarModalEntrega();
            
            // Actualizar stock en las tarjetas
            if (data.productos_actualizados) {
                data.productos_actualizados.forEach(prod => {
                    const stockElement = document.getElementById(`cantidad-${prod.id}`);
                    if (stockElement) {
                        stockElement.textContent = prod.nuevo_stock;
                        
                        // Actualizar clases de stock
                        stockElement.className = 'stock-value';
                        if (prod.nuevo_stock < 5) stockElement.classList.add('stock-critical');
                        else if (prod.nuevo_stock < 10) stockElement.classList.add('stock-warning');
                        else stockElement.classList.add('stock-good');
                        
                        // Deshabilitar checkbox si no hay stock
                        if (prod.nuevo_stock <= 0) {
                            const checkbox = document.querySelector(`input[data-id="${prod.id}"]`);
                            if (checkbox) checkbox.disabled = true;
                        }
                    }
                });
            }
            
        } else {
            mostrarNotificacion(data.error || 'Error al procesar la entrega', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('Error de conexión. Intente nuevamente.', 'error');
    })
    .finally(() => {
        btnSubmit.innerHTML = originalText;
        btnSubmit.disabled = false;
    });
}

// Función para mostrar notificaciones
function mostrarNotificacion(mensaje, tipo = 'info', duracion = 5000) {
    const container = document.getElementById('notificaciones-container');
    const notificacion = document.createElement('div');
    notificacion.className = `notificacion ${tipo}`;
    
    const iconos = {
        'exito': 'fas fa-check-circle',
        'error': 'fas fa-exclamation-circle', 
        'info': 'fas fa-info-circle',
        'warning': 'fas fa-exclamation-triangle'
    };
    
    notificacion.innerHTML = `
        <i class="${iconos[tipo] || iconos['info']}"></i>
        <span>${mensaje}</span>
        <button class="cerrar" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(notificacion);
    
    if (duracion > 0) {
        setTimeout(() => {
            if (notificacion.parentElement) {
                notificacion.style.animation = 'slideOutRight 0.3s ease-in';
                setTimeout(() => notificacion.remove(), 300);
            }
        }, duracion);
    }
}

// Funciones adicionales para compatibilidad
function editarProducto(id) {
    window.location.href = `editar.php?id=${id}`;
}

function eliminarProducto(id, nombre) {
    if (confirm(`¿Está seguro de que desea eliminar el producto "${nombre}"?`)) {
        // Implementar eliminación
        console.log('Eliminar producto:', id);
    }
}

function verProducto(id) {
    window.location.href = `ver-producto.php?id=${id}`;
}

function abrirModalEnvio(btn) {
    // Función para transferencias (mantener funcionalidad existente)
    console.log('Abrir modal de transferencia para:', btn.dataset.id);
}

// Función para cerrar sesión
async function manejarCerrarSesion(event) {
    event.preventDefault();
    
    if (confirm('¿Está seguro de que desea cerrar sesión?')) {
        mostrarNotificacion('Cerrando sesión...', 'info', 2000);
        
        setTimeout(() => {
            window.location.href = '../logout.php';
        }, 1000);
    }
}
</script>

</body>
</html>