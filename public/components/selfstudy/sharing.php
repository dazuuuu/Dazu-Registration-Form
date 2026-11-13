<?php
// public/components/selfstudy/sharing.php — the "Sharing" tab: which other tutors can open the course (No access / Can view / Can edit).
// Only the owner and admin can change it; every other tutor with access sees it read-only. Checked again on the server by access_api.php.
require __DIR__ . '/boot.php';
require_once __DIR__ . '/ui.php';

$id = (int) ($_GET['id'] ?? 0);
if (!$sst_ready || !ssc_installed($db) || !ssa_installed($db)) sst_deny(503, 'Self-study needs migration 109 first.', $sst_base);
$c = sst_course($db, $viewer, $id);
if (!$c) sst_deny(404, 'That course was not found, or it was not shared with you.', $sst_base);
$caps = ssa_caps($c, $viewer);
$page_title = 'Sharing: ' . $c['title'];
$extra_css = '<link rel="stylesheet" href="' . sst_e(url('assets/css/selfstudy-tutor.css')) . '?v=3">';
$cfg = ['csrf' => csrf_token(), 'endpoint' => $sst_base . 'access_api.php', 'course' => $id, 'caps' => $caps, 'tutors' => $caps['pick'] ? ssa_tutors($db, $viewer, $id) : [], 'page' => 'sharing', 'isAdmin' => $viewer['role'] === 'admin'];
ob_start();
?>
<div class="sst-page sst-ed">
<?php sst_titlebar($c, 'sharing', $sst_base, (bool) $c['can_edit'], false); ?>
<div class="ssa-msg" id="ssaMsg" role="status" hidden></div>
<div class="ssa-one"><section class="sst-card" id="sstShare"></section></div>
</div>
<script type="application/json" id="ssaCfg"><?php echo json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
<script src="<?php echo sst_e(url('assets/js/selfstudy-access.js')); ?>?v=1" defer></script>
<?php
$content = ob_get_clean();
require $sst_layout;
