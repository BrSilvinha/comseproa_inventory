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
require_once "config/database.php";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - COMSEPROA | Sistema de Gestión</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Panel de control del sistema de gestión de inventario COMSEPROA">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Preconnect para optimizar carga de fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <!-- CSS exclusivo para dashboard -->
    <link rel="stylesheet" href="assets/css/dashboard-styles.css">
    
    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
    <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
</head>
<body>

<!-- Mobile hamburger menu button -->
<button class="menu-toggle" id="menuToggle" aria-label="Abrir menú de navegación">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar Navigation -->
<nav class="sidebar" id="sidebar" role="navigation" aria-label="Menú principal">
    <h2>COMSEPROA</h2>
    <ul>
        <li>
            <a href="dashboard.php" aria-label="Ir a inicio">
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
                <li><a href="usuarios/registrar.php" role="menuitem"><i class="fas fa-user-plus"></i> Registrar Usuario</a></li>
                <li><a href="usuarios/listar.php" role="menuitem"><i class="fas fa-list"></i> Lista de Usuarios</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- Warehouses Section - Adjusted according to permissions -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Almacenes" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-warehouse"></i> Almacenes</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <?php if ($usuario_rol == 'admin'): ?>
                <li><a href="almacenes/registrar.php" role="menuitem"><i class="fas fa-plus"></i> Registrar Almacén</a></li>
                <?php endif; ?>
                <li><a href="almacenes/listar.php" role="menuitem"><i class="fas fa-list"></i> Lista de Almacenes</a></li>
            </ul>
        </li>
        
        <!-- Products Section -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Productos" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-boxes"></i> Productos</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li><a href="productos/registrar.php" role="menuitem"><i class="fas fa-plus"></i> Registrar Producto</a></li>
                <li><a href="productos/listar.php" role="menuitem"><i class="fas fa-list"></i> Lista de Productos</a></li>
                <li><a href="productos/categorias.php" role="menuitem"><i class="fas fa-tags"></i> Categorías</a></li>
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
                    <a href="notificaciones/pendientes.php" role="menuitem">
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
                                echo '<span class="badge" aria-label="' . $total_pendientes . ' solicitudes pendientes">' . $total_pendientes . '</span>';
                            }
                        }
                        ?>
                    </a>
                </li>
                <li><a href="notificaciones/historial.php" role="menuitem"><i class="fas fa-history"></i> Historial de Solicitudes</a></li>
                <li><a href="uniformes/historial_entregas_uniformes.php" role="menuitem"><i class="fas fa-tshirt"></i> Historial de Entregas</a></li>
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
                <li><a href="reportes/inventario.php" role="menuitem"><i class="fas fa-warehouse"></i> Inventario General</a></li>
                <li><a href="reportes/movimientos.php" role="menuitem"><i class="fas fa-exchange-alt"></i> Movimientos</a></li>
                <li><a href="reportes/usuarios.php" role="menuitem"><i class="fas fa-users"></i> Actividad de Usuarios</a></li>
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
                <li><a href="perfil/configuracion.php" role="menuitem"><i class="fas fa-cog"></i> Configuración</a></li>
                <li><a href="perfil/cambiar-password.php" role="menuitem"><i class="fas fa-key"></i> Cambiar Contraseña</a></li>
            </ul>
        </li>

        <!-- Logout -->
        <li>
            <a href="logout.php" aria-label="Cerrar sesión" onclick="return confirm('¿Estás seguro de que deseas cerrar sesión?')">
                <span><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</span>
            </a>
        </li>
    </ul>
</nav>

