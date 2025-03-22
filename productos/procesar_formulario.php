<?php
session_start();
require_once "../config/database.php";

// Inicializar la respuesta
$response = ['success' => false, 'message' => ''];

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    $response['message'] = 'No has iniciado sesión';
    echo json_encode($response);
    exit();
}

// Procesar los datos enviados por POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validar datos recibidos
    if (!isset($_POST['producto_id'], $_POST['almacen_origen'], $_POST['almacen_destino'], $_POST['cantidad']) ||
        !filter_var($_POST['producto_id'], FILTER_VALIDATE_INT) || 
        !filter_var($_POST['almacen_origen'], FILTER_VALIDATE_INT) ||
        !filter_var($_POST['almacen_destino'], FILTER_VALIDATE_INT) ||
        !filter_var($_POST['cantidad'], FILTER_VALIDATE_INT)) {
        
        $response['message'] = 'Datos no válidos';
        echo json_encode($response);
        exit();
    }
    
    $producto_id = $_POST['producto_id'];
    $almacen_origen = $_POST['almacen_origen'];
    $almacen_destino = $_POST['almacen_destino'];
    $cantidad = $_POST['cantidad'];
    
    // Validar que la cantidad sea mayor que cero
    if ($cantidad <= 0) {
        $response['message'] = 'La cantidad debe ser mayor a 0';
        echo json_encode($response);
        exit();
    }
    
    // Verificar que haya suficiente stock
    $sql_check = "SELECT cantidad FROM productos WHERE id = ? AND almacen_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ii", $producto_id, $almacen_origen);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    
    if ($result->num_rows == 0) {
        $response['message'] = 'Producto no encontrado';
        echo json_encode($response);
        exit();
    }
    
    $stock = $result->fetch_assoc()['cantidad'];
    
    if ($cantidad > $stock) {
        $response['message'] = 'No hay suficiente stock disponible';
        echo json_encode($response);
        exit();
    }
    
    // Obtener el nombre del almacén de destino
    $sql_almacen = "SELECT nombre FROM almacenes WHERE id = ?";
    $stmt_almacen = $conn->prepare($sql_almacen);
    $stmt_almacen->bind_param("i", $almacen_destino);
    $stmt_almacen->execute();
    $result_almacen = $stmt_almacen->get_result();
    
    if ($result_almacen->num_rows == 0) {
        $response['message'] = 'Almacén de destino no encontrado';
        echo json_encode($response);
        exit();
    }
    
    $nombre_almacen = $result_almacen->fetch_assoc()['nombre'];
    
    // Iniciar transacción para asegurar la integridad de los datos
    $conn->begin_transaction();
    
    try {
        // Reducir la cantidad en el almacén de origen
        $sql_update = "UPDATE productos SET cantidad = cantidad - ? WHERE id = ? AND almacen_id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("iii", $cantidad, $producto_id, $almacen_origen);
        $stmt_update->execute();
        
        // Obtener los detalles del producto para usar en la inserción o actualización
        $sql_get_product = "SELECT * FROM productos WHERE id = ? AND almacen_id = ?";
        $stmt_get_product = $conn->prepare($sql_get_product);
        $stmt_get_product->bind_param("ii", $producto_id, $almacen_origen);
        $stmt_get_product->execute();
        $product_details = $stmt_get_product->get_result()->fetch_assoc();
        
        // Registrar la transferencia como pendiente
        $fecha_actual = date('Y-m-d H:i:s');
        $sql_log = "INSERT INTO solicitudes_transferencia (producto_id, almacen_origen, almacen_destino, cantidad, fecha_solicitud, estado, usuario_id) 
                    VALUES (?, ?, ?, ?, ?, 'pendiente', ?)";
        $stmt_log = $conn->prepare($sql_log);
        $stmt_log->bind_param("iiiisi", $producto_id, $almacen_origen, $almacen_destino, $cantidad, $fecha_actual, $_SESSION['user_id']);
        $stmt_log->execute();
        
        // Confirmar transacción
        $conn->commit();
        
        $response['success'] = true;
        $response['message'] = "Producto enviado con éxito al almacén $nombre_almacen";
        echo json_encode($response);
        
    } catch (Exception $e) {
        // Revertir cambios en caso de error
        $conn->rollback();
        $response['message'] = 'Error: ' . $e->getMessage();
        echo json_encode($response);
    }
} else {
    $response['message'] = 'Método no permitido';
    echo json_encode($response);
}
?>