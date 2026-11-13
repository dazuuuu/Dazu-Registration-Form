<?php
// public/components/selfstudy/coming.php — the Content, Questions, Who sees it and Sharing tabs open here until their steps are built.
require __DIR__ . '/boot.php';
require_once __DIR__ . '/ui.php';

$id = (int) ($_GET['id'] ?? 0);
$c = $sst_ready ? sst_course($db, $viewer, $id) : null;
if (!$c) sst_deny(404, 'That course was not found, or it was not shared with you.', $sst_base);
$tab = in_array($_GET['tab'] ?? '', ['content', 'questions', 'who', 'sharing'], true) ? $_GET['tab'] : 'content';
$names = ['content' => 'Content', 'questions' => 'Questions', 'who' => 'Who sees it', 'sharing' => 'Sharing'];
$blurb = [
    'content' => 'Here you will add modules and topics: text, pictures, code, tables and a YouTube video.',
    'questions' => 'Here you will write the topic questions, the module quizzes and the Checkpoint and Final exam questions.',
    'who' => 'Here you will choose which students can see this course.',
    'sharing' => 'Here you will share the course with other tutors as "Can view" or "Can edit".',
];
$page_title = $names[$tab] . ': ' . $c['title'];
$extra_css = '<link rel="stylesheet" href="' . sst_e(url('assets/css/selfstudy-tutor.css')) . '?v=1">';
ob_start();
?>
<div class="sst-page">
<?php sst_titlebar($c, $tab, $sst_base, $c['can_edit'], false); ?>
<section class="sst-card sst-soon"><i class="fas fa-hourglass-half"></i><h3><?php echo sst_e($names[$tab]); ?> opens soon</h3><p><?php echo sst_e($blurb[$tab]); ?></p>
    <a class="sst-btn" href="<?php echo sst_e($sst_base . 'edit.php?id=' . $id); ?>">&larr; Back to Details</a></section>
</div>
<?php
$content = ob_get_clean();
require $sst_layout;
