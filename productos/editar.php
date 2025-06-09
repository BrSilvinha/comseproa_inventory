<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

// Evitar secuestro de sesión
session_regenerate_id(true);

require_once "../config/database.php";

$user_name = isset($_SESSION["user_name"]) ? $_SESSION["user_name"] : "Usuario";
$usuario_rol = isset($_SESSION["user_role"]) ? $_SESSION["user_role"] : "usuario";
$usuario_almacen_id = isset($_SESSION["almacen_id"]) ? $_SESSION["almacen_id"] : null;

// Verificar que el usuario sea administrador
if ($usuario_rol !== 'admin') {
    $_SESSION['error'] = "No tiene permisos para editar productos.";
    header("Location: listar.php");
    exit();
}

// ⭐ MANTENER LA LÓGICA ORIGINAL - Validar el ID del producto
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error'] = "ID de producto no válido.";
    header("Location: listar.php");
    exit();
}

$producto_id = $_GET['id'];

// ⭐ MANTENER LA LÓGICA ORIGINAL - OBTENER Y PROCESAR PARÁMETROS DE CONTEXTO
$context_params = isset($_GET['from']) ? $_GET['from'] : '';
parse_str($context_params, $context_array);

// Función para construir URL de retorno inteligente
function buildReturnUrl($context_array, $current_product) {
    $base_url = 'listar.php';
    $params = [];
    
    // Prioridad: categoría > almacén > default
    if (isset($context_array['categoria_id']) && !empty($context_array['categoria_id'])) {
        $params['categoria_id'] = $context_array['categoria_id'];
    }
    
    if (isset($context_array['almacen_id']) && !empty($context_array['almacen_id'])) {
        $params['almacen_id'] = $context_array['almacen_id'];
    }
    
    if (isset($context_array['busqueda']) && !empty($context_array['busqueda'])) {
        $params['busqueda'] = $context_array['busqueda'];
    }
    
    if (isset($context_array['pagina']) && !empty($context_array['pagina'])) {
        $params['pagina'] = $context_array['pagina'];
    }
    
    // Si no hay contexto específico, usar el almacén del producto
    if (empty($params)) {
        $params['almacen_id'] = $current_product['almacen_id'];
    }
    
    return $base_url . (!empty($params) ? '?' . http_build_query($params) : '');
}

// Función para obtener texto descriptivo del contexto
function getContextDescription($context_array, $producto) {
    if (isset($context_array['categoria_id']) && !empty($context_array['categoria_id'])) {
        return 'Categoría: ' . htmlspecialchars($producto['categoria_nombre']);
    } elseif (isset($context_array['almacen_id']) && !empty($context_array['almacen_id'])) {
        return 'Almacén: ' . htmlspecialchars($producto['almacen_nombre']);
    } elseif (isset($context_array['busqueda']) && !empty($context_array['busqueda'])) {
        return 'Búsqueda: ' . htmlspecialchars($context_array['busqueda']);
    }
    return 'Lista de Productos';
}

// Obtener información del producto
$sql = "SELECT p.*, c.nombre as categoria_nombre, a.nombre as almacen_nombre 
        FROM productos p 
        JOIN categorias c ON p.categoria_id = c.id 
        JOIN almacenes a ON p.almacen_id = a.id 
        WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $producto_id);
$stmt->execute();
$result = $stmt->get_result();
$producto = $result->fetch_assoc();
$stmt->close();

if (!$producto) {
    $_SESSION['error'] = "Producto no encontrado.";
    header("Location: listar.php");
    exit();
}

// ⭐ CONSTRUIR URLs DE NAVEGACIÓN CON CONTEXTO
$return_url = buildReturnUrl($context_array, $producto);
$return_text = getContextDescription($context_array, $producto);

// Obtener lista de categorías
$sql_categorias = "SELECT id, nombre FROM categorias ORDER BY nombre";
$categorias = $conn->query($sql_categorias);

// Obtener lista de almacenes
$sql_almacenes = "SELECT id, nombre FROM almacenes ORDER BY nombre";
$almacenes = $conn->query($sql_almacenes);

$mensaje = "";
$error = "";

