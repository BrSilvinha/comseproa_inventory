<?php
/**
 * API de Búsqueda Instantánea
 * Busca productos en tiempo real
 */

require_once __DIR__ . '/../bootstrap.php';

// Verificar autenticación
if (!Session::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit();
}

// Verificar que sea petición AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Solo peticiones AJAX']);
    exit();
}

try {
    $db = Database::getInstance();
    $usuario_rol = Session::get('user_role', 'usuario');
    $usuario_almacen_id = Session::get('almacen_id');

    // Obtener parámetros de búsqueda
    $query = trim($_GET['q'] ?? '');
    $almacen_id = isset($_GET['almacen_id']) ? (int)$_GET['almacen_id'] : null;
    $categoria_id = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : null;
    $limit = min(20, (int)($_GET['limit'] ?? 10)); // Máximo 20 resultados

    if (empty($query) || strlen($query) < 2) {
        echo json_encode(['success' => false, 'error' => 'Query muy corto']);
        exit();
    }

    // Construir consulta SQL
    $sql = "SELECT 
                p.id,
                p.nombre,
                p.descripcion,
                p.cantidad,
                p.stock_minimo,
                p.precio_unitario,
                c.nombre as categoria,
                a.nombre as almacen
            FROM productos p
            LEFT JOIN categorias c ON p.categoria_id = c.id
            LEFT JOIN almacenes a ON p.almacen_id = a.id
            WHERE 1=1";

    $params = [];
    $types = '';

    // Filtro de búsqueda por texto
    $searchTerms = explode(' ', $query);
    $searchConditions = [];
    
    foreach ($searchTerms as $term) {
        $term = trim($term);
        if (strlen($term) >= 2) {
            $searchConditions[] = "(p.nombre LIKE ? OR p.descripcion LIKE ? OR p.codigo LIKE ?)";
            $params[] = "%$term%";
            $params[] = "%$term%";
            $params[] = "%$term%";
            $types .= 'sss';
        }
    }

    if (!empty($searchConditions)) {
        $sql .= " AND (" . implode(" AND ", $searchConditions) . ")";
    }

    // Filtros adicionales
    if ($categoria_id) {
        $sql .= " AND p.categoria_id = ?";
        $params[] = $categoria_id;
        $types .= 'i';
    }

    if ($almacen_id) {
        $sql .= " AND p.almacen_id = ?";
        $params[] = $almacen_id;
        $types .= 'i';
    }

    // Restricciones por rol
    if ($usuario_rol !== 'admin' && $usuario_almacen_id) {
        $sql .= " AND p.almacen_id = ?";
        $params[] = $usuario_almacen_id;
        $types .= 'i';
    }

    // Ordenar por relevancia
    $sql .= " ORDER BY 
                CASE 
                    WHEN p.nombre LIKE ? THEN 1
                    WHEN p.nombre LIKE ? THEN 2
                    WHEN p.descripcion LIKE ? THEN 3
                    ELSE 4
                END,
                p.nombre ASC
              LIMIT ?";

    // Parámetros para ordenación
    $params[] = "$query%";      // Comienza con
    $params[] = "%$query%";     // Contiene
    $params[] = "%$query%";     // Descripción contiene
    $params[] = $limit;
    $types .= 'sssi';

    // Ejecutar consulta
    $results = $db->fetchAll($sql, $params, $types);

    // Procesar resultados
    $productos = [];
    foreach ($results as $row) {
        $productos[] = [
            'id' => (int)$row['id'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'cantidad' => (int)$row['cantidad'],
            'stock_minimo' => (int)$row['stock_minimo'],
            'precio_unitario' => $row['precio_unitario'] ? (float)$row['precio_unitario'] : null,
            'categoria' => $row['categoria'],
            'almacen' => $row['almacen']
        ];
    }

    // Log de búsqueda (para analytics)
    Logger::info("Search performed", [
        'query' => $query,
        'results_count' => count($productos),
        'user_id' => Session::get('user_id'),
        'filters' => [
            'almacen_id' => $almacen_id,
            'categoria_id' => $categoria_id
        ]
    ]);

    // Retornar resultados
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $productos,
        'query' => $query,
        'total' => count($productos),
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    Logger::error("Search API error", [
        'message' => $e->getMessage(),
        'query' => $_GET['q'] ?? '',
        'user_id' => Session::get('user_id')
    ]);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
}
?>