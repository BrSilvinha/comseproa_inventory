<?php
/**
 * Script para crear usuario administrador inicial
 * Ejecutar una sola vez después de configurar la base de datos
 */

require_once __DIR__ . '/bootstrap.php';

echo "<h1>🔧 Setup Administrador COMSEPROA</h1>\n";

try {
    // Verificar conexión a base de datos
    $db = Database::getInstance();
    $conn = $db->getConnection();
    echo "✅ Conexión a base de datos exitosa<br>\n";
    
    // Verificar si existe la tabla usuarios
    $tableExists = $db->fetchOne("SHOW TABLES LIKE 'usuarios'");
    if (!$tableExists) {
        echo "❌ La tabla 'usuarios' no existe. Ejecuta primero el script de creación de tablas.<br>\n";
        exit();
    }
    
    // Verificar si ya existe un administrador
    $adminExists = $db->fetchOne("SELECT id FROM usuarios WHERE rol = 'administrador' LIMIT 1");
    
    if ($adminExists) {
        echo "ℹ️ Ya existe un usuario administrador en el sistema.<br>\n";
        echo "<h2>👥 Usuarios existentes:</h2>\n";
        
        $users = $db->fetchAll("SELECT id, nombre, apellidos, correo, rol, estado FROM usuarios ORDER BY rol, nombre");
        if ($users) {
            echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
            echo "<tr><th>ID</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th></tr>\n";
            foreach ($users as $user) {
                echo "<tr>";
                echo "<td>{$user['id']}</td>";
                echo "<td>{$user['nombre']} {$user['apellidos']}</td>";
                echo "<td>{$user['correo']}</td>";
                echo "<td>{$user['rol']}</td>";
                echo "<td>{$user['estado']}</td>";
                echo "</tr>\n";
            }
            echo "</table>\n";
        }
    } else {
        // Verificar si existe algún almacén
        $almacen = $db->fetchOne("SELECT id FROM almacenes LIMIT 1");
        $almacenId = $almacen ? $almacen['id'] : null;
        
        // Si no hay almacenes, crear uno por defecto
        if (!$almacenId) {
            $sqlAlmacen = "INSERT INTO almacenes (nombre, ubicacion, descripcion, estado) VALUES (?, ?, ?, ?)";
            $db->execute($sqlAlmacen, ['Almacén Principal', 'Sede Central', 'Almacén principal del sistema', 'activo']);
            $almacen = $db->fetchOne("SELECT id FROM almacenes WHERE nombre = 'Almacén Principal'");
            $almacenId = $almacen['id'];
            echo "✅ Almacén principal creado<br>\n";
        }
        
        // Crear usuario administrador por defecto
        $adminData = [
            'dni' => '00000000',
            'nombre' => 'Administrador',
            'apellidos' => 'Sistema',
            'correo' => 'admin@comseproa.com',
            'contrasena' => password_hash('admin123', PASSWORD_DEFAULT),
            'rol' => 'administrador',
            'estado' => 'activo',
            'almacen_id' => $almacenId
        ];
        
        $sql = "INSERT INTO usuarios (dni, nombre, apellidos, correo, contrasena, rol, estado, almacen_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $result = $db->execute($sql, [
            $adminData['dni'],
            $adminData['nombre'],
            $adminData['apellidos'], 
            $adminData['correo'],
            $adminData['contrasena'],
            $adminData['rol'],
            $adminData['estado'],
            $adminData['almacen_id']
        ]);
        
        if ($result) {
            echo "✅ Usuario administrador creado exitosamente!<br>\n";
            echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
            echo "<h3>📋 Credenciales de Administrador:</h3>\n";
            echo "<strong>Email:</strong> admin@comseproa.com<br>\n";
            echo "<strong>Contraseña:</strong> admin123<br>\n";
            echo "<br>\n";
            echo "<strong>⚠️ IMPORTANTE:</strong> Cambia la contraseña después del primer login.<br>\n";
            echo "</div>\n";
            
            Logger::info("Admin user created", ['email' => $adminData['correo']]);
        } else {
            echo "❌ Error al crear usuario administrador<br>\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . htmlspecialchars($e->getMessage()) . "<br>\n";
    Logger::error("Setup admin error", ['message' => $e->getMessage()]);
}

echo "<br><hr>\n";
echo "<p><a href='index.php'>← Ir al Login</a> | <a href='test_config.php'>🧪 Test de Configuración</a></p>\n";
echo "<p><small>Ejecutado el: " . date('Y-m-d H:i:s') . "</small></p>\n";
?>