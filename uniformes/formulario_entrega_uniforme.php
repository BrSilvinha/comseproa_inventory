<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

session_regenerate_id(true);
require_once "../config/database.php";

$user_name = $_SESSION["user_name"] ?? "Usuario";
$usuario_almacen_id = $_SESSION["almacen_id"] ?? null;
$usuario_rol = $_SESSION["user_role"] ?? "usuario";

// ID de la categoría de uniformes/ropa según tu base de datos
$categoria_uniformes_id = 1; // Categoría "Ropa" en tu BD

// Obtener productos de uniformes disponibles (CONSULTA CORREGIDA)
$sql_productos = "SELECT p.id, p.nombre, p.cantidad, p.color, p.talla_dimensiones, 
                         a.nombre as almacen_nombre, p.almacen_id 
                  FROM productos p 
                  JOIN almacenes a ON p.almacen_id = a.id 
                  WHERE p.categoria_id = ? AND p.cantidad > 0";

// Filtrar por almacén si no es admin
if ($usuario_rol != 'admin' && $usuario_almacen_id) {
    $sql_productos .= " AND p.almacen_id = ?";
    $stmt = $conn->prepare($sql_productos);
    $stmt->bind_param("ii", $categoria_uniformes_id, $usuario_almacen_id);
} else {
    $stmt = $conn->prepare($sql_productos);
    $stmt->bind_param("i", $categoria_uniformes_id);
}

$stmt->execute();
$result_productos = $stmt->get_result();

// Convertir a array para JavaScript
$productos_array = [];
while ($producto = $result_productos->fetch_assoc()) {
    $productos_array[] = $producto;
}

// Obtener almacenes disponibles
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
    <title>Entrega de Uniformes - GRUPO SEAL</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Formulario de entrega de uniformes - Sistema de gestión GRUPO SEAL">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- CSS consistente -->
    <link rel="stylesheet" href="../assets/css/listar-usuarios.css">
    <link rel="stylesheet" href="../assets/css/uniformes-entrega.css">
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
        <li><a href="../dashboard.php"><span><i class="fas fa-home"></i> Inicio</span></a></li>

        <?php if ($usuario_rol == 'admin'): ?>
        <li class="submenu-container">
            <a href="#" aria-expanded="false" role="button">
                <span><i class="fas fa-users"></i> Usuarios</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <li><a href="../usuarios/registrar.php"><i class="fas fa-user-plus"></i> Registrar Usuario</a></li>
                <li><a href="../usuarios/listar.php"><i class="fas fa-list"></i> Lista de Usuarios</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <li class="submenu-container">
            <a href="#" aria-expanded="false" role="button">
                <span><i class="fas fa-warehouse"></i> Almacenes</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <?php if ($usuario_rol == 'admin'): ?>
                <li><a href="../almacenes/registrar.php"><i class="fas fa-plus"></i> Registrar Almacén</a></li>
                <?php endif; ?>
                <li><a href="../almacenes/listar.php"><i class="fas fa-list"></i> Lista de Almacenes</a></li>
            </ul>
        </li>

        <li class="submenu-container">
            <a href="#" aria-expanded="false" role="button">
                <span><i class="fas fa-boxes"></i> Productos</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <?php if ($usuario_rol == 'admin'): ?>
                <li><a href="../productos/registrar.php"><i class="fas fa-plus"></i> Registrar Producto</a></li>
                <?php endif; ?>
                <li><a href="../productos/listar.php"><i class="fas fa-list"></i> Lista de Productos</a></li>
                <li><a href="../productos/categorias.php"><i class="fas fa-tags"></i> Categorías</a></li>
            </ul>
        </li>

        <li class="submenu-container">
            <a href="#" aria-expanded="false" role="button">
                <span><i class="fas fa-tshirt"></i> Uniformes</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <li><a href="formulario_entrega_uniforme.php"><i class="fas fa-hand-holding"></i> Entregar Uniformes</a></li>
                <li><a href="historial_entregas_uniformes.php"><i class="fas fa-history"></i> Historial de Entregas</a></li>
            </ul>
        </li>
        
        <li class="submenu-container">
            <a href="#" aria-expanded="false" role="button">
                <span><i class="fas fa-bell"></i> Notificaciones</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <li>
                    <a href="../notificaciones/pendientes.php">
                        <i class="fas fa-clock"></i> Solicitudes Pendientes
                        <?php if ($total_pendientes > 0): ?>
                        <span class="badge-small"><?php echo $total_pendientes; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li><a href="../notificaciones/historial.php"><i class="fas fa-history"></i> Historial de Solicitudes</a></li>
            </ul>
        </li>

        <?php if ($usuario_rol == 'admin'): ?>
        <li class="submenu-container">
            <a href="#" aria-expanded="false" role="button">
                <span><i class="fas fa-chart-bar"></i> Reportes</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <li><a href="../reportes/inventario.php"><i class="fas fa-warehouse"></i> Inventario General</a></li>
                <li><a href="../reportes/movimientos.php"><i class="fas fa-exchange-alt"></i> Movimientos</a></li>
                <li><a href="../reportes/usuarios.php"><i class="fas fa-users"></i> Actividad de Usuarios</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <li class="submenu-container">
            <a href="#" aria-expanded="false" role="button">
                <span><i class="fas fa-user-circle"></i> Mi Perfil</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <li><a href="../perfil/configuracion.php"><i class="fas fa-cog"></i> Configuración</a></li>
                <li><a href="../perfil/cambiar-password.php"><i class="fas fa-key"></i> Cambiar Contraseña</a></li>
            </ul>
        </li>

        <li>
            <a href="#" onclick="manejarCerrarSesion(event)">
                <span><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</span>
            </a>
        </li>
    </ul>
