<?php
// reportes/generar_excel_movimientos.php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

require_once "../config/database.php";
require_once "../libs/PhpSpreadsheet/vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

$user_name = $_SESSION["user_name"] ?? "Usuario";
$usuario_almacen_id = $_SESSION["almacen_id"] ?? null;
$usuario_rol = $_SESSION["user_role"] ?? "usuario";

// Obtener filtros
$filtro_fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$filtro_fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-t');
$filtro_almacen = isset($_GET['almacen']) ? $_GET['almacen'] : '';
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';

$param_fecha_inicio = $filtro_fecha_inicio . ' 00:00:00';
$param_fecha_fin = $filtro_fecha_fin . ' 23:59:59';

// Query para movimientos
$sql_movimientos = "
    SELECT 
        m.id,
        m.fecha,
        m.cantidad,
        m.estado,
        m.tipo as tipo_movimiento,
        p.nombre as producto_nombre,
        CONCAT('PROD-', LPAD(p.id, 4, '0')) as producto_codigo,
        ao.nombre as almacen_origen,
        ad.nombre as almacen_destino,
        u.nombre as usuario_nombre
    FROM movimientos m
    LEFT JOIN productos p ON m.producto_id = p.id
    LEFT JOIN almacenes ao ON m.almacen_origen = ao.id
    LEFT JOIN almacenes ad ON m.almacen_destino = ad.id
    LEFT JOIN usuarios u ON m.usuario_id = u.id
    WHERE m.fecha BETWEEN ? AND ?
";

// Aplicar filtros
if (!empty($filtro_almacen) && $usuario_rol == 'admin') {
    $sql_movimientos .= " AND (m.almacen_origen = ? OR m.almacen_destino = ?)";
    if (!empty($filtro_tipo)) {
        $sql_movimientos .= " AND m.tipo = ?";
        $sql_movimientos .= " ORDER BY m.fecha DESC";
        $stmt_movimientos = $conn->prepare($sql_movimientos);
        $stmt_movimientos->bind_param("ssiss", $param_fecha_inicio, $param_fecha_fin, $filtro_almacen, $filtro_almacen, $filtro_tipo);
    } else {
        $sql_movimientos .= " ORDER BY m.fecha DESC";
        $stmt_movimientos = $conn->prepare($sql_movimientos);
        $stmt_movimientos->bind_param("ssii", $param_fecha_inicio, $param_fecha_fin, $filtro_almacen, $filtro_almacen);
    }
} elseif ($usuario_rol != 'admin') {
    $sql_movimientos .= " AND (m.almacen_origen = ? OR m.almacen_destino = ?)";
    if (!empty($filtro_tipo)) {
        $sql_movimientos .= " AND m.tipo = ?";
        $sql_movimientos .= " ORDER BY m.fecha DESC";
        $stmt_movimientos = $conn->prepare($sql_movimientos);
        $stmt_movimientos->bind_param("ssiss", $param_fecha_inicio, $param_fecha_fin, $usuario_almacen_id, $usuario_almacen_id, $filtro_tipo);
    } else {
        $sql_movimientos .= " ORDER BY m.fecha DESC";
        $stmt_movimientos = $conn->prepare($sql_movimientos);
        $stmt_movimientos->bind_param("ssii", $param_fecha_inicio, $param_fecha_fin, $usuario_almacen_id, $usuario_almacen_id);
    }
} else {
    if (!empty($filtro_tipo)) {
        $sql_movimientos .= " AND m.tipo = ?";
        $sql_movimientos .= " ORDER BY m.fecha DESC";
        $stmt_movimientos = $conn->prepare($sql_movimientos);
        $stmt_movimientos->bind_param("sss", $param_fecha_inicio, $param_fecha_fin, $filtro_tipo);
    } else {
        $sql_movimientos .= " ORDER BY m.fecha DESC";
        $stmt_movimientos = $conn->prepare($sql_movimientos);
        $stmt_movimientos->bind_param("ss", $param_fecha_inicio, $param_fecha_fin);
    }
}

$stmt_movimientos->execute();
$result_movimientos = $stmt_movimientos->get_result();

