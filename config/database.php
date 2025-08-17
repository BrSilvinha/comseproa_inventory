<?php
/**
 * Configuración de base de datos LEGACY
 * Este archivo se mantiene para compatibilidad con código existente
 * NUEVA IMPLEMENTACIÓN: Usar Database::getInstance() del core
 */

// Cargar bootstrap si no está cargado
if (!class_exists('Config')) {
    require_once __DIR__ . '/../bootstrap.php';
}

try {
    // Usar nueva clase Database
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Variables legacy para compatibilidad
    $host = Config::get('DB_HOST');
    $usuario = Config::get('DB_USERNAME');
    $contraseña = Config::get('DB_PASSWORD');
    $base_datos = Config::get('DB_NAME');
    
} catch (Exception $e) {
    Logger::critical("Database connection failed: " . $e->getMessage());
    
    if (Config::isDebug()) {
        die("Error de conexión: " . $e->getMessage());
    } else {
        die("Error de conexión a la base de datos. Contacte al administrador.");
    }
}