</nav>

<!-- Contenido Principal -->
<main class="content" id="main-content" role="main">
    <!-- Header de la página -->
    <div class="page-header">
        <h1>
            <i class="fas fa-tshirt"></i>
            Entrega de Uniformes
        </h1>
        <p class="page-description">
            Registra la entrega de uniformes al personal de la empresa
        </p>
        
        <!-- Breadcrumb -->
        <nav class="breadcrumb">
            <a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a>
            <span>></span>
            <span>Uniformes</span>
            <span>></span>
            <span class="current">Entrega de Uniformes</span>
        </nav>
    </div>

    <!-- Formulario de entrega -->
    <div class="entrega-container">
        <div class="form-header">
            <div class="form-icon">
                <i class="fas fa-tshirt"></i>
            </div>
            <h2>Formulario de Entrega</h2>
            <p>Complete los datos del trabajador y seleccione los uniformes a entregar</p>
        </div>

        <form class="uniform-delivery-form" id="formulario-entrega" method="POST" action="procesar_entrega_uniforme.php">
            <!-- Información del trabajador -->
            <div class="destinatario-section">
                <h3 class="section-title">
                    <i class="fas fa-user"></i>
                    Información del Trabajador
                </h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="nombre_destinatario" class="form-label">
                            <i class="fas fa-user"></i>
                            Nombre Completo del Trabajador
                            <span class="required">*</span>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="nombre_destinatario" 
                               name="nombre_destinatario" 
                               placeholder="Ingrese el nombre completo del trabajador"
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

            <!-- Uniformes a entregar -->
            <div class="productos-section">
                <div class="productos-header">
                    <h3 class="section-title">
                        <i class="fas fa-tshirt"></i>
                        Uniformes a Entregar
                    </h3>
                    <button type="button" class="btn-add-producto" id="agregar-producto">
                        <i class="fas fa-plus"></i>
                        Agregar Uniforme
                    </button>
                </div>
                
                <div id="productos-container">
                    <!-- Los uniformes se agregarán dinámicamente aquí -->
                </div>

                <?php if (empty($productos_array)): ?>
                <div class="no-products-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>No hay uniformes disponibles en stock.</p>
                    <small>Contacte al administrador para agregar productos de la categoría uniformes.</small>
                </div>
                <?php endif; ?>
            </div>

            <!-- Acciones del formulario -->
            <?php if (!empty($productos_array)): ?>
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
            <?php endif; ?>
        </form>
    </div>
