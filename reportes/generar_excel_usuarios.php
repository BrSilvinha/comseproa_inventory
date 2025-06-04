<?php
// reportes/generar_excel_usuarios.php
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
require_once "../libs/PhpSpreadsheet/vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

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
        (
            SELECT COUNT(*) 
            FROM movimientos m 
            WHERE m.usuario_id = u.id 
            AND m.estado = 'pendiente'
            AND m.fecha BETWEEN ? AND ?
        ) +
        (
            SELECT COUNT(*) 
            FROM solicitudes_transferencia s 
            WHERE s.usuario_id = u.id 
            AND s.estado = 'pendiente'
            AND s.fecha_solicitud BETWEEN ? AND ?
        ) as pendientes,
        GREATEST(
            COALESCE((SELECT MAX(m.fecha) FROM movimientos m WHERE m.usuario_id = u.id), '1970-01-01'),
            COALESCE((SELECT MAX(s.fecha_solicitud) FROM solicitudes_transferencia s WHERE s.usuario_id = u.id), '1970-01-01')
        ) as ultima_actividad,
        a.nombre as almacen_nombre
    FROM usuarios u
    LEFT JOIN almacenes a ON u.almacen_id = a.id
    WHERE u.estado = 'activo'
";

if (!empty($filtro_usuario)) {
    $sql_actividad .= " AND u.id = ?";
    $sql_actividad .= " ORDER BY total_actividades DESC";
    $stmt_actividad = $conn->prepare($sql_actividad);
    $stmt_actividad->bind_param("sssssssssssssi", 
        $param_fecha_inicio, $param_fecha_fin,
        $param_fecha_inicio, $param_fecha_fin,
        $param_fecha_inicio, $param_fecha_fin,
        $param_fecha_inicio, $param_fecha_fin,
        $param_fecha_inicio, $param_fecha_fin,
        $param_fecha_inicio, $param_fecha_fin,
        $filtro_usuario
    );
} else {
    $sql_actividad .= " ORDER BY total_actividades DESC";
    $stmt_actividad = $conn->prepare($sql_actividad);
    $stmt_actividad->bind_param("ssssssssssssss", 
        $param_fecha_inicio, $param_fecha_fin,
        $param_fecha_inicio, $param_fecha_fin,
        $param_fecha_inicio, $param_fecha_fin,
        $param_fecha_inicio, $param_fecha_fin,
        $param_fecha_inicio, $param_fecha_fin,
        $param_fecha_inicio, $param_fecha_fin
    );
}

$stmt_actividad->execute();
$result_actividad = $stmt_actividad->get_result();

// Actividad reciente detallada
$sql_reciente = "
    (
        SELECT 
            m.id,
            m.fecha as fecha_actividad,
            m.cantidad,
            m.estado,
            m.tipo as tipo_actividad,
            u.nombre as usuario_nombre,
            p.nombre as producto_nombre,
            'movimiento' as tipo_registro
        FROM movimientos m
        JOIN usuarios u ON m.usuario_id = u.id
        LEFT JOIN productos p ON m.producto_id = p.id
        WHERE m.fecha BETWEEN ? AND ?
    )
    UNION ALL
    (
        SELECT 
            s.id,
            s.fecha_solicitud as fecha_actividad,
            s.cantidad,
            s.estado,
            'transferencia' as tipo_actividad,
            u.nombre as usuario_nombre,
            p.nombre as producto_nombre,
            'solicitud' as tipo_registro
        FROM solicitudes_transferencia s
        JOIN usuarios u ON s.usuario_id = u.id
        LEFT JOIN productos p ON s.producto_id = p.id
        WHERE s.fecha_solicitud BETWEEN ? AND ?
    )
    ORDER BY fecha_actividad DESC
    LIMIT 100
";

$stmt_reciente = $conn->prepare($sql_reciente);
$stmt_reciente->bind_param("ssss", $param_fecha_inicio, $param_fecha_fin, $param_fecha_inicio, $param_fecha_fin);
$stmt_reciente->execute();
$result_reciente = $stmt_reciente->get_result();

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

// Crear Excel
$spreadsheet = new Spreadsheet();

// === HOJA 1: RESUMEN ===
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Resumen');

// Encabezado
$sheet->setCellValue('A1', 'REPORTE DE ACTIVIDAD DE USUARIOS - GRUPO SEAL');
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
$row += 2;

// Estadísticas generales
$sheet->setCellValue('A' . $row, 'ESTADÍSTICAS DEL PERÍODO');
$sheet->mergeCells('A' . $row . ':B' . $row);
$sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
$row++;

$promedio = $stats['usuarios_activos'] > 0 ? $stats['total_actividades'] / $stats['usuarios_activos'] : 0;

$stats_data = [
    ['Usuarios Activos:', number_format($stats['usuarios_activos'])],
    ['Total Actividades:', number_format($stats['total_actividades'])],
    ['Promedio por Usuario:', number_format($promedio, 1)]
];

foreach ($stats_data as $stat) {
    $sheet->setCellValue('A' . $row, $stat[0]);
    $sheet->setCellValue('B' . $row, $stat[1]);
    $row++;
}

