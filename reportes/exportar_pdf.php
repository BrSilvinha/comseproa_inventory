<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

require_once "../config/database.php";

// Verificar permisos
$usuario_rol = $_SESSION["user_role"] ?? "usuario";
$usuario_almacen_id = $_SESSION["almacen_id"] ?? null;
$user_name = $_SESSION["user_name"] ?? "Usuario";

// Obtener tipo de reporte
$tipo_reporte = $_GET['tipo'] ?? 'inventario';
$almacen_id = isset($_GET['almacen_id']) ? (int)$_GET['almacen_id'] : null;

// Verificar permisos específicos
if ($tipo_reporte == 'usuarios' && $usuario_rol != 'admin') {
    http_response_code(403);
    exit('No tienes permisos para generar este reporte');
}

// Instalar TCPDF: composer require tecnickcom/tcpdf
// Si no tienes composer, puedes descargar TCPDF manualmente
require_once '../vendor/tcpdf/tcpdf.php';

class PDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 20);
        $this->Cell(0, 15, 'GRUPO SEAL - Sistema de Inventario', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(20);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Página '.$this->getAliasNumPage().'/'.$this->getAliasNbPages().' - Generado el '.date('d/m/Y H:i:s'), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// Crear nuevo PDF
$pdf = new PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('Sistema COMSEPROA');
$pdf->SetAuthor($user_name);
$pdf->SetTitle('Reporte de ' . ucfirst($tipo_reporte));
$pdf->SetSubject('Reporte del Sistema de Inventario');

$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

$pdf->AddPage();

switch($tipo_reporte) {
    case 'inventario':
        generarReporteInventario($pdf, $conn, $almacen_id, $usuario_rol, $usuario_almacen_id);
        break;
    case 'movimientos':
        generarReporteMovimientos($pdf, $conn, $usuario_rol, $usuario_almacen_id);
        break;
    case 'usuarios':
        generarReporteUsuarios($pdf, $conn);
        break;
    default:
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'Tipo de reporte no válido', 0, 1);
}

// Salida del PDF
$filename = 'reporte_' . $tipo_reporte . '_' . date('Y-m-d_H-i-s') . '.pdf';
$pdf->Output($filename, 'D');

function generarReporteInventario($pdf, $conn, $almacen_id, $usuario_rol, $usuario_almacen_id) {
    // Verificar permisos
    if ($usuario_rol != 'admin' && $almacen_id && $usuario_almacen_id != $almacen_id) {
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'No tienes permiso para ver este reporte', 0, 1);
        return;
    }

    // Título del reporte
    $pdf->SetFont('helvetica', 'B', 16);
    if ($almacen_id) {
        // Obtener info del almacén
        $stmt = $conn->prepare("SELECT nombre, ubicacion FROM almacenes WHERE id = ?");
        $stmt->bind_param("i", $almacen_id);
        $stmt->execute();
        $almacen_info = $stmt->get_result()->fetch_assoc();
        $pdf->Cell(0, 10, 'Reporte de Inventario - ' . $almacen_info['nombre'], 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'Ubicación: ' . $almacen_info['ubicacion'], 0, 1, 'C');
    } else {
        $pdf->Cell(0, 10, 'Reporte de Inventario General', 0, 1, 'C');
    }
    
    $pdf->Ln(10);

    // Estadísticas generales
    if ($almacen_id) {
        $sql_stats = "SELECT COUNT(DISTINCT p.categoria_id) as total_categorias, COUNT(p.id) as total_productos, 
                      COALESCE(SUM(p.cantidad), 0) as total_stock, COALESCE(AVG(p.cantidad), 0) as promedio_stock 
                      FROM productos p WHERE p.almacen_id = ?";
        $stmt = $conn->prepare($sql_stats);
        $stmt->bind_param("i", $almacen_id);
    } else {
        $sql_stats = "SELECT COUNT(DISTINCT p.categoria_id) as total_categorias, COUNT(p.id) as total_productos, 
                      COALESCE(SUM(p.cantidad), 0) as total_stock, COALESCE(AVG(p.cantidad), 0) as promedio_stock 
                      FROM productos p";
        $stmt = $conn->prepare($sql_stats);
    }
    
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();

    // Mostrar estadísticas
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Estadísticas Generales', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    
    $pdf->Cell(50, 6, 'Total Productos:', 0, 0);
    $pdf->Cell(40, 6, number_format($stats['total_productos']), 0, 1);
    
    $pdf->Cell(50, 6, 'Total Categorías:', 0, 0);
    $pdf->Cell(40, 6, number_format($stats['total_categorias']), 0, 1);
    
    $pdf->Cell(50, 6, 'Stock Total:', 0, 0);
    $pdf->Cell(40, 6, number_format($stats['total_stock']) . ' unidades', 0, 1);
    
    $pdf->Cell(50, 6, 'Promedio por Producto:', 0, 0);
    $pdf->Cell(40, 6, number_format($stats['promedio_stock'], 1) . ' unidades', 0, 1);
    
    $pdf->Ln(10);

    // Productos con stock crítico
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Productos con Stock Crítico (< 10 unidades)', 0, 1);
    
    if ($almacen_id) {
        $sql_critico = "SELECT p.nombre, p.cantidad, c.nombre as categoria 
                        FROM productos p JOIN categorias c ON p.categoria_id = c.id 
                        WHERE p.almacen_id = ? AND p.cantidad < 10 ORDER BY p.cantidad ASC";
        $stmt = $conn->prepare($sql_critico);
        $stmt->bind_param("i", $almacen_id);
    } else {
        $sql_critico = "SELECT p.nombre, p.cantidad, c.nombre as categoria, a.nombre as almacen 
                        FROM productos p JOIN categorias c ON p.categoria_id = c.id 
                        JOIN almacenes a ON p.almacen_id = a.id 
                        WHERE p.cantidad < 10 ORDER BY p.cantidad ASC";
        $stmt = $conn->prepare($sql_critico);
    }
    
    $stmt->execute();
    $productos_criticos = $stmt->get_result();

    // Tabla de productos críticos
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(60, 6, 'Producto', 1, 0, 'C');
    $pdf->Cell(40, 6, 'Categoría', 1, 0, 'C');
    if (!$almacen_id) $pdf->Cell(40, 6, 'Almacén', 1, 0, 'C');
    $pdf->Cell(25, 6, 'Stock', 1, 1, 'C');

    $pdf->SetFont('helvetica', '', 8);
    while ($prod = $productos_criticos->fetch_assoc()) {
        $pdf->Cell(60, 5, substr($prod['nombre'], 0, 35), 1, 0);
        $pdf->Cell(40, 5, substr($prod['categoria'], 0, 20), 1, 0);
        if (!$almacen_id) $pdf->Cell(40, 5, substr($prod['almacen'], 0, 20), 1, 0);
        $pdf->Cell(25, 5, $prod['cantidad'], 1, 1, 'C');
    }
    
    if ($productos_criticos->num_rows == 0) {
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->Cell(0, 6, 'No hay productos con stock crítico', 0, 1, 'C');
    }
}

