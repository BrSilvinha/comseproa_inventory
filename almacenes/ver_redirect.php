<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

// Verificar permisos: admin puede ver cualquier almacén, usuario solo su almacén asignado
$usuario_rol = $_SESSION["user_role"] ?? "usuario";
$usuario_almacen_id = $_SESSION["almacen_id"] ?? null;

if (isset($_POST['view_almacen_id'])) {
    $almacen_id = (int)$_POST['view_almacen_id'];
    
    // Si no es admin, verificar que solo pueda acceder a su almacén asignado
    if ($usuario_rol != 'admin' && $usuario_almacen_id != $almacen_id) {
        $_SESSION['error'] = "No tienes permiso para acceder a este almacén";
        header("Location: listar.php");
        exit();
    }
    
    $_SESSION['view_almacen_id'] = $almacen_id;
    header("Location: ver-almacen.php");
    exit();
}

header("Location: listar.php");
exit();
?>