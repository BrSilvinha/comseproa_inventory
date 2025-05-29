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

// Función para validar datos de entrada
function validarDatosEntrada($datos) {
    $errores = [];
    
    if (!$datos['producto_id'] || $datos['producto_id'] <= 0) {
        $errores[] = 'ID de producto no válido.';
    }
    
    if (!$datos['almacen_origen'] || $datos['almacen_origen'] <= 0) {
        $errores[] = 'Almacén de origen no válido.';
    }
    
    if (!$datos['almacen_destino'] || $datos['almacen_destino'] <= 0) {
        $errores[] = 'Debe seleccionar un almacén de destino válido.';
    }
    
    if (!$datos['cantidad'] || $datos['cantidad'] <= 0) {
        $errores[] = 'La cantidad debe ser mayor a 0.';
    }
    
    if ($datos['cantidad'] > 999999) {
        $errores[] = 'La cantidad no puede exceder 999,999 unidades.';
    }
    
    if ($datos['almacen_origen'] === $datos['almacen_destino']) {
        $errores[] = 'El almacén de origen y destino no pueden ser el mismo.';
    }
    
    return $errores;
}

// Función para validar permisos del usuario
function validarPermisos($usuario_rol, $usuario_almacen_id, $almacen_origen) {
    // Si no es admin, verificar que solo pueda transferir desde su almacén
    if ($usuario_rol !== 'admin' && $usuario_almacen_id != $almacen_origen) {
        return 'No tiene permisos para transferir productos desde este almacén.';
    }
    return null;
}

// Función para registrar movimiento
function registrarMovimiento($conn, $datos, $usuario_id, $descripcion) {
    try {
        $sql_movimiento = "INSERT INTO movimientos 
                           (producto_id, almacen_origen, almacen_destino, cantidad, tipo, usuario_id, estado, descripcion, fecha_movimiento) 
                           VALUES (?, ?, ?, ?, 'transferencia', ?, 'pendiente', ?, NOW())";
        $stmt = $conn->prepare($sql_movimiento);
        $stmt->bind_param("iiiiss", 
            $datos['producto_id'], 
            $datos['almacen_origen'], 
            $datos['almacen_destino'], 
            $datos['cantidad'], 
            $usuario_id, 
            $descripcion
        );
        
        $resultado = $stmt->execute();
        $stmt->close();
        
        return $resultado;
    } catch (Exception $e) {
        error_log("Error al registrar movimiento: " . $e->getMessage());
        return false;
    }
}

// Función para registrar en log de actividad
function registrarLogActividad($conn, $usuario_id, $detalle) {
    try {
        $sql_log = "INSERT INTO logs_actividad (usuario_id, accion, detalle, ip_address, user_agent, fecha_accion) 
                    VALUES (?, 'SOLICITAR_TRANSFERENCIA', ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql_log);
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido', 0, 255);
        
        $stmt->bind_param("isss", $usuario_id, $detalle, $ip_address, $user_agent);
        $resultado = $stmt->execute();
        $stmt->close();
        
        return $resultado;
    } catch (Exception $e) {
        error_log("Error al registrar log de actividad: " . $e->getMessage());
        return false;
    }
}

// Función para auto-aprobar transferencias (solo para admins)
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
            
            if (!$stmt_update->execute()) {
                $stmt_update->close();
                $stmt->close();
                return ['success' => false, 'error' => 'Error al actualizar cantidad en destino'];
            }
            $stmt_update->close();
        } else {
            // Producto no existe, crear nuevo
            $sql_create = "INSERT INTO productos 
                          (nombre, modelo, color, talla_dimensiones, cantidad, unidad_medida, estado, observaciones, categoria_id, almacen_id, fecha_registro) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
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
            
            if (!$stmt_create->execute()) {
                $stmt_create->close();
                $stmt->close();
                return ['success' => false, 'error' => 'Error al crear producto en destino'];
            }
            $stmt_create->close();
        }
        $stmt->close();
        
        // Actualizar estado de la solicitud
        $sql_aprobar = "UPDATE solicitudes_transferencia 
                        SET estado = 'aprobada', fecha_procesamiento = NOW(), procesado_por = ? 
                        WHERE id = ?";
        $stmt_aprobar = $conn->prepare($sql_aprobar);
        $stmt_aprobar->bind_param("ii", $usuario_id, $solicitud_id);
        
        if (!$stmt_aprobar->execute()) {
            $stmt_aprobar->close();
            return ['success' => false, 'error' => 'Error al actualizar estado de solicitud'];
        }
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

