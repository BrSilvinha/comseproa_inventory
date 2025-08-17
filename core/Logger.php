<?php
/**
 * Sistema de logging seguro
 * Maneja logs con rotación automática y niveles de severidad
 */
class Logger
{
    const EMERGENCY = 0;
    const ALERT = 1;
    const CRITICAL = 2;
    const ERROR = 3;
    const WARNING = 4;
    const NOTICE = 5;
    const INFO = 6;
    const DEBUG = 7;

    private static $levels = [
        0 => 'EMERGENCY',
        1 => 'ALERT',
        2 => 'CRITICAL',
        3 => 'ERROR',
        4 => 'WARNING',
        5 => 'NOTICE',
        6 => 'INFO',
        7 => 'DEBUG'
    ];

    /**
     * Log message with level
     */
    private static function log($level, $message, $context = [])
    {
        $configLevel = self::getConfigLevel();
        
        if ($level > $configLevel) {
            return; // Don't log if level is above configured level
        }

        $logDir = __DIR__ . '/../' . Config::get('LOG_PATH', 'logs/');
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $filename = $logDir . 'app-' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $levelName = self::$levels[$level];
        
        // Sanitize message to prevent log injection
        $message = str_replace(["\n", "\r"], ' ', $message);
        
        // Add context if provided
        $contextStr = '';
        if (!empty($context)) {
            $contextStr = ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        $logEntry = "[{$timestamp}] {$levelName}: {$message}{$contextStr}" . PHP_EOL;
        
        // Write to file with lock
        file_put_contents($filename, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Rotate logs if needed
        self::rotateLogs($logDir);
    }

    /**
     * Get configured log level
     */
    private static function getConfigLevel()
    {
        $levelStr = Config::get('LOG_LEVEL', 'error');
        return array_search(strtoupper($levelStr), self::$levels) ?: 3;
    }

    /**
     * Rotate logs to prevent disk space issues
     */
    private static function rotateLogs($logDir)
    {
        $maxFiles = (int)Config::get('LOG_MAX_FILES', 10);
        $files = glob($logDir . 'app-*.log');
        
        if (count($files) > $maxFiles) {
            // Sort by modification time
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Remove oldest files
            $filesToRemove = array_slice($files, 0, count($files) - $maxFiles);
            foreach ($filesToRemove as $file) {
                unlink($file);
            }
        }
    }

    // Public logging methods
    public static function emergency($message, $context = [])
    {
        self::log(self::EMERGENCY, $message, $context);
    }

    public static function alert($message, $context = [])
    {
        self::log(self::ALERT, $message, $context);
    }

    public static function critical($message, $context = [])
    {
        self::log(self::CRITICAL, $message, $context);
    }

    public static function error($message, $context = [])
    {
        self::log(self::ERROR, $message, $context);
    }

    public static function warning($message, $context = [])
    {
        self::log(self::WARNING, $message, $context);
    }

    public static function notice($message, $context = [])
    {
        self::log(self::NOTICE, $message, $context);
    }

    public static function info($message, $context = [])
    {
        self::log(self::INFO, $message, $context);
    }

    public static function debug($message, $context = [])
    {
        self::log(self::DEBUG, $message, $context);
    }
}