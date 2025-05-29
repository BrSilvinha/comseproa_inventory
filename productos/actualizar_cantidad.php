<?php
session_start();

// Configurar cabeceras JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

// Función para enviar respuesta JSON y terminar
function sendJsonResponse($success, $message, $data = []) {
    $response = array_merge([
        'success' => $success,
        'message' => $message,
        'timestamp' => time()
    ], $data);
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    sendJsonResponse(false, 'Sesión expirada. Por favor, inicie sesión nuevamente.');
}

// Verificar si la solicitud es POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    sendJsonResponse(false, 'Método no permitido. Solo se acepta POST.');
}

// Verificar permisos del usuario
$usuario_rol = $_SESSION["user_role"] ?? "usuario";
if ($usuario_rol !== 'admin') {
    http_response_code(403);
    sendJsonResponse(false, 'No tiene permisos para modificar cantidades.');
}

// Verificar datos requeridos
if (!isset($_POST['producto_id'], $_POST['accion'])) {
    http_response_code(400);
    sendJsonResponse(false, 'Faltan parámetros requeridos (producto_id, accion).');
}

$producto_id = filter_var($_POST['producto_id'], FILTER_VALIDATE_INT);
$accion = trim($_POST['accion']);

// Validar producto_id
if (!$producto_id || $producto_id <= 0) {
    http_response_code(400);
    sendJsonResponse(false, 'ID de producto no válido.');
}

// Validar acción
if (!in_array($accion, ['sumar', 'restar'])) {
    http_response_code(400);
    sendJsonResponse(false, 'Acción no válida. Solo se permite "sumar" o "restar".');
}

require_once "../config/database.php";

try {
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Obtener información actual del producto con bloqueo para evitar condiciones de carrera
    $sql_producto = "SELECT p.id, p.nombre, p.cantidad, p.almacen_id, a.nombre as almacen_nombre 
                     FROM productos p 
                     JOIN almacenes a ON p.almacen_id = a.id 
                     WHERE p.id = ? FOR UPDATE";
    $stmt_producto = $conn->prepare($sql_producto);
    $stmt_producto->bind_param("i", $producto_id);
    $stmt_producto->execute();
    $result = $stmt_producto->get_result();

    if ($result->num_rows === 0) {
        $conn->rollback();
        http_response_code(404);
        sendJsonResponse(false, 'Producto no encontrado.');
    }

    $producto = $result->fetch_assoc();
    $cantidad_actual = (int)$producto['cantidad'];
    $almacen_id = (int)$producto['almacen_id'];
    $stmt_producto->close();

    // Calcular nueva cantidad
    $nueva_cantidad = $cantidad_actual;
    $cambio_cantidad = 1;
    
    if ($accion === 'sumar') {
        $nueva_cantidad = $cantidad_actual + 1;
        $tipo_movimiento = 'entrada';
    } elseif ($accion === 'restar') {
        if ($cantidad_actual <= 0) {
            $conn->rollback();
            sendJsonResponse(false, 'No se puede reducir la cantidad. El stock ya es 0.');
        }
        $nueva_cantidad = $cantidad_actual - 1;
        $tipo_movimiento = 'salida';
    }

    // Validar límites
    if ($nueva_cantidad < 0) {
        $conn->rollback();
        sendJsonResponse(false, 'La cantidad no puede ser negativa.');
    }
    
    if ($nueva_cantidad > 999999) {
        $conn->rollback();
        sendJsonResponse(false, 'La cantidad no puede exceder 999,999 unidades.');
    }

    // Actualizar la cantidad en la base de datos
    $sql_update = "UPDATE productos SET cantidad = ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ii", $nueva_cantidad, $producto_id);
    
    if (!$stmt_update->execute()) {
        $conn->rollback();
        http_response_code(500);
        sendJsonResponse(false, 'Error al actualizar la cantidad en la base de datos.');
    }
    
    $stmt_update->close();

    // Registrar el movimiento en el historial (SIMPLIFICADO)
    $usuario_id = $_SESSION["user_id"];
    $descripcion = "Ajuste manual de cantidad: {$accion} 1 unidad";
    
    // Usar solo las columnas que existen
    $sql_movimiento = "INSERT INTO movimientos (producto_id, almacen_origen, cantidad, tipo, usuario_id, estado, descripcion) 
                       VALUES (?, ?, ?, ?, ?, 'completado', ?)";
    $stmt_movimiento = $conn->prepare($sql_movimiento);
    $stmt_movimiento->bind_param("iiiiss", $producto_id, $almacen_id, $cambio_cantidad, $tipo_movimiento, $usuario_id, $descripcion);
    
    if (!$stmt_movimiento->execute()) {
        // No es crítico si falla el log, pero lo registramos
        error_log("Error al registrar movimiento para producto {$producto_id}: " . $stmt_movimiento->error);
    }
    
    $stmt_movimiento->close();

    // Opcional: Registrar en log de actividad para auditoría (SIMPLIFICADO)
    try {
        $sql_log = "INSERT INTO logs_actividad (usuario_id, accion, detalle, fecha_accion) 
                    VALUES (?, 'ACTUALIZAR_STOCK', ?, NOW())";
        $stmt_log = $conn->prepare($sql_log);
        $detalle = "Actualizó stock del producto '{$producto['nombre']}' de {$cantidad_actual} a {$nueva_cantidad} unidades";
        
        $stmt_log->bind_param("is", $usuario_id, $detalle);
        $stmt_log->execute();
        $stmt_log->close();
    } catch (Exception $e) {
        // Si la tabla logs_actividad no existe, no es crítico
        error_log("Log de actividad no disponible: " . $e->getMessage());
    }

    // Confirmar transacción
    $conn->commit();
    
    // Determinar estado del stock para respuesta
    $estado_stock = 'normal';
    if ($nueva_cantidad < 5) {
        $estado_stock = 'critico';
    } elseif ($nueva_cantidad < 10) {
        $estado_stock = 'bajo';
    }
    
    // Respuesta exitosa con información adicional
    sendJsonResponse(true, 'Cantidad actualizada correctamente', [
        'nueva_cantidad' => $nueva_cantidad,
        'cantidad_anterior' => $cantidad_actual,
        'cambio' => $accion === 'sumar' ? '+1' : '-1',
        'estado_stock' => $estado_stock,
        'producto_nombre' => $producto['nombre'],
        'almacen_nombre' => $producto['almacen_nombre'],
        'puede_restar' => $nueva_cantidad > 0
    ]);

} catch (mysqli_sql_exception $e) {
    // Rollback en caso de error de base de datos
    $conn->rollback();
    
    // Log del error para debugging
    error_log("Error de base de datos en actualizar_cantidad.php: " . $e->getMessage());
    
    http_response_code(500);
    sendJsonResponse(false, 'Error interno del servidor. Por favor, inténtelo más tarde.');
    
} catch (Exception $e) {
    // Rollback en caso de cualquier otro error
    $conn->rollback();
    
    // Log del error
    error_log("Error general en actualizar_cantidad.php: " . $e->getMessage());
    
    http_response_code(500);
    sendJsonResponse(false, 'Error inesperado. Por favor, inténtelo más tarde.');
    
} finally {
    // Cerrar conexión si existe
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>