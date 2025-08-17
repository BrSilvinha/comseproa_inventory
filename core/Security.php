<?php
/**
 * Clase para manejo de seguridad del sistema
 * Headers de seguridad, rate limiting, etc.
 */
class Security
{
    /**
     * Configurar headers de seguridad
     */
    public static function setSecurityHeaders()
    {
        // Prevenir clickjacking
        header('X-Frame-Options: DENY');
        
        // Prevenir MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Habilitar XSS protection del navegador
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // HTTPS Strict Transport Security (solo en HTTPS)
        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
        // Content Security Policy básico
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline'; " .
               "style-src 'self' 'unsafe-inline'; " .
               "img-src 'self' data:; " .
               "font-src 'self'; " .
               "connect-src 'self'; " .
               "frame-ancestors 'none';";
        
        header("Content-Security-Policy: $csp");
        
        // Permissions Policy (Feature Policy)
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    }
    
    /**
     * Verificar si la conexión es HTTPS
     */
    public static function isHttps()
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               $_SERVER['SERVER_PORT'] == 443 ||
               (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }
    
    /**
     * Rate limiting para login
     */
    public static function checkLoginAttempts($email)
    {
        $maxAttempts = (int)Config::get('MAX_LOGIN_ATTEMPTS', 5);
        $lockoutTime = (int)Config::get('LOGIN_LOCKOUT_TIME', 900); // 15 minutos
        
        $key = 'login_attempts_' . md5($email . $_SERVER['REMOTE_ADDR']);
        
        Session::start();
        $attempts = Session::get($key, []);
        $now = time();
        
        // Limpiar intentos antiguos
        $attempts = array_filter($attempts, function($timestamp) use ($now, $lockoutTime) {
            return ($now - $timestamp) < $lockoutTime;
        });
        
        // Verificar si está bloqueado
        if (count($attempts) >= $maxAttempts) {
            $oldestAttempt = min($attempts);
            $remainingTime = $lockoutTime - ($now - $oldestAttempt);
            
            Logger::warning("Login blocked due to rate limiting", [
                'email' => $email,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'attempts' => count($attempts),
                'remaining_time' => $remainingTime
            ]);
            
            return [
                'blocked' => true,
                'remaining_time' => $remainingTime,
                'attempts' => count($attempts)
            ];
        }
        
        return ['blocked' => false, 'attempts' => count($attempts)];
    }
    
    /**
     * Registrar intento de login fallido
     */
    public static function recordFailedLogin($email)
    {
        $key = 'login_attempts_' . md5($email . $_SERVER['REMOTE_ADDR']);
        
        Session::start();
        $attempts = Session::get($key, []);
        $attempts[] = time();
        
        Session::set($key, $attempts);
        
        Logger::warning("Failed login attempt recorded", [
            'email' => $email,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'total_attempts' => count($attempts)
        ]);
    }
    
    /**
     * Limpiar intentos de login después de login exitoso
     */
    public static function clearLoginAttempts($email)
    {
        $key = 'login_attempts_' . md5($email . $_SERVER['REMOTE_ADDR']);
        
        Session::start();
        Session::remove($key);
        
        Logger::info("Login attempts cleared for successful login", [
            'email' => $email,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }
    
    /**
     * Validar origen de la petición
     */
    public static function validateOrigin()
    {
        $allowedOrigins = [
            Config::get('APP_URL'),
            'https://inventary.gruposealsac.me'
        ];
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
        
        if (empty($origin)) {
            return true; // Permitir peticiones sin origen (navegación directa)
        }
        
        foreach ($allowedOrigins as $allowed) {
            if (strpos($origin, $allowed) === 0) {
                return true;
            }
        }
        
        Logger::warning("Invalid origin detected", [
            'origin' => $origin,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        return false;
    }
    
    /**
     * Sanitizar input para prevenir XSS
     */
    public static function sanitizeInput($input)
    {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Generar nonce para CSP
     */
    public static function generateNonce()
    {
        return base64_encode(random_bytes(16));
    }
}
?>