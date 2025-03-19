<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

// Evita secuestro de sesión
session_regenerate_id(true);

require_once "../config/database.php";

$mensaje = "";
$error = "";
$nombre = "";
$ubicacion = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!empty($_POST["nombre"]) && !empty($_POST["ubicacion"])) {
        $nombre = trim($_POST["nombre"]);
        $ubicacion = trim($_POST["ubicacion"]);

        // Verificar si el almacén ya existe
        $sql_check = "SELECT id FROM almacenes WHERE nombre = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("s", $nombre);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $error = "⚠️ El almacén ya existe.";
        } else {
            // Insertar el nuevo almacén
            $sql = "INSERT INTO almacenes (nombre, ubicacion) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param("ss", $nombre, $ubicacion);
                if ($stmt->execute()) {
                    $mensaje = "✅ Almacén registrado con éxito.";
                    // Limpiar valores después del registro exitoso
                    $nombre = "";
                    $ubicacion = "";
                } else {
                    $error = "❌ Error al registrar el almacén: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = "❌ Error en la consulta SQL: " . $conn->error;
            }
        }
        $stmt_check->close();
    } else {
        $error = "⚠️ Todos los campos son obligatorios.";
    }
}

$user_name = isset($_SESSION["user_name"]) ? $_SESSION["user_name"] : "Usuario";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Almacén - COMSEPROA</title>
    <link rel="stylesheet" href="../assets/css/styles-dashboard.css">
    <link rel="stylesheet" href="../assets/css/styles-almacenes.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>

<!-- Menú Lateral -->
<nav class="sidebar">
    <h2>GRUPO SEAL</h2>
    <ul>
        <li><a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a></li>

        <!-- Usuarios -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Usuarios">
                <i class="fas fa-users"></i> Usuarios <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <li><a href="../usuarios/registrar.php"><i class="fas fa-user-plus"></i> Registrar Usuario</a></li>
                <li><a href="../usuarios/listar.php"><i class="fas fa-list"></i> Lista de Usuarios</a></li>
            </ul>
        </li>

        <!-- Almacenes -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Almacenes">
                <i class="fas fa-warehouse"></i> Almacenes <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <li><a href="registrar.php"><i class="fas fa-plus"></i> Registrar Almacén</a></li>
                <li><a href="listar.php"><i class="fas fa-list"></i> Lista de Almacenes</a></li>
            </ul>
        </li>
        <!-- Notificaciones -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Notificaciones">
                <i class="fas fa-bell"></i> Notificaciones <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <li><a href="notificaciones/pendientes.php"><i class="fas fa-clock"></i> Solicitudes Pendientes <span class="badge">3</span></a></li>
                <li><a href="notificaciones/historial.php"><i class="fas fa-list"></i> Historial de Solicitudes</a></li>
            </ul>
        </li>
        <!-- Cerrar Sesión -->
        <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a></li>
    </ul>
</nav>

<!-- Contenido Principal -->
<div class="main-content">
    <h2>Registrar Nuevo Almacén</h2>

    <?php if (!empty($mensaje)): ?>
        <p class="message"><?php echo $mensaje; ?></p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p class="error"><?php echo $error; ?></p>
    <?php endif; ?>

    <div class="form-container">
        <form action="" method="POST">
            <label for="nombre">Nombre del almacén:</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>" required>

            <label for="ubicacion">Ubicación:</label>
            <input type="text" id="ubicacion" name="ubicacion" value="<?php echo htmlspecialchars($ubicacion); ?>" required>

            <button type="submit"><i class="fas fa-save"></i> Registrar</button>
        </form>
    </div>
</div>

<script src="../assets/js/script.js"></script>
</body>
</html>
