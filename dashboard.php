<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: /views/login_form.php");
    exit();
}

// Prevent session hijacking
session_regenerate_id(true);

$user_name = isset($_SESSION["user_name"]) ? $_SESSION["user_name"] : "Usuario";
$usuario_almacen_id = isset($_SESSION["almacen_id"]) ? $_SESSION["almacen_id"] : null;
$usuario_rol = isset($_SESSION["user_role"]) ? $_SESSION["user_role"] : "usuario";

// Require database connection
require_once "config/database.php";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - COMSEPROA</title>
    <link rel="stylesheet" href="assets/css/styles-dashboard.css">
    <link rel="stylesheet" href="../assets/css/styles-pendientes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>

<!-- Mobile hamburger menu button -->
<button class="menu-toggle" id="menuToggle">
    <i class="fas fa-bars"></i>
</button>

<!-- Side Menu -->
<nav class="sidebar" id="sidebar">
    <h2>GRUPO SEAL</h2>
    <ul>
        <li><a href="dashboard.php"><i class="fas fa-home"></i> Inicio</a></li>

        <!-- Users - Only visible to administrators -->
        <?php if ($usuario_rol == 'admin'): ?>
        <li class="submenu-container">
            <a href="#" aria-label="Menú Usuarios">
                <i class="fas fa-users"></i> Usuarios <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <li><a href="usuarios/registrar.php"><i class="fas fa-user-plus"></i> Registrar Usuario</a></li>
                <li><a href="usuarios/listar.php"><i class="fas fa-list"></i> Lista de Usuarios</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- Warehouses - Adjusted according to permissions -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Almacenes">
                <i class="fas fa-warehouse"></i> Almacenes <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <?php if ($usuario_rol == 'admin'): ?>
                <li><a href="almacenes/registrar.php"><i class="fas fa-plus"></i> Registrar Almacén</a></li>
                <?php endif; ?>
                <li><a href="almacenes/listar.php"><i class="fas fa-list"></i> Lista de Almacenes</a></li>
            </ul>
        </li>
        
        <!-- Notifications -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Notificaciones">
                <i class="fas fa-bell"></i> Notificaciones <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <li><a href="notificaciones/pendientes.php"><i class="fas fa-clock"></i> Solicitudes Pendientes 
                <?php 
                // Count pending requests to show in badge
                $sql_pendientes = "SELECT COUNT(*) as total FROM solicitudes_transferencia WHERE estado = 'pendiente'";
                
                // If user is not admin, filter by their warehouse
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
                <li><a href="notificaciones/historial.php"><i class="fas fa-list"></i> Historial de Solicitudes</a></li>
                <li><a href="uniformes/historial_entregas_uniformes.php"><i class="fas fa-tshirt"></i> Historial de Entregas de Uniformes</a></li>
            </ul>
        </li>

        <!-- Logout -->
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a></li>
    </ul>
</nav>

<!-- Main Content -->
<main class="content" id="main-content">
    <h1>Bienvenido, <?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?></h1>
    <div id="contenido-dinamico">
        <section class="dashboard-grid">
            <?php if ($usuario_rol == 'admin'): ?>
            <!-- Cards for administrators -->
            <article class="card">
                <h3>Usuarios</h3>
                <p>Administrar usuarios del sistema</p>
                <a href="usuarios/listar.php">Ver más</a>
            </article>
            <article class="card">
                <h3>Almacenes</h3>
                <p>Ver ubicaciones de los almacenes</p>
                <a href="almacenes/listar.php">Ver más</a>
            </article>
            <article class="card">
                <h3>Registrar Usuario</h3>
                <p>Agregar un nuevo usuario</p>
                <a href="usuarios/registrar.php">Registrar</a>
            </article>
            <?php else: ?>
            <!-- Cards for regular users -->
            <article class="card">
                <h3>Mi Almacén</h3>
                <p>Ver información de tu almacén asignado</p>
                <a href="almacenes/listar.php">Ver más</a>
            </article>
            <article class="card">
                <h3>Solicitudes Pendientes</h3>
                <p>Revisar solicitudes de transferencia pendientes</p>
                <a href="notificaciones/pendientes.php">Ver más</a>
            </article>
            <article class="card">
                <h3>Historial</h3>
                <p>Ver historial de solicitudes</p>
                <a href="notificaciones/historial.php">Ver más</a>
            </article>
            <?php endif; ?>
        </section>
    </div>
</main>

<!-- Container for dynamic notifications -->
<div id="notificaciones-container"></div>

<script src="assets/js/script.js"></script>
</body>
</html>