<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

session_regenerate_id(true);

// Obtener información del usuario
$user_name = $_SESSION["user_name"] ?? "Usuario";
$usuario_rol = $_SESSION["user_role"] ?? "usuario";
$usuario_almacen_id = $_SESSION["almacen_id"] ?? null;

// Verificar permisos de administrador
if ($usuario_rol !== 'admin') {
    $_SESSION['error'] = "No tienes permisos para registrar productos.";
    header("Location: listar.php");
    exit();
}

require_once "../config/database.php";

// Obtener parámetros de la URL (opcional)
$almacen_id_param = isset($_GET['almacen_id']) ? (int)$_GET['almacen_id'] : null;
$categoria_id_param = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : null;

// Obtener lista de almacenes
$sql_almacenes = "SELECT id, nombre FROM almacenes ORDER BY nombre";
$result_almacenes = $conn->query($sql_almacenes);

// Obtener lista de categorías
$sql_categorias = "SELECT id, nombre FROM categorias ORDER BY nombre";
$result_categorias = $conn->query($sql_categorias);

// Definir campos por categoría
$campos_por_categoria = [
    1 => ["nombre", "modelo", "color", "talla_dimensiones", "cantidad", "unidad_medida", "estado", "observaciones"],
    2 => ["nombre", "modelo", "color", "cantidad", "unidad_medida", "estado", "observaciones"],
    3 => ["nombre", "color", "talla_dimensiones", "cantidad", "unidad_medida", "estado", "observaciones"],
    4 => ["nombre", "modelo", "color", "cantidad", "unidad_medida", "estado", "observaciones"],
    6 => ["nombre", "modelo", "color", "cantidad", "unidad_medida", "estado", "observaciones"],
];

$mensaje = "";
$error = "";

// Valores para mantener en el formulario
$form_data = [
    'almacen_id' => $almacen_id_param,
    'categoria_id' => $categoria_id_param,
    'nombre' => '',
    'modelo' => '',
    'color' => '',
    'talla_dimensiones' => '',
    'cantidad' => '',
    'unidad_medida' => '',
    'estado' => 'Nuevo',
    'observaciones' => ''
];

