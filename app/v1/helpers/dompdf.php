<?php
// Load Dompdf from Composer vendor/ or the bundled copy at app/v1/lib/dompdf.

if (!function_exists('dazu_load_dompdf')) {
    function dazu_load_dompdf(): bool
    {
        if (class_exists('Dompdf\\Dompdf')) {
            return true;
        }

        if (!defined('PROJECT_ROOT')) {
            require_once __DIR__ . '/../config/paths.php';
        }

        $candidates = [
            PROJECT_ROOT . '/vendor/autoload.php',
            dirname(__DIR__, 2) . '/vendor/autoload.php',
            dirname(__DIR__) . '/lib/dompdf/autoload.inc.php',
        ];
        foreach ($candidates as $path) {
            if (!is_file($path)) {
                continue;
            }
            require_once $path;
            if (class_exists('Dompdf\\Dompdf')) {
                return true;
            }
        }

        return class_exists('Dompdf\\Dompdf');
    }
}
