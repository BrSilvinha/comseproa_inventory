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

// Función para limpiar y validar datos de entrada
function limpiarDato($dato, $tipo = 'string') {
    switch ($tipo) {
        case 'int':
            return filter_var($dato, FILTER_VALIDATE_INT);
        case 'email':
            return filter_var($dato, FILTER_VALIDATE_EMAIL);
        case 'string':
        default:
            return trim(htmlspecialchars($dato, ENT_QUOTES, 'UTF-8'));
    }
}

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    enviarRespuesta(false, 'No autorizado. Debe iniciar sesión.');
}

// Evitar secuestro de sesión
session_regenerate_id(true);

// Verificar que sea una petición POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    enviarRespuesta(false, 'Método no permitido. Use POST.');
}

require_once "../config/database.php";

try {
    // Obtener y validar datos del formulario
    $producto_id = limpiarDato($_POST['producto_id'] ?? '', 'int');
    $almacen_origen = limpiarDato($_POST['almacen_origen'] ?? '', 'int');
    $almacen_destino = limpiarDato($_POST['almacen_destino'] ?? '', 'int');
    $cantidad = limpiarDato($_POST['cantidad'] ?? '', 'int');
    
    // Validaciones básicas
    if (!$producto_id || $producto_id <= 0) {
        http_response_code(400);
        enviarRespuesta(false, 'ID de producto no válido.');
    }
    
    if (!$almacen_origen || $almacen_origen <= 0) {
        http_response_code(400);
        enviarRespuesta(false, 'Almacén de origen no válido.');
    }
    
    if (!$almacen_destino || $almacen_destino <= 0) {
        http_response_code(400);
        enviarRespuesta(false, 'Debe seleccionar un almacén de destino válido.');
    }
    
    if (!$cantidad || $cantidad <= 0) {
        http_response_code(400);
        enviarRespuesta(false, 'La cantidad debe ser mayor a 0.');
    }
    
    if ($almacen_origen === $almacen_destino) {
        http_response_code(400);
        enviarRespuesta(false, 'El almacén de origen y destino no pueden ser el mismo.');
    }
    
    // Validar permisos del usuario
    $usuario_rol = $_SESSION["user_role"] ?? "usuario";
    $usuario_almacen_id = $_SESSION["almacen_id"] ?? null;
    
    // Si no es admin, verificar que solo pueda transferir desde su almacén
    if ($usuario_rol !== 'admin' && $usuario_almacen_id != $almacen_origen) {
        http_response_code(403);
        enviarRespuesta(false, 'No tiene permisos para transferir productos desde este almacén.');
    }
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Verificar que el producto existe y obtener información completa
    $sql_producto = "SELECT p.*, c.nombre as categoria_nombre, a.nombre as almacen_nombre 
                     FROM productos p 
                     JOIN categorias c ON p.categoria_id = c.id 
                     JOIN almacenes a ON p.almacen_id = a.id 
                     WHERE p.id = ? AND p.almacen_id = ? FOR UPDATE";
    $stmt = $conn->prepare($sql_producto);
    $stmt->bind_param("ii", $producto_id, $almacen_origen);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $conn->rollback();
        http_response_code(404);
        enviarRespuesta(false, 'Producto no encontrado en el almacén de origen.');
    }
    
    $producto = $result->fetch_assoc();
    $stmt->close();
    
    // Verificar stock suficiente
    if ($cantidad > $producto['cantidad']) {
        $conn->rollback();
        http_response_code(400);
        enviarRespuesta(false, "Stock insuficiente. Disponible: {$producto['cantidad']} unidades, solicitado: {$cantidad} unidades.");
    }
    
    // Verificar que el almacén de destino existe
    $sql_almacen_destino = "SELECT id, nombre FROM almacenes WHERE id = ?";
    $stmt = $conn->prepare($sql_almacen_destino);
    $stmt->bind_param("i", $almacen_destino);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $conn->rollback();
        http_response_code(404);
        enviarRespuesta(false, 'Almacén de destino no encontrado.');
    }
    
    $almacen_destino_info = $result->fetch_assoc();
    $stmt->close();
    
    // Reducir stock en el almacén de origen
    $sql_reducir = "UPDATE productos SET cantidad = cantidad - ? WHERE id = ? AND almacen_id = ?";
    $stmt = $conn->prepare($sql_reducir);
    $stmt->bind_param("iii", $cantidad, $producto_id, $almacen_origen);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        $conn->rollback();
        enviarRespuesta(false, 'Error al actualizar el stock en el almacén de origen.');
    }
    $stmt->close();
    
    // Crear solicitud de transferencia
    $fecha_actual = date('Y-m-d H:i:s');
    $usuario_id = $_SESSION['user_id'];
    $observaciones = "Transferencia solicitada desde el sistema web";
    
    $sql_solicitud = "INSERT INTO solicitudes_transferencia 
                      (producto_id, almacen_origen, almacen_destino, cantidad, fecha_solicitud, estado, usuario_id, observaciones) 
                      VALUES (?, ?, ?, ?, ?, 'pendiente', ?, ?)";
    $stmt = $conn->prepare($sql_solicitud);
    $stmt->bind_param("iiiissi", 
        $producto_id, 
        $almacen_origen, 
        $almacen_destino, 
        $cantidad, 
        $fecha_actual, 
        $usuario_id, 
        $observaciones
    );
    
    if (!$stmt->execute()) {
        $conn->rollback();
        enviarRespuesta(false, 'Error al crear la solicitud de transferencia.');
    }
    
    $solicitud_id = $conn->insert_id;
    $stmt->close();
    
    // Registrar movimiento
    $sql_movimiento = "INSERT INTO movimientos 
                       (producto_id, almacen_origen, almacen_destino, cantidad, tipo, usuario_id, estado, descripcion, fecha_movimiento) 
                       VALUES (?, ?, ?, ?, 'transferencia', ?, 'pendiente', ?, NOW())";
    $stmt = $conn->prepare($sql_movimiento);
    $descripcion = "Solicitud de transferencia #{$solicitud_id}: {$cantidad} unidades de {$producto['nombre']} a {$almacen_destino_info['nombre']}";
    $stmt->bind_param("iiiiss", 
        $producto_id, 
        $almacen_origen, 
        $almacen_destino, 
        $cantidad, 
        $usuario_id, 
        $descripcion
    );
    
    if (!$stmt->execute()) {
        // Log del error pero no cancelar la transacción
        error_log("Error al registrar movimiento: " . $stmt->error);
    }
    $stmt->close();
    
    // Registrar en log de actividad
    $sql_log = "INSERT INTO logs_actividad (usuario_id, accion, detalle, fecha_accion) 
                VALUES (?, 'SOLICITAR_TRANSFERENCIA', ?, NOW())";
    $stmt = $conn->prepare($sql_log);
    $detalle = "Solicitó transferencia de {$cantidad} unidades de '{$producto['nombre']}' desde {$producto['almacen_nombre']} hacia {$almacen_destino_info['nombre']}";
    $stmt->bind_param("is", $usuario_id, $detalle);
    
    if (!$stmt->execute()) {
        error_log("Error al registrar log de actividad: " . $stmt->error);
    }
    $stmt->close();
    
    // Si el usuario es admin y está transfiriendo a un almacén donde él tiene permisos,
    // podemos aprobar automáticamente la transferencia (opcional)
    $auto_aprobar = false;
    if ($usuario_rol === 'admin') {
        $auto_aprobar = true; // Los admins pueden auto-aprobar
    }
    
    if ($auto_aprobar) {
        // Lógica de auto-aprobación para admins
        $resultado_aprobacion = autoAprobarTransferencia($conn, $solicitud_id, $producto, $cantidad, $almacen_destino, $usuario_id);
        
        if ($resultado_aprobacion['success']) {
            $conn->commit();
            enviarRespuesta(true, "Transferencia completada exitosamente a {$almacen_destino_info['nombre']}", [
                'solicitud_id' => $solicitud_id,
                'estado' => 'completada',
                'auto_aprobada' => true,
                'almacen_destino' => $almacen_destino_info['nombre'],
                'cantidad_transferida' => $cantidad,
                'producto_nombre' => $producto['nombre']
            ]);
        }
    }
    
    // Confirmar transacción (solicitud pendiente)
    $conn->commit();
    
    enviarRespuesta(true, "Solicitud de transferencia enviada correctamente a {$almacen_destino_info['nombre']}", [
        'solicitud_id' => $solicitud_id,
        'estado' => 'pendiente',
        'almacen_destino' => $almacen_destino_info['nombre'],
        'cantidad_solicitada' => $cantidad,
        'producto_nombre' => $producto['nombre'],
        'mensaje_adicional' => 'La transferencia está pendiente de aprobación.'
    ]);
    
} catch (Exception $e) {
    // Rollback en caso de error
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    
    // Log del error
    error_log("Error en procesar_formulario.php: " . $e->getMessage() . " | Usuario: " . ($_SESSION["user_id"] ?? 'desconocido'));
    
    http_response_code(500);
    enviarRespuesta(false, 'Error interno del servidor. Por favor, inténtelo más tarde.');
    
} finally {
    // Cerrar conexión
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}

