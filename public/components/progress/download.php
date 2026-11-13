<?php
// public/components/progress/download.php — the PDF: one student (mode=one), the ticked students (mode=selected) or every student in the
// current filter (mode=all). POST only, with the security token. Every requested student is compared with this viewer's list on the
// server; one student outside it refuses the whole request. Each download is written to the activity log.
require __DIR__ . '/boot.php';
require_once __DIR__ . '/../../../app/v1/helpers/progress_pdf.php';

function sp_refuse(int $code, string $msg, string $back): void { sp_deny($code, $msg, $back); }

$back = $sp_base . 'index.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sp_refuse(405, 'Use the Download buttons on the Student Progress page.', $back);
if (!csrf_verify($_POST['csrf_token'] ?? null)) sp_refuse(400, 'Security check failed. Please refresh and try again.', $back);

$mode = (string) ($_POST['mode'] ?? '');
if ($mode === 'all') {
    // Exactly the students the overview shows for this filter (same function, so "all" means the same list).
    $f = ['tab' => (string) ($_POST['tab'] ?? 'all'), 'q' => trim((string) ($_POST['q'] ?? '')), 'course' => (int) ($_POST['course'] ?? 0),
          'tutor' => $viewer['role'] === 'admin' ? (int) ($_POST['tutor'] ?? 0) : 0, 'sort' => (string) ($_POST['sort'] ?? 'attention')];
    $ids = array_keys(sp_list($db, $viewer, $f));
    $action = 'download_all';
} elseif ($mode === 'one' || $mode === 'selected') {
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])), fn($i) => $i > 0)));
    $allowed = sp_allowed_ids($db, $viewer, $ids);
    if (count($allowed) !== count($ids)) sp_refuse(403, 'You can only download students on your own list. Nothing was downloaded.', $back);
    $action = $mode === 'one' ? 'download_one' : 'download_selected';
} else {
    sp_refuse(400, 'Choose what to download.', $back);
}
if (!$ids) sp_refuse(400, 'There are no students to download.', $back);
if (count($ids) > SP_PDF_MAX) sp_refuse(400, 'That is ' . count($ids) . ' students. One PDF holds up to ' . SP_PDF_MAX . '. Narrow the filter (course or tutor) and download again.', $back);

try {
    $bytes = sp_pdf_build($db, $viewer, $ids);
} catch (Throwable $e) {
    error_log('[StudentProgress] PDF failed: ' . $e->getMessage());
    sp_refuse(500, 'The PDF could not be made. Please try again, or download fewer students.', $back);
}
sp_log($db, $viewer, $action, $ids);

$name = count($ids) === 1 ? 'student-progress-' . $ids[0] : 'student-progress-' . count($ids) . '-students';
while (ob_get_level()) ob_end_clean();
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $name . '-' . date('Y-m-d') . '.pdf"');
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: private, no-store');
echo $bytes;
exit;
