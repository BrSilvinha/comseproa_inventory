<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    header("Location: /views/login_form.php");
    exit();
}

require_once "../config/database.php"; // Conectar a la base de datos

// Validar el ID del almacén
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    die("ID de almacén no válido");
}

$almacen_id = $_GET['id'];

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>

<!-- Menú Lateral -->
<nav class="sidebar">
    <h2>GRUPO SEAL</h2>
    <ul>
        <li><a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a></li>
        <li class="submenu-container">
            <a href="#"><i class="fas fa-users"></i> Usuarios <i class="fas fa-chevron-down"></i></a>
            <ul class="submenu">
                <li><a href="../usuarios/registrar.php"><i class="fas fa-user-plus"></i> Registrar Usuario</a></li>
                <li><a href="../usuarios/listar.php"><i class="fas fa-list"></i> Lista de Usuarios</a></li>
            </ul>
        </li>
        <li class="submenu-container">
            <a href="#"><i class="fas fa-warehouse"></i> Almacenes <i class="fas fa-chevron-down"></i></a>
            <ul class="submenu">
                <li><a href="registrar.php"><i class="fas fa-plus"></i> Registrar Almacén</a></li>
                <li><a href="listar.php"><i class="fas fa-list"></i> Lista de Almacenes</a></li>
            </ul>
        </li>
        <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a></li>
    </ul>
</nav>

<!-- Contenido Principal -->
<div class="main-content">
    <h2><?php echo htmlspecialchars($almacen['nombre']); ?></h2>
    <h3>Categorías en este almacén</h3>
    <div class="categorias-container">
        <?php if ($categorias->num_rows > 0): ?>
            <?php while ($categoria = $categorias->fetch_assoc()): ?>
                <div class="categoria-card">
                    <i class="fas fa-box-open fa-2x"></i> <!-- Ícono de categoría -->
                    <h4><?php echo htmlspecialchars($categoria['nombre']); ?></h4>
                    <p>Productos: <?php echo $categoria['total_productos']; ?></p>

                    <button class="btn-registrar" onclick="location.href='../almacenes/registrar_producto.php?almacen_id=<?php echo $almacen_id; ?>&categoria_id=<?php echo $categoria['id']; ?>'">
                        <i class="fas fa-plus"></i> Registrar Producto
                    </button>


                    <button class="btn-lista" onclick="location.href='/productos/listar.php?almacen_id=<?php echo $almacen_id; ?>&categoria_id=<?php echo $categoria['id']; ?>'">
                        <i class="fas fa-list"></i> Lista de Productos
                    </button>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No hay categorías registradas.</p>
        <?php endif; ?>
    </div>
</div>

<script src="../assets/js/script.js"></script>
</body>
</html>