/**
 * Función para auto-aprobar transferencias (solo para admins)
 */
function autoAprobarTransferencia($conn, $solicitud_id, $producto, $cantidad, $almacen_destino, $usuario_id) {
    try {
        // Verificar si el producto ya existe en el almacén destino
        $sql_existe = "SELECT id, cantidad FROM productos 
                       WHERE nombre = ? AND categoria_id = ? AND almacen_id = ?";
        $stmt = $conn->prepare($sql_existe);
        $stmt->bind_param("sii", $producto['nombre'], $producto['categoria_id'], $almacen_destino);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Producto existe, actualizar cantidad
            $producto_destino = $result->fetch_assoc();
            $sql_update = "UPDATE productos SET cantidad = cantidad + ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("ii", $cantidad, $producto_destino['id']);
            $stmt_update->execute();
            $stmt_update->close();
        } else {
            // Producto no existe, crear nuevo
            $sql_create = "INSERT INTO productos 
                          (nombre, modelo, color, talla_dimensiones, cantidad, unidad_medida, estado, observaciones, categoria_id, almacen_id) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_create = $conn->prepare($sql_create);
            $stmt_create->bind_param("ssssssssii", 
                $producto['nombre'],
                $producto['modelo'],
                $producto['color'],
                $producto['talla_dimensiones'],
                $cantidad,
                $producto['unidad_medida'],
                $producto['estado'],
                $producto['observaciones'],
                $producto['categoria_id'],
                $almacen_destino
            );
            $stmt_create->execute();
            $stmt_create->close();
        }
        $stmt->close();
        
        // Actualizar estado de la solicitud
        $sql_aprobar = "UPDATE solicitudes_transferencia 
                        SET estado = 'aprobada', fecha_procesamiento = NOW(), usuario_aprobador = ? 
                        WHERE id = ?";
        $stmt_aprobar = $conn->prepare($sql_aprobar);
        $stmt_aprobar->bind_param("ii", $usuario_id, $solicitud_id);
        $stmt_aprobar->execute();
        $stmt_aprobar->close();
        
        // Actualizar estado del movimiento
        $sql_mov = "UPDATE movimientos SET estado = 'completado' 
                    WHERE producto_id = ? AND tipo = 'transferencia' AND estado = 'pendiente' 
                    ORDER BY fecha_movimiento DESC LIMIT 1";
        $stmt_mov = $conn->prepare($sql_mov);
        $stmt_mov->bind_param("i", $producto['id']);
        $stmt_mov->execute();
        $stmt_mov->close();
        
        return ['success' => true];
        
    } catch (Exception $e) {
        error_log("Error en auto-aprobación: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?>