// Procesar formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST["nombre"] ?? '');
    $modelo = trim($_POST["modelo"] ?? '');
    $color = trim($_POST["color"] ?? '');
    $talla_dimensiones = trim($_POST["talla_dimensiones"] ?? '');
    $cantidad = isset($_POST["cantidad"]) ? intval($_POST["cantidad"]) : 0;
    $unidad_medida = trim($_POST["unidad_medida"] ?? '');
    $estado = trim($_POST["estado"] ?? '');
    $observaciones = trim($_POST["observaciones"] ?? '');
    $categoria_id = isset($_POST["categoria_id"]) ? intval($_POST["categoria_id"]) : 0;
    $almacen_id = isset($_POST["almacen_id"]) ? intval($_POST["almacen_id"]) : 0;

    if (!empty($nombre) && $cantidad >= 0 && !empty($unidad_medida) && !empty($estado) && $categoria_id > 0 && $almacen_id > 0) {
        // Verificar si el nombre ya existe en otro producto del mismo almacén
        $sql_check = "SELECT id FROM productos WHERE nombre = ? AND almacen_id = ? AND id != ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("sii", $nombre, $almacen_id, $producto_id);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $error = "⚠️ Ya existe un producto con ese nombre en el almacén seleccionado.";
        } else {
            // Actualizar el producto
            $sql_update = "UPDATE productos SET 
                          nombre = ?, modelo = ?, color = ?, talla_dimensiones = ?, 
                          cantidad = ?, unidad_medida = ?, estado = ?, observaciones = ?, 
                          categoria_id = ?, almacen_id = ? 
                          WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);

            if ($stmt_update) {
                $stmt_update->bind_param("ssssisssiii", 
                    $nombre, $modelo, $color, $talla_dimensiones, 
                    $cantidad, $unidad_medida, $estado, $observaciones, 
                    $categoria_id, $almacen_id, $producto_id
                );
                
                if ($stmt_update->execute()) {
                    $_SESSION['success'] = "✅ Producto actualizado con éxito.";
                    
                    // ⭐ REDIRIGIR MANTENIENDO EL CONTEXTO ORIGINAL
                    $redirect_url = "ver-producto.php?id=" . $producto_id;
                    if ($context_params) {
                        $redirect_url .= '&from=' . urlencode($context_params);
                    }
                    header("Location: " . $redirect_url);
                    exit();
                } else {
                    $error = "❌ Error al actualizar el producto: " . $stmt_update->error;
                }
                $stmt_update->close();
            } else {
                $error = "❌ Error en la consulta SQL: " . $conn->error;
            }
        }
        $stmt_check->close();
    } else {
        $error = "⚠️ Todos los campos obligatorios deben estar completos.";
    }
}

// Contar solicitudes pendientes para el badge
$sql_pendientes = "SELECT COUNT(*) as total FROM solicitudes_transferencia WHERE estado = 'pendiente'";
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
    <title>Editar Producto - <?php echo htmlspecialchars($producto['nombre']); ?> - GRUPO SEAL</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Editar producto <?php echo htmlspecialchars($producto['nombre']); ?>">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Preconnect para optimizar carga de fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <!-- CSS específico corregido -->
    <link rel="stylesheet" href="../assets/css/productos/productos-editar.css">
    
    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon.ico">
    <link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
</head>
<body>

<!-- Botón de hamburguesa para dispositivos móviles -->
<button class="menu-toggle" id="menuToggle" aria-label="Abrir menú de navegación">
    <i class="fas fa-bars"></i>
</button>

