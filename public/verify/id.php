<?php
// Public student ID card verification — the QR code on the back of every card opens verify/id.php?k=<qr_hash>.
// Shows only the student's name, course and status (never a phone number, email or ID number).
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/services/IdCardService.php';

$page_title = 'Verify a Student ID — Dazu Training Institute';
$page_description = 'Check that a Dazu Training Institute student ID card is genuine.';

$key = strtolower(trim((string) ($_GET['k'] ?? '')));
$card = null;
if (preg_match('/^[a-f0-9]{16,64}$/', $key)) {
    $st = \Models\Connection::getInstance()->getConnection()->prepare(
        "SELECT k.status, k.valid_until, k.snapshot_name, k.snapshot_course, r.status AS reg_status
         FROM student_id_cards k JOIN registrations r ON r.id = k.registration_id
         WHERE k.qr_hash = ? AND k.status IN ('generated','ready','collected') LIMIT 1"
    );
    $st->execute([$key]);
    $card = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$card) {
        // Tutor staff card: active while the tutor account is.
        $st = \Models\Connection::getInstance()->getConnection()->prepare(
            "SELECT IF(t.is_active = 1 AND u.is_active = 1, 'approved', 'inactive') AS reg_status, 'generated' AS status,
                    YEAR(DATE_ADD(t.id_issued_at, INTERVAL 2 YEAR)) AS valid_until, 'Tutor' AS snapshot_course,
                    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name,''), ' ', COALESCE(up.last_name,''))), ''), u.username) AS snapshot_name, 1 AS staff, u.email
             FROM tutors t JOIN users u ON u.id = t.user_id LEFT JOIN user_profiles up ON up.user_id = u.id WHERE t.id_qr_hash = ? LIMIT 1"
        );
        $st->execute([$key]);
        $card = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if (!$card) {
        // Manually entered card: active until an admin deletes or deactivates it.
        try {
            require_once __DIR__ . '/../../app/v1/services/ManualIdService.php';
            $st = \Models\Connection::getInstance()->getConnection()->prepare(
                "SELECT IF(is_active = 1, 'approved', 'inactive') AS reg_status, 'generated' AS status, valid_until, role, title, full_name
                 FROM manual_id_cards WHERE qr_hash = ? LIMIT 1"
            );
            $st->execute([$key]);
            if ($m = $st->fetch(PDO::FETCH_ASSOC)) {
                $mc = midc_card($m + ['id_no' => '', 'qr_hash' => $key, 'photo_path' => '']);
                $card = ['reg_status' => $m['reg_status'], 'status' => 'generated', 'valid_until' => $m['valid_until'],
                         'snapshot_name' => $m['full_name'], 'snapshot_course' => $mc['snapshot_course'], 'staff' => $m['role'] !== 'student'];
            }
        } catch (Throwable $e) { /* table not installed yet */ }
    }
}
$staffCard = !empty($card['staff']);
// Staff (tutors, admins, any Dazu staff): name, photo and position come from the website's Team section (Admin → Team),
// matched by email, then by name.
$photo = null;
if ($staffCard) {
    try {
        $st = \Models\Connection::getInstance()->getConnection()->prepare(
            "SELECT name, position, photo FROM team_members
             WHERE (? <> '' AND email <> '' AND LOWER(email) = LOWER(?)) OR LOWER(TRIM(name)) = LOWER(TRIM(?))
             ORDER BY (? <> '' AND LOWER(email) = LOWER(?)) DESC LIMIT 1"
        );
        $em = (string) ($card['email'] ?? '');
        $st->execute([$em, $em, (string) $card['snapshot_name'], $em, $em]);
        if ($tm = $st->fetch(PDO::FETCH_ASSOC)) {
            if (trim((string) $tm['name']) !== '') $card['snapshot_name'] = $tm['name'];
            if (trim((string) $tm['position']) !== '') $card['snapshot_course'] = $tm['position'];
            $rel = ltrim((string) $tm['photo'], '/');
            if ($rel !== '' && is_file(__DIR__ . '/../' . $rel)) $photo = url($rel);
        }
    } catch (Throwable $e) { /* no team section */ }
}
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$expired = $card && (int) $card['valid_until'] > 0 && (int) $card['valid_until'] < (int) date('Y');
$active = $card && !$expired && $card['reg_status'] === 'approved';

