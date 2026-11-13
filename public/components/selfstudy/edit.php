<?php
// public/components/selfstudy/edit.php — create / edit a self-study course: the Details tab (image, title, description, level, exam time limits),
// the live "how it will look" card and the "Before you publish" checklist. Saving goes through action.php; every permission is checked there too.
require __DIR__ . '/boot.php';
require_once __DIR__ . '/ui.php';

$id = (int) ($_GET['id'] ?? 0);
$c = null;
if (!$sst_ready) sst_deny(503, 'Self-study needs migration 107 first.', $sst_base);
if ($id) {
    $c = sst_course($db, $viewer, $id);
    if (!$c) sst_deny(404, 'That course was not found, or it was not shared with you.', $sst_base);       // no access looks the same as "does not exist"
} elseif ($viewer['role'] !== 'tutor') {
    sst_deny(403, 'Only a tutor can create a self-study course.', $sst_base);
}
$canEdit = $c ? $c['can_edit'] : true;
$isAdmin = $viewer['role'] === 'admin';
$check = $c ? sst_checklist($db, $id) : [
    ['key' => 'image', 'label' => 'Course image', 'done' => false], ['key' => 'text', 'label' => 'Title and description', 'done' => false], ['key' => 'level', 'label' => 'Level chosen', 'done' => true],
    ['key' => 'content', 'label' => 'At least 1 module with a topic', 'done' => false], ['key' => 'quizzes', 'label' => 'Enough questions for every module quiz', 'done' => false], ['key' => 'exams', 'label' => 'Checkpoint and Final exam questions', 'done' => false], ['key' => 'who', 'label' => 'Choose who sees it', 'done' => false]];
$ready = !array_filter($check, fn($i) => !$i['done']);
$hasProgress = $c ? sst_has_progress($db, $id) : false;
$tutor = $c ? (ss2_rows($db, "SELECT COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, ''))), ''), u.username) AS n FROM tutors t JOIN users u ON u.id = t.user_id LEFT JOIN user_profiles up ON up.user_id = u.id WHERE t.id = ?", [(int) $c['owner_tutor_id']])[0]['n'] ?? 'Your tutor') : $viewer['name'];
$d = [
    'title' => $c['title'] ?? '', 'short_description' => $c['short_description'] ?? '', 'level' => $c['level'] ?? 'beginner',
    'checkpoint_minutes' => (int) ($c['checkpoint_minutes'] ?? 45), 'final_minutes' => (int) ($c['final_minutes'] ?? 60),
];
if (!empty($_SESSION['sst_form']) && (int) ($_SESSION['sst_form']['id'] ?? 0) === $id) { $d = array_merge($d, $_SESSION['sst_form']['data']); unset($_SESSION['sst_form']); }   // what was typed before a refused save
$hasCover = $c && !empty($c['cover_original']);
$mods = $c ? (int) ss2_rows($db, 'SELECT COUNT(*) AS n FROM self_study_modules WHERE course_id = ?', [$id])[0]['n'] : 0;
$crop = $hasCover && $c['crop_w'] !== null ? ['x' => (int) $c['crop_x'], 'y' => (int) $c['crop_y'], 'w' => (int) $c['crop_w']] : null;
$cropMsg = $hasCover && $c['crop_w'] !== null ? sst_cover_msg((int) $c['crop_w'], (int) $c['crop_h']) : '';
$page_title = $c ? 'Edit: ' . $c['title'] : 'New self-study course';
$extra_css = '<link rel="stylesheet" href="' . sst_e(url('assets/css/selfstudy-tutor.css')) . '?v=1">';
$cfg = ['hasCover' => $hasCover, 'src' => $hasCover ? url($c['cover_original']) : '', 'ow' => (int) ($c['cover_orig_w'] ?? 0), 'oh' => (int) ($c['cover_orig_h'] ?? 0), 'crop' => $crop,
        'min' => SST_COVER_MIN_WIDTH, 'maxBytes' => SST_COVER_MAX_BYTES, 'readonly' => !$canEdit, 'mods' => $mods, 'tutor' => $tutor, 'levels' => array_map(fn($k) => $k, SS_LEVELS)];
