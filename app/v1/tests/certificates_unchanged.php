<?php
// Proves existing certificates and their look do not change.
//   php app/v1/tests/certificates_unchanged.php seed            adds three old-style certificates (scratch database; needs assignment_flow_seed.php first)
//   php app/v1/tests/certificates_unchanged.php save <file>     remembers a checksum of every existing certificate row (all columns except the new ones)
//                                                                and of the HTML the existing certificate template makes for each one
//   php app/v1/tests/certificates_unchanged.php compare <file>  does it again and says IDENTICAL or CHANGED
// Run "save" with the OLD code and database, then migrate and run "compare" with the NEW code.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!getenv('DAZU_DB_NAME')) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database.\n"); exit(2); }
define('UPLOAD_TEST_MODE', 1);
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../models/CertificateModel.php';
require_once __DIR__ . '/../services/CertificateService.php';
require_once __DIR__ . '/../helpers/certificate_pdf.php';

$mode = $argv[1] ?? ''; $file = $argv[2] ?? '';
$db = \Models\Connection::getInstance()->getConnection();
if ($mode === 'seed') {
    $skills = [null, 'and has met all the requirements of the programme', 'Covering web design, databases and a final project.'];
    foreach ([11, 12, 13] as $i => $uid) {
        $r = $db->prepare('SELECT id, course_id, full_name FROM registrations WHERE user_id = ? LIMIT 1');
        $r->execute([$uid]);
        $reg = $r->fetch(PDO::FETCH_ASSOC);
        $db->prepare("INSERT INTO certificates (registration_id, course_id, certificate_no, qr_code_hash, status, snapshot_name, snapshot_course, snapshot_duration_months, snapshot_dates, taught_skills, tutor_name, ceo_name, issued_at)
                      VALUES (?, ?, ?, ?, 'issued', ?, 'Seed Course', ?, 'Jan 2025 to Jan 2026', ?, 'Tina Tutor', 'Simon Mwangi', '2026-01-15 10:00:00')")
           ->execute([$reg['id'], $reg['course_id'], 'DDS-2026-90' . $i, md5("seed$i"), $i === 2 ? 'a very long student name indeed for the line' : $reg['full_name'], 12 + $i * 6, $skills[$i]]);
    }
    echo "Seeded 3 old-style certificates.\n";
    exit(0);
}
if (!in_array($mode, ['save', 'compare'], true) || $file === '') { fwrite(STDERR, "Usage: certificates_unchanged.php seed|save|compare <file>\n"); exit(2); }
$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
$cols = $db->query($driver === 'pgsql'
    ? "SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'certificates' ORDER BY ordinal_position"
    : "SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'certificates' ORDER BY ordinal_position")->fetchAll(PDO::FETCH_COLUMN);
$cols = array_values(array_filter(array_map('strtolower', $cols), fn($c) => !in_array($c, ['application_id', 'version', 'replaced_by_id'], true)));
$saved = $mode === 'compare' ? json_decode((string) file_get_contents($file), true) : null;
$bound = $mode === 'save' ? (int) $db->query('SELECT COALESCE(MAX(id), 0) FROM certificates')->fetchColumn() : (int) $saved['bound'];
$rows = $db->query('SELECT ' . implode(', ', $cols) . " FROM certificates WHERE id <= $bound ORDER BY id")->fetchAll(PDO::FETCH_NUM);
$ctx = hash_init('md5');
foreach ($rows as $r) hash_update($ctx, json_encode(array_map(fn($v) => $v === null ? null : (string) $v, $r)) . "\n");
$rowHash = hash_final($ctx);
$model = new \Models\CertificateModel($db);
$render = [];
foreach ($db->query("SELECT id FROM certificates WHERE id <= $bound ORDER BY id")->fetchAll(PDO::FETCH_COLUMN) as $id) {
    $render[(int) $id] = md5(cert_render_html($model->getCertificateById((int) $id)));
}
$out = ['bound' => $bound, 'rows' => count($rows), 'row_md5' => $rowHash, 'render' => $render];
if ($mode === 'save') {
    file_put_contents($file, json_encode($out, JSON_PRETTY_PRINT));
    echo "certificates: {$out['rows']} rows, row checksum $rowHash\n";
    foreach ($render as $id => $h) echo "certificate $id look (HTML) checksum $h\n";
    exit(0);
}
$bad = 0;
$cmp = function (string $what, $a, $b) use (&$bad) { $same = $a === $b; if (!$same) $bad++; echo ($same ? 'IDENTICAL ' : 'CHANGED   ') . $what . "\n"; };
$cmp("certificate rows ({$out['rows']}), checksum $rowHash", $saved['row_md5'], $rowHash);
foreach ($render as $id => $h) $cmp("certificate $id look (HTML) checksum $h", $saved['render'][$id] ?? null, $h);
echo $bad ? "$bad CHANGED\n" : "All identical\n";
exit($bad ? 1 : 0);