<!-- Main Content -->
<main class="content" id="main-content" role="main">
    <header>
        <h1>
            Bienvenido, <?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?>
            <small style="display: block; font-size: 0.6em; font-weight: 300; color: #666; margin-top: 5px;">
                <?php 
                echo $usuario_rol == 'admin' ? 'Administrador del Sistema' : 'Usuario de Almacén'; 
                if ($usuario_almacen_id && $usuario_rol != 'admin') {
                    // Obtener nombre del almacén
                    $sql_almacen = "SELECT nombre FROM almacenes WHERE id = ?";
                    $stmt_almacen = $conn->prepare($sql_almacen);
                    $stmt_almacen->bind_param("i", $usuario_almacen_id);
                    $stmt_almacen->execute();
                    $result_almacen = $stmt_almacen->get_result();
                    if ($row_almacen = $result_almacen->fetch_assoc()) {
                        echo ' - ' . htmlspecialchars($row_almacen['nombre']);
                    }
                }
                ?>
            </small>
        </h1>
    </header>

    <div id="contenido-dinamico">
        <section class="dashboard-grid" role="region" aria-label="Panel de control">
            <?php if ($usuario_rol == 'admin'): ?>
            <!-- Admin Dashboard Cards -->
            <article class="card">
                <h3><i class="fas fa-users"></i> Gestión de Usuarios</h3>
                <p>Administrar usuarios del sistema, roles y permisos de acceso.</p>
                <a href="usuarios/listar.php" aria-label="Ver gestión de usuarios">
                    Ver Usuarios
                </a>
            </article>

            <article class="card">
                <h3><i class="fas fa-warehouse"></i> Gestión de Almacenes</h3>
                <p>Administrar ubicaciones de almacenes y asignaciones.</p>
                <a href="almacenes/listar.php" aria-label="Ver gestión de almacenes">
                    Ver Almacenes
                </a>
            </article>

            <article class="card">
                <h3><i class="fas fa-chart-line"></i> Reportes y Estadísticas</h3>
                <p>Generar reportes detallados del inventario y movimientos.</p>
                <a href="reportes/inventario.php" aria-label="Ver reportes del sistema">
                    Ver Reportes
                </a>
            </article>

            <article class="card">
                <h3><i class="fas fa-bell"></i> Centro de Notificaciones</h3>
                <p>Revisar todas las solicitudes y notificaciones del sistema.</p>
                <a href="notificaciones/pendientes.php" aria-label="Ver centro de notificaciones">
                    Ver Notificaciones
                    <?php if (isset($total_pendientes) && $total_pendientes > 0): ?>
                    <span class="badge" style="margin-left: 10px;"><?php echo $total_pendientes; ?></span>
                    <?php endif; ?>
                </a>
            </article>

            <article class="card">
                <h3><i class="fas fa-plus-circle"></i> Acciones Rápidas</h3>
                <p>Registrar nuevos usuarios, productos y almacenes.</p>
                <a href="usuarios/registrar.php" aria-label="Acceder a registro rápido">
                    Registrar Nuevo
                </a>
            </article>

            <article class="card">
                <h3><i class="fas fa-boxes"></i> Inventario General</h3>
                <p>Vista general del inventario en todos los almacenes.</p>
                <a href="productos/listar.php" aria-label="Ver inventario general">
                    Ver Inventario
                </a>
            </article>

            <?php else: ?>
            <!-- Regular User Dashboard Cards -->
            <article class="card">
                <h3><i class="fas fa-warehouse"></i> Mi Almacén</h3>
                <p>Ver información detallada de tu almacén asignado y productos disponibles.</p>
                <a href="almacenes/listar.php" aria-label="Ver información de mi almacén">
                    Ver Mi Almacén
                </a>
            </article>

            <article class="card">
                <h3><i class="fas fa-clock"></i> Solicitudes Pendientes</h3>
                <p>Revisar y gestionar solicitudes de transferencia pendientes de aprobación.</p>
                <a href="notificaciones/pendientes.php" aria-label="Ver solicitudes pendientes">
                    Ver Solicitudes
                    <?php if (isset($total_pendientes) && $total_pendientes > 0): ?>
                    <span class="badge" style="margin-left: 10px;"><?php echo $total_pendientes; ?></span>
                    <?php endif; ?>
                </a>
            </article>

            <article class="card">
                <h3><i class="fas fa-history"></i> Historial de Actividad</h3>
                <p>Consultar el historial completo de solicitudes y transferencias.</p>
                <a href="notificaciones/historial.php" aria-label="Ver historial de actividad">
                    Ver Historial
                </a>
            </article>

            <article class="card">
                <h3><i class="fas fa-boxes"></i> Productos Disponibles</h3>
                <p>Explorar el catálogo de productos disponibles en el sistema.</p>
                <a href="productos/listar.php" aria-label="Ver productos disponibles">
                    Ver Productos
                </a>
            </article>

            <article class="card">
                <h3><i class="fas fa-tshirt"></i> Entregas de Uniformes</h3>
                <p>Consultar historial de entregas de uniformes y equipamiento.</p>
                <a href="uniformes/historial_entregas_uniformes.php" aria-label="Ver entregas de uniformes">
                    Ver Entregas
                </a>
            </article>

            <article class="card">
                <h3><i class="fas fa-user-cog"></i> Mi Perfil</h3>
                <p>Gestionar configuración personal y cambiar contraseña.</p>
                <a href="perfil/configuracion.php" aria-label="Ver configuración de perfil">
                    Configurar Perfil
                </a>
            </article>
            <?php endif; ?>
        </section>
    </div>
