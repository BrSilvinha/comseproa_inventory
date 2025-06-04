<?php
// reportes/generar_pdf_usuarios.php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

// Solo administradores pueden acceder a este reporte
if ($_SESSION["user_role"] != 'admin') {
    die("Acceso denegado");
}

require_once "../config/database.php";
require_once "../libs/tcpdf/tcpdf.php";

$user_name = $_SESSION["user_name"] ?? "Usuario";

// Obtener filtros
$filtro_fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$filtro_fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-t');
$filtro_usuario = isset($_GET['usuario']) ? $_GET['usuario'] : '';

$param_fecha_inicio = $filtro_fecha_inicio . ' 00:00:00';
$param_fecha_fin = $filtro_fecha_fin . ' 23:59:59';

// Query para actividad de usuarios
$sql_actividad = "
    SELECT 
        u.id as usuario_id,
        u.nombre as usuario_nombre,
        u.correo as usuario_email,
        u.rol,
        (
            SELECT COUNT(*) 
            FROM movimientos m 
            WHERE m.usuario_id = u.id 
            AND m.fecha BETWEEN ? AND ?
        ) +
        (
            SELECT COUNT(*) 
            FROM solicitudes_transferencia s 
            WHERE s.usuario_id = u.id 
            AND s.fecha_solicitud BETWEEN ? AND ?
        ) as total_actividades,
        (
            SELECT COUNT(*) 
            FROM movimientos m 
            WHERE m.usuario_id = u.id 
            AND m.estado = 'completado'
            AND m.fecha BETWEEN ? AND ?
        ) +
        (
            SELECT COUNT(*) 
            FROM solicitudes_transferencia s 
            WHERE s.usuario_id = u.id 
            AND s.estado = 'aprobada'
            AND s.fecha_solicitud BETWEEN ? AND ?
        ) as completadas,
        a.nombre as almacen_nombre
    FROM usuarios u
    LEFT JOIN almacenes a ON u.almacen_id = a.id
    WHERE u.estado = 'activo'
";

if (!empty($filtro_usuario)) {
    $sql_actividad .= " AND u.id = ?";
    $sql_actividad .= " ORDER BY total_actividades DESC";
    $stmt_actividad = $conn->prepare($sql_actividad);
    $stmt_actividad->bind_param("sssssssssi", 
        $param_fecha_inicio, $param_fecha_fin,
        $param_fecha_inicio, $param_fecha_fin,
        $param_fecha_inicio, $param_fecha_fin,
        $param_fecha_inicio, $param_fecha_fin,
        $filtro_usuario
    );
} else {
    $sql_actividad .= " ORDER BY total_actividades DESC";
    $stmt_actividad = $conn->prepare($sql_actividad);
    $stmt_actividad->bind_param("ssssssss", 
        $param_fecha_inicio, $param_fecha_fin,
        $param_fecha_inicio, $param_fecha_fin,
        $param_fecha_inicio, $param_fecha_fin,
        $param_fecha_inicio, $param_fecha_fin
    );
}

$stmt_actividad->execute();
$result_actividad = $stmt_actividad->get_result();

// Estadísticas generales
$sql_stats = "
    SELECT 
        COUNT(DISTINCT u.id) as usuarios_activos,
        (
            SELECT COUNT(*) FROM movimientos m 
            WHERE m.fecha BETWEEN ? AND ?
        ) + 
        (
            SELECT COUNT(*) FROM solicitudes_transferencia s 
            WHERE s.fecha_solicitud BETWEEN ? AND ?
        ) as total_actividades
    FROM usuarios u
    WHERE u.estado = 'activo'
";

$stmt_stats = $conn->prepare($sql_stats);
$stmt_stats->bind_param("ssss", $param_fecha_inicio, $param_fecha_fin, $param_fecha_inicio, $param_fecha_fin);
$stmt_stats->execute();
$result_stats = $stmt_stats->get_result();
$stats = $result_stats->fetch_assoc();

