<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

session_regenerate_id(true);

// Obtener el rol del usuario para control de acceso
$user_name = $_SESSION["user_name"] ?? "Usuario";
$usuario_rol = $_SESSION["user_role"] ?? "usuario";
$usuario_almacen_id = $_SESSION["almacen_id"] ?? null;

// Solo administradores pueden registrar productos
if ($usuario_rol !== 'admin') {
    $_SESSION['error'] = "No tienes permisos para registrar productos.";
    header("Location: ../dashboard.php");
    exit();
}

require_once "../config/database.php";

// Obtener parámetros de la URL (opcionales)
$almacen_preseleccionado = isset($_GET['almacen_id']) ? (int)$_GET['almacen_id'] : null;
$categoria_preseleccionada = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : null;

$mensaje = "";
$error = "";

// Obtener lista de almacenes
$sql_almacenes = "SELECT id, nombre FROM almacenes ORDER BY nombre";
$result_almacenes = $conn->query($sql_almacenes);

// Obtener lista de categorías
$sql_categorias = "SELECT id, nombre FROM categorias ORDER BY nombre";
$result_categorias = $conn->query($sql_categorias);

// Manejo del formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST["nombre"] ?? '');
    $modelo = trim($_POST["modelo"] ?? '');
    $color = trim($_POST["color"] ?? '');
    $talla_dimensiones = trim($_POST["talla_dimensiones"] ?? '');
    $cantidad = isset($_POST["cantidad"]) ? (int)$_POST["cantidad"] : 0;
    $unidad_medida = trim($_POST["unidad_medida"] ?? '');
    $estado = trim($_POST["estado"] ?? '');
    $observaciones = trim($_POST["observaciones"] ?? '');
    $almacen_id = isset($_POST["almacen_id"]) ? (int)$_POST["almacen_id"] : 0;
    $categoria_id = isset($_POST["categoria_id"]) ? (int)$_POST["categoria_id"] : 0;

    // Validaciones
    if (empty($nombre)) {
        $error = "⚠️ El nombre del producto es obligatorio.";
    } elseif ($cantidad <= 0) {
        $error = "⚠️ La cantidad debe ser mayor a 0.";
    } elseif (empty($unidad_medida)) {
        $error = "⚠️ La unidad de medida es obligatoria.";
    } elseif (empty($estado)) {
        $error = "⚠️ El estado del producto es obligatorio.";
    } elseif ($almacen_id <= 0) {
        $error = "⚠️ Debe seleccionar un almacén válido.";
    } elseif ($categoria_id <= 0) {
        $error = "⚠️ Debe seleccionar una categoría válida.";
    } else {
        // Verificar si el producto ya existe en el mismo almacén
        $sql_check = "SELECT id FROM productos WHERE nombre = ? AND almacen_id = ? AND categoria_id = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("sii", $nombre, $almacen_id, $categoria_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $error = "⚠️ Ya existe un producto con ese nombre en este almacén y categoría.";
        } else {
            // Insertar el nuevo producto
            $sql = "INSERT INTO productos (nombre, modelo, color, talla_dimensiones, cantidad, unidad_medida, estado, observaciones, almacen_id, categoria_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param("ssssisssii", $nombre, $modelo, $color, $talla_dimensiones, $cantidad, $unidad_medida, $estado, $observaciones, $almacen_id, $categoria_id);
                
                if ($stmt->execute()) {
                    $producto_id = $conn->insert_id;
                    
                    // Registrar el movimiento inicial
                    $usuario_id = $_SESSION["user_id"];
                    $sql_movimiento = "INSERT INTO movimientos (producto_id, almacen_origen, cantidad, tipo, usuario_id, estado) 
                                       VALUES (?, ?, ?, 'entrada', ?, 'completado')";
                    $stmt_movimiento = $conn->prepare($sql_movimiento);
                    $stmt_movimiento->bind_param("iiii", $producto_id, $almacen_id, $cantidad, $usuario_id);
                    $stmt_movimiento->execute();
                    $stmt_movimiento->close();
                    
                    $_SESSION['success'] = "✅ Producto registrado con éxito.";
                    
                    // Redirigir según el contexto
                    if ($almacen_preseleccionado && $categoria_preseleccionada) {
                        header("Location: listar.php?almacen_id={$almacen_preseleccionado}&categoria_id={$categoria_preseleccionada}");
                    } elseif ($almacen_preseleccionado) {
                        header("Location: ../almacenes/ver-almacen.php?id={$almacen_preseleccionado}");
                    } else {
                        header("Location: listar.php");
                    }
                    exit();
                } else {
                    $error = "❌ Error al registrar el producto: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = "❌ Error en la consulta SQL: " . $conn->error;
            }
        }
        $stmt_check->close();
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
    <h2>GRUPO SEAL</h2>
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
                <li><a href="../uniformes/historial_entregas_uniformes.php" role="menuitem"><i class="fas fa-tshirt"></i> Ver Historial de Entregas</a></li>
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
    <?php if (!empty($mensaje)): ?>
        <div class="alert success">
            <i class="fas fa-check-circle"></i> <?php echo $mensaje; ?>
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
                
                <?php if ($almacen_preseleccionado): ?>
                <a href="../almacenes/ver-almacen.php?id=<?php echo $almacen_preseleccionado; ?>" class="btn-header btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    <span>Volver al Almacén</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Breadcrumb -->
    <nav class="breadcrumb" aria-label="Ruta de navegación">
        <a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a>
        <span><i class="fas fa-chevron-right"></i></span>
        
        <?php if ($almacen_preseleccionado): ?>
            <a href="../almacenes/listar.php">Almacenes</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <a href="../almacenes/ver-almacen.php?id=<?php echo $almacen_preseleccionado; ?>">Almacén</a>
            <span><i class="fas fa-chevron-right"></i></span>
        <?php else: ?>
            <a href="listar.php">Productos</a>
            <span><i class="fas fa-chevron-right"></i></span>
        <?php endif; ?>
        
        <span class="current">Registrar Producto</span>
    </nav>

    <!-- Formulario de registro -->
    <section class="form-section">
        <div class="form-container">
            <div class="form-header">
                <div class="form-icon">
                    <i class="fas fa-box"></i>
                </div>
                <h2>Información del Producto</h2>
                <p>Complete todos los campos marcados con (*) para registrar el producto</p>
            </div>

            <form id="formRegistrarProducto" action="" method="POST" autocomplete="off">
                <div class="form-grid">
                    <!-- Información básica -->
                    <div class="form-section-title">
                        <h3><i class="fas fa-info-circle"></i> Información Básica</h3>
                    </div>
                    
                    <div class="form-group">
                        <label for="nombre" class="form-label">
                            <i class="fas fa-tag"></i>
                            Nombre del Producto *
                        </label>
                        <input 
                            type="text" 
                            id="nombre" 
                            name="nombre" 
                            placeholder="Ej: Casco de seguridad, Uniforme operativo..."
                            required
                            autocomplete="off"
                            maxlength="100"
                            value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>"
                        >
                        <div class="field-hint">
                            <i class="fas fa-info-circle"></i>
                            Ingrese un nombre descriptivo y único para el producto
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="modelo" class="form-label">
                            <i class="fas fa-barcode"></i>
                            Modelo
                        </label>
                        <input 
                            type="text" 
                            id="modelo" 
                            name="modelo" 
                            placeholder="Ej: XL-500, Pro-2024..."
                            autocomplete="off"
                            maxlength="50"
                            value="<?php echo isset($_POST['modelo']) ? htmlspecialchars($_POST['modelo']) : ''; ?>"
                        >
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
                            placeholder="Ej: Negro, Azul marino, Verde..."
                            autocomplete="off"
                            maxlength="30"
                            value="<?php echo isset($_POST['color']) ? htmlspecialchars($_POST['color']) : ''; ?>"
                        >
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
                            placeholder="Ej: XL, 42, 30x25x10 cm..."
                            autocomplete="off"
                            maxlength="50"
                            value="<?php echo isset($_POST['talla_dimensiones']) ? htmlspecialchars($_POST['talla_dimensiones']) : ''; ?>"
                        >
                    </div>

                    <!-- Ubicación -->
                    <div class="form-section-title">
                        <h3><i class="fas fa-map-marker-alt"></i> Ubicación y Clasificación</h3>
                    </div>

                    <div class="form-group">
                        <label for="almacen_id" class="form-label">
                            <i class="fas fa-warehouse"></i>
                            Almacén de Destino *
                        </label>
                        <select id="almacen_id" name="almacen_id" required class="form-select">
                            <option value="">Seleccione un almacén</option>
                            <?php while ($almacen = $result_almacenes->fetch_assoc()): ?>
                                <option value="<?php echo $almacen['id']; ?>" 
                                    <?php echo ($almacen_preseleccionado == $almacen['id'] || (isset($_POST['almacen_id']) && $_POST['almacen_id'] == $almacen['id'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($almacen['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="field-hint">
                            <i class="fas fa-info-circle"></i>
                            Seleccione el almacén donde se guardará el producto
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="categoria_id" class="form-label">
                            <i class="fas fa-layer-group"></i>
                            Categoría *
                        </label>
                        <select id="categoria_id" name="categoria_id" required class="form-select">
                            <option value="">Seleccione una categoría</option>
                            <?php while ($categoria = $result_categorias->fetch_assoc()): ?>
                                <option value="<?php echo $categoria['id']; ?>" 
                                    <?php echo ($categoria_preseleccionada == $categoria['id'] || (isset($_POST['categoria_id']) && $_POST['categoria_id'] == $categoria['id'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($categoria['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="field-hint">
                            <i class="fas fa-info-circle"></i>
                            Clasifique el producto según su tipo o uso
                        </div>
                    </div>

                    <!-- Cantidad y estado -->
                    <div class="form-section-title">
                        <h3><i class="fas fa-cubes"></i> Cantidad y Estado</h3>
                    </div>

                    <div class="form-group">
                        <label for="cantidad" class="form-label">
                            <i class="fas fa-sort-numeric-up"></i>
                            Cantidad Inicial *
                        </label>
                        <div class="quantity-input">
                            <button type="button" class="qty-btn minus" onclick="adjustQuantity(-1)">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input 
                                type="number" 
                                id="cantidad" 
                                name="cantidad" 
                                min="1" 
                                value="<?php echo isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 1; ?>"
                                required
                                class="qty-input"
                            >
                            <button type="button" class="qty-btn plus" onclick="adjustQuantity(1)">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="field-hint">
                            <i class="fas fa-info-circle"></i>
                            Cantidad inicial que se agregará al inventario
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="unidad_medida" class="form-label">
                            <i class="fas fa-balance-scale"></i>
                            Unidad de Medida *
                        </label>
                        <select id="unidad_medida" name="unidad_medida" required class="form-select">
                            <option value="">Seleccione una unidad</option>
                            <option value="Unidad" <?php echo (isset($_POST['unidad_medida']) && $_POST['unidad_medida'] == 'Unidad') ? 'selected' : ''; ?>>Unidad</option>
                            <option value="Par" <?php echo (isset($_POST['unidad_medida']) && $_POST['unidad_medida'] == 'Par') ? 'selected' : ''; ?>>Par</option>
                            <option value="Set" <?php echo (isset($_POST['unidad_medida']) && $_POST['unidad_medida'] == 'Set') ? 'selected' : ''; ?>>Set</option>
                            <option value="Caja" <?php echo (isset($_POST['unidad_medida']) && $_POST['unidad_medida'] == 'Caja') ? 'selected' : ''; ?>>Caja</option>
                            <option value="Paquete" <?php echo (isset($_POST['unidad_medida']) && $_POST['unidad_medida'] == 'Paquete') ? 'selected' : ''; ?>>Paquete</option>
                            <option value="Kilogramo" <?php echo (isset($_POST['unidad_medida']) && $_POST['unidad_medida'] == 'Kilogramo') ? 'selected' : ''; ?>>Kilogramo</option>
                            <option value="Metro" <?php echo (isset($_POST['unidad_medida']) && $_POST['unidad_medida'] == 'Metro') ? 'selected' : ''; ?>>Metro</option>
                            <option value="Litro" <?php echo (isset($_POST['unidad_medida']) && $_POST['unidad_medida'] == 'Litro') ? 'selected' : ''; ?>>Litro</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="estado" class="form-label">
                            <i class="fas fa-check-circle"></i>
                            Estado del Producto *
                        </label>
                        <select id="estado" name="estado" required class="form-select">
                            <option value="">Seleccione el estado</option>
                            <option value="Nuevo" <?php echo (isset($_POST['estado']) && $_POST['estado'] == 'Nuevo') ? 'selected' : ''; ?>>Nuevo</option>
                            <option value="Usado" <?php echo (isset($_POST['estado']) && $_POST['estado'] == 'Usado') ? 'selected' : ''; ?>>Usado</option>
                            <option value="Renovado" <?php echo (isset($_POST['estado']) && $_POST['estado'] == 'Renovado') ? 'selected' : ''; ?>>Renovado</option>
                            <option value="Dañado" <?php echo (isset($_POST['estado']) && $_POST['estado'] == 'Dañado') ? 'selected' : ''; ?>>Dañado</option>
                        </select>
                    </div>

                    <!-- Observaciones -->
                    <div class="form-section-title">
                        <h3><i class="fas fa-comment-alt"></i> Información Adicional</h3>
                    </div>

                    <div class="form-group full-width">
                        <label for="observaciones" class="form-label">
                            <i class="fas fa-sticky-note"></i>
                            Observaciones
                        </label>
                        <textarea 
                            id="observaciones" 
                            name="observaciones" 
                            rows="4"
                            placeholder="Información adicional sobre el producto (características especiales, instrucciones, etc.)"
                            maxlength="500"
                            class="form-textarea"
                        ><?php echo isset($_POST['observaciones']) ? htmlspecialchars($_POST['observaciones']) : ''; ?></textarea>
                        <div class="character-counter">
                            <span id="charCount">0</span>/500 caracteres
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit" id="btnRegistrar">
                        <i class="fas fa-save"></i>
                        Registrar Producto
                    </button>
                    
                    <button type="button" class="btn-reset" onclick="limpiarFormulario()">
                        <i class="fas fa-undo"></i>
                        Limpiar Formulario
                    </button>
                    
                    <a href="listar.php" class="btn-cancel">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </a>
                </div>
            </form>
        </div>

        <!-- Panel de ayuda -->
        <aside class="help-panel">
            <div class="help-header">
                <h3>
                    <i class="fas fa-question-circle"></i>
                    Ayuda para Registro
                </h3>
            </div>
            <div class="help-content">
                <div class="help-item">
                    <h4><i class="fas fa-lightbulb"></i> Consejos</h4>
                    <ul>
                        <li>Use nombres descriptivos y únicos</li>
                        <li>Incluya modelo y color cuando sea relevante</li>
                        <li>Verifique la categoría antes de guardar</li>
                        <li>Agregue observaciones para características especiales</li>
                    </ul>
                </div>
                
                <div class="help-item">
                    <h4><i class="fas fa-exclamation-triangle"></i> Campos Obligatorios</h4>
                    <ul>
                        <li>Nombre del producto</li>
                        <li>Almacén de destino</li>
                        <li>Categoría</li>
                        <li>Cantidad inicial</li>
                        <li>Unidad de medida</li>
                        <li>Estado del producto</li>
                    </ul>
                </div>
                
                <div class="help-item">
                    <h4><i class="fas fa-keyboard"></i> Atajos de Teclado</h4>
                    <ul>
                        <li><kbd>Ctrl + S</kbd> - Guardar producto</li>
                        <li><kbd>Ctrl + R</kbd> - Limpiar formulario</li>
                        <li><kbd>Esc</kbd> - Cancelar</li>
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