// Variable para controlar si estamos en una transacción
$en_transaccion = false;

try {
    // Obtener y validar datos del formulario
    $datos = [
        'producto_id' => limpiarDato($_POST['producto_id'] ?? '', 'int'),
        'almacen_origen' => limpiarDato($_POST['almacen_origen'] ?? '', 'int'),
        'almacen_destino' => limpiarDato($_POST['almacen_destino'] ?? '', 'int'),
        'cantidad' => limpiarDato($_POST['cantidad'] ?? '', 'int')
    ];
    
    // Validaciones básicas
    $errores = validarDatosEntrada($datos);
    if (!empty($errores)) {
        http_response_code(400);
        enviarRespuesta(false, implode(' ', $errores));
    }
    
    // Validar permisos del usuario
    $usuario_rol = $_SESSION["user_role"] ?? "usuario";
    $usuario_almacen_id = $_SESSION["almacen_id"] ?? null;
    
    $error_permisos = validarPermisos($usuario_rol, $usuario_almacen_id, $datos['almacen_origen']);
    if ($error_permisos) {
        http_response_code(403);
        enviarRespuesta(false, $error_permisos);
    }
    
    // Iniciar transacción
    $conn->begin_transaction();
    $en_transaccion = true;
    
    // Obtener información completa del producto con bloqueo para evitar condiciones de carrera
    $sql_producto = "SELECT p.*, c.nombre as categoria_nombre, a.nombre as almacen_nombre 
                     FROM productos p 
                     JOIN categorias c ON p.categoria_id = c.id 
                     JOIN almacenes a ON p.almacen_id = a.id 
                     WHERE p.id = ? AND p.almacen_id = ? FOR UPDATE";
    $stmt = $conn->prepare($sql_producto);
    $stmt->bind_param("ii", $datos['producto_id'], $datos['almacen_origen']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->rollback();
        $en_transaccion = false;
        http_response_code(404);
        enviarRespuesta(false, 'Producto no encontrado en el almacén de origen.');
    }

    $producto = $result->fetch_assoc();
    $cantidad_actual = (int)$producto['cantidad'];
    $stmt->close();

    // Verificar stock suficiente
    if ($datos['cantidad'] > $cantidad_actual) {
        $conn->rollback();
        $en_transaccion = false;
        http_response_code(400);
        enviarRespuesta(false, "Stock insuficiente. Disponible: {$cantidad_actual} unidades, solicitado: {$datos['cantidad']} unidades.");
    }
    
    // Verificar que el almacén de destino existe
    $sql_almacen_destino = "SELECT id, nombre FROM almacenes WHERE id = ?";
    $stmt = $conn->prepare($sql_almacen_destino);
    $stmt->bind_param("i", $datos['almacen_destino']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->rollback();
        $en_transaccion = false;
        http_response_code(404);
        enviarRespuesta(false, 'Almacén de destino no encontrado.');
    }
    
    $almacen_destino_info = $result->fetch_assoc();
    $stmt->close();
    
    // Reducir stock en el almacén de origen
    $sql_reducir = "UPDATE productos SET cantidad = cantidad - ? WHERE id = ? AND almacen_id = ?";
    $stmt = $conn->prepare($sql_reducir);
    $stmt->bind_param("iii", $datos['cantidad'], $datos['producto_id'], $datos['almacen_origen']);
    
    if (!$stmt->execute() || $stmt->affected_rows === 0) {
        $stmt->close();
        $conn->rollback();
        $en_transaccion = false;
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
        $datos['producto_id'], 
        $datos['almacen_origen'], 
        $datos['almacen_destino'], 
        $datos['cantidad'], 
        $fecha_actual, 
        $usuario_id, 
        $observaciones
    );
    
    if (!$stmt->execute()) {
        $stmt->close();
        $conn->rollback();
        $en_transaccion = false;
        enviarRespuesta(false, 'Error al crear la solicitud de transferencia.');
    }
    
    $solicitud_id = $conn->insert_id;
    $stmt->close();
    
    // Registrar movimiento
    $descripcion = "Solicitud de transferencia #{$solicitud_id}: {$datos['cantidad']} unidades de {$producto['nombre']} a {$almacen_destino_info['nombre']}";
    if (!registrarMovimiento($conn, $datos, $usuario_id, $descripcion)) {
        // Log del error pero no cancelar la transacción
        error_log("Error al registrar movimiento para solicitud #{$solicitud_id}");
    }
    
    // Registrar en log de actividad
    $detalle = "Solicitó transferencia de {$datos['cantidad']} unidades de '{$producto['nombre']}' desde {$producto['almacen_nombre']} hacia {$almacen_destino_info['nombre']}";
    if (!registrarLogActividad($conn, $usuario_id, $detalle)) {
        error_log("Error al registrar log de actividad para solicitud #{$solicitud_id}");
    }
    
    // Determinar si auto-aprobar (solo para admins)
    $auto_aprobar = false;
    if ($usuario_rol === 'admin') {
        $auto_aprobar = true; // Los admins pueden auto-aprobar
    }
    
    if ($auto_aprobar) {
        // Lógica de auto-aprobación para admins
        $resultado_aprobacion = autoAprobarTransferencia($conn, $solicitud_id, $producto, $datos['cantidad'], $datos['almacen_destino'], $usuario_id);
        
        if ($resultado_aprobacion['success']) {
            $conn->commit();
            $en_transaccion = false;
            enviarRespuesta(true, "Transferencia completada exitosamente a {$almacen_destino_info['nombre']}", [
                'solicitud_id' => $solicitud_id,
                'estado' => 'completada',
                'auto_aprobada' => true,
                'almacen_destino' => $almacen_destino_info['nombre'],
                'cantidad_transferida' => $datos['cantidad'],
                'producto_nombre' => $producto['nombre']
            ]);
        } else {
            // Si falla la auto-aprobación, continuar como solicitud pendiente
            error_log("Error en auto-aprobación: " . ($resultado_aprobacion['error'] ?? 'Error desconocido'));
        }
    }
    
    // Confirmar transacción (solicitud pendiente)
    $conn->commit();
    $en_transaccion = false;
    
    enviarRespuesta(true, "Solicitud de transferencia enviada correctamente a {$almacen_destino_info['nombre']}", [
        'solicitud_id' => $solicitud_id,
        'estado' => 'pendiente',
        'almacen_destino' => $almacen_destino_info['nombre'],
        'cantidad_solicitada' => $datos['cantidad'],
        'producto_nombre' => $producto['nombre'],
        'mensaje_adicional' => 'La transferencia está pendiente de aprobación.'
    ]);
    
} catch (mysqli_sql_exception $e) {
    // Rollback en caso de error de base de datos
    if ($en_transaccion) {
        $conn->rollback();
    }
    
    // Log del error para debugging
    error_log("Error de base de datos en procesar_formulario.php: " . $e->getMessage());
    
    http_response_code(500);
    enviarRespuesta(false, 'Error interno del servidor. Por favor, inténtelo más tarde.');
    
} catch (Exception $e) {
    // Rollback en caso de cualquier otro error
    if ($en_transaccion) {
        $conn->rollback();
    }
    
    // Log del error
    error_log("Error general en procesar_formulario.php: " . $e->getMessage() . " | Usuario: " . ($_SESSION["user_id"] ?? 'desconocido'));
    
    http_response_code(500);
    enviarRespuesta(false, 'Error inesperado. Por favor, inténtelo más tarde.');
    
} finally {
    // Cerrar conexión si existe
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>