function generarReporteMovimientos($pdf, $conn, $usuario_rol, $usuario_almacen_id) {
    // Obtener filtros de la URL o usar valores por defecto
    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
    $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
    
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Reporte de Movimientos', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Período: ' . $fecha_inicio . ' al ' . $fecha_fin, 0, 1, 'C');
    $pdf->Ln(10);

    // Estadísticas
    $param_fecha_inicio = $fecha_inicio . ' 00:00:00';
    $param_fecha_fin = $fecha_fin . ' 23:59:59';
    
    $sql_stats = "SELECT COUNT(*) as total_movimientos,
                  SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completados,
                  SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes
                  FROM movimientos WHERE fecha BETWEEN ? AND ?";
    
    if ($usuario_rol != 'admin') {
        $sql_stats .= " AND (almacen_origen = ? OR almacen_destino = ?)";
        $stmt = $conn->prepare($sql_stats);
        $stmt->bind_param("ssii", $param_fecha_inicio, $param_fecha_fin, $usuario_almacen_id, $usuario_almacen_id);
    } else {
        $stmt = $conn->prepare($sql_stats);
        $stmt->bind_param("ss", $param_fecha_inicio, $param_fecha_fin);
    }
    
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();

    // Mostrar estadísticas
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Estadísticas del Período', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    
    $pdf->Cell(40, 6, 'Total Movimientos:', 0, 0);
    $pdf->Cell(30, 6, number_format($stats['total_movimientos']), 0, 1);
    
    $pdf->Cell(40, 6, 'Completados:', 0, 0);
    $pdf->Cell(30, 6, number_format($stats['completados']), 0, 1);
    
    $pdf->Cell(40, 6, 'Pendientes:', 0, 0);
    $pdf->Cell(30, 6, number_format($stats['pendientes']), 0, 1);
    
    $pdf->Ln(10);

    // Tabla de movimientos recientes
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Movimientos Recientes', 0, 1);
    
    $sql_movimientos = "SELECT m.fecha, m.cantidad, m.estado, m.tipo, p.nombre as producto, 
                        ao.nombre as origen, ad.nombre as destino, u.nombre as usuario
                        FROM movimientos m
                        LEFT JOIN productos p ON m.producto_id = p.id
                        LEFT JOIN almacenes ao ON m.almacen_origen = ao.id
                        LEFT JOIN almacenes ad ON m.almacen_destino = ad.id
                        LEFT JOIN usuarios u ON m.usuario_id = u.id
                        WHERE m.fecha BETWEEN ? AND ?";
    
    if ($usuario_rol != 'admin') {
        $sql_movimientos .= " AND (m.almacen_origen = ? OR m.almacen_destino = ?)";
    }
    
    $sql_movimientos .= " ORDER BY m.fecha DESC LIMIT 20";
    
    if ($usuario_rol != 'admin') {
        $stmt = $conn->prepare($sql_movimientos);
        $stmt->bind_param("ssii", $param_fecha_inicio, $param_fecha_fin, $usuario_almacen_id, $usuario_almacen_id);
    } else {
        $stmt = $conn->prepare($sql_movimientos);
        $stmt->bind_param("ss", $param_fecha_inicio, $param_fecha_fin);
    }
    
    $stmt->execute();
    $movimientos = $stmt->get_result();

    // Cabecera de tabla
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(25, 6, 'Fecha', 1, 0, 'C');
    $pdf->Cell(35, 6, 'Producto', 1, 0, 'C');
    $pdf->Cell(20, 6, 'Cantidad', 1, 0, 'C');
    $pdf->Cell(25, 6, 'Origen', 1, 0, 'C');
    $pdf->Cell(25, 6, 'Destino', 1, 0, 'C');
    $pdf->Cell(20, 6, 'Estado', 1, 0, 'C');
    $pdf->Cell(30, 6, 'Usuario', 1, 1, 'C');

    $pdf->SetFont('helvetica', '', 7);
    while ($mov = $movimientos->fetch_assoc()) {
        $pdf->Cell(25, 5, date('d/m/Y', strtotime($mov['fecha'])), 1, 0);
        $pdf->Cell(35, 5, substr($mov['producto'], 0, 20), 1, 0);
        $pdf->Cell(20, 5, number_format($mov['cantidad']), 1, 0, 'C');
        $pdf->Cell(25, 5, substr($mov['origen'] ?? 'Sistema', 0, 15), 1, 0);
        $pdf->Cell(25, 5, substr($mov['destino'] ?? 'Sistema', 0, 15), 1, 0);
        $pdf->Cell(20, 5, $mov['estado'], 1, 0, 'C');
        $pdf->Cell(30, 5, substr($mov['usuario'], 0, 18), 1, 1);
    }
}

