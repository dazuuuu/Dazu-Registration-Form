<?php
// public/components/progress/student.php — one student's full progress card, plus "Send a nudge" and "Add my note".
require __DIR__ . '/boot.php';

$uid = (int) ($_GET['u'] ?? $_POST['u'] ?? 0);
// The student must be on this viewer's list (a tutor: their own students only). Checked here on the server for every request.
$students = $uid > 0 ? sp_students($db, $viewer, ['ids' => [$uid]]) : [];
if (!$students) {
    sp_deny(404, 'That student is not on your list.', $sp_base . 'index.php');
}
$self = $sp_base . 'student.php?u=' . $uid;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        sp_flash('error', 'Security check failed. Please refresh and try again.');
    } elseif (($_POST['action'] ?? '') === 'nudge') {
        $r = sp_nudge($db, $viewer, $uid);
        sp_flash($r['ok'] ? 'success' : 'error', $r['message']);
    } elseif (($_POST['action'] ?? '') === 'note') {
        $r = sp_add_note($db, $viewer, $uid, (string) ($_POST['note'] ?? ''));
        sp_flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Note saved.' : $r['error']);
    }
    header('Location: ' . $self);
    exit;
}

$c = sp_compute($db, $students, true)[$uid];
$notes = sp_notes($db, $viewer, $uid);
$sl = $c['subject_lines'];
$pr = $c['projects'];
$k = $c['status']['key'];
$first = trim(explode(' ', trim($c['full_name']))[0]);
$gap = $c['should_be'] === null ? null : (int) round($c['actual_pct'] - $c['should_be']);
$gapText = $gap === null ? '' : ($gap > 2 ? 'Ahead by ' . $gap . ' points.' : ($gap < -2 ? 'Behind by ' . abs($gap) . ' points.' : 'Right on track.'));
$modeText = ['online' => 'Online classes', 'physical' => 'Physical classes'][$c['study_mode'] ?? ''] ?? '';

$page_title = 'Progress: ' . $c['full_name'];
$breadcrumbs = [['label' => 'Student Progress', 'url' => $sp_base . 'index.php'], ['label' => $c['full_name'], 'active' => true]];
ob_start();
echo sp_css();
?>
<div class="sp sp-<?php echo sp_e($k); ?>">
<a href="<?php echo sp_e($sp_base . 'index.php'); ?>" class="btn btn-link btn-sm ps-0 mb-2">&larr; All students</a>
<div class="sp-stack">

<div class="sp-card">
    <div class="sp-head">
        <?php echo sp_avatar($c, true); ?>
        <div class="flex-grow-1">
            <div class="d-flex flex-wrap gap-3 align-items-center"><h3><?php echo sp_e($c['full_name']); ?></h3><?php echo sp_pill($c); ?></div>
            <div class="meta">Student No <?php echo sp_e($c['registration_no']); ?> · <?php echo sp_e($c['course_title']); ?><?php echo $modeText ? ' · ' . sp_e($modeText) : ''; ?><br>
                <?php echo $c['start_date'] ? 'Started ' . sp_e(date('j M Y', strtotime($c['start_date']))) : ''; ?><?php echo $c['finish_date'] ? ' · Finishes ' . sp_e(date('j M Y', strtotime($c['finish_date']))) : ''; ?> · Last hand-in <?php echo $c['last_handin'] ? sp_e(sp_ago($c['days_since'])) : 'none yet'; ?>
                <?php if ($viewer['role'] === 'admin' && $c['tutors']): ?><br>Tutor: <?php echo sp_e(implode(', ', $c['tutors'])); ?><?php endif; ?></div>
        </div>
        <form method="post" action="<?php echo sp_e($sp_base . 'download.php'); ?>">
            <?php echo csrf_field(); ?><input type="hidden" name="mode" value="one"><input type="hidden" name="ids[]" value="<?php echo $uid; ?>">
            <button class="btn btn-line"><i class="fas fa-file-pdf me-1"></i>Download card</button>
        </form>
    </div>
    <div class="sp-lbl mt-4"><b style="font-size:16px">Course progress</b><b style="font-size:24px"><?php echo (int) round($c['actual_pct']); ?>%</b></div>
    <?php echo sp_bar($c); ?>
    <div class="d-flex justify-content-between small text-muted mt-2"><span>Start</span><span><b style="border-left:2px solid #1f2326;padding-left:6px;font-weight:400"><?php echo sp_e(sp_should_text($c)); ?>.<?php echo $gapText ? ' ' . sp_e($gapText) : ''; ?></b></span><span>Finish</span></div>
