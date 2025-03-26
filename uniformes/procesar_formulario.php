<?php
session_start();
require_once "../config/database.php";

header('Content-Type: application/json');

function enviarProductoMultiple($conn, $producto_id, $almacen_origen, $almacen_destino, $cantidad) {
    // Lógica para transferir producto
    $stmt = $conn->prepare("INSERT INTO solicitudes_transferencia 
        (producto_id, almacen_origen, almacen_destino, cantidad, estado) 
        VALUES (?, ?, ?, ?, 'pendiente')");
    
    $stmt->bind_param("iiii", $producto_id, $almacen_origen, $almacen_destino, $cantidad);
    return $stmt->execute();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $almacen_destino = $_POST['almacen_destino'] ?? null;
    $almacen_origen = $_POST['almacen_origen'] ?? null;
    $productos_ids = $_POST['producto_id'] ?? null;

    if (!$almacen_destino || !$almacen_origen || !$productos_ids) {
        echo json_encode([
            'success' => false, 
            'message' => 'Datos incompletos'
        ]);
        exit;
    }

    // Manejar múltiples productos
    $ids_array = explode(',', $productos_ids);
    $transferencias_exitosas = 0;

    foreach ($ids_array as $producto_id) {
        // Obtener cantidad del producto
        $stmt = $conn->prepare("SELECT cantidad FROM productos WHERE id = ? AND almacen_id = ?");
        $stmt->bind_param("ii", $producto_id, $almacen_origen);
        $stmt->execute();
        $result = $stmt->get_result();
        $producto = $result->fetch_assoc();

        if ($producto) {
            if (enviarProductoMultiple($conn, $producto_id, $almacen_origen, $almacen_destino, $producto['cantidad'])) {
                $transferencias_exitosas++;
            }
        }
    }

    if ($transferencias_exitosas > 0) {
        echo json_encode([
            'success' => true, 
            'message' => "Se han iniciado $transferencias_exitosas transferencias de producto(s)"
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'No se pudo realizar ninguna transferencia'
        ]);
    }
}
?>