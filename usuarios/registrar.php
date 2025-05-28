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

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = htmlspecialchars(trim($_POST["nombre"]));
    $apellidos = htmlspecialchars(trim($_POST["apellidos"]));
    $dni = trim($_POST["dni"]);
    $celular = trim($_POST["celular"]);
    $direccion = htmlspecialchars(trim($_POST["direccion"]));
    $correo = trim($_POST["correo"]);
    $contraseña = $_POST["contraseña"];
    $confirmar_contraseña = $_POST["confirmar_contraseña"];
    $rol = trim($_POST["rol"]);
    $almacen_id = isset($_POST["almacen_id"]) ? intval($_POST["almacen_id"]) : NULL;

    if (
        empty($nombre) || empty($apellidos) || empty($dni) || empty($celular) || empty($correo) || 
        empty($contraseña) || empty($confirmar_contraseña) || empty($rol)
    ) {
        $mensaje = "Todos los campos son obligatorios.";
    } elseif (strlen($dni) != 8 || !ctype_digit($dni)) {
        $mensaje = "El DNI debe tener exactamente 8 números.";
    } elseif (!preg_match("/^[0-9]{9,15}$/", $celular)) {
        $mensaje = "El número de celular debe tener entre 9 y 15 dígitos.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "Formato de correo no válido.";
    } elseif ($contraseña !== $confirmar_contraseña) {
        $mensaje = "Las contraseñas no coinciden.";
    } elseif (strlen($contraseña) < 6) {
        $mensaje = "La contraseña debe tener al menos 6 caracteres.";
    } elseif ($rol === "almacenero" && empty($almacen_id)) {
        $mensaje = "Debe asignar un almacén al almacenero.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE correo = ? OR dni = ?");
        $stmt->bind_param("ss", $correo, $dni);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $mensaje = "El usuario con este correo o DNI ya está registrado.";
        } else {
            $contrasena_hash = password_hash($contraseña, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO usuarios (nombre, apellidos, dni, celular, direccion, correo, contrasena, rol, estado, almacen_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'activo', ?)");
            $stmt->bind_param("ssssssssi", $nombre, $apellidos, $dni, $celular, $direccion, $correo, $contrasena_hash, $rol, $almacen_id);

            if ($stmt->execute()) {
                $_SESSION['success'] = "✅ Usuario registrado exitosamente.";
                header("Location: registrar.php");
                exit();
            } else {
                $mensaje = "Error al registrar el usuario.";
            }
        }
        $stmt->close();
    }
}

