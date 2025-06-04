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

// ===== FUNCIÓN PARA VALIDAR FORTALEZA DE CONTRASEÑA =====
function validarFortalezaPassword($password) {
    $score = 0;
    $requisitos = [
        'longitud' => false,
        'mayuscula' => false,
        'minuscula' => false,
        'numero' => false,
        'especial' => false
    ];
    
    // Longitud mínima de 8 caracteres
    if (strlen($password) >= 8) {
        $score += 20;
        $requisitos['longitud'] = true;
    }
    
    // Al menos una mayúscula
    if (preg_match('/[A-Z]/', $password)) {
        $score += 20;
        $requisitos['mayuscula'] = true;
    }
    
    // Al menos una minúscula
    if (preg_match('/[a-z]/', $password)) {
        $score += 20;
        $requisitos['minuscula'] = true;
    }
    
    // Al menos un número
    if (preg_match('/[0-9]/', $password)) {
        $score += 20;
        $requisitos['numero'] = true;
    }
    
    // Al menos un caracter especial
    if (preg_match('/[^A-Za-z0-9]/', $password)) {
        $score += 20;
        $requisitos['especial'] = true;
    }
    
    // Determinar nivel de fortaleza
    $nivel = 'weak';
    if ($score >= 60) $nivel = 'medium';
    if ($score >= 100) $nivel = 'strong';
    
    return [
        'score' => $score,
        'nivel' => $nivel,
        'requisitos' => $requisitos
    ];
}