<!-- ===== SIDEBAR Y NAVEGACIÓN UNIFICADO ===== -->
<nav class="sidebar" id="sidebar" role="navigation" aria-label="Menú principal">
    <h2>GRUPO SEAL</h2>
    <ul>
        <li>
            <a href="../dashboard.php" aria-label="Ir a inicio">
                <span><i class="fas fa-home"></i> Inicio</span>
            </a>
        </li>

        <!-- Users Section - Only visible to administrators -->
        <?php if ($usuario_rol == 'admin'): ?>
        <li class="submenu-container">
            <a href="#" aria-label="Menú Usuarios" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-users"></i> Usuarios</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li><a href="../usuarios/registrar.php" role="menuitem"><i class="fas fa-user-plus"></i> Registrar Usuario</a></li>
                <li><a href="../usuarios/listar.php" role="menuitem"><i class="fas fa-list"></i> Lista de Usuarios</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- Warehouses Section - Adjusted according to permissions -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Almacenes" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-warehouse"></i> Almacenes</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <?php if ($usuario_rol == 'admin'): ?>
                <li><a href="../almacenes/registrar.php" role="menuitem"><i class="fas fa-plus"></i> Registrar Almacén</a></li>
                <?php endif; ?>
                <li><a href="../almacenes/listar.php" role="menuitem"><i class="fas fa-list"></i> Lista de Almacenes</a></li>
            </ul>
        </li>
        
        <!-- Historial Section - Reemplaza la sección de Entregas -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Historial" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-history"></i> Historial</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li><a href="../entregas/historial.php"role="menuitem"><i class="fas fa-hand-holding"></i> Historial de Entregas</a></li>
                <li><a href="../notificaciones/historial.php" role="menuitem"><i class="fas fa-exchange-alt"></i> Historial de Solicitudes</a></li>
            </ul>
        </li>
        
        <!-- Notifications Section - Con badge rojo de notificaciones -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Notificaciones" aria-expanded="false" role="button" tabindex="0">
                <span>
                    <i class="fas fa-bell"></i> Notificaciones
                </span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li>
                    <a href="../notificaciones/pendientes.php" role="menuitem">
                        <i class="fas fa-clock"></i> Solicitudes Pendientes
                        <?php if ($total_pendientes > 0): ?>
                        <span class="badge-small"><?php echo $total_pendientes; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </li>

        <!-- Reports Section (Admin only) -->
        <?php if ($usuario_rol == 'admin'): ?>
        <li class="submenu-container">
            <a href="#" aria-label="Menú Reportes" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-chart-bar"></i> Reportes</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li><a href="../reportes/inventario.php" role="menuitem"><i class="fas fa-warehouse"></i> Inventario General</a></li>
                <li><a href="../reportes/movimientos.php" role="menuitem"><i class="fas fa-exchange-alt"></i> Movimientos</a></li>
                <li><a href="../reportes/usuarios.php" role="menuitem"><i class="fas fa-users"></i> Actividad de Usuarios</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- User Profile -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Perfil" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-user-circle"></i> Mi Perfil</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li><a href="../perfil/cambiar-password.php" role="menuitem"><i class="fas fa-key"></i> Cambiar Contraseña</a></li>
            </ul>
        </li>

        <!-- Logout -->
        <li>
            <a href="#" onclick="manejarCerrarSesion(event)" aria-label="Cerrar sesión">
                <span><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</span>
            </a>
        </li>
    </ul>
</nav>