// Procesar formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Capturar datos del formulario
    $form_data = [
        'almacen_id' => (int)($_POST["almacen_id"] ?? 0),
        'categoria_id' => (int)($_POST["categoria_id"] ?? 0),
        'nombre' => trim($_POST["nombre"] ?? ''),
        'modelo' => trim($_POST["modelo"] ?? ''),
        'color' => trim($_POST["color"] ?? ''),
        'talla_dimensiones' => trim($_POST["talla_dimensiones"] ?? ''),
        'cantidad' => (int)($_POST["cantidad"] ?? 0),
        'unidad_medida' => trim($_POST["unidad_medida"] ?? ''),
        'estado' => trim($_POST["estado"] ?? 'Nuevo'),
        'observaciones' => trim($_POST["observaciones"] ?? '')
    ];

    // Validaciones
    if (empty($form_data['nombre'])) {
        $error = "⚠️ El nombre del producto es obligatorio.";
    } elseif ($form_data['almacen_id'] <= 0) {
        $error = "⚠️ Debe seleccionar un almacén válido.";
    } elseif ($form_data['categoria_id'] <= 0) {
        $error = "⚠️ Debe seleccionar una categoría válida.";
    } elseif ($form_data['cantidad'] <= 0) {
        $error = "⚠️ La cantidad debe ser mayor a 0.";
    } elseif (empty($form_data['unidad_medida'])) {
        $error = "⚠️ La unidad de medida es obligatoria.";
    } else {
        // Verificar que el almacén existe
        $sql_check_almacen = "SELECT id FROM almacenes WHERE id = ?";
        $stmt_check = $conn->prepare($sql_check_almacen);
        $stmt_check->bind_param("i", $form_data['almacen_id']);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows == 0) {
            $error = "⚠️ El almacén seleccionado no existe.";
        }
        $stmt_check->close();

        // Verificar que la categoría existe
        if (empty($error)) {
            $sql_check_categoria = "SELECT id FROM categorias WHERE id = ?";
            $stmt_check = $conn->prepare($sql_check_categoria);
            $stmt_check->bind_param("i", $form_data['categoria_id']);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows == 0) {
                $error = "⚠️ La categoría seleccionada no existe.";
            }
            $stmt_check->close();
        }

        // Verificar si el producto ya existe en el almacén
        if (empty($error)) {
            $sql_check_producto = "SELECT id FROM productos WHERE nombre = ? AND almacen_id = ? AND categoria_id = ?";
            $stmt_check = $conn->prepare($sql_check_producto);
            $stmt_check->bind_param("sii", $form_data['nombre'], $form_data['almacen_id'], $form_data['categoria_id']);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                $error = "⚠️ Ya existe un producto con ese nombre en el almacén seleccionado.";
            }
            $stmt_check->close();
        }

        // Si no hay errores, insertar el producto
        if (empty($error)) {
            $sql_insert = "INSERT INTO productos (nombre, modelo, color, talla_dimensiones, cantidad, unidad_medida, estado, observaciones, almacen_id, categoria_id) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql_insert);

            if ($stmt) {
                $stmt->bind_param("ssssssssii", 
                    $form_data['nombre'],
                    $form_data['modelo'],
                    $form_data['color'],
                    $form_data['talla_dimensiones'],
                    $form_data['cantidad'],
                    $form_data['unidad_medida'],
                    $form_data['estado'],
                    $form_data['observaciones'],
                    $form_data['almacen_id'],
                    $form_data['categoria_id']
                );

                if ($stmt->execute()) {
                    $producto_id = $conn->insert_id;
                    
                    // Registrar la acción en logs (opcional)
                    $usuario_id = $_SESSION["user_id"];
                    $sql_log = "INSERT INTO logs_actividad (usuario_id, accion, detalle, fecha_accion) 
                                VALUES (?, 'REGISTRAR_PRODUCTO', ?, NOW())";
                    $stmt_log = $conn->prepare($sql_log);
                    $detalle = "Registró producto: {$form_data['nombre']} (ID: {$producto_id})";
                    $stmt_log->bind_param("is", $usuario_id, $detalle);
                    $stmt_log->execute();
                    $stmt_log->close();
                    
                    $_SESSION['success'] = "✅ Producto registrado con éxito.";
                    
                    // Limpiar formulario después del registro exitoso
                    $form_data = [
                        'almacen_id' => $form_data['almacen_id'], // Mantener almacén seleccionado
                        'categoria_id' => $form_data['categoria_id'], // Mantener categoría seleccionada
                        'nombre' => '',
                        'modelo' => '',
                        'color' => '',
                        'talla_dimensiones' => '',
                        'cantidad' => '',
                        'unidad_medida' => '',
                        'estado' => 'Nuevo',
                        'observaciones' => ''
                    ];
                } else {
                    $error = "❌ Error al registrar el producto: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = "❌ Error en la consulta SQL: " . $conn->error;
            }
        }
    }
}

// Contar solicitudes pendientes para el badge
$sql_pendientes = "SELECT COUNT(*) as total FROM solicitudes_transferencia WHERE estado = 'pendiente'";
$result_pendientes = $conn->query($sql_pendientes);
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
    <title>Registrar Producto - COMSEPROA</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Registrar nuevo producto en el sistema COMSEPROA">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- CSS específico para registrar productos -->
    <link rel="stylesheet" href="../assets/css/productos-registrar.css">
</head>
<body>

<!-- Botón de hamburguesa para dispositivos móviles -->
<button class="menu-toggle" id="menuToggle" aria-label="Abrir menú de navegación">
    <i class="fas fa-bars"></i>
</button>

