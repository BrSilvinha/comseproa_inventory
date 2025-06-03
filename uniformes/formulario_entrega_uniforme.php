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

// Obtener productos de uniformes disponibles
$sql_productos = "SELECT p.id, p.nombre, p.cantidad, a.nombre as almacen_nombre, p.almacen_id 
                 FROM productos p 
                 JOIN almacenes a ON p.almacen_id = a.id 
                 WHERE p.categoria = 'uniforme' AND p.cantidad > 0";

if ($usuario_rol != 'admin' && $usuario_almacen_id) {
    $sql_productos .= " AND p.almacen_id = ?";
    $stmt = $conn->prepare($sql_productos);
    $stmt->bind_param("i", $usuario_almacen_id);
    $stmt->execute();
    $result_productos = $stmt->get_result();
} else {
    $result_productos = $conn->query($sql_productos);
}

// Obtener almacenes
if ($usuario_rol == 'admin') {
    $sql_almacenes = "SELECT id, nombre FROM almacenes ORDER BY nombre";
    $result_almacenes = $conn->query($sql_almacenes);
} else {
    $sql_almacenes = "SELECT id, nombre FROM almacenes WHERE id = ?";
    $stmt_almacenes = $conn->prepare($sql_almacenes);
    $stmt_almacenes->bind_param("i", $usuario_almacen_id);
    $stmt_almacenes->execute();
    $result_almacenes = $stmt_almacenes->get_result();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrega de Uniformes - GRUPO SEAL</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Formulario de entrega de uniformes - Sistema de gestión GRUPO SEAL">
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
    <link rel="stylesheet" href="../assets/css/uniformes-entrega.css">
    
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
    <!-- Page Header -->
    <div class="page-header">
        <h1>Entrega de Uniformes</h1>
        <p class="page-description">
            Registra la entrega de uniformes al personal asignado
        </p>
        <nav class="breadcrumb">
            <a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a>
            <span>></span>
            <span>Uniformes</span>
            <span>></span>
            <span class="current">Entrega de Uniformes</span>
        </nav>
    </div>

    <!-- Form Container -->
    <div class="entrega-container">
        <!-- Form Header -->
        <div class="form-header">
            <div class="form-icon">
                <i class="fas fa-tshirt"></i>
            </div>
            <h2>Formulario de Entrega</h2>
            <p>Complete los datos del destinatario y seleccione los uniformes a entregar</p>
        </div>

        <!-- Delivery Form -->
        <form class="uniform-delivery-form" id="formulario-entrega" method="POST" action="procesar_entrega_uniforme.php">
            <!-- Recipient Information -->
            <div class="destinatario-section">
                <h3 class="section-title">
                    <i class="fas fa-user"></i>
                    Información del Destinatario
                </h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="nombre_destinatario" class="form-label">
                            <i class="fas fa-user"></i>
                            Nombre Completo
                            <span class="required">*</span>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="nombre_destinatario" 
                               name="nombre_destinatario" 
                               placeholder="Ingrese el nombre completo del destinatario"
                               required>
                        <div class="field-hint">
                            <i class="fas fa-info-circle"></i>
                            Nombre y apellidos del empleado que recibirá los uniformes
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="dni_destinatario" class="form-label">
                            <i class="fas fa-id-card"></i>
                            Número de DNI
                            <span class="required">*</span>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="dni_destinatario" 
                               name="dni_destinatario" 
                               placeholder="12345678"
                               pattern="[0-9]{8}"
                               maxlength="8"
                               required>
                        <div class="field-hint">
                            <i class="fas fa-info-circle"></i>
                            Documento Nacional de Identidad (8 dígitos)
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Section -->
            <div class="productos-section">
                <div class="productos-header">
                    <h3 class="section-title">
                        <i class="fas fa-boxes"></i>
                        Productos a Entregar
                    </h3>
                    <button type="button" class="btn-add-producto" id="agregar-producto">
                        <i class="fas fa-plus"></i>
                        Agregar Producto
                    </button>
                </div>
                
                <div id="productos-container">
                    <!-- Los productos se agregarán dinámicamente aquí -->
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn-submit" id="btn-entregar">
                    <i class="fas fa-hand-holding"></i>
                    Realizar Entrega
                </button>
                <a href="historial_entregas_uniformes.php" class="btn-cancel">
                    <i class="fas fa-times"></i>
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</main>

<!-- Container for dynamic notifications -->
<div id="notificaciones-container" role="alert" aria-live="polite"></div>

<!-- JavaScript del Dashboard -->
<script src="../assets/js/universal-confirmation-system.js"></script>
<script>
// Productos disponibles (desde PHP)
const productosDisponibles = <?php echo json_encode($result_productos->fetch_all(MYSQLI_ASSOC)); ?>;

// Contador de productos
let contadorProductos = 0;

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

    // Agregar primer producto por defecto
    agregarProducto();
    
    // Botón agregar producto
    document.getElementById('agregar-producto').addEventListener('click', agregarProducto);
    
    // Validación del formulario
    document.getElementById('formulario-entrega').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        // Enviar datos
        fetch('procesar_entrega_uniforme.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarNotificacion(data.message, 'exito');
                setTimeout(() => {
                    window.location.href = 'historial_entregas_uniformes.php';
                }, 2000);
            } else {
                mostrarNotificacion(data.error || 'Error al procesar la entrega', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error de conexión', 'error');
        });
    });

    // Validación de DNI
    document.getElementById('dni_destinatario').addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
});

