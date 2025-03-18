<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

require_once "../config/database.php"; // Conectar a la base de datos

// Verificar si se proporcionó almacén y categoría en la URL
if (!isset($_GET['almacen_id']) || !filter_var($_GET['almacen_id'], FILTER_VALIDATE_INT) || 
    !isset($_GET['categoria_id']) || !filter_var($_GET['categoria_id'], FILTER_VALIDATE_INT)) {
    die("⚠️ Datos no válidos.");
}

$almacen_id = $_GET['almacen_id'];
$categoria_id = $_GET['categoria_id'];

// Definir el nombre de la categoría
$categorias = [
    1 => "Ropa",
    2 => "Accesorios de seguridad",
    3 => "Kebras y fundas nuevas"
];

$nombre_categoria = $categorias[$categoria_id] ?? "Desconocida";

$mensaje = "";
$error = "";

// Manejo del formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST["nombre"] ?? '');
    $modelo = trim($_POST["modelo"] ?? '');
    $color = trim($_POST["color"] ?? '');
    $talla_dimensiones = trim($_POST["talla_dimensiones"] ?? '');
    $cantidad = isset($_POST["cantidad"]) ? intval($_POST["cantidad"]) : 0;
    $unidad_medida = trim($_POST["unidad_medida"] ?? '');
    $estado = trim($_POST["estado"] ?? '');
    $observaciones = trim($_POST["observaciones"] ?? '');

    if (!empty($nombre) && $cantidad > 0 && !empty($unidad_medida) && !empty($estado)) {
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
        $error = "⚠️ Todos los campos obligatorios deben llenarse correctamente.";
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
    <link rel="stylesheet" href="../assets/css/styles-productos.css">
    <link rel="stylesheet" href="../assets/css/styles-registrar-producto.css">
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
                <li><a href="../almacenes/registrar.php"><i class="fas fa-plus"></i> Registrar Almacén</a></li>
                <li><a href="../almacenes/listar.php"><i class="fas fa-list"></i> Lista de Almacenes</a></li>
            </ul>
        </li>

        <!-- Cerrar Sesión -->
        <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a></li>
    </ul>
</nav>

<div class="main-content">
    <h1>Registrar Nuevo Producto</h1>
    <?php if (!empty($mensaje)): ?>
        <p class="message"> <?php echo $mensaje; ?> </p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p class="error"> <?php echo $error; ?> </p>
    <?php endif; ?>

    <div class="form-container">
    <form action="" method="POST">
        <label>Categoría seleccionada:</label>
        <input type="text" value="<?php echo htmlspecialchars($nombre_categoria); ?>" readonly>
        <input type="hidden" name="categoria" value="<?php echo $categoria_id; ?>">
        
        <div class="form-grid">
            <div class="form-group">
                <label for="nombre">Denominación:</label>
                <input type="text" id="nombre" name="nombre" required>
            </div>
            
            <?php if ($categoria_id == 1 || $categoria_id == 2): ?>
            <div class="form-group">
                <label for="modelo">Modelo:</label>
                <input type="text" id="modelo" name="modelo">
            </div>
            <?php endif; ?>
        
            <div class="form-group">
                <label for="color">Color:</label>
                <input type="text" id="color" name="color">
            </div>
        
            <div class="form-group">
                <label for="talla_dimensiones">Talla / Dimensiones:</label>
                <input type="text" id="talla_dimensiones" name="talla_dimensiones">
            </div>
        
            <div class="form-group">
                <label for="cantidad">Cantidad:</label>
                <input type="number" id="cantidad" name="cantidad" min="1" required>
            </div>
        
            <div class="form-group">
                <label for="unidad_medida">Unidad de Medida:</label>
                <input type="text" id="unidad_medida" name="unidad_medida" required>
            </div>
        
            <div class="form-group">
                <label for="estado">Estado:</label>
                <select id="estado" name="estado" required>
                    <option value="Nuevo">Nuevo</option>
                    <option value="Usado">Usado</option>
                    <option value="Dañado">Dañado</option>
                </select>
            </div>
        
            <div class="form-group">
                <label for="observaciones">Observaciones:</label>
                <textarea id="observaciones" name="observaciones"></textarea>
            </div>
        </div>
        
        <button type="submit" class="btn-registrar">Registrar Producto</button>
    </form>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    var categoria = "<?php echo $categoria_id; ?>";

    if (categoria === "1" || categoria === "2") {
        document.getElementById("campo-modelo").style.display = "block";
        document.getElementById("campo-color").style.display = "block";
        document.getElementById("campo-talla").style.display = "block";
    } else if (categoria === "3") {
        document.getElementById("campo-color").style.display = "block";
        document.getElementById("campo-talla").style.display = "block";
    }
});
</script>
<script src="../assets/js/script.js"></script>
</body>
</html>
