<?php
// HTML → PDF via Dompdf (Composer). Required for admission letters and fee structures.

if (!function_exists('load_dompdf')) {
    function load_dompdf(): bool
    {
        if (class_exists('Dompdf\\Dompdf')) {
            return true;
        }

        require_once __DIR__ . '/vendor_autoload.php';
        if (safe_composer_autoload() && class_exists('Dompdf\\Dompdf')) {
            return true;
        }

        return false;
    }
}

if (!function_exists('html_to_pdf')) {
    /**
     * @return string|null PDF binary
     */
    function html_to_pdf(string $html, string $paper = 'A4', string $orientation = 'portrait'): ?string
    {
        if (!load_dompdf()) {
            error_log('[pdf] Dompdf is not installed. Run: composer install');
            return null;
        }

        try {
            $dompdf = new \Dompdf\Dompdf([
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
            ]);
            $dompdf->loadHtml($html);
            $dompdf->setPaper($paper, $orientation);
            $dompdf->render();
            return $dompdf->output();
        } catch (Throwable $e) {
            error_log('[pdf] render failed: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('html_to_pdf_file')) {
    function html_to_pdf_file(string $html, string $absolutePath): bool
    {
        $pdf = html_to_pdf($html);
        if ($pdf === null) {
            return false;
        }
        $dir = dirname($absolutePath);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
        return @file_put_contents($absolutePath, $pdf) !== false;
    }
}
