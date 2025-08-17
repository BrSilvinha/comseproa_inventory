<?php
/**
 * Script de testing para verificar la configuración
 * Ejecutar desde navegador o línea de comandos para verificar que todo funcione
 */

// Cargar bootstrap
require_once 'bootstrap.php';

echo "<h1>🧪 Test de Configuración COMSEPROA v2.0</h1>\n";

$allPassed = true;

// Test 1: Configuración
echo "<h2>1. ✅ Test de Configuración</h2>\n";
try {
    $dbConfig = Config::database();
    echo "✓ Configuración cargada correctamente<br>\n";
    echo "- Host: " . TemplateHelper::h($dbConfig['host']) . "<br>\n";
    echo "- Database: " . TemplateHelper::h($dbConfig['database']) . "<br>\n";
    echo "- Debug Mode: " . (Config::isDebug() ? 'ON' : 'OFF') . "<br>\n";
} catch (Exception $e) {
    echo "❌ Error en configuración: " . TemplateHelper::h($e->getMessage()) . "<br>\n";
    $allPassed = false;
}

// Test 2: Base de datos
echo "<h2>2. 🗄️ Test de Base de Datos</h2>\n";
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Test de conexión
    $result = $db->fetchOne("SELECT 1 as test");
    if ($result && $result['test'] == 1) {
        echo "✓ Conexión a base de datos exitosa<br>\n";
    } else {
        throw new Exception("Test query failed");
    }
    
    // Test de prepared statements
    $count = $db->fetchOne("SELECT COUNT(*) as total FROM usuarios");
    echo "✓ Prepared statements funcionando - Usuarios: " . $count['total'] . "<br>\n";
    
} catch (Exception $e) {
    echo "❌ Error en base de datos: " . TemplateHelper::h($e->getMessage()) . "<br>\n";
    $allPassed = false;
}

// Test 3: Sesiones
echo "<h2>3. 🔐 Test de Sesiones</h2>\n";
try {
    // Test de inicio de sesión
    Session::start();
    echo "✓ Sesiones iniciadas correctamente<br>\n";
    
    // Test de almacenamiento
    Session::set('test_key', 'test_value');
    $value = Session::get('test_key');
    if ($value === 'test_value') {
        echo "✓ Almacenamiento de sesión funcionando<br>\n";
    } else {
        throw new Exception("Session storage failed");
    }
    
    Session::remove('test_key');
    
} catch (Exception $e) {
    echo "❌ Error en sesiones: " . TemplateHelper::h($e->getMessage()) . "<br>\n";
    $allPassed = false;
}

// Test 4: Validación y Sanitización
echo "<h2>4. 🛡️ Test de Validación</h2>\n";
try {
    // Test XSS protection
    $maliciousInput = '<script>alert("XSS")</script>';
    $safeOutput = TemplateHelper::h($maliciousInput);
    if (strpos($safeOutput, '<script>') === false) {
        echo "✓ Protección XSS funcionando<br>\n";
    } else {
        throw new Exception("XSS protection failed");
    }
    
    // Test de validación de email
    $validEmail = Validator::validateEmail('test@example.com');
    $invalidEmail = Validator::validateEmail('invalid-email');
    if ($validEmail && !$invalidEmail) {
        echo "✓ Validación de email funcionando<br>\n";
    } else {
        throw new Exception("Email validation failed");
    }
    
    // Test CSRF token
    $token = Validator::generateCsrfToken();
    if (!empty($token) && strlen($token) === 64) {
        echo "✓ Generación de tokens CSRF funcionando<br>\n";
    } else {
        throw new Exception("CSRF token generation failed");
    }
    
} catch (Exception $e) {
    echo "❌ Error en validación: " . TemplateHelper::h($e->getMessage()) . "<br>\n";
    $allPassed = false;
}

// Test 5: Logging
echo "<h2>5. 📝 Test de Logging</h2>\n";
try {
    Logger::info("Test log message");
    
    $logFile = 'logs/app-' . date('Y-m-d') . '.log';
    if (file_exists($logFile)) {
        echo "✓ Sistema de logs funcionando<br>\n";
        echo "- Archivo: " . TemplateHelper::h($logFile) . "<br>\n";
    } else {
        throw new Exception("Log file not created");
    }
    
} catch (Exception $e) {
    echo "❌ Error en logging: " . TemplateHelper::h($e->getMessage()) . "<br>\n";
    $allPassed = false;
}

// Test 6: Autoloader
echo "<h2>6. 🔄 Test de Autoloader</h2>\n";
try {
    // Verificar que las clases se cargan automáticamente
    $classes = ['Config', 'Database', 'Session', 'Validator', 'Logger', 'TemplateHelper'];
    $loadedClasses = [];
    
    foreach ($classes as $class) {
        if (class_exists($class)) {
            $loadedClasses[] = $class;
        }
    }
    
    if (count($loadedClasses) === count($classes)) {
        echo "✓ Autoloader funcionando - Clases cargadas: " . implode(', ', $loadedClasses) . "<br>\n";
    } else {
        throw new Exception("Some classes not loaded: " . implode(', ', array_diff($classes, $loadedClasses)));
    }
    
} catch (Exception $e) {
    echo "❌ Error en autoloader: " . TemplateHelper::h($e->getMessage()) . "<br>\n";
    $allPassed = false;
}

// Test 7: Archivos de configuración
echo "<h2>7. 📁 Test de Archivos</h2>\n";
try {
    $requiredFiles = [
        '.env' => 'Variables de entorno',
        'bootstrap.php' => 'Bootstrap del sistema',
        'core/Config.php' => 'Clase de configuración',
        'core/Database.php' => 'Clase de base de datos',
        'assets/css/main.css' => 'CSS principal',
        'assets/js/main.js' => 'JavaScript principal'
    ];
    
    $missingFiles = [];
    foreach ($requiredFiles as $file => $description) {
        if (!file_exists($file)) {
            $missingFiles[] = "$file ($description)";
        }
    }
    
    if (empty($missingFiles)) {
        echo "✓ Todos los archivos requeridos están presentes<br>\n";
    } else {
        throw new Exception("Archivos faltantes: " . implode(', ', $missingFiles));
    }
    
} catch (Exception $e) {
    echo "❌ Error en archivos: " . TemplateHelper::h($e->getMessage()) . "<br>\n";
    $allPassed = false;
}

// Resultado final
echo "<h2>🎯 Resultado Final</h2>\n";
if ($allPassed) {
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<strong>🎉 ¡TODOS LOS TESTS PASARON!</strong><br>\n";
    echo "El sistema está correctamente configurado y listo para usar.<br>\n";
    echo "Puedes eliminar este archivo de testing: <code>test_config.php</code>\n";
    echo "</div>\n";
} else {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<strong>⚠️ ALGUNOS TESTS FALLARON</strong><br>\n";
    echo "Revisa los errores anteriores y corrige la configuración antes de usar el sistema.<br>\n";
    echo "Consulta el archivo MIGRATION_GUIDE.md para más información.\n";
    echo "</div>\n";
}

echo "<hr>\n";
echo "<p><small>Test ejecutado el: " . date('Y-m-d H:i:s') . "</small></p>\n";
?>