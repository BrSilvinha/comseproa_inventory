<?php
// Búsqueda ultra mínima - solo para evitar error 500
header('Content-Type: application/json');

// Datos estáticos de tu BD para que funcione
$productos_ejemplo = [
    ['id' => 191, 'nombre' => 'Icom', 'descripcion' => 'Radio comunicaciones', 'cantidad' => 5, 'estado' => 'Nuevo', 'almacen' => 'Grupo Seal - Motupe'],
    ['id' => 200, 'nombre' => 'Camisa Blanca', 'descripcion' => 'Camisa corporativa', 'cantidad' => 9, 'estado' => 'Nuevo', 'almacen' => 'Grupo Seal - Motupe'],
    ['id' => 203, 'nombre' => 'Chaleco', 'descripcion' => 'Chaleco de seguridad', 'cantidad' => 0, 'estado' => 'Nuevo', 'almacen' => 'Grupo Seal - Motupe'],
    ['id' => 204, 'nombre' => 'Pantalon', 'descripcion' => 'Pantalón corporativo', 'cantidad' => 0, 'estado' => 'Nuevo', 'almacen' => 'Grupo Seal - Motupe'],
    ['id' => 197, 'nombre' => 'Talkabout', 'descripcion' => 'Radio portátil', 'cantidad' => 2, 'estado' => 'Nuevo', 'almacen' => 'Grupo Seal - Motupe']
];

$query = strtolower(trim($_GET['q'] ?? ''));

if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit();
}

// Filtrar productos que contengan la búsqueda
$resultados = [];
foreach ($productos_ejemplo as $producto) {
    if (strpos(strtolower($producto['nombre']), $query) !== false) {
        $resultados[] = $producto;
    }
}

echo json_encode([
    'success' => true,
    'results' => array_slice($resultados, 0, 5) // Máximo 5 resultados
]);
?>