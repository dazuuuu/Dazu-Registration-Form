<?php
// Tutor: open submissions, work students handed in without an assignment being set. List (tabs) or one submission (?id=)
// with the link / document, the student's note, and marks + feedback: publish the grade or return it for changes.
require __DIR__ . '/../../components/assignments/tutor_boot.php';
require_once __DIR__ . '/../../../app/v1/helpers/open_submissions.php';
require_once __DIR__ . '/../../../app/v1/services/PortalNotifications.php';

$id = (int) ($_GET['id'] ?? 0);
$sub = $id ? os_get($pdo, $id, $tutorId) : null;
if ($id && !$sub) asg_back('open.php', null, 'Submission not found.');
$error = null;

if ($sub && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $self = 'open.php?id=' . $id;
    asg_csrf_or_back($self);
    $action = ($_POST['action'] ?? '') === 'return' ? 'returned' : 'graded';
    $max = (float) ($_POST['max_score'] ?? 100);
    $scoreRaw = trim((string) ($_POST['score'] ?? ''));
    $feedback = mb_substr(trim(str_replace("\r\n", "\n", (string) ($_POST['feedback'] ?? ''))), 0, 5000);
    if ($max <= 0 || $max > 1000) $error = 'Out of must be between 1 and 1000.';
    elseif ($action === 'graded' && ($scoreRaw === '' || !is_numeric($scoreRaw) || (float) $scoreRaw < 0 || (float) $scoreRaw > $max)) $error = 'Enter marks between 0 and ' . $max . '.';
    elseif ($action === 'returned' && $feedback === '') $error = 'Tell the student what to change before returning the work.';
    else {
        os_grade($pdo, $id, $action, $action === 'graded' ? round((float) $scoreRaw, 2) : null, $max, $feedback, $me);
        $fresh = os_get($pdo, $id);
        PortalNotifications::notify($pdo, (int) $sub['student_user_id'], 'grade',
            $action === 'graded' ? 'Graded: ' . $sub['title'] : 'Changes asked: ' . $sub['title'],
            $action === 'graded' ? 'Result: ' . os_score_text($fresh) : 'Your tutor returned your work. Read the feedback and resubmit.',
            'students/assignments/submit.php' . ($action === 'returned' ? '?edit=' . $id : ''));
        if (isset($_POST['next'])) {
            $n = os_for_tutor($pdo, $tutorId, 'awaiting');
            asg_back($n ? 'open.php?id=' . (int) $n[0]['id'] : 'open.php', $action === 'graded' ? 'Grade published.' : 'Returned for changes.');
        }
        asg_back($self, $action === 'graded' ? 'Grade published. The student has been told.' : 'Returned to the student for changes.');
    }
}

$tab = in_array($_GET['tab'] ?? '', ['awaiting', 'graded', 'returned', 'all'], true) ? $_GET['tab'] : 'awaiting';
$q = trim((string) ($_GET['q'] ?? ''));
$rows = $sub ? [] : os_for_tutor($pdo, $tutorId, $tab, $q);
$awaiting = os_count_awaiting($pdo, $tutorId);
$page_title = $sub ? 'Grade: ' . $sub['full_name'] : 'Open submissions';
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo asg_e($error); ?></div><?php endif; ?>

<?php if (!$sub): ?>
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
    <div class="d-flex flex-wrap gap-2">
        <a href="index.php" class="btn btn-sm btn-outline-secondary">‹ Assignment submissions</a>
        <?php foreach (['awaiting' => 'Awaiting grading', 'graded' => 'Graded', 'returned' => 'Returned', 'all' => 'All'] as $k => $lbl): ?>
            <a href="?tab=<?php echo $k; ?>" class="btn btn-sm <?php echo $tab === $k ? 'btn-brand' : 'btn-outline-secondary'; ?>"><?php echo $lbl; ?><?php if ($k === 'awaiting' && $awaiting): ?> <span class="badge bg-light text-dark ms-1"><?php echo $awaiting; ?></span><?php endif; ?></a>
        <?php endforeach; ?>
    </div>
    <form method="GET" class="d-flex gap-2"><input type="hidden" name="tab" value="<?php echo asg_e($tab); ?>">
        <input type="search" name="q" class="form-control form-control-sm" placeholder="Search student or title" value="<?php echo asg_e($q); ?>"></form>
</div>
<div class="card-tutor">
    <?php if (!$rows): ?><div class="as-empty"><i class="fas fa-inbox"></i><?php echo $tab === 'awaiting' ? 'No open submissions awaiting grading.' : 'Nothing here.'; ?></div><?php else: ?>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Student</th><th>Title</th><th>Type</th><th>Submitted</th><th>Status</th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr>
                <td class="fw-semibold"><?php echo asg_e($r['full_name']); ?><div class="small text-muted fw-normal"><?php echo asg_e($r['course_title'] ?: ''); ?></div></td>
                <td><?php echo asg_e($r['title']); ?></td>
                <td class="small"><?php echo asg_e(os_kind_label($r['kind'])); ?></td>
                <td class="small"><?php echo asg_e(date('D j M, H:i', strtotime($r['submitted_at']))); ?></td>
                <td><?php echo $r['status'] === 'graded' ? '<span class="asg-chip st-graded">' . asg_e(os_score_text($r)) . '</span>'
                    : ($r['status'] === 'returned' ? '<span class="asg-chip st-returned">Returned</span>' : '<span class="asg-chip st-submitted">Awaiting</span>'); ?></td>
                <td class="text-end"><a href="?id=<?php echo (int) $r['id']; ?>" class="btn btn-sm <?php echo $r['status'] === 'submitted' ? 'btn-brand' : 'btn-outline-secondary'; ?>"><?php echo $r['status'] === 'submitted' ? 'Grade' : 'Open'; ?></a></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</div>
