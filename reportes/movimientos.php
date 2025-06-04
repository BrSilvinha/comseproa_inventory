<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: /views/login_form.php");
    exit();
}

// Prevent session hijacking
session_regenerate_id(true);

$user_name = isset($_SESSION["user_name"]) ? $_SESSION["user_name"] : "Usuario";
$usuario_almacen_id = isset($_SESSION["almacen_id"]) ? $_SESSION["almacen_id"] : null;
$usuario_rol = isset($_SESSION["user_role"]) ? $_SESSION["user_role"] : "usuario";

// Require database connection
require_once "../config/database.php";

// ===== CONTAR SOLICITUDES PENDIENTES PARA EL BADGE =====
$total_pendientes = 0;
$sql_pendientes = "SELECT COUNT(*) as total FROM solicitudes_transferencia WHERE estado = 'pendiente'";

if ($usuario_rol != 'admin') {
    $sql_pendientes .= " AND almacen_destino = ?";
    $stmt_pendientes = $conn->prepare($sql_pendientes);
    $stmt_pendientes->bind_param("i", $usuario_almacen_id);
    $stmt_pendientes->execute();
    $result_pendientes = $stmt_pendientes->get_result();
    $stmt_pendientes->close();
} else {
    $result_pendientes = $conn->query($sql_pendientes);
}

if ($result_pendientes && $row_pendientes = $result_pendientes->fetch_assoc()) {
    $total_pendientes = $row_pendientes['total'];
}

// ===== OBTENER DATOS DE MOVIMIENTOS =====
$filtro_fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$filtro_fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-t');
$filtro_almacen = isset($_GET['almacen']) ? $_GET['almacen'] : '';
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';

// Query para movimientos - USANDO LA TABLA CORRECTA 'movimientos'
$sql_movimientos = "
    SELECT 
        m.id,
        m.fecha,
        m.cantidad,
        m.estado,
        m.tipo as tipo_movimiento,
        p.nombre as producto_nombre,
        CONCAT('PROD-', LPAD(p.id, 4, '0')) as producto_codigo,
        ao.nombre as almacen_origen,
        ad.nombre as almacen_destino,
        u.nombre as usuario_nombre
    FROM movimientos m
    LEFT JOIN productos p ON m.producto_id = p.id
    LEFT JOIN almacenes ao ON m.almacen_origen = ao.id
    LEFT JOIN almacenes ad ON m.almacen_destino = ad.id
    LEFT JOIN usuarios u ON m.usuario_id = u.id
    WHERE m.fecha BETWEEN ? AND ?
";

// Construir parámetros dinámicamente
$param_fecha_inicio = $filtro_fecha_inicio . ' 00:00:00';
$param_fecha_fin = $filtro_fecha_fin . ' 23:59:59';

if (!empty($filtro_almacen) && $usuario_rol == 'admin') {
    $sql_movimientos .= " AND (m.almacen_origen = ? OR m.almacen_destino = ?)";
    if (!empty($filtro_tipo)) {
        $sql_movimientos .= " AND m.tipo = ?";
        $sql_movimientos .= " ORDER BY m.fecha DESC LIMIT 100";
        $stmt_movimientos = $conn->prepare($sql_movimientos);
        $stmt_movimientos->bind_param("ssiss", $param_fecha_inicio, $param_fecha_fin, $filtro_almacen, $filtro_almacen, $filtro_tipo);
    } else {
        $sql_movimientos .= " ORDER BY m.fecha DESC LIMIT 100";
        $stmt_movimientos = $conn->prepare($sql_movimientos);
        $stmt_movimientos->bind_param("ssii", $param_fecha_inicio, $param_fecha_fin, $filtro_almacen, $filtro_almacen);
    }
} elseif ($usuario_rol != 'admin') {
    $sql_movimientos .= " AND (m.almacen_origen = ? OR m.almacen_destino = ?)";
    if (!empty($filtro_tipo)) {
        $sql_movimientos .= " AND m.tipo = ?";
        $sql_movimientos .= " ORDER BY m.fecha DESC LIMIT 100";
        $stmt_movimientos = $conn->prepare($sql_movimientos);
        $stmt_movimientos->bind_param("ssiss", $param_fecha_inicio, $param_fecha_fin, $usuario_almacen_id, $usuario_almacen_id, $filtro_tipo);
    } else {
        $sql_movimientos .= " ORDER BY m.fecha DESC LIMIT 100";
        $stmt_movimientos = $conn->prepare($sql_movimientos);
        $stmt_movimientos->bind_param("ssii", $param_fecha_inicio, $param_fecha_fin, $usuario_almacen_id, $usuario_almacen_id);
    }
} else {
    if (!empty($filtro_tipo)) {
        $sql_movimientos .= " AND m.tipo = ?";
        $sql_movimientos .= " ORDER BY m.fecha DESC LIMIT 100";
        $stmt_movimientos = $conn->prepare($sql_movimientos);
        $stmt_movimientos->bind_param("sss", $param_fecha_inicio, $param_fecha_fin, $filtro_tipo);
    } else {
        $sql_movimientos .= " ORDER BY m.fecha DESC LIMIT 100";
        $stmt_movimientos = $conn->prepare($sql_movimientos);
        $stmt_movimientos->bind_param("ss", $param_fecha_inicio, $param_fecha_fin);
    }
}

