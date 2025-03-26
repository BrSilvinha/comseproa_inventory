<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    header("Location: /views/login_form.php");
    exit();
}

// Evitar secuestro de sesión, igual que en dashboard.php
session_regenerate_id(true);

// Obtener información del usuario
$user_name = isset($_SESSION["user_name"]) ? $_SESSION["user_name"] : "Usuario";
$usuario_almacen_id = isset($_SESSION["almacen_id"]) ? $_SESSION["almacen_id"] : null;
$usuario_rol = isset($_SESSION["user_role"]) ? $_SESSION["user_role"] : "usuario";

require_once "../config/database.php"; // Conectar a la base de datos

// Validar el ID del almacén
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    die("ID de almacén no válido");
}

$almacen_id = $_GET['id'];

// Si el usuario no es admin, verificar que solo pueda acceder a su almacén asignado
if ($usuario_rol != 'admin' && $usuario_almacen_id != $almacen_id) {
    // Redirigir o mostrar mensaje de error si intenta acceder a un almacén que no es el suyo
    die("No tienes permiso para acceder a este almacén");
}

// Obtener información del almacén
$sql = "SELECT * FROM almacenes WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $almacen_id);
$stmt->execute();
$result = $stmt->get_result();
$almacen = $result->fetch_assoc();
$stmt->close();

if (!$almacen) {
    die("Almacén no encontrado");
}

// Obtener todas las categorías
$sql_categorias = "SELECT c.id, c.nombre,
                   (SELECT COUNT(*) FROM productos p WHERE p.categoria_id = c.id AND p.almacen_id = ?) AS total_productos
                   FROM categorias c";
$stmt_categorias = $conn->prepare($sql_categorias);
$stmt_categorias->bind_param("i", $almacen_id);
$stmt_categorias->execute();
$categorias = $stmt_categorias->get_result();
$stmt_categorias->close();

// Contar solicitudes pendientes para el badge
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

$total_pendientes = 0;
if ($result_pendientes && $row_pendientes = $result_pendientes->fetch_assoc()) {
    $total_pendientes = $row_pendientes['total'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Almacén - COMSEPROA</title>
    <link rel="stylesheet" href="../assets/css/styles-dashboard.css">
    <link rel="stylesheet" href="../assets/css/styles-almacenes.css">
    <link rel="stylesheet" href="../assets/css/styles-categorias.css">
    <link rel="stylesheet" href="../assets/css/styles-pendientes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body data-almacen-id="<?php echo $almacen_id; ?>">

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
                <li><a href="registrar.php"><i class="fas fa-plus"></i> Registrar Almacén</a></li>
                <?php endif; ?>
                <li><a href="listar.php"><i class="fas fa-list"></i> Lista de Almacenes</a></li>
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
                if ($total_pendientes > 0) {
                    echo '<span class="badge">' . $total_pendientes . '</span>';
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

<!-- Contenido Principal -->
<main class="content" id="main-content">
    <h2><?php echo htmlspecialchars($almacen['nombre']); ?></h2>
    <h3>Categorías en este almacén</h3>
    <div class="categorias-container">
        <?php if ($categorias->num_rows > 0): ?>
            <?php while ($categoria = $categorias->fetch_assoc()): ?>
                <div class="categoria-card">
                    <i class="fas fa-box-open fa-2x"></i> <!-- Ícono de categoría -->
                    <h4><?php echo htmlspecialchars($categoria['nombre']); ?></h4>
                    <p>Productos: <?php echo $categoria['total_productos']; ?></p>

                    <button class="btn-registrar" onclick="location.href='../productos/registrar.php?almacen_id=<?php echo $almacen_id; ?>&categoria_id=<?php echo $categoria['id']; ?>'">
                        <i class="fas fa-plus"></i> Registrar Producto
                    </button>
                    <button class="btn-lista" onclick="location.href='../productos/listar.php?almacen_id=<?php echo $almacen_id; ?>&categoria_id=<?php echo $categoria['id']; ?>'">
                        <i class="fas fa-list"></i> Lista de Productos
                    </button>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No hay categorías registradas.</p>
        <?php endif; ?>
    </div>
</main>

<script src="../assets/js/script.js"></script>
</body>
</html>