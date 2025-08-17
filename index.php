<?php


try {
    // Cargar bootstrap con manejo de errores
    require_once __DIR__ . '/bootstrap.php';
    
    // Verificar si el usuario ya está autenticado
    if (Session::isAuthenticated()) {
        // Redirigir al dashboard
        redirect(baseUrl('dashboard.php'));
    } else {
        // Redirigir al login
        redirect(baseUrl('views/login_form.php'));
    }
    
} catch (Exception $e) {
    // Si hay error en el bootstrap, mostrar página de error simple
    http_response_code(500);
    
    // Log del error si es posible
    if (function_exists('error_log')) {
        error_log("Bootstrap error: " . $e->getMessage());
    }
    
    // Página de error simple sin dependencias
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - COMSEPROA</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
            .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; margin: 20px auto; max-width: 600px; }
            .solution { background: #d1ecf1; color: #0c5460; padding: 20px; border-radius: 5px; margin: 20px auto; max-width: 600px; }
        </style>
    </head>
    <body>
        <h1>🚨 Error de Configuración</h1>
        <div class="error">
            <strong>Error:</strong> <?= htmlspecialchars($e->getMessage()) ?>
        </div>
        <div class="solution">
            <h3>💡 Solución:</h3>
            <ol style="text-align: left;">
                <li>Verificar que el archivo <code>.env</code> existe</li>
                <li>Comprobar permisos de archivos (755 para directorios, 644 para archivos)</li>
                <li>Verificar que la carpeta <code>logs/</code> tiene permisos de escritura</li>
                <li>Revisar credenciales de base de datos en <code>.env</code></li>
            </ol>
            <p><a href="test_config.php">🧪 Ejecutar Test de Configuración</a></p>
        </div>
    </body>
    </html>
    <?php
    exit();
}