</main>

<!-- Container para notificaciones dinámicas -->
<div id="notificaciones-container" role="alert" aria-live="polite"></div>

<script>
// Uniformes disponibles desde PHP
const uniformesDisponibles = <?php echo json_encode($productos_array); ?>;
let contadorUniformes = 0;

document.addEventListener('DOMContentLoaded', function() {
    // Elementos del menú (reutilizado del dashboard)
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
                
                // Cerrar otros submenús
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
                
                // Toggle del submenú actual
                submenu.classList.toggle('activo');
                const isExpanded = submenu.classList.contains('activo');
                
                if (chevron) {
                    chevron.style.transform = isExpanded ? 'rotate(180deg)' : 'rotate(0deg)';
                }
            });
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

    // Solo proceder si hay uniformes disponibles
    if (uniformesDisponibles.length > 0) {
        // Agregar primer uniforme por defecto
        agregarUniforme();
        
        // Botón agregar uniforme
        document.getElementById('agregar-producto').addEventListener('click', agregarUniforme);
        
        // Procesar formulario de entrega
        document.getElementById('formulario-entrega').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!validarFormulario()) {
                return;
            }
            
            const formData = new FormData(this);
            
            // Mostrar indicador de carga
            const btnEntregar = document.getElementById('btn-entregar');
            const originalText = btnEntregar.innerHTML;
            btnEntregar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
            btnEntregar.disabled = true;
            
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
                    btnEntregar.innerHTML = originalText;
                    btnEntregar.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarNotificacion('Error de conexión. Intente nuevamente.', 'error');
                btnEntregar.innerHTML = originalText;
                btnEntregar.disabled = false;
            });
        });
    }

    // Validación de DNI en tiempo real
    document.getElementById('dni_destinatario').addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value.length > 8) {
            this.value = this.value.slice(0, 8);
        }
    });
});

