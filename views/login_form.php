<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | COMSEPROA</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>

    <!-- Sección Izquierda: Logo y Nombre -->
    <div class="left-section">
        <img src="../assets/img/logo.png" alt="Logo de COMSEPROA" class="logo">
    </div>

    <!-- Línea Divisoria -->
    <div class="divider"></div>

    <!-- Sección Derecha: Formulario de Login -->
    <div class="right-section">
        <h2>Login</h2>
        <form action="../auth/login.php" method="POST">
            <label for="correo">Correo:</label>
            <input type="email" id="correo" name="correo" required>

            <label for="password">Contraseña:</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Iniciar Sesión</button>
        </form>
    </div>

</body>
</html>
