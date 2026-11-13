<?php
// public/components/selfstudy/questions.php — the Questions tab: every question bank of the course (topics, Knowledge Check, module quizzes, Checkpoint and Final Exam)
// and the "Answers to mark" list for long answers.
require __DIR__ . '/boot.php';
require_once __DIR__ . '/ui.php';

$id = (int) ($_GET['id'] ?? 0);
if (!$sst_ready || !ssc_installed($db)) sst_deny(503, 'Self-study needs migration 108 first.', $sst_base);
$c = sst_course($db, $viewer, $id);
if (!$c) sst_deny(404, 'That course was not found, or it was not shared with you.', $sst_base);
$canEdit = $c['can_edit'];
$ready = !array_filter(sst_checklist($db, $id), fn($i) => !$i['done']);
$page_title = 'Questions: ' . $c['title'];
$extra_css = '<link rel="stylesheet" href="' . sst_e(url('assets/css/selfstudy-tutor.css')) . '?v=2">';
$cfg = ['csrf' => csrf_token(), 'endpoint' => $sst_base . 'api.php', 'course' => $id, 'readonly' => !$canEdit, 'mode' => 'questions', 'contentUrl' => $sst_base . 'content.php?id=' . $id,
        'outline' => ssc_outline($db, $id), 'bank' => (string) ($_GET['bank'] ?? ''), 'topicsPerAttempt' => SS2_TOPIC_QUESTIONS];
ob_start();
?>
<div class="sst-page sst-ed">
<?php sst_titlebar($c, 'questions', $sst_base, $canEdit, false, $ready, 'editor'); ?>
<?php echo sst_bank_warnings($db, $id, $sst_base); ?>
<?php if (!$canEdit): ?><div class="sst-note"><i class="fas fa-lock"></i> <?php echo $viewer['role'] === 'admin' ? 'You are looking at this course as admin. Only the owner and tutors with "Can edit" can change it.' : 'You can open this course but not change it ("Can view").'; ?></div><?php endif; ?>
<?php if ($canEdit): ?><form method="post" action="<?php echo sst_e($sst_base . 'action.php'); ?>" id="f_publish_only"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="do" value="publish_only"><input type="hidden" name="back" value="questions"></form><?php endif; ?>
<section class="sst-card ssc-mark" id="sscMark" hidden>
    <h4>Answers to mark <span class="ssc-badge" id="sscMarkN"></span></h4>
    <p class="sst-hint">Students' long answers. Read the answer and write a short comment: the student sees it under that question. The topic never waits for your marking.</p>
    <div id="sscMarkList"></div>
</section>
<div class="ssc-grid">
    <aside class="sst-card ssc-outline" id="sscOutline" aria-label="Question banks">
        <button type="button" class="ssc-otoggle" id="sscOToggle" aria-expanded="false"><span><i class="fas fa-circle-question"></i> Question banks</span><i class="fas fa-chevron-down"></i></button>
        <div class="ssc-obody" id="sscOBody"><h4>Question banks</h4><div id="sscTree"></div></div>
    </aside>
    <div class="ssc-main"><section class="sst-card" id="sscQBox"><div class="ssc-empty"><i class="fas fa-circle-question"></i><p>Choose a question bank on the left.</p></div></section></div>
</div>
</div>
<script type="application/json" id="sscCfg"><?php echo json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
<script src="<?php echo sst_e(url('assets/js/selfstudy-editor.js')); ?>?v=1" defer></script>
<?php
$content = ob_get_clean();
require $sst_layout;