// Estadísticas
$sql_stats = "
    SELECT 
        COUNT(*) as total_movimientos,
        SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completados,
        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'rechazado' THEN 1 ELSE 0 END) as rechazados,
        SUM(CASE WHEN tipo = 'entrada' THEN cantidad ELSE 0 END) as total_entradas,
        SUM(CASE WHEN tipo = 'salida' THEN cantidad ELSE 0 END) as total_salidas,
        SUM(CASE WHEN tipo = 'transferencia' THEN cantidad ELSE 0 END) as total_transferencias
    FROM movimientos 
    WHERE fecha BETWEEN ? AND ?
";

if ($usuario_rol != 'admin') {
    $sql_stats .= " AND (almacen_origen = ? OR almacen_destino = ?)";
    $stmt_stats = $conn->prepare($sql_stats);
    $stmt_stats->bind_param("ssii", $param_fecha_inicio, $param_fecha_fin, $usuario_almacen_id, $usuario_almacen_id);
} else {
    $stmt_stats = $conn->prepare($sql_stats);
    $stmt_stats->bind_param("ss", $param_fecha_inicio, $param_fecha_fin);
}

$stmt_stats->execute();
$result_stats = $stmt_stats->get_result();
$stats = $result_stats->fetch_assoc();

// Crear Excel
$spreadsheet = new Spreadsheet();

// === HOJA 1: RESUMEN ===
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Resumen');

