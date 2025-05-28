<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

// Evita secuestro de sesión
session_regenerate_id(true);

$user_name = isset($_SESSION["user_name"]) ? $_SESSION["user_name"] : "Usuario";
$usuario_almacen_id = isset($_SESSION["almacen_id"]) ? $_SESSION["almacen_id"] : null;
$usuario_rol = isset($_SESSION["user_role"]) ? $_SESSION["user_role"] : "usuario";

require_once "../config/database.php";

// Obtener datos del usuario a editar
if (isset($_GET["id"])) {
    $id = (int) $_GET["id"];
    $stmt = $conn->prepare("SELECT nombre, apellidos, dni, correo, rol, estado, almacen_id FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();
    $stmt->close();

    if (!$usuario) {
        die("Usuario no encontrado.");
    }
} else {
    die("ID de usuario no válido.");
}

// Obtener lista de almacenes
$almacenes = [];
$stmt = $conn->prepare("SELECT id, nombre FROM almacenes");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $almacenes[] = $row;
}
$stmt->close();

// Guardar cambios
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST["nombre"]);
    $apellidos = trim($_POST["apellidos"]);
    $dni = trim($_POST["dni"]);
    $correo = trim($_POST["correo"]);
    $rol = $_POST["rol"];
    $estado = $_POST["estado"];
    $almacen_id = $_POST["almacen_id"];

    // Validaciones
    if (!preg_match("/^\d{8}$/", $dni)) {
        $_SESSION['mensaje_error'] = "El DNI debe tener exactamente 8 dígitos.";
        header("Location: editar_usuario.php?id=" . $id);
        exit();
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['mensaje_error'] = "Correo electrónico no válido.";
        header("Location: editar_usuario.php?id=" . $id);
        exit();
    }

    $stmt = $conn->prepare("UPDATE usuarios SET nombre=?, apellidos=?, dni=?, correo=?, rol=?, estado=?, almacen_id=? WHERE id=?");
    $stmt->bind_param("ssssssii", $nombre, $apellidos, $dni, $correo, $rol, $estado, $almacen_id, $id);

    if ($stmt->execute()) {
        $_SESSION['mensaje_exito'] = "Usuario actualizado correctamente.";
        header("Location: listar.php");
        exit();
    } else {
        $_SESSION['mensaje_error'] = "Error al actualizar usuario.";
        header("Location: editar_usuario.php?id=" . $id);
        exit();
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario - COMSEPROA</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <!-- CSS específico para editar usuario -->
    <link rel="stylesheet" href="../assets/css/editar-usuario.css">
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Editar información de usuario en el sistema COMSEPROA">
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

    <!-- Menú Lateral -->
    <nav class="sidebar" id="sidebar" role="navigation" aria-label="Menú principal">
        <h2>COMSEPROA</h2>
        <ul>
            <li><a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a></li>

            <!-- Usuarios - Solo visible para administradores -->
            <?php if ($usuario_rol == 'admin'): ?>
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
            <?php endif; ?>

            <!-- Almacenes -->
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
                            <?php 
                            // Contar solicitudes pendientes para mostrar en el badge
                            $sql_pendientes = "SELECT COUNT(*) as total FROM solicitudes_transferencia WHERE estado = 'pendiente'";
                            
                            // Si el usuario no es admin, filtrar por su almacén
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
                    <li><a href="../notificaciones/historial.php" role="menuitem"><i class="fas fa-history"></i> Historial de Solicitudes</a></li>
                    <li><a href="../uniformes/historial_entregas_uniformes.php" role="menuitem"><i class="fas fa-tshirt"></i> Historial de Entregas</a></li>
                </ul>
            </li>

            <!-- Cerrar Sesión -->
            <li>
                <a href="#" onclick="manejarCerrarSesion(event)" aria-label="Cerrar sesión">
                    <i class="fas fa-sign-out-alt"></i> Cerrar sesión
                </a>
            </li>
        </ul>
    </nav>
    
    <main class="content" id="main-content" role="main">
        <header>
            <h1>Editar Usuario</h1>
        </header>
        
        <div class="register-container">
            <!-- Mostrar mensajes de sesión -->
            <?php if (isset($_SESSION['mensaje_exito'])): ?>
                <div class="mensaje exito" role="alert" style="display: none;">
                    <?php echo $_SESSION['mensaje_exito']; unset($_SESSION['mensaje_exito']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['mensaje_error'])): ?>
                <div class="mensaje error" role="alert" style="display: none;">
                    <?php echo $_SESSION['mensaje_error']; unset($_SESSION['mensaje_error']); ?>
                </div>
            <?php endif; ?>
            
            <form method="post" novalidate id="formEditarUsuario">
                <div class="form-group">
                    <input type="text" name="nombre" placeholder="Nombre" value="<?= htmlspecialchars($usuario['nombre']) ?>" required aria-label="Nombre del usuario">
                    <input type="text" name="apellidos" placeholder="Apellidos" value="<?= htmlspecialchars($usuario['apellidos']) ?>" required aria-label="Apellidos del usuario">
                </div>
                <div class="form-group">
                    <input type="text" name="dni" placeholder="DNI" maxlength="8" value="<?= htmlspecialchars($usuario['dni']) ?>" required pattern="\d{8}" title="El DNI debe contener 8 dígitos" aria-label="DNI del usuario">
                    <input type="email" name="correo" placeholder="Correo Electrónico" value="<?= htmlspecialchars($usuario['correo']) ?>" required aria-label="Correo electrónico">
                </div>
                <div class="form-group">
                    <select name="rol" required aria-label="Rol del usuario">
                        <option value="">Seleccione un rol</option>
                        <option value="admin" <?= $usuario['rol'] == 'admin' ? 'selected' : ''; ?>>Administrador</option>
                        <option value="almacenero" <?= $usuario['rol'] == 'almacenero' ? 'selected' : ''; ?>>Almacenero</option>
                    </select>
                    <select name="almacen_id" aria-label="Almacén asignado">
                        <option value="">Seleccione un almacén</option>
                        <?php foreach ($almacenes as $almacen): ?>
                            <option value="<?= $almacen["id"] ?>" <?= ($usuario['almacen_id'] == $almacen["id"]) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($almacen["nombre"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <select name="estado" aria-label="Estado del usuario">
                        <option value="activo" <?= $usuario['estado'] == 'activo' ? 'selected' : ''; ?>>Activo</option>
                        <option value="inactivo" <?= $usuario['estado'] == 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                    </select>
                </div>
                <button type="button" class="btn" onclick="manejarGuardarCambios()">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </form>
            
            <!-- Enlaces de navegación -->
            <div style="text-align: center; margin-top: 30px; padding-top: 25px; border-top: 2px solid #e9ecef;">
                <a href="listar.php" class="btn" style="background: #c8c9ca; color: #0a253c; text-decoration: none; margin-right: 15px;">
                    <i class="fas fa-arrow-left"></i> Volver a la Lista
                </a>
                <a href="registrar.php" class="btn" style="background: #17a2b8; color: white; text-decoration: none;">
                    <i class="fas fa-user-plus"></i> Registrar Nuevo
                </a>
            </div>
        </div>
    </main>
    
    <!-- Contenedor para notificaciones dinámicas -->
    <div id="notificaciones-container" role="alert" aria-live="polite"></div>

    <!-- JavaScript con sistema de confirmaciones integrado -->
    <script>
    // ===================================================================
    // SISTEMA DE CONFIRMACIONES PERSONALIZADO
    // ===================================================================

    /**
     * Sistema de confirmaciones elegante y personalizable
     */
    class ConfirmationSystem {
        constructor() {
            this.createModal();
            this.bindEvents();
        }

        createModal() {
            const modalHTML = `
                <div id="confirmation-modal" class="confirmation-modal" role="dialog" aria-modal="true">
                    <div class="confirmation-overlay"></div>
                    <div class="confirmation-container">
                        <div class="confirmation-header">
                            <div class="confirmation-icon">
                                <i class="fas fa-question-circle"></i>
                            </div>
                            <h3 class="confirmation-title">Confirmar Acción</h3>
                        </div>
                        <div class="confirmation-body">
                            <p class="confirmation-message">¿Estás seguro de que deseas realizar esta acción?</p>
                            <div class="confirmation-details" style="display: none;"></div>
                        </div>
                        <div class="confirmation-footer">
                            <button type="button" class="btn-cancel">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                            <button type="button" class="btn-confirm">
                                <i class="fas fa-check"></i> Confirmar
                            </button>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', modalHTML);
            this.addStyles();
        }

        addStyles() {
            const styles = `
                <style id="confirmation-styles">
                .confirmation-modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    z-index: 9999;
                    display: none;
                    align-items: center;
                    justify-content: center;
                    opacity: 0;
                    transition: opacity 0.3s ease;
                }

                .confirmation-modal.show {
                    display: flex;
                    opacity: 1;
                }

                .confirmation-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(10, 37, 60, 0.8);
                    backdrop-filter: blur(5px);
                }

                .confirmation-container {
                    background: white;
                    border-radius: 15px;
                    box-shadow: 0 20px 40px rgba(10, 37, 60, 0.3);
                    max-width: 500px;
                    width: 90%;
                    overflow: hidden;
                    position: relative;
                    transform: scale(0.8);
                    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                }

                .confirmation-modal.show .confirmation-container {
                    transform: scale(1);
                }

                .confirmation-header {
                    background: linear-gradient(135deg, #0a253c 0%, #164463 100%);
                    color: white;
                    padding: 25px;
                    text-align: center;
                }

                .confirmation-icon {
                    font-size: 48px;
                    margin-bottom: 15px;
                    opacity: 0.9;
                }

                .confirmation-icon.warning { color: #ffc107; }
                .confirmation-icon.danger { color: #dc3545; }
                .confirmation-icon.success { color: #28a745; }
                .confirmation-icon.info { color: #17a2b8; }

                .confirmation-title {
                    font-size: 22px;
                    font-weight: 700;
                    margin: 0;
                    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
                }

                .confirmation-body {
                    padding: 30px;
                    text-align: center;
                }

                .confirmation-message {
                    font-size: 16px;
                    color: #0a253c;
                    margin: 0 0 15px 0;
                    line-height: 1.6;
                    font-weight: 500;
                }

                .confirmation-details {
                    background: #f8f9fa;
                    border-left: 4px solid #0a253c;
                    padding: 15px;
                    margin: 15px 0;
                    border-radius: 0 8px 8px 0;
                    text-align: left;
                }

                .confirmation-details h4 {
                    margin: 0 0 10px 0;
                    color: #0a253c;
                    font-size: 14px;
                    font-weight: 600;
                    text-transform: uppercase;
                }

                .confirmation-details p {
                    margin: 5px 0;
                    font-size: 14px;
                    color: #666;
                }

                .confirmation-footer {
                    padding: 20px 30px 30px;
                    display: flex;
                    gap: 15px;
                    justify-content: center;
                }

                .confirmation-footer button {
                    padding: 12px 25px;
                    border: none;
                    border-radius: 8px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    min-width: 120px;
                    justify-content: center;
                }

                .btn-cancel {
                    background: #6c757d;
                    color: white;
                }

                .btn-cancel:hover {
                    background: #5a6268;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
                }

                .btn-confirm {
                    background: linear-gradient(135deg, #0a253c 0%, #164463 100%);
                    color: white;
                }

                .btn-confirm:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(10, 37, 60, 0.3);
                }

                .btn-confirm.warning {
                    background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
                    color: #0a253c;
                }

                @media (max-width: 768px) {
                    .confirmation-container {
                        width: 95%;
                        margin: 20px;
                    }
                    .confirmation-footer {
                        flex-direction: column;
                    }
                    .confirmation-footer button {
                        width: 100%;
                    }
                }
                </style>
            `;
            document.head.insertAdjacentHTML('beforeend', styles);
        }

        bindEvents() {
            const modal = document.getElementById('confirmation-modal');
            const cancelBtn = modal.querySelector('.btn-cancel');
            const overlay = modal.querySelector('.confirmation-overlay');

            cancelBtn.addEventListener('click', () => this.hide());
            overlay.addEventListener('click', () => this.hide());

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && modal.classList.contains('show')) {
                    this.hide();
                }
            });
        }

        show(options = {}) {
            return new Promise((resolve) => {
                const modal = document.getElementById('confirmation-modal');
                const title = modal.querySelector('.confirmation-title');
                const message = modal.querySelector('.confirmation-message');
                const details = modal.querySelector('.confirmation-details');
                const confirmBtn = modal.querySelector('.btn-confirm');
                const icon = modal.querySelector('.confirmation-icon i');
                const iconContainer = modal.querySelector('.confirmation-icon');

                title.textContent = options.title || 'Confirmar Acción';
                message.textContent = options.message || '¿Estás seguro de que deseas realizar esta acción?';

                if (options.details) {
                    details.innerHTML = options.details;
                    details.style.display = 'block';
                } else {
                    details.style.display = 'none';
                }

                const type = options.type || 'info';
                iconContainer.className = `confirmation-icon ${type}`;

                const icons = {
                    danger: 'fa-exclamation-triangle',
                    warning: 'fa-exclamation-circle',
                    success: 'fa-check-circle',
                    info: 'fa-question-circle'
                };
                icon.className = `fas ${icons[type] || icons.info}`;

                confirmBtn.className = `btn-confirm ${type !== 'info' ? type : ''}`;
                confirmBtn.innerHTML = `<i class="fas fa-check"></i> ${options.confirmText || 'Confirmar'}`;

                modal.classList.add('show');
                confirmBtn.focus();

                const handleConfirm = () => {
                    confirmBtn.removeEventListener('click', handleConfirm);
                    this.hide();
                    resolve(true);
                };

                const handleCancel = () => {
                    modal.removeEventListener('hidden', handleCancel);
                    resolve(false);
                };

                confirmBtn.addEventListener('click', handleConfirm);
                modal.addEventListener('hidden', handleCancel, { once: true });
            });
        }

        hide() {
            const modal = document.getElementById('confirmation-modal');
            modal.classList.remove('show');
            setTimeout(() => {
                modal.dispatchEvent(new CustomEvent('hidden'));
            }, 300);
        }
    }

    // Instancia global del sistema de confirmaciones
    const confirmationSystem = new ConfirmationSystem();

    // ===== FUNCIONES DE CONFIRMACIÓN ESPECÍFICAS =====

    async function confirmarGuardarCambios(nombreUsuario = '') {
        const details = nombreUsuario ? `
            <h4>Usuario a modificar:</h4>
            <p><strong>${nombreUsuario}</strong></p>
            <p><small>Los cambios se aplicarán inmediatamente y no se pueden deshacer.</small></p>
        ` : '';

        return await confirmationSystem.show({
            title: 'Guardar Cambios',
            message: '¿Estás seguro de que deseas guardar los cambios realizados?',
            details: details,
            type: 'warning',
            confirmText: 'Guardar Cambios'
        });
    }

    async function confirmarCerrarSesion() {
        return await confirmationSystem.show({
            title: 'Cerrar Sesión',
            message: '¿Estás seguro de que deseas cerrar tu sesión?',
            details: '<p><small>Tendrás que iniciar sesión nuevamente para acceder al sistema.</small></p>',
            type: 'info',
            confirmText: 'Cerrar Sesión'
        });
    }

    // ===== SISTEMA DE NOTIFICACIONES =====

    function mostrarNotificacionMejorada(mensaje, tipo = 'info', duracion = 5000) {
        let container = document.getElementById('notificaciones-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notificaciones-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                max-width: 400px;
            `;
            document.body.appendChild(container);
        }

        const iconos = {
            exito: 'fa-check-circle',
            error: 'fa-exclamation-triangle',
            warning: 'fa-exclamation-circle',
            info: 'fa-info-circle'
        };

        const notificacion = document.createElement('div');
        notificacion.className = `notificacion ${tipo}`;
        notificacion.style.cssText = `
            background: white;
            border-left: 5px solid ${tipo === 'exito' ? '#28a745' : tipo === 'error' ? '#dc3545' : tipo === 'warning' ? '#ffc107' : '#0a253c'};
            padding: 15px 20px;
            margin-bottom: 10px;
            border-radius: 0 8px 8px 0;
            box-shadow: 0 4px 12px rgba(10, 37, 60, 0.15);
            position: relative;
            animation: slideInRight 0.4s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        `;

        notificacion.innerHTML = `
            <i class="fas ${iconos[tipo] || iconos.info}" style="font-size: 20px; color: ${tipo === 'exito' ? '#28a745' : tipo === 'error' ? '#dc3545' : tipo === 'warning' ? '#ffc107' : '#0a253c'};"></i>
            <span style="flex: 1; color: #0a253c; font-weight: 500;">${mensaje}</span>
            <button class="cerrar" aria-label="Cerrar notificación" style="background: none; border: none; font-size: 16px; cursor: pointer; color: #666; padding: 0;">&times;</button>
        `;

        container.appendChild(notificacion);

        const cerrarBtn = notificacion.querySelector('.cerrar');
        cerrarBtn.addEventListener('click', () => {
            notificacion.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => notificacion.remove(), 300);
        });

        if (duracion > 0) {
            setTimeout(() => {
                if (notificacion.parentNode) {
                    notificacion.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => notificacion.remove(), 300);
                }
            }, duracion);
        }

        // Agregar animaciones CSS si no existen
        if (!document.getElementById('notification-animations')) {
            const animationStyles = document.createElement('style');
            animationStyles.id = 'notification-animations';
            animationStyles.textContent = `
                @keyframes slideInRight {
                    from { opacity: 0; transform: translateX(30px); }
                    to { opacity: 1; transform: translateX(0); }
                }
                @keyframes slideOutRight {
                    from { opacity: 1; transform: translateX(0); }
                    to { opacity: 0; transform: translateX(30px); }
                }
            `;
            document.head.appendChild(animationStyles);
        }
    }

    // ===== FUNCIONES PRINCIPALES =====

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
        
        // Validación de formularios
        const inputs = document.querySelectorAll('input, select');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                clearValidationError(this);
            });
        });
        
        // Funciones de validación
        function validateField(field) {
            const value = field.value.trim();
            let isValid = true;
            let errorMessage = '';
            
            if (field.name === 'dni') {
                if (value && !/^\d{8}$/.test(value)) {
                    isValid = false;
                    errorMessage = 'El DNI debe tener exactamente 8 dígitos';
                }
            } else if (field.name === 'correo') {
                if (value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                    isValid = false;
                    errorMessage = 'Formato de correo no válido';
                }
            }
            
            if (!isValid) {
                showValidationError(field, errorMessage);
            } else {
                clearValidationError(field);
            }
            
            return isValid;
        }
        
        function showValidationError(field, message) {
            field.classList.add('error');
            
            const existingError = field.parentNode.querySelector('.validation-error');
            if (existingError) {
                existingError.remove();
            }
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'validation-error';
            errorDiv.textContent = message;
            field.parentNode.appendChild(errorDiv);
        }
        
        function clearValidationError(field) {
            field.classList.remove('error');
            field.classList.add('success');
            const errorDiv = field.parentNode.querySelector('.validation-error');
            if (errorDiv) {
                errorDiv.remove();
            }
        }
        
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

        // Mostrar mensajes de sesión
        const mensajes = document.querySelectorAll('.mensaje');
        mensajes.forEach(mensaje => {
            const texto = mensaje.textContent.trim();
            if (texto) {
                const tipo = mensaje.classList.contains('exito') ? 'exito' : 'error';
                setTimeout(() => {
                    mostrarNotificacionMejorada(texto, tipo);
                }, 500);
            }
        });

        // Mostrar notificación de bienvenida
        setTimeout(() => {
            mostrarNotificacionMejorada('Editando usuario: <?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellidos']) ?>', 'info', 3000);
        }, 1000);
    });

    // ===== FUNCIONES DE CONFIRMACIÓN =====

    /**
     * Manejar guardar cambios con confirmación
     */
    async function manejarGuardarCambios() {
        const form = document.getElementById('formEditarUsuario');
        
        // Validar formulario primero
        let isFormValid = true;
        const formInputs = form.querySelectorAll('input[required], select[required]');
        
        formInputs.forEach(input => {
            if (!input.value.trim()) {
                isFormValid = false;
                input.classList.add('error');
            }
        });
        
        if (!isFormValid) {
            mostrarNotificacionMejorada('Por favor, completa todos los campos requeridos', 'error');
            return;
        }

        // Obtener datos del formulario
        const formData = new FormData(form);
        const nombreCompleto = `${formData.get('nombre')} ${formData.get('apellidos')}`;
        
        // Mostrar confirmación
        const confirmado = await confirmarGuardarCambios(nombreCompleto);
        
        if (confirmado) {
            // Mostrar indicador de carga
            const submitBtn = form.querySelector('button');
            const originalHTML = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            submitBtn.disabled = true;
            
            // Enviar formulario
            form.submit();
        }
    }

    /**
     * Manejar cerrar sesión con confirmación
     */
    async function manejarCerrarSesion(event) {
        event.preventDefault();
        
        const confirmado = await confirmarCerrarSesion();
        
        if (confirmado) {
            // Mostrar mensaje de despedida
            mostrarNotificacionMejorada('Cerrando sesión...', 'info', 2000);
            
            setTimeout(() => {
                window.location.href = '../logout.php';
            }, 1000);
        }
    }

    // Prevenir envío accidental del formulario
    document.getElementById('formEditarUsuario').addEventListener('submit', function(e) {
        e.preventDefault();
        manejarGuardarCambios();
    });

    // Detectar cambios sin guardar
    let formChanged = false;
    const form = document.getElementById('formEditarUsuario');
    const inputs = form.querySelectorAll('input, select');
    
    inputs.forEach(input => {
        input.addEventListener('input', () => {
            formChanged = true;
        });
    });

    // Advertir sobre cambios sin guardar al salir
    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = '¿Estás seguro de que deseas salir? Los cambios no guardados se perderán.';
            return e.returnValue;
        }
    });

    // Remover advertencia cuando se guarde
    form.addEventListener('submit', () => {
        formChanged = false;
    });
    </script>
</body>
</html>