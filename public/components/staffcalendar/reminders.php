<?php
// public/components/staffcalendar/reminders.php — the small "Reminders" list of the Staff Calendar (the in-app copy of the reminders sent by app/v1/tools/staffcalendar_reminders.php).
//  GET  ?json=1            -> {unread, items[]} for the logged-in person only
//  POST op=read (id or 0)  -> marks one (or all) of the person's own reminders as read (security token required)
//  GET  (no json)          -> the same list as a plain page
// The person is always the logged-in one; a reminder id of somebody else changes nothing. Role is checked in boot.php (plain "Not allowed." for the JSON request).
$json = ($_GET['json'] ?? '') === '1' || $_SERVER['REQUEST_METHOD'] === 'POST';
if ($json) $sc_plain = true;
$page_title = 'Reminders';
$sa_custom_header = true;
require __DIR__ . '/boot.php';
require_once __DIR__ . '/../../../app/v1/services/StaffReminders.php';

$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ready = srm_installed($db);
$uid = (int) $actor['uid'];

if ($json) {
    header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
    if (!$ready) { echo json_encode(['unread' => 0, 'items' => []]); exit; }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verify($_POST['csrf_token'] ?? null)) { http_response_code(403); echo json_encode(['ok' => false, 'error' => 'Security check failed.']); exit; }
        srm_inbox_read($db, $uid, (int) ($_POST['id'] ?? 0));
        if (!empty($_POST['back'])) { header_remove('Content-Type'); header('Location: ' . url($sc_portal . '/staffcalendar/reminders.php')); exit; }
    }
    echo json_encode(['ok' => true, 'unread' => srm_inbox_unread($db, $uid), 'items' => srm_inbox_list($db, $uid)], JSON_UNESCAPED_UNICODE);
    exit;
}

$self = url($sc_portal . '/staffcalendar/reminders.php');
if (!$ready) { $content = '<div class="alert alert-warning">Reminders are not installed yet. Ask the office to run System Updates (migration 121).</div>'; return; }
$items = srm_inbox_list($db, $uid, 60);
$unread = srm_inbox_unread($db, $uid);
ob_start();
?>
<style>
.rm{--g:#048C44;--gd:#037A3B;--gl:#E3F3EA;--mut:#6b7067;--line:#e6e9e4;max-width:720px;color:#1d1b1c}
.rm h1{font-size:1.5rem;font-weight:800;margin:0 0 2px}.rm .sub{color:var(--mut);font-size:.9rem;margin:0 0 14px}
.rm a.back{color:var(--gd);font-weight:700;text-decoration:none;font-size:.88rem}
.rm-card{background:#fff;border-radius:16px;box-shadow:0 1px 2px rgba(29,27,28,.04),0 6px 20px rgba(29,27,28,.05);overflow:hidden}
.rm-item{display:block;padding:12px 16px;border-top:1px solid var(--line);text-decoration:none;color:inherit}.rm-item:first-child{border-top:0}
.rm-item.new{background:var(--gl)}.rm-item b{display:block;font-size:.92rem}.rm-item span{display:block;font-size:.9rem;overflow-wrap:anywhere}.rm-item small{color:var(--mut)}
.rm-empty{padding:22px 16px;color:var(--mut)}
.rm-btn{background:var(--g);color:#fff;border:0;border-radius:10px;padding:8px 16px;font-weight:700;font-size:.86rem;cursor:pointer;font-family:inherit}
@media (max-width:767.98px){.rm h1{font-size:1.25rem}.rm-btn{padding:10px 16px}}
</style>
<div class="rm">
    <a class="back" href="<?php echo $e($sc_base); ?>"><i class="fas fa-arrow-left"></i> Staff Calendar</a>
    <h1>Reminders</h1>
    <p class="sub"><?php echo $unread > 0 ? $e($unread . ' unread') : 'You are all caught up.'; ?> You can turn reminders on or off in “My settings” on the calendar.</p>
    <?php if ($unread > 0) { ?>
        <form method="post" action="<?php echo $e($self); ?>" style="margin-bottom:12px"><?php echo csrf_field(); ?><input type="hidden" name="op" value="read"><input type="hidden" name="id" value="0"><input type="hidden" name="back" value="1"><button class="rm-btn" type="submit">Mark all as read</button></form>
    <?php } ?>
    <div class="rm-card">
        <?php if (!$items) { ?><div class="rm-empty">No reminders yet.</div><?php } ?>
        <?php foreach ($items as $r) { ?>
            <a class="rm-item<?php echo $r['read'] ? '' : ' new'; ?>" href="<?php echo $e(url($r['url'] !== '' ? $r['url'] : $sc_portal . '/staffcalendar/index.php')); ?>">
                <b><?php echo $e($r['title']); ?></b><span><?php echo $e($r['body']); ?></span><small><?php echo $e((new DateTime($r['at'], scal_tz()))->format('D j M, g:i a')); ?></small>
            </a>
        <?php } ?>
    </div>
</div>
<?php
$content = ob_get_clean();