$almacenes_result = $conn->query("SELECT id, nombre FROM almacenes");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Usuario - COMSEPROA</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <!-- CSS estandarizado -->
    <link rel="stylesheet" href="../assets/css/sidebar-styles.css">
    <link rel="stylesheet" href="../assets/css/registrar-usuario.css">
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Registrar nuevo usuario en el sistema COMSEPROA">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon.ico">
</head>
<body>
    <!-- Incluir sidebar estandarizado -->
    <?php include '../assets/includes/sidebar-component.php'; ?>

    <main class="content" id="main-content" role="main">
        <header>
            <h1>Registrar Usuario</h1>
        </header>
        
        <div class="register-container">
            <!-- Mostrar mensajes -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="mensaje exito" role="alert">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($mensaje)): ?>
                <div class="mensaje error" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>
            
            <form action="" method="POST" id="formRegistrarUsuario" novalidate>
                <div class="form-group">
                    <input type="text" id="nombre" name="nombre" placeholder="Nombre" required aria-label="Nombre del usuario" autocomplete="given-name" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
                    <input type="text" id="apellidos" name="apellidos" placeholder="Apellidos" required aria-label="Apellidos del usuario" autocomplete="family-name" value="<?= htmlspecialchars($_POST['apellidos'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <input type="text" id="dni" name="dni" placeholder="DNI (8 dígitos)" maxlength="8" required pattern="\d{8}" title="El DNI debe contener 8 dígitos" aria-label="DNI del usuario" value="<?= htmlspecialchars($_POST['dni'] ?? '') ?>">
                    <input type="tel" id="celular" name="celular" placeholder="Celular (9-15 dígitos)" required pattern="[0-9]{9,15}" title="El celular debe tener entre 9 y 15 dígitos" aria-label="Número de celular" value="<?= htmlspecialchars($_POST['celular'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <input type="email" id="correo" name="correo" placeholder="Correo Electrónico" required aria-label="Correo electrónico" autocomplete="email" value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>">
                    <input type="text" id="direccion" name="direccion" placeholder="Dirección" required aria-label="Dirección del usuario" autocomplete="street-address" value="<?= htmlspecialchars($_POST['direccion'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <input type="password" id="contraseña" name="contraseña" placeholder="Contraseña (mín. 6 caracteres)" required minlength="6" aria-label="Contraseña" autocomplete="new-password">
                    <input type="password" id="confirmar_contraseña" name="confirmar_contraseña" placeholder="Confirmar Contraseña" required minlength="6" aria-label="Confirmar contraseña" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <select id="rol" name="rol" required aria-label="Rol del usuario">
                        <option value="">Seleccione un rol</option>
                        <option value="admin" <?= (isset($_POST['rol']) && $_POST['rol'] == 'admin') ? 'selected' : '' ?>>Administrador</option>
                        <option value="almacenero" <?= (isset($_POST['rol']) && $_POST['rol'] == 'almacenero') ? 'selected' : '' ?>>Almacenero</option>
                    </select>
                    <select id="almacen_id" name="almacen_id" aria-label="Almacén asignado (requerido para almaceneros)">
                        <option value="">Seleccione un almacén</option>
                        <?php while ($almacen = $almacenes_result->fetch_assoc()): ?>
                            <option value="<?php echo $almacen["id"]; ?>" <?= (isset($_POST['almacen_id']) && $_POST['almacen_id'] == $almacen["id"]) ? 'selected' : '' ?>>
                                <?php echo htmlspecialchars($almacen["nombre"]); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit">
                    <i class="fas fa-user-plus"></i> Registrar Usuario
                </button>
            </form>
            
            <!-- Enlaces de navegación rápida -->
            <div style="text-align: center; margin-top: 30px; padding-top: 25px; border-top: 2px solid #e9ecef;">
                <a href="listar.php" class="btn" style="background: #17a2b8; color: white; text-decoration: none; margin-right: 15px;">
                    <i class="fas fa-list"></i> Ver Lista de Usuarios
                </a>
                <a href="../dashboard.php" class="btn" style="background: #6c757d; color: white; text-decoration: none;">
                    <i class="fas fa-home"></i> Volver al Dashboard
                </a>
            </div>
        </div>
    </main>

    <!-- Contenedor para notificaciones dinámicas -->
    <div id="notificaciones-container" role="alert" aria-live="polite"></div>

    <!-- Scripts -->
    <script src="../assets/js/script.js"></script>
    <script src="../assets/js/universal-confirmation-system.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('formRegistrarUsuario');
        
        if (form) {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                // Obtener datos del formulario
                const nombre = document.getElementById('nombre').value.trim();
                const apellidos = document.getElementById('apellidos').value.trim();
                const rol = document.getElementById('rol').value;
                
                // Validar campos requeridos
                if (!nombre || !apellidos || !rol) {
                    mostrarNotificacion('Por favor, completa todos los campos obligatorios', 'error');
                    return;
                }
                
                // Mostrar confirmación
                const confirmado = await confirmarRegistroUsuario(nombre + ' ' + apellidos, rol);
                
                if (confirmado) {
                    // Mostrar indicador de carga
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalHTML = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
                    submitBtn.disabled = true;
                    
                    // Enviar formulario
                    form.submit();
                }
            });
        }
        
        // Validación en tiempo real para rol y almacén
        const rolSelect = document.getElementById('rol');
        const almacenSelect = document.getElementById('almacen_id');
        
        if (rolSelect && almacenSelect) {
            rolSelect.addEventListener('change', function() {
                if (this.value === 'almacenero') {
                    almacenSelect.setAttribute('required', 'required');
                    almacenSelect.style.borderColor = '#ffc107';
                } else {
                    almacenSelect.removeAttribute('required');
                    almacenSelect.style.borderColor = '';
                }
            });
        }
        
        // Sistema de notificaciones mejorado
        function mostrarNotificacion(mensaje, tipo = 'info') {
            const container = document.getElementById('notificaciones-container');
            const notificacion = document.createElement('div');
            notificacion.className = `notificacion ${tipo}`;
            notificacion.innerHTML = `
                ${mensaje}
                <span class="cerrar">&times;</span>
            `;
            
            container.appendChild(notificacion);
            
            const cerrarBtn = notificacion.querySelector('.cerrar');
            cerrarBtn.addEventListener('click', function() {
                notificacion.remove();
            });
            
            setTimeout(() => {
                if (notificacion.parentNode) {
                    notificacion.remove();
                }
            }, 5000);
        }
        
        // Mostrar mensajes de sesión como notificaciones
        const mensajes = document.querySelectorAll('.mensaje');
        mensajes.forEach(mensaje => {
            const texto = mensaje.textContent.trim();
            if (texto) {
                const tipo = mensaje.classList.contains('exito') ? 'exito' : 'error';
                setTimeout(() => {
                    mostrarNotificacion(texto, tipo);
                    mensaje.style.display = 'none';
                }, 500);
            }
        });
        
        // Hacer disponible globalmente
        window.mostrarNotificacion = mostrarNotificacion;
    });
    </script>
</body>
</html>
<?php $conn->close(); ?>