$act = fn(string $do) => '<form method="post" action="' . sst_e($sst_base . 'action.php') . '" id="f_' . $do . '">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="do" value="' . $do . '"></form>';
ob_start();
?>
<div class="sst-page">
<?php sst_titlebar($c, 'details', $sst_base, $canEdit, true, $ready); ?>
<?php echo sst_bank_warnings($db, $id, $sst_base); ?>
<?php if ($c && !$canEdit): ?><div class="sst-note"><i class="fas fa-lock"></i> <?php echo $isAdmin ? 'You are looking at this course as admin. Only the owner and tutors with "Can edit" can change it.' : 'You can open this course but not change it ("Can view").'; ?></div><?php endif; ?>
<form method="post" action="<?php echo sst_e($sst_base . 'action.php'); ?>" enctype="multipart/form-data" id="sstForm" novalidate>
<?php echo csrf_field(); ?>
<input type="hidden" name="id" value="<?php echo $id; ?>">
<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo SST_COVER_MAX_BYTES; ?>">
<input type="hidden" name="crop_x" id="cropX"><input type="hidden" name="crop_y" id="cropY"><input type="hidden" name="crop_w" id="cropW">
<fieldset class="sst-cols" <?php echo $canEdit ? '' : 'disabled'; ?>>
<div class="sst-left">
    <section class="sst-card">
        <h4>Course image</h4>
        <div class="sst-img">
            <div class="sst-crop" id="sstCrop">
                <div class="sst-stage" id="sstStage" <?php echo $hasCover ? '' : 'hidden'; ?>><img id="sstImg" alt="Course image with the crop frame" <?php echo $hasCover ? 'src="' . sst_e(url($c['cover_original'])) . '"' : ''; ?>><div class="sst-frame" id="sstFrame" tabindex="0" aria-label="Crop frame: drag to move, drag the corner to resize"><i class="sst-handle" id="sstHandle"></i></div></div>
                <div class="sst-drop" id="sstDrop" <?php echo $hasCover ? 'hidden' : ''; ?>><i class="fas fa-image"></i><span>Choose a picture</span></div>
            </div>
            <div class="sst-imgside">
                <div class="sst-imgbtns">
                    <label class="sst-btn" for="sstFile"><i class="fas fa-upload"></i> <?php echo $hasCover ? 'Replace image' : 'Choose image'; ?></label>
                    <button type="button" class="sst-btn" id="sstReset"<?php echo $hasCover ? '' : ' disabled'; ?>>Reset crop</button>
                    <input type="file" name="cover" id="sstFile" accept="image/jpeg,image/png,image/webp" hidden>
                </div>
                <p class="sst-hint">Best size: 1600 × 900 pixels or bigger (smallest allowed 1200 pixels wide).<br>JPG, PNG or WebP, up to 10 MB.<br>The image is fitted to the card automatically, and you can move and resize the crop frame.<br>It is saved in sharp sizes for the course card, the study page and phones, so it stays clear.</p>
                <span class="sst-ok" id="sstCropMsg"<?php echo $cropMsg ? '' : ' hidden'; ?>><i class="fas fa-check"></i> <span id="sstCropTxt"><?php echo sst_e($cropMsg); ?></span></span>
                <div class="sst-err" id="sstImgErr" role="alert"></div>
            </div>
        </div>
    </section>

    <section class="sst-card">
        <h4>Course details</h4>
        <label class="sst-l" for="sstTitle">Course title</label>
        <input class="sst-in" type="text" id="sstTitle" name="title" maxlength="200" value="<?php echo sst_e($d['title']); ?>" placeholder="For example: Python for Data Analysis" autocomplete="off">
        <div class="sst-l two"><label for="sstDesc">Short description (shown on the card)</label><span id="sstCount"><?php echo mb_strlen($d['short_description']); ?> / 160</span></div>
        <input class="sst-in" type="text" id="sstDesc" name="short_description" maxlength="160" value="<?php echo sst_e($d['short_description']); ?>" placeholder="One or two lines that tell a student what they will learn" autocomplete="off">
        <div class="sst-l">Level</div>
        <div class="sst-levels" role="radiogroup" aria-label="Level">
            <?php foreach (SS_LEVELS as $k => $label): ?><label class="sst-lv"><input type="radio" name="level" value="<?php echo sst_e($k); ?>"<?php echo $d['level'] === $k ? ' checked' : ''; ?>><span><?php echo sst_e($label); ?></span></label><?php endforeach; ?>
        </div>
    </section>

    <section class="sst-card">
        <h4>Exam time limits (you choose for this course)</h4>
        <div class="sst-two">
            <div><label class="sst-l" for="sstCp">Checkpoint Exam</label><select class="sst-in" id="sstCp" name="checkpoint_minutes"><?php foreach (SST_MINUTES as $m) echo '<option value="' . $m . '"' . ($d['checkpoint_minutes'] === $m ? ' selected' : '') . '>' . $m . ' minutes</option>'; ?></select></div>
            <div><label class="sst-l" for="sstFin">Final Exam</label><select class="sst-in" id="sstFin" name="final_minutes"><?php foreach (SST_MINUTES as $m) echo '<option value="' . $m . '"' . ($d['final_minutes'] === $m ? ' selected' : '') . '>' . $m . ' minutes</option>'; ?></select></div>
        </div>
        <p class="sst-hint">Fixed rules for every course: module quizzes pass at 50% (3 tries a week). Exams pass at 70% (2 tries a day, 10 a month). A tutor can reset a student twice a month for quizzes and twice a month for exams.</p>
    </section>
</div>

