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
    <title>Editar Usuario - GRUPO SEAL</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/styles-dashboard.css">
    <link rel="stylesheet" href="../assets/css/editar-usuario.css">
    <link rel="stylesheet" href="../assets/css/styles-pendientes.css">
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Editar información de usuario en el sistema GRUPO SEAL">
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
        <h2>GRUPO SEAL</h2>
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
            
            <!-- Productos -->
            <li class="submenu-container">
                <a href="#" aria-label="Menú Productos" aria-expanded="false" role="button" tabindex="0">
                    <span><i class="fas fa-boxes"></i> Productos</span>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <ul class="submenu" role="menu">
                    <li><a href="../productos/registrar.php" role="menuitem"><i class="fas fa-plus"></i> Registrar Producto</a></li>
                    <li><a href="../productos/listar.php" role="menuitem"><i class="fas fa-list"></i> Lista de Productos</a></li>
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

    <!-- JavaScript -->
    <script src="../assets/js/script.js"></script>
    <script src="../assets/js/universal-confirmation-system.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
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
                    mostrarNotificacion(texto, tipo);
                }, 500);
            }
        });

        // Mostrar notificación de bienvenida
        setTimeout(() => {
            mostrarNotificacion('Editando usuario: <?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellidos']) ?>', 'info', 3000);
        }, 1000);
        
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
    });

    // Funciones de confirmación
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
            mostrarNotificacion('Por favor, completa todos los campos requeridos', 'error');
            return;
        }

        // Obtener datos del formulario
        const formData = new FormData(form);
        const nombreCompleto = `${formData.get('nombre')} ${formData.get('apellidos')}`;
        
        // Mostrar confirmación
        const confirmado = await confirmarEdicionUsuario(nombreCompleto);
        
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

    async function manejarCerrarSesion(event) {
        event.preventDefault();
        
        const confirmado = await confirmarCerrarSesion();
        
        if (confirmado) {
            // Mostrar mensaje de despedida
            mostrarNotificacion('Cerrando sesión...', 'info', 2000);
            
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