<!-- Menú Lateral -->
<nav class="sidebar" id="sidebar" role="navigation" aria-label="Menú principal">
    <h2>COMSEPROA</h2>
    <ul>
        <li>
            <a href="../dashboard.php" aria-label="Ir a inicio">
                <span><i class="fas fa-home"></i> Inicio</span>
            </a>
        </li>

        <!-- Users - Only visible to administrators -->
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

        <!-- Warehouses -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Almacenes" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-warehouse"></i> Almacenes</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li><a href="../almacenes/registrar.php" role="menuitem"><i class="fas fa-plus"></i> Registrar Almacén</a></li>
                <li><a href="../almacenes/listar.php" role="menuitem"><i class="fas fa-list"></i> Lista de Almacenes</a></li>
            </ul>
        </li>
        
        <!-- Products Section -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Productos" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-boxes"></i> Productos</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li class="active"><a href="registrar.php" role="menuitem"><i class="fas fa-plus"></i> Registrar Producto</a></li>
                <li><a href="listar.php" role="menuitem"><i class="fas fa-list"></i> Lista de Productos</a></li>
                <li><a href="categorias.php" role="menuitem"><i class="fas fa-tags"></i> Categorías</a></li>
            </ul>
        </li>
        
        <!-- Notifications -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Notificaciones" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-bell"></i> Notificaciones</span>
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
                <li><a href="../notificaciones/historial.php" role="menuitem"><i class="fas fa-history"></i> Historial de Solicitudes</a></li>
                <li><a href="../uniformes/historial_entregas_uniformes.php" role="menuitem"><i class="fas fa-tshirt"></i> Historial de Entregas</a></li>
            </ul>
        </li>

        <!-- Reports Section -->
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

        <!-- User Profile -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Perfil" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-user-circle"></i> Mi Perfil</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li><a href="../perfil/configuracion.php" role="menuitem"><i class="fas fa-cog"></i> Configuración</a></li>
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
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert success">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; ?>
            <?php unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- Header de la página -->
    <header class="page-header">
        <div class="header-content">
            <div class="header-info">
                <h1>
                    <i class="fas fa-plus-circle"></i>
                    Registrar Nuevo Producto
                </h1>
                <p class="page-description">
                    Complete la información del producto que desea agregar al inventario
                </p>
            </div>
            
            <div class="header-actions">
                <a href="listar.php" class="btn-header btn-secondary">
                    <i class="fas fa-list"></i>
                    <span>Ver Productos</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Breadcrumb -->
    <nav class="breadcrumb" aria-label="Ruta de navegación">
        <a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a>
        <span><i class="fas fa-chevron-right"></i></span>
        <a href="listar.php">Productos</a>
        <span><i class="fas fa-chevron-right"></i></span>
        <span class="current">Registrar</span>
    </nav>

    <!-- Formulario de registro -->
    <section class="register-section">
        <div class="form-container">
            <div class="form-header">
                <div class="form-icon">
                    <i class="fas fa-box"></i>
                </div>
                <h2>Información del Producto</h2>
                <p>Complete todos los campos requeridos para registrar el producto</p>
            </div>

            <form id="formRegistrarProducto" action="" method="POST" autocomplete="off">
                <!-- Selección de Almacén y Categoría -->
                <div class="selection-section">
                    <div class="form-group">
                        <label for="almacen_id" class="form-label required">
                            <i class="fas fa-warehouse"></i>
                            Almacén de destino
                        </label>
                        <select id="almacen_id" name="almacen_id" required class="form-select">
                            <option value="">Seleccione un almacén</option>
                            <?php while ($almacen = $result_almacenes->fetch_assoc()): ?>
                                <option value="<?php echo $almacen['id']; ?>" 
                                    <?php echo ($form_data['almacen_id'] == $almacen['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($almacen['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="field-hint">
                            <i class="fas fa-info-circle"></i>
                            Seleccione el almacén donde se registrará el producto
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="categoria_id" class="form-label required">
                            <i class="fas fa-tags"></i>
                            Categoría del producto
                        </label>
                        <select id="categoria_id" name="categoria_id" required class="form-select">
                            <option value="">Seleccione una categoría</option>
                            <?php while ($categoria = $result_categorias->fetch_assoc()): ?>
                                <option value="<?php echo $categoria['id']; ?>" 
                                    <?php echo ($form_data['categoria_id'] == $categoria['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($categoria['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="field-hint">
                            <i class="fas fa-info-circle"></i>
                            La categoría determinará qué campos están disponibles
                        </div>
                    </div>
                </div>

                <!-- Campos dinámicos del producto -->
                <div class="product-fields" id="productFields" style="display: none;">
                    <div class="fields-grid">
                        <!-- Nombre (siempre visible) -->
                        <div class="form-group" data-field="nombre">
                            <label for="nombre" class="form-label required">
                                <i class="fas fa-box"></i>
                                Nombre del producto
                            </label>
                            <input 
                                type="text" 
                                id="nombre" 
                                name="nombre" 
                                value="<?php echo htmlspecialchars($form_data['nombre']); ?>"
                                placeholder="Ej: Camisa de seguridad, Casco protector..."
                                required
                                autocomplete="off"
                                maxlength="100"
                                class="form-input"
                            >
                            <div class="field-hint">
                                <i class="fas fa-info-circle"></i>
                                Ingrese un nombre descriptivo y único para el producto
                            </div>
                        </div>

                        <!-- Modelo (dinámico) -->
                        <div class="form-group" data-field="modelo" style="display: none;">
                            <label for="modelo" class="form-label">
                                <i class="fas fa-barcode"></i>
                                Modelo
                            </label>
                            <input 
                                type="text" 
                                id="modelo" 
                                name="modelo" 
                                value="<?php echo htmlspecialchars($form_data['modelo']); ?>"
                                placeholder="Ej: XL-2024, PRO-500..."
                                autocomplete="off"
                                maxlength="50"
                                class="form-input"
                            >
                        </div>

                        <!-- Color (dinámico) -->
                        <div class="form-group" data-field="color" style="display: none;">
                            <label for="color" class="form-label">
                                <i class="fas fa-palette"></i>
                                Color
                            </label>
                            <input 
                                type="text" 
                                id="color" 
                                name="color" 
                                value="<?php echo htmlspecialchars($form_data['color']); ?>"
                                placeholder="Ej: Azul marino, Naranja reflectivo..."
                                autocomplete="off"
                                maxlength="30"
                                class="form-input"
                            >
                        </div>

                        <!-- Talla/Dimensiones (dinámico) -->
                        <div class="form-group" data-field="talla_dimensiones" style="display: none;">
                            <label for="talla_dimensiones" class="form-label">
                                <i class="fas fa-ruler-combined"></i>
                                Talla / Dimensiones
                            </label>
                            <input 
                                type="text" 
                                id="talla_dimensiones" 
                                name="talla_dimensiones" 
                                value="<?php echo htmlspecialchars($form_data['talla_dimensiones']); ?>"
                                placeholder="Ej: L, XL, 30cm x 25cm..."
                                autocomplete="off"
                                maxlength="50"
                                class="form-input"
                            >
                        </div>

                        <!-- Cantidad -->
                        <div class="form-group" data-field="cantidad">
                            <label for="cantidad" class="form-label required">
                                <i class="fas fa-sort-numeric-up"></i>
                                Cantidad inicial
                            </label>
                            <input 
                                type="number" 
                                id="cantidad" 
                                name="cantidad" 
                                value="<?php echo $form_data['cantidad']; ?>"
                                min="1"
                                max="99999"
                                required
                                class="form-input"
                            >
                            <div class="field-hint">
                                <i class="fas fa-info-circle"></i>
                                Cantidad de productos que se registrarán inicialmente
                            </div>
                        </div>

                        <!-- Unidad de medida -->
                        <div class="form-group" data-field="unidad_medida">
                            <label for="unidad_medida" class="form-label required">
                                <i class="fas fa-balance-scale"></i>
                                Unidad de medida
                            </label>
                            <select id="unidad_medida" name="unidad_medida" required class="form-select">
                                <option value="">Seleccione una unidad</option>
                                <option value="Unidad" <?php echo ($form_data['unidad_medida'] == 'Unidad') ? 'selected' : ''; ?>>Unidad</option>
                                <option value="Par" <?php echo ($form_data['unidad_medida'] == 'Par') ? 'selected' : ''; ?>>Par</option>
                                <option value="Paquete" <?php echo ($form_data['unidad_medida'] == 'Paquete') ? 'selected' : ''; ?>>Paquete</option>
                                <option value="Caja" <?php echo ($form_data['unidad_medida'] == 'Caja') ? 'selected' : ''; ?>>Caja</option>
                                <option value="Metro" <?php echo ($form_data['unidad_medida'] == 'Metro') ? 'selected' : ''; ?>>Metro</option>
                                <option value="Kilogramo" <?php echo ($form_data['unidad_medida'] == 'Kilogramo') ? 'selected' : ''; ?>>Kilogramo</option>
                                <option value="Litro" <?php echo ($form_data['unidad_medida'] == 'Litro') ? 'selected' : ''; ?>>Litro</option>
                            </select>
                        </div>

                        <!-- Estado -->
                        <div class="form-group" data-field="estado">
                            <label for="estado" class="form-label required">
                                <i class="fas fa-check-circle"></i>
                                Estado del producto
                            </label>
                            <select id="estado" name="estado" required class="form-select">
                                <option value="Nuevo" <?php echo ($form_data['estado'] == 'Nuevo') ? 'selected' : ''; ?>>Nuevo</option>
                                <option value="Usado" <?php echo ($form_data['estado'] == 'Usado') ? 'selected' : ''; ?>>Usado</option>
                                <option value="Dañado" <?php echo ($form_data['estado'] == 'Dañado') ? 'selected' : ''; ?>>Dañado</option>
                            </select>
                        </div>

                        <!-- Observaciones -->
                        <div class="form-group full-width" data-field="observaciones">
                            <label for="observaciones" class="form-label">
                                <i class="fas fa-comment"></i>
                                Observaciones
                            </label>
                            <textarea 
                                id="observaciones" 
                                name="observaciones" 
                                placeholder="Información adicional sobre el producto..."
                                rows="4"
                                maxlength="500"
                                class="form-textarea"
                            ><?php echo htmlspecialchars($form_data['observaciones']); ?></textarea>
                            <div class="field-hint">
                                <i class="fas fa-info-circle"></i>
                                Información adicional que considere relevante
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones de acción -->
                <div class="form-actions">
                    <button type="submit" class="btn-submit" id="btnRegistrar">
                        <i class="fas fa-save"></i>
                        <span>Registrar Producto</span>
                    </button>
                    
                    <button type="button" class="btn-reset" onclick="limpiarFormulario()">
                        <i class="fas fa-undo"></i>
                        <span>Limpiar Formulario</span>
                    </button>
                    
                    <a href="listar.php" class="btn-cancel">
                        <i class="fas fa-times"></i>
                        <span>Cancelar</span>
                    </a>
                </div>
            </form>
        </div>

        <!-- Panel de ayuda -->
        <aside class="help-panel">
            <div class="panel-header">
                <h3>
                    <i class="fas fa-question-circle"></i>
                    Ayuda para registro
                </h3>
            </div>
            
            <div class="panel-content">
                <div class="help-section">
                    <h4><i class="fas fa-lightbulb"></i> Consejos</h4>
                    <ul>
                        <li>Use nombres descriptivos y únicos para cada producto</li>
                        <li>Verifique que el almacén y categoría sean correctos</li>
                        <li>La cantidad inicial debe ser precisa para el inventario</li>
                        <li>Complete las observaciones para información adicional</li>
                    </ul>
                </div>
                
                <div class="help-section">
                    <h4><i class="fas fa-info-circle"></i> Campos por categoría</h4>
                    <ul>
                        <li><strong>Ropa:</strong> Incluye modelo, color y talla</li>
                        <li><strong>Accesorios:</strong> Incluye modelo y color</li>
                        <li><strong>Fundas:</strong> Incluye color y dimensiones</li>
                        <li><strong>Armas:</strong> Incluye modelo y color</li>
                        <li><strong>Walkie-Talkie:</strong> Incluye modelo y color</li>
                    </ul>
                </div>
                
                <div class="help-section">
                    <h4><i class="fas fa-keyboard"></i> Atajos de teclado</h4>
                    <ul>
                        <li><kbd>Ctrl</kbd> + <kbd>S</kbd> - Guardar producto</li>
                        <li><kbd>Ctrl</kbd> + <kbd>R</kbd> - Limpiar formulario</li>
                        <li><kbd>Esc</kbd> - Cancelar y volver</li>
                    </ul>
                </div>
            </div>
        </aside>
    </section>
</main>

<!-- Container for dynamic notifications -->
<div id="notificaciones-container" role="alert" aria-live="polite"></div>

<!-- JavaScript -->
<script src="../assets/js/productos-registrar.js"></script>
</body>
</html>