<?php
session_start();

// Configurar cabeceras para JSON y evitar cache
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Función para enviar respuesta JSON y terminar ejecución
function enviarRespuesta($success, $message, $data = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $data));
    exit();
}

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    enviarRespuesta(false, 'No autorizado. Debe iniciar sesión.');
}

// Evitar secuestro de sesión
session_regenerate_id(true);

// Verificar que el usuario sea administrador
$usuario_rol = $_SESSION["user_role"] ?? "usuario";
if ($usuario_rol !== 'admin') {
    http_response_code(403);
    enviarRespuesta(false, 'No tiene permisos para actualizar cantidades de productos.');
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    enviarRespuesta(false, 'Método no permitido. Use POST.');
}

// Obtener y validar datos de entrada
$producto_id = filter_input(INPUT_POST, 'producto_id', FILTER_VALIDATE_INT);
$accion = filter_input(INPUT_POST, 'accion', FILTER_SANITIZE_STRING);
$almacen_id = filter_input(INPUT_POST, 'almacen_id', FILTER_VALIDATE_INT);

// Validar datos requeridos
if (!$producto_id || $producto_id <= 0) {
    http_response_code(400);
    enviarRespuesta(false, 'ID de producto no válido.');
}

if (!in_array($accion, ['sumar', 'restar'])) {
    http_response_code(400);
    enviarRespuesta(false, 'Acción no válida. Use "sumar" o "restar".');
}

require_once "../config/database.php";

try {
    // Iniciar transacción para asegurar consistencia
    $conn->begin_transaction();
    
    // Obtener información actual del producto con bloqueo para evitar condiciones de carrera
    $sql_producto = "SELECT p.id, p.nombre, p.cantidad, p.almacen_id, a.nombre as almacen_nombre 
                     FROM productos p 
                     JOIN almacenes a ON p.almacen_id = a.id 
                     WHERE p.id = ? FOR UPDATE";
    $stmt = $conn->prepare($sql_producto);
    $stmt->bind_param("i", $producto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $conn->rollback();
        http_response_code(404);
        enviarRespuesta(false, 'Producto no encontrado.');
    }
    
    $producto = $result->fetch_assoc();
    $cantidad_actual = (int)$producto['cantidad'];
    $almacen_producto = (int)$producto['almacen_id'];
    $stmt->close();
    
    // Si se proporciona almacen_id, verificar que coincida
    if ($almacen_id && $almacen_id !== $almacen_producto) {
        $conn->rollback();
        http_response_code(400);
        enviarRespuesta(false, 'El producto no pertenece al almacén especificado.');
    }
    
    // Calcular nueva cantidad
    $nueva_cantidad = $cantidad_actual;
    $cambio = 0;
    
    if ($accion === 'sumar') {
        $nueva_cantidad = $cantidad_actual + 1;
        $cambio = 1;
    } elseif ($accion === 'restar') {
        if ($cantidad_actual <= 0) {
            $conn->rollback();
            enviarRespuesta(false, 'No se puede reducir más el stock. La cantidad actual es 0.');
        }
        $nueva_cantidad = $cantidad_actual - 1;
        $cambio = -1;
    }
    
    // Validar límites razonables
    if ($nueva_cantidad < 0) {
        $conn->rollback();
        enviarRespuesta(false, 'La cantidad no puede ser negativa.');
    }
    
    if ($nueva_cantidad > 99999) {
        $conn->rollback();
        enviarRespuesta(false, 'La cantidad no puede exceder 99,999 unidades.');
    }
    
    // Actualizar la cantidad en la base de datos
    $sql_update = "UPDATE productos SET cantidad = ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ii", $nueva_cantidad, $producto_id);
    
    if (!$stmt_update->execute()) {
        $conn->rollback();
        enviarRespuesta(false, 'Error al actualizar la cantidad en la base de datos.');
    }
    
    $filas_afectadas = $stmt_update->affected_rows;
    $stmt_update->close();
    
    if ($filas_afectadas === 0) {
        $conn->rollback();
        enviarRespuesta(false, 'No se pudo actualizar la cantidad del producto.');
    }
    
    // Registrar el movimiento en el historial
    $usuario_id = $_SESSION["user_id"];
    $tipo_movimiento = ($accion === 'sumar') ? 'entrada' : 'salida';
    $descripcion = "Ajuste manual de inventario: {$accion} 1 unidad";
    
    $sql_movimiento = "INSERT INTO movimientos (producto_id, almacen_origen, cantidad, tipo, usuario_id, estado, descripcion, fecha_movimiento) 
                       VALUES (?, ?, ?, ?, ?, 'completado', ?, NOW())";
    $stmt_movimiento = $conn->prepare($sql_movimiento);
    $stmt_movimiento->bind_param("iiisis", 
        $producto_id, 
        $almacen_producto, 
        abs($cambio), 
        $tipo_movimiento, 
        $usuario_id, 
        $descripcion
    );
    
    if (!$stmt_movimiento->execute()) {
        // Log del error pero no cancelar la transacción por esto
        error_log("Error al registrar movimiento: " . $stmt_movimiento->error);
    }
    $stmt_movimiento->close();
    
    // Registrar en log de actividad
    $sql_log = "INSERT INTO logs_actividad (usuario_id, accion, detalle, fecha_accion) 
                VALUES (?, 'ACTUALIZAR_CANTIDAD', ?, NOW())";
    $stmt_log = $conn->prepare($sql_log);
    $detalle = "Actualizó cantidad del producto '{$producto['nombre']}' de {$cantidad_actual} a {$nueva_cantidad} en {$producto['almacen_nombre']}";
    $stmt_log->bind_param("is", $usuario_id, $detalle);
    
    if (!$stmt_log->execute()) {
        // Log del error pero continuar
        error_log("Error al registrar log de actividad: " . $stmt_log->error);
    }
    $stmt_log->close();
    
    // Confirmar transacción
    $conn->commit();
    
    // Preparar respuesta exitosa
    $mensaje = "Stock actualizado correctamente";
    if ($accion === 'sumar') {
        $mensaje .= ": +1 unidad";
    } else {
        $mensaje .= ": -1 unidad";
    }
    
    // Determinar estado del stock
    $estado_stock = 'good';
    if ($nueva_cantidad < 5) {
        $estado_stock = 'critical';
    } elseif ($nueva_cantidad < 10) {
        $estado_stock = 'warning';
    }
    
    enviarRespuesta(true, $mensaje, [
        'nueva_cantidad' => $nueva_cantidad,
        'cantidad_anterior' => $cantidad_actual,
        'cambio' => $cambio,
        'estado_stock' => $estado_stock,
        'producto_id' => $producto_id,
        'producto_nombre' => $producto['nombre'],
        'almacen_nombre' => $producto['almacen_nombre']
    ]);
    
} catch (Exception $e) {
    // Rollback en caso de error
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    
    // Log del error detallado
    error_log("Error en actualizar_cantidad.php: " . $e->getMessage() . " | Producto ID: {$producto_id} | Usuario: " . $_SESSION["user_id"]);
    
    http_response_code(500);
    enviarRespuesta(false, 'Error interno del servidor. Por favor, inténtelo más tarde.');
    
} finally {
    // Cerrar conexión si existe
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>