<!-- Contenido Principal -->
<main class="content" id="main-content" role="main">
    <!-- Mensajes de éxito o error -->
    <?php if (!empty($error)): ?>
        <div class="alert error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- Header de página -->
    <div class="page-header">
        <h1>
            <i class="fas fa-edit"></i>
            Editar Producto
        </h1>
        <p class="page-description">
            Modifica la información del producto "<?php echo htmlspecialchars($producto['nombre']); ?>"
        </p>
        
        <!-- ⭐ BREADCRUMB CON CONTEXTO -->
        <div class="breadcrumb">
            <a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <a href="<?php echo $return_url; ?>"><?php echo $return_text; ?></a>
            <span><i class="fas fa-chevron-right"></i></span>
            <a href="ver-producto.php?id=<?php echo $producto_id; ?><?php echo $context_params ? '&from=' . urlencode($context_params) : ''; ?>"><?php echo htmlspecialchars($producto['nombre']); ?></a>
            <span><i class="fas fa-chevron-right"></i></span>
            <span class="current">Editar</span>
        </div>
    </div>

    <!-- ===== LAYOUT DE DOS COLUMNAS ===== -->
    <div class="edit-layout">
        <!-- ===== COLUMNA PRINCIPAL - FORMULARIO ===== -->
        <div class="edit-main">
            <div class="edit-container">
                <div class="form-header">
                    <div class="form-icon">
                        <i class="fas fa-edit"></i>
                    </div>
                    <h2>Editar Información del Producto</h2>
                    <p>Actualice los campos que desea modificar de manera organizada</p>
                </div>

                <form id="formEditarProducto" action="" method="POST" autocomplete="off">
                    
                    <!-- ===== SECCIÓN 1: INFORMACIÓN BÁSICA ===== -->
                    <div class="form-section-card">
                        <div class="form-section-header">
                            <h3><i class="fas fa-info-circle"></i> Información Básica</h3>
                            <p class="form-section-subtitle">Datos principales del producto</p>
                        </div>
                        <div class="form-section-content">
                            <div class="form-grid two-columns">
                                <div class="form-group">
                                    <label for="nombre" class="form-label">
                                        <i class="fas fa-box"></i>
                                        Nombre del Producto
                                        <span class="required">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        id="nombre" 
                                        name="nombre" 
                                        value="<?php echo htmlspecialchars($producto['nombre']); ?>" 
                                        required
                                        autocomplete="off"
                                        maxlength="100"
                                        placeholder="Nombre descriptivo del producto"
                                    >
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Nombre descriptivo y único del producto
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="categoria_id" class="form-label">
                                        <i class="fas fa-tags"></i>
                                        Categoría
                                        <span class="required">*</span>
                                    </label>
                                    <select id="categoria_id" name="categoria_id" required>
                                        <option value="">Seleccione una categoría</option>
                                        <?php while ($categoria = $categorias->fetch_assoc()): ?>
                                            <option value="<?php echo $categoria['id']; ?>" 
                                                    <?php echo ($categoria['id'] == $producto['categoria_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($categoria['nombre']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Categoría a la que pertenece el producto
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== SECCIÓN 2: CARACTERÍSTICAS DEL PRODUCTO ===== -->
                    <div class="form-section-card">
                        <div class="form-section-header">
                            <h3><i class="fas fa-cogs"></i> Características del Producto</h3>
                            <p class="form-section-subtitle">Detalles específicos y propiedades físicas</p>
                        </div>
                        <div class="form-section-content">
                            <div class="form-grid three-columns">
                                <div class="form-group">
                                    <label for="modelo" class="form-label">
                                        <i class="fas fa-tag"></i>
                                        Modelo
                                    </label>
                                    <input 
                                        type="text" 
                                        id="modelo" 
                                        name="modelo" 
                                        value="<?php echo htmlspecialchars($producto['modelo']); ?>" 
                                        autocomplete="off"
                                        maxlength="50"
                                        placeholder="Modelo o referencia"
                                    >
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Modelo o referencia del fabricante
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="color" class="form-label">
                                        <i class="fas fa-palette"></i>
                                        Color
                                    </label>
                                    <input 
                                        type="text" 
                                        id="color" 
                                        name="color" 
                                        value="<?php echo htmlspecialchars($producto['color']); ?>" 
                                        autocomplete="off"
                                        maxlength="30"
                                        placeholder="Color principal"
                                    >
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Color predominante del producto
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="talla_dimensiones" class="form-label">
                                        <i class="fas fa-ruler"></i>
                                        Talla / Dimensiones
                                    </label>
                                    <input 
                                        type="text" 
                                        id="talla_dimensiones" 
                                        name="talla_dimensiones" 
                                        value="<?php echo htmlspecialchars($producto['talla_dimensiones']); ?>" 
                                        autocomplete="off"
                                        maxlength="50"
                                        placeholder="Ej: L, 10x5x3 cm"
                                    >
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Talla o dimensiones físicas
                                    </div>
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="estado" class="form-label">
                                        <i class="fas fa-shield-alt"></i>
                                        Estado del Producto
                                        <span class="required">*</span>
                                    </label>
                                    <select id="estado" name="estado" required>
                                        <option value="">Seleccione el estado</option>
                                        <option value="Nuevo" <?php echo ($producto['estado'] === 'Nuevo') ? 'selected' : ''; ?>>
                                            🆕 Nuevo
                                        </option>
                                        <option value="Usado" <?php echo ($producto['estado'] === 'Usado') ? 'selected' : ''; ?>>
                                            ♻️ Usado
                                        </option>
                                        <option value="Dañado" <?php echo ($producto['estado'] === 'Dañado') ? 'selected' : ''; ?>>
                                            ⚠️ Dañado
                                        </option>
                                    </select>
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Condición actual del producto
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== SECCIÓN 3: INVENTARIO Y UBICACIÓN ===== -->
                    <div class="form-section-card">
                        <div class="form-section-header">
                            <h3><i class="fas fa-warehouse"></i> Inventario y Ubicación</h3>
                            <p class="form-section-subtitle">Control de stock y almacenamiento</p>
                        </div>
                        <div class="form-section-content">
                            <div class="form-grid three-columns">
                                <div class="form-group">
                                    <label for="cantidad" class="form-label">
                                        <i class="fas fa-sort-numeric-up"></i>
                                        Cantidad Disponible
                                        <span class="required">*</span>
                                    </label>
                                    <input 
                                        type="number" 
                                        id="cantidad" 
                                        name="cantidad" 
                                        value="<?php echo $producto['cantidad']; ?>" 
                                        min="0"
                                        max="99999"
                                        required
                                        placeholder="0"
                                    >
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Cantidad actual en stock
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="unidad_medida" class="form-label">
                                        <i class="fas fa-balance-scale"></i>
                                        Unidad de Medida
                                        <span class="required">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        id="unidad_medida" 
                                        name="unidad_medida" 
                                        value="<?php echo htmlspecialchars($producto['unidad_medida']); ?>" 
                                        required
                                        autocomplete="off"
                                        maxlength="20"
                                        placeholder="Ej: unidades, kg, litros"
                                    >
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Forma de medir el producto
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="almacen_id" class="form-label">
                                        <i class="fas fa-building"></i>
                                        Almacén de Ubicación
                                        <span class="required">*</span>
                                    </label>
                                    <select id="almacen_id" name="almacen_id" required>
                                        <option value="">Seleccione un almacén</option>
                                        <?php while ($almacen = $almacenes->fetch_assoc()): ?>
                                            <option value="<?php echo $almacen['id']; ?>" 
                                                    <?php echo ($almacen['id'] == $producto['almacen_id']) ? 'selected' : ''; ?>>
                                                🏢 <?php echo htmlspecialchars($almacen['nombre']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Almacén donde se encuentra el producto
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== SECCIÓN 4: OBSERVACIONES ADICIONALES ===== -->
                    <div class="form-section-card">
                        <div class="form-section-header">
                            <h3><i class="fas fa-comment-alt"></i> Observaciones Adicionales</h3>
                            <p class="form-section-subtitle">Información complementaria y notas importantes</p>
                        </div>
                        <div class="form-section-content">
                            <div class="form-group full-width">
                                <label for="observaciones" class="form-label">
                                    <i class="fas fa-sticky-note"></i>
                                    Notas y Observaciones
                                </label>
                                <textarea 
                                    id="observaciones" 
                                    name="observaciones" 
                                    rows="4"
                                    maxlength="500"
                                    placeholder="Escriba aquí cualquier observación importante sobre el producto, su uso, mantenimiento, o características especiales..."
                                ><?php echo htmlspecialchars($producto['observaciones']); ?></textarea>
                                <div class="field-hint">
                                    <i class="fas fa-info-circle"></i>
                                    Información adicional que considere importante (opcional - máximo 500 caracteres)
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== ACCIONES DEL FORMULARIO ===== -->
                    <div class="form-actions-card">
                        <div class="form-actions">
                            <button type="submit" class="btn-submit" id="btnGuardar">
                                <i class="fas fa-save"></i>
                                Guardar Cambios
                            </button>
                            
                            <a href="ver-producto.php?id=<?php echo $producto_id; ?><?php echo $context_params ? '&from=' . urlencode($context_params) : ''; ?>" class="btn-cancel">
                                <i class="fas fa-times"></i>
                                Cancelar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ===== BARRA LATERAL DERECHA - ACCIONES ADICIONALES ===== -->
        <div class="edit-sidebar">
            <div class="additional-actions">
                <div class="additional-actions-header">
                    <h3>Acciones Rápidas</h3>
                    <p>Opciones relacionadas</p>
                </div>
                
                <div class="action-item">
                    <a href="ver-producto.php?id=<?php echo $producto_id; ?><?php echo $context_params ? '&from=' . urlencode($context_params) : ''; ?>" class="action-link">
                        <i class="fas fa-eye"></i>
                        <div>
                            <strong>Ver Detalles</strong>
                            <small>Vista completa del producto</small>
                        </div>
                    </a>
                </div>
                
                <div class="action-item">
                    <a href="<?php echo $return_url; ?>" class="action-link">
                        <i class="fas fa-list"></i>
                        <div>
                            <strong>Volver a Lista</strong>
                            <small><?php echo $return_text; ?></small>
                        </div>
                    </a>
                </div>
                
                <div class="action-item">
                    <a href="#" onclick="eliminarProducto(<?php echo $producto_id; ?>, '<?php echo htmlspecialchars($producto['nombre']); ?>')" class="action-link danger">
                        <i class="fas fa-trash-alt"></i>
                        <div>
                            <strong>Eliminar</strong>
                            <small>⚠️ Eliminar producto</small>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Container for dynamic notifications -->
<div id="notificaciones-container" role="alert" aria-live="polite"></div>

<!-- ⭐ JAVASCRIPT CON LÓGICA ORIGINAL -->
<script>
// Variables para el contexto
const CONTEXT_PARAMS = '<?php echo urlencode($context_params); ?>';
const RETURN_URL = '<?php echo $return_url; ?>';
const PRODUCT_ID = <?php echo $producto_id; ?>;

document.addEventListener('DOMContentLoaded', function() {
    // ⭐ LIMPIAR URL DESPUÉS DE CARGAR (SIN CAMBIAR LA FUNCIONALIDAD)
    if (window.location.search && window.history.replaceState) {
        // Crear una URL limpia para mostrar
        const cleanUrl = window.location.pathname;
        const pageTitle = 'Editar Producto - <?php echo htmlspecialchars($producto['nombre']); ?> - GRUPO SEAL';
        
        // Reemplazar la URL en el historial sin recargar la página
        window.history.replaceState(
            { 
                productId: PRODUCT_ID, 
                context: CONTEXT_PARAMS 
            }, 
            pageTitle, 
            cleanUrl
        );
    }
    
    // Elementos principales
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const submenuContainers = document.querySelectorAll('.submenu-container');
    const form = document.getElementById('formEditarProducto');
    
    // Toggle del menú móvil
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            if (mainContent) {
                mainContent.classList.toggle('with-sidebar');
            }
            
            // Cambiar icono del botón
            const icon = this.querySelector('i');
            if (sidebar.classList.contains('active')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
                this.setAttribute('aria-label', 'Cerrar menú de navegación');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
                this.setAttribute('aria-label', 'Abrir menú de navegación');
            }
        });
    }
    
    // Funcionalidad de submenús
    submenuContainers.forEach(container => {
        const link = container.querySelector('a');
        const submenu = container.querySelector('.submenu');
        const chevron = link.querySelector('.fa-chevron-down');
        
        if (link && submenu) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Cerrar otros submenús
                submenuContainers.forEach(otherContainer => {
                    if (otherContainer !== container) {
                        const otherSubmenu = otherContainer.querySelector('.submenu');
                        const otherChevron = otherContainer.querySelector('.fa-chevron-down');
                        const otherLink = otherContainer.querySelector('a');
                        
                        if (otherSubmenu && otherSubmenu.classList.contains('activo')) {
                            otherSubmenu.classList.remove('activo');
                            if (otherChevron) {
                                otherChevron.style.transform = 'rotate(0deg)';
                            }
                            if (otherLink) {
                                otherLink.setAttribute('aria-expanded', 'false');
                            }
                        }
                    }
                });
                
                // Toggle del submenú actual
                submenu.classList.toggle('activo');
                const isExpanded = submenu.classList.contains('activo');
                
                if (chevron) {
                    chevron.style.transform = isExpanded ? 'rotate(180deg)' : 'rotate(0deg)';
                }
                
                link.setAttribute('aria-expanded', isExpanded.toString());
            });
        }
    });
    
    // Cerrar menú móvil al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
                if (mainContent) {
                    mainContent.classList.remove('with-sidebar');
                }
                
                const icon = menuToggle.querySelector('i');
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
                menuToggle.setAttribute('aria-label', 'Abrir menú de navegación');
            }
        }
    });
    
    // Navegación por teclado
    document.addEventListener('keydown', function(e) {
        // Cerrar menú móvil con Escape
        if (e.key === 'Escape' && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            if (mainContent) {
                mainContent.classList.remove('with-sidebar');
            }
            menuToggle.focus();
        }
        
        // Indicador visual para navegación por teclado
        if (e.key === 'Tab') {
            document.body.classList.add('keyboard-navigation');
        }
    });
    
    document.addEventListener('mousedown', function() {
        document.body.classList.remove('keyboard-navigation');
    });
    
    // Validación del formulario
    if (form) {
        const inputs = form.querySelectorAll('input[required], select[required]');
        const submitBtn = document.getElementById('btnGuardar');
        
        function validateForm() {
            let isValid = true;
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('error');
                    input.closest('.form-group').classList.add('error');
                } else {
                    input.classList.remove('error');
                    input.classList.add('success');
                    input.closest('.form-group').classList.remove('error');
                    input.closest('.form-group').classList.add('success');
                }
            });
            
            if (submitBtn) {
                if (isValid) {
                    submitBtn.classList.add('has-changes');
                } else {
                    submitBtn.classList.remove('has-changes');
                }
            }
            
            return isValid;
        }
        
        inputs.forEach(input => {
            input.addEventListener('blur', validateForm);
            input.addEventListener('input', function() {
                validateForm();
                // Marcar como modificado
                this.classList.add('modified');
            });
        });
        
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                mostrarNotificacion('Por favor, complete todos los campos obligatorios.', 'error');
            } else {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
                submitBtn.disabled = true;
                submitBtn.classList.add('loading');
            }
        });
    }
    
    // Detección de cambios en el formulario
    const originalValues = {};
    const inputs = form.querySelectorAll('input, select, textarea');
    
    inputs.forEach(input => {
        originalValues[input.name] = input.value;
    });
    
    function checkForChanges() {
        let hasChanges = false;
        
        inputs.forEach(input => {
            if (input.value !== originalValues[input.name]) {
                hasChanges = true;
                input.classList.add('modified');
            } else {
                input.classList.remove('modified');
            }
        });
        
        const submitBtn = document.getElementById('btnGuardar');
        if (submitBtn) {
            if (hasChanges) {
                submitBtn.classList.add('has-changes');
            } else {
                submitBtn.classList.remove('has-changes');
            }
        }
    }
    
    inputs.forEach(input => {
        input.addEventListener('input', checkForChanges);
        input.addEventListener('change', checkForChanges);
    });
});

