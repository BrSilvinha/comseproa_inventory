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

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection configuration
require_once "../config/database.php";

// Obtener estadísticas generales
function obtenerEstadisticas($conn, $usuario_rol, $usuario_almacen_id) {
    $stats = [];
    
    // Total de entregas
    $sql_entregas = "SELECT COUNT(*) as total FROM entrega_uniformes";
    if ($usuario_rol != 'admin' && $usuario_almacen_id) {
        $sql_entregas .= " WHERE almacen_id = ?";
        $stmt = $conn->prepare($sql_entregas);
        $stmt->bind_param("i", $usuario_almacen_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql_entregas);
    }
    $stats['entregas'] = $result->fetch_assoc()['total'];
    
    // Total de productos entregados
    $sql_productos = "SELECT SUM(cantidad) as total FROM entrega_uniformes";
    if ($usuario_rol != 'admin' && $usuario_almacen_id) {
        $sql_productos .= " WHERE almacen_id = ?";
        $stmt = $conn->prepare($sql_productos);
        $stmt->bind_param("i", $usuario_almacen_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql_productos);
    }
    $stats['productos'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Almacenes involucrados
    if ($usuario_rol == 'admin') {
        $sql_almacenes = "SELECT COUNT(DISTINCT almacen_id) as total FROM entrega_uniformes";
        $result = $conn->query($sql_almacenes);
        $stats['almacenes'] = $result->fetch_assoc()['total'];
    } else {
        $stats['almacenes'] = 1;
    }
    
    // Entregas este mes
    $sql_mes = "SELECT COUNT(*) as total FROM entrega_uniformes WHERE MONTH(fecha_entrega) = MONTH(CURDATE()) AND YEAR(fecha_entrega) = YEAR(CURDATE())";
    if ($usuario_rol != 'admin' && $usuario_almacen_id) {
        $sql_mes .= " AND almacen_id = ?";
        $stmt = $conn->prepare($sql_mes);
        $stmt->bind_param("i", $usuario_almacen_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql_mes);
    }
    $stats['mes'] = $result->fetch_assoc()['total'];
    
    return $stats;
}

// Obtener entregas recientes para la tabla
function obtenerEntregasRecientes($conn, $usuario_rol, $usuario_almacen_id, $limite = 10) {
    $sql = "SELECT eu.*, p.nombre as producto_nombre, a.nombre as almacen_nombre 
            FROM entrega_uniformes eu 
            JOIN productos p ON eu.producto_id = p.id 
            JOIN almacenes a ON eu.almacen_id = a.id";
    
    if ($usuario_rol != 'admin' && $usuario_almacen_id) {
        $sql .= " WHERE eu.almacen_id = ?";
    }
    
    $sql .= " ORDER BY eu.fecha_entrega DESC LIMIT " . $limite;
    
    if ($usuario_rol != 'admin' && $usuario_almacen_id) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $usuario_almacen_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Obtener estadísticas
$estadisticas = obtenerEstadisticas($conn, $usuario_rol, $usuario_almacen_id);
$entregas_recientes = obtenerEntregasRecientes($conn, $usuario_rol, $usuario_almacen_id);

// Procesar filtros si existen
$filtros = [];
if (isset($_GET['fecha_inicio']) || isset($_GET['fecha_fin']) || isset($_GET['almacen_id'])) {
    $filtros = [
        'fecha_inicio' => $_GET['fecha_inicio'] ?? '',
        'fecha_fin' => $_GET['fecha_fin'] ?? '',
        'almacen_id' => $_GET['almacen_id'] ?? ''
    ];
}

// Obtener almacenes para el filtro
if ($usuario_rol == 'admin') {
    $sql_almacenes = "SELECT id, nombre FROM almacenes ORDER BY nombre";
    $result_almacenes = $conn->query($sql_almacenes);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Uniformes - GRUPO SEAL</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Reportes y estadísticas de uniformes - Sistema de gestión GRUPO SEAL">
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
    <link rel="stylesheet" href="../assets/css/uniformes-reportes.css">
    
    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon.ico">
    <link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
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

        <!-- Uniformes Section -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Uniformes" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-tshirt"></i> Uniformes</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li><a href="formulario_entrega_uniforme.php" role="menuitem"><i class="fas fa-hand-holding"></i> Entregar Uniformes</a></li>
                <li><a href="historial_entregas_uniformes.php" role="menuitem"><i class="fas fa-history"></i> Historial de Entregas</a></li>
                <li><a href="reportes_uniformes.php" role="menuitem"><i class="fas fa-chart-bar"></i> Reportes</a></li>
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
    <!-- Reports Header -->
    <div class="reports-header">
        <div class="header-content">
            <div class="header-info">
                <h1>Reportes de Uniformes</h1>
                <p>Panel de estadísticas y análisis de entregas de uniformes</p>
            </div>
            <div class="header-actions">
                <a href="#" class="btn-export" onclick="exportarReporte()">
                    <i class="fas fa-download"></i>
                    Exportar PDF
                </a>
                <a href="#" class="btn-export" onclick="exportarExcel()">
                    <i class="fas fa-file-excel"></i>
                    Exportar Excel
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Grid -->
    <div class="stats-grid">
        <div class="stat-card entregas">
            <div class="stat-icon"></div>
            <div class="stat-value"><?php echo number_format($estadisticas['entregas']); ?></div>
            <div class="stat-label">Total Entregas</div>
        </div>
        
        <div class="stat-card productos">
            <div class="stat-icon"></div>
            <div class="stat-value"><?php echo number_format($estadisticas['productos']); ?></div>
            <div class="stat-label">Productos Entregados</div>
        </div>
        
        <div class="stat-card almacenes">
            <div class="stat-icon"></div>
            <div class="stat-value"><?php echo number_format($estadisticas['almacenes']); ?></div>
            <div class="stat-label">Almacenes Activos</div>
        </div>
        
        <div class="stat-card mes">
            <div class="stat-icon"></div>
            <div class="stat-value"><?php echo number_format($estadisticas['mes']); ?></div>
            <div class="stat-label">Entregas Este Mes</div>
        </div>
    </div>

    <!-- Executive Summary -->
    <div class="executive-summary">
        <div class="summary-header">
            <h3>Resumen Ejecutivo</h3>
        </div>
        <div class="summary-content">
            <div class="summary-item">
                <div class="summary-value"><?php echo number_format($estadisticas['entregas'] > 0 ? $estadisticas['productos'] / $estadisticas['entregas'] : 0, 1); ?></div>
                <div class="summary-label">Promedio por Entrega</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?php echo $estadisticas['mes']; ?></div>
                <div class="summary-label">Entregas Mensuales</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?php echo date('d/m/Y'); ?></div>
                <div class="summary-label">Último Reporte</div>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
        <div class="filters-header">
            <h3>Filtros de Búsqueda</h3>
        </div>
        <form class="filters-form" method="GET">
            <div class="filter-group">
                <label class="filter-label">Fecha de Inicio</label>
                <input type="date" class="filter-control" name="fecha_inicio" 
                       value="<?php echo htmlspecialchars($filtros['fecha_inicio'] ?? ''); ?>">
            </div>
            <div class="filter-group">
                <label class="filter-label">Fecha de Fin</label>
                <input type="date" class="filter-control" name="fecha_fin"
                       value="<?php echo htmlspecialchars($filtros['fecha_fin'] ?? ''); ?>">
            </div>
            <?php if ($usuario_rol == 'admin'): ?>
            <div class="filter-group">
                <label class="filter-label">Almacén</label>
                <select class="filter-control" name="almacen_id">
                    <option value="">Todos los almacenes</option>
                    <?php while ($almacen = $result_almacenes->fetch_assoc()): ?>
                        <option value="<?php echo $almacen['id']; ?>" 
                                <?php echo ($filtros['almacen_id'] == $almacen['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($almacen['nombre']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="filter-group">
                <button type="submit" class="btn-filter">
                    <i class="fas fa-search"></i>
                    Filtrar
                </button>
            </div>
        </form>
    </div>

    <!-- Charts Section -->
    <div class="charts-section">
        <div class="chart-card">
            <div class="chart-header">
                <h4>Entregas por Mes</h4>
                <div class="chart-options">
                    <span class="chart-option active">6M</span>
                    <span class="chart-option">1A</span>
                    <span class="chart-option">TODO</span>
                </div>
            </div>
            <div class="chart-body">
                <div class="chart-placeholder">
                    Gráfico de entregas por mes<br>
                    <small>Implementar con Chart.js o biblioteca similar</small>
                </div>
            </div>
        </div>
        
        <div class="chart-card">
            <div class="chart-header">
                <h4>Productos Más Entregados</h4>
                <div class="chart-options">
                    <span class="chart-option active">Top 10</span>
                    <span class="chart-option">Top 20</span>
                </div>
            </div>
            <div class="chart-body">
                <div class="chart-placeholder">
                    Gráfico de productos más entregados<br>
                    <small>Implementar con Chart.js o biblioteca similar</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Reports Table -->
    <div class="reports-table-section">
        <div class="table-header">
            <h3>Entregas Recientes</h3>
        </div>
        <div class="table-responsive">
            <table class="reports-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Destinatario</th>
                        <th>DNI</th>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Almacén</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($entregas_recientes)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">
                                No hay entregas para mostrar
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($entregas_recientes as $entrega): ?>
                            <tr>
                                <td>
                                    <?php 
                                    $fecha = new DateTime($entrega['fecha_entrega']);
                                    echo $fecha->format('d/m/Y'); 
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($entrega['nombre_destinatario']); ?></td>
                                <td><?php echo htmlspecialchars($entrega['dni_destinatario']); ?></td>
                                <td><?php echo htmlspecialchars($entrega['producto_nombre']); ?></td>
                                <td><?php echo number_format($entrega['cantidad']); ?></td>
                                <td><?php echo htmlspecialchars($entrega['almacen_nombre']); ?></td>
                                <td>
                                    <span class="status-badge status-entregado">
                                        Entregado
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Container for dynamic notifications -->
<div id="notificaciones-container" role="alert" aria-live="polite"></div>

<!-- JavaScript del Dashboard -->
<script src="../assets/js/universal-confirmation-system.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Elementos principales (igual que el dashboard)
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const submenuContainers = document.querySelectorAll('.submenu-container');
    
    // Toggle del menú móvil (igual que el dashboard)
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
    
    // Funcionalidad de submenús (igual que el dashboard)
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

    // Cerrar menú móvil al hacer clic fuera (igual que el dashboard)
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
    
    // Navegación por teclado (igual que el dashboard)
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

    // Event listeners para las opciones de gráficos (específico de reportes)
    document.querySelectorAll('.chart-option').forEach(option => {
        option.addEventListener('click', function() {
            // Remover clase active de hermanos
            this.parentNode.querySelectorAll('.chart-option').forEach(opt => {
                opt.classList.remove('active');
            });
            
            // Agregar clase active al elemento clickeado
            this.classList.add('active');
            
            // Aquí iría la lógica para actualizar el gráfico
            console.log('Actualizar gráfico con opción:', this.textContent);
        });
    });

    // Validación de fechas en filtros
    document.querySelector('.filters-form').addEventListener('submit', function(e) {
        const fechaInicio = document.querySelector('input[name="fecha_inicio"]').value;
        const fechaFin = document.querySelector('input[name="fecha_fin"]').value;

        if (fechaInicio && fechaFin && new Date(fechaInicio) > new Date(fechaFin)) {
            e.preventDefault();
            mostrarNotificacion('La fecha de inicio no puede ser mayor que la fecha de fin', 'error');
        }
    });
});

// Funciones para exportar reportes
function exportarReporte() {
    // Implementar exportación a PDF
    mostrarNotificacion('Función de exportación a PDF en desarrollo', 'info');
}

function exportarExcel() {
    // Implementar exportación a Excel
    mostrarNotificacion('Función de exportación a Excel en desarrollo', 'info');
}

// Función para mostrar notificación
function mostrarNotificacion(mensaje, tipo = 'info') {
    const container = document.getElementById('notificaciones-container');
    const notificacion = document.createElement('div');
    notificacion.className = `notificacion ${tipo}`;
    notificacion.innerHTML = `
        <i class="fas fa-${tipo === 'exito' ? 'check-circle' : tipo === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        ${mensaje}
    `;
    
    container.appendChild(notificacion);
    
    setTimeout(() => {
        notificacion.remove();
    }, 5000);
}

// Función para cerrar sesión con confirmación (igual que el dashboard)
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