</div>

<div class="sp-four">
    <div><small>Average so far</small><b><?php echo $c['average'] === null ? '<span style="font-size:22px">no mark yet</span>' : sp_e(sp_pct($c['average'])); ?></b><span class="sub">From released marks only</span></div>
    <div><small>Assignments handed in</small><b><?php echo (int) $c['handed']; ?> of <?php echo (int) $c['total']; ?></b><span class="sub"><?php echo (int) $c['missing']; ?> missing<?php echo $c['late'] ? ' · ' . (int) $c['late'] . ' handed in late' : ''; ?><?php echo $c['not_due'] ? ' · ' . (int) $c['not_due'] . ' not yet due' : ''; ?></span></div>
    <div><small>Monthly projects average</small><b><?php echo $c['project_average'] === null ? '<span style="font-size:22px">no mark yet</span>' : sp_e(sp_pct($c['project_average'])); ?></b><span class="sub"><?php echo (int) $c['monthly_marked']; ?> marked of <?php echo (int) $c['monthly_total']; ?></span></div>
    <div><small>Last hand-in date</small><b class="sm"><?php echo sp_e(sp_last_text($c)); ?></b><span class="sub"><?php echo $c['last_handin'] ? sp_e(sp_ago($c['days_since'])) : 'Nothing handed in yet'; ?></span></div>
</div>

<?php
// Self-study (read-only): hidden when the student has no self-study course. Never changes the status word above.
require_once __DIR__ . '/selfstudy_section.php';
echo sp_selfstudy_html(ssd_cards($db, $viewer, [$uid])[$uid] ?? [], $uid, $sp_portal);
?>