// Autoajustar columnas
$sheet->getColumnDimension('A')->setAutoSize(true);
$sheet->getColumnDimension('B')->setAutoSize(true);

// === HOJA 2: ACTIVIDAD POR USUARIO ===
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Actividad por Usuario');

// Encabezado
$headers = [
    'Usuario', 'Email', 'Rol', 'Almacén', 'Total Actividades', 
    'Completadas', 'Pendientes', '% Éxito', 'Última Actividad'
];

$col = 'A';
foreach ($headers as $header) {
    $sheet2->setCellValue($col . '1', $header);
    $sheet2->getStyle($col . '1')->getFont()->setBold(true);
    $sheet2->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F4FD');
    $col++;
}

// Datos de actividad por usuario
$row = 2;
while ($usuario = $result_actividad->fetch_assoc()) {
    $porcentaje_exito = $usuario['total_actividades'] > 0 ? 
        ($usuario['completadas'] / $usuario['total_actividades']) * 100 : 0;
    
    $ultima_actividad = ($usuario['ultima_actividad'] && $usuario['ultima_actividad'] != '1970-01-01 00:00:00') 
        ? date('d/m/Y H:i', strtotime($usuario['ultima_actividad'])) 
        : 'Sin actividad';
    
    $sheet2->setCellValue('A' . $row, $usuario['usuario_nombre']);
    $sheet2->setCellValue('B' . $row, $usuario['usuario_email']);
    $sheet2->setCellValue('C' . $row, ucfirst($usuario['rol']));
    $sheet2->setCellValue('D' . $row, $usuario['almacen_nombre'] ?? 'N/A');
    $sheet2->setCellValue('E' . $row, $usuario['total_actividades']);
    $sheet2->setCellValue('F' . $row, $usuario['completadas']);
    $sheet2->setCellValue('G' . $row, $usuario['pendientes']);
    $sheet2->setCellValue('H' . $row, number_format($porcentaje_exito, 1) . '%');
    $sheet2->setCellValue('I' . $row, $ultima_actividad);
    
    // Colorear según el nivel de actividad
    if ($usuario['total_actividades'] == 0) {
        $sheet2->getStyle('E' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFCDD2'); // Rojo claro
    } elseif ($usuario['total_actividades'] > 50) {
        $sheet2->getStyle('E' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C8E6C9'); // Verde claro
    } elseif ($usuario['total_actividades'] > 20) {
        $sheet2->getStyle('E' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF3E0'); // Naranja claro
    }
    
    $row++;
}

// Autoajustar columnas
foreach (range('A', 'I') as $column) {
    $sheet2->getColumnDimension($column)->setAutoSize(true);
}

// === HOJA 3: ACTIVIDAD RECIENTE ===
$sheet3 = $spreadsheet->createSheet();
$sheet3->setTitle('Actividad Reciente');

// Encabezado
$headers3 = [
    'ID', 'Fecha', 'Hora', 'Usuario', 'Tipo', 'Producto', 
    'Cantidad', 'Estado', 'Registro'
];

$col = 'A';
foreach ($headers3 as $header) {
    $sheet3->setCellValue($col . '1', $header);
    $sheet3->getStyle($col . '1')->getFont()->setBold(true);
    $sheet3->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F4FD');
    $col++;
}

// Datos de actividad reciente
$row = 2;
while ($actividad = $result_reciente->fetch_assoc()) {
    $fecha_obj = new DateTime($actividad['fecha_actividad']);
    
    $sheet3->setCellValue('A' . $row, '#' . str_pad($actividad['id'], 4, '0', STR_PAD_LEFT));
    $sheet3->setCellValue('B' . $row, $fecha_obj->format('d/m/Y'));
    $sheet3->setCellValue('C' . $row, $fecha_obj->format('H:i:s'));
    $sheet3->setCellValue('D' . $row, $actividad['usuario_nombre']);
    $sheet3->setCellValue('E' . $row, ucfirst($actividad['tipo_actividad']));
    $sheet3->setCellValue('F' . $row, $actividad['producto_nombre']);
    $sheet3->setCellValue('G' . $row, $actividad['cantidad']);
    $sheet3->setCellValue('H' . $row, ucfirst($actividad['estado']));
    $sheet3->setCellValue('I' . $row, ucfirst($actividad['tipo_registro']));
    
    // Colorear según el estado
    $estado_normalizado = $actividad['estado'];
    if ($estado_normalizado == 'aprobada') $estado_normalizado = 'completado';
    if ($estado_normalizado == 'rechazada') $estado_normalizado = 'rechazado';
    
    $estado_color = '';
    switch($estado_normalizado) {
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
        $sheet3->getStyle('H' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($estado_color);
    }
    
    $row++;
}

// Autoajustar columnas
foreach (range('A', 'I') as $column) {
    $sheet3->getColumnDimension($column)->setAutoSize(true);
}

// Configurar descarga
$filename = 'reporte_usuarios_' . $filtro_fecha_inicio . '_' . $filtro_fecha_fin . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>