<?php
// reportes/generar_pdf_movimientos.php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

require_once "../config/database.php";
require_once "../libs/tcpdf/tcpdf.php";

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
        $sql_movimientos .= " ORDER BY m.fecha DESC LIMIT 200";
        $stmt_movimientos = $conn->prepare($sql_movimientos);
        $stmt_movimientos->bind_param("ssiss", $param_fecha_inicio, $param_fecha_fin, $filtro_almacen, $filtro_almacen, $filtro_tipo);
    } else {
        $sql_movimientos .= " ORDER BY m.fecha DESC LIMIT 200";
        $stmt_movimientos = $conn->prepare($sql_movimientos);
        $stmt_movimientos->bind_param("ssii", $param_fecha_inicio, $param_fecha_fin, $filtro_almacen, $filtro_almacen);
    }
} elseif ($usuario_rol != 'admin') {
    $sql_movimientos .= " AND (m.almacen_origen = ? OR m.almacen_destino = ?)";
    if (!empty($filtro_tipo)) {
        $sql_movimientos .= " AND m.tipo = ?";
        $sql_movimientos .= " ORDER BY m.fecha DESC LIMIT 200";
        $stmt_movimientos = $conn->prepare($sql_movimientos);
        $stmt_movimientos->bind_param("ssiss", $param_fecha_inicio, $param_fecha_fin, $usuario_almacen_id, $usuario_almacen_id, $filtro_tipo);
    } else {
        $sql_movimientos .= " ORDER BY m.fecha DESC LIMIT 200";
        $stmt_movimientos = $conn->prepare($sql_movimientos);
        $stmt_movimientos->bind_param("ssii", $param_fecha_inicio, $param_fecha_fin, $usuario_almacen_id, $usuario_almacen_id);
    }
} else {
    if (!empty($filtro_tipo)) {
        $sql_movimientos .= " AND m.tipo = ?";
        $sql_movimientos .= " ORDER BY m.fecha DESC LIMIT 200";
        $stmt_movimientos = $conn->prepare($sql_movimientos);
        $stmt_movimientos->bind_param("sss", $param_fecha_inicio, $param_fecha_fin, $filtro_tipo);
    } else {
        $sql_movimientos .= " ORDER BY m.fecha DESC LIMIT 200";
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
        SUM(CASE WHEN estado = 'rechazado' THEN 1 ELSE 0 END) as rechazados
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

// Crear PDF
class MovimientosPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'REPORTE DE MOVIMIENTOS - GRUPO SEAL', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(20);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Página '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

$pdf = new MovimientosPDF();
$pdf->SetCreator('GRUPO SEAL System');
$pdf->SetAuthor($user_name);
$pdf->SetTitle('Reporte de Movimientos');

$pdf->AddPage();

// Información del reporte
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Reporte de Movimientos de Inventario', 0, 1);

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 8, 'Período: ' . date('d/m/Y', strtotime($filtro_fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($filtro_fecha_fin)), 0, 1);
$pdf->Cell(0, 8, 'Generado el: ' . date('d/m/Y H:i:s'), 0, 1);
$pdf->Cell(0, 8, 'Por: ' . $user_name, 0, 1);

if (!empty($filtro_tipo)) {
    $pdf->Cell(0, 8, 'Tipo de movimiento: ' . ucfirst($filtro_tipo), 0, 1);
}

$pdf->Ln(10);

// Estadísticas generales
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'ESTADÍSTICAS DEL PERÍODO', 0, 1);
$pdf->SetFont('helvetica', '', 10);

$pdf->Cell(50, 8, 'Total Movimientos:', 0, 0);
$pdf->Cell(50, 8, number_format($stats['total_movimientos']), 0, 1);

$pdf->Cell(50, 8, 'Completados:', 0, 0);
$pdf->Cell(50, 8, number_format($stats['completados']), 0, 1);

$pdf->Cell(50, 8, 'Pendientes:', 0, 0);
$pdf->Cell(50, 8, number_format($stats['pendientes']), 0, 1);

$pdf->Cell(50, 8, 'Rechazados:', 0, 0);
$pdf->Cell(50, 8, number_format($stats['rechazados']), 0, 1);

$pdf->Ln(10);

// Tabla de movimientos
if ($result_movimientos->num_rows > 0) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'DETALLE DE MOVIMIENTOS', 0, 1);

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(15, 8, 'ID', 1, 0, 'C');
    $pdf->Cell(25, 8, 'Fecha', 1, 0, 'C');
    $pdf->Cell(40, 8, 'Producto', 1, 0, 'C');
    $pdf->Cell(20, 8, 'Cantidad', 1, 0, 'C');
    $pdf->Cell(35, 8, 'Origen', 1, 0, 'C');
    $pdf->Cell(35, 8, 'Destino', 1, 0, 'C');
    $pdf->Cell(20, 8, 'Estado', 1, 1, 'C');

    $pdf->SetFont('helvetica', '', 7);
    while ($movimiento = $result_movimientos->fetch_assoc()) {
        $pdf->Cell(15, 8, '#' . str_pad($movimiento['id'], 4, '0', STR_PAD_LEFT), 1, 0);
        $pdf->Cell(25, 8, date('d/m/Y', strtotime($movimiento['fecha'])), 1, 0);
        $pdf->Cell(40, 8, substr($movimiento['producto_nombre'], 0, 20), 1, 0);
        $pdf->Cell(20, 8, number_format($movimiento['cantidad']), 1, 0, 'C');
        $pdf->Cell(35, 8, substr($movimiento['almacen_origen'] ?? 'Sistema', 0, 15), 1, 0);
        $pdf->Cell(35, 8, substr($movimiento['almacen_destino'] ?? 'Sistema', 0, 15), 1, 0);
        $pdf->Cell(20, 8, ucfirst($movimiento['estado']), 1, 1);
    }
}

// Configurar descarga
$filename = 'reporte_movimientos_' . $filtro_fecha_inicio . '_' . $filtro_fecha_fin . '.pdf';
$pdf->Output($filename, 'D');
?>