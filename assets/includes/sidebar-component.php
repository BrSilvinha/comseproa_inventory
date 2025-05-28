<?php
// ===================================================================
// COMPONENTE SIDEBAR REUTILIZABLE PARA TODAS LAS VISTAS
// ===================================================================

// Obtener datos de sesión si no están disponibles
if (!isset($usuario_rol)) {
    $usuario_rol = isset($_SESSION["user_role"]) ? $_SESSION["user_role"] : "usuario";
}
if (!isset($usuario_almacen_id)) {
    $usuario_almacen_id = isset($_SESSION["almacen_id"]) ? $_SESSION["almacen_id"] : null;
}

// Contar solicitudes pendientes para el badge
$pendientes_count = 0;
if (isset($conn)) {
    $sql_pendientes = "SELECT COUNT(*) as total FROM solicitudes_transferencia WHERE estado = 'pendiente'";
    
    if ($usuario_rol != 'admin' && $usuario_almacen_id) {
        $sql_pendientes .= " AND almacen_destino = ?";
        if ($stmt_pendientes = $conn->prepare($sql_pendientes)) {
            $stmt_pendientes->bind_param("i", $usuario_almacen_id);
            $stmt_pendientes->execute();
            $result_pendientes = $stmt_pendientes->get_result();
        }
    } else {
        $result_pendientes = $conn->query($sql_pendientes);
    }
    
    if (isset($result_pendientes) && $result_pendientes && $row_pendientes = $result_pendientes->fetch_assoc()) {
        $pendientes_count = $row_pendientes['total'];
    }
}

// Detectar la página actual para activar el menú correspondiente
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Función para generar rutas relativas correctas
function getRelativePath($targetDir = '') {
    global $current_dir;
    $baseDirs = ['comseproa_inventory', 'dashboard', ''];
    
    if (in_array($current_dir, $baseDirs) || $current_dir == basename($_SERVER['DOCUMENT_ROOT'])) {
        return $targetDir ? $targetDir . '/' : '';
    } else {
        return $targetDir ? '../' . $targetDir . '/' : '../';
    }
}
?>

