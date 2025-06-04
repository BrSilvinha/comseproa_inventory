<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

// Prevent session hijacking
session_regenerate_id(true);

$user_id = $_SESSION["user_id"];
$user_name = isset($_SESSION["user_name"]) ? $_SESSION["user_name"] : "Usuario";
$usuario_almacen_id = isset($_SESSION["almacen_id"]) ? $_SESSION["almacen_id"] : null;
$usuario_rol = isset($_SESSION["user_role"]) ? $_SESSION["user_role"] : "usuario";

// Require database connection
require_once "../config/database.php";

// Variables para mensajes
$mensaje = "";
$tipo_mensaje = "";

// ===== OBTENER DATOS DEL USUARIO =====
$user_data = [];
$sql_user = "SELECT u.*, a.nombre as almacen_nombre FROM usuarios u 
             LEFT JOIN almacenes a ON u.almacen_id = a.id 
             WHERE u.id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

if ($result_user && $row_user = $result_user->fetch_assoc()) {
    $user_data = $row_user;
} else {
    $mensaje = "Error al cargar los datos del usuario.";
    $tipo_mensaje = "error";
}
$stmt_user->close();

// ===== PROCESAR FORMULARIO DE CONFIGURACIÓN =====
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    
    if ($_POST['action'] == 'actualizar_perfil') {
        // Validar y sanitizar datos
        $nombre = trim($_POST['nombre'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $departamento = trim($_POST['departamento'] ?? '');
        $cargo = trim($_POST['cargo'] ?? '');
        
        // Validaciones básicas
        $errores = [];
        
        if (empty($nombre)) {
            $errores[] = "El nombre es obligatorio.";
        }
        
        if (empty($apellidos)) {
            $errores[] = "Los apellidos son obligatorios.";
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = "El email es obligatorio y debe ser válido.";
        }
        
        // Verificar si el email ya existe (excepto el usuario actual)
        if (!empty($email)) {
            $sql_check_email = "SELECT id FROM usuarios WHERE email = ? AND id != ?";
            $stmt_check = $conn->prepare($sql_check_email);
            $stmt_check->bind_param("si", $email, $user_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check->num_rows > 0) {
                $errores[] = "El email ya está registrado por otro usuario.";
            }
            $stmt_check->close();
        }
        
        if (empty($errores)) {
            // Actualizar datos del usuario
            $sql_update = "UPDATE usuarios SET 
                          nombre = ?, 
                          apellidos = ?, 
                          email = ?, 
                          telefono = ?, 
                          departamento = ?, 
                          cargo = ?,
                          fecha_actualizacion = CURRENT_TIMESTAMP 
                          WHERE id = ?";
            
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("ssssssi", $nombre, $apellidos, $email, $telefono, $departamento, $cargo, $user_id);
            
            if ($stmt_update->execute()) {
                // Actualizar datos de sesión
                $_SESSION["user_name"] = $nombre . ' ' . $apellidos;
                
                // Recargar datos del usuario
                $user_data['nombre'] = $nombre;
                $user_data['apellidos'] = $apellidos;
                $user_data['email'] = $email;
                $user_data['telefono'] = $telefono;
                $user_data['departamento'] = $departamento;
                $user_data['cargo'] = $cargo;
                
                $mensaje = "Perfil actualizado correctamente.";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al actualizar el perfil.";
                $tipo_mensaje = "error";
            }
            $stmt_update->close();
        } else {
            $mensaje = implode("<br>", $errores);
            $tipo_mensaje = "error";
        }
    }
    
    // Procesar configuración de notificaciones
    if ($_POST['action'] == 'actualizar_notificaciones') {
        $notif_email = isset($_POST['notif_email']) ? 1 : 0;
        $notif_sistema = isset($_POST['notif_sistema']) ? 1 : 0;
        $notif_transferencias = isset($_POST['notif_transferencias']) ? 1 : 0;
        $notif_solicitudes = isset($_POST['notif_solicitudes']) ? 1 : 0;
        
        // Crear tabla de configuraciones si no existe
        $sql_create_config = "CREATE TABLE IF NOT EXISTS configuraciones_usuario (
            id INT PRIMARY KEY AUTO_INCREMENT,
            usuario_id INT NOT NULL,
            notif_email TINYINT(1) DEFAULT 1,
            notif_sistema TINYINT(1) DEFAULT 1,
            notif_transferencias TINYINT(1) DEFAULT 1,
            notif_solicitudes TINYINT(1) DEFAULT 1,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            UNIQUE KEY unique_usuario (usuario_id)
        )";
        $conn->query($sql_create_config);
        
        // Actualizar o insertar configuraciones
        $sql_config = "INSERT INTO configuraciones_usuario 
                      (usuario_id, notif_email, notif_sistema, notif_transferencias, notif_solicitudes) 
                      VALUES (?, ?, ?, ?, ?) 
                      ON DUPLICATE KEY UPDATE 
                      notif_email = VALUES(notif_email), 
                      notif_sistema = VALUES(notif_sistema),
                      notif_transferencias = VALUES(notif_transferencias),
                      notif_solicitudes = VALUES(notif_solicitudes),
                      fecha_actualizacion = CURRENT_TIMESTAMP";
        
        $stmt_config = $conn->prepare($sql_config);
        $stmt_config->bind_param("iiiii", $user_id, $notif_email, $notif_sistema, $notif_transferencias, $notif_solicitudes);
        
        if ($stmt_config->execute()) {
            $mensaje = "Configuración de notificaciones actualizada correctamente.";
            $tipo_mensaje = "success";
        } else {
            $mensaje = "Error al actualizar las notificaciones.";
            $tipo_mensaje = "error";
        }
        $stmt_config->close();
    }
}

