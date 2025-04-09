<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

require_once "../config/database.php";

// Obtener el rol del usuario - igual que en dashboard.php
$usuario_rol = isset($_SESSION["user_role"]) ? $_SESSION["user_role"] : "usuario";
$usuario_almacen_id = isset($_SESSION["almacen_id"]) ? $_SESSION["almacen_id"] : null;

// Verificar los parámetros de almacén y categoría
if (!isset($_GET['almacen_id'], $_GET['categoria_id']) || 
    !filter_var($_GET['almacen_id'], FILTER_VALIDATE_INT) || 
    !filter_var($_GET['categoria_id'], FILTER_VALIDATE_INT)) {
    die("Datos no válidos");
}

$almacen_id = $_GET['almacen_id'];
$categoria_id = $_GET['categoria_id'];
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

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
    1 => ["nombre", "modelo", "color", "talla_dimensiones", "cantidad", "unidad_medida", "estado", "observaciones"],
    2 => ["nombre", "modelo", "color", "cantidad", "unidad_medida", "estado", "observaciones"],
    3 => ["nombre", "color", "talla_dimensiones", "cantidad", "unidad_medida", "estado", "observaciones"],
    4 => ["nombre", "modelo", "color", "cantidad", "unidad_medida", "estado", "observaciones"],
    6 => ["nombre", "modelo", "color", "cantidad", "unidad_medida", "estado", "observaciones"],
];

$campos_seleccionados = $campos_por_categoria[$categoria_id] ?? ["nombre", "cantidad", "estado"];

// Paginación
$productos_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) && filter_var($_GET['pagina'], FILTER_VALIDATE_INT) ? $_GET['pagina'] : 1;
$inicio = ($pagina_actual - 1) * $productos_por_pagina;

// Validar y obtener el campo de filtro
$campo_filtro = isset($_GET['campo_filtro']) && in_array($_GET['campo_filtro'], $campos_seleccionados) ? $_GET['campo_filtro'] : null;

// Construcción de la consulta base - Asegúrate de incluir el ID
$sql_productos = "SELECT id, " . implode(", ", $campos_seleccionados) . " FROM productos WHERE categoria_id = ? AND almacen_id = ?";
$params = [$categoria_id, $almacen_id];
$types = "ii";

// Aplicar búsqueda si hay un término y un campo de filtro válido
if (!empty($busqueda) && $campo_filtro) {
    $sql_productos .= " AND $campo_filtro LIKE ?";
    $busqueda_param = "$busqueda%"; // Solo los que comiencen con la búsqueda
    $params[] = $busqueda_param;
    $types .= "s";
}

// Contar el total de productos que coinciden con la búsqueda y almacén
$sql_total = "SELECT COUNT(*) AS total FROM productos WHERE categoria_id = ? AND almacen_id = ?";
if (!empty($busqueda) && $campo_filtro) {
    $sql_total .= " AND $campo_filtro LIKE ?";
}

$stmt_total = $conn->prepare($sql_total);
if (!empty($busqueda) && $campo_filtro) {
    $busqueda_param = "$busqueda%";
    $stmt_total->bind_param("iis", $categoria_id, $almacen_id, $busqueda_param);
} else {
    $stmt_total->bind_param("ii", $categoria_id, $almacen_id);
}

$stmt_total->execute();
$result_total = $stmt_total->get_result();
$total_productos = $result_total->fetch_assoc()['total'];
$stmt_total->close();

// Calcular total de páginas
$total_paginas = ($total_productos > 0) ? ceil($total_productos / $productos_por_pagina) : 1;

// Si hay menos productos que la cantidad por página, ocultar la paginación
$mostrar_paginacion = $total_productos > $productos_por_pagina;

// Agregar paginación a la consulta
$sql_productos .= " LIMIT ?, ?";
$params[] = $inicio;
$params[] = $productos_por_pagina;
$types .= "ii";

// Preparar y ejecutar la consulta
$stmt_productos = $conn->prepare($sql_productos);
if (!$stmt_productos) {
    die("Error en la preparación de la consulta: " . $conn->error);
}