<!-- Botón hamburguesa móvil -->
<button class="menu-toggle" id="menuToggle" aria-label="Abrir menú de navegación">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar Navigation -->
<nav class="sidebar" id="sidebar" role="navigation" aria-label="Menú principal">
    <div class="sidebar-header">
        <h2>COMSEPROA</h2>
        <p class="sidebar-subtitle">Sistema de Gestión</p>
    </div>
    
    <ul class="sidebar-menu">
        <!-- Inicio -->
        <li class="menu-item <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
            <a href="<?= getRelativePath() ?>dashboard.php" aria-label="Ir a inicio">
                <i class="fas fa-home"></i>
                <span>Inicio</span>
            </a>
        </li>

        <!-- Usuarios - Solo visible para administradores -->
        <?php if ($usuario_rol == 'admin'): ?>
        <li class="menu-item submenu-container <?= ($current_dir == 'usuarios') ? 'active' : '' ?>">
            <a href="#" aria-label="Menú Usuarios" aria-expanded="<?= ($current_dir == 'usuarios') ? 'true' : 'false' ?>" role="button" tabindex="0">
                <i class="fas fa-users"></i>
                <span>Usuarios</span>
                <i class="fas fa-chevron-down submenu-arrow"></i>
            </a>
            <ul class="submenu <?= ($current_dir == 'usuarios') ? 'activo' : '' ?>" role="menu">
                <li class="<?= ($current_page == 'registrar.php' && $current_dir == 'usuarios') ? 'active' : '' ?>">
                    <a href="<?= getRelativePath('usuarios') ?>registrar.php" role="menuitem">
                        <i class="fas fa-user-plus"></i>
                        <span>Registrar Usuario</span>
                    </a>
                </li>
                <li class="<?= ($current_page == 'listar.php' && $current_dir == 'usuarios') ? 'active' : '' ?>">
                    <a href="<?= getRelativePath('usuarios') ?>listar.php" role="menuitem">
                        <i class="fas fa-list"></i>
                        <span>Lista de Usuarios</span>
                    </a>
                </li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- Almacenes -->
        <li class="menu-item submenu-container <?= ($current_dir == 'almacenes') ? 'active' : '' ?>">
            <a href="#" aria-label="Menú Almacenes" aria-expanded="<?= ($current_dir == 'almacenes') ? 'true' : 'false' ?>" role="button" tabindex="0">
                <i class="fas fa-warehouse"></i>
                <span>Almacenes</span>
                <i class="fas fa-chevron-down submenu-arrow"></i>
            </a>
            <ul class="submenu <?= ($current_dir == 'almacenes') ? 'activo' : '' ?>" role="menu">
                <?php if ($usuario_rol == 'admin'): ?>
                <li class="<?= ($current_page == 'registrar.php' && $current_dir == 'almacenes') ? 'active' : '' ?>">
                    <a href="<?= getRelativePath('almacenes') ?>registrar.php" role="menuitem">
                        <i class="fas fa-plus"></i>
                        <span>Registrar Almacén</span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="<?= ($current_page == 'listar.php' && $current_dir == 'almacenes') ? 'active' : '' ?>">
                    <a href="<?= getRelativePath('almacenes') ?>listar.php" role="menuitem">
                        <i class="fas fa-list"></i>
                        <span>Lista de Almacenes</span>
                    </a>
                </li>
            </ul>
        </li>
        
        <!-- Productos -->
        <li class="menu-item submenu-container <?= ($current_dir == 'productos') ? 'active' : '' ?>">
            <a href="#" aria-label="Menú Productos" aria-expanded="<?= ($current_dir == 'productos') ? 'true' : 'false' ?>" role="button" tabindex="0">
                <i class="fas fa-boxes"></i>
                <span>Productos</span>
                <i class="fas fa-chevron-down submenu-arrow"></i>
            </a>
            <ul class="submenu <?= ($current_dir == 'productos') ? 'activo' : '' ?>" role="menu">
                <li class="<?= ($current_page == 'registrar.php' && $current_dir == 'productos') ? 'active' : '' ?>">
                    <a href="<?= getRelativePath('productos') ?>registrar.php" role="menuitem">
                        <i class="fas fa-plus"></i>
                        <span>Registrar Producto</span>
                    </a>
                </li>
                <li class="<?= ($current_page == 'listar.php' && $current_dir == 'productos') ? 'active' : '' ?>">
                    <a href="<?= getRelativePath('productos') ?>listar.php" role="menuitem">
                        <i class="fas fa-list"></i>
                        <span>Lista de Productos</span>
                    </a>
                </li>
                <li class="<?= ($current_page == 'categorias.php' && $current_dir == 'productos') ? 'active' : '' ?>">
                    <a href="<?= getRelativePath('productos') ?>categorias.php" role="menuitem">
                        <i class="fas fa-tags"></i>
                        <span>Categorías</span>
                    </a>
                </li>
            </ul>
        </li>
        
        <!-- Notificaciones -->
        <li class="menu-item submenu-container <?= ($current_dir == 'notificaciones' || $current_dir == 'uniformes') ? 'active' : '' ?>">
            <a href="#" aria-label="Menú Notificaciones" aria-expanded="<?= ($current_dir == 'notificaciones' || $current_dir == 'uniformes') ? 'true' : 'false' ?>" role="button" tabindex="0">
                <i class="fas fa-bell"></i>
                <span>Notificaciones</span>
                <?php if ($pendientes_count > 0): ?>
                <span class="badge" aria-label="<?= $pendientes_count ?> solicitudes pendientes"><?= $pendientes_count ?></span>
                <?php endif; ?>
                <i class="fas fa-chevron-down submenu-arrow"></i>
            </a>
            <ul class="submenu <?= ($current_dir == 'notificaciones' || $current_dir == 'uniformes') ? 'activo' : '' ?>" role="menu">
                <li class="<?= ($current_page == 'pendientes.php' && $current_dir == 'notificaciones') ? 'active' : '' ?>">
                    <a href="<?= getRelativePath('notificaciones') ?>pendientes.php" role="menuitem">
                        <i class="fas fa-clock"></i>
                        <span>Solicitudes Pendientes</span>
                        <?php if ($pendientes_count > 0): ?>
                        <span class="badge-small"><?= $pendientes_count ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="<?= ($current_page == 'historial.php' && $current_dir == 'notificaciones') ? 'active' : '' ?>">
                    <a href="<?= getRelativePath('notificaciones') ?>historial.php" role="menuitem">
                        <i class="fas fa-history"></i>
                        <span>Historial de Solicitudes</span>
                    </a>
                </li>
                <li class="<?= (strpos($current_page, 'historial_entregas') !== false && $current_dir == 'uniformes') ? 'active' : '' ?>">
                    <a href="<?= getRelativePath('uniformes') ?>historial_entregas_uniformes.php" role="menuitem">
                        <i class="fas fa-tshirt"></i>
                        <span>Historial de Entregas</span>
                    </a>
                </li>
            </ul>
        </li>

        <!-- Reportes - Solo para administradores -->
        <?php if ($usuario_rol == 'admin'): ?>
        <li class="menu-item submenu-container <?= ($current_dir == 'reportes') ? 'active' : '' ?>">
            <a href="#" aria-label="Menú Reportes" aria-expanded="<?= ($current_dir == 'reportes') ? 'true' : 'false' ?>" role="button" tabindex="0">
                <i class="fas fa-chart-bar"></i>
                <span>Reportes</span>
                <i class="fas fa-chevron-down submenu-arrow"></i>
            </a>
            <ul class="submenu <?= ($current_dir == 'reportes') ? 'activo' : '' ?>" role="menu">
                <li class="<?= ($current_page == 'inventario.php' && $current_dir == 'reportes') ? 'active' : '' ?>">
                    <a href="<?= getRelativePath('reportes') ?>inventario.php" role="menuitem">
                        <i class="fas fa-warehouse"></i>
                        <span>Inventario General</span>
                    </a>
                </li>
                <li class="<?= ($current_page == 'movimientos.php' && $current_dir == 'reportes') ? 'active' : '' ?>">
                    <a href="<?= getRelativePath('reportes') ?>movimientos.php" role="menuitem">
                        <i class="fas fa-exchange-alt"></i>
                        <span>Movimientos</span>
                    </a>
                </li>
                <li class="<?= ($current_page == 'usuarios.php' && $current_dir == 'reportes') ? 'active' : '' ?>">
                    <a href="<?= getRelativePath('reportes') ?>usuarios.php" role="menuitem">
                        <i class="fas fa-users"></i>
                        <span>Actividad de Usuarios</span>
                    </a>
                </li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- Mi Perfil -->
        <li class="menu-item submenu-container <?= ($current_dir == 'perfil') ? 'active' : '' ?>">
            <a href="#" aria-label="Menú Perfil" aria-expanded="<?= ($current_dir == 'perfil') ? 'true' : 'false' ?>" role="button" tabindex="0">
                <i class="fas fa-user-circle"></i>
                <span>Mi Perfil</span>
                <i class="fas fa-chevron-down submenu-arrow"></i>
            </a>
            <ul class="submenu <?= ($current_dir == 'perfil') ? 'activo' : '' ?>" role="menu">
                <li class="<?= ($current_page == 'configuracion.php' && $current_dir == 'perfil') ? 'active' : '' ?>">
                    <a href="<?= getRelativePath('perfil') ?>configuracion.php" role="menuitem">
                        <i class="fas fa-cog"></i>
                        <span>Configuración</span>
                    </a>
                </li>
                <li class="<?= ($current_page == 'cambiar-password.php' && $current_dir == 'perfil') ? 'active' : '' ?>">
                    <a href="<?= getRelativePath('perfil') ?>cambiar-password.php" role="menuitem">
                        <i class="fas fa-key"></i>
                        <span>Cambiar Contraseña</span>
                    </a>
                </li>
            </ul>
        </li>
    </ul>

    <!-- Footer del sidebar -->
    <div class="sidebar-footer">
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <div class="user-details">
                <span class="user-name"><?= htmlspecialchars(isset($_SESSION["user_name"]) ? $_SESSION["user_name"] : "Usuario") ?></span>
                <span class="user-role"><?= ucfirst($usuario_rol) ?></span>
            </div>
        </div>
        
        <a href="<?= getRelativePath() ?>logout.php" 
           class="logout-btn" 
           onclick="return confirm('¿Estás seguro de que deseas cerrar sesión?')"
           aria-label="Cerrar sesión">
            <i class="fas fa-sign-out-alt"></i>
            <span>Cerrar Sesión</span>
        </a>
    </div>
</nav>