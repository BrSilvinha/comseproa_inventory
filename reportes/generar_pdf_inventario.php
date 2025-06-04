<?php
// reportes/generar_pdf_inventario.php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

require_once "../config/database.php";
require_once "../libs/tcpdf/tcpdf.php";

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

// Obtener productos por categoría
if ($almacen_id) {
    $sql_categorias = "SELECT c.nombre, 
        COUNT(p.id) as total_productos,
        COALESCE(SUM(p.cantidad), 0) as total_stock
        FROM categorias c
        LEFT JOIN productos p ON c.id = p.categoria_id AND p.almacen_id = ?
        GROUP BY c.id, c.nombre
        ORDER BY total_stock DESC";
    $stmt = $conn->prepare($sql_categorias);
    $stmt->bind_param("i", $almacen_id);
} else {
    $sql_categorias = "SELECT c.nombre, 
        COUNT(p.id) as total_productos,
        COALESCE(SUM(p.cantidad), 0) as total_stock
        FROM categorias c
        LEFT JOIN productos p ON c.id = p.categoria_id
        GROUP BY c.id, c.nombre
        ORDER BY total_stock DESC";
    $stmt = $conn->prepare($sql_categorias);
}

$stmt->execute();
$categorias_stats = $stmt->get_result();
$stmt->close();

// Obtener productos con stock bajo
if ($almacen_id) {
    $sql_bajo_stock = "SELECT p.nombre, p.cantidad, c.nombre as categoria
        FROM productos p
        JOIN categorias c ON p.categoria_id = c.id
        WHERE p.almacen_id = ? AND p.cantidad < 10
        ORDER BY p.cantidad ASC";
    $stmt = $conn->prepare($sql_bajo_stock);
    $stmt->bind_param("i", $almacen_id);
} else {
    $sql_bajo_stock = "SELECT p.nombre, p.cantidad, c.nombre as categoria, a.nombre as almacen
        FROM productos p
        JOIN categorias c ON p.categoria_id = c.id
        JOIN almacenes a ON p.almacen_id = a.id
        WHERE p.cantidad < 10
        ORDER BY p.cantidad ASC";
    $stmt = $conn->prepare($sql_bajo_stock);
}

$stmt->execute();
$productos_bajo_stock = $stmt->get_result();
$stmt->close();

// Crear PDF
class InventarioPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'REPORTE DE INVENTARIO - COMSEPROA', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(20);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Página '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

$pdf = new InventarioPDF();
$pdf->SetCreator('COMSEPROA System');
$pdf->SetAuthor($user_name);
$pdf->SetTitle('Reporte de Inventario');

$pdf->AddPage();

// Información del reporte
$pdf->SetFont('helvetica', 'B', 14);
if ($almacen_info) {
    $pdf->Cell(0, 10, 'Almacén: ' . $almacen_info['nombre'], 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 8, 'Ubicación: ' . $almacen_info['ubicacion'], 0, 1);
} else {
    $pdf->Cell(0, 10, 'Inventario General - Todos los Almacenes', 0, 1);
}

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 8, 'Generado el: ' . date('d/m/Y H:i:s'), 0, 1);
$pdf->Cell(0, 8, 'Por: ' . $user_name, 0, 1);
$pdf->Ln(10);

// Estadísticas generales
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'ESTADÍSTICAS GENERALES', 0, 1);
$pdf->SetFont('helvetica', '', 10);

$pdf->Cell(50, 8, 'Total Productos:', 0, 0);
$pdf->Cell(50, 8, number_format($stats['total_productos']), 0, 1);

$pdf->Cell(50, 8, 'Total Categorías:', 0, 0);
$pdf->Cell(50, 8, number_format($stats['total_categorias']), 0, 1);

$pdf->Cell(50, 8, 'Stock Total:', 0, 0);
$pdf->Cell(50, 8, number_format($stats['total_stock']) . ' unidades', 0, 1);

$pdf->Cell(50, 8, 'Promedio por Producto:', 0, 0);
$pdf->Cell(50, 8, number_format($stats['promedio_stock'], 1) . ' unidades', 0, 1);

$pdf->Ln(10);

// Distribución por categorías
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'DISTRIBUCIÓN POR CATEGORÍAS', 0, 1);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(60, 8, 'Categoría', 1, 0, 'C');
$pdf->Cell(30, 8, 'Productos', 1, 0, 'C');
$pdf->Cell(30, 8, 'Stock Total', 1, 0, 'C');
$pdf->Cell(30, 8, '% del Total', 1, 1, 'C');

$pdf->SetFont('helvetica', '', 9);
$total_general = $stats['total_stock'];

while ($cat = $categorias_stats->fetch_assoc()) {
    $porcentaje = $total_general > 0 ? ($cat['total_stock'] / $total_general) * 100 : 0;
    
    $pdf->Cell(60, 8, substr($cat['nombre'], 0, 25), 1, 0);
    $pdf->Cell(30, 8, number_format($cat['total_productos']), 1, 0, 'C');
    $pdf->Cell(30, 8, number_format($cat['total_stock']), 1, 0, 'C');
    $pdf->Cell(30, 8, number_format($porcentaje, 1) . '%', 1, 1, 'C');
}

$pdf->Ln(10);

// Productos con stock crítico
if ($productos_bajo_stock->num_rows > 0) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'PRODUCTOS CON STOCK CRÍTICO (< 10 unidades)', 0, 1);

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(70, 8, 'Producto', 1, 0, 'C');
    $pdf->Cell(40, 8, 'Categoría', 1, 0, 'C');
    if (!$almacen_id) {
        $pdf->Cell(40, 8, 'Almacén', 1, 0, 'C');
        $pdf->Cell(30, 8, 'Stock', 1, 1, 'C');
    } else {
        $pdf->Cell(30, 8, 'Stock', 1, 1, 'C');
    }

    $pdf->SetFont('helvetica', '', 9);
    while ($prod = $productos_bajo_stock->fetch_assoc()) {
        $pdf->Cell(70, 8, substr($prod['nombre'], 0, 30), 1, 0);
        $pdf->Cell(40, 8, substr($prod['categoria'], 0, 15), 1, 0);
        if (!$almacen_id) {
            $pdf->Cell(40, 8, substr($prod['almacen'], 0, 15), 1, 0);
        }
        $pdf->Cell(30, 8, $prod['cantidad'], 1, 1, 'C');
    }
}

// Configurar descarga
$filename = 'reporte_inventario_' . date('Y-m-d_H-i-s') . '.pdf';
if ($almacen_info) {
    $filename = 'inventario_' . preg_replace('/[^a-zA-Z0-9]/', '_', $almacen_info['nombre']) . '_' . date('Y-m-d_H-i-s') . '.pdf';
}

$pdf->Output($filename, 'D');
?>