<div class="sst-right">
    <section class="sst-card">
        <h4>How it will look to students</h4>
        <div class="sst-pcard">
            <div class="sst-pcv"><canvas id="sstPrev" width="800" height="450" <?php echo $hasCover ? '' : 'hidden'; ?>></canvas>
                <?php if (!$hasCover): ?><div class="sst-nocover" id="sstPrevNone"><i class="fas fa-image"></i></div><?php endif; ?>
                <span class="sst-lvltag" id="pLevel"><?php echo sst_e(SS_LEVELS[$d['level']]); ?></span></div>
            <div class="sst-pb">
                <div class="sst-meta"><span><i class="fas fa-user"></i> <?php echo sst_e($tutor); ?></span><span>Self-paced · <?php echo $mods; ?> module<?php echo $mods === 1 ? '' : 's'; ?></span></div>
                <h3 id="pTitle"><?php echo sst_e($d['title'] !== '' ? $d['title'] : 'Your course title'); ?></h3>
                <p id="pDesc"><?php echo sst_e($d['short_description'] !== '' ? $d['short_description'] : 'Your short description appears here.'); ?></p>
                <div class="sst-prog"><span>Progress</span><b>0%</b></div><div class="sst-pbar"><i></i></div>
                <div class="sst-pf"><span>Not started</span><span class="sst-start">Start</span></div>
            </div>
        </div>
    </section>

    <section class="sst-card">
        <h4>Before you publish</h4>
        <ul class="sst-check" id="sstCheck">
            <?php foreach ($check as $i): ?><li data-k="<?php echo sst_e($i['key']); ?>" class="<?php echo $i['done'] ? 'done' : 'todo'; ?>"><span class="bx"><i class="fas fa-check"></i></span><span><?php echo sst_e($i['label']); ?><small class="sr-only"><?php echo $i['done'] ? ' (done)' : ' (not done yet)'; ?></small></span></li><?php endforeach; ?>
        </ul>
        <p class="sst-hint" id="sstPubHint"><?php echo $ready ? 'Everything is ready. Press Publish.' : 'Publish switches on when every item above is ticked. The server checks them again when you press it.'; ?></p>
    </section>
</div>
</fieldset>
</form>

<?php if ($c): ?>
<section class="sst-card sst-more">
    <h4>More</h4>
    <div class="sst-moreb">
        <?php if ($c['can_edit'] && $viewer['role'] === 'tutor'): ?>
            <button class="sst-btn" type="submit" form="f_duplicate"><i class="fas fa-copy"></i> Duplicate as a new draft</button>
            <?php if ($c['status'] === 'archived'): ?><button class="sst-btn" type="submit" form="f_unarchive"><i class="fas fa-box-open"></i> Restore from archive</button>
            <?php else: ?><button class="sst-btn" type="submit" form="f_archive" data-confirm="Archive this course? Students will no longer see it, but all their progress is kept."><i class="fas fa-box-archive"></i> Archive</button><?php endif; ?>
            <?php if ($c['status'] === 'draft' && !$hasProgress): ?><button class="sst-btn sst-danger" type="submit" form="f_delete" data-confirm="Delete this draft for good? Its modules, topics and questions will be removed. This cannot be undone."><i class="fas fa-trash"></i> Delete draft</button><?php endif; ?>
        <?php endif; ?>
        <?php if ($isAdmin && $c['status'] === 'published'): ?><button class="sst-btn" type="submit" form="f_unpublish" data-confirm="Take this course back to a draft? Students will stop seeing it; their progress is kept."><i class="fas fa-eye-slash"></i> Unpublish</button><?php endif; ?>
    </div>
    <?php if ($hasProgress && $c['status'] === 'draft'): ?><p class="sst-hint">Students already have progress in this course, so it cannot be deleted. Archive it instead.</p><?php endif; ?>
    <?php if ($isAdmin): ?>
        <form method="post" action="<?php echo sst_e($sst_base . 'action.php'); ?>" class="sst-transfer"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="do" value="transfer">
            <label class="sst-l" for="sstOwner">Move ownership to another tutor (for example when the creator leaves)</label>
            <div class="sst-trow"><select class="sst-in" id="sstOwner" name="tutor_id"><?php foreach (sst_active_tutors($db) as $t) echo '<option value="' . (int) $t['id'] . '"' . ((int) $t['id'] === (int) $c['owner_tutor_id'] ? ' selected' : '') . '>' . sst_e($t['name']) . '</option>'; ?></select>
            <button class="sst-btn" type="submit" data-confirm="Move this course to the chosen tutor? The current owner will lose access unless it is shared with them again.">Move course</button></div>
        </form>
    <?php endif; ?>
</section>
<?php foreach (['duplicate', 'archive', 'unarchive', 'delete', 'unpublish'] as $do) echo $act($do); ?>
<?php endif; ?>
</div>
<script type="application/json" id="sstCfg"><?php echo json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES); ?></script>
<script src="<?php echo sst_e(url('assets/js/selfstudy-tutor.js')); ?>?v=1" defer></script>
<?php
$content = ob_get_clean();
require $sst_layout;
