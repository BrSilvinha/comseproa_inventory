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
    <link rel="stylesheet" href="../assets/css/productos-listar.css">
    
    <!-- Estilos adicionales para los controles de stock -->
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
    </style>
</head>
<body data-user-role="<?php echo htmlspecialchars($usuario_rol); ?>" data-almacen-id="<?php echo $filtro_almacen_id ?: $usuario_almacen_id; ?>">

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
                <li class="active"><a href="listar.php" role="menuitem"><i class="fas fa-list"></i> Lista de Productos</a></li>
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
    <section class="products-section">
        <?php if ($result_productos && $result_productos->num_rows > 0): ?>
            <div class="products-grid">
                <?php while ($producto = $result_productos->fetch_assoc()): ?>
                    <div class="product-card" data-producto-id="<?php echo $producto['id']; ?>">
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

<!-- Modal de Transferencia de Producto -->
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

<!-- JavaScript SOLO el archivo externo - SIN script adicional -->
<script src="../assets/js/productos-listar.js"></script>

</body>
</html>