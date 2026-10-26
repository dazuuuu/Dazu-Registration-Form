<?php
// Public check of a letter of recommendation: the QR on the letter opens verify/letter.php?k=<hash>.
// A wrong, unknown or not-released (or hidden again) letter gets the same "cannot verify" page. Shows only the name, the course and the
// finish date: never a mark, a result, a comment or the tutor's sentence. No login, noindex.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
$db = \Models\Connection::getInstance()->getConnection();
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$k = strtolower((string) ($_GET['k'] ?? ''));
$row = null;
if (preg_match('/^[a-f0-9]{32}$/', $k)) {
    try {
        $st = $db->prepare("SELECT r.full_name, c.title, f.finished_on
                            FROM course_release cr
                            JOIN registrations r ON r.user_id = cr.student_user_id AND r.course_id = cr.course_id AND r.status = 'approved'
                            JOIN courses c ON c.id = cr.course_id
                            LEFT JOIN course_finish f ON f.student_user_id = cr.student_user_id AND f.course_id = cr.course_id AND f.undone_at IS NULL
                            WHERE cr.letter_hash = ? AND cr.letter_released_at IS NOT NULL LIMIT 1");
        $st->execute([$k]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $ex) {
        $row = null;
    }
}
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title>Verify a letter · Dazu Training Institute</title>
<style>body{margin:0;background:#f0f0f0;font-family:Poppins,system-ui,sans-serif;color:#373435}main{max-width:440px;margin:0 auto;padding:28px 16px;text-align:center}
.b{display:inline-block;background:#e4f2dd;color:#2f7a14;font-weight:700;border-radius:999px;padding:6px 14px;font-size:13px}.box{background:#fff;border-radius:14px;margin-top:18px;padding:16px;text-align:left;font-size:14px}
.w{width:64px;height:64px;border-radius:50%;background:#fde2e2;display:flex;align-items:center;justify-content:center;margin:20px auto 12px;font-size:30px;color:#b91c1c}</style></head><body><main>
<?php if ($row): ?>
  <span class="b">&#10003; Verified letter of recommendation</span>
  <h1 style="font-size:20px;margin:16px 0 2px"><?php echo $e(mb_strtoupper($row['full_name'])); ?></h1>
  <div class="box"><div>Completed <strong><?php echo $e($row['title']); ?></strong> at Dazu Training Institute.</div>
    <?php if ($row['finished_on']): ?><div style="margin-top:6px;color:#6b7067">Finished <?php echo $e(date('j F Y', strtotime($row['finished_on']))); ?></div><?php endif; ?></div>
  <p style="font-size:12px;color:#777;margin-top:14px">This page only confirms that Dazu Training Institute issued this letter.</p>
<?php else: ?>
  <div class="w">!</div><h1 style="font-size:20px;margin:0 0 6px">We cannot verify this letter</h1>
  <p style="font-size:13px;color:#777">This letter is not active, or it is not in our records. Please do not trust it until the office confirms it.</p>
<?php endif; ?>
</main></body></html>