$stmt_productos->bind_param($types, ...$params);
$stmt_productos->execute();
$productos = $stmt_productos->get_result();
$stmt_productos->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Lista de Productos - COMSEPROA</title>
    <link rel="stylesheet" href="../assets/css/styles-listar-productos.css">
    <link rel="stylesheet" href="../assets/css/styles-dashboard.css">
    <link rel="stylesheet" href="../assets/css/styles-cantidad.css">
    <link rel="stylesheet" href="../assets/css/styles-pendientes.css">
    <link rel="stylesheet" href="../assets/css/entregas-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body data-user-role="<?php echo htmlspecialchars($usuario_rol); ?>" data-almacen-id="<?php echo intval($almacen_id); ?>">

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
                <li><a href="../almacenes/registrar.php"><i class="fas fa-plus"></i> Registrar Almacén</a></li>
                <?php endif; ?>
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
                <li><a href="../notificaciones/historial.php"><i class="fas fa-list"></i> Historial de Solicitudes</a></li>
                <li><a href="../uniformes/historial_entregas_uniformes.php"><i class="fas fa-tshirt"></i> Historial de Entregas de Uniformes</a></li>
            </ul>
        </li>
        
        <!-- Cerrar Sesión -->
        <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a></li>
    </ul>
</nav>
<div class="contenedor-titulo-busqueda">
    <div class="titulo-productos">
        <h1>Productos en <?php echo htmlspecialchars($almacen['nombre']); ?> - 
            <span><?php echo htmlspecialchars($categoria['nombre']); ?></span>
        </h1>
    </div>
    <div class="busqueda">
        <form method="GET">
            <input type="hidden" name="almacen_id" value="<?php echo $almacen_id; ?>">
            <input type="hidden" name="categoria_id" value="<?php echo $categoria_id; ?>">
            
            <!-- Selector de campo -->
            <select name="campo_filtro">
                <?php foreach ($campos_seleccionados as $campo): ?>
                    <option value="<?php echo $campo; ?>" <?php echo (isset($_GET['campo_filtro']) && $_GET['campo_filtro'] == $campo) ? 'selected' : ''; ?>>
                        <?php echo ucfirst(str_replace("_", " ", $campo)); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Campo de búsqueda -->
            <input type="text" name="busqueda" placeholder="Buscar producto" value="<?php echo htmlspecialchars($busqueda); ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
            
            <!-- Botón de entregar uniforme solo para almaceneros -->
            <?php if ($usuario_rol == 'almacenero'): ?>
                <button type="button" class="btn entregar-uniforme"><i class="fas fa-truck"></i> Entregar Uniforme</button>
            <?php endif; ?>
            
            <!-- Solicitar button -->
            <button type="button" class="btn solicitar-global"><i class="fas fa-hand-paper"></i> Solicitar</button>
        </form>
    </div>
</div>

