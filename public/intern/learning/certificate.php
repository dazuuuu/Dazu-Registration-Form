<?php
// Training certificate (PDF) — only once every assigned learning item is done.
require __DIR__ . '/../_boot.php';
require_once __DIR__ . '/../../../app/v1/helpers/certificate_pdf.php';

$modules = in_modules($db, (int) $intern['id']);
if (!$modules || in_training_percent($modules) < 100) {
    SessionModel::setFlash('error', 'Finish all your learning first — the certificate unlocks at 100%.');
    header('Location: ' . url('intern/learning/index.php'));
    exit;
}
$done = 0;
foreach ($modules as $m) foreach ($m['items'] as $it) $done = max($done, strtotime((string) $it['done_at']));
in_audit($db, (int) $intern['id'], 'training_certificate_downloaded');

$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$logo = ($f = cert_logo_file()) ? '<img src="' . _cert_data_uri($f) . '" style="height:20mm;" />' : '';
$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>@page{size:A4 landscape;margin:0}*{margin:0;padding:0}
body{width:297mm;height:210mm;font-family:"Times New Roman",serif;color:#373435}</style></head><body>'
    . '<div style="position:absolute;left:10mm;top:10mm;width:277mm;height:190mm;border:2mm solid #44A024;"></div>'
    . '<div style="position:absolute;left:14mm;top:14mm;width:269mm;height:182mm;border:0.4mm solid #373435;"></div>'
    . '<div style="position:absolute;left:0;width:297mm;top:24mm;text-align:center;">' . $logo . '</div>'
    . '<div style="position:absolute;left:0;width:297mm;top:52mm;text-align:center;font-size:34pt;font-weight:bold;letter-spacing:2pt;">CERTIFICATE OF TRAINING</div>'
    . '<div style="position:absolute;left:0;width:297mm;top:70mm;text-align:center;font-size:12pt;letter-spacing:3pt;color:#44A024;font-weight:bold;">INTERNSHIP LEARNING PROGRAMME</div>'
    . '<div style="position:absolute;left:0;width:297mm;top:86mm;text-align:center;font-size:12pt;">This is to certify that</div>'
    . '<div style="position:absolute;left:0;width:297mm;top:96mm;text-align:center;font-size:30pt;font-style:italic;font-weight:bold;color:#44A024;">' . $e($intern['full_name']) . '</div>'
    . '<div style="position:absolute;left:40mm;width:217mm;top:118mm;text-align:center;font-size:13pt;line-height:1.5;">completed the internship training at Dazu Training Institute'
    . ($intern['department'] ? ' in the <b>' . $e($intern['department']) . '</b> department' : '') . ', covering: '
    . $e(implode(', ', array_column($modules, 'title'))) . '.</div>'
    . '<div style="position:absolute;left:40mm;width:217mm;top:160mm;font-size:11pt;">'
    . '<table style="width:217mm"><tr><td style="width:50%;border-top:0.4mm solid #373435;padding-top:2mm;text-align:center;">' . $e($intern['supervisor_name'] ?: 'Supervisor') . '<br><span style="font-size:9pt">SUPERVISOR</span></td>'
    . '<td style="width:50%;text-align:center;">Date: ' . date('j F Y', $done ?: time()) . '</td></tr></table></div>'
    . '</body></html>';
$opts = new \Dompdf\Options();
$opts->setIsRemoteEnabled(false);
$opts->setDefaultFont('Times-Roman');
$pdf = new \Dompdf\Dompdf($opts);
$pdf->loadHtml($html, 'UTF-8');
$pdf->setPaper('A4', 'landscape');
$pdf->render();
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Dazu_Training_Certificate.pdf"');
echo $pdf->output();