function generarReporteUsuarios($pdf, $conn) {
    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
    $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
    
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Reporte de Actividad de Usuarios', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Período: ' . $fecha_inicio . ' al ' . $fecha_fin, 0, 1, 'C');
    $pdf->Ln(10);

    $param_fecha_inicio = $fecha_inicio . ' 00:00:00';
    $param_fecha_fin = $fecha_fin . ' 23:59:59';

    // Consulta de actividad por usuario
    $sql_usuarios = "SELECT u.nombre, u.correo, u.rol, a.nombre as almacen,
                     (SELECT COUNT(*) FROM movimientos m WHERE m.usuario_id = u.id AND m.fecha BETWEEN ? AND ?) +
                     (SELECT COUNT(*) FROM solicitudes_transferencia s WHERE s.usuario_id = u.id AND s.fecha_solicitud BETWEEN ? AND ?) as total_actividades
                     FROM usuarios u
                     LEFT JOIN almacenes a ON u.almacen_id = a.id
                     WHERE u.estado = 'activo'
                     ORDER BY total_actividades DESC";
    
    $stmt = $conn->prepare($sql_usuarios);
    $stmt->bind_param("ssss", $param_fecha_inicio, $param_fecha_fin, $param_fecha_inicio, $param_fecha_fin);
    $stmt->execute();
    $usuarios = $stmt->get_result();

    // Tabla de usuarios
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(45, 6, 'Usuario', 1, 0, 'C');
    $pdf->Cell(55, 6, 'Email', 1, 0, 'C');
    $pdf->Cell(20, 6, 'Rol', 1, 0, 'C');
    $pdf->Cell(40, 6, 'Almacén', 1, 0, 'C');
    $pdf->Cell(20, 6, 'Actividades', 1, 1, 'C');

    $pdf->SetFont('helvetica', '', 8);
    while ($usuario = $usuarios->fetch_assoc()) {
        $pdf->Cell(45, 5, substr($usuario['nombre'], 0, 25), 1, 0);
        $pdf->Cell(55, 5, substr($usuario['correo'], 0, 30), 1, 0);
        $pdf->Cell(20, 5, $usuario['rol'], 1, 0, 'C');
        $pdf->Cell(40, 5, substr($usuario['almacen'] ?? 'N/A', 0, 20), 1, 0);
        $pdf->Cell(20, 5, number_format($usuario['total_actividades']), 1, 1, 'C');
    }
}
?>