// ===== PROCESAR CAMBIO DE CONTRASEÑA =====
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'cambiar_password') {
    
    $password_actual = $_POST['password_actual'] ?? '';
    $password_nueva = $_POST['password_nueva'] ?? '';
    $password_confirmar = $_POST['password_confirmar'] ?? '';
    
    $errores = [];
    
    // Validaciones básicas
    if (empty($password_actual)) {
        $errores[] = "La contraseña actual es obligatoria.";
    }
    
    if (empty($password_nueva)) {
        $errores[] = "La nueva contraseña es obligatoria.";
    }
    
    if (empty($password_confirmar)) {
        $errores[] = "Debes confirmar la nueva contraseña.";
    }
    
    if ($password_nueva !== $password_confirmar) {
        $errores[] = "Las contraseñas nuevas no coinciden.";
    }
    
    if ($password_actual === $password_nueva) {
        $errores[] = "La nueva contraseña debe ser diferente a la actual.";
    }
    
    // Validar fortaleza de la nueva contraseña
    if (!empty($password_nueva)) {
        $fortaleza = validarFortalezaPassword($password_nueva);
        
        if (strlen($password_nueva) < 8) {
            $errores[] = "La contraseña debe tener al menos 8 caracteres.";
        }
        
        if (!$fortaleza['requisitos']['mayuscula']) {
            $errores[] = "La contraseña debe contener al menos una letra mayúscula.";
        }
        
        if (!$fortaleza['requisitos']['minuscula']) {
            $errores[] = "La contraseña debe contener al menos una letra minúscula.";
        }
        
        if (!$fortaleza['requisitos']['numero']) {
            $errores[] = "La contraseña debe contener al menos un número.";
        }
        
        if (!$fortaleza['requisitos']['especial']) {
            $errores[] = "La contraseña debe contener al menos un caracter especial (!@#$%^&*).";
        }
    }
    
    // Si no hay errores de validación, proceder con la verificación y actualización
    if (empty($errores)) {
        // Obtener la contraseña actual de la base de datos
        $sql_get_password = "SELECT password FROM usuarios WHERE id = ?";
        $stmt_get = $conn->prepare($sql_get_password);
        $stmt_get->bind_param("i", $user_id);
        $stmt_get->execute();
        $result_get = $stmt_get->get_result();
        
        if ($result_get && $row = $result_get->fetch_assoc()) {
            $password_actual_hash = $row['password'];
            
            // Verificar la contraseña actual
            if (password_verify($password_actual, $password_actual_hash)) {
                // Hashear la nueva contraseña
                $password_nueva_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
                
                // Actualizar la contraseña en la base de datos
                $sql_update = "UPDATE usuarios SET 
                              password = ?, 
                              fecha_actualizacion = CURRENT_TIMESTAMP 
                              WHERE id = ?";
                
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("si", $password_nueva_hash, $user_id);
                
                if ($stmt_update->execute()) {
                    $mensaje = "Contraseña actualizada correctamente.";
                    $tipo_mensaje = "success";
                    
                    // Opcional: Registrar el cambio en un log de seguridad
                    $sql_log = "INSERT INTO log_seguridad (usuario_id, accion, descripcion, fecha) 
                               VALUES (?, 'cambio_password', 'Usuario cambió su contraseña', CURRENT_TIMESTAMP)";
                    $stmt_log = $conn->prepare($sql_log);
                    if ($stmt_log) {
                        $stmt_log->bind_param("i", $user_id);
                        $stmt_log->execute();
                        $stmt_log->close();
                    }
                    
                } else {
                    $mensaje = "Error al actualizar la contraseña.";
                    $tipo_mensaje = "error";
                }
                $stmt_update->close();
                
            } else {
                $mensaje = "La contraseña actual es incorrecta.";
                $tipo_mensaje = "error";
            }
        } else {
            $mensaje = "Error al verificar la contraseña actual.";
            $tipo_mensaje = "error";
        }
        $stmt_get->close();
        
    } else {
        $mensaje = implode("<br>", $errores);
        $tipo_mensaje = "error";
    }
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
    <title>Cambiar Contraseña - GRUPO SEAL | Sistema de Gestión</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Cambiar contraseña - Sistema de gestión GRUPO SEAL">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Preconnect para optimizar carga de fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <!-- CSS consistente con el dashboard -->
    <link rel="stylesheet" href="../assets/css/listar-usuarios.css">
    <link rel="stylesheet" href="../assets/css/perfil-cambiar-password.css">
    
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
        <li class="submenu-container activo">
            <a href="#" aria-label="Menú Perfil" aria-expanded="true" role="button" tabindex="0">
                <span><i class="fas fa-user-circle"></i> Mi Perfil</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu activo" role="menu">
                <li><a href="configuracion.php" role="menuitem"><i class="fas fa-cog"></i> Configuración</a></li>
                <li class="activo"><a href="cambiar-password.php" role="menuitem"><i class="fas fa-key"></i> Cambiar Contraseña</a></li>
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
<main class="content" role="main">
    
    <!-- Header de Seguridad -->
    <header class="password-header">
        <div class="password-header-content">
            <div class="password-header-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="password-header-info">
                <h1><i class="fas fa-key"></i> Cambiar Contraseña</h1>
                <p>Mantén tu cuenta segura actualizando regularmente tu contraseña. Asegúrate de usar una contraseña fuerte y única.</p>
            </div>
        </div>
    </header>

    <!-- Mostrar mensajes -->
    <?php if (!empty($mensaje)): ?>
    <div class="password-alert <?php echo $tipo_mensaje; ?>">
        <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
        <?php echo $mensaje; ?>
    </div>
    <?php endif; ?>

    <!-- Contenedor Principal -->
    <div class="password-container">
        <div class="password-form-wrapper">
            
            <!-- Header del Formulario -->
            <div class="password-form-header">
                <div class="password-form-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <h2 class="password-form-title">Actualizar Contraseña</h2>
                <p class="password-form-subtitle">Ingresa tu contraseña actual y define una nueva contraseña segura</p>
            </div>
            
            <!-- Formulario de Cambio de Contraseña -->
            <form class="password-form" method="POST" action="" id="passwordForm">
                <input type="hidden" name="action" value="cambiar_password">
                
                <!-- Contraseña Actual -->
                <div class="password-form-group">
                    <label class="password-form-label">
                        <i class="fas fa-lock"></i>
                        Contraseña Actual <span class="required">*</span>
                    </label>
                    <div class="password-input-wrapper">
                        <input type="password" 
                               name="password_actual" 
                               id="passwordActual"
                               class="password-form-input" 
                               placeholder="Ingresa tu contraseña actual"
                               required>
                        <button type="button" class="password-toggle-btn" data-target="passwordActual">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Nueva Contraseña -->
                <div class="password-form-group">
                    <label class="password-form-label">
                        <i class="fas fa-key"></i>
                        Nueva Contraseña <span class="required">*</span>
                    </label>
                    <div class="password-input-wrapper">
                        <input type="password" 
                               name="password_nueva" 
                               id="passwordNueva"
                               class="password-form-input" 
                               placeholder="Ingresa tu nueva contraseña"
                               required>
                        <button type="button" class="password-toggle-btn" data-target="passwordNueva">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    
                    <!-- Indicador de Fortaleza -->
                    <div class="password-strength" id="passwordStrength" style="display: none;">
                        <div class="password-strength-label">
                            <span class="password-strength-text">Fortaleza de la contraseña:</span>
                            <span class="password-strength-score" id="strengthScore">Débil</span>
                        </div>
                        <div class="password-strength-bar">
                            <div class="password-strength-fill" id="strengthFill"></div>
                        </div>
                    </div>
                    
                    <!-- Requisitos de Contraseña -->
                    <div class="password-requirements">
                        <h4><i class="fas fa-list-check"></i> Requisitos de la contraseña:</h4>
                        <div class="password-requirements-list">
                            <div class="password-requirement" id="req-length">
                                <div class="password-requirement-icon">
                                    <i class="fas fa-times"></i>
                                </div>
                                <span>Al menos 8 caracteres</span>
                            </div>
                            <div class="password-requirement" id="req-upper">
                                <div class="password-requirement-icon">
                                    <i class="fas fa-times"></i>
                                </div>
                                <span>Al menos una letra mayúscula (A-Z)</span>
                            </div>
                            <div class="password-requirement" id="req-lower">
                                <div class="password-requirement-icon">
                                    <i class="fas fa-times"></i>
                                </div>
                                <span>Al menos una letra minúscula (a-z)</span>
                            </div>
                            <div class="password-requirement" id="req-number">
                                <div class="password-requirement-icon">
                                    <i class="fas fa-times"></i>
                                </div>
                                <span>Al menos un número (0-9)</span>
                            </div>
                            <div class="password-requirement" id="req-special">
                                <div class="password-requirement-icon">
                                    <i class="fas fa-times"></i>
                                </div>
                                <span>Al menos un caracter especial (!@#$%^&*)</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Confirmar Nueva Contraseña -->
                <div class="password-form-group">
                    <label class="password-form-label">
                        <i class="fas fa-check-double"></i>
                        Confirmar Nueva Contraseña <span class="required">*</span>
                    </label>
                    <div class="password-input-wrapper">
                        <input type="password" 
                               name="password_confirmar" 
                               id="passwordConfirmar"
                               class="password-form-input" 
                               placeholder="Confirma tu nueva contraseña"
                               required>
                        <button type="button" class="password-toggle-btn" data-target="passwordConfirmar">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-validation-message" id="matchMessage" style="display: none;"></div>
                </div>
                
                <!-- Botones de Acción -->
                <div class="password-form-actions">
                    <button type="submit" class="password-btn password-btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i>
                        Cambiar Contraseña
                    </button>
                    <a href="configuracion.php" class="password-btn password-btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Volver
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Información de Seguridad -->
        <div class="password-security-info">
            <h3><i class="fas fa-shield-alt"></i> Consejos de Seguridad</h3>
            <div class="password-security-tips">
                <div class="password-security-tip">
                    <div class="password-security-tip-icon">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <div class="password-security-tip-text">
                        Usa una combinación de letras mayúsculas, minúsculas, números y símbolos especiales.
                    </div>
                </div>
                <div class="password-security-tip">
                    <div class="password-security-tip-icon">
                        <i class="fas fa-ban"></i>
                    </div>
                    <div class="password-security-tip-text">
                        Evita usar información personal como nombres, fechas de nacimiento o palabras comunes.
                    </div>
                </div>
                <div class="password-security-tip">
                    <div class="password-security-tip-icon">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <div class="password-security-tip-text">
                        Cambia tu contraseña regularmente y nunca la compartas con otras personas.
                    </div>
                </div>
                <div class="password-security-tip">
                    <div class="password-security-tip-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    <div class="password-security-tip-text">
                        Usa contraseñas únicas para cada cuenta y considera usar un gestor de contraseñas.
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</main>

<!-- Container for dynamic notifications -->
<div id="notificaciones-container" role="alert" aria-live="polite"></div>

<!-- Modal de Confirmación -->
<div class="password-modal" id="confirmModal">
    <div class="password-modal-content">
        <div class="password-modal-header">
            <h2><i class="fas fa-question-circle"></i> Confirmar Cambio</h2>
        </div>
        <div class="password-modal-body">
            <p>¿Estás seguro de que deseas cambiar tu contraseña? Esta acción no se puede deshacer.</p>
        </div>
        <div class="password-modal-actions">
            <button type="button" class="password-btn password-btn-secondary" onclick="cerrarModal()">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button type="button" class="password-btn password-btn-primary" onclick="confirmarCambio()">
                <i class="fas fa-check"></i> Confirmar
            </button>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="../assets/js/universal-confirmation-system.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ===== ELEMENTOS DEL DOM =====
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const submenuContainers = document.querySelectorAll('.submenu-container');
    const passwordForm = document.getElementById('passwordForm');
    const passwordNueva = document.getElementById('passwordNueva');
    const passwordConfirmar = document.getElementById('passwordConfirmar');
    const submitBtn = document.getElementById('submitBtn');
    const toggleButtons = document.querySelectorAll('.password-toggle-btn');
    const mainContent = document.querySelector('.content');
    
    // ===== FUNCIONALIDAD DEL MENÚ MÓVIL =====
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
    
    // ===== FUNCIONALIDAD DE SUBMENÚS =====
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
    
    // ===== BOTONES DE MOSTRAR/OCULTAR CONTRASEÑA =====
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const targetInput = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            if (targetInput.type === 'password') {
                targetInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                targetInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
    
    // ===== VALIDACIÓN DE FORTALEZA DE CONTRASEÑA =====
    if (passwordNueva) {
        passwordNueva.addEventListener('input', function() {
            const password = this.value;
            validarFortalezaPassword(password);
        });
    }
    
    // ===== VALIDACIÓN DE CONFIRMACIÓN DE CONTRASEÑA =====
    if (passwordConfirmar) {
        passwordConfirmar.addEventListener('input', function() {
            validarConfirmacionPassword();
        });
    }
    
    // ===== FUNCIÓN PARA VALIDAR FORTALEZA =====
    function validarFortalezaPassword(password) {
        const strengthContainer = document.getElementById('passwordStrength');
        const strengthFill = document.getElementById('strengthFill');
        const strengthScore = document.getElementById('strengthScore');
        
        if (password.length > 0) {
            strengthContainer.style.display = 'block';
        } else {
            strengthContainer.style.display = 'none';
            return;
        }
        
        let score = 0;
        const requirements = {
            length: password.length >= 8,
            upper: /[A-Z]/.test(password),
            lower: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[^A-Za-z0-9]/.test(password)
        };
        
        // Calcular puntuación
        Object.values(requirements).forEach(met => {
            if (met) score += 20;
        });
        
        // Actualizar indicadores visuales
        updateRequirement('req-length', requirements.length);
        updateRequirement('req-upper', requirements.upper);
        updateRequirement('req-lower', requirements.lower);
        updateRequirement('req-number', requirements.number);
        updateRequirement('req-special', requirements.special);
        
        // Actualizar barra de fortaleza
        let nivel = 'weak';
        let texto = 'Débil';
        
        if (score >= 60) {
            nivel = 'medium';
            texto = 'Medio';
        }
        if (score >= 100) {
            nivel = 'strong';
            texto = 'Fuerte';
        }
        
        strengthFill.className = `password-strength-fill ${nivel}`;
        strengthScore.className = `password-strength-score ${nivel}`;
        strengthScore.textContent = texto;
        
        // Actualizar estado del botón
        updateSubmitButton();
    }
    
    // ===== FUNCIÓN PARA ACTUALIZAR REQUISITOS =====
    function updateRequirement(reqId, met) {
        const element = document.getElementById(reqId);
        const icon = element.querySelector('.password-requirement-icon i');
        
        if (met) {
            element.classList.add('met');
            icon.classList.remove('fa-times');
            icon.classList.add('fa-check');
        } else {
            element.classList.remove('met');
            icon.classList.remove('fa-check');
            icon.classList.add('fa-times');
        }
    }
    
    // ===== FUNCIÓN PARA VALIDAR CONFIRMACIÓN =====
    function validarConfirmacionPassword() {
        const matchMessage = document.getElementById('matchMessage');
        const passwordValue = passwordNueva.value;
        const confirmValue = passwordConfirmar.value;
        
        if (confirmValue.length > 0) {
            matchMessage.style.display = 'block';
            
            if (passwordValue === confirmValue) {
                matchMessage.className = 'password-validation-message success';
                matchMessage.innerHTML = '<i class="fas fa-check"></i> Las contraseñas coinciden';
                passwordConfirmar.classList.remove('invalid');
                passwordConfirmar.classList.add('valid');
            } else {
                matchMessage.className = 'password-validation-message error';
                matchMessage.innerHTML = '<i class="fas fa-times"></i> Las contraseñas no coinciden';
                passwordConfirmar.classList.remove('valid');
                passwordConfirmar.classList.add('invalid');
            }
        } else {
            matchMessage.style.display = 'none';
            passwordConfirmar.classList.remove('valid', 'invalid');
        }
        
        updateSubmitButton();
    }
    
    // ===== FUNCIÓN PARA ACTUALIZAR BOTÓN DE ENVÍO =====
    function updateSubmitButton() {
        const passwordValue = passwordNueva.value;
        const confirmValue = passwordConfirmar.value;
        
        // Verificar que todos los requisitos se cumplan
        const requirements = {
            length: passwordValue.length >= 8,
            upper: /[A-Z]/.test(passwordValue),
            lower: /[a-z]/.test(passwordValue),
            number: /[0-9]/.test(passwordValue),
            special: /[^A-Za-z0-9]/.test(passwordValue)
        };
        
        const allRequirementsMet = Object.values(requirements).every(met => met);
        const passwordsMatch = passwordValue === confirmValue && confirmValue.length > 0;
        
        if (allRequirementsMet && passwordsMatch) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('disabled');
        } else {
            submitBtn.disabled = true;
            submitBtn.classList.add('disabled');
        }
    }
    
    // ===== ENVÍO DEL FORMULARIO CON CONFIRMACIÓN =====
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const modal = document.getElementById('confirmModal');
            modal.style.display = 'block';
        });
    }
    
    // ===== NAVEGACIÓN POR TECLADO =====
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('confirmModal');
            if (modal.style.display === 'block') {
                modal.style.display = 'none';
            } else if (sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                if (mainContent) {
                    mainContent.classList.remove('with-sidebar');
                }
                menuToggle.focus();
            }
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
                if (mainContent) {
                    mainContent.classList.remove('with-sidebar');
                }
                const icon = menuToggle.querySelector('i');
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        }
        
        // Cerrar modal al hacer clic fuera
        const modal = document.getElementById('confirmModal');
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
    
    // Mostrar mensaje de bienvenida
    setTimeout(() => {
        mostrarNotificacion('Formulario de cambio de contraseña cargado correctamente.', 'info', 3000);
    }, 1000);
});

// ===== FUNCIONES DE MODAL =====
function cerrarModal() {
    const modal = document.getElementById('confirmModal');
    modal.style.display = 'none';
}

function confirmarCambio() {
    const modal = document.getElementById('confirmModal');
    const submitBtn = document.getElementById('submitBtn');
    
    modal.style.display = 'none';
    
    // Mostrar estado de carga
    submitBtn.classList.add('loading');
    submitBtn.disabled = true;
    
    // Enviar formulario
    setTimeout(() => {
        document.getElementById('passwordForm').submit();
    }, 500);
}

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
    notificacion.className = `alert ${tipo}`;
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