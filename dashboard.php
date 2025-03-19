<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: /views/login_form.php");
    exit();
}

// Evita secuestro de sesión
session_regenerate_id(true);

$user_name = isset($_SESSION["user_name"]) ? $_SESSION["user_name"] : "Usuario";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - COMSEPROA</title>
    <link rel="stylesheet" href="assets/css/styles-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>

<!-- Menú Lateral -->
<nav class="sidebar">
    <h2>GRUPO SEAL</h2>
    <ul>
        <li><a href="dashboard.php"><i class="fas fa-home"></i> Inicio</a></li>

        <!-- Usuarios -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Usuarios">
                <i class="fas fa-users"></i> Usuarios <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <li><a href="usuarios/registrar.php"><i class="fas fa-user-plus"></i> Registrar Usuario</a></li>
                <li><a href="usuarios/listar.php"><i class="fas fa-list"></i> Lista de Usuarios</a></li>
            </ul>
        </li>

        <!-- Almacenes -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Almacenes">
                <i class="fas fa-warehouse"></i> Almacenes <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <li><a href="almacenes/registrar.php"><i class="fas fa-plus"></i> Registrar Almacén</a></li>
                <li><a href="almacenes/listar.php"><i class="fas fa-list"></i> Lista de Almacenes</a></li>
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
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a></li>
    </ul>
</nav>

<!-- Contenido Principal -->
<main class="content" id="main-content">
    <h1>Bienvenido, <?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?></h1>
    <div id="contenido-dinamico">
        <section class="dashboard-grid">
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
        </section>
    </div>
</main>
<script src="assets/js/script.js"></script>
</body>
</html>