$stmt_movimientos->execute();
$result_movimientos = $stmt_movimientos->get_result();

// Estadísticas - USANDO LA TABLA CORRECTA
$sql_stats = "
    SELECT 
        COUNT(*) as total_movimientos,
        SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completados,
        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'rechazado' THEN 1 ELSE 0 END) as rechazados
    FROM movimientos 
    WHERE fecha BETWEEN ? AND ?
";

if ($usuario_rol != 'admin') {
    $sql_stats .= " AND (almacen_origen = ? OR almacen_destino = ?)";
    $stmt_stats = $conn->prepare($sql_stats);
    $stmt_stats->bind_param("ssii", $param_fecha_inicio, $param_fecha_fin, $usuario_almacen_id, $usuario_almacen_id);
} else {
    $stmt_stats = $conn->prepare($sql_stats);
    $stmt_stats->bind_param("ss", $param_fecha_inicio, $param_fecha_fin);
}

$stmt_stats->execute();
$result_stats = $stmt_stats->get_result();
$stats = $result_stats->fetch_assoc();

// Obtener lista de almacenes para el filtro
$almacenes = [];
if ($usuario_rol == 'admin') {
    $sql_almacenes = "SELECT id, nombre FROM almacenes ORDER BY nombre";
    $result_almacenes = $conn->query($sql_almacenes);
    if ($result_almacenes) {
        while ($row = $result_almacenes->fetch_assoc()) {
            $almacenes[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Movimientos - GRUPO SEAL</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Reportes de movimientos del sistema de gestión de inventario">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Preconnect para optimizar carga de fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- CSS específico para reportes de movimientos -->
    <link rel="stylesheet" href="../assets/css/reportes-movimientos.css">
    
    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon.ico">
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
        
        <!-- Historial Section -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Historial" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-history"></i> Historial</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li><a href="../entregas/historial.php" role="menuitem"><i class="fas fa-hand-holding"></i> Historial de Entregas</a></li>
                <li><a href="../notificaciones/historial.php" role="menuitem"><i class="fas fa-exchange-alt"></i> Historial de Solicitudes</a></li>
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
                        <?php if ($total_pendientes > 0): ?>
                        <span class="badge-small"><?php echo $total_pendientes; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </li>

        <!-- Reports Section -->
        <?php if ($usuario_rol == 'admin'): ?>
        <li class="submenu-container active">
            <a href="#" aria-label="Menú Reportes" aria-expanded="true" role="button" tabindex="0">
                <span><i class="fas fa-chart-bar"></i> Reportes</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu activo" role="menu">
                <li><a href="../reportes/inventario.php" role="menuitem"><i class="fas fa-warehouse"></i> Inventario General</a></li>
                <li class="active"><a href="../reportes/movimientos.php" role="menuitem"><i class="fas fa-exchange-alt"></i> Movimientos</a></li>
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
    <!-- Header -->
    <header class="movimientos-header">
        <div class="header-content">
            <div class="header-info">
                <h1><i class="fas fa-exchange-alt"></i> Reportes de Movimientos</h1>
                <p>Análisis detallado de transferencias y movimientos de inventario</p>
            </div>
            <div class="header-actions">
                <button class="btn-export" onclick="exportarReporte()">
                    <i class="fas fa-download"></i> Exportar PDF
                </button>
                <button class="btn-export" onclick="exportarExcel()">
                    <i class="fas fa-file-excel"></i> Exportar Excel
                </button>
            </div>
        </div>
    </header>

    <!-- Estadísticas Generales -->
    <section class="stats-grid">
        <div class="stat-card total">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-value"><?php echo number_format($stats['total_movimientos']); ?></div>
            <div class="stat-label">Total Movimientos</div>
        </div>
        
        <div class="stat-card completados">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-value"><?php echo number_format($stats['completados']); ?></div>
            <div class="stat-label">Completados</div>
        </div>
        
        <div class="stat-card pendientes">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-value"><?php echo number_format($stats['pendientes']); ?></div>
            <div class="stat-label">Pendientes</div>
        </div>
        
        <div class="stat-card rechazados">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-value"><?php echo number_format($stats['rechazados']); ?></div>
            <div class="stat-label">Rechazados</div>
        </div>
    </section>

    <!-- Filtros -->
    <section class="filters-section">
        <div class="filters-header">
            <h3><i class="fas fa-filter"></i> Filtros de Búsqueda</h3>
        </div>
        
        <form class="filters-form" method="GET">
            <div class="filter-group">
                <label class="filter-label">Fecha Inicio</label>
                <input type="date" name="fecha_inicio" value="<?php echo $filtro_fecha_inicio; ?>" class="filter-control">
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Fecha Fin</label>
                <input type="date" name="fecha_fin" value="<?php echo $filtro_fecha_fin; ?>" class="filter-control">
            </div>
            
            <?php if ($usuario_rol == 'admin'): ?>
            <div class="filter-group">
                <label class="filter-label">Almacén</label>
                <select name="almacen" class="filter-control">
                    <option value="">Todos los almacenes</option>
                    <?php foreach ($almacenes as $almacen): ?>
                    <option value="<?php echo $almacen['id']; ?>" <?php echo ($filtro_almacen == $almacen['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($almacen['nombre']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="filter-group">
                <label class="filter-label">Tipo</label>
                <select name="tipo" class="filter-control">
                    <option value="">Todos los tipos</option>
                    <option value="entrada" <?php echo ($filtro_tipo == 'entrada') ? 'selected' : ''; ?>>Entrada</option>
                    <option value="salida" <?php echo ($filtro_tipo == 'salida') ? 'selected' : ''; ?>>Salida</option>
                    <option value="transferencia" <?php echo ($filtro_tipo == 'transferencia') ? 'selected' : ''; ?>>Transferencia</option>
                </select>
            </div>
            
            <button type="submit" class="btn-filter">
                <i class="fas fa-search"></i> Filtrar
            </button>
        </form>
    </section>

    <!-- Tabla de Movimientos -->
    <section class="movimientos-table-section">
        <div class="table-header">
            <h3><i class="fas fa-table"></i> Detalle de Movimientos</h3>
        </div>
        
        <div class="table-responsive">
            <table class="movimientos-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> ID</th>
                        <th><i class="fas fa-calendar"></i> Fecha</th>
                        <th><i class="fas fa-box"></i> Producto</th>
                        <th><i class="fas fa-sort-numeric-up"></i> Cantidad</th>
                        <th><i class="fas fa-warehouse"></i> Origen</th>
                        <th><i class="fas fa-warehouse"></i> Destino</th>
                        <th><i class="fas fa-user"></i> Usuario</th>
                        <th><i class="fas fa-info-circle"></i> Estado</th>
                        <th><i class="fas fa-tag"></i> Tipo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_movimientos->num_rows > 0): ?>
                        <?php while ($movimiento = $result_movimientos->fetch_assoc()): ?>
                        <tr>
                            <td class="mov-id">#<?php echo str_pad($movimiento['id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td class="mov-fecha"><?php echo date('d/m/Y H:i', strtotime($movimiento['fecha'])); ?></td>
                            <td class="mov-producto">
                                <div class="producto-info">
                                    <strong><?php echo htmlspecialchars($movimiento['producto_nombre']); ?></strong>
                                    <small><?php echo htmlspecialchars($movimiento['producto_codigo']); ?></small>
                                </div>
                            </td>
                            <td class="mov-cantidad"><?php echo number_format($movimiento['cantidad']); ?></td>
                            <td class="mov-almacen"><?php echo htmlspecialchars($movimiento['almacen_origen'] ?? 'Sistema'); ?></td>
                            <td class="mov-almacen"><?php echo htmlspecialchars($movimiento['almacen_destino'] ?? 'Sistema'); ?></td>
                            <td class="mov-usuario"><?php echo htmlspecialchars($movimiento['usuario_nombre']); ?></td>
                            <td class="mov-estado">
                                <?php
                                $estado_class = '';
                                switch($movimiento['estado']) {
                                    case 'completado':
                                        $estado_class = 'estado-completado';
                                        break;
                                    case 'pendiente':
                                        $estado_class = 'estado-pendiente';
                                        break;
                                    case 'rechazado':
                                        $estado_class = 'estado-rechazado';
                                        break;
                                }
                                ?>
                                <span class="estado-badge <?php echo $estado_class; ?>">
                                    <?php echo ucfirst($movimiento['estado']); ?>
                                </span>
                            </td>
                            <td class="mov-tipo"><?php echo ucfirst($movimiento['tipo_movimiento']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="no-results">
                                <i class="fas fa-search"></i>
                                <p>No se encontraron movimientos con los filtros aplicados</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

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
        
        if (link && submenu) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                submenuContainers.forEach(otherContainer => {
                    if (otherContainer !== container) {
                        const otherSubmenu = otherContainer.querySelector('.submenu');
                        const otherLink = otherContainer.querySelector('a');
                        
                        if (otherSubmenu && otherSubmenu.classList.contains('activo')) {
                            otherSubmenu.classList.remove('activo');
                            if (otherLink) {
                                otherLink.setAttribute('aria-expanded', 'false');
                            }
                        }
                    }
                });
                
                submenu.classList.toggle('activo');
                const isExpanded = submenu.classList.contains('activo');
                link.setAttribute('aria-expanded', isExpanded.toString());
            });
        }
    });
});

// Función para exportar reporte
function exportarReporte() {
    mostrarNotificacion('Generando reporte PDF...', 'info');
    // Aquí iría la lógica para generar PDF
}

function exportarExcel() {
    mostrarNotificacion('Generando archivo Excel...', 'info');
    // Aquí iría la lógica para generar Excel
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
</script>
</body>
</html>