// ===== OBTENER CONFIGURACIONES DE NOTIFICACIONES =====
$notificaciones_config = [
    'notif_email' => 1,
    'notif_sistema' => 1,
    'notif_transferencias' => 1,
    'notif_solicitudes' => 1
];

$sql_config = "SELECT * FROM configuraciones_usuario WHERE usuario_id = ?";
$stmt_config = $conn->prepare($sql_config);
if ($stmt_config) {
    $stmt_config->bind_param("i", $user_id);
    $stmt_config->execute();
    $result_config = $stmt_config->get_result();
    
    if ($result_config && $row_config = $result_config->fetch_assoc()) {
        $notificaciones_config = $row_config;
    }
    $stmt_config->close();
}

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

if ($result_pendientes) {
    $result_pendientes->free();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del Perfil - GRUPO SEAL | Sistema de Gestión</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Configuración del perfil de usuario - Sistema de gestión GRUPO SEAL">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#6f42c1">
    
    <!-- Preconnect para optimizar carga de fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <!-- CSS específico para configuración -->
    <link rel="stylesheet" href="../assets/css/sidebar-styles.css">
    <link rel="stylesheet" href="../assets/css/perfil-configuracion.css">
    
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
    <div class="sidebar-header">
        <h2>GRUPO SEAL</h2>
        <p class="sidebar-subtitle">Sistema de Gestión</p>
    </div>
    
    <ul class="sidebar-menu">
        <li class="menu-item">
            <a href="../dashboard.php" aria-label="Ir a inicio">
                <i class="fas fa-home"></i>
                <span>Inicio</span>
            </a>
        </li>

        <!-- Users Section - Only visible to administrators -->
        <?php if ($usuario_rol == 'admin'): ?>
        <li class="menu-item submenu-container">
            <a href="#" aria-label="Menú Usuarios" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-users"></i> Usuarios</span>
                <i class="fas fa-chevron-down submenu-arrow"></i>
            </a>
            <ul class="submenu" role="menu">
                <li><a href="../usuarios/registrar.php" role="menuitem"><i class="fas fa-user-plus"></i> Registrar Usuario</a></li>
                <li><a href="../usuarios/listar.php" role="menuitem"><i class="fas fa-list"></i> Lista de Usuarios</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- Warehouses Section -->
        <li class="menu-item submenu-container">
            <a href="#" aria-label="Menú Almacenes" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-warehouse"></i> Almacenes</span>
                <i class="fas fa-chevron-down submenu-arrow"></i>
            </a>
            <ul class="submenu" role="menu">
                <?php if ($usuario_rol == 'admin'): ?>
                <li><a href="../almacenes/registrar.php" role="menuitem"><i class="fas fa-plus"></i> Registrar Almacén</a></li>
                <?php endif; ?>
                <li><a href="../almacenes/listar.php" role="menuitem"><i class="fas fa-list"></i> Lista de Almacenes</a></li>
            </ul>
        </li>
        
        <!-- Historial Section -->
        <li class="menu-item submenu-container">
            <a href="#" aria-label="Menú Historial" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-history"></i> Historial</span>
                <i class="fas fa-chevron-down submenu-arrow"></i>
            </a>
            <ul class="submenu" role="menu">
                <li><a href="../entregas/historial.php" role="menuitem"><i class="fas fa-hand-holding"></i> Historial de Entregas</a></li>
                <li><a href="../notificaciones/historial.php" role="menuitem"><i class="fas fa-exchange-alt"></i> Historial de Solicitudes</a></li>
            </ul>
        </li>
        
        <!-- Notifications Section -->
        <li class="menu-item submenu-container">
            <a href="#" aria-label="Menú Notificaciones" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-bell"></i> Notificaciones</span>
                <i class="fas fa-chevron-down submenu-arrow"></i>
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

        <!-- Reports Section (Admin only) -->
        <?php if ($usuario_rol == 'admin'): ?>
        <li class="menu-item submenu-container">
            <a href="#" aria-label="Menú Reportes" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-chart-bar"></i> Reportes</span>
                <i class="fas fa-chevron-down submenu-arrow"></i>
            </a>
            <ul class="submenu" role="menu">
                <li><a href="../reportes/inventario.php" role="menuitem"><i class="fas fa-warehouse"></i> Inventario General</a></li>
                <li><a href="../reportes/movimientos.php" role="menuitem"><i class="fas fa-exchange-alt"></i> Movimientos</a></li>
                <li><a href="../reportes/usuarios.php" role="menuitem"><i class="fas fa-users"></i> Actividad de Usuarios</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- User Profile -->
        <li class="menu-item submenu-container active">
            <a href="#" aria-label="Menú Perfil" aria-expanded="true" role="button" tabindex="0">
                <span><i class="fas fa-user-circle"></i> Mi Perfil</span>
                <i class="fas fa-chevron-down submenu-arrow"></i>
            </a>
            <ul class="submenu activo" role="menu">
                <li class="active"><a href="configuracion.php" role="menuitem"><i class="fas fa-cog"></i> Configuración</a></li>
                <li><a href="cambiar-password.php" role="menuitem"><i class="fas fa-key"></i> Cambiar Contraseña</a></li>
            </ul>
        </li>
    </ul>
    
    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <div class="user-details">
                <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="user-role"><?php echo $usuario_rol == 'admin' ? 'Administrador' : 'Usuario'; ?></span>
            </div>
        </div>
        <a href="#" onclick="manejarCerrarSesion(event)" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Cerrar Sesión</span>
        </a>
    </div>
</nav>

<!-- Main Content -->
<main class="config-page" role="main">
    <!-- Header de Configuración -->
    <header class="config-header">
        <div class="config-header-content">
            <div class="config-avatar-large">
                <i class="fas fa-user"></i>
            </div>
            <div class="config-user-info">
                <h1><i class="fas fa-cogs"></i> Configuración del Perfil</h1>
                <p>Gestiona tu información personal y preferencias del sistema</p>
                <div class="config-user-meta">
                    <div class="config-meta-item">
                        <i class="fas fa-user"></i>
                        <span><?php echo htmlspecialchars($user_data['nombre'] ?? '') . ' ' . htmlspecialchars($user_data['apellidos'] ?? ''); ?></span>
                    </div>
                    <div class="config-meta-item">
                        <i class="fas fa-envelope"></i>
                        <span><?php echo htmlspecialchars($user_data['email'] ?? ''); ?></span>
                    </div>
                    <div class="config-meta-item">
                        <i class="fas fa-user-tag"></i>
                        <span><?php echo $usuario_rol == 'admin' ? 'Administrador' : 'Usuario de Almacén'; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Mostrar mensajes -->
    <?php if (!empty($mensaje)): ?>
    <div class="config-alert <?php echo $tipo_mensaje; ?>">
        <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
        <?php echo $mensaje; ?>
    </div>
    <?php endif; ?>

    <!-- Contenedor Principal -->
    <div class="config-container">
        
        <!-- Tarjeta de Información Personal -->
        <div class="config-card personal">
            <div class="config-card-header">
                <h2 class="config-card-title">
                    <i class="fas fa-user"></i>
                    Información Personal
                </h2>
                <p class="config-card-subtitle">Actualiza tus datos personales y de contacto</p>
            </div>
            
            <!-- Sección de Avatar -->
            <div class="config-avatar-section">
                <div class="config-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="config-avatar-info">
                    <h4>Foto de Perfil</h4>
                    <p>Personaliza tu avatar. Se recomienda una imagen de 300x300 píxeles en formato JPG o PNG.</p>
                    <div class="config-avatar-actions">
                        <div class="config-file-upload">
                            <input type="file" id="avatar-upload" accept="image/*">
                            <label for="avatar-upload" class="config-file-upload-label">
                                <i class="fas fa-upload"></i>
                                Subir Foto
                            </label>
                        </div>
                        <button type="button" class="config-btn-remove">
                            <i class="fas fa-trash"></i>
                            Eliminar
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Formulario de Datos Personales -->
            <form class="config-form" method="POST" action="">
                <input type="hidden" name="action" value="actualizar_perfil">
                
                <div class="config-form-row">
                    <div class="config-form-group">
                        <label class="config-form-label">
                            <i class="fas fa-user"></i>
                            Nombre <span class="required">*</span>
                        </label>
                        <input type="text" 
                               name="nombre" 
                               class="config-form-input" 
                               value="<?php echo htmlspecialchars($user_data['nombre'] ?? ''); ?>" 
                               placeholder="Tu nombre"
                               required>
                    </div>
                    
                    <div class="config-form-group">
                        <label class="config-form-label">
                            <i class="fas fa-user"></i>
                            Apellidos <span class="required">*</span>
                        </label>
                        <input type="text" 
                               name="apellidos" 
                               class="config-form-input" 
                               value="<?php echo htmlspecialchars($user_data['apellidos'] ?? ''); ?>" 
                               placeholder="Tus apellidos"
                               required>
                    </div>
                </div>
                
                <div class="config-form-row">
                    <div class="config-form-group">
                        <label class="config-form-label">
                            <i class="fas fa-envelope"></i>
                            Email <span class="required">*</span>
                        </label>
                        <input type="email" 
                               name="email" 
                               class="config-form-input" 
                               value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" 
                               placeholder="tu@email.com"
                               required>
                    </div>
                    
                    <div class="config-form-group">
                        <label class="config-form-label">
                            <i class="fas fa-phone"></i>
                            Teléfono
                        </label>
                        <input type="tel" 
                               name="telefono" 
                               class="config-form-input" 
                               value="<?php echo htmlspecialchars($user_data['telefono'] ?? ''); ?>" 
                               placeholder="+51 999 999 999">
                    </div>
                </div>
                
                <div class="config-form-row">
                    <div class="config-form-group">
                        <label class="config-form-label">
                            <i class="fas fa-building"></i>
                            Departamento
                        </label>
                        <input type="text" 
                               name="departamento" 
                               class="config-form-input" 
                               value="<?php echo htmlspecialchars($user_data['departamento'] ?? ''); ?>" 
                               placeholder="Ej: Logística, Recursos Humanos">
                    </div>
                    
                    <div class="config-form-group">
                        <label class="config-form-label">
                            <i class="fas fa-id-badge"></i>
                            Cargo
                        </label>
                        <input type="text" 
                               name="cargo" 
                               class="config-form-input" 
                               value="<?php echo htmlspecialchars($user_data['cargo'] ?? ''); ?>" 
                               placeholder="Ej: Supervisor, Coordinador">
                    </div>
                </div>
                
                <?php if (!empty($user_data['almacen_nombre'])): ?>
                <div class="config-info-box">
                    <h4><i class="fas fa-warehouse"></i> Información del Almacén</h4>
                    <p><strong>Almacén asignado:</strong> <?php echo htmlspecialchars($user_data['almacen_nombre']); ?></p>
                </div>
                <?php endif; ?>
                
                <div class="config-actions">
                    <button type="submit" class="config-btn config-btn-primary">
                        <i class="fas fa-save"></i>
                        Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Tarjeta de Configuración de Notificaciones -->
        <div class="config-card preferences">
            <div class="config-card-header">
                <h2 class="config-card-title">
                    <i class="fas fa-bell"></i>
                    Configuración de Notificaciones
                </h2>
                <p class="config-card-subtitle">Personaliza qué notificaciones deseas recibir</p>
            </div>
            
            <form class="config-form" method="POST" action="">
                <input type="hidden" name="action" value="actualizar_notificaciones">
                
                <div class="config-notifications">
                    <div class="config-notification-item">
                        <div class="config-notification-info">
                            <h5>Notificaciones por Email</h5>
                            <p>Recibir notificaciones importantes en tu correo electrónico</p>
                        </div>
                        <div class="config-toggle <?php echo !empty($notificaciones_config['notif_email']) ? 'active' : ''; ?>" 
                             data-toggle="notif_email">
                        </div>
                        <input type="hidden" name="notif_email" value="<?php echo !empty($notificaciones_config['notif_email']) ? '1' : '0'; ?>">
                    </div>
                    
                    <div class="config-notification-item">
                        <div class="config-notification-info">
                            <h5>Notificaciones del Sistema</h5>
                            <p>Alertas y mensajes importantes dentro del sistema</p>
                        </div>
                        <div class="config-toggle <?php echo !empty($notificaciones_config['notif_sistema']) ? 'active' : ''; ?>" 
                             data-toggle="notif_sistema">
                        </div>
                        <input type="hidden" name="notif_sistema" value="<?php echo !empty($notificaciones_config['notif_sistema']) ? '1' : '0'; ?>">
                    </div>
                    
                    <div class="config-notification-item">
                        <div class="config-notification-info">
                            <h5>Transferencias de Productos</h5>
                            <p>Notificaciones sobre movimientos de inventario</p>
                        </div>
                        <div class="config-toggle <?php echo !empty($notificaciones_config['notif_transferencias']) ? 'active' : ''; ?>" 
                             data-toggle="notif_transferencias">
                        </div>
                        <input type="hidden" name="notif_transferencias" value="<?php echo !empty($notificaciones_config['notif_transferencias']) ? '1' : '0'; ?>">
                    </div>
                    
                    <div class="config-notification-item">
                        <div class="config-notification-info">
                            <h5>Solicitudes Pendientes</h5>
                            <p>Alertas sobre solicitudes que requieren tu atención</p>
                        </div>
                        <div class="config-toggle <?php echo !empty($notificaciones_config['notif_solicitudes']) ? 'active' : ''; ?>" 
                             data-toggle="notif_solicitudes">
                        </div>
                        <input type="hidden" name="notif_solicitudes" value="<?php echo !empty($notificaciones_config['notif_solicitudes']) ? '1' : '0'; ?>">
                    </div>
                </div>
                
                <div class="config-actions">
                    <button type="submit" class="config-btn config-btn-success">
                        <i class="fas fa-bell"></i>
                        Actualizar Notificaciones
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Tarjeta de Seguridad -->
        <div class="config-card security">
            <div class="config-card-header">
                <h2 class="config-card-title">
                    <i class="fas fa-shield-alt"></i>
                    Seguridad de la Cuenta
                </h2>
                <p class="config-card-subtitle">Gestiona la seguridad de tu cuenta</p>
            </div>
            
            <div class="config-form">
                <div class="config-info-box">
                    <h4><i class="fas fa-key"></i> Cambiar Contraseña</h4>
                    <p>Mantén tu cuenta segura actualizando regularmente tu contraseña.</p>
                </div>
                
                <div class="config-actions">
                    <a href="cambiar-password.php" class="config-btn config-btn-secondary">
                        <i class="fas fa-key"></i>
                        Cambiar Contraseña
                    </a>
                </div>
            </div>
        </div>
        
    </div>
</main>

<!-- Container for dynamic notifications -->
<div id="notificaciones-container" role="alert" aria-live="polite"></div>

<!-- JavaScript -->
<script src="../assets/js/universal-confirmation-system.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ===== ELEMENTOS DEL DOM =====
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const submenuContainers = document.querySelectorAll('.submenu-container');
    const toggles = document.querySelectorAll('.config-toggle');
    
    // ===== FUNCIONALIDAD DEL MENÚ MÓVIL =====
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            
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
    
    // ===== FUNCIONALIDAD DE SUBMENÚS =====
    submenuContainers.forEach(container => {
        const link = container.querySelector('a');
        const submenu = container.querySelector('.submenu');
        
        if (link && submenu) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Cerrar otros submenús
                submenuContainers.forEach(otherContainer => {
                    if (otherContainer !== container) {
                        const otherSubmenu = otherContainer.querySelector('.submenu');
                        if (otherSubmenu) {
                            otherSubmenu.classList.remove('activo');
                        }
                    }
                });
                
                // Toggle del submenú actual
                submenu.classList.toggle('activo');
            });
        }
    });
    
    // ===== FUNCIONALIDAD DE TOGGLES =====
    toggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const isActive = this.classList.contains('active');
            const toggleName = this.getAttribute('data-toggle');
            const hiddenInput = document.querySelector(`input[name="${toggleName}"]`);
            
            if (isActive) {
                this.classList.remove('active');
                if (hiddenInput) hiddenInput.value = '0';
            } else {
                this.classList.add('active');
                if (hiddenInput) hiddenInput.value = '1';
            }
        });
    });
    
    // ===== VALIDACIÓN DE FORMULARIO =====
    const forms = document.querySelectorAll('.config-form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('input[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('invalid');
                    isValid = false;
                } else {
                    field.classList.remove('invalid');
                    field.classList.add('valid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                mostrarNotificacion('Por favor, completa todos los campos obligatorios.', 'error');
            }
        });
    });
    
    // ===== PREVIEW DE AVATAR =====
    const avatarUpload = document.getElementById('avatar-upload');
    if (avatarUpload) {
        avatarUpload.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const avatar = document.querySelector('.config-avatar');
                    avatar.style.backgroundImage = `url(${e.target.result})`;
                    avatar.style.backgroundSize = 'cover';
                    avatar.style.backgroundPosition = 'center';
                    avatar.innerHTML = '';
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // ===== NAVEGACIÓN POR TECLADO =====
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            menuToggle.focus();
        }
        
        if (e.key === 'Tab') {
            document.body.classList.add('keyboard-navigation');
        }
    });
    
    document.addEventListener('mousedown', function() {
        document.body.classList.remove('keyboard-navigation');
    });
    
    // ===== CERRAR MENÚ AL HACER CLIC FUERA =====
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
                const icon = menuToggle.querySelector('i');
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        }
    });
    
    // Mostrar mensaje de bienvenida
    setTimeout(() => {
        mostrarNotificacion('Configuración del perfil cargada correctamente.', 'info', 3000);
    }, 1000);
});

// ===== FUNCIÓN PARA CERRAR SESIÓN =====
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

// ===== FUNCIÓN PARA MOSTRAR NOTIFICACIONES =====
function mostrarNotificacion(mensaje, tipo, duracion = 5000) {
    const container = document.getElementById('notificaciones-container');
    if (!container) return;
    
    const notificacion = document.createElement('div');
    notificacion.className = `config-alert ${tipo}`;
    notificacion.innerHTML = `
        <i class="fas fa-${tipo === 'success' ? 'check-circle' : 
                          tipo === 'error' ? 'exclamation-triangle' : 
                          'info-circle'}"></i>
        ${mensaje}
    `;
    
    container.appendChild(notificacion);
    
    setTimeout(() => {
        notificacion.remove();
    }, duracion);
}
</script>
</body>
</html>