</main>

<!-- Container for dynamic notifications -->
<div id="notificaciones-container" role="alert" aria-live="polite"></div>

<!-- JavaScript optimizado -->
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
            mainContent.classList.toggle('with-sidebar');
            
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
    
    // Cerrar menú móvil al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
                mainContent.classList.remove('with-sidebar');
                
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
            mainContent.classList.remove('with-sidebar');
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
    
    // Sistema de notificaciones
    window.mostrarNotificacion = function(mensaje, tipo = 'info', duracion = 5000) {
        const container = document.getElementById('notificaciones-container');
        const notificacion = document.createElement('div');
        notificacion.className = `notificacion ${tipo}`;
        
        notificacion.innerHTML = `
            ${mensaje}
            <button class="cerrar" aria-label="Cerrar notificación">&times;</button>
        `;
        
        container.appendChild(notificacion);
        
        // Cerrar notificación
        const cerrarBtn = notificacion.querySelector('.cerrar');
        cerrarBtn.addEventListener('click', function() {
            notificacion.remove();
        });
        
        // Auto-cerrar después del tiempo especificado
        if (duracion > 0) {
            setTimeout(() => {
                if (notificacion.parentNode) {
                    notificacion.remove();
                }
            }, duracion);
        }
    };
    
    // Actualizar badges de notificaciones (opcional, para tiempo real)
    function actualizarBadgesNotificaciones() {
        // Esta función puede ser llamada periódicamente para actualizar
        // los contadores de notificaciones sin recargar la página
        fetch('api/obtener_notificaciones_count.php')
            .then(response => response.json())
            .then(data => {
                const badges = document.querySelectorAll('.badge');
                badges.forEach(badge => {
                    if (data.pendientes > 0) {
                        badge.textContent = data.pendientes;
                        badge.style.display = 'inline-flex';
                    } else {
                        badge.style.display = 'none';
                    }
                });
            })
            .catch(error => {
                console.log('No se pudieron actualizar las notificaciones:', error);
            });
    }
    
    // Actualizar badges cada 30 segundos (opcional)
    // setInterval(actualizarBadgesNotificaciones, 30000);
    
    // Añadir efectos de hover mejorados a las tarjetas
    const cards = document.querySelectorAll('.card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
    
    // Mostrar notificación de bienvenida
    setTimeout(() => {
        mostrarNotificacion(
            `¡Bienvenido de vuelta, <?php echo htmlspecialchars($user_name); ?>!`, 
            'exito', 
            3000
        );
    }, 1000);
});

// Función para confirmar acciones importantes
function confirmarAccion(mensaje, callback) {
    if (confirm(mensaje)) {
        if (typeof callback === 'function') {
            callback();
        }
        return true;
    }
    return false;
}

// Manejo de errores globales
window.addEventListener('error', function(e) {
    console.error('Error detectado:', e.error);
    mostrarNotificacion('Se ha producido un error. Por favor, recarga la página.', 'error');
});

// Optimización de rendimiento: lazy loading para recursos no críticos
if ('IntersectionObserver' in window) {
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                imageObserver.unobserve(img);
            }
        });
    });
    
    document.querySelectorAll('img[data-src]').forEach(img => {
        imageObserver.observe(img);
    });
}
</script>

<!-- Estilos adicionales para navegación por teclado -->
<style>
.keyboard-navigation *:focus {
    outline: 3px solid #17a2b8 !important;
    outline-offset: 2px !important;
}

.lazy {
    opacity: 0;
    transition: opacity 0.3s;
}

.lazy.loaded {
    opacity: 1;
}

/* Mejora de accesibilidad para lectores de pantalla */
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
</style>

</body>
</html>