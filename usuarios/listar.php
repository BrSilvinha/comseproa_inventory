<?php
session_start();
require_once "../config/database.php"; // Incluir archivo de conexión

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_name = $_SESSION["user_name"] ?? "Usuario";
$usuario_rol = $_SESSION["user_role"] ?? "usuario";
$usuario_almacen_id = $_SESSION["almacen_id"] ?? null;

session_regenerate_id(true);

// Restricción de acceso basada en roles
if ($usuario_rol !== 'admin') {
    // Si no es admin, redirigir al dashboard
    header("Location: ../dashboard.php");
    exit();
}

// Manejo de AJAX para eliminar usuario o cambiar estado
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"], $_POST["id"])) {
    header("Content-Type: application/json");
    $response = ["success" => false];

    $id = (int) $_POST["id"]; // Convertir ID a entero para mayor seguridad

    if ($_POST["action"] === "delete") {
        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $id);
        $response["success"] = $stmt->execute();
        $stmt->close();
    } elseif ($_POST["action"] === "toggle_status") {
        $stmt = $conn->prepare("UPDATE usuarios SET estado = IF(estado = 'Activo', 'Inactivo', 'Activo') WHERE id = ?");
        $stmt->bind_param("i", $id);
        $response["success"] = $stmt->execute();
        $stmt->close();
    }

    echo json_encode($response);
    exit();
}

// Paginación
$usuarios_por_pagina = 5;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $usuarios_por_pagina;

// Obtener total de usuarios (para calcular la paginación)
$query_total = "SELECT COUNT(*) AS total FROM usuarios WHERE estado != 'Eliminado'";
$result_total = $conn->query($query_total);
$total_usuarios = $result_total->fetch_assoc()["total"];
$total_paginas = ceil($total_usuarios / $usuarios_por_pagina);

// Obtener los almacenes para la lista de selección
$almacenes_query = "SELECT id, nombre FROM almacenes";
$almacenes_result = $conn->query($almacenes_query);
$almacenes = $almacenes_result->fetch_all(MYSQLI_ASSOC);

// Obtener lista de usuarios con filtros
$where = " WHERE u.estado != 'Eliminado' ";
$params = [];
$types = "";

// Filtros dinámicos
if (!empty($_GET["nombre"])) {
    $where .= " AND u.nombre LIKE ?";
    $params[] = "%" . $_GET["nombre"] . "%";
    $types .= "s";
}
if (!empty($_GET["dni"])) {
    $where .= " AND u.dni = ?";
    $params[] = $_GET["dni"];
    $types .= "s";
}
if (!empty($_GET["estado"])) {
    $where .= " AND u.estado = ?";
    $params[] = $_GET["estado"];
    $types .= "s";
}
if (!empty($_GET["almacen"])) {
    $where .= " AND a.nombre = ?";
    $params[] = $_GET["almacen"];
    $types .= "s";
}

$query = "SELECT u.id, u.nombre, u.apellidos, u.dni, u.correo, u.rol, u.estado, a.nombre AS almacen 
          FROM usuarios u 
          LEFT JOIN almacenes a ON u.almacen_id = a.id
          $where";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$usuarios = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Contar solicitudes pendientes para mostrar en el badge
