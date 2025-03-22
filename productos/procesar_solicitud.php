<?php
session_start();
require_once "../config/database.php";

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

// Verificar que se recibieron los parámetros necesarios
if (!isset($_GET['id'], $_GET['accion'])) {
    $_SESSION['error'] = "Error: Faltan parámetros para procesar la solicitud.";
    header("Location: pendientes.php");
    exit();
}

$solicitud_id = intval($_GET['id']);
$accion = $_GET['accion'];

// Validar la acción
if ($accion !== "aprobar" && $accion !== "rechazar") {
    $_SESSION['error'] = "Error: Acción no válida.";
    header("Location: pendientes.php");
    exit();
}

// Obtener información de la solicitud
$sql_solicitud = "SELECT producto_id, almacen_origen, almacen_destino, cantidad 
                FROM solicitudes_transferencia 
                WHERE id = ? AND estado = 'pendiente'";
$stmt_solicitud = $conn->prepare($sql_solicitud);
$stmt_solicitud->bind_param("i", $solicitud_id);
$stmt_solicitud->execute();
$result_solicitud = $stmt_solicitud->get_result();

if ($result_solicitud->num_rows === 0) {
    $_SESSION['error'] = "Error: La solicitud no existe o ya ha sido procesada.";
    header("Location: pendientes.php");
    exit();
}

$solicitud = $result_solicitud->fetch_assoc();
$stmt_solicitud->close();

// Comenzar transacción
$conn->begin_transaction();

try {
    // Si se aprueba, realizar la transferencia
    if ($accion === "aprobar") {
        // Verificar stock actual en almacén origen
        $sql_verificar = "SELECT cantidad, categoria_id, nombre FROM productos WHERE id = ? AND almacen_id = ?";
        $stmt_verificar = $conn->prepare($sql_verificar);
        $stmt_verificar->bind_param("ii", $solicitud['producto_id'], $solicitud['almacen_origen']);
        $stmt_verificar->execute();
        $result_verificar = $stmt_verificar->get_result();
        
        if ($result_verificar->num_rows === 0) {
            throw new Exception("El producto ya no existe en el almacén origen.");
        }
        
        $producto_origen = $result_verificar->fetch_assoc();
        $stmt_verificar->close();
        
        // Verificar si hay suficiente stock
        if ($producto_origen['cantidad'] < $solicitud['cantidad']) {
            throw new Exception("No hay suficiente stock en el almacén origen. Stock actual: " . $producto_origen['cantidad']);
        }
        
        // Reducir stock en almacén origen
        $sql_reducir = "UPDATE productos SET cantidad = cantidad - ? WHERE id = ? AND almacen_id = ?";
        $stmt_reducir = $conn->prepare($sql_reducir);
        $stmt_reducir->bind_param("iii", $solicitud['cantidad'], $solicitud['producto_id'], $solicitud['almacen_origen']);
        $stmt_reducir->execute();
        $stmt_reducir->close();
        
        // Verificar si el producto ya existe en el almacén destino
        $sql_existe = "SELECT id, cantidad FROM productos WHERE nombre = (SELECT nombre FROM productos WHERE id = ?) 
                     AND almacen_id = ?";
        $stmt_existe = $conn->prepare($sql_existe);
        $stmt_existe->bind_param("ii", $solicitud['producto_id'], $solicitud['almacen_destino']);
        $stmt_existe->execute();
        $result_existe = $stmt_existe->get_result();
        
        if ($result_existe->num_rows > 0) {
            // Actualizar cantidad si el producto ya existe
            $producto_destino = $result_existe->fetch_assoc();
            $sql_aumentar = "UPDATE productos SET cantidad = cantidad + ? WHERE id = ?";
            $stmt_aumentar = $conn->prepare($sql_aumentar);
            $stmt_aumentar->bind_param("ii", $solicitud['cantidad'], $producto_destino['id']);
            $stmt_aumentar->execute();
            $stmt_aumentar->close();
        } else {
            // Crear nuevo producto en almacén destino copiando propiedades del original
            $sql_obtener = "SELECT * FROM productos WHERE id = ?";
            $stmt_obtener = $conn->prepare($sql_obtener);
            $stmt_obtener->bind_param("i", $solicitud['producto_id']);
            $stmt_obtener->execute();
            $result_obtener = $stmt_obtener->get_result();
            $producto_original = $result_obtener->fetch_assoc();
            $stmt_obtener->close();
            
            $sql_insertar = "INSERT INTO productos (nombre, modelo, color, talla_dimensiones, cantidad, 
                           unidad_medida, estado, observaciones, categoria_id, almacen_id) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_insertar = $conn->prepare($sql_insertar);
            $stmt_insertar->bind_param("ssssssssii", 
                $producto_original['nombre'],
                $producto_original['modelo'],
                $producto_original['color'],
                $producto_original['talla_dimensiones'],
                $solicitud['cantidad'],
                $producto_original['unidad_medida'],
                $producto_original['estado'],
                $producto_original['observaciones'],
                $producto_original['categoria_id'],
                $solicitud['almacen_destino']
            );
            $stmt_insertar->execute();
            $stmt_insertar->close();
        }
        
        // Registrar el movimiento en la tabla de movimientos
        $tipo = 'transferencia';
        $estado = 'completado';
        $usuario_id = $_SESSION["user_id"];
        
        $sql_movimiento = "INSERT INTO movimientos (producto_id, almacen_origen, almacen_destino, cantidad, tipo, usuario_id, estado)
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_movimiento = $conn->prepare($sql_movimiento);
        $stmt_movimiento->bind_param("iiiisss", 
            $solicitud['producto_id'], 
            $solicitud['almacen_origen'], 
            $solicitud['almacen_destino'], 
            $solicitud['cantidad'], 
            $tipo, 
            $usuario_id, 
            $estado
        );
        $stmt_movimiento->execute();
        $stmt_movimiento->close();
    }
    
    // Actualizar estado de la solicitud
    $nuevo_estado = ($accion === "aprobar") ? "aprobada" : "rechazada";
    $sql_actualizar = "UPDATE solicitudes_transferencia SET estado = ?, fecha_procesamiento = NOW() WHERE id = ?";
    $stmt_actualizar = $conn->prepare($sql_actualizar);
    $stmt_actualizar->bind_param("si", $nuevo_estado, $solicitud_id);
    $stmt_actualizar->execute();
    $stmt_actualizar->close();
    
    // Confirmar la transacción
    $conn->commit();
    
    $_SESSION['success'] = "La solicitud ha sido " . ($accion === "aprobar" ? "aprobada" : "rechazada") . " correctamente.";
} catch (Exception $e) {
    // Revertir cambios en caso de error
    $conn->rollback();
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

header("Location: pendientes.php");
exit();
?>