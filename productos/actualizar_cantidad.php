<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Verificar si la solicitud es POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Verificar datos requeridos
if (!isset($_POST['producto_id'], $_POST['accion']) || 
    !filter_var($_POST['producto_id'], FILTER_VALIDATE_INT)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Datos no válidos']);
    exit();
}

require_once "../config/database.php";

$producto_id = intval($_POST['producto_id']);
$accion = $_POST['accion'];

// Obtener la cantidad actual
$sql_actual = "SELECT cantidad, almacen_id FROM productos WHERE id = ?";
$stmt_actual = $conn->prepare($sql_actual);
$stmt_actual->bind_param("i", $producto_id);
$stmt_actual->execute();
$result = $stmt_actual->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
    exit();
}

$producto = $result->fetch_assoc();
$cantidad_actual = $producto['cantidad'];
$almacen_id = isset($_POST['almacen_id']) ? intval($_POST['almacen_id']) : $producto['almacen_id'];
$stmt_actual->close();

// Calcular nueva cantidad
$nueva_cantidad = $cantidad_actual;
if ($accion === 'sumar') {
    $nueva_cantidad = $cantidad_actual + 1;
} elseif ($accion === 'restar' && $cantidad_actual > 0) {
    $nueva_cantidad = $cantidad_actual - 1;
}

// Actualizar la cantidad en la base de datos
$sql_update = "UPDATE productos SET cantidad = ? WHERE id = ?";
$stmt_update = $conn->prepare($sql_update);
$stmt_update->bind_param("ii", $nueva_cantidad, $producto_id);
$resultado = $stmt_update->execute();
$stmt_update->close();

// Registrar el movimiento
if ($resultado) {
    $usuario_id = $_SESSION["user_id"];
    
    // Solo registra movimiento si es diferente a la cantidad anterior
    if ($nueva_cantidad != $cantidad_actual) {
        $tipo = ($accion === 'sumar') ? 'entrada' : 'salida';
        $cambio_cantidad = 1; // Siempre cambiamos en 1
        
        $sql_movimiento = "INSERT INTO movimientos (producto_id, almacen_origen, cantidad, tipo, usuario_id, estado) 
                           VALUES (?, ?, ?, ?, ?, 'completado')";
        $stmt_movimiento = $conn->prepare($sql_movimiento);
        $stmt_movimiento->bind_param("iiisi", $producto_id, $almacen_id, $cambio_cantidad, $tipo, $usuario_id);
        $stmt_movimiento->execute();
        $stmt_movimiento->close();
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Cantidad actualizada',
        'nueva_cantidad' => $nueva_cantidad
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error al actualizar la cantidad']);
}
?>