$sql_pendientes = "SELECT COUNT(*) as total FROM solicitudes_transferencia WHERE estado = 'pendiente'";
$result_pendientes = $conn->query($sql_pendientes);
$pendientes_count = $result_pendientes ? $result_pendientes->fetch_assoc()['total'] : 0;

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Usuarios</title>
    <link rel="stylesheet" href="../assets/css/styles-dashboard.css">
    <link rel="stylesheet" href="../assets/css/styles-form.css">
    <link rel="stylesheet" href="../assets/css/styles-lista-usuarios.css">
    <link rel="stylesheet" href="../assets/css/styles-pendientes.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <!-- Botón de hamburguesa para dispositivos móviles -->
    <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <div class="dashboard-container">
        <!-- Menú Lateral -->
        <nav class="sidebar" id="sidebar">
            <h2>GRUPO SEAL</h2>
            <ul>
                <li><a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a></li>

                <!-- Usuarios - Solo visible para administradores -->
                <li class="submenu-container">
                    <a href="#" aria-label="Menú Usuarios">
                        <i class="fas fa-users"></i> Usuarios <i class="fas fa-chevron-down"></i>
                    </a>
                    <ul class="submenu">
                        <li><a href="registrar.php"><i class="fas fa-user-plus"></i> Registrar Usuario</a></li>
                        <li><a href="listar.php"><i class="fas fa-list"></i> Lista de Usuarios</a></li>
                    </ul>
                </li>

                <!-- Almacenes -->
                <li class="submenu-container">
                    <a href="#" aria-label="Menú Almacenes">
                        <i class="fas fa-warehouse"></i> Almacenes <i class="fas fa-chevron-down"></i>
                    </a>
                    <ul class="submenu">
                        <li><a href="../almacenes/registrar.php"><i class="fas fa-plus"></i> Registrar Almacén</a></li>
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
                            <span class="badge"><?= $pendientes_count ?></span>
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
            <h1>Lista de Usuarios</h1>

            <!-- Formulario de Búsqueda -->
            <form method="GET" action="listar.php" class="filter-form">
                <input type="text" name="nombre" placeholder="Buscar por nombre" value="<?= htmlspecialchars($_GET['nombre'] ?? '') ?>">
                <input type="text" name="dni" placeholder="Buscar por DNI" value="<?= htmlspecialchars($_GET['dni'] ?? '') ?>">
                <select name="estado">
                    <option value="">Todos</option>
                    <option value="Activo" <?= (isset($_GET["estado"]) && $_GET["estado"] == "Activo") ? "selected" : "" ?>>Activo</option>
                    <option value="Inactivo" <?= (isset($_GET["estado"]) && $_GET["estado"] == "Inactivo") ? "selected" : "" ?>>Inactivo</option>
                </select>
                <select name="almacen">
                    <option value="">Todos los almacenes</option>
                    <?php foreach ($almacenes as $almacen): ?>
                        <option value="<?= htmlspecialchars($almacen["nombre"]) ?>" <?= (isset($_GET["almacen"]) && $_GET["almacen"] == $almacen["nombre"]) ? "selected" : "" ?>>
                            <?= htmlspecialchars($almacen["nombre"]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Buscar</button>
                <a href="listar.php" class="reset-btn">Restablecer</a>
            </form>

            <!-- Tabla de Usuarios -->
            <table class="usuarios-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Apellidos</th>
                        <th>DNI</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Almacén</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr id="user-<?= $usuario['id'] ?>">
                            <td><?= $usuario['id'] ?></td>
                            <td><?= htmlspecialchars($usuario['nombre']) ?></td>
                            <td><?= htmlspecialchars($usuario['apellidos']) ?></td>
                            <td><?= htmlspecialchars($usuario['dni']) ?></td>
                            <td><?= htmlspecialchars($usuario['correo']) ?></td>
                            <td><?= htmlspecialchars($usuario['rol']) ?></td>
                            <td><?= htmlspecialchars($usuario['estado']) ?></td>
                            <td><?= htmlspecialchars($usuario['almacen'] ?? 'N/A') ?></td>
                            <td>
                                <button onclick="editUser(<?= $usuario['id'] ?>)">Editar</button>
                                <button onclick="deleteUser(<?= $usuario['id'] ?>)">Eliminar</button>
                                <button onclick="toggleStatus(<?= $usuario['id'] ?>)">
                                    <?= $usuario['estado'] === 'Activo' ? 'Inhabilitar' : 'Habilitar' ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Paginación -->
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <a href="?pagina=<?= $i ?>" class="<?= $i == $pagina_actual ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </main>
    </div>

    <script>
        function editUser(userId) {
            window.location.href = "editar_usuario.php?id=" + userId;
        }

        function deleteUser(userId) {
            if (confirm("¿Seguro que deseas eliminar este usuario?")) {
                $.post("listar.php", { action: "delete", id: userId }, function (response) {
                    if (response.success) {
                        $("#user-" + userId).remove();
                    } else {
                        alert("Error al eliminar usuario");
                    }
                }, "json");
            }
        }

        function toggleStatus(userId) {
            $.post("listar.php", { action: "toggle_status", id: userId }, function (response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert("Error al actualizar estado");
                }
            }, "json");
        }
    </script>
    <script src="../assets/js/script.js"></script>
</body>
</html>