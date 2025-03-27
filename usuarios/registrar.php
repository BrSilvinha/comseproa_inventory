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
                $_SESSION['mensaje_exito'] = "Usuario registrado exitosamente.";
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
    <link rel="stylesheet" href="../assets/css/styles-dashboard.css">
    <link rel="stylesheet" href="../assets/css/styles-form.css">
    <link rel="stylesheet" href="../assets/css/styles-pendientes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
<!-- Botón de hamburguesa para dispositivos móviles -->
<button class="menu-toggle" id="menuToggle">
    <i class="fas fa-bars"></i>
</button>

<!-- Menú Lateral -->
<nav class="sidebar" id="sidebar">
    <h2>GRUPO SEAL</h2>
    <ul>
        <li><a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a></li>

        <!-- Usuarios - Solo visible para administradores -->
        <?php if ($usuario_rol == 'admin'): ?>
        <li class="submenu-container">
            <a href="#" aria-label="Menú Usuarios">
                <i class="fas fa-users"></i> Usuarios <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <li><a href="registrar.php"><i class="fas fa-user-plus"></i> Registrar Usuario</a></li>
                <li><a href="listar.php"><i class="fas fa-list"></i> Lista de Usuarios</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- Almacenes -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Almacenes">
                <i class="fas fa-warehouse"></i> Almacenes <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <?php if ($usuario_rol == 'admin'): ?>
                <li><a href="../almacenes/registrar.php"><i class="fas fa-plus"></i> Registrar Almacén</a></li>
                <?php endif; ?>
                <li><a href="../almacenes/listar.php"><i class="fas fa-list"></i> Lista de Almacenes</a></li>
            </ul>
        </li>
        
        <!-- Notificaciones -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Notificaciones">
                <i class="fas fa-bell"></i> Notificaciones <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <li><a href="../notificaciones/pendientes.php"><i class="fas fa-clock"></i> Solicitudes Pendientes 
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
                    echo '<span class="badge">' . $row_pendientes['total'] . '</span>';
                }
                ?>
                </a></li>
                <li><a href="../notificaciones/historial.php"><i class="fas fa-list"></i> Historial de Solicitudes</a></li>
                <li><a href="../uniformes/historial_entregas_uniformes.php"><i class="fas fa-tshirt"></i> Historial de Entregas de Uniformes</a></li>
            </ul>
        </li>

        <!-- Cerrar Sesión -->
        <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a></li>
    </ul>
</nav>

<main class="content" id="main-content">
    <h1>Registrar Usuario</h1>
    <div class="register-container">
        <?php if (!empty($mensaje)): ?>
            <p class="mensaje"><?php echo $mensaje; ?></p>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['mensaje_exito'])): ?>
            <p class="mensaje exito"><?php echo $_SESSION['mensaje_exito']; unset($_SESSION['mensaje_exito']); ?></p>
        <?php endif; ?>
        
        <form action="" method="POST">
            <div class="form-group">
                <input type="text" name="nombre" placeholder="Nombre" required>
                <input type="text" name="apellidos" placeholder="Apellidos" required>
            </div>
            <div class="form-group">
                <input type="text" name="dni" placeholder="DNI" maxlength="8" required>
                <input type="text" name="celular" placeholder="Celular" required>
            </div>
            <div class="form-group">
                <input type="email" name="correo" placeholder="Correo Electrónico" required>
                <input type="text" name="direccion" placeholder="Dirección" required>
            </div>
            <div class="form-group">
                <input type="password" name="contraseña" placeholder="Contraseña" required>
                <input type="password" name="confirmar_contraseña" placeholder="Confirmar Contraseña" required>
            </div>
            <div class="form-group">
                <select name="rol" required>
                    <option value="">Seleccione un rol</option>
                    <option value="admin">Administrador</option>
                    <option value="almacenero">Almacenero</option>
                </select>
                <select name="almacen_id">
                    <option value="">Seleccione un almacén</option>
                    <?php while ($almacen = $almacenes_result->fetch_assoc()): ?>
                        <option value="<?php echo $almacen["id"]; ?>"><?php echo htmlspecialchars($almacen["nombre"]); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit">Registrar</button>
        </form>
    </div>
</main>
<script src="../assets/js/script.js"></script>
</body>
</html>
<?php $conn->close(); ?>