<?php
// reportes/generar_excel_inventario.php
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
$usuario_rol = $_SESSION["user_role"] ?? "usuario";
$usuario_almacen_id = $_SESSION["almacen_id"] ?? null;

// Obtener ID del almacén (si se especifica)
$almacen_id = isset($_GET['almacen_id']) ? (int)$_GET['almacen_id'] : null;

// Verificar permisos
if ($usuario_rol != 'admin' && $almacen_id && $usuario_almacen_id != $almacen_id) {
    die("No tienes permiso para generar este reporte");
}

// Obtener información del almacén
$almacen_info = null;
if ($almacen_id) {
    $sql_almacen = "SELECT * FROM almacenes WHERE id = ?";
    $stmt = $conn->prepare($sql_almacen);
    $stmt->bind_param("i", $almacen_id);
    $stmt->execute();
    $almacen_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Obtener estadísticas
if ($almacen_id) {
    $sql_stats = "SELECT 
        COUNT(DISTINCT p.categoria_id) as total_categorias,
        COUNT(p.id) as total_productos,
        COALESCE(SUM(p.cantidad), 0) as total_stock,
        COALESCE(AVG(p.cantidad), 0) as promedio_stock,
        COALESCE(MIN(p.cantidad), 0) as stock_minimo,
        COALESCE(MAX(p.cantidad), 0) as stock_maximo
        FROM productos p 
        WHERE p.almacen_id = ?";
    $stmt = $conn->prepare($sql_stats);
    $stmt->bind_param("i", $almacen_id);
} else {
    $sql_stats = "SELECT 
        COUNT(DISTINCT p.categoria_id) as total_categorias,
        COUNT(p.id) as total_productos,
        COALESCE(SUM(p.cantidad), 0) as total_stock,
        COALESCE(AVG(p.cantidad), 0) as promedio_stock,
        COALESCE(MIN(p.cantidad), 0) as stock_minimo,
        COALESCE(MAX(p.cantidad), 0) as stock_maximo
        FROM productos p";
    $stmt = $conn->prepare($sql_stats);
}

$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Obtener productos detallados
if ($almacen_id) {
    $sql_productos = "SELECT 
        p.id,
        p.nombre,
        p.cantidad,
        c.nombre as categoria,
        p.precio,
        (p.cantidad * p.precio) as valor_total
        FROM productos p
        JOIN categorias c ON p.categoria_id = c.id
        WHERE p.almacen_id = ?
        ORDER BY c.nombre, p.nombre";
    $stmt = $conn->prepare($sql_productos);
    $stmt->bind_param("i", $almacen_id);
} else {
    $sql_productos = "SELECT 
        p.id,
        p.nombre,
        p.cantidad,
        c.nombre as categoria,
        a.nombre as almacen,
        p.precio,
        (p.cantidad * p.precio) as valor_total
        FROM productos p
        JOIN categorias c ON p.categoria_id = c.id
        JOIN almacenes a ON p.almacen_id = a.id
        ORDER BY a.nombre, c.nombre, p.nombre";
    $stmt = $conn->prepare($sql_productos);
}

$stmt->execute();
$productos = $stmt->get_result();
$stmt->close();

// Obtener productos por categoría
if ($almacen_id) {
    $sql_categorias = "SELECT c.nombre, 
        COUNT(p.id) as total_productos,
        COALESCE(SUM(p.cantidad), 0) as total_stock,
        COALESCE(SUM(p.cantidad * p.precio), 0) as valor_total
        FROM categorias c
        LEFT JOIN productos p ON c.id = p.categoria_id AND p.almacen_id = ?
        GROUP BY c.id, c.nombre
        ORDER BY total_stock DESC";
    $stmt = $conn->prepare($sql_categorias);
    $stmt->bind_param("i", $almacen_id);
} else {
    $sql_categorias = "SELECT c.nombre, 
        COUNT(p.id) as total_productos,
        COALESCE(SUM(p.cantidad), 0) as total_stock,
        COALESCE(SUM(p.cantidad * p.precio), 0) as valor_total
        FROM categorias c
        LEFT JOIN productos p ON c.id = p.categoria_id
        GROUP BY c.id, c.nombre
        ORDER BY total_stock DESC";
    $stmt = $conn->prepare($sql_categorias);
}

$stmt->execute();
$categorias_stats = $stmt->get_result();
$stmt->close();

// Crear Excel
$spreadsheet = new Spreadsheet();

// === HOJA 1: RESUMEN ===
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Resumen');

// Encabezado
$sheet->setCellValue('A1', 'REPORTE DE INVENTARIO - COMSEPROA');
$sheet->mergeCells('A1:F1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Información del reporte
$row = 3;
if ($almacen_info) {
    $sheet->setCellValue('A' . $row, 'Almacén:');
    $sheet->setCellValue('B' . $row, $almacen_info['nombre']);
    $row++;
    $sheet->setCellValue('A' . $row, 'Ubicación:');
    $sheet->setCellValue('B' . $row, $almacen_info['ubicacion']);
    $row++;
} else {
    $sheet->setCellValue('A' . $row, 'Tipo:');
    $sheet->setCellValue('B' . $row, 'Inventario General - Todos los Almacenes');
    $row++;
}

$sheet->setCellValue('A' . $row, 'Generado el:');
$sheet->setCellValue('B' . $row, date('d/m/Y H:i:s'));
$row++;
$sheet->setCellValue('A' . $row, 'Por:');
$sheet->setCellValue('B' . $row, $user_name);
$row += 2;

// Estadísticas generales
$sheet->setCellValue('A' . $row, 'ESTADÍSTICAS GENERALES');
$sheet->mergeCells('A' . $row . ':B' . $row);
$sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
$row++;

$stats_data = [
    ['Total Productos:', number_format($stats['total_productos'])],
    ['Total Categorías:', number_format($stats['total_categorias'])],
    ['Stock Total:', number_format($stats['total_stock']) . ' unidades'],
    ['Promedio por Producto:', number_format($stats['promedio_stock'], 1) . ' unidades'],
    ['Stock Mínimo:', number_format($stats['stock_minimo']) . ' unidades'],
    ['Stock Máximo:', number_format($stats['stock_maximo']) . ' unidades']
];

foreach ($stats_data as $stat) {
    $sheet->setCellValue('A' . $row, $stat[0]);
    $sheet->setCellValue('B' . $row, $stat[1]);
    $row++;
}

// Autoajustar columnas
$sheet->getColumnDimension('A')->setAutoSize(true);
$sheet->getColumnDimension('B')->setAutoSize(true);

// === HOJA 2: PRODUCTOS DETALLADOS ===
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Productos Detallados');

// Encabezado
$headers = ['ID', 'Producto', 'Categoría', 'Stock', 'Precio', 'Valor Total'];
if (!$almacen_id) {
    array_splice($headers, 3, 0, 'Almacén'); // Insertar 'Almacén' en posición 3
}

$col = 'A';
foreach ($headers as $header) {
    $sheet2->setCellValue($col . '1', $header);
    $sheet2->getStyle($col . '1')->getFont()->setBold(true);
    $sheet2->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F4FD');
    $col++;
}

// Datos de productos
$row = 2;
while ($producto = $productos->fetch_assoc()) {
    $col = 'A';
    $sheet2->setCellValue($col++ . $row, 'PROD-' . str_pad($producto['id'], 4, '0', STR_PAD_LEFT));
    $sheet2->setCellValue($col++ . $row, $producto['nombre']);
    $sheet2->setCellValue($col++ . $row, $producto['categoria']);
    
    if (!$almacen_id) {
        $sheet2->setCellValue($col++ . $row, $producto['almacen']);
    }
    
    $sheet2->setCellValue($col++ . $row, $producto['cantidad']);
    $sheet2->setCellValue($col++ . $row, $producto['precio']);
    $sheet2->setCellValue($col++ . $row, $producto['valor_total']);
    $row++;
}

// Autoajustar columnas
foreach (range('A', $col) as $column) {
    $sheet2->getColumnDimension($column)->setAutoSize(true);
}

// === HOJA 3: RESUMEN POR CATEGORÍAS ===
$sheet3 = $spreadsheet->createSheet();
$sheet3->setTitle('Resumen por Categorías');

// Encabezado
$headers3 = ['Categoría', 'Total Productos', 'Stock Total', 'Valor Total', '% del Stock'];
$col = 'A';
foreach ($headers3 as $header) {
    $sheet3->setCellValue($col . '1', $header);
    $sheet3->getStyle($col . '1')->getFont()->setBold(true);
    $sheet3->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F4FD');
    $col++;
}

// Datos de categorías
$row = 2;
$total_general = $stats['total_stock'];
while ($cat = $categorias_stats->fetch_assoc()) {
    $porcentaje = $total_general > 0 ? ($cat['total_stock'] / $total_general) * 100 : 0;
    
    $sheet3->setCellValue('A' . $row, $cat['nombre']);
    $sheet3->setCellValue('B' . $row, $cat['total_productos']);
    $sheet3->setCellValue('C' . $row, $cat['total_stock']);
    $sheet3->setCellValue('D' . $row, $cat['valor_total']);
    $sheet3->setCellValue('E' . $row, number_format($porcentaje, 1) . '%');
    $row++;
}

// Autoajustar columnas
foreach (range('A', 'E') as $column) {
    $sheet3->getColumnDimension($column)->setAutoSize(true);
}

// Configurar descarga
$filename = 'reporte_inventario_' . date('Y-m-d_H-i-s') . '.xlsx';
if ($almacen_info) {
    $filename = 'inventario_' . preg_replace('/[^a-zA-Z0-9]/', '_', $almacen_info['nombre']) . '_' . date('Y-m-d_H-i-s') . '.xlsx';
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>