// Encabezado
$sheet->setCellValue('A1', 'REPORTE DE MOVIMIENTOS - GRUPO SEAL');
$sheet->mergeCells('A1:F1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Información del reporte
$row = 3;
$sheet->setCellValue('A' . $row, 'Período:');
$sheet->setCellValue('B' . $row, date('d/m/Y', strtotime($filtro_fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($filtro_fecha_fin)));
$row++;
$sheet->setCellValue('A' . $row, 'Generado el:');
$sheet->setCellValue('B' . $row, date('d/m/Y H:i:s'));
$row++;
$sheet->setCellValue('A' . $row, 'Por:');
$sheet->setCellValue('B' . $row, $user_name);
$row++;

if (!empty($filtro_tipo)) {
    $sheet->setCellValue('A' . $row, 'Tipo de movimiento:');
    $sheet->setCellValue('B' . $row, ucfirst($filtro_tipo));
    $row++;
}

$row++;

// Estadísticas generales
$sheet->setCellValue('A' . $row, 'ESTADÍSTICAS DEL PERÍODO');
$sheet->mergeCells('A' . $row . ':B' . $row);
$sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
$row++;

$stats_data = [
    ['Total Movimientos:', number_format($stats['total_movimientos'])],
    ['Completados:', number_format($stats['completados'])],
    ['Pendientes:', number_format($stats['pendientes'])],
    ['Rechazados:', number_format($stats['rechazados'])],
    ['Total Entradas:', number_format($stats['total_entradas']) . ' unidades'],
    ['Total Salidas:', number_format($stats['total_salidas']) . ' unidades'],
    ['Total Transferencias:', number_format($stats['total_transferencias']) . ' unidades']
];

foreach ($stats_data as $stat) {
    $sheet->setCellValue('A' . $row, $stat[0]);
    $sheet->setCellValue('B' . $row, $stat[1]);
    $row++;
}

// Autoajustar columnas
$sheet->getColumnDimension('A')->setAutoSize(true);
$sheet->getColumnDimension('B')->setAutoSize(true);

// === HOJA 2: MOVIMIENTOS DETALLADOS ===
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Movimientos Detallados');

// Encabezado
$headers = [
    'ID', 'Fecha', 'Hora', 'Producto', 'Código', 'Cantidad', 
    'Origen', 'Destino', 'Usuario', 'Tipo', 'Estado'
];

$col = 'A';
foreach ($headers as $header) {
    $sheet2->setCellValue($col . '1', $header);
    $sheet2->getStyle($col . '1')->getFont()->setBold(true);
    $sheet2->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F4FD');
    $col++;
}

// Datos de movimientos
$row = 2;
while ($movimiento = $result_movimientos->fetch_assoc()) {
    $fecha_obj = new DateTime($movimiento['fecha']);
    
    $sheet2->setCellValue('A' . $row, '#' . str_pad($movimiento['id'], 4, '0', STR_PAD_LEFT));
    $sheet2->setCellValue('B' . $row, $fecha_obj->format('d/m/Y'));
    $sheet2->setCellValue('C' . $row, $fecha_obj->format('H:i:s'));
    $sheet2->setCellValue('D' . $row, $movimiento['producto_nombre']);
    $sheet2->setCellValue('E' . $row, $movimiento['producto_codigo']);
    $sheet2->setCellValue('F' . $row, $movimiento['cantidad']);
    $sheet2->setCellValue('G' . $row, $movimiento['almacen_origen'] ?? 'Sistema');
    $sheet2->setCellValue('H' . $row, $movimiento['almacen_destino'] ?? 'Sistema');
    $sheet2->setCellValue('I' . $row, $movimiento['usuario_nombre']);
    $sheet2->setCellValue('J' . $row, ucfirst($movimiento['tipo_movimiento']));
    $sheet2->setCellValue('K' . $row, ucfirst($movimiento['estado']));
    
    // Colorear según el estado
    $estado_color = '';
    switch($movimiento['estado']) {
        case 'completado':
            $estado_color = 'C8E6C9'; // Verde claro
            break;
        case 'pendiente':
            $estado_color = 'FFF3E0'; // Naranja claro
            break;
        case 'rechazado':
            $estado_color = 'FFCDD2'; // Rojo claro
            break;
    }
    
    if ($estado_color) {
        $sheet2->getStyle('K' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($estado_color);
    }
    
    $row++;
}

// Autoajustar columnas
foreach (range('A', 'K') as $column) {
    $sheet2->getColumnDimension($column)->setAutoSize(true);
}

// === HOJA 3: ANÁLISIS POR TIPO ===
$sheet3 = $spreadsheet->createSheet();
$sheet3->setTitle('Análisis por Tipo');

// Análisis por tipo de movimiento
$sql_tipo = "
    SELECT 
        tipo,
        COUNT(*) as cantidad_movimientos,
        SUM(cantidad) as total_unidades,
        SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completados,
        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'rechazado' THEN 1 ELSE 0 END) as rechazados
    FROM movimientos 
    WHERE fecha BETWEEN ? AND ?
";

if ($usuario_rol != 'admin') {
    $sql_tipo .= " AND (almacen_origen = ? OR almacen_destino = ?)";
    $stmt_tipo = $conn->prepare($sql_tipo);
    $stmt_tipo->bind_param("ssii", $param_fecha_inicio, $param_fecha_fin, $usuario_almacen_id, $usuario_almacen_id);
} else {
    $stmt_tipo = $conn->prepare($sql_tipo);
    $stmt_tipo->bind_param("ss", $param_fecha_inicio, $param_fecha_fin);
}

$sql_tipo .= " GROUP BY tipo ORDER BY cantidad_movimientos DESC";

$stmt_tipo->execute();
$result_tipo = $stmt_tipo->get_result();

// Encabezado para análisis por tipo
$headers3 = ['Tipo', 'Cantidad Movimientos', 'Total Unidades', 'Completados', 'Pendientes', 'Rechazados', '% Éxito'];
$col = 'A';
foreach ($headers3 as $header) {
    $sheet3->setCellValue($col . '1', $header);
    $sheet3->getStyle($col . '1')->getFont()->setBold(true);
    $sheet3->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F4FD');
    $col++;
}

// Datos por tipo
$row = 2;
while ($tipo = $result_tipo->fetch_assoc()) {
    $porcentaje_exito = $tipo['cantidad_movimientos'] > 0 ? 
        ($tipo['completados'] / $tipo['cantidad_movimientos']) * 100 : 0;
    
    $sheet3->setCellValue('A' . $row, ucfirst($tipo['tipo']));
    $sheet3->setCellValue('B' . $row, $tipo['cantidad_movimientos']);
    $sheet3->setCellValue('C' . $row, $tipo['total_unidades']);
    $sheet3->setCellValue('D' . $row, $tipo['completados']);
    $sheet3->setCellValue('E' . $row, $tipo['pendientes']);
    $sheet3->setCellValue('F' . $row, $tipo['rechazados']);
    $sheet3->setCellValue('G' . $row, number_format($porcentaje_exito, 1) . '%');
    $row++;
}

// Autoajustar columnas
foreach (range('A', 'G') as $column) {
    $sheet3->getColumnDimension($column)->setAutoSize(true);
}

// Configurar descarga
$filename = 'reporte_movimientos_' . $filtro_fecha_inicio . '_' . $filtro_fecha_fin . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>