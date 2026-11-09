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

        // The bundled copy first: it needs nothing else. Composer's vendor/autoload.php loads every package's files at once,
        // so one missing file there (e.g. thecodingmachine/safe/generated/xmlrpc.php after a partial upload) breaks every PDF.
        $candidates = array_merge([
            dirname(__DIR__) . '/lib/dompdf/autoload.inc.php',
        ], vendor_autoload_files());
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