// Función para agregar un nuevo producto
function agregarProducto() {
    contadorProductos++;
    
    const container = document.getElementById('productos-container');
    const productoDiv = document.createElement('div');
    productoDiv.className = 'producto-item';
    productoDiv.id = `producto-${contadorProductos}`;
    
    productoDiv.innerHTML = `
        <div class="producto-header">
            <span class="producto-titulo">Producto ${contadorProductos}</span>
            <button type="button" class="btn-remove-producto" onclick="removerProducto(${contadorProductos})">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <div class="producto-fields">
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-tshirt"></i>
                    Producto
                </label>
                <select class="form-select" name="producto_id[]" required>
                    <option value="">Seleccione un producto</option>
                    ${productosDisponibles.map(producto => 
                        `<option value="${producto.id}" data-stock="${producto.cantidad}" data-almacen="${producto.almacen_id}">
                            ${producto.nombre} - ${producto.almacen_nombre} (Stock: ${producto.cantidad})
                        </option>`
                    ).join('')}
                </select>
                <input type="hidden" name="producto_almacen[]" value="">
            </div>
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-hashtag"></i>
                    Cantidad
                </label>
                <div class="quantity-input">
                    <button type="button" class="qty-btn" onclick="cambiarCantidad(${contadorProductos}, -1)">-</button>
                    <input type="number" class="qty-input" name="producto_cantidad[]" value="1" min="1" max="999" required>
                    <button type="button" class="qty-btn" onclick="cambiarCantidad(${contadorProductos}, 1)">+</button>
                </div>
            </div>
        </div>
    `;
    
    container.appendChild(productoDiv);
    
    // Agregar evento change al select para actualizar el almacén
    const select = productoDiv.querySelector('select[name="producto_id[]"]');
    const hiddenAlmacen = productoDiv.querySelector('input[name="producto_almacen[]"]');
    
    select.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            hiddenAlmacen.value = selectedOption.dataset.almacen;
        } else {
            hiddenAlmacen.value = '';
        }
    });
}

// Función para remover un producto
function removerProducto(id) {
    const producto = document.getElementById(`producto-${id}`);
    if (producto) {
        producto.remove();
    }
}

// Función para cambiar cantidad
function cambiarCantidad(productoId, cambio) {
    const input = document.querySelector(`#producto-${productoId} .qty-input`);
    const nuevaCantidad = parseInt(input.value) + cambio;
    if (nuevaCantidad >= 1) {
        input.value = nuevaCantidad;
    }
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