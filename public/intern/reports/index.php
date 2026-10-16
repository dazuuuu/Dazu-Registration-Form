<?php
// Reports & Logbook: daily logs, weekly and monthly reports. Rejected or pending ones can be fixed and sent again.
// The tutor's teaching ratings (if any) also show here as part of the logbook.
require __DIR__ . '/../_boot.php';
require_once __DIR__ . '/../../../app/v1/services/InternTeachingService.php';

$self = url('intern/reports/index.php');
if (intern_post_ok()) {
    $r = in_report_save($db, $intern, $_POST);
    SessionModel::setFlash($r['ok'] ? 'success' : 'error', in_e($r['ok'] ? 'Report sent to your supervisor.' : $r['error']));
    header('Location: ' . $self);
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Location: ' . $self);
    exit;
}
$reports = in_reports($db, (int) $intern['id']);
$edit = null;
if ($eid = (int) ($_GET['edit'] ?? 0)) foreach ($reports as $r) if ((int) $r['id'] === $eid && $r['status'] !== 'approved') $edit = $r;
$taught = array_filter(it_topics_for_intern($db, (int) $intern['id'], false), fn($t) => $t['status'] === 'taught');
$pill = ['approved' => 'g', 'pending' => 'o', 'rejected' => 'r'];
$val = fn($k, $d = '') => in_e($edit[$k] ?? $d);
$page_title = 'Reports & Logbook';
ob_start();
?>
<div class="row g-3">
    <div class="col-lg-5">
        <form method="POST" class="ip-card" id="new"><?php echo csrf_field(); ?>
            <h3 class="mb-3"><?php echo $edit ? 'Fix and send again' : 'New report'; ?></h3>
            <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label small fw-semibold">Type</label>
                    <select name="type" class="form-select"><?php foreach (['daily' => 'Daily log', 'weekly' => 'Weekly report', 'monthly' => 'Monthly report'] as $k => $l): ?>
                        <option value="<?php echo $k; ?>" <?php echo ($edit['type'] ?? '') === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php endforeach; ?></select></div>
                <div class="col-6"><label class="form-label small fw-semibold">Day / week / month of</label><input type="date" name="period_start" value="<?php echo $val('period_start', date('Y-m-d')); ?>" max="<?php echo date('Y-m-d'); ?>" class="form-control"></div>
            </div>
            <?php if ($edit && $edit['supervisor_comment']): ?><div class="ip-alert mb-2"><strong>Supervisor:</strong> “<?php echo in_e($edit['supervisor_comment']); ?>”</div><?php endif; ?>
            <label class="form-label small fw-semibold">Activities done *</label><textarea name="activities" class="form-control mb-2" rows="4" required><?php echo $val('activities'); ?></textarea>
            <label class="form-label small fw-semibold">Skills learned</label><textarea name="skills" class="form-control mb-2" rows="2"><?php echo $val('skills'); ?></textarea>
            <label class="form-label small fw-semibold">Challenges</label><textarea name="challenges" class="form-control mb-2" rows="2"><?php echo $val('challenges'); ?></textarea>
            <label class="form-label small fw-semibold">Lessons learned</label><textarea name="lessons" class="form-control mb-3" rows="2"><?php echo $val('lessons'); ?></textarea>
            <button class="btn btn-success"><i class="fas fa-paper-plane me-1"></i>Send to supervisor</button>
            <?php if ($edit): ?><a href="<?php echo $self; ?>" class="btn btn-link">Cancel</a><?php endif; ?>
        </form>
    </div>
    <div class="col-lg-7">
        <div class="ip-card">
            <h3 class="mb-3">My reports</h3>
            <?php if (!$reports): ?><div class="ip-muted">No reports yet. Start with today's daily log.</div><?php endif; ?>
            <?php foreach ($reports as $r): ?>
                <details class="border rounded-3 p-3 mb-2">
                    <summary class="d-flex justify-content-between align-items-center gap-2"><span><span class="fw-semibold"><?php echo in_e(in_report_label($r)); ?></span>
                        <span class="ip-muted d-block"><?php echo $r['status'] === 'approved' ? 'Approved ' . date('j M', strtotime($r['reviewed_at'])) : 'Sent ' . date('j M', strtotime($r['updated_at'] ?: $r['created_at'])); ?></span></span>
                        <span class="ip-pill <?php echo $pill[$r['status']]; ?>"><?php echo $r['status'] === 'pending' ? 'Pending review' : ucfirst($r['status']); ?></span></summary>
                    <div class="small mt-2">
                        <?php foreach (['activities' => 'Activities done', 'skills' => 'Skills learned', 'challenges' => 'Challenges', 'lessons' => 'Lessons learned'] as $k => $l): if ($r[$k]): ?>
                            <div class="mb-1"><strong><?php echo $l; ?>:</strong> <span style="white-space:pre-line"><?php echo in_e($r[$k]); ?></span></div><?php endif; endforeach; ?>
                        <?php if ($r['supervisor_comment']): ?><div class="<?php echo $r['status'] === 'rejected' ? 'ip-alert' : 'p-2 bg-light rounded-3'; ?> mt-2">“<?php echo in_e($r['supervisor_comment']); ?>”</div><?php endif; ?>
                        <?php if ($r['status'] !== 'approved'): ?><a class="btn btn-sm btn-outline-success mt-2" href="?edit=<?php echo (int) $r['id']; ?>#new"><?php echo $r['status'] === 'rejected' ? 'Fix and re-send' : 'Edit'; ?></a><?php endif; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
        <?php if ($taught): ?>
        <div class="ip-card">
            <h3 class="mb-3">Teaching log</h3>
            <?php foreach ($taught as $t): ?>
                <div class="ip-row"><span class="ip-pill d">Teaching</span><div class="grow"><div class="nm"><?php echo in_e($t['title']); ?></div>
                    <div class="ip-muted">Taught <?php echo date('j M', strtotime($t['taught_at'])); ?> · <?php echo in_e($t['tutor_name']); ?><?php echo $t['rating_comment'] ? ' · “' . in_e(mb_strimwidth($t['rating_comment'], 0, 80, '…')) . '”' : ''; ?></div></div>
                    <?php if ($t['preparation'] !== null): ?><span class="ip-pill g"><?php echo round(($t['preparation'] + $t['clarity'] + $t['confidence']) / 3, 1); ?> / 5</span><?php endif; ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/intern/layout.php';
