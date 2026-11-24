<?php
// app/v1/helpers/receipt_pdf.php — render a payment receipt (ReceiptGenerator HTML) as a PDF.
require_once __DIR__ . '/dompdf.php';
require_once __DIR__ . '/../services/ReceiptGenerator.php';

if (!function_exists('receipt_pdf_render')) {
    function receipt_pdf_render(array $reg, array $payment, float $totalPaid): string
    {
        dazu_load_dompdf();
        $html = '<html><head><meta charset="utf-8"></head><body style="margin:24px;">' . ReceiptGenerator::generate($reg, $payment, $totalPaid) . '</body></html>';
        $options = new \Dompdf\Options();
        $options->setIsRemoteEnabled(true);   // the logo <img> is a remote absolute URL
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html);
        $dompdf->render();
        return $dompdf->output();
    }
}
