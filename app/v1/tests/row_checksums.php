<?php
// Checksum of every existing row of the assignment tables (all columns except the new subject_id) and of the files they point to.
//   php app/v1/tests/row_checksums.php save <file>      remembers the rows that exist now (the "before")
//   php app/v1/tests/row_checksums.php compare <file>   checksums the SAME rows again and says IDENTICAL or CHANGED per table
// Only rows that existed at "save" time are looked at (rows with an id up to the saved highest id), so tests that add new rows do not hide or cause a change.
// Run against a scratch database: DAZU_DB_NAME=<db> [DAZU_DB_DRIVER=mysql DAZU_DB_PORT=3307 DAZU_DB_HOST=127.0.0.1 DAZU_DB_USER=root DAZU_DB_PASS=]
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!getenv('DAZU_DB_NAME')) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database.\n"); exit(2); }
define('UPLOAD_TEST_MODE', 1);
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../helpers/assignment_files.php';

$mode = $argv[1] ?? ''; $file = $argv[2] ?? '';
if (!in_array($mode, ['save', 'compare'], true) || $file === '') { fwrite(STDERR, "Usage: row_checksums.php save|compare <file>\n"); exit(2); }
$db = \Models\Connection::getInstance()->getConnection();
$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

$tables = $db->query($driver === 'pgsql'
    ? "SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema() AND table_name LIKE 'tutor\\_assignment%' ORDER BY 1"
    : "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'tutor\\_assignment%' ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN);
$tables = array_map('strtolower', $tables);
$saved = $mode === 'compare' ? json_decode((string) file_get_contents($file), true) : null;
if ($mode === 'compare' && !$saved) { fwrite(STDERR, "Cannot read $file\n"); exit(2); }

$result = [];
foreach ($tables as $t) {
    $cols = $db->query($driver === 'pgsql'
        ? "SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = '$t' ORDER BY ordinal_position"
        : "SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$t' ORDER BY ordinal_position")->fetchAll(PDO::FETCH_COLUMN);
    $cols = array_values(array_filter(array_map('strtolower', $cols), fn($c) => $c !== 'subject_id'));
    $key = in_array('id', $cols, true) ? 'id' : (in_array('assignment_id', $cols, true) ? 'assignment_id' : null);
    $bound = $mode === 'save' ? ($key ? (int) $db->query("SELECT COALESCE(MAX($key), 0) FROM $t")->fetchColumn() : null) : ($saved['bounds'][$t] ?? null);
    $where = $key && $bound !== null ? " WHERE $key <= $bound" : '';
    $rows = $db->query('SELECT ' . implode(', ', $cols) . " FROM $t$where ORDER BY " . implode(', ', $cols))->fetchAll(PDO::FETCH_NUM);
    $ctx = hash_init('md5');
    foreach ($rows as $r) hash_update($ctx, json_encode(array_map(fn($v) => $v === null ? null : (string) $v, $r)) . "\n");
    $result[$t] = ['bound' => $bound, 'rows' => count($rows), 'md5' => hash_final($ctx)];
}

// The files the rows point to (private storage).
$fileCols = ['tutor_assignment_files' => 'file_path', 'tutor_assignment_attachments' => 'file_path', 'tutor_assignment_submissions' => 'file_path'];
$ctx = hash_init('md5'); $nf = 0; $missing = 0;
foreach ($fileCols as $t => $c) {
    if (!in_array($t, $tables, true)) continue;
    $bound = $mode === 'save' ? (int) $db->query("SELECT COALESCE(MAX(id), 0) FROM $t")->fetchColumn() : (int) ($saved['bounds'][$t] ?? 0);
    foreach ($db->query("SELECT id, $c FROM $t WHERE $c IS NOT NULL AND $c <> '' AND id <= $bound ORDER BY id")->fetchAll(PDO::FETCH_NUM) as [$id, $rel]) {
        $abs = asg_file_abs((string) $rel);
        if ($abs) { hash_update($ctx, "$t:$id:" . md5_file($abs) . "\n"); $nf++; } else { hash_update($ctx, "$t:$id:MISSING\n"); $missing++; }
    }
}
$result['(files on disk)'] = ['bound' => null, 'rows' => $nf, 'md5' => hash_final($ctx)];

if ($mode === 'save') {
    file_put_contents($file, json_encode(['bounds' => array_map(fn($r) => $r['bound'], $result), 'tables' => $result], JSON_PRETTY_PRINT));
    printf("%-42s %6s  %s\n", 'table', 'rows', 'md5');
    foreach ($result as $t => $r) printf("%-42s %6d  %s\n", $t, $r['rows'], $r['md5']);
    echo "saved to $file\n";
    exit(0);
}
$bad = 0;
printf("%-42s %6s  %-32s  %s\n", 'table', 'rows', 'md5 now', 'before');
foreach ($result as $t => $r) {
    $b = $saved['tables'][$t] ?? null;
    $same = $b && $b['md5'] === $r['md5'] && $b['rows'] === $r['rows'];
    if (!$same) $bad++;
    printf("%-42s %6d  %s  %s\n", $t, $r['rows'], $r['md5'], $same ? 'IDENTICAL' : 'CHANGED (before: ' . ($b['rows'] ?? '?') . ' rows ' . ($b['md5'] ?? '') . ')');
}
echo $bad ? "$bad table(s) CHANGED\n" : "All " . count($result) . " checksums IDENTICAL\n";
exit($bad ? 1 : 0);