<div class="sp-cols">
  <div class="sp-stack">
    <div class="sp-card">
        <div class="sp-sec">Subjects <small>Made by the system from released marks. Missing work is not counted as zero.</small></div>
        <?php if ($sl['lines']): ?>
        <div class="table-responsive"><table class="sp-tbl">
            <thead><tr><th>Subject</th><th>Assignments</th><th class="hide-sm">Monthly project</th><th>Average</th><th class="hide-sm">Result</th></tr></thead>
            <tbody>
            <?php foreach ($sl['lines'] as $l): $pct = $l['total'] ? round($l['handed'] / $l['total'] * 100) : 0; $wc = strtolower((string) ($l['word'] ?? '')); ?>
                <tr>
                    <td><b><?php echo sp_e($l['name']); ?></b></td>
                    <td class="barcell"><small><b><?php echo (int) $l['handed']; ?> of <?php echo (int) $l['total']; ?></b> handed in</small><div class="sp-bar thin"><i style="width:<?php echo $pct; ?>%"></i></div></td>
                    <td class="hide-sm"><?php echo sp_e($l['project']); ?></td>
                    <td><b><?php echo sp_e($l['text']); ?></b></td>
                    <td class="hide-sm"><?php echo !empty($l['word']) ? '<span class="sp-word ' . sp_e($wc) . '">' . sp_e($l['word']) . '</span>' : ''; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
        <?php if (!$sl['lines'] && !$sl['not_started']): ?><div class="sp-fold">No subjects are set up for this course yet.</div><?php endif; ?>
        <?php if ($sl['not_started']): ?><div class="sp-fold"><?php echo (int) $sl['not_started']; ?> more subject<?php echo $sl['not_started'] === 1 ? '' : 's'; ?> not started yet</div><?php endif; ?>
    </div>

    <div class="sp-card">
        <div class="sp-sec">Monthly projects <?php if ($pr['items']): ?><span class="sp-word">Average <?php echo $pr['average'] === null ? 'no mark yet' : sp_e(sp_pct($pr['average'])); ?></span><?php endif; ?></div>
        <?php if ($pr['items']): ?>
        <div class="sp-proj sp-list">
            <?php foreach ($pr['items'] as $p): ?>
                <div class="r"><div><b><?php echo sp_e($p['title']); ?></b><div class="sub"><?php echo sp_e($p['label']); ?></div></div>
                    <?php echo $p['state'] === 'marked' ? '<b>' . sp_e($p['text']) . '</b>' : '<span class="sp-chip ' . ($p['state'] === 'missing' ? 'missing' : '') . '">' . sp_e($p['text']) . '</span>'; ?></div>
            <?php endforeach; ?>
        </div>
        <?php else: ?><div class="sp-fold">No monthly projects given yet.</div><?php endif; ?>
    </div>
  </div>

  <div class="sp-stack">
    <div class="sp-card">
        <div class="sp-sec">For you, the tutor <?php echo sp_pill($c); ?></div>
        <div class="sp-tutor">
            <b><?php echo sp_e($c['full_name']); ?> is <?php echo sp_e(strtolower($c['status']['word'])); ?>.</b>
            <?php if ($c['status']['reasons']): ?><ul><?php foreach ($c['status']['reasons'] as $r): ?><li class="<?php echo $k === 'good' ? '' : ($k === 'bad' ? 'bad' : 'warn'); ?>"><?php echo sp_e($r); ?></li><?php endforeach; ?></ul><?php endif; ?>
            <div class="todo"><b>What to do:</b> <?php echo sp_e($c['status']['action']); ?></div>
        </div>
        <div class="sp-actions">
            <?php if ($viewer['role'] === 'tutor'): ?>
            <form method="post" onsubmit="return confirm('Send a nudge to <?php echo sp_e(addslashes($c['full_name'])); ?> now?');">
                <?php echo csrf_field(); ?><input type="hidden" name="u" value="<?php echo $uid; ?>"><input type="hidden" name="action" value="nudge">
                <button class="btn btn-g"><i class="fas fa-paper-plane me-1"></i>Send a nudge</button>
            </form>
            <?php endif; ?>
            <button class="btn btn-line" type="button" onclick="document.getElementById('spNote').classList.toggle('d-none');document.getElementById('spNoteText').focus();"><i class="fas fa-comment me-1"></i>Add my note</button>
        </div>
        <form method="post" id="spNote" class="d-none mt-3">
            <?php echo csrf_field(); ?><input type="hidden" name="u" value="<?php echo $uid; ?>"><input type="hidden" name="action" value="note">
            <textarea name="note" id="spNoteText" class="form-control" rows="3" maxlength="2000" placeholder="Write a note about <?php echo sp_e($first); ?>. Only you and admin can read it." required></textarea>
            <button class="btn btn-g mt-2">Save note</button>
        </form>
        <div class="sp-notes">
            <div class="sp-lbl2"><?php echo $viewer['role'] === 'admin' ? 'Tutor notes (admin can see all)' : 'Your notes (only you and admin see these)'; ?></div>
            <?php foreach ($notes as $n): ?>
                <div class="n"><small><?php echo sp_e(sp_local($n['created_at'], 'j M')); ?> · <?php echo sp_e($n['author_name']); ?></small><?php echo sp_e($n['note']); ?></div>
            <?php endforeach; ?>
            <?php if (!$notes): ?><div class="text-muted small mt-2">No notes yet. They are never on the downloaded card.</div><?php endif; ?>
        </div>
    </div>

    <div class="sp-card">
        <div class="sp-sec">Assignments <small><?php echo (int) $c['handed']; ?> of <?php echo (int) $c['total']; ?> handed in</small></div>
        <div class="sp-list">
            <?php foreach ($c['recent'] as $r): ?>
                <div class="r"><div><b><?php echo sp_e($r['title']); ?></b><div class="sub"><?php echo sp_e($r['note']); ?></div></div>
                    <span class="sp-chip <?php echo sp_e($r['state']); ?>"><?php echo sp_e($r['text']); ?></span></div>
            <?php endforeach; ?>
            <?php if (!$c['recent']): ?><div class="sp-fold">No assignments given yet.</div><?php endif; ?>
        </div>
    </div>
  </div>
</div>

</div>
</div>
<?php
$content = ob_get_clean();
require $sp_layout;
