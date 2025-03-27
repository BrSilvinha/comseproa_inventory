<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: /views/login_form.php");
    exit();
}

// Prevent session hijacking
session_regenerate_id(true);

$user_name = isset($_SESSION["user_name"]) ? $_SESSION["user_name"] : "Usuario";
$usuario_almacen_id = isset($_SESSION["almacen_id"]) ? $_SESSION["almacen_id"] : null;
$usuario_rol = isset($_SESSION["user_role"]) ? $_SESSION["user_role"] : "usuario";

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection configuration
require_once "../config/database.php";

// Consultar almacenes
if ($usuario_rol == 'admin') {
    $sql_almacenes = "SELECT id, nombre, ubicacion FROM almacenes ORDER BY id DESC";
    $result_almacenes = $conn->query($sql_almacenes);
} else {
    // Si no es admin, mostrar solo el almacén asignado
    if ($usuario_almacen_id) {
        $sql_almacenes = "SELECT id, nombre, ubicacion FROM almacenes WHERE id = ?";
        $stmt_almacenes = $conn->prepare($sql_almacenes);
        $stmt_almacenes->bind_param("i", $usuario_almacen_id);
        $stmt_almacenes->execute();
        $result_almacenes = $stmt_almacenes->get_result();
    } else {
        $result_almacenes = false;
    }
}

// Variable para almacenar entregas
$entregas = [];
$error_mensaje = '';

// Función para obtener entregas por almacén
function obtenerEntregasPorAlmacen($conn, $almacen_id, $filtros = []) {
    $query = '
        SELECT 
            eu.id,
            eu.nombre_destinatario,
            eu.dni_destinatario,
            eu.fecha_entrega,
            p.nombre as producto_nombre,
            eu.cantidad,
            a.nombre as almacen_nombre
        FROM 
            entrega_uniformes eu
        JOIN 
            productos p ON eu.producto_id = p.id
        JOIN 
            almacenes a ON eu.almacen_id = a.id
        WHERE 
            eu.almacen_id = ?
    ';

    $params = [$almacen_id];

    // Agregar filtros
    if (!empty($filtros['dni'])) {
        $query .= ' AND eu.dni_destinatario LIKE ?';
        $params[] = '%' . $filtros['dni'] . '%';
    }

    // Filtros de fecha
    if (!empty($filtros['fecha_inicio'])) {
        $query .= ' AND eu.fecha_entrega >= ?';
        $params[] = $filtros['fecha_inicio'];
    }
    if (!empty($filtros['fecha_fin'])) {
        $query .= ' AND eu.fecha_entrega <= ?';
        $params[] = $filtros['fecha_fin'];
    }

    $query .= ' ORDER BY eu.fecha_entrega DESC';

    $stmt = $conn->prepare($query);
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $entregasAgrupadas = [];
    while ($row = $result->fetch_assoc()) {
        $key = $row['fecha_entrega'] . '|' . $row['nombre_destinatario'] . '|' . $row['dni_destinatario'] . '|' . $row['almacen_nombre'];
        
        if (!isset($entregasAgrupadas[$key])) {
            $entregasAgrupadas[$key] = [
                'fecha_entrega' => $row['fecha_entrega'],
                'nombre_destinatario' => $row['nombre_destinatario'],
                'dni_destinatario' => $row['dni_destinatario'],
                'almacen_nombre' => $row['almacen_nombre'],
                'productos' => []
            ];
        }
        
        $productoExistente = false;
        foreach ($entregasAgrupadas[$key]['productos'] as &$producto) {
            if ($producto['nombre'] === $row['producto_nombre']) {
                $producto['cantidad'] += $row['cantidad'];
                $productoExistente = true;
                break;
            }
        }
        
        if (!$productoExistente) {
            $entregasAgrupadas[$key]['productos'][] = [
                'nombre' => $row['producto_nombre'],
                'cantidad' => $row['cantidad']
            ];
        }
    }

    return array_values($entregasAgrupadas);
}