// Función para mostrar notificaciones
function mostrarNotificacion(mensaje, tipo = 'info', duracion = 5000) {
    const container = document.getElementById('notificaciones-container');
    if (!container) return;
    
    const notificacion = document.createElement('div');
    notificacion.className = `notificacion ${tipo}`;
    
    const iconos = {
        'exito': 'fas fa-check-circle',
        'error': 'fas fa-exclamation-circle', 
        'info': 'fas fa-info-circle',
        'warning': 'fas fa-exclamation-triangle'
    };
    
    notificacion.innerHTML = `
        <i class="${iconos[tipo] || iconos['info']}"></i>
        <span>${mensaje}</span>
        <button class="cerrar" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(notificacion);
    
    // Auto-remover después de la duración especificada
    if (duracion > 0) {
        setTimeout(() => {
            if (notificacion.parentElement) {
                notificacion.style.animation = 'slideOutRight 0.3s ease-in';
                setTimeout(() => notificacion.remove(), 300);
            }
        }, duracion);
    }
}

// ⭐ FUNCIÓN PARA ELIMINAR PRODUCTO CON REDIRECCIÓN CONTEXTUAL
async function eliminarProducto(id, nombre) {
    if (confirm(`¿Está seguro de que desea eliminar el producto "${nombre}"?\n\nEsta acción no se puede deshacer.`)) {
        mostrarNotificacion('Eliminando producto...', 'info');
        
        try {
            const response = await fetch('eliminar_producto.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: id })
            });

            const data = await response.json();

            if (data.success) {
                mostrarNotificacion('Producto eliminado correctamente', 'exito');
                
                setTimeout(() => {
                    // Redirigir a la lista con contexto
                    window.location.href = RETURN_URL;
                }, 2000);
            } else {
                mostrarNotificacion(data.message || 'Error al eliminar el producto', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            mostrarNotificacion('Error de conexión al eliminar el producto', 'error');
        }
    }
}

// Función para cerrar sesión con confirmación
async function manejarCerrarSesion(event) {
    event.preventDefault();
    
    if (confirm('¿Está seguro de que desea cerrar sesión?')) {
        mostrarNotificacion('Cerrando sesión...', 'info', 2000);
        
        setTimeout(() => {
            window.location.href = '../logout.php';
        }, 1000);
    }
}

// Manejo de errores globales
window.addEventListener('error', function(e) {
    console.error('Error detectado:', e.error);
    mostrarNotificacion('Se ha producido un error. Por favor, recarga la página.', 'error');
});

// Mostrar notificaciones si hay mensajes de sesión
<?php if (isset($_SESSION['success'])): ?>
mostrarNotificacion('<?php echo $_SESSION['success']; ?>', 'exito');
<?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
mostrarNotificacion('<?php echo $_SESSION['error']; ?>', 'error');
<?php unset($_SESSION['error']); ?>
<?php endif; ?>
</script>
</body>
</html>