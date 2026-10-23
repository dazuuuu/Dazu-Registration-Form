<?php
// Public tutor ID verification: the QR on the front of every tutor card opens verify/tutor/<token>.
// Shows only: photo (setting), printed name, role, ID number, status, course area, year issued, checked on.
// A wrong token, a stopped token and an inactive tutor all get the same "cannot verify" page.
// No login, noindex, rate limited by IP (only a salted hash of the IP is kept, for one hour), no IP stored in the database.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/services/StaffIdService.php';

$started = microtime(true);
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
$db = \Models\Connection::getInstance()->getConnection();
$s = idset_all($db);
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

// Rate limit: 30 checks per IP per 10 minutes.
$limited = false;
$dir = storage_path('cache/verify_rl');
if (is_dir($dir) || @mkdir($dir, 0775, true)) {
    $slot = $dir . '/' . hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . floor(time() / 600) . '|' . __FILE__ . php_uname('n'));
    $n = (int) @file_get_contents($slot) + 1;
    @file_put_contents($slot, (string) $n, LOCK_EX);
    $limited = $n > 30;
    if (mt_rand(1, 50) === 1) foreach (glob($dir . '/*') ?: [] as $f) if (filemtime($f) < time() - 1200) @unlink($f);
}

$token = (string) ($_GET['t'] ?? '');
$t = null;
if (!$limited && preg_match('/^[A-Za-z0-9]{32,64}$/', $token)) {
    try {
        $st = $db->prepare(
            "SELECT t.id, t.user_id, t.display_name, t.display_role, t.staff_no, t.id_issued_at,
                    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name,''), ' ', COALESCE(up.last_name,''))), ''), u.username) AS full_name,
                    (SELECT GROUP_CONCAT(DISTINCT c.title ORDER BY c.title SEPARATOR ', ') FROM course_tutors ct JOIN courses c ON c.id = ct.course_id WHERE ct.tutor_id = t.id) AS teaches
             FROM tutors t JOIN users u ON u.id = t.user_id LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE t.verify_token = ? AND t.is_active = 1 AND u.is_active = 1 AND t.staff_no IS NOT NULL LIMIT 1"
        );
        $st->execute([$token]);
        $t = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $ex) {
        $t = null;
    }
}
if ($limited) http_response_code(429);
$photo = null;
if ($t) {
    $db->prepare('UPDATE tutors SET verify_scans = verify_scans + 1, last_scanned_at = NOW() WHERE id = ?')->execute([(int) $t['id']]);
    if ($s['show_photo_on_verify'] === '1' && ($abs = idc_student_photo_file($db, (int) $t['user_id']))) {
        $crop = json_decode((string) ($db->query('SELECT photo_crop FROM tutors WHERE id = ' . (int) $t['id'])->fetchColumn()), true);
        $photo = idc_photo_uri($abs, 32, 40, 2, is_array($crop) ? $crop : null, 0.15);   // small copy, never a link to the file
    }
}
// Similar response time whether or not the token is valid.
$wait = 0.25 - (microtime(true) - $started);
if ($wait > 0) usleep((int) ($wait * 1e6));
$eat = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
require_once __DIR__ . '/../../app/v1/helpers/certificate_pdf.php';
$logoAbs = cert_logo_file();
$logoUri = $logoAbs ? 'data:' . (str_ends_with(strtolower($logoAbs), '.png') ? 'image/png' : 'image/jpeg') . ';base64,' . base64_encode((string) file_get_contents($logoAbs)) : null;
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow"><title>Verify a tutor ID · Dazu Training Institute</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}body{margin:0;background:#f0f0f0;font-family:Poppins,system-ui,sans-serif;color:#373435}
.top{background:#fff;display:flex;justify-content:space-between;align-items:center;padding:12px 16px}.top img{height:30px}.top span{font-size:12px;color:#777}
main{max-width:440px;margin:0 auto;padding:22px 16px 40px;text-align:center}
.badge{display:inline-flex;gap:6px;align-items:center;background:#e4f2dd;color:#2f7a14;font-weight:700;font-size:13px;border-radius:999px;padding:6px 14px}
.photo{width:128px;aspect-ratio:4/5;margin:18px auto 12px;border:4px solid #44A024;border-radius:14px;overflow:hidden;background:#fff}.photo img{width:100%;height:100%;object-fit:cover;display:block}
h1{font-size:20px;margin:6px 0 2px;text-transform:uppercase}.role{color:#44A024;font-weight:700;font-size:11px;letter-spacing:.16em;text-transform:uppercase}
.pill{display:inline-block;background:#44A024;color:#fff;font-weight:700;border-radius:999px;padding:4px 16px;margin-top:8px;font-size:14px}
.box{background:#fff;border-radius:14px;margin-top:18px;text-align:left;padding:4px 16px}.row{display:flex;justify-content:space-between;gap:12px;padding:11px 0;border-bottom:1px solid #eee;font-size:13px}.row:last-child{border:0}.row span{color:#777}.row b{text-align:right}
.note{font-size:12px;color:#777;margin-top:14px;line-height:1.5}
.warn{width:64px;height:64px;border-radius:50%;background:#fde2e2;display:flex;align-items:center;justify-content:center;margin:20px auto 12px}
</style></head><body>
<div class="top"><?php echo $logoUri ? '<img src="' . $logoUri . '" alt="Dazu Training Institute">' : '<b>DAZU</b>'; ?><span>dazu.ac.ke/verify</span></div>
<main>
<?php if ($t): $name = trim((string) $t['display_name']) ?: $t['full_name']; $role = trim((string) $t['display_role']) ?: 'Tutor'; ?>
  <span class="badge">&#10003; Verified Dazu tutor</span>
  <?php if ($photo): ?><div class="photo"><img src="<?php echo $photo; ?>" alt="Photo of <?php echo $e($name); ?>"></div><?php else: ?><div style="height:18px"></div><?php endif; ?>
  <h1><?php echo $e($name); ?></h1>
  <div class="role"><?php echo $e($role); ?></div>
  <div class="pill"><?php echo $e($t['staff_no']); ?></div>
  <div class="box">
    <div class="row"><span>Status</span><b>Active</b></div>
    <?php if ($t['teaches']): ?><div class="row"><span>Teaches</span><b><?php echo $e($t['teaches']); ?></b></div><?php endif; ?>
    <div class="row"><span>Issued</span><b><?php echo $e($t['id_issued_at'] ? date('Y', strtotime($t['id_issued_at'])) : $eat->format('Y')); ?></b></div>
    <div class="row"><span>Checked on</span><b><?php echo $e($eat->format('j M Y, H:i')); ?> EAT</b></div>
  </div>
  <p class="note">This page confirms that this person is a tutor at Dazu Training Institute. Compare the photo with the person in front of you.</p>
<?php else: ?>
  <div class="warn"><svg width="30" height="30" viewBox="0 0 24 24" fill="#b91c1c"><path d="M12 2L1 21h22L12 2zm1 15h-2v2h2v-2zm0-7h-2v5h2v-5z"/></svg></div>
  <h1 style="text-transform:none">We cannot verify this ID</h1>
  <p class="note" style="margin-top:6px">This ID is not active, or it is not in our records. Please do not trust it until the office confirms it.</p>
  <div class="box" style="padding:14px 16px"><b style="font-size:14px">Ask the office</b><div style="font-size:14px;margin-top:6px"><?php echo $e($s['phone']); ?><br><?php echo $e($s['email']); ?></div></div>
<?php endif; ?>
</main></body></html>
