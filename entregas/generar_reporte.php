<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

require_once "../config/database.php";

$usuario_rol = $_SESSION["user_role"] ?? "usuario";
$usuario_almacen_id = $_SESSION["almacen_id"] ?? null;

// Obtener parámetros
$formato = $_GET['formato'] ?? 'pdf'; // pdf o excel
$filtro_almacen_id = isset($_GET['almacen_id']) ? (int)$_GET['almacen_id'] : null;
$filtro_categoria_id = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : null;

// Verificar permisos
if ($filtro_almacen_id && $usuario_rol != 'admin' && $usuario_almacen_id != $filtro_almacen_id) {
    die("No tienes permisos para generar reportes de este almacén");
}

// Determinar almacén
$almacen_id_reporte = null;
if ($usuario_rol == 'admin') {
    $almacen_id_reporte = $filtro_almacen_id;
} else {
    $almacen_id_reporte = $usuario_almacen_id;
}

if (!$almacen_id_reporte) {
    die("Debe especificar un almacén para generar el reporte");
}

// Preparar filtros para la consulta
$filtros = [
    'dni' => $_GET['dni'] ?? '',
    'nombre' => $_GET['nombre'] ?? '',
    'fecha_inicio' => $_GET['fecha_inicio'] ?? '',
    'fecha_fin' => $_GET['fecha_fin'] ?? ''
];

