<?php
// public/components/staffcalendar/gifts.php — "Gift history" and the gift settings (the amount, and who is excluded). SUPER ADMIN ONLY: boot.php refuses everyone else with the real role
// read from the database, and every action is checked again in StaffGifts. The tracker only records who says they sent a gift: no money moves.
$sc_portal = 'superadmin';
$page_title = 'Gift history';
$sa_custom_header = true;
require __DIR__ . '/boot.php';
require_once __DIR__ . '/../../../app/v1/services/StaffGifts.php';

$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$self = url('superadmin/staffcalendar/gifts.php');
if (!sbd_installed($db) || !sg_installed($db)) {
    $content = '<div class="alert alert-warning">The gift tracker is not installed yet. Ask the office to run System Updates (migration 117).</div>';
    return;
}

$now = scal_now();
$amount = sg_amount($db);
$people = sg_people($db);
$excl = sg_exclusions($db);
$history = sg_history($db, $now);

ob_start();
?>
<style>
.gh{--g:#048C44;--gd:#037A3B;--gl:#E3F3EA;--mut:#6b7067;--line:#e6e9e4;max-width:980px;color:#1d1b1c}
.gh h1{font-size:1.5rem;font-weight:800;margin:0 0 2px}.gh .sub{color:var(--mut);font-size:.9rem;margin:0 0 16px}
.gh a.back{color:var(--gd);font-weight:700;text-decoration:none;font-size:.88rem}
.gh-card{background:#fff;border-radius:16px;box-shadow:0 1px 2px rgba(29,27,28,.04),0 6px 20px rgba(29,27,28,.05);padding:16px 18px;margin-bottom:14px}
.gh-card h2{font-size:1.05rem;font-weight:700;margin:0 0 4px}
.gh input[type=text],.gh input[type=number]{border:1px solid #d8ddd5;border-radius:10px;padding:8px 12px;font-family:inherit;font-size:.95rem}
.gh-btn{background:var(--g);color:#fff;border:0;border-radius:10px;padding:8px 16px;font-weight:700;font-size:.86rem;cursor:pointer;font-family:inherit}
.gh-row{display:grid;grid-template-columns:1fr auto 1.2fr auto;gap:10px;align-items:center;padding:8px 0;border-top:1px solid var(--line)}
.gh-row small{color:var(--mut)}
.gh-h{display:flex;flex-wrap:wrap;gap:6px 14px;align-items:baseline;justify-content:space-between}
.gh-pill{border-radius:999px;padding:2px 10px;font-weight:700;font-size:.74rem;background:var(--gl);color:var(--gd)}.gh-pill.x{background:#eee;color:#555}
.gh-meta{color:var(--mut);font-size:.88rem;margin-top:4px}
@media (max-width:767.98px){.gh-row{grid-template-columns:1fr;gap:6px}.gh h1{font-size:1.25rem}.gh-btn{padding:10px 16px}}
</style>
<div class="gh">
    <a class="back" href="<?php echo $e(url('superadmin/staffcalendar/index.php')); ?>"><i class="fas fa-arrow-left"></i> Staff Calendar</a>
    <h1>Gift history</h1>
    <p class="sub">The gift tracker only records who says they sent a gift and who received it. Dazu does not move any money.</p>

    <div class="gh-card">
        <h2>Gift amount: <?php echo $e(sg_kes($amount)); ?></h2>
        <div class="gh-meta">The amount and the “Excluded from gifts” list are changed in <a href="<?php echo $e(url('superadmin/staffcalendar/settings.php') . '?tab=gifts'); ?>">Settings · Staff Calendar · Gifts</a>. A new amount applies to new birthdays only. <?php echo $excl ? (int) count($excl) . ' ' . (count($excl) === 1 ? 'person is' : 'people are') . ' excluded.' : 'Nobody is excluded.'; ?></div>
    </div>

    <h2 style="font-size:1.1rem;font-weight:700;margin:18px 0 8px">Past birthdays</h2>
    <?php if (!$history): ?><div class="gh-card" style="color:var(--mut)">No gift lists yet. A list is made the first time someone opens the Staff Calendar on a birthday.</div><?php endif; ?>
    <?php foreach ($history as $h): ?>
    <div class="gh-card">
        <div class="gh-h"><b><?php echo $e($h['person']); ?> · <?php echo $e((new DateTime($h['date'], scal_tz()))->format('D j M Y')); ?></b><span class="gh-pill<?php echo $h['open'] ? '' : ' x'; ?>"><?php echo $h['open'] ? 'Open' : 'Read-only history'; ?></span></div>
        <div class="gh-meta"><?php echo (int) $h['received']; ?> of <?php echo (int) $h['expected']; ?> received · <?php echo $e(sg_kes($h['received_kes'])); ?> of <?php echo $e(sg_kes($h['expected_kes'])); ?> · <?php echo $e(sg_kes($h['amount'])); ?> each<?php echo $h['says_sent'] ? ' · ' . (int) $h['says_sent'] . ' said sent, not yet confirmed' : ''; ?></div>
        <div class="gh-meta"><?php echo $h['not_yet'] ? 'Had not sent: ' . $e(implode(', ', $h['not_yet'])) : 'Everyone had sent.'; ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php
$content = ob_get_clean();