// Preparar filtros
$filtros = [];
if (isset($_GET['dni']) || isset($_GET['fecha_inicio']) || isset($_GET['fecha_fin'])) {
    $filtros = [
        'dni' => $_GET['dni'] ?? '',
        'fecha_inicio' => $_GET['fecha_inicio'] ?? '',
        'fecha_fin' => $_GET['fecha_fin'] ?? ''
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Entregas de Uniformes - COMSEPROA</title>
    <link rel="stylesheet" href="../assets/css/styles-dashboard.css">
    <link rel="stylesheet" href="../assets/css/uniform-delivery-history.css">
    <link rel="stylesheet" href="../assets/css/styles-almacenes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <nav class="sidebar" id="sidebar">
    <h2>GRUPO SEAL</h2>
    <ul>
        <li><a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a></li>

        <!-- Users - Only visible to administrators -->
        <?php if ($usuario_rol == 'admin'): ?>
        <li class="submenu-container">
            <a href="#" aria-label="Menú Usuarios">
                <i class="fas fa-users"></i> Usuarios <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <li><a href="../usuarios/registrar.php"><i class="fas fa-user-plus"></i> Registrar Usuario</a></li>
                <li><a href="../usuarios/listar.php"><i class="fas fa-list"></i> Lista de Usuarios</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- Warehouses - Adjusted according to permissions -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Almacenes">
                <i class="fas fa-warehouse"></i> Almacenes <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <?php if ($usuario_rol == 'admin'): ?>
                <li><a href="../almacenes/registrar.php"><i class="fas fa-plus"></i> Registrar Almacén</a></li>
                <?php endif; ?>
                <li><a href="../almacenes/listar.php"><i class="fas fa-list"></i> Lista de Almacenes</a></li>
            </ul>
        </li>
        
        <!-- Notifications -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Notificaciones">
                <i class="fas fa-bell"></i> Notificaciones <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <li><a href="../notificaciones/pendientes.php"><i class="fas fa-clock"></i> Solicitudes Pendientes 
                <?php 
                // Count pending requests to show in badge
                $sql_pendientes = "SELECT COUNT(*) as total FROM solicitudes_transferencia WHERE estado = 'pendiente'";
                
                // If user is not admin, filter by their warehouse
                if ($usuario_rol != 'admin') {
                    $sql_pendientes .= " AND almacen_destino = ?";
                    $stmt_pendientes = $conn->prepare($sql_pendientes);
                    $stmt_pendientes->bind_param("i", $usuario_almacen_id);
                    $stmt_pendientes->execute();
                    $result_pendientes = $stmt_pendientes->get_result();
                } else {
                    $result_pendientes = $conn->query($sql_pendientes);
                }
                
                if ($result_pendientes && $row_pendientes = $result_pendientes->fetch_assoc()) {
                    echo '<span class="badge">' . $row_pendientes['total'] . '</span>';
                }
                ?>
                </a></li>
                <li><a href="../notificaciones/historial.php"><i class="fas fa-list"></i> Historial de Solicitudes</a></li>
                <li><a href="../uniformes/historial_entregas_uniformes.php"><i class="fas fa-tshirt"></i> Historial de Entregas de Uniformes</a></li>
            </ul>
        </li>

        <!-- Logout -->
        <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a></li>
    </ul>
</nav>


    <!-- Main Content -->
    <main class="content" id="main-content">
        <h2>Historial de Entregas de Uniformes</h2>

        <?php if ($usuario_rol == 'admin'): ?>
            <div class="almacenes-container">
                <?php if ($result_almacenes && $result_almacenes->num_rows > 0): ?>
                    <?php while ($almacen = $result_almacenes->fetch_assoc()): ?>
                        <div class="almacen-card">
                            <h3><?php echo htmlspecialchars($almacen["nombre"]); ?></h3>
                            <p><strong>Ubicación:</strong> <?php echo htmlspecialchars($almacen["ubicacion"]); ?></p>
                            <a href="#" class="btn-ver mostrar-entregas" data-almacen-id="<?php echo $almacen['id']; ?>">
                                <i class="fas fa-eye"></i> Ver Entregas
                            </a>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No hay almacenes registrados.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div id="contenedor-historial-entregas" style="<?php echo $usuario_rol != 'admin' ? 'display:block;' : 'display:none;'; ?>">
            <form method="GET" class="uniform-filter-form" id="formulario-filtros">
                <div class="row">
                    <div class="col-md-4">
                        <label for="filtro-dni" class="form-label">Filtrar por DNI</label>
                        <input type="text" class="form-control" id="filtro-dni" name="dni" 
                               placeholder="Número de DNI" 
                               value="<?php echo htmlspecialchars($_GET['dni'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="filtro-fecha-inicio" class="form-label">Fecha de Inicio</label>
                        <input type="date" class="form-control" id="filtro-fecha-inicio" 
                               name="fecha_inicio"
                               value="<?php echo htmlspecialchars($_GET['fecha_inicio'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="filtro-fecha-fin" class="form-label">Fecha de Fin</label>
                        <input type="date" class="form-control" id="filtro-fecha-fin" 
                               name="fecha_fin"
                               value="<?php echo htmlspecialchars($_GET['fecha_fin'] ?? ''); ?>">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Buscar</button>
                    <a href="?" class="btn btn-secondary">Limpiar Filtros</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table uniform-delivery-table" id="tabla-historial-entregas">
                    <thead>
                        <tr>
                            <th>Almacén</th>
                            <th>Fecha</th>
                            <th>Destinatario</th>
                            <th>DNI</th>
                            <th>Productos Entregados</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Si no es admin, obtener entregas del almacén asignado
                        if ($usuario_rol != 'admin' && $usuario_almacen_id) {
                            $entregas = obtenerEntregasPorAlmacen($conn, $usuario_almacen_id, $filtros);
                        }

                        if (empty($entregas)): ?>
                            <tr>
                                <td colspan="5" class="no-results-message">
                                    No hay entregas para mostrar
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($entregas as $entrega): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($entrega['almacen_nombre']); ?></td>
                                    <td>
                                        <?php 
                                        $fecha = new DateTime($entrega['fecha_entrega']);
                                        echo $fecha->format('d/m/Y'); 
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($entrega['nombre_destinatario']); ?></td>
                                    <td><?php echo htmlspecialchars($entrega['dni_destinatario']); ?></td>
                                    <td>
                                        <ul class="productos-lista">
                                            <?php foreach ($entrega['productos'] as $producto): ?>
                                                <li>
                                                    <?php 
                                                    echo htmlspecialchars($producto['nombre']) . 
                                                         ' (Cantidad: ' . 
                                                         htmlspecialchars($producto['cantidad']) . 
                                                         ')'; 
                                                    ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Manejar clic en "Ver Entregas" para admin
        const botonesVerEntregas = document.querySelectorAll('.mostrar-entregas');
        const contenedorHistorial = document.getElementById('contenedor-historial-entregas');
        const tablaHistorial = document.getElementById('tabla-historial-entregas');
        const almacenesContainer = document.querySelector('.almacenes-container');

        botonesVerEntregas.forEach(boton => {
            boton.addEventListener('click', function(e) {
                e.preventDefault();
                const almacenId = this.dataset.almacenId;

                // Ocultar los cuadros de almacén
                almacenesContainer.style.display = 'none';

                // Obtener entregas por AJAX
                fetch(`/uniformes/obtener_entregas_ajax.php?almacen_id=${almacenId}`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    contenedorHistorial.style.display = 'block';
                    
                    // Limpiar tabla
                    tablaHistorial.querySelector('tbody').innerHTML = 
                        data.length > 0 
                        ? data.map(entrega => `
                            <tr>
                                <td>${entrega.almacen_nombre}</td>
                                <td>${new Date(entrega.fecha_entrega).toLocaleDateString('es-PE', {
                                    day: '2-digit',
                                    month: '2-digit',
                                    year: 'numeric'
                                })}</td>
                                <td>${entrega.nombre_destinatario}</td>
                                <td>${entrega.dni_destinatario}</td>
                                <td>
                                    <ul class="productos-lista">
                                        ${entrega.productos.map(producto => 
                                            `<li>${producto.nombre} (Cantidad: ${producto.cantidad})</li>`
                                        ).join('')}
                                    </ul>
                                </td>
                            </tr>
                        `).join('') 
                        : `
                            <tr>
                                <td colspan="5" class="no-results-message">
                                    No hay entregas para este almacén
                                </td>
                            </tr>
                        `;
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('No se pudieron cargar las entregas');
                });
            });
        });

        // Validación de fechas
        const form = document.querySelector('.uniform-filter-form');
        form.addEventListener('submit', function(e) {
            const fechaInicio = document.getElementById('filtro-fecha-inicio').value;
            const fechaFin = document.getElementById('filtro-fecha-fin').value;

            if (fechaInicio && fechaFin && new Date(fechaInicio) > new Date(fechaFin)) {
                e.preventDefault();
                alert('La fecha de inicio no puede ser mayor que la fecha de fin');
            }
        });
    });
    </script>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>