<div class="main-content">
    <!-- Mostrar notificaciones de éxito o error si existen -->
    <div id="notificaciones-container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="notificacion exito">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <span class="cerrar">&times;</span>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="notificacion error">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <span class="cerrar">&times;</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Zona de productos seleccionados -->
    <div id="zona-seleccionados" class="zona-seleccionados" style="display: none;">
        <div class="header-seleccionados">
            <h3>Productos Seleccionados</h3>
            <span id="contador-seleccionados">0</span>
            <button id="btn-limpiar-seleccion" class="btn btn-secundario">
                <i class="fas fa-times"></i> Limpiar
            </button>
        </div>
        <div id="lista-seleccionados" class="lista-seleccionados">
            <!-- Los productos seleccionados se mostrarán aquí dinámicamente -->
        </div>
        <div class="acciones-seleccionados">
            <button id="btn-continuar-entrega" class="btn btn-principal">
                <i class="fas fa-truck"></i> Continuar Entrega
            </button>
        </div>
    </div>

    <div class="table-container">
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
                                <?php if ($campo === 'cantidad'): ?>
                                    <td>
                                        <div class="control-cantidad">
                                            <?php if ($usuario_rol == 'admin'): ?>
                                            <button class="btn stock" data-id="<?php echo intval($producto['id']); ?>" data-accion="restar">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <?php endif; ?>
                                            <span id="cantidad-<?php echo intval($producto['id']); ?>"><?php echo intval($producto['cantidad']); ?></span>
                                            <?php if ($usuario_rol == 'admin'): ?>
                                            <button class="btn stock" data-id="<?php echo intval($producto['id']); ?>" data-accion="sumar">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php else: ?>
                                    <td><?php echo htmlspecialchars($producto[$campo]); ?></td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <td>
                                <?php if (intval($producto['cantidad']) > 0): ?>
                                    <button class="btn enviar" 
                                        data-id="<?php echo intval($producto['id']); ?>"
                                        data-nombre="<?php echo htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-almacen="<?php echo intval($almacen_id); ?>"
                                        data-cantidad="<?php echo intval($producto['cantidad']); ?>">
                                        <i class="fas fa-paper-plane"></i> Enviar
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay productos registrados en esta categoría.</p>
        <?php endif; ?>
    </div>

    <!-- Paginación -->
    <?php if ($mostrar_paginacion): ?>
    <nav>
        <ul class="pagination">
            <?php if ($pagina_actual > 1): ?>
                <li>
                    <a href="?pagina=<?= $pagina_actual - 1 ?>&busqueda=<?= urlencode($busqueda) ?>&almacen_id=<?= $almacen_id ?>&categoria_id=<?= $categoria_id ?>&campo_filtro=<?= urlencode($campo_filtro) ?>" 
                       class="mantener-seleccion">
                        Anterior
                    </a>
                </li>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                <li class="<?= ($i == $pagina_actual) ? 'active' : '' ?>">
                    <a href="?pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>&almacen_id=<?= $almacen_id ?>&categoria_id=<?= $categoria_id ?>&campo_filtro=<?= urlencode($campo_filtro) ?>" 
                       class="mantener-seleccion">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>

            <?php if ($pagina_actual < $total_paginas): ?>
                <li>
                    <a href="?pagina=<?= $pagina_actual + 1 ?>&busqueda=<?= urlencode($busqueda) ?>&almacen_id=<?= $almacen_id ?>&categoria_id=<?= $categoria_id ?>&campo_filtro=<?= urlencode($campo_filtro) ?>" 
                       class="mantener-seleccion">
                        Siguiente
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<!-- Modal de Formulario de Envío -->
<div id="modalFormulario" class="modal">
    <div class="modal-contenido">
        <span class="cerrar">&times;</span>
        <h2>Enviar Producto</h2>
        <form id="formEnviar" method="POST">
            <input type="hidden" id="producto_id" name="producto_id">
            <input type="hidden" id="almacen_origen" name="almacen_origen">
            
            <p id="producto_nombre">Producto: </p>
            <p>Stock Disponible: <span id="stock_disponible"></span></p>
            
            <div class="form-group">
                <label for="cantidad">Cantidad:</label>
                <input type="number" id="cantidad" name="cantidad" min="1" value="1">
            </div>
            
            <div class="form-group">
                <label for="almacen_destino">Almacén de Destino:</label>
                <select id="almacen_destino" name="almacen_destino" required>
                    <option value="">Seleccione un almacén</option>
                    <?php
                    // Obtener lista de almacenes, excluyendo el almacén actual
                    $sql_almacenes = "SELECT id, nombre FROM almacenes WHERE id != ?";
                    $stmt_almacenes = $conn->prepare($sql_almacenes);
                    $stmt_almacenes->bind_param("i", $almacen_id);
                    $stmt_almacenes->execute();
                    $result_almacenes = $stmt_almacenes->get_result();
                    
                    while ($almacen_destino = $result_almacenes->fetch_assoc()) {
                        echo "<option value='{$almacen_destino['id']}'>{$almacen_destino['nombre']}</option>";
                    }
                    $stmt_almacenes->close();
                    ?>
                </select>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secundario cerrar">Cancelar</button>
                <button type="submit" class="btn enviar">Enviar Producto</button>
            </div>
        </form>
    </div>
</div>

<!-- Agregar después del modal de envío de producto, un nuevo modal para entrega de uniformes -->
<div id="modalEntregaUniforme" class="modal">
    <div class="modal-contenido">
        <span class="cerrar">&times;</span>
        <h2>Confirmar Entrega de Uniformes</h2>
        <form id="formEntregaUniforme" method="POST">
            <input type="hidden" name="almacen_id" id="almacen_id" value="3">
            
            <div class="form-grupo">
                <label for="nombre_destinatario">Nombre Completo del Destinatario</label>
                <input 
                    type="text" 
                    id="nombre_destinatario" 
                    name="nombre_destinatario" 
                    placeholder="Nombre completo" 
                    required
                >
            </div>
            
            <div class="form-grupo">
                <label for="dni_destinatario">DNI del Destinatario</label>
                <input 
                    type="text" 
                    id="dni_destinatario" 
                    name="dni_destinatario" 
                    placeholder="8 dígitos" 
                    pattern="\d{8}" 
                    maxlength="8" 
                    required
                >
            </div>
            
            <div id="lista-uniformes-entrega">
                <!-- Productos seleccionados se insertarán dinámicamente -->
            </div>
            
            <button type="submit" class="btn btn-primario">Confirmar Entrega</button>
        </form>
    </div>
</div>
<script src="../assets/js/script.js"></script>
<script src="../assets/js/entregas.js"></script>
</body>
</html>