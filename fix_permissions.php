<?php
/**
 * Script de reparación de permisos y configuración
 * Ejecutar este archivo para solucionar problemas comunes
 */

echo "<h1>🔧 Script de Reparación - COMSEPROA</h1>\n";

$fixes = [];
$errors = [];

// 1. Verificar y crear directorio logs
echo "<h2>1. Verificando directorio de logs...</h2>\n";
$logsDir = __DIR__ . '/logs';
if (!is_dir($logsDir)) {
    if (mkdir($logsDir, 0755, true)) {
        $fixes[] = "✓ Directorio logs/ creado";
    } else {
        $errors[] = "❌ No se pudo crear directorio logs/";
    }
} else {
    $fixes[] = "✓ Directorio logs/ existe";
}

// Verificar permisos de escritura en logs
if (is_dir($logsDir) && !is_writable($logsDir)) {
    if (chmod($logsDir, 0755)) {
        $fixes[] = "✓ Permisos de logs/ corregidos";
    } else {
        $errors[] = "❌ No se pudieron corregir permisos de logs/";
    }
}

// 2. Verificar archivo .env
echo "<h2>2. Verificando archivo .env...</h2>\n";
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    // Copiar desde .env.example
    $envExample = __DIR__ . '/.env.example';
    if (file_exists($envExample)) {
        if (copy($envExample, $envFile)) {
            $fixes[] = "✓ Archivo .env creado desde .env.example";
            echo "<div style='background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
            echo "<strong>⚠️ IMPORTANTE:</strong> Edita el archivo .env con tus credenciales reales de base de datos";
            echo "</div>\n";
        } else {
            $errors[] = "❌ No se pudo crear .env desde .env.example";
        }
    } else {
        // Crear .env básico
        $envContent = "DB_HOST=localhost\nDB_USERNAME=tu_usuario\nDB_PASSWORD=tu_password\nDB_NAME=tu_base_datos\nAPP_DEBUG=false\n";
        if (file_put_contents($envFile, $envContent)) {
            $fixes[] = "✓ Archivo .env básico creado";
            echo "<div style='background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
            echo "<strong>⚠️ IMPORTANTE:</strong> Edita el archivo .env con tus credenciales reales";
            echo "</div>\n";
        } else {
            $errors[] = "❌ No se pudo crear archivo .env";
        }
    }
} else {
    $fixes[] = "✓ Archivo .env existe";
}

// 3. Verificar archivos core
echo "<h2>3. Verificando archivos del sistema...</h2>\n";
$coreFiles = [
    'bootstrap.php',
    'core/Config.php',
    'core/Database.php',
    'core/Session.php',
    'core/Validator.php',
    'core/Logger.php',
    'core/TemplateHelper.php'
];

$missingCore = [];
foreach ($coreFiles as $file) {
    if (!file_exists(__DIR__ . '/' . $file)) {
        $missingCore[] = $file;
    }
}

if (empty($missingCore)) {
    $fixes[] = "✓ Todos los archivos core están presentes";
} else {
    $errors[] = "❌ Archivos core faltantes: " . implode(', ', $missingCore);
}

// 4. Test de conexión a base de datos (solo si bootstrap funciona)
echo "<h2>4. Probando conexión a base de datos...</h2>\n";
try {
    // Cargar bootstrap con manejo de errores
    if (file_exists(__DIR__ . '/bootstrap.php')) {
        ob_start();
        require_once __DIR__ . '/bootstrap.php';
        ob_end_clean();
        
        // Test de base de datos
        $db = Database::getInstance();
        $result = $db->fetchOne("SELECT 1 as test");
        if ($result && $result['test'] == 1) {
            $fixes[] = "✓ Conexión a base de datos exitosa";
        } else {
            $errors[] = "❌ Error en consulta de prueba a base de datos";
        }
    } else {
        $errors[] = "❌ No se encontró bootstrap.php";
    }
} catch (Exception $e) {
    $errors[] = "❌ Error de base de datos: " . $e->getMessage();
    echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>Error de BD:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<strong>Solución:</strong> Verifica las credenciales en el archivo .env";
    echo "</div>\n";
}

// 5. Crear archivo .htaccess para seguridad (si no existe)
echo "<h2>5. Verificando seguridad...</h2>\n";
$htaccessFile = __DIR__ . '/.htaccess';
if (!file_exists($htaccessFile)) {
    $htaccessContent = "# Seguridad básica\n";
    $htaccessContent .= "RewriteEngine On\n";
    $htaccessContent .= "# Proteger archivos sensibles\n";
    $htaccessContent .= "<Files \".env\">\n";
    $htaccessContent .= "    Require all denied\n";
    $htaccessContent .= "</Files>\n";
    $htaccessContent .= "<Files \"*.log\">\n";
    $htaccessContent .= "    Require all denied\n";
    $htaccessContent .= "</Files>\n";
    
    if (file_put_contents($htaccessFile, $htaccessContent)) {
        $fixes[] = "✓ Archivo .htaccess de seguridad creado";
    } else {
        $errors[] = "❌ No se pudo crear .htaccess";
    }
} else {
    $fixes[] = "✓ Archivo .htaccess existe";
}

// Mostrar resultados
echo "<h2>📊 Resultados de la Reparación</h2>\n";

if (!empty($fixes)) {
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<h3>✅ Correcciones Aplicadas:</h3>\n";
    foreach ($fixes as $fix) {
        echo "<p>$fix</p>\n";
    }
    echo "</div>\n";
}

if (!empty($errors)) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<h3>❌ Errores Encontrados:</h3>\n";
    foreach ($errors as $error) {
        echo "<p>$error</p>\n";
    }
    echo "</div>\n";
}

// Instrucciones finales
echo "<h2>🎯 Próximos Pasos</h2>\n";
echo "<div style='background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
echo "<ol>\n";
echo "<li><strong>Editar .env:</strong> Configura tus credenciales de base de datos en el archivo .env</li>\n";
echo "<li><strong>Probar configuración:</strong> <a href='test_config.php'>Ejecutar test de configuración</a></li>\n";
echo "<li><strong>Probar login:</strong> <a href='views/login_form.php'>Ir al formulario de login</a></li>\n";
echo "<li><strong>Eliminar este archivo:</strong> Una vez que todo funcione, elimina fix_permissions.php</li>\n";
echo "</ol>\n";
echo "</div>\n";

echo "<hr>\n";
echo "<p><small>Reparación ejecutada el: " . date('Y-m-d H:i:s') . "</small></p>\n";
?>