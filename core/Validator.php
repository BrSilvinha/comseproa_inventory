<?php
/**
 * Sistema de validación y sanitización centralizado
 * Previene XSS, SQL injection y valida datos de entrada
 */
class Validator
{
    /**
     * Sanitizar string para output HTML (previene XSS)
     */
    public static function sanitizeHtml($string)
    {
        if ($string === null) return '';
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Sanitizar string para atributos HTML
     */
    public static function sanitizeAttribute($string)
    {
        if ($string === null) return '';
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Sanitizar entrada de usuario (limpia espacios, etc.)
     */
    public static function sanitizeInput($input)
    {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        
        if ($input === null) return '';
        
        return trim(strip_tags($input));
    }

    /**
     * Validar email
     */
    public static function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validar entero
     */
    public static function validateInt($value, $min = null, $max = null)
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        if ($int === false) return false;
        
        if ($min !== null && $int < $min) return false;
        if ($max !== null && $int > $max) return false;
        
        return $int;
    }

    /**
     * Validar string con longitud
     */
    public static function validateString($string, $minLength = 0, $maxLength = null)
    {
        if (!is_string($string)) return false;
        
        $length = strlen($string);
        if ($length < $minLength) return false;
        if ($maxLength !== null && $length > $maxLength) return false;
        
        return true;
    }

    /**
     * Validar contraseña segura
     */
    public static function validatePassword($password)
    {
        // Al menos 8 caracteres, una mayúscula, una minúscula, un número
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/', $password);
    }

    /**
     * Validar array de datos con reglas
     */
    public static function validate($data, $rules)
    {
        $errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            
            foreach ($fieldRules as $rule => $params) {
                switch ($rule) {
                    case 'required':
                        if ($params && empty($value)) {
                            $errors[$field][] = "El campo {$field} es requerido";
                        }
                        break;
                        
                    case 'email':
                        if (!empty($value) && !self::validateEmail($value)) {
                            $errors[$field][] = "El campo {$field} debe ser un email válido";
                        }
                        break;
                        
                    case 'min_length':
                        if (!empty($value) && strlen($value) < $params) {
                            $errors[$field][] = "El campo {$field} debe tener al menos {$params} caracteres";
                        }
                        break;
                        
                    case 'max_length':
                        if (!empty($value) && strlen($value) > $params) {
                            $errors[$field][] = "El campo {$field} no puede tener más de {$params} caracteres";
                        }
                        break;
                        
                    case 'numeric':
                        if (!empty($value) && !is_numeric($value)) {
                            $errors[$field][] = "El campo {$field} debe ser numérico";
                        }
                        break;
                        
                    case 'password':
                        if (!empty($value) && !self::validatePassword($value)) {
                            $errors[$field][] = "El campo {$field} debe tener al menos 8 caracteres, una mayúscula, una minúscula y un número";
                        }
                        break;
                }
            }
        }
        
        return $errors;
    }

    /**
     * Generar token CSRF
     */
    public static function generateCsrfToken()
    {
        Session::start();
        
        if (!Session::get('csrf_token') || 
            !Session::get('csrf_token_time') || 
            time() - Session::get('csrf_token_time') > 3600) {
            
            $token = bin2hex(random_bytes(32));
            Session::set('csrf_token', $token);
            Session::set('csrf_token_time', time());
        }
        
        return Session::get('csrf_token');
    }

    /**
     * Verificar token CSRF
     */
    public static function verifyCsrfToken($token)
    {
        Session::start();
        
        $sessionToken = Session::get('csrf_token');
        $tokenTime = Session::get('csrf_token_time');
        
        // Verificar que existe el token y no ha expirado (1 hora)
        if (!$sessionToken || !$tokenTime || time() - $tokenTime > 3600) {
            return false;
        }
        
        return hash_equals($sessionToken, $token);
    }

    /**
     * Obtener campo hidden CSRF para formularios
     */
    public static function getCsrfField()
    {
        $token = self::generateCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Validar token CSRF (método legacy)
     */
    public static function validateCsrfToken($token)
    {
        return self::verifyCsrfToken($token);
    }

    /**
     * Limpiar datos para JSON output
     */
    public static function sanitizeForJson($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeForJson'], $data);
        }
        
        if (is_string($data)) {
            return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        
        return $data;
    }
}