// Función para obtener entregas para el reporte
function obtenerEntregasReporte($conn, $almacen_id, $categoria_id = null, $filtros = []) {
    $query = '
        SELECT 
            eu.id,
            eu.nombre_destinatario,
            eu.dni_destinatario,
            eu.fecha_entrega,
            p.nombre as producto_nombre,
            p.modelo,
            p.color,
            p.talla_dimensiones,
            eu.cantidad,
            p.unidad_medida,
            a.nombre as almacen_nombre,
            a.ubicacion as almacen_ubicacion,
            u.nombre as usuario_responsable,
            c.nombre as categoria_nombre
        FROM 
            entrega_uniformes eu
        JOIN 
            productos p ON eu.producto_id = p.id
        JOIN 
            almacenes a ON eu.almacen_id = a.id
        JOIN
            categorias c ON p.categoria_id = c.id
        LEFT JOIN
            usuarios u ON eu.usuario_responsable_id = u.id
        WHERE 
            eu.almacen_id = ?
    ';

    $params = [$almacen_id];
    $types = 'i';

    // Filtro por categoría
    if ($categoria_id) {
        $query .= ' AND p.categoria_id = ?';
        $params[] = $categoria_id;
        $types .= 'i';
    }

    // Otros filtros
    if (!empty($filtros['dni'])) {
        $query .= ' AND eu.dni_destinatario LIKE ?';
        $params[] = '%' . $filtros['dni'] . '%';
        $types .= 's';
    }

    if (!empty($filtros['nombre'])) {
        $query .= ' AND eu.nombre_destinatario LIKE ?';
        $params[] = '%' . $filtros['nombre'] . '%';
        $types .= 's';
    }

    if (!empty($filtros['fecha_inicio'])) {
        $query .= ' AND DATE(eu.fecha_entrega) >= ?';
        $params[] = $filtros['fecha_inicio'];
        $types .= 's';
    }
    if (!empty($filtros['fecha_fin'])) {
        $query .= ' AND DATE(eu.fecha_entrega) <= ?';
        $params[] = $filtros['fecha_fin'];
        $types .= 's';
    }

    $query .= ' ORDER BY eu.fecha_entrega DESC, eu.nombre_destinatario';
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param($types, ...$params);
    
    if (!$stmt->execute()) {
        return [];
    }
    
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Obtener información del almacén
$sql_almacen = "SELECT * FROM almacenes WHERE id = ?";
$stmt = $conn->prepare($sql_almacen);
$stmt->bind_param("i", $almacen_id_reporte);
$stmt->execute();
$almacen_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Obtener información de la categoría si existe
$categoria_info = null;
if ($filtro_categoria_id) {
    $sql_categoria = "SELECT * FROM categorias WHERE id = ?";
    $stmt = $conn->prepare($sql_categoria);
    $stmt->bind_param("i", $filtro_categoria_id);
    $stmt->execute();
    $categoria_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Obtener datos para el reporte
$entregas = obtenerEntregasReporte($conn, $almacen_id_reporte, $filtro_categoria_id, $filtros);

if (empty($entregas)) {
    die("No hay datos para generar el reporte con los filtros seleccionados");
}

// Generar reporte según el formato
if ($formato === 'excel') {
    generarReporteExcel($entregas, $almacen_info, $categoria_info, $filtros);
} else {
    generarReportePDF($entregas, $almacen_info, $categoria_info, $filtros);
}

// Función para generar reporte en Excel
function generarReporteExcel($entregas, $almacen_info, $categoria_info, $filtros) {
    // Verificar si PhpSpreadsheet está disponible
    if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        // Fallback: generar CSV si no hay PhpSpreadsheet
        generarReporteCSV($entregas, $almacen_info, $categoria_info, $filtros);
        return;
    }
    
    require_once '../vendor/autoload.php'; // Ajustar ruta según tu instalación
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Configurar título
    $titulo = "Reporte de Entregas - " . $almacen_info['nombre'];
    if ($categoria_info) {
        $titulo .= " - " . $categoria_info['nombre'];
    }
    
    $sheet->setCellValue('A1', $titulo);
    $sheet->mergeCells('A1:L1');
    
    // Información del reporte
    $fila = 3;
    $sheet->setCellValue('A' . $fila, 'Almacén:');
    $sheet->setCellValue('B' . $fila, $almacen_info['nombre']);
    $fila++;
    
    $sheet->setCellValue('A' . $fila, 'Ubicación:');
    $sheet->setCellValue('B' . $fila, $almacen_info['ubicacion']);
    $fila++;
    
    if ($categoria_info) {
        $sheet->setCellValue('A' . $fila, 'Categoría:');
        $sheet->setCellValue('B' . $fila, $categoria_info['nombre']);
        $fila++;
    }
    
    $sheet->setCellValue('A' . $fila, 'Fecha del reporte:');
    $sheet->setCellValue('B' . $fila, date('d/m/Y H:i'));
    $fila++;
    
    $sheet->setCellValue('A' . $fila, 'Total de entregas:');
    $sheet->setCellValue('B' . $fila, count($entregas));
    $fila += 2;
    
    // Encabezados
    $encabezados = [
        'Fecha Entrega', 'Destinatario', 'DNI', 'Categoría', 'Producto', 
        'Modelo', 'Color', 'Talla', 'Cantidad', 'Unidad', 'Responsable'
    ];
    
    $columna = 'A';
    foreach ($encabezados as $encabezado) {
        $sheet->setCellValue($columna . $fila, $encabezado);
        $columna++;
    }
    $fila++;
    
    // Datos
    foreach ($entregas as $entrega) {
        $sheet->setCellValue('A' . $fila, date('d/m/Y H:i', strtotime($entrega['fecha_entrega'])));
        $sheet->setCellValue('B' . $fila, $entrega['nombre_destinatario']);
        $sheet->setCellValue('C' . $fila, $entrega['dni_destinatario']);
        $sheet->setCellValue('D' . $fila, $entrega['categoria_nombre']);
        $sheet->setCellValue('E' . $fila, $entrega['producto_nombre']);
        $sheet->setCellValue('F' . $fila, $entrega['modelo'] ?: '-');
        $sheet->setCellValue('G' . $fila, $entrega['color'] ?: '-');
        $sheet->setCellValue('H' . $fila, $entrega['talla_dimensiones'] ?: '-');
        $sheet->setCellValue('I' . $fila, $entrega['cantidad']);
        $sheet->setCellValue('J' . $fila, $entrega['unidad_medida']);
        $sheet->setCellValue('K' . $fila, $entrega['usuario_responsable'] ?: 'No registrado');
        $fila++;
    }
    
    // Configurar estilo
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A' . ($fila - count($entregas) - 1) . ':K' . ($fila - count($entregas) - 1))->getFont()->setBold(true);
    
    // Autoajustar columnas
    foreach (range('A', 'K') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Generar archivo
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    
    $filename = 'reporte_entregas_' . $almacen_info['nombre'] . '_' . date('Y-m-d_H-i-s') . '.xlsx';
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer->save('php://output');
    exit();
}

// Función para generar reporte en CSV (fallback)
function generarReporteCSV($entregas, $almacen_info, $categoria_info, $filtros) {
    $filename = 'reporte_entregas_' . $almacen_info['nombre'] . '_' . date('Y-m-d_H-i-s') . '.csv';
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Información del reporte
    fputcsv($output, ['REPORTE DE ENTREGAS - ' . $almacen_info['nombre']]);
    fputcsv($output, []);
    fputcsv($output, ['Almacén:', $almacen_info['nombre']]);
    fputcsv($output, ['Ubicación:', $almacen_info['ubicacion']]);
    if ($categoria_info) {
        fputcsv($output, ['Categoría:', $categoria_info['nombre']]);
    }
    fputcsv($output, ['Fecha del reporte:', date('d/m/Y H:i')]);
    fputcsv($output, ['Total de entregas:', count($entregas)]);
    fputcsv($output, []);
    
    // Encabezados
    fputcsv($output, [
        'Fecha Entrega', 'Destinatario', 'DNI', 'Categoría', 'Producto',
        'Modelo', 'Color', 'Talla', 'Cantidad', 'Unidad', 'Responsable'
    ]);
    
    // Datos
    foreach ($entregas as $entrega) {
        fputcsv($output, [
            date('d/m/Y H:i', strtotime($entrega['fecha_entrega'])),
            $entrega['nombre_destinatario'],
            $entrega['dni_destinatario'],
            $entrega['categoria_nombre'],
            $entrega['producto_nombre'],
            $entrega['modelo'] ?: '-',
            $entrega['color'] ?: '-',
            $entrega['talla_dimensiones'] ?: '-',
            $entrega['cantidad'],
            $entrega['unidad_medida'],
            $entrega['usuario_responsable'] ?: 'No registrado'
        ]);
    }
    
    fclose($output);
    exit();
}

// Función para generar reporte en PDF
function generarReportePDF($entregas, $almacen_info, $categoria_info, $filtros) {
    // Si TCPDF no está disponible, usar HTML simple
    if (!class_exists('TCPDF')) {
        generarReporteHTML($entregas, $almacen_info, $categoria_info, $filtros);
        return;
    }
    
    require_once '../vendor/tcpdf/tcpdf.php'; // Ajustar ruta según tu instalación
    
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Configuración del documento
    $pdf->SetCreator('Sistema GRUPO SEAL');
    $pdf->SetAuthor('GRUPO SEAL');
    $pdf->SetTitle('Reporte de Entregas');
    $pdf->SetSubject('Historial de Entregas');
    
    // Configurar página
    $pdf->SetHeaderData('', 0, 'GRUPO SEAL', 'Reporte de Entregas');
    $pdf->setHeaderFont(['helvetica', '', 12]);
    $pdf->setFooterFont(['helvetica', '', 8]);
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(15, 27, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(TRUE, 25);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    
    $pdf->AddPage();
    
    // Título
    $pdf->SetFont('helvetica', 'B', 16);
    $titulo = 'Reporte de Entregas - ' . $almacen_info['nombre'];
    if ($categoria_info) {
        $titulo .= "\n" . $categoria_info['nombre'];
    }
    $pdf->Cell(0, 10, $titulo, 0, 1, 'C');
    $pdf->Ln(5);
    
    // Información del reporte
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(30, 6, 'Almacén:', 0, 0, 'L');
    $pdf->Cell(0, 6, $almacen_info['nombre'], 0, 1, 'L');
    $pdf->Cell(30, 6, 'Ubicación:', 0, 0, 'L');
    $pdf->Cell(0, 6, $almacen_info['ubicacion'], 0, 1, 'L');
    
    if ($categoria_info) {
        $pdf->Cell(30, 6, 'Categoría:', 0, 0, 'L');
        $pdf->Cell(0, 6, $categoria_info['nombre'], 0, 1, 'L');
    }
    
    $pdf->Cell(30, 6, 'Fecha:', 0, 0, 'L');
    $pdf->Cell(0, 6, date('d/m/Y H:i'), 0, 1, 'L');
    $pdf->Cell(30, 6, 'Total entregas:', 0, 0, 'L');
    $pdf->Cell(0, 6, count($entregas), 0, 1, 'L');
    $pdf->Ln(5);
    
    // Tabla de entregas
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(20, 8, 'Fecha', 1, 0, 'C');
    $pdf->Cell(35, 8, 'Destinatario', 1, 0, 'C');
    $pdf->Cell(20, 8, 'DNI', 1, 0, 'C');
    $pdf->Cell(30, 8, 'Categoría', 1, 0, 'C');
    $pdf->Cell(40, 8, 'Producto', 1, 0, 'C');
    $pdf->Cell(15, 8, 'Cant.', 1, 0, 'C');
    $pdf->Cell(20, 8, 'Responsable', 1, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 7);
    foreach ($entregas as $entrega) {
        // Verificar si necesita nueva página
        if ($pdf->GetY() > 250) {
            $pdf->AddPage();
            // Repetir encabezados
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(20, 8, 'Fecha', 1, 0, 'C');
            $pdf->Cell(35, 8, 'Destinatario', 1, 0, 'C');
            $pdf->Cell(20, 8, 'DNI', 1, 0, 'C');
            $pdf->Cell(30, 8, 'Categoría', 1, 0, 'C');
            $pdf->Cell(40, 8, 'Producto', 1, 0, 'C');
            $pdf->Cell(15, 8, 'Cant.', 1, 0, 'C');
            $pdf->Cell(20, 8, 'Responsable', 1, 1, 'C');
            $pdf->SetFont('helvetica', '', 7);
        }
        
        $pdf->Cell(20, 6, date('d/m/Y', strtotime($entrega['fecha_entrega'])), 1, 0, 'C');
        $pdf->Cell(35, 6, substr($entrega['nombre_destinatario'], 0, 25), 1, 0, 'L');
        $pdf->Cell(20, 6, $entrega['dni_destinatario'], 1, 0, 'C');
        $pdf->Cell(30, 6, substr($entrega['categoria_nombre'], 0, 20), 1, 0, 'L');
        $pdf->Cell(40, 6, substr($entrega['producto_nombre'], 0, 30), 1, 0, 'L');
        $pdf->Cell(15, 6, $entrega['cantidad'], 1, 0, 'C');
        $pdf->Cell(20, 6, substr($entrega['usuario_responsable'] ?: 'N/R', 0, 15), 1, 1, 'L');
    }
    
    // Generar archivo
    $filename = 'reporte_entregas_' . $almacen_info['nombre'] . '_' . date('Y-m-d_H-i-s') . '.pdf';
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    
    $pdf->Output($filename, 'D');
    exit();
}

// Función para generar reporte HTML (fallback)
function generarReporteHTML($entregas, $almacen_info, $categoria_info, $filtros) {
    $filename = 'reporte_entregas_' . $almacen_info['nombre'] . '_' . date('Y-m-d_H-i-s') . '.html';
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Reporte de Entregas</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .info { margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .center { text-align: center; }
            @media print { body { margin: 0; } }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Reporte de Entregas</h1>
            <h2>' . htmlspecialchars($almacen_info['nombre']) . '</h2>';
    
    if ($categoria_info) {
        echo '<h3>' . htmlspecialchars($categoria_info['nombre']) . '</h3>';
    }
    
    echo '</div>
        <div class="info">
            <p><strong>Almacén:</strong> ' . htmlspecialchars($almacen_info['nombre']) . '</p>
            <p><strong>Ubicación:</strong> ' . htmlspecialchars($almacen_info['ubicacion']) . '</p>';
    
    if ($categoria_info) {
        echo '<p><strong>Categoría:</strong> ' . htmlspecialchars($categoria_info['nombre']) . '</p>';
    }
    
    echo '<p><strong>Fecha del reporte:</strong> ' . date('d/m/Y H:i') . '</p>
            <p><strong>Total de entregas:</strong> ' . count($entregas) . '</p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Destinatario</th>
                    <th>DNI</th>
                    <th>Categoría</th>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Responsable</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($entregas as $entrega) {
        echo '<tr>
                <td class="center">' . date('d/m/Y H:i', strtotime($entrega['fecha_entrega'])) . '</td>
                <td>' . htmlspecialchars($entrega['nombre_destinatario']) . '</td>
                <td class="center">' . htmlspecialchars($entrega['dni_destinatario']) . '</td>
                <td>' . htmlspecialchars($entrega['categoria_nombre']) . '</td>
                <td>' . htmlspecialchars($entrega['producto_nombre']) . '</td>
                <td class="center">' . $entrega['cantidad'] . ' ' . htmlspecialchars($entrega['unidad_medida']) . '</td>
                <td>' . htmlspecialchars($entrega['usuario_responsable'] ?: 'No registrado') . '</td>
              </tr>';
    }
    
    echo '</tbody>
        </table>
    </body>
    </html>';
    
    exit();
}
?>