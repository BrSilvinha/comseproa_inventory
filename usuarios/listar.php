<?php
session_start();
require_once "../config/database.php"; // Incluir archivo de conexión

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_name = $_SESSION["user_name"] ?? "Usuario";
$usuario_rol = $_SESSION["user_role"] ?? "usuario";
$usuario_almacen_id = $_SESSION["almacen_id"] ?? null;

session_regenerate_id(true);

// Restricción de acceso basada en roles
if ($usuario_rol !== 'admin') {
    // Si no es admin, redirigir al dashboard
    header("Location: ../dashboard.php");
    exit();
}

// Manejo de AJAX para eliminar usuario o cambiar estado
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"], $_POST["id"])) {
    header("Content-Type: application/json");
    $response = ["success" => false];

    $id = (int) $_POST["id"]; // Convertir ID a entero para mayor seguridad

    if ($_POST["action"] === "delete") {
        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $id);
        $response["success"] = $stmt->execute();
        $stmt->close();
    } elseif ($_POST["action"] === "toggle_status") {
        $stmt = $conn->prepare("UPDATE usuarios SET estado = IF(estado = 'Activo', 'Inactivo', 'Activo') WHERE id = ?");
        $stmt->bind_param("i", $id);
        $response["success"] = $stmt->execute();
        $stmt->close();
    }

    echo json_encode($response);
    exit();
}

// Paginación
$usuarios_por_pagina = 5;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $usuarios_por_pagina;

// Obtener total de usuarios (para calcular la paginación)
$query_total = "SELECT COUNT(*) AS total FROM usuarios WHERE estado != 'Eliminado'";
$result_total = $conn->query($query_total);
$total_usuarios = $result_total->fetch_assoc()["total"];
$total_paginas = ceil($total_usuarios / $usuarios_por_pagina);

// Obtener los almacenes para la lista de selección
$almacenes_query = "SELECT id, nombre FROM almacenes";
$almacenes_result = $conn->query($almacenes_query);
$almacenes = $almacenes_result->fetch_all(MYSQLI_ASSOC);

// Obtener lista de usuarios con filtros
$where = " WHERE u.estado != 'Eliminado' ";
$params = [];
$types = "";

// Filtros dinámicos
if (!empty($_GET["nombre"])) {
    $where .= " AND u.nombre LIKE ?";
    $params[] = "%" . $_GET["nombre"] . "%";
    $types .= "s";
}
if (!empty($_GET["dni"])) {
    $where .= " AND u.dni = ?";
    $params[] = $_GET["dni"];
    $types .= "s";
}
if (!empty($_GET["estado"])) {
    $where .= " AND u.estado = ?";
    $params[] = $_GET["estado"];
    $types .= "s";
}
if (!empty($_GET["almacen"])) {
    $where .= " AND a.nombre = ?";
    $params[] = $_GET["almacen"];
    $types .= "s";
}

$query = "SELECT u.id, u.nombre, u.apellidos, u.dni, u.correo, u.rol, u.estado, a.nombre AS almacen 
          FROM usuarios u 
          LEFT JOIN almacenes a ON u.almacen_id = a.id
          $where
          LIMIT $usuarios_por_pagina OFFSET $offset";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$usuarios = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Contar solicitudes pendientes para mostrar en el badge
