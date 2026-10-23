<?php
// Tutor: Practical marks. For a "No submission (practical)" assignment the tutor records each student's mark
// (or Pass / Not yet). Only the tutor's own students on this assignment. Held grades stay hidden until the release time.
require __DIR__ . '/../../components/assignments/tutor_boot.php';

use Models\AssignmentModel;

$id = (int) ($_GET['id'] ?? 0);
$a = $am->getForTutor($id, $tutorId);
if (!$a || !(int) ($a['no_submission'] ?? 0) || $a['status'] !== 'published') asg_back('index.php', null, 'Practical marks are only for published "No submission" assignments.');
$self = 'marks.php?id=' . $id;
$students = $am->assignmentStudents($a);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    asg_csrf_or_back($self);
    $notifier = new AssignmentNotifier($pdo, $am);
    $held = AssignmentModel::gradesHeld($a);
    $saved = 0;
    $errs = [];
    foreach ((array) ($_POST['mark'] ?? []) as $suid => $v) {
        $suid = (int) $suid;
        $v = trim((string) $v);
        if ($v === '' || !isset($students[$suid]) || !$am->isTutorStudent($tutorId, $suid)) continue;
        $r = $am->recordPractical($a, $suid, $v, $me);
        if (!$r['ok']) { $errs[] = $students[$suid]['student']['full_name'] . ': ' . $r['error']; continue; }
        $saved++;
        if (!$held) $notifier->result($a, $am->submissionById($r['submission_id']), 'published');
    }
    asg_back($self, $saved ? 'Saved ' . $saved . ' mark' . ($saved === 1 ? '' : 's') . '.' . ($held ? ' Students see them at the release time.' : ' Students have been told.') : null,
        $errs ? implode(' ', $errs) : null);
}

$page_title = 'Practical marks';
ob_start();
?>
<a href="view.php?id=<?php echo $id; ?>" class="btn btn-link btn-sm ps-0 mb-2">← Back to the assignment</a>
<form method="POST" class="card-tutor p-3 p-md-4"><?php echo csrf_field(); ?>
    <h5 class="mb-0">Practical marks: <?php echo asg_e($a['title']); ?></h5>
    <div class="small text-muted mb-3">Students send nothing for this one. Record what you saw. <?php echo $a['grading_mode'] === 'marks' ? 'Marks out of ' . (int) $a['max_score'] . '.' : ''; ?>
        <?php echo AssignmentModel::gradesHeld($a) ? ' Grades are held until ' . asg_e(AssignmentModel::local($a['release_grades_at'])) . ' EAT.' : ''; ?></div>
    <?php if (!$students): ?><p class="text-muted mb-0">No students on this assignment.</p><?php endif; ?>
    <?php foreach ($students as $suid => $r): $sb = $r['sub']; $cur = $sb && $sb['status'] === 'graded' ? ($a['grading_mode'] === 'marks' ? (string) (float) $sb['score'] : (string) $sb['pass_result']) : ''; ?>
        <div class="d-flex align-items-center gap-2 py-2 border-bottom flex-wrap">
            <span class="flex-grow-1 fw-semibold"><?php echo asg_e($r['student']['full_name']); ?></span>
            <?php if ($a['grading_mode'] === 'marks'): ?>
                <input type="number" name="mark[<?php echo (int) $suid; ?>]" min="0" max="<?php echo (int) $a['max_score']; ?>" step="0.5" class="form-control form-control-sm" style="max-width:100px" value="<?php echo asg_e($cur); ?>">
                <span class="small text-muted">/ <?php echo (int) $a['max_score']; ?></span>
            <?php else: ?>
                <div class="btn-group btn-group-sm" role="group">
                    <?php foreach (['pass' => 'Pass', 'not_yet' => 'Not yet'] as $k => $lbl): ?>
                        <input type="radio" class="btn-check" name="mark[<?php echo (int) $suid; ?>]" value="<?php echo $k; ?>" id="m<?php echo (int) $suid . $k; ?>" <?php echo $cur === $k ? 'checked' : ''; ?>>
                        <label class="btn btn-outline-success" for="m<?php echo (int) $suid . $k; ?>"><?php echo $lbl; ?></label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php if ($students): ?><button class="btn btn-brand mt-3">Save marks</button><?php endif; ?>
    <p class="small text-muted mt-2 mb-0">Leave a box empty to skip a student. Missing marks are not counted as zero.</p>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
