<?php
/**
 * Helper para templates seguros
 * Proporciona funciones de escape y formateo para vistas
 */
class TemplateHelper
{
    /**
     * Escapar contenido HTML (alias para Validator::sanitizeHtml)
     */
    public static function h($content)
    {
        return Validator::sanitizeHtml($content);
    }

    /**
     * Escapar atributos HTML
     */
    public static function attr($content)
    {
        return Validator::sanitizeAttribute($content);
    }

    /**
     * Formatear fecha
     */
    public static function date($date, $format = 'd/m/Y')
    {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return '-';
        }
        
        try {
            $dateObj = new DateTime($date);
            return $dateObj->format($format);
        } catch (Exception $e) {
            return '-';
        }
    }

    /**
     * Formatear fecha y hora
     */
    public static function datetime($datetime, $format = 'd/m/Y H:i')
    {
        return self::date($datetime, $format);
    }

    /**
     * Formatear número
     */
    public static function number($number, $decimals = 0)
    {
        if (!is_numeric($number)) {
            return '0';
        }
        
        return number_format($number, $decimals, '.', ',');
    }

    /**
     * Formatear moneda
     */
    public static function currency($amount, $currency = 'PEN')
    {
        if (!is_numeric($amount)) {
            return 'S/ 0.00';
        }
        
        $symbol = $currency === 'PEN' ? 'S/ ' : $currency . ' ';
        return $symbol . number_format($amount, 2, '.', ',');
    }

    /**
     * Generar badge de estado
     */
    public static function statusBadge($status, $labels = [])
    {
        $defaultLabels = [
            'active' => ['text' => 'Activo', 'class' => 'badge-success'],
            'inactive' => ['text' => 'Inactivo', 'class' => 'badge-secondary'],
            'pending' => ['text' => 'Pendiente', 'class' => 'badge-warning'],
            'approved' => ['text' => 'Aprobado', 'class' => 'badge-success'],
            'rejected' => ['text' => 'Rechazado', 'class' => 'badge-danger'],
            'new' => ['text' => 'Nuevo', 'class' => 'badge-primary'],
            'used' => ['text' => 'Usado', 'class' => 'badge-info'],
            'damaged' => ['text' => 'Dañado', 'class' => 'badge-danger'],
        ];

        $config = array_merge($defaultLabels, $labels);
        $statusConfig = $config[$status] ?? ['text' => $status, 'class' => 'badge-secondary'];

        return sprintf(
            '<span class="badge %s">%s</span>',
            self::attr($statusConfig['class']),
            self::h($statusConfig['text'])
        );
    }

    /**
     * Generar botón con icono
     */
    public static function button($text, $url, $icon = '', $class = 'btn-primary', $attributes = [])
    {
        $iconHtml = $icon ? '<i class="' . self::attr($icon) . '"></i> ' : '';
        $attrString = '';
        
        foreach ($attributes as $attr => $value) {
            $attrString .= sprintf(' %s="%s"', $attr, self::attr($value));
        }

        return sprintf(
            '<a href="%s" class="btn %s"%s>%s%s</a>',
            self::attr($url),
            self::attr($class),
            $attrString,
            $iconHtml,
            self::h($text)
        );
    }

    /**
     * Generar enlace con confirmación
     */
    public static function deleteButton($text, $url, $message = null, $class = 'btn-danger')
    {
        $message = $message ?: "¿Está seguro de que desea eliminar este elemento?";
        
        return sprintf(
            '<a href="%s" class="btn %s delete-link" data-message="%s"><i class="fas fa-trash"></i> %s</a>',
            self::attr($url),
            self::attr($class),
            self::attr($message),
            self::h($text)
        );
    }

    /**
     * Generar paginación
     */
    public static function pagination($currentPage, $totalPages, $baseUrl, $params = [])
    {
        if ($totalPages <= 1) {
            return '';
        }

        $html = '<nav aria-label="Paginación"><ul class="pagination justify-content-center">';
        
        // Botón anterior
        if ($currentPage > 1) {
            $prevParams = array_merge($params, ['pagina' => $currentPage - 1]);
            $prevUrl = $baseUrl . '?' . http_build_query($prevParams);
            $html .= sprintf(
                '<li class="page-item"><a class="page-link" href="%s">&laquo; Anterior</a></li>',
                self::attr($prevUrl)
            );
        }

        // Números de página
        $start = max(1, $currentPage - 2);
        $end = min($totalPages, $currentPage + 2);

        if ($start > 1) {
            $firstParams = array_merge($params, ['pagina' => 1]);
            $firstUrl = $baseUrl . '?' . http_build_query($firstParams);
            $html .= sprintf(
                '<li class="page-item"><a class="page-link" href="%s">1</a></li>',
                self::attr($firstUrl)
            );
            
            if ($start > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $pageParams = array_merge($params, ['pagina' => $i]);
            $pageUrl = $baseUrl . '?' . http_build_query($pageParams);
            $activeClass = $i === $currentPage ? ' active' : '';
            
            $html .= sprintf(
                '<li class="page-item%s"><a class="page-link" href="%s">%d</a></li>',
                $activeClass,
                self::attr($pageUrl),
                $i
            );
        }

        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            
            $lastParams = array_merge($params, ['pagina' => $totalPages]);
            $lastUrl = $baseUrl . '?' . http_build_query($lastParams);
            $html .= sprintf(
                '<li class="page-item"><a class="page-link" href="%s">%d</a></li>',
                self::attr($lastUrl),
                $totalPages
            );
        }

        // Botón siguiente
        if ($currentPage < $totalPages) {
            $nextParams = array_merge($params, ['pagina' => $currentPage + 1]);
            $nextUrl = $baseUrl . '?' . http_build_query($nextParams);
            $html .= sprintf(
                '<li class="page-item"><a class="page-link" href="%s">Siguiente &raquo;</a></li>',
                self::attr($nextUrl)
            );
        }

        $html .= '</ul></nav>';
        return $html;
    }

    /**
     * Generar breadcrumb
     */
    public static function breadcrumb($items)
    {
        if (empty($items)) {
            return '';
        }

        $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
        
        $lastIndex = count($items) - 1;
        foreach ($items as $index => $item) {
            if ($index === $lastIndex) {
                // Último elemento (actual)
                $html .= sprintf(
                    '<li class="breadcrumb-item active" aria-current="page">%s</li>',
                    self::h($item['text'])
                );
            } else {
                // Elementos con enlace
                if (isset($item['url'])) {
                    $html .= sprintf(
                        '<li class="breadcrumb-item"><a href="%s">%s</a></li>',
                        self::attr($item['url']),
                        self::h($item['text'])
                    );
                } else {
                    $html .= sprintf(
                        '<li class="breadcrumb-item">%s</li>',
                        self::h($item['text'])
                    );
                }
            }
        }
        
        $html .= '</ol></nav>';
        return $html;
    }

    /**
     * Truncar texto
     */
    public static function truncate($text, $length = 100, $suffix = '...')
    {
        if (strlen($text) <= $length) {
            return self::h($text);
        }
        
        return self::h(substr($text, 0, $length)) . $suffix;
    }

    /**
     * Mostrar mensaje flash
     */
    public static function flashMessage($type, $message)
    {
        if (empty($message)) {
            return '';
        }

        return sprintf(
            '<div class="alert alert-%s alert-dismissible fade show" role="alert">
                %s
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>',
            self::attr($type),
            self::h($message)
        );
    }

    /**
     * Generar select con opciones
     */
    public static function select($name, $options, $selected = null, $attributes = [])
    {
        $attrString = '';
        foreach ($attributes as $attr => $value) {
            $attrString .= sprintf(' %s="%s"', $attr, self::attr($value));
        }

        $html = sprintf('<select name="%s"%s>', self::attr($name), $attrString);
        
        foreach ($options as $value => $text) {
            $selectedAttr = ($value == $selected) ? ' selected' : '';
            $html .= sprintf(
                '<option value="%s"%s>%s</option>',
                self::attr($value),
                $selectedAttr,
                self::h($text)
            );
        }
        
        $html .= '</select>';
        return $html;
    }
}