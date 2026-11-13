<?php
// public/components/selfstudy/who.php — the "Who sees it" tab: choose students (grouped by their course) and see who will see the course.
// A tutor sees and can pick only their own students; admin can pick anyone. The save call (access_api.php) checks this again on the server.
require __DIR__ . '/boot.php';
require_once __DIR__ . '/ui.php';

$id = (int) ($_GET['id'] ?? 0);
if (!$sst_ready || !ssc_installed($db) || !ssa_installed($db)) sst_deny(503, 'Self-study needs migration 109 first.', $sst_base);
$c = sst_course($db, $viewer, $id);
if (!$c) sst_deny(404, 'That course was not found, or it was not shared with you.', $sst_base);
$caps = ssa_caps($c, $viewer);
if (!$caps['pick']) sst_deny(403, 'You cannot choose students for this course.', $sst_base);
$picker = ssa_picker($db, $viewer, $id);
$readyOther = !array_filter(sst_checklist($db, $id), fn($i) => !$i['done'] && $i['key'] !== 'who');
$page_title = 'Who sees it: ' . $c['title'];
$extra_css = '<link rel="stylesheet" href="' . sst_e(url('assets/css/selfstudy-tutor.css')) . '?v=3">';
$cfg = ['csrf' => csrf_token(), 'endpoint' => $sst_base . 'access_api.php', 'course' => $id, 'status' => $c['status'], 'canPublish' => (bool) $c['can_edit'], 'readyOther' => $readyOther, 'isAdmin' => $viewer['role'] === 'admin',
        'groups' => $picker['groups'], 'audience' => $picker['audience'], 'caps' => $caps, 'tutors' => ssa_tutors($db, $viewer, $id), 'contentUrl' => $sst_base . 'content.php?id=' . $id];
ob_start();
?>
<div class="sst-page sst-ed sst-who">
<?php sst_titlebar($c, 'who', $sst_base, (bool) $c['can_edit'], false, $readyOther, 'who'); ?>
<div class="ssa-msg" id="ssaMsg" role="status" hidden></div>
<div class="ssa-grid">
    <section class="sst-card ssa-pick" aria-label="Choose your students">
        <div class="ssa-head">
            <h3><?php echo $viewer['role'] === 'admin' ? 'Choose students' : 'Choose your students'; ?></h3>
            <div class="ssa-switch" role="group" aria-label="How to choose"><button type="button" class="on" data-mode="student" aria-pressed="true">By student</button><button type="button" data-mode="course" aria-pressed="false">By whole course</button></div>
        </div>
        <label class="ssa-search"><i class="fas fa-search"></i><input type="search" id="ssaQ" placeholder="Search by name or number" aria-label="Search by name or number" autocomplete="off"></label>
        <p class="sst-hint"><?php echo $viewer['role'] === 'admin' ? 'As admin you can pick any student.' : 'You can only pick your own students.'; ?> You can mix students from different courses.</p>
        <div id="ssaGroups"></div>
        <div class="ssa-bottom"><button type="button" class="sst-btn" id="ssaAll">Select all <?php echo $viewer['role'] === 'admin' ? 'students' : 'my students'; ?></button><button type="button" class="sst-btn" id="ssaClear">Clear</button></div>
    </section>
    <div class="ssa-side">
        <section class="sst-card ssa-who" aria-live="polite"><h4>Who will see it</h4><div id="ssaWho"></div></section>
        <section class="sst-card" id="sstShare"></section>
    </div>
</div>
</div>
<script type="application/json" id="ssaCfg"><?php echo json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
<script src="<?php echo sst_e(url('assets/js/selfstudy-access.js')); ?>?v=1" defer></script>
<?php
$content = ob_get_clean();
require $sst_layout;
