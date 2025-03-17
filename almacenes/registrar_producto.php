<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

require_once "../config/database.php";

// Verificar si se proporcionó almacén
if (!isset($_GET['almacen_id']) || !filter_var($_GET['almacen_id'], FILTER_VALIDATE_INT)) {
    die("Datos no válidos.");
}

$almacen_id = $_GET['almacen_id'];

$mensaje = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!empty($_POST["nombre"]) && !empty($_POST["cantidad"]) && !empty($_POST["unidad_medida"]) && !empty($_POST["estado"])) {
        $nombre = trim($_POST["nombre"]);
        $modelo = trim($_POST["modelo"] ?? '');
        $color = trim($_POST["color"] ?? '');
        $talla_dimensiones = trim($_POST["talla_dimensiones"] ?? '');
        $cantidad = intval($_POST["cantidad"]);
        $unidad_medida = trim($_POST["unidad_medida"]);
        $estado = trim($_POST["estado"]);
        $observaciones = trim($_POST["observaciones"] ?? '');
        $categoria_id = intval($_POST["categoria"]);

        // Insertar el producto en la base de datos
        $sql = "INSERT INTO productos (nombre, modelo, color, talla_dimensiones, cantidad, unidad_medida, estado, observaciones, almacen_id, categoria_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("ssssissssi", $nombre, $modelo, $color, $talla_dimensiones, $cantidad, $unidad_medida, $estado, $observaciones, $almacen_id, $categoria_id);
            if ($stmt->execute()) {
                $mensaje = "✅ Producto registrado con éxito.";
            } else {
                $error = "❌ Error al registrar el producto: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "❌ Error en la consulta SQL: " . $conn->error;
        }
    } else {
        $error = "⚠️ Todos los campos obligatorios deben llenarse.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Producto - COMSEPROA</title>
    <link rel="stylesheet" href="../assets/css/styles-dashboard.css">
    <link rel="stylesheet" href="../assets/css/styles-registrar-producto.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js"></script>
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

        <!-- Cerrar Sesión -->
        <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a></li>
    </ul>
</nav>
<!-- Contenido Principal -->
<div class="main-content">
    <h1>Registrar Nuevo Producto</h1>

    <?php if (!empty($mensaje)): ?>
        <p class="message"><?php echo $mensaje; ?></p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p class="error"><?php echo $error; ?></p>
    <?php endif; ?>

    <div class="form-container">
        <form action="" method="POST">
            <label for="categoria">Seleccionar Categoría:</label>
            <select id="categoria" name="categoria" required onchange="mostrarCampos()">
                <option value="">Seleccione una categoría</option>
                <option value="1">Ropa</option>
                <option value="2">Accesorios de seguridad</option>
                <option value="3">Kebras y fundas nuevas</option>
            </select>

            <label for="nombre">Denominación:</label>
            <input type="text" id="nombre" name="nombre" required>

            <div id="campo-modelo">
                <label for="modelo">Modelo:</label>
                <input type="text" id="modelo" name="modelo">
            </div>

            <div id="campo-color">
                <label for="color">Color:</label>
                <input type="text" id="color" name="color">
            </div>

            <div id="campo-talla">
                <label for="talla_dimensiones">Talla / Dimensiones:</label>
                <input type="text" id="talla_dimensiones" name="talla_dimensiones">
            </div>

            <label for="cantidad">Cantidad:</label>
            <input type="number" id="cantidad" name="cantidad" required>

            <label for="unidad_medida">Unidad de Medida:</label>
            <input type="text" id="unidad_medida" name="unidad_medida" required>

            <label for="estado">Estado:</label>
            <select id="estado" name="estado" required>
                <option value="Nuevo">Nuevo</option>
                <option value="Usado">Usado</option>
                <option value="Dañado">Dañado</option>
            </select>

            <label for="observaciones">Observaciones:</label>
            <textarea id="observaciones" name="observaciones"></textarea>

            <button type="submit"><i class="fas fa-save"></i> Registrar Producto</button>
        </form>
    </div>
</div>

<script>
function mostrarCampos() {
    var categoria = document.getElementById("categoria").value;
    
    // Ocultar todos los campos adicionales por defecto
    document.getElementById("campo-modelo").style.display = "none";
    document.getElementById("campo-color").style.display = "none";
    document.getElementById("campo-talla").style.display = "none";

    if (categoria == "1" || categoria == "2") { 
        // Ropa y Accesorios de seguridad: Muestran Modelo, Color y Talla/Dimensiones
        document.getElementById("campo-modelo").style.display = "block";
        document.getElementById("campo-color").style.display = "block";
        document.getElementById("campo-talla").style.display = "block";
    } else if (categoria == "3") {
        // Kebras y fundas nuevas: Solo muestran Color y Talla/Dimensiones
        document.getElementById("campo-color").style.display = "block";
        document.getElementById("campo-talla").style.display = "block";
    }
}
</script>
<script src="../assets/js/script.js"></script>
</body>
</html>
