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
        die("El DNI debe tener exactamente 8 dígitos.");
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        die("Correo electrónico no válido.");
    }

    $stmt = $conn->prepare("UPDATE usuarios SET nombre=?, apellidos=?, dni=?, correo=?, rol=?, estado=?, almacen_id=? WHERE id=?");
    $stmt->bind_param("ssssssii", $nombre, $apellidos, $dni, $correo, $rol, $estado, $almacen_id, $id);

    if ($stmt->execute()) {
        header("Location: listar.php?mensaje=Usuario actualizado correctamente");
        exit();
    } else {
        echo "Error al actualizar usuario.";
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
                    <li><a href="../usuarios/registrar.php"><i class="fas fa-user-plus"></i> Registrar Usuario</a></li>
                    <li><a href="../usuarios/listar.php"><i class="fas fa-list"></i> Lista de Usuarios</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- Almacenes - Ajustado según permisos -->
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
                </ul>
            </li>

            <!-- Cerrar Sesión -->
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a></li>
        </ul>
    </nav>
    
    <main class="content" id="main-content">
        <h1>Editar Usuario</h1>
        <div class="register-container">
            <form method="post">
                <div class="form-group">
                    <input type="text" name="nombre" placeholder="Nombre" value="<?= htmlspecialchars($usuario['nombre']) ?>" required>
                    <input type="text" name="apellidos" placeholder="Apellidos" value="<?= htmlspecialchars($usuario['apellidos']) ?>" required>
                </div>
                <div class="form-group">
                    <input type="text" name="dni" placeholder="DNI" maxlength="8" value="<?= htmlspecialchars($usuario['dni']) ?>" required pattern="\d{8}" title="El DNI debe contener 8 dígitos">
                    <input type="email" name="correo" placeholder="Correo Electrónico" value="<?= htmlspecialchars($usuario['correo']) ?>" required>
                </div>
                <div class="form-group">
                    <select name="rol" required>
                        <option value="admin" <?= $usuario['rol'] == 'admin' ? 'selected' : ''; ?>>Administrador</option>
                        <option value="almacenero" <?= $usuario['rol'] == 'almacenero' ? 'selected' : ''; ?>>Almacenero</option>
                    </select>
                    <select name="almacen_id">
                        <option value="">Seleccione un almacén</option>
                        <?php foreach ($almacenes as $almacen): ?>
                            <option value="<?= $almacen["id"] ?>" <?= ($usuario['almacen_id'] == $almacen["id"]) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($almacen["nombre"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <select name="estado">
                        <option value="activo" <?= $usuario['estado'] == 'activo' ? 'selected' : ''; ?>>Activo</option>
                        <option value="inactivo" <?= $usuario['estado'] == 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                    </select>
                </div>
                <button type="submit" class="btn">Guardar Cambios</button>
            </form>
        </div>
    </main>
    
    <script src="../assets/js/script.js"></script>
    <!-- Contenedor para notificaciones dinámicas -->
    <div id="notificaciones-container"></div>

</body>
</html>