// Función para agregar un nuevo uniforme
function agregarUniforme() {
    contadorUniformes++;
    
    const container = document.getElementById('productos-container');
    const uniformeDiv = document.createElement('div');
    uniformeDiv.className = 'producto-item';
    uniformeDiv.id = `uniforme-${contadorUniformes}`;
    
    let opcionesUniformes = '<option value="">Seleccione un uniforme</option>';
    uniformesDisponibles.forEach(uniforme => {
        const descripcion = uniforme.color || uniforme.talla_dimensiones ? 
            ` - ${uniforme.color || ''} ${uniforme.talla_dimensiones || ''}`.replace(/\s+/g, ' ').trim() : '';
        
        opcionesUniformes += `<option value="${uniforme.id}" data-stock="${uniforme.cantidad}" data-almacen="${uniforme.almacen_id}">
            ${uniforme.nombre}${descripcion} - ${uniforme.almacen_nombre} (Stock: ${uniforme.cantidad})
        </option>`;
    });
    
    uniformeDiv.innerHTML = `
        <div class="producto-header">
            <span class="producto-titulo">Uniforme ${contadorUniformes}</span>
            <button type="button" class="btn-remove-producto" onclick="removerUniforme(${contadorUniformes})">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <div class="producto-fields">
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-tshirt"></i>
                    Uniforme
                </label>
                <select class="form-select" name="producto_id[]" required>
                    ${opcionesUniformes}
                </select>
                <input type="hidden" name="producto_almacen[]" value="">
            </div>
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-hashtag"></i>
                    Cantidad
                </label>
                <div class="quantity-input">
                    <button type="button" class="qty-btn" onclick="cambiarCantidad(${contadorUniformes}, -1)">-</button>
                    <input type="number" class="qty-input" name="producto_cantidad[]" value="1" min="1" max="999" required>
                    <button type="button" class="qty-btn" onclick="cambiarCantidad(${contadorUniformes}, 1)">+</button>
                </div>
                <div class="stock-info" style="margin-top: 5px; font-size: 12px; color: #666;"></div>
            </div>
        </div>
    `;
    
    container.appendChild(uniformeDiv);
    
    // Agregar evento change al select para actualizar el almacén y validar stock
    const select = uniformeDiv.querySelector('select[name="producto_id[]"]');
    const hiddenAlmacen = uniformeDiv.querySelector('input[name="producto_almacen[]"]');
    const cantidadInput = uniformeDiv.querySelector('input[name="producto_cantidad[]"]');
    const stockInfo = uniformeDiv.querySelector('.stock-info');
    
    select.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            const stock = parseInt(selectedOption.dataset.stock);
            hiddenAlmacen.value = selectedOption.dataset.almacen;
            cantidadInput.max = stock;
            stockInfo.textContent = `Stock disponible: ${stock}`;
            
            // Ajustar cantidad si excede el stock
            if (parseInt(cantidadInput.value) > stock) {
                cantidadInput.value = stock;
            }
        } else {
            hiddenAlmacen.value = '';
            cantidadInput.max = 999;
            stockInfo.textContent = '';
        }
    });
    
    // Validar cantidad al cambiar
    cantidadInput.addEventListener('input', function() {
        const max = parseInt(this.max);
        if (parseInt(this.value) > max && max !== 999) {
            this.value = max;
        }
    });
}

// Función para remover un uniforme
function removerUniforme(id) {
    const uniforme = document.getElementById(`uniforme-${id}`);
    if (uniforme) {
        uniforme.remove();
    }
    
    // Asegurar que siempre quede al menos un uniforme
    const container = document.getElementById('productos-container');
    if (container.children.length === 0) {
        agregarUniforme();
    }
}

// Función para cambiar cantidad
function cambiarCantidad(uniformeId, cambio) {
    const input = document.querySelector(`#uniforme-${uniformeId} .qty-input`);
    const nuevaCantidad = parseInt(input.value) + cambio;
    const min = parseInt(input.min);
    const max = parseInt(input.max);
    
    if (nuevaCantidad >= min && nuevaCantidad <= max) {
        input.value = nuevaCantidad;
    }
}

// Función para validar el formulario
function validarFormulario() {
    const nombre = document.getElementById('nombre_destinatario').value.trim();
    const dni = document.getElementById('dni_destinatario').value.trim();
    const productos = document.querySelectorAll('select[name="producto_id[]"]');
    
    if (!nombre) {
        mostrarNotificacion('El nombre del trabajador es obligatorio', 'error');
        return false;
    }
    
    if (!dni || dni.length !== 8) {
        mostrarNotificacion('El DNI debe tener exactamente 8 dígitos', 'error');
        return false;
    }
    
    let hayProductosSeleccionados = false;
    productos.forEach(select => {
        if (select.value) {
            hayProductosSeleccionados = true;
        }
    });
    
    if (!hayProductosSeleccionados) {
        mostrarNotificacion('Debe seleccionar al menos un uniforme', 'error');
        return false;
    }
    
    return true;
}

// Función para mostrar notificaciones
function mostrarNotificacion(mensaje, tipo = 'info') {
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
    
    // Auto-remover después de 5 segundos
    setTimeout(() => {
        if (notificacion.parentElement) {
            notificacion.style.animation = 'slideOutRight 0.3s ease-in';
            setTimeout(() => notificacion.remove(), 300);
        }
    }, 5000);
}

// Función para cerrar sesión con confirmación
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