<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

require_once "../config/database.php";

// Verificar los parámetros de almacén y categoría
if (!isset($_GET['almacen_id'], $_GET['categoria_id']) || 
    !filter_var($_GET['almacen_id'], FILTER_VALIDATE_INT) || 
    !filter_var($_GET['categoria_id'], FILTER_VALIDATE_INT)) {
    die("Datos no válidos");
}

$almacen_id = $_GET['almacen_id'];
$categoria_id = $_GET['categoria_id'];

// Obtener el nombre de la categoría
$sql_categoria = "SELECT nombre FROM categorias WHERE id = ?";
$stmt_categoria = $conn->prepare($sql_categoria);
$stmt_categoria->bind_param("i", $categoria_id);
$stmt_categoria->execute();
$result_categoria = $stmt_categoria->get_result();
$categoria = $result_categoria->fetch_assoc();
$stmt_categoria->close();

// Obtener el nombre del almacén
$sql_almacen = "SELECT nombre FROM almacenes WHERE id = ?";
$stmt_almacen = $conn->prepare($sql_almacen);
$stmt_almacen->bind_param("i", $almacen_id);
$stmt_almacen->execute();
$result_almacen = $stmt_almacen->get_result();
$almacen = $result_almacen->fetch_assoc();
$stmt_almacen->close();

if (!$categoria || !$almacen) {
    die("Categoría o almacén no encontrados");
}

// Definir qué columnas se muestran por categoría
$campos_por_categoria = [
    1 => ["nombre", "modelo", "color", "talla_dimensiones", "cantidad", "unidad_medida", "estado", "observaciones"],  // Ropa
    2 => ["nombre", "modelo", "color", "cantidad", "unidad_medida", "estado", "observaciones"],  // Accesorios de seguridad
    3 => ["nombre", "color", "talla_dimensiones", "cantidad", "unidad_medida", "estado", "observaciones"],  // Kebras y fundas nuevas
];

// Obtener los campos relevantes según la categoría
$campos_seleccionados = $campos_por_categoria[$categoria_id] ?? ["nombre", "cantidad", "estado"];  // Valores por defecto

// Paginación
$productos_por_pagina = 8; // Número de productos por página
$pagina_actual = isset($_GET['pagina']) && filter_var($_GET['pagina'], FILTER_VALIDATE_INT) ? $_GET['pagina'] : 1;
$inicio = ($pagina_actual - 1) * $productos_por_pagina;

// Contar el total de productos
$sql_total = "SELECT COUNT(*) AS total FROM productos WHERE categoria_id = ? AND almacen_id = ?";
$stmt_total = $conn->prepare($sql_total);
$stmt_total->bind_param("ii", $categoria_id, $almacen_id);
$stmt_total->execute();
$result_total = $stmt_total->get_result();
$total_productos = $result_total->fetch_assoc()['total'];
$stmt_total->close();

$total_paginas = ceil($total_productos / $productos_por_pagina);

// Obtener los productos con paginación
$columnas_sql = implode(", ", $campos_seleccionados);
$sql_productos = "SELECT $columnas_sql FROM productos WHERE categoria_id = ? AND almacen_id = ? LIMIT ?, ?";
$stmt_productos = $conn->prepare($sql_productos);
$stmt_productos->bind_param("iiii", $categoria_id, $almacen_id, $inicio, $productos_por_pagina);
$stmt_productos->execute();
$productos = $stmt_productos->get_result();
$stmt_productos->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Productos - COMSEPROA</title>
    <link rel="stylesheet" href="../assets/css/styles-listar-productos.css">
    <link rel="stylesheet" href="../assets/css/styles-dashboard.css">
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
                <li><a href="../almacenes/registrar.php"><i class="fas fa-plus"></i> Registrar Almacén</a></li>
                <li><a href="../almacenes/listar.php"><i class="fas fa-list"></i> Lista de Almacenes</a></li>
            </ul>
        </li>
        <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a></li>
    </ul>
</nav>

<div class="titulo-productos">
    <h1>Productos en <?php echo htmlspecialchars($almacen['nombre']); ?> - 
        <span><?php echo htmlspecialchars($categoria['nombre']); ?></span>
    </h1>
</div>
<?php if ($productos->num_rows > 0): ?>
    <table>
    <thead>
        <tr>
            <?php foreach ($campos_seleccionados as $campo): ?>
                <th><?php echo ucfirst(str_replace("_", " ", $campo)); ?></th>
            <?php endforeach; ?>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($producto = $productos->fetch_assoc()): ?>
            <tr>
                <?php foreach ($campos_seleccionados as $campo): ?>
                    <td>
                        <?php if ($campo == 'cantidad'): ?>
                            <button class="btn stock aumentar"><i class="fas fa-plus"></i></button>
                            <span><?php echo htmlspecialchars($producto[$campo]); ?></span>
                            <button class="btn stock disminuir"><i class="fas fa-minus"></i></button>
                        <?php else: ?>
                            <?php echo htmlspecialchars($producto[$campo]); ?>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>

                <!-- Botones de acciones -->
                <td>
                    <button class="btn enviar"><i class="fas fa-paper-plane"></i> Enviar</button>
                    <button class="btn entregar"><i class="fas fa-truck"></i> Entregar</button>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

    <!-- Paginación -->
    <div class="paginacion">
        <?php if ($pagina_actual > 1): ?>
            <a href="?almacen_id=<?php echo $almacen_id; ?>&categoria_id=<?php echo $categoria_id; ?>&pagina=<?php echo $pagina_actual - 1; ?>">&laquo; Anterior</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
            <a href="?almacen_id=<?php echo $almacen_id; ?>&categoria_id=<?php echo $categoria_id; ?>&pagina=<?php echo $i; ?>" 
               class="<?php echo $i == $pagina_actual ? 'activo' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <?php if ($pagina_actual < $total_paginas): ?>
            <a href="?almacen_id=<?php echo $almacen_id; ?>&categoria_id=<?php echo $categoria_id; ?>&pagina=<?php echo $pagina_actual + 1; ?>">Siguiente &raquo;</a>
        <?php endif; ?>
    </div>

<?php else: ?>
    <p>No hay productos registrados en esta categoría.</p>
<?php endif; ?>

<script src="../assets/js/script.js"></script>
</body>
</html>