$extra_head = <<<'CSS'
<style>
.vi{background:#f6f8f5;min-height:70vh;padding:56px 16px;color:#1f2937;font-family:Poppins,system-ui,sans-serif}
.vi *{box-sizing:border-box}
.vi-wrap{max-width:560px;margin:0 auto}
.vi-chip{display:inline-block;font-size:11px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:#2f7a14;background:#eaf4e4;border:1px solid #cfe5c2;border-radius:999px;padding:4px 12px}
.vi h1{font-size:clamp(26px,5vw,36px);font-weight:800;letter-spacing:-.02em;margin:14px 0 22px;color:#0f172a;text-transform:none}
.vi-res{border-radius:18px;overflow:hidden;border:1px solid #e2e8f0;background:#fff;box-shadow:0 8px 30px rgba(15,23,42,.06)}
.vi-band{display:flex;align-items:center;gap:14px;padding:18px 22px;color:#fff}
.vi-band.ok{background:#44A024}.vi-band.bad{background:#b91c1c}.vi-band.warn{background:#b45309}
.vi-band b{font-size:18px;display:block}.vi-band small{opacity:.92}
.vi-body{padding:22px}
.vi-name{font-size:24px;font-weight:800;color:#373435;margin:0 0 4px}
.vi-k{font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#44A024;margin-top:14px}
.vi-v{font-weight:600;color:#0f172a;margin-top:2px}
.vi-foot{font-size:12px;color:#64748b;margin-top:16px;text-align:center}
.vi svg{flex:none;display:block}
</style>
CSS;

ob_start();
?>
<div class="vi"><div class="vi-wrap">
  <span class="vi-chip"><?php echo $staffCard ? 'Staff' : 'Student'; ?> ID Verification</span>
  <h1>Dazu <?php echo $staffCard ? 'staff' : 'student'; ?> ID card</h1>
  <div class="vi-res">
    <?php if (!$card): ?>
      <div class="vi-band bad">
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
        <div><b>Not a valid Dazu ID card</b><small>This code was not issued by Dazu Training Institute, or the card has been cancelled.</small></div>
      </div>
    <?php else: ?>
      <div class="vi-band <?php echo $active ? 'ok' : 'warn'; ?>">
        <?php if ($active): ?>
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="12" cy="12" r="10"/><path d="M7 12.5l3 3 7-7"/></svg>
          <div><b><?php echo $staffCard ? 'Active staff member' : 'Active student'; ?></b><small>This card was issued by Dazu Training Institute.</small></div>
        <?php else: ?>
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg>
          <div><b><?php echo $expired ? 'Card expired' : 'Not currently active'; ?></b><small>Genuine Dazu card, but <?php echo $expired ? 'it was valid until ' . (int) $card['valid_until'] : ($staffCard ? 'this person no longer works with us' : 'the student is not currently enrolled'); ?>.</small></div>
        <?php endif; ?>
      </div>
      <div class="vi-body">
        <?php if ($photo): ?><img src="<?php echo $e($photo); ?>" alt="" style="width:110px;height:110px;object-fit:cover;border-radius:50%;display:block;margin:0 auto 14px;border:3px solid #eaf4e4"><?php endif; ?>
        <p class="vi-name"><?php echo $e($card['snapshot_name']); ?></p>
        <div class="vi-k"><?php echo $staffCard ? 'Position' : 'Course'; ?></div><div class="vi-v"><?php echo $e($card['snapshot_course']); ?></div>
        <div class="vi-k">Status</div><div class="vi-v"><?php echo $active ? 'Active' : ($expired ? 'Expired' : 'Inactive'); ?><?php echo $card['valid_until'] ? ' · valid until ' . (int) $card['valid_until'] : ''; ?></div>
      </div>
    <?php endif; ?>
  </div>
  <p class="vi-foot">Check that the name above matches the card and the person presenting it.</p>
</div></div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
