<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: /views/login_form.php");
    exit();
}

session_regenerate_id(true);

require_once "../config/database.php"; // Asegúrate de que este archivo conecta a la BD

$user_name = $_SESSION["user_name"] ?? "Usuario";
// Obtener el rol y el almacén asignado del usuario
$usuario_rol = $_SESSION["user_role"] ?? "usuario";
$usuario_almacen_id = $_SESSION["almacen_id"] ?? null;

// Consultar almacenes registrados
// Si es admin, mostrar todos los almacenes
// Si no es admin, mostrar solo el almacén asignado
if ($usuario_rol == 'admin') {
    $sql = "SELECT id, nombre, ubicacion FROM almacenes ORDER BY id DESC";
    $result = $conn->query($sql);
} else {
    // Si no es admin, verificar que tenga un almacén asignado
    if ($usuario_almacen_id) {
        $sql = "SELECT id, nombre, ubicacion FROM almacenes WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $usuario_almacen_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        // Si no tiene almacén asignado
        $result = false;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - COMSEPROA</title>
    <link rel="stylesheet" href="../assets/css/styles-dashboard.css">
    <link rel="stylesheet" href="../assets/css/styles-almacenes.css">
    <link rel="stylesheet" href="../assets/css/styles-pendientes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>

<!-- Menú Lateral -->
<nav class="sidebar">
    <h2>GRUPO SEAL</h2>
    <ul>
        <li><a href="/dashboard.php"><i class="fas fa-home"></i> Inicio</a></li>
        
        <!-- Usuarios - Solo visible para administradores -->
        <?php if ($usuario_rol == 'admin'): ?>
        <li class="submenu-container">
            <a href="#"><i class="fas fa-users"></i> Usuarios <i class="fas fa-chevron-down"></i></a>
            <ul class="submenu">
                <li><a href="/usuarios/registrar.php"><i class="fas fa-user-plus"></i> Registrar Usuario</a></li>
                <li><a href="/usuarios/listar.php"><i class="fas fa-list"></i> Lista de Usuarios</a></li>
            </ul>
        </li>
        <?php endif; ?>
        
        <!-- Almacenes - Ajustado según permisos -->
        <li class="submenu-container">
            <a href="#"><i class="fas fa-warehouse"></i> Almacenes <i class="fas fa-chevron-down"></i></a>
            <ul class="submenu">
                <?php if ($usuario_rol == 'admin'): ?>
                <li><a href="/almacenes/registrar.php"><i class="fas fa-plus"></i> Registrar Almacén</a></li>
                <?php endif; ?>
                <li><a href="/almacenes/listar.php"><i class="fas fa-list"></i> Lista de Almacenes</a></li>
            </ul>
        </li>
        
        <!-- Notificaciones -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Notificaciones">
                <i class="fas fa-bell"></i> Notificaciones <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <li><a href="/notificaciones/pendientes.php"><i class="fas fa-clock"></i> Solicitudes Pendientes 
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
                <li><a href="/notificaciones/historial.php"><i class="fas fa-list"></i> Historial de Solicitudes</a></li>
            </ul>
        </li>
        
        <li><a href="/logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a></li>
    </ul>
</nav>

<!-- Contenido Principal -->

<!-- Mensajes de éxito o error -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert success">
        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; ?>
        <?php unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert error">
        <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; ?>
        <?php unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<div class="main-content">
    <h2><?php echo ($usuario_rol == 'admin') ? 'Almacenes Registrados' : 'Mi Almacén Asignado'; ?></h2>

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
            <?php if ($usuario_rol != 'admin' && !$usuario_almacen_id): ?>
                <p>No tienes un almacén asignado. Por favor, contacta con un administrador.</p>
            <?php else: ?>
                <p>No hay almacenes registrados.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script src="../assets/js/script.js"></script>
</body>
</html>