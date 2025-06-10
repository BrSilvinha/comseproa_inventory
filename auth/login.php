<?php
session_start();
require_once __DIR__ . "/../config/database.php"; // Conexión a la BD

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $correo = trim($_POST["correo"]);
    $password = $_POST["password"];

    if (empty($correo) || empty($password)) {
        $error = "empty_fields";
        header("Location: ../views/login_form.php?error=" . urlencode($error));
        exit();
    }

    // Validar formato de email
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = "invalid_email";
        header("Location: ../views/login_form.php?error=" . urlencode($error));
        exit();
    }

    // Modificado para incluir almacen_id en la consulta
    $sql = "SELECT id, nombre, apellidos, contrasena, rol, almacen_id FROM usuarios WHERE correo = ? AND estado = 'activo'";

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Error en la consulta SQL: " . $conn->error);
        $error = "system_error";
        header("Location: ../views/login_form.php?error=" . urlencode($error));
        exit();
    }

    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verificar la contraseña
        if (password_verify($password, $user["contrasena"])) {
            // Login exitoso - regenerar ID de sesión por seguridad
            session_regenerate_id(true);
            
            // Guardar sesión
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_name"] = $user["nombre"] . " " . $user["apellidos"];
            $_SESSION["user_role"] = $user["rol"];
            // Agregar almacen_id a la sesión
            $_SESSION["almacen_id"] = $user["almacen_id"];
            // Para mantener compatibilidad con el código existente
            $_SESSION["rol"] = $user["rol"];
            
            // Opcional: Registrar último acceso (agregar esta columna a la BD si es necesario)
            /*
            $update_sql = "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            if ($update_stmt) {
                $update_stmt->bind_param("i", $user["id"]);
                $update_stmt->execute();
                $update_stmt->close();
            }
            */

            // Redirigir al dashboard
            header("Location: ../dashboard.php");
            exit();
        } else {
            $error = "invalid_credentials";
        }
    } else {
        $error = "invalid_credentials";
    }

    $stmt->close();
    $conn->close();
    
    header("Location: ../views/login_form.php?error=" . urlencode($error));
    exit();
} else {
    // Si alguien accede directamente al archivo sin POST, redirigir al login
    header("Location: ../views/login_form.php");
    exit();
}
?>