<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

session_regenerate_id(true);
require_once "../config/database.php";

$user_name = $_SESSION["user_name"] ?? "Usuario";
$usuario_rol = $_SESSION["user_role"] ?? "usuario";
$usuario_almacen_id = $_SESSION["almacen_id"] ?? null;

// Obtener lista de categorías con estadísticas
$sql_categorias = "SELECT c.id, c.nombre, c.descripcion,
                   COUNT(DISTINCT p.id) as total_productos,
                   COUNT(DISTINCT p.almacen_id) as almacenes_con_productos,
                   COALESCE(SUM(p.cantidad), 0) as stock_total
                   FROM categorias c
                   LEFT JOIN productos p ON c.id = p.categoria_id
                   GROUP BY c.id, c.nombre, c.descripcion
                   ORDER BY c.nombre";
$categorias = $conn->query($sql_categorias);

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
    <title>Categorías de Productos - COMSEPROA</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Gestión de categorías de productos - Sistema COMSEPROA">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- CSS específico para categorías -->
    <link rel="stylesheet" href="../assets/css/productos-categorias.css">
</head>
<body>

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
                <li><a href="../uniformes/historial_entregas_uniformes.php" role="menuitem"><i class="fas fa-tshirt"></i> Ver Historial de Entregas</a></li>
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

    <!-- Header de la página -->
    <header class="page-header">
        <div class="header-content">
            <div class="header-info">
                <h1>
                    <i class="fas fa-tags"></i>
                    Categorías de Productos
                </h1>
                <p class="page-description">
                    Gestiona las categorías para organizar y clasificar tus productos
                </p>
            </div>
        </div>
    </header>

    <!-- Breadcrumb -->
    <nav class="breadcrumb" aria-label="Ruta de navegación">
        <a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a>
        <span><i class="fas fa-chevron-right"></i></span>
        <a href="listar.php">Productos</a>
        <span><i class="fas fa-chevron-right"></i></span>
        <span class="current">Categorías</span>
    </nav>

    <!-- Lista de categorías -->
    <section class="categories-section">
        <?php if ($categorias && $categorias->num_rows > 0): ?>
            <div class="categories-grid">
                <?php while ($categoria = $categorias->fetch_assoc()): ?>
                    <div class="category-card" data-categoria-id="<?php echo $categoria['id']; ?>">
                        <div class="card-header">
                            <div class="category-icon">
                                <i class="fas fa-tag"></i>
                            </div>
                            <div class="category-info">
                                <h3><?php echo htmlspecialchars($categoria['nombre']); ?></h3>
                                <?php if (!empty($categoria['descripcion'])): ?>
                                <p class="category-description"><?php echo htmlspecialchars($categoria['descripcion']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <div class="category-stats">
                                <div class="stat-item">
                                    <div class="stat-icon">
                                        <i class="fas fa-boxes"></i>
                                    </div>
                                    <div class="stat-content">
                                        <span class="stat-value"><?php echo number_format($categoria['total_productos']); ?></span>
                                        <span class="stat-label">Productos</span>
                                    </div>
                                </div>
                                
                                <div class="stat-item">
                                    <div class="stat-icon">
                                        <i class="fas fa-warehouse"></i>
                                    </div>
                                    <div class="stat-content">
                                        <span class="stat-value"><?php echo number_format($categoria['almacenes_con_productos']); ?></span>
                                        <span class="stat-label">Almacenes</span>
                                    </div>
                                </div>
                                
                                <div class="stat-item">
                                    <div class="stat-icon">
                                        <i class="fas fa-cubes"></i>
                                    </div>
                                    <div class="stat-content">
                                        <span class="stat-value"><?php echo number_format($categoria['stock_total']); ?></span>
                                        <span class="stat-label">Stock Total</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-footer">
                            <div class="card-actions">
                                <a href="listar.php?categoria_id=<?php echo $categoria['id']; ?>" class="btn-card btn-view">
                                    <i class="fas fa-eye"></i>
                                    Ver Productos
                                </a>
                                
                                <?php if ($usuario_rol == 'admin'): ?>
                                <a href="registrar.php?categoria_id=<?php echo $categoria['id']; ?>" class="btn-card btn-add">
                                    <i class="fas fa-plus"></i>
                                    Nuevo Producto
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-tags"></i>
                </div>
                <h3>No hay categorías registradas</h3>
                <p>No se encontraron categorías en el sistema.</p>
                
                <?php if ($usuario_rol == 'admin'): ?>
                <div class="empty-actions">
                    <p><strong>Nota:</strong> Las categorías son gestionadas por el administrador del sistema.</p>
                    <p>Contacta con el administrador para agregar nuevas categorías.</p>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Información adicional -->
    <aside class="info-panel">
        <div class="panel-header">
            <h3>
                <i class="fas fa-info-circle"></i>
                Información sobre Categorías
            </h3>
        </div>
        
        <div class="panel-content">
            <div class="info-item">
                <div class="info-icon">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <div class="info-content">
                    <h4>¿Qué son las categorías?</h4>
                    <p>Las categorías te permiten organizar y clasificar tus productos de manera lógica, facilitando la búsqueda y gestión del inventario.</p>
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-icon">
                    <i class="fas fa-search"></i>
                </div>
                <div class="info-content">
                    <h4>Buscar productos por categoría</h4>
                    <p>Haz clic en "Ver Productos" en cualquier categoría para ver todos los productos clasificados en esa categoría específica.</p>
                </div>
            </div>
            
            <?php if ($usuario_rol == 'admin'): ?>
            <div class="info-item">
                <div class="info-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div class="info-content">
                    <h4>Agregar productos</h4>
                    <p>Puedes agregar nuevos productos directamente a una categoría usando el botón "Nuevo Producto".</p>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="quick-stats">
                <h4>Estadísticas Rápidas</h4>
                <div class="stats-summary">
                    <?php
                    // Calcular estadísticas totales
                    $categorias->data_seek(0); // Reset cursor
                    $total_categorias = $categorias->num_rows;
                    $total_productos_global = 0;
                    $total_stock_global = 0;
                    
                    while ($cat = $categorias->fetch_assoc()) {
                        $total_productos_global += $cat['total_productos'];
                        $total_stock_global += $cat['stock_total'];
                    }
                    ?>
                    <div class="summary-item">
                        <span class="summary-value"><?php echo $total_categorias; ?></span>
                        <span class="summary-label">Categorías Activas</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-value"><?php echo number_format($total_productos_global); ?></span>
                        <span class="summary-label">Productos Totales</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-value"><?php echo number_format($total_stock_global); ?></span>
                        <span class="summary-label">Unidades en Stock</span>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</main>

<!-- Container for dynamic notifications -->
<div id="notificaciones-container" role="alert" aria-live="polite"></div>

<!-- JavaScript -->
<script src="../assets/js/productos-categorias.js"></script>
</body>
</html>