<p class="small text-muted fst-italic mt-2">Work students sent without an assignment being set. Oldest first.</p>

<?php else: $meta = json_decode((string) $sub['link_meta'], true) ?: []; ?>
<a href="open.php" class="btn btn-link btn-sm ps-0 mb-2">‹ Open submissions</a>
<div class="row g-3">
<div class="col-lg-7">
    <div class="card-tutor p-3 p-md-4">
        <h5 class="mb-1"><?php echo asg_e($sub['title']); ?></h5>
        <div class="small text-muted mb-3"><?php echo asg_e($sub['full_name']); ?><?php echo $sub['registration_no'] ? ' (' . asg_e($sub['registration_no']) . ')' : ''; ?>
            · <?php echo asg_e($sub['course_title'] ?: 'No course'); ?> · Submitted <?php echo asg_e(date('D j M Y, H:i', strtotime($sub['submitted_at']))); ?></div>

        <div class="asg-section"><?php echo asg_e(os_kind_label($sub['kind'])); ?></div>
        <?php if ($sub['kind'] === 'file'): ?>
            <a class="asg-pill" href="<?php echo asg_e(asg_files_url('open', (int) $sub['id'], true)); ?>"><i class="fas fa-download"></i><?php echo asg_e($sub['original_name']); ?>
                <span class="text-muted">(<?php echo asg_human_bytes((int) $sub['bytes']); ?>)</span></a>
            <?php if ($sub['mime'] === 'application/pdf'): ?><a class="asg-pill" target="_blank" href="<?php echo asg_e(asg_files_url('open', (int) $sub['id'])); ?>"><i class="fas fa-eye"></i>View</a><?php endif; ?>
        <?php else: ?>
            <div class="asg-box">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div style="min-width:0">
                        <div class="fw-semibold"><i class="<?php echo $sub['kind'] === 'github' ? 'fab fa-github' : 'fab fa-google-drive'; ?> me-1"></i><?php echo asg_e($meta['name'] ?? ''); ?></div>
                        <?php if (!empty($meta['description'])): ?><div class="small text-muted"><?php echo asg_e($meta['description']); ?></div><?php endif; ?>
                        <div class="small text-muted text-break"><?php echo asg_e($sub['url']); ?></div>
                        <?php $bits = array_filter([$meta['language'] ?? '', ($meta['type'] ?? '') === 'repo' ? '★ ' . (int) ($meta['stars'] ?? 0) : '',
                            !empty($meta['pushed_at']) ? 'last push ' . date('j M Y', strtotime($meta['pushed_at'])) : '']); if ($bits): ?>
                            <div class="small text-muted"><?php echo asg_e(implode(' · ', $bits)); ?></div><?php endif; ?>
                        <div class="small <?php echo !empty($meta['verified']) ? 'text-success' : 'text-warning'; ?>"><i class="fas <?php echo !empty($meta['verified']) ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?> me-1"></i><?php echo !empty($meta['verified']) ? 'Checked: the link worked and was open when submitted.' : 'Could not be confirmed when submitted.'; ?></div>
                    </div>
                    <?php echo asg_link($sub['url'], 'Open', 'btn btn-sm btn-outline-dark'); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($sub['note']): ?><div class="asg-section">Note from the student</div><div class="asg-box" style="white-space:pre-wrap"><?php echo asg_e($sub['note']); ?></div><?php endif; ?>
    </div>
</div>
<div class="col-lg-5">
    <form method="POST" class="asg-panel"><?php echo csrf_field(); ?>
        <h6 class="fw-bold">Marks</h6>
        <div class="d-flex align-items-center gap-2 mb-3">
            <input type="number" step="0.5" min="0" name="score" class="form-control form-control-lg text-center fw-bold" style="max-width:110px"
                   value="<?php echo asg_e($_POST['score'] ?? ($sub['score'] !== null ? (float) $sub['score'] : '')); ?>">
            <span class="text-muted">out of</span>
            <input type="number" step="1" min="1" max="1000" name="max_score" class="form-control" style="max-width:90px" value="<?php echo asg_e($_POST['max_score'] ?? (float) $sub['max_score']); ?>">
        </div>
        <label class="asg-section d-block" for="fb">Feedback</label>
        <textarea id="fb" name="feedback" class="form-control mb-3" rows="5" placeholder="What went well, what to improve (required when returning)"><?php echo asg_e($_POST['feedback'] ?? (string) $sub['feedback']); ?></textarea>
        <div class="row g-2">
            <div class="col-12"><button name="action" value="grade" class="btn btn-brand w-100"><?php echo $sub['status'] === 'graded' ? 'Update grade' : 'Publish grade'; ?></button></div>
            <div class="col-6"><button name="action" value="grade" class="btn btn-outline-success w-100" onclick="this.form.insertAdjacentHTML('beforeend','<input type=hidden name=next value=1>')">Publish &amp; next</button></div>
            <div class="col-6"><button name="action" value="return" class="btn btn-outline-danger w-100" onclick="return confirm('Send the work back to the student for changes?')">Return for changes</button></div>
        </div>
        <p class="small text-muted mt-2 mb-0">The student is notified either way. Current status: <strong><?php echo asg_e(['submitted' => 'awaiting grading', 'graded' => 'graded', 'returned' => 'returned'][$sub['status']]); ?></strong>.</p>
    </form>
</div>
</div>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/tutor/layout.php';