// Crear PDF
class UsuariosPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'REPORTE DE ACTIVIDAD DE USUARIOS - GRUPO SEAL', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(20);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Página '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

$pdf = new UsuariosPDF();
$pdf->SetCreator('GRUPO SEAL System');
$pdf->SetAuthor($user_name);
$pdf->SetTitle('Reporte de Actividad de Usuarios');

$pdf->AddPage();

// Información del reporte
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Reporte de Actividad de Usuarios', 0, 1);

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 8, 'Período: ' . date('d/m/Y', strtotime($filtro_fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($filtro_fecha_fin)), 0, 1);
$pdf->Cell(0, 8, 'Generado el: ' . date('d/m/Y H:i:s'), 0, 1);
$pdf->Cell(0, 8, 'Por: ' . $user_name, 0, 1);
$pdf->Ln(10);

// Estadísticas generales
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'ESTADÍSTICAS DEL PERÍODO', 0, 1);
$pdf->SetFont('helvetica', '', 10);

$pdf->Cell(50, 8, 'Usuarios Activos:', 0, 0);
$pdf->Cell(50, 8, number_format($stats['usuarios_activos']), 0, 1);

$pdf->Cell(50, 8, 'Total Actividades:', 0, 0);
$pdf->Cell(50, 8, number_format($stats['total_actividades']), 0, 1);

$promedio = $stats['usuarios_activos'] > 0 ? $stats['total_actividades'] / $stats['usuarios_activos'] : 0;
$pdf->Cell(50, 8, 'Promedio por Usuario:', 0, 0);
$pdf->Cell(50, 8, number_format($promedio, 1), 0, 1);

$pdf->Ln(10);

// Tabla de actividad de usuarios
if ($result_actividad->num_rows > 0) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'ACTIVIDAD POR USUARIO', 0, 1);

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(50, 8, 'Usuario', 1, 0, 'C');
    $pdf->Cell(40, 8, 'Email', 1, 0, 'C');
    $pdf->Cell(20, 8, 'Rol', 1, 0, 'C');
    $pdf->Cell(40, 8, 'Almacén', 1, 0, 'C');
    $pdf->Cell(20, 8, 'Total', 1, 0, 'C');
    $pdf->Cell(20, 8, 'Complet.', 1, 1, 'C');

    $pdf->SetFont('helvetica', '', 8);
    while ($usuario = $result_actividad->fetch_assoc()) {
        $pdf->Cell(50, 8, substr($usuario['usuario_nombre'], 0, 25), 1, 0);
        $pdf->Cell(40, 8, substr($usuario['usuario_email'], 0, 20), 1, 0);
        $pdf->Cell(20, 8, ucfirst($usuario['rol']), 1, 0, 'C');
        $pdf->Cell(40, 8, substr($usuario['almacen_nombre'] ?? 'N/A', 0, 20), 1, 0);
        $pdf->Cell(20, 8, number_format($usuario['total_actividades']), 1, 0, 'C');
        $pdf->Cell(20, 8, number_format($usuario['completadas']), 1, 1, 'C');
    }
}

// Análisis adicional
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'ANÁLISIS', 0, 1);
$pdf->SetFont('helvetica', '', 10);

// Top 3 usuarios más activos
$result_actividad->data_seek(0); // Reset del cursor
$pdf->Cell(0, 8, 'TOP 3 USUARIOS MÁS ACTIVOS:', 0, 1);
$contador = 1;
while ($usuario = $result_actividad->fetch_assoc() && $contador <= 3) {
    $pdf->Cell(10, 8, $contador . '.', 0, 0);
    $pdf->Cell(0, 8, $usuario['usuario_nombre'] . ' - ' . number_format($usuario['total_actividades']) . ' actividades', 0, 1);
    $contador++;
}

// Configurar descarga
$filename = 'reporte_usuarios_' . $filtro_fecha_inicio . '_' . $filtro_fecha_fin . '.pdf';
$pdf->Output($filename, 'D');
?>