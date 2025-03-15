<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: /views/login_form.php");
    exit();
}

session_regenerate_id(true);

require_once "../config/database.php"; // Asegúrate de que este archivo conecta a la BD

$user_name = $_SESSION["user_name"] ?? "Usuario";

// Consultar almacenes registrados
$sql = "SELECT id, nombre, ubicacion FROM almacenes ORDER BY id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - COMSEPROA</title>
    <link rel="stylesheet" href="../assets/css/styles-dashboard.css">
    <link rel="stylesheet" href="../assets/css/styles-almacenes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>

<!-- Menú Lateral -->
<nav class="sidebar">
    <h2>GRUPO SEAL</h2>
    <ul>
        <li><a href="/dashboard.php"><i class="fas fa-home"></i> Inicio</a></li>
        <li class="submenu-container">
            <a href="#"><i class="fas fa-users"></i> Usuarios <i class="fas fa-chevron-down"></i></a>
            <ul class="submenu">
                <li><a href="/usuarios/registrar.php"><i class="fas fa-user-plus"></i> Registrar Usuario</a></li>
                <li><a href="/usuarios/listar.php"><i class="fas fa-list"></i> Lista de Usuarios</a></li>
            </ul>
        </li>
        <li class="submenu-container">
            <a href="#"><i class="fas fa-warehouse"></i> Almacenes <i class="fas fa-chevron-down"></i></a>
            <ul class="submenu">
                <li><a href="/almacenes/registrar.php"><i class="fas fa-plus"></i> Registrar Almacén</a></li>
                <li><a href="/almacenes/listar.php"><i class="fas fa-list"></i> Lista de Almacenes</a></li>
            </ul>
        </li>
        <li><a href="/logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a></li>
    </ul>
</nav>

<!-- Contenido Principal -->
<div class="main-content">
    <h2>Almacenes Registrados</h2>

    <div class="almacenes-container">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="almacen-card">
                    <h3><?php echo htmlspecialchars($row["nombre"]); ?></h3>
                    <p><strong>Ubicación:</strong> <?php echo htmlspecialchars($row["ubicacion"]); ?></p>
                    <a href="/almacenes/ver-almacen.php?id=<?php echo htmlspecialchars($row['id']); ?>" class="btn-ver">
                        <i class="fas fa-eye"></i> Ver Almacén
                    </a>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No hay almacenes registrados.</p>
        <?php endif; ?>
    </div>
</div>

<script src="../assets/js/script.js"></script>
</body>
</html>
