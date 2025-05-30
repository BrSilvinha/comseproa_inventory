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
    
    <!-- CSS consistente -->
    <link rel="stylesheet" href="../assets/css/listar-usuarios.css">
    <link rel="stylesheet" href="../assets/css/registrar-usuario-consistent.css">
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Registrar nuevo usuario en el sistema COMSEPROA">
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
            
            <!-- Productos -->
            <li class="submenu-container">
                <a href="#" aria-label="Menú Productos" aria-expanded="false" role="button" tabindex="0">
                    <span><i class="fas fa-boxes"></i> Productos</span>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <ul class="submenu" role="menu">
                    <li><a href="../productos/registrar.php" role="menuitem"><i class="fas fa-plus"></i> Registrar Producto</a></li>
                    <li><a href="../productos/listar.php" role="menuitem"><i class="fas fa-list"></i> Lista de Productos</a></li>
                    <li><a href="../productos/categorias.php" role="menuitem"><i class="fas fa-tags"></i> Categorías</a></li>
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
                                    echo '<span class="badge-small" aria-label="' . $total_pendientes . ' solicitudes pendientes">' . $total_pendientes . '</span>';
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
            <h1>Registrar Usuario</h1>
        </header>
        
        <div class="register-container">
            <!-- Mostrar mensajes -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="mensaje exito" role="alert" style="display: none;">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($mensaje)): ?>
                <div class="mensaje error" role="alert" style="display: none;">
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
            <div class="navigation-links">
                <a href="listar.php" class="btn" style="background: #17a2b8; color: white; text-decoration: none;">
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
    <script src="../assets/js/universal-confirmation-system.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Elementos principales
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const submenuContainers = document.querySelectorAll('.submenu-container');
        const form = document.getElementById('formRegistrarUsuario');
        
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

        // Manejo del formulario con confirmaciones
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
                    almacenSelect.style.background = 'rgba(255, 193, 7, 0.1)';
                } else {
                    almacenSelect.removeAttribute('required');
                    almacenSelect.style.borderColor = '';
                    almacenSelect.style.background = '';
                }
            });
        }
        
        // Validación de campos
        const inputs = document.querySelectorAll('input, select');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                clearValidationError(this);
            });
        });
        
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
            } else if (field.name === 'celular') {
                if (value && !/^[0-9]{9,15}$/.test(value)) {
                    isValid = false;
                    errorMessage = 'El celular debe tener entre 9 y 15 dígitos';
                }
            } else if (field.name === 'confirmar_contraseña') {
                const password = document.getElementById('contraseña').value;
                if (value && value !== password) {
                    isValid = false;
                    errorMessage = 'Las contraseñas no coinciden';
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

        // Mostrar mensajes de sesión como notificaciones
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
            mostrarNotificacion('Formulario de registro cargado correctamente', 'info', 3000);
        }, 1000);
    });

    // Función para cerrar sesión con confirmación
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
<?php $conn->close(); ?>