$sql_pendientes = "SELECT COUNT(*) as total FROM solicitudes_transferencia WHERE estado = 'pendiente'";
$result_pendientes = $conn->query($sql_pendientes);
$pendientes_count = $result_pendientes ? $result_pendientes->fetch_assoc()['total'] : 0;

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Usuarios - COMSEPROA</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <!-- jQuery para funcionalidad AJAX -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    
    <!-- CSS específico para listar usuarios -->
    <link rel="stylesheet" href="../assets/css/listar-usuarios.css">
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Lista y gestión de usuarios del sistema COMSEPROA">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon.ico">
</head>
<body>
    <!-- Botón de hamburguesa para dispositivos móviles -->
    <button class="menu-toggle" id="menuToggle" aria-label="Abrir menú de navegación">
        <i class="fas fa-bars"></i>
    </button>

    <div class="dashboard-container">
        <!-- Menú Lateral -->
        <nav class="sidebar" id="sidebar" role="navigation" aria-label="Menú principal">
            <h2>COMSEPROA</h2>
            <ul>
                <li><a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a></li>

                <!-- Usuarios - Solo visible para administradores -->
                <li class="submenu-container">
                    <a href="#" aria-label="Menú Usuarios" aria-expanded="false" role="button" tabindex="0">
                        <span><i class="fas fa-users"></i> Usuarios</span>
                        <i class="fas fa-chevron-down"></i>
                    </a>
                    <ul class="submenu" role="menu">
                        <li><a href="registrar.php" role="menuitem"><i class="fas fa-user-plus"></i> Registrar Usuario</a></li>
                        <li><a href="listar.php" role="menuitem"><i class="fas fa-list"></i> Lista de Usuarios</a></li>
                    </ul>
                </li>

                <!-- Almacenes -->
                <li class="submenu-container">
                    <a href="#" aria-label="Menú Almacenes" aria-expanded="false" role="button" tabindex="0">
                        <span><i class="fas fa-warehouse"></i> Almacenes</span>
                        <i class="fas fa-chevron-down"></i>
                    </a>
                    <ul class="submenu" role="menu">
                        <li><a href="../almacenes/registrar.php" role="menuitem"><i class="fas fa-plus"></i> Registrar Almacén</a></li>
                        <li><a href="../almacenes/listar.php" role="menuitem"><i class="fas fa-list"></i> Lista de Almacenes</a></li>
                    </ul>
                </li>
                
                <!-- Notificaciones -->
                <li class="submenu-container">
                    <a href="#" aria-label="Menú Notificaciones" aria-expanded="false" role="button" tabindex="0">
                        <span><i class="fas fa-bell"></i> Notificaciones</span>
                        <i class="fas fa-chevron-down"></i>
                    </a>
                    <ul class="submenu" role="menu">
                        <li>
                            <a href="../notificaciones/pendientes.php" role="menuitem">
                                <i class="fas fa-clock"></i> Solicitudes Pendientes 
                                <?php if ($pendientes_count > 0): ?>
                                <span class="badge" aria-label="<?= $pendientes_count ?> solicitudes pendientes"><?= $pendientes_count ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li><a href="../notificaciones/historial.php" role="menuitem"><i class="fas fa-history"></i> Historial de Solicitudes</a></li>
                        <li><a href="../uniformes/historial_entregas_uniformes.php" role="menuitem"><i class="fas fa-tshirt"></i> Historial de Entregas</a></li>
                    </ul>
                </li>

                <!-- Cerrar Sesión -->
                <li>
                    <a href="../logout.php" onclick="return confirm('¿Estás seguro de que deseas cerrar sesión?')">
                        <i class="fas fa-sign-out-alt"></i> Cerrar sesión
                    </a>
                </li>
            </ul>
        </nav>

        <main class="content" id="main-content" role="main">
            <header>
                <h1>Lista de Usuarios</h1>
            </header>

            <!-- Mensaje de éxito si existe -->
            <?php if (isset($_GET['mensaje'])): ?>
            <div class="mensaje exito" role="alert">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($_GET['mensaje']) ?>
            </div>
            <?php endif; ?>

            <!-- Formulario de Búsqueda -->
            <form method="GET" action="listar.php" class="filter-form" role="search" aria-label="Filtros de búsqueda">
                <input type="text" name="nombre" placeholder="Buscar por nombre" value="<?= htmlspecialchars($_GET['nombre'] ?? '') ?>" aria-label="Buscar por nombre">
                <input type="text" name="dni" placeholder="Buscar por DNI" value="<?= htmlspecialchars($_GET['dni'] ?? '') ?>" aria-label="Buscar por DNI" maxlength="8">
                <select name="estado" aria-label="Filtrar por estado">
                    <option value="">Todos los estados</option>
                    <option value="Activo" <?= (isset($_GET["estado"]) && $_GET["estado"] == "Activo") ? "selected" : "" ?>>Activo</option>
                    <option value="Inactivo" <?= (isset($_GET["estado"]) && $_GET["estado"] == "Inactivo") ? "selected" : "" ?>>Inactivo</option>
                </select>
                <select name="almacen" aria-label="Filtrar por almacén">
                    <option value="">Todos los almacenes</option>
                    <?php foreach ($almacenes as $almacen): ?>
                        <option value="<?= htmlspecialchars($almacen["nombre"]) ?>" <?= (isset($_GET["almacen"]) && $_GET["almacen"] == $almacen["nombre"]) ? "selected" : "" ?>>
                            <?= htmlspecialchars($almacen["nombre"]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" aria-label="Aplicar filtros">
                    <i class="fas fa-search"></i> Buscar
                </button>
                <a href="listar.php" class="reset-btn" aria-label="Limpiar filtros">
                    <i class="fas fa-eraser"></i> Restablecer
                </a>
            </form>

            <!-- Tabla de Usuarios -->
            <div class="table-responsive">
                <table class="usuarios-table" role="table" aria-label="Lista de usuarios del sistema">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Nombre</th>
                            <th scope="col">Apellidos</th>
                            <th scope="col">DNI</th>
                            <th scope="col">Correo</th>
                            <th scope="col">Rol</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Almacén</th>
                            <th scope="col">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 60px; color: #666;">
                                <i class="fas fa-users" style="font-size: 64px; margin-bottom: 20px; display: block; opacity: 0.3;"></i>
                                <strong style="font-size: 18px; display: block; margin-bottom: 10px;">No se encontraron usuarios</strong>
                                <span style="font-size: 14px;">Intenta ajustar los filtros de búsqueda</span>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($usuarios as $usuario): ?>
                            <tr id="user-<?= $usuario['id'] ?>">
                                <td><strong>#<?= $usuario['id'] ?></strong></td>
                                <td><?= htmlspecialchars($usuario['nombre']) ?></td>
                                <td><?= htmlspecialchars($usuario['apellidos']) ?></td>
                                <td><code><?= htmlspecialchars($usuario['dni']) ?></code></td>
                                <td><?= htmlspecialchars($usuario['correo']) ?></td>
                                <td>
                                    <span class="rol-badge rol-<?= $usuario['rol'] ?>">
                                        <?= ucfirst(htmlspecialchars($usuario['rol'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="estado-badge estado-<?= strtolower($usuario['estado']) ?>">
                                        <?= htmlspecialchars($usuario['estado']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($usuario['almacen'] ?? 'Sin asignar') ?></td>
                                <td class="acciones">
                                    <button type="button" onclick="editUser(<?= $usuario['id'] ?>)" title="Editar usuario" aria-label="Editar usuario <?= htmlspecialchars($usuario['nombre']) ?>">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <button type="button" onclick="deleteUser(<?= $usuario['id'] ?>)" title="Eliminar usuario" aria-label="Eliminar usuario <?= htmlspecialchars($usuario['nombre']) ?>">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                    <button type="button" onclick="toggleStatus(<?= $usuario['id'] ?>)" title="Cambiar estado" aria-label="<?= $usuario['estado'] === 'Activo' ? 'Inhabilitar' : 'Habilitar' ?> usuario <?= htmlspecialchars($usuario['nombre']) ?>">
                                        <i class="fas fa-<?= $usuario['estado'] === 'Activo' ? 'ban' : 'check' ?>"></i>
                                        <?= $usuario['estado'] === 'Activo' ? 'Inhabilitar' : 'Habilitar' ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <?php if ($total_paginas > 1): ?>
            <nav class="pagination" role="navigation" aria-label="Navegación de páginas">
                <?php if ($pagina_actual > 1): ?>
                <a href="?pagina=<?= $pagina_actual - 1 ?><?= http_build_query(array_filter($_GET, function($key) { return $key !== 'pagina'; }, ARRAY_FILTER_USE_KEY)) ? '&' . http_build_query(array_filter($_GET, function($key) { return $key !== 'pagina'; }, ARRAY_FILTER_USE_KEY)) : '' ?>" class="prev" aria-label="Página anterior">
                    <i class="fas fa-chevron-left"></i> Anterior
                </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <a href="?pagina=<?= $i ?><?= http_build_query(array_filter($_GET, function($key) { return $key !== 'pagina'; }, ARRAY_FILTER_USE_KEY)) ? '&' . http_build_query(array_filter($_GET, function($key) { return $key !== 'pagina'; }, ARRAY_FILTER_USE_KEY)) : '' ?>" 
                       class="<?= $i == $pagina_actual ? 'active' : '' ?>" 
                       aria-label="Página <?= $i ?>"
                       <?= $i == $pagina_actual ? 'aria-current="page"' : '' ?>>
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($pagina_actual < $total_paginas): ?>
                <a href="?pagina=<?= $pagina_actual + 1 ?><?= http_build_query(array_filter($_GET, function($key) { return $key !== 'pagina'; }, ARRAY_FILTER_USE_KEY)) ? '&' . http_build_query(array_filter($_GET, function($key) { return $key !== 'pagina'; }, ARRAY_FILTER_USE_KEY)) : '' ?>" class="next" aria-label="Página siguiente">
                    Siguiente <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </nav>
            <?php endif; ?>

            <!-- Información de resultados -->
            <div class="results-info" role="status" aria-live="polite">
                <i class="fas fa-info-circle"></i>
                Mostrando <strong><?= count($usuarios) ?></strong> de <strong><?= $total_usuarios ?></strong> usuarios
                <?php if ($total_paginas > 1): ?>
                (Página <strong><?= $pagina_actual ?></strong> de <strong><?= $total_paginas ?></strong>)
                <?php endif; ?>
            </div>

            <!-- Enlaces de navegación rápida -->
            <div style="text-align: center; margin-top: 30px; padding: 25px; background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(10, 37, 60, 0.1);">
                <a href="registrar.php" style="background: #28a745; color: white; padding: 12px 20px; text-decoration: none; border-radius: 6px; margin: 0 10px; display: inline-flex; align-items: center; gap: 8px; font-weight: 600;">
                    <i class="fas fa-user-plus"></i> Registrar Nuevo Usuario
                </a>
                <a href="../dashboard.php" style="background: #6c757d; color: white; padding: 12px 20px; text-decoration: none; border-radius: 6px; margin: 0 10px; display: inline-flex; align-items: center; gap: 8px; font-weight: 600;">
                    <i class="fas fa-home"></i> Volver al Dashboard
                </a>
            </div>
        </main>
    </div>

    <!-- Contenedor para notificaciones dinámicas -->
    <div id="notificaciones-container" role="alert" aria-live="polite"></div>

    <!-- JavaScript mejorado -->
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
        
        // Navegación por teclado
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                if (mainContent) {
                    mainContent.classList.remove('with-sidebar');
                }
                menuToggle.focus();
            }
            
            if (e.key === 'Tab') {
                document.body.classList.add('keyboard-navigation');
            }
        });
        
        document.addEventListener('mousedown', function() {
            document.body.classList.remove('keyboard-navigation');
        });
        
        // Sistema de notificaciones
        window.mostrarNotificacion = function(mensaje, tipo = 'info', duracion = 5000) {
            let container = document.getElementById('notificaciones-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'notificaciones-container';
                document.body.appendChild(container);
            }
            
            const notificacion = document.createElement('div');
            notificacion.className = `notificacion ${tipo}`;
            
            notificacion.innerHTML = `
                ${mensaje}
                <button class="cerrar" aria-label="Cerrar notificación">&times;</button>
            `;
            
            container.appendChild(notificacion);
            
            const cerrarBtn = notificacion.querySelector('.cerrar');
            cerrarBtn.addEventListener('click', function() {
                notificacion.remove();
            });
            
            if (duracion > 0) {
                setTimeout(() => {
                    if (notificacion.parentNode) {
                        notificacion.remove();
                    }
                }, duracion);
            }
        };
        
        // Mostrar mensajes existentes como notificaciones
        const mensajes = document.querySelectorAll('.mensaje');
        mensajes.forEach(mensaje => {
            const texto = mensaje.textContent.trim();
            const tipo = mensaje.classList.contains('exito') ? 'exito' : 'error';
            if (texto) {
                setTimeout(() => {
                    mostrarNotificacion(texto, tipo);
                    mensaje.style.display = 'none';
                }, 500);
            }
        });

        // Auto-expandir el submenú de usuarios
        setTimeout(() => {
            const usuariosSubmenu = document.querySelector('.submenu-container');
            if (usuariosSubmenu) {
                const link = usuariosSubmenu.querySelector('a');
                const submenu = usuariosSubmenu.querySelector('.submenu');
                const chevron = usuariosSubmenu.querySelector('.fa-chevron-down');
                
                if (link && submenu) {
                    submenu.classList.add('activo');
                    if (chevron) {
                        chevron.style.transform = 'rotate(180deg)';
                    }
                    link.setAttribute('aria-expanded', 'true');
                }
            }
        }, 100);

        // Mostrar notificación de bienvenida
        setTimeout(() => {
            mostrarNotificacion('Lista de usuarios cargada correctamente', 'exito', 3000);
        }, 1000);
    });

    // Funciones específicas para gestión de usuarios
    function editUser(userId) {
        // Añadir efecto visual
        const userRow = document.getElementById('user-' + userId);
        userRow.style.background = 'rgba(23, 162, 184, 0.1)';
        userRow.style.transform = 'scale(1.02)';
        
        setTimeout(() => {
            window.location.href = "editar_usuario.php?id=" + userId;
        }, 200);
    }

    function deleteUser(userId) {
        if (confirm("¿Estás seguro de que deseas eliminar este usuario? Esta acción no se puede deshacer.")) {
            const userRow = document.getElementById('user-' + userId);
            userRow.style.opacity = '0.5';
            userRow.style.pointerEvents = 'none';
            userRow.classList.add('loading-row');
            
            if (typeof $ !== 'undefined') {
                $.post("listar.php", { action: "delete", id: userId }, function (response) {
                    if (response.success) {
                        userRow.classList.add('fade-out');
                        
                        setTimeout(() => {
                            userRow.remove();
                            // Actualizar contador
                            const resultsInfo = document.querySelector('.results-info');
                            if (resultsInfo) {
                                const currentCount = parseInt(resultsInfo.textContent.match(/\d+/)[0]) - 1;
                                resultsInfo.innerHTML = resultsInfo.innerHTML.replace(/\d+/, currentCount);
                            }
                        }, 300);
                        
                        mostrarNotificacion('Usuario eliminado correctamente', 'exito');
                    } else {
                        userRow.style.opacity = '1';
                        userRow.style.pointerEvents = 'auto';
                        userRow.classList.remove('loading-row');
                        mostrarNotificacion('Error al eliminar usuario', 'error');
                    }
                }, "json").fail(function() {
                    userRow.style.opacity = '1';
                    userRow.style.pointerEvents = 'auto';
                    userRow.classList.remove('loading-row');
                    mostrarNotificacion('Error de conexión al eliminar usuario', 'error');
                });
            } else {
                // Fallback si jQuery no está disponible
                fetch('listar.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=delete&id=' + userId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        userRow.remove();
                        mostrarNotificacion('Usuario eliminado correctamente', 'exito');
                    } else {
                        userRow.style.opacity = '1';
                        userRow.style.pointerEvents = 'auto';
                        userRow.classList.remove('loading-row');
                        mostrarNotificacion('Error al eliminar usuario', 'error');
                    }
                })
                .catch(error => {
                    userRow.style.opacity = '1';
                    userRow.style.pointerEvents = 'auto';
                    userRow.classList.remove('loading-row');
                    mostrarNotificacion('Error de conexión al eliminar usuario', 'error');
                });
            }
        }
    }

    function toggleStatus(userId) {
        const userRow = document.getElementById('user-' + userId);
        const statusBadge = userRow.querySelector('.estado-badge');
        const originalStatus = statusBadge.textContent.trim();
        
        // Efecto visual temporal
        statusBadge.style.opacity = '0.5';
        
        if (typeof $ !== 'undefined') {
            $.post("listar.php", { action: "toggle_status", id: userId }, function (response) {
                if (response.success) {
                    setTimeout(() => {
                        location.reload();
                    }, 300);
                    mostrarNotificacion('Estado del usuario actualizado correctamente', 'exito');
                } else {
                    statusBadge.style.opacity = '1';
                    mostrarNotificacion('Error al actualizar estado del usuario', 'error');
                }
            }, "json").fail(function() {
                statusBadge.style.opacity = '1';
                mostrarNotificacion('Error de conexión al actualizar estado', 'error');
            });
        } else {
            // Fallback si jQuery no está disponible
            fetch('listar.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=toggle_status&id=' + userId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    statusBadge.style.opacity = '1';
                    mostrarNotificacion('Error al actualizar estado del usuario', 'error');
                }
            })
            .catch(error => {
                statusBadge.style.opacity = '1';
                mostrarNotificacion('Error de conexión al actualizar estado', 'error');
            });
        }
    }
    </script>
</body>
</html>