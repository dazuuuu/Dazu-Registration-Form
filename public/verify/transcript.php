<?php
// Public check of an academic transcript: the QR on a released transcript opens verify/transcript.php?t=<token>.
// Shows only the name, the course, the transcript number and the release date: never a mark or a grade.
// A wrong or unknown code gets the same "cannot verify" page. No login, noindex.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
$db = \Models\Connection::getInstance()->getConnection();
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$t = strtolower((string) ($_GET['t'] ?? ''));
$row = null;
if (preg_match('/^[a-f0-9]{40}$/', $t)) {
    try {
        $st = $db->prepare('SELECT surname, other_names, course_title, transcript_no, released_at FROM transcripts WHERE verify_token = ? LIMIT 1');
        $st->execute([$t]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $ex) {
        $row = null;
    }
}
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title>Verify a transcript · Dazu Training Institute</title>
<style>body{margin:0;background:#f0f0f0;font-family:Poppins,system-ui,sans-serif;color:#373435}main{max-width:440px;margin:0 auto;padding:28px 16px;text-align:center}
.b{display:inline-block;background:#e4f2dd;color:#2f7a14;font-weight:700;border-radius:999px;padding:6px 14px;font-size:13px}.box{background:#fff;border-radius:14px;margin-top:18px;padding:16px;text-align:left;font-size:14px}
.k{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#6b7067;margin-top:10px}.k:first-child{margin-top:0}.v{font-weight:600}
.w{width:64px;height:64px;border-radius:50%;background:#fde2e2;display:flex;align-items:center;justify-content:center;margin:20px auto 12px;font-size:30px;color:#b91c1c}</style></head><body><main>
<?php if ($row): ?>
  <span class="b">&#10003; Verified academic transcript</span>
  <h1 style="font-size:20px;margin:16px 0 2px"><?php echo $e(mb_strtoupper(trim($row['other_names'] . ' ' . $row['surname']))); ?></h1>
  <div class="box">
    <div class="k">Course</div><div class="v"><?php echo $e($row['course_title']); ?></div>
    <div class="k">Transcript number</div><div class="v"><?php echo $e($row['transcript_no']); ?></div>
    <div class="k">Released on</div><div class="v"><?php echo $e(date('j F Y', strtotime($row['released_at']))); ?></div>
  </div>
  <p style="font-size:12px;color:#777;margin-top:14px">This page only confirms that Dazu Training Institute issued this transcript. Marks are not shown here.</p>
<?php else: ?>
  <div class="w">!</div><h1 style="font-size:20px;margin:0 0 6px">We cannot verify this transcript</h1>
  <p style="color:#6b7067;font-size:14px">The code is not one Dazu Training Institute issued. Contact the office if you think this is a mistake.</p>
<?php endif; ?>
</main></body></html>
