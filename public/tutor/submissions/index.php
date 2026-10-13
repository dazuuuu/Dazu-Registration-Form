<?php
// Tutor: Grade Submissions (spec 8.2). Assignment queue with tabs and filters, extension/unlock requests, CSV export.
// The "Course builder" tab keeps grading of module answers and unit tests/projects exactly as before.
require __DIR__ . '/../../components/assignments/tutor_boot.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/SubmissionModel.php';
require_once __DIR__ . '/../../../app/v1/models/CurriculumModel.php';
require_once __DIR__ . '/../../../app/v1/models/ProgressModel.php';
require_once __DIR__ . '/../../../app/v1/models/TutorCourseModel.php';
require_once __DIR__ . '/../../../app/v1/controllers/TutorController.php';

use Models\AssignmentModel;
use Models\SubmissionModel;
use Models\TutorCourseModel;
use Controllers\TutorController;

$tc = new TutorCourseModel($pdo);
$error = null;

// Course builder grading (unchanged behaviour).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null)) {
    $ctrl = new TutorController();
    $subId = (int) $_POST['submission_id'];
    $r = ($_POST['kind'] ?? '') === 'module'
        ? $ctrl->gradeModuleSubmission($subId, $me, (float) $_POST['marks'], trim($_POST['feedback'] ?? ''))
        : $ctrl->gradeSubmission($subId, $me, (float) $_POST['marks'], trim($_POST['feedback'] ?? ''));
    if ($r['ok']) asg_back('index.php?tab=course', 'Graded. Student notified by email.');
    $error = $r['error'];
}

$tab = in_array($_GET['tab'] ?? '', ['awaiting', 'requests', 'graded', 'returned', 'all', 'course'], true) ? $_GET['tab'] : 'awaiting';
$filters = ['course' => (int) ($_GET['course'] ?? 0), 'assignment' => (int) ($_GET['assignment'] ?? 0), 'q' => trim((string) ($_GET['q'] ?? ''))];

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="grades-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student', 'Course', 'Assignment', 'Status', 'Submitted (EAT)', 'Version', 'Late', 'Marks', 'Out of', 'Result', 'Published (EAT)'], ",", "\"", "");
    foreach ($am->queue($tutorId, 'all', $filters) as $r) {
        fputcsv($out, [$r['full_name'], $r['course_title'], $r['title'], ucfirst($r['status']), AssignmentModel::local($r['submitted_at'], 'Y-m-d H:i'),
            (int) $r['version'], $r['is_late'] ? 'Yes' : '', $r['status'] === 'graded' && $r['grading_mode'] === 'marks' ? (float) $r['score'] : '',
            $r['grading_mode'] === 'marks' ? (int) $r['max_score'] : '', $r['status'] === 'graded' ? (string) $r['grade_label'] : '',
            AssignmentModel::local($r['published_at'], 'Y-m-d H:i')], ",", "\"", "");
    }
    exit;
}

$awaitingCount = count($am->queue($tutorId, 'awaiting'));
$requests = $am->requests($tutorId);
$moduleSubs = $tc->pendingForTutor($me);
$legacySubs = (new SubmissionModel($pdo))->getPendingForTutor($tutorId);
$courseWorkCount = count($moduleSubs) + count($legacySubs);
$rows = in_array($tab, ['awaiting', 'graded', 'returned', 'all'], true) ? $am->queue($tutorId, $tab, $filters) : [];
$courses = $am->tutorCourses($tutorId);
$assignments = $am->listAll($tutorId);
$qs = fn(array $over) => '?' . http_build_query(array_filter(array_merge(['tab' => $tab] + $filters, $over), fn($v) => $v !== '' && $v !== 0 && $v !== null));
$page_title = 'Grade Submissions';
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo asg_e($error); ?></div><?php endif; ?>
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
    <div class="asg-tabs d-flex flex-wrap gap-2">
        <?php foreach (['awaiting' => 'Awaiting grading', 'requests' => 'Requests', 'graded' => 'Graded', 'returned' => 'Returned', 'all' => 'All'] as $k => $lbl):
            $n = $k === 'awaiting' ? $awaitingCount : ($k === 'requests' ? count($requests) : null); ?>
            <a href="<?php echo $qs(['tab' => $k]); ?>" class="btn btn-sm <?php echo $tab === $k ? 'btn-brand' : 'btn-outline-secondary'; ?>"><?php echo $lbl; ?><?php if ($n): ?> <span class="badge bg-light text-dark ms-1"><?php echo $n; ?></span><?php endif; ?></a>
        <?php endforeach; ?>
        <?php if ($courseWorkCount || $tab === 'course'): ?>
            <a href="?tab=course" class="btn btn-sm <?php echo $tab === 'course' ? 'btn-brand' : 'btn-outline-secondary'; ?>">Course builder <span class="badge bg-light text-dark ms-1"><?php echo $courseWorkCount; ?></span></a>
        <?php endif; ?>
    </div>
    <a href="<?php echo $qs(['export' => 'csv']); ?>" class="btn btn-sm btn-outline-dark" title="Download grades as CSV"><i class="fas fa-download me-1"></i>Export grades</a>
</div>

<?php if (in_array($tab, ['awaiting', 'graded', 'returned', 'all'], true)): ?>
<form class="row g-2 mb-3" method="GET"><input type="hidden" name="tab" value="<?php echo asg_e($tab); ?>">
    <div class="col-md-4"><select name="course" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">All my courses (<?php echo count($courses); ?>)</option>
        <?php foreach ($courses as $cid => $ct): ?><option value="<?php echo (int) $cid; ?>" <?php echo $filters['course'] === (int) $cid ? 'selected' : ''; ?>><?php echo asg_e($ct); ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><select name="assignment" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">All assignments</option>
        <?php foreach ($assignments as $x): if ($filters['course'] && (int) $x['course_id'] !== $filters['course']) continue; ?><option value="<?php echo (int) $x['id']; ?>" <?php echo $filters['assignment'] === (int) $x['id'] ? 'selected' : ''; ?>><?php echo asg_e($x['title']); ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><input type="search" name="q" class="form-control form-control-sm" placeholder="Search student" value="<?php echo asg_e($filters['q']); ?>"></div>
</form>
<div class="card-tutor">
    <?php if (!$rows): ?><div class="as-empty"><i class="fas fa-inbox"></i><?php echo $tab === 'awaiting' ? 'No submissions awaiting grading.' : 'Nothing here.'; ?></div><?php else: ?>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Student</th><th>Assignment and course</th><th>Submitted</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
            $state = $r['status'] === 'graded' ? 'graded' : ($r['status'] === 'returned' ? 'returned' : 'submitted');
            $label = $r['status'] === 'submitted' ? ((int) $r['version'] > 1 ? 'Resubmitted' : 'Awaiting') : null; ?>
            <tr>
                <td class="fw-semibold"><?php echo asg_e($r['full_name']); ?></td>
                <td><div class="fw-semibold"><?php echo asg_e($r['title']); ?></div><div class="small text-muted"><?php echo asg_e($r['course_title'] ?: 'No course'); ?></div></td>
                <td class="small"><?php echo asg_e(AssignmentModel::local($r['submitted_at'], 'D j M, H:i')); ?><?php if ($r['is_late']): ?> <span class="asg-chip st-returned"><i class="fas fa-clock"></i>Late</span><?php endif; ?></td>
                <td><?php echo $label === 'Resubmitted' ? '<span class="asg-chip st-extension"><i class="fas fa-rotate"></i>Resubmitted</span>' : asg_chip($state, $state === 'graded' ? AssignmentModel::resultText($r) : $label);
                    if ($r['status'] === 'submitted' && $r['graded_at']): ?> <span class="small text-muted">draft grade</span><?php endif; ?></td>
                <td class="text-end"><a href="grade.php?id=<?php echo (int) $r['id']; ?>" class="btn btn-sm <?php echo $r['status'] === 'submitted' ? 'btn-brand' : 'btn-outline-secondary'; ?>"><?php echo $r['status'] === 'submitted' ? 'Grade' : 'Open'; ?></a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
<p class="small text-muted fst-italic mt-2">Oldest first. Only submissions from students you teach appear here.</p>

<?php elseif ($tab === 'requests'): ?>
<div class="card-tutor">
    <?php if (!$requests): ?><div class="as-empty"><i class="fas fa-hand"></i>No extension or unlock requests waiting.</div><?php endif; ?>
    <div class="list-group list-group-flush">
    <?php foreach ($requests as $q): ?>
        <div class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2" id="req-<?php echo (int) $q['id']; ?>">
            <div style="min-width:0">
                <span class="asg-chip st-extension me-1"><i class="fas <?php echo $q['type'] === 'unlock' ? 'fa-lock-open' : 'fa-e'; ?>"></i><?php echo $q['type'] === 'unlock' ? 'Unlock' : 'Extension'; ?></span>
                <strong><?php echo asg_e($q['full_name']); ?></strong> · <?php echo asg_e($q['title']); ?> <span class="small text-muted">(<?php echo asg_e($q['course_title'] ?: 'No course'); ?>)</span>
                <div class="small text-muted">Asked <?php echo asg_e(AssignmentModel::local($q['created_at'], 'D j M, H:i')); ?>
                    <?php if ($q['type'] === 'extension'): ?> · wants <?php echo asg_e(AssignmentModel::local($q['requested_due_at'], 'D j M, H:i')); ?> · <?php echo asg_e(AssignmentModel::REASONS[$q['reason_code']] ?? 'Other'); ?><?php endif; ?></div>
            </div>
            <a href="request.php?id=<?php echo (int) $q['id']; ?>" class="btn btn-sm btn-brand">Decide</a>
        </div>
    <?php endforeach; ?>
    </div>
</div>

<?php else: ?>
<?php if (!$moduleSubs && !$legacySubs): ?><div class="card-tutor"><p class="p-3 text-muted mb-0">No course builder work awaiting grading.</p></div><?php endif; ?>
<?php foreach ($moduleSubs as $sub): ?>
<div class="card-tutor mb-3" id="sub-<?php echo (int) $sub['id']; ?>">
    <div class="head d-flex justify-content-between flex-wrap gap-2">
        <span><?php echo htmlspecialchars($sub['module_title']); ?> · <?php echo htmlspecialchars($sub['item_title']); ?></span>
        <span class="small"><?php echo htmlspecialchars(TutorCourseModel::ANSWER_MODES[$sub['answer_mode']] ?? ''); ?></span>
    </div>
    <div class="p-3">
        <p class="mb-1"><strong><?php echo htmlspecialchars($sub['full_name']); ?></strong> (<?php echo htmlspecialchars($sub['registration_no']); ?>) · <?php echo htmlspecialchars($sub['course_title']); ?></p>
        <p class="small text-muted">Submitted <?php echo date('d M Y H:i', strtotime($sub['submitted_at'])); ?></p>
        <?php if ($sub['question_text']): ?><div class="small text-muted mb-2" style="white-space:pre-wrap"><strong>Question:</strong> <?php echo htmlspecialchars($sub['question_text']); ?></div><?php endif; ?>
        <div class="bg-light p-3 rounded mb-3">
            <?php if ($sub['answer_text']): ?><div style="white-space:pre-wrap"><?php echo htmlspecialchars($sub['answer_text']); ?></div><?php endif; ?>
            <?php if ($sub['file_path']): ?><a href="<?php echo htmlspecialchars(settings_asset_url($sub['file_path'])); ?>" target="_blank" class="btn btn-sm btn-outline-secondary mt-2"><i class="fas fa-download me-1"></i>Download submitted file</a><?php endif; ?>
            <?php if ($sub['github_url']): ?><a href="<?php echo htmlspecialchars($sub['github_url']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-dark mt-2"><i class="fab fa-github me-1"></i><?php echo htmlspecialchars($sub['github_url']); ?></a><?php endif; ?>
        </div>
        <form method="POST"><?php echo csrf_field(); ?>
            <input type="hidden" name="submission_id" value="<?php echo (int) $sub['id']; ?>"><input type="hidden" name="kind" value="module">
            <div class="row g-2">
                <div class="col-md-3"><label class="form-label">Marks (out of <?php echo (int) ($sub['max_score'] ?: 10); ?>)</label>
                    <input type="number" step="0.5" min="0" max="<?php echo (int) ($sub['max_score'] ?: 10); ?>" name="marks" class="form-control" required></div>
                <div class="col-md-9"><label class="form-label">Feedback</label><textarea name="feedback" class="form-control" rows="2"></textarea></div>
            </div>
            <button class="btn btn-brand mt-2"><i class="fas fa-check me-1"></i>Submit grade</button>
        </form>
    </div>
</div>
<?php endforeach; ?>
<?php foreach ($legacySubs as $sub): ?>
<div class="card-tutor mb-3" id="sub-<?php echo (int) $sub['id']; ?>">
<div class="head"><?php echo ucfirst($sub['submission_type']); ?>: <?php echo htmlspecialchars((string) $sub['item_title']); ?></div>
<div class="p-3">
<p><strong>Student:</strong> <?php echo htmlspecialchars($sub['full_name']); ?> (<?php echo htmlspecialchars($sub['registration_no']); ?>)</p>
<p><strong>Course:</strong> <?php echo htmlspecialchars($sub['course_title']); ?></p>
<div class="bg-light p-3 rounded mb-3"><?php echo nl2br(htmlspecialchars($sub['answer_text'] ?? '')); ?>
<?php if ($sub['file_path']): ?><br><a href="<?php echo htmlspecialchars($sub['file_path']); ?>" target="_blank">Download attachment</a><?php endif; ?>
</div>
<form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="submission_id" value="<?php echo (int) $sub['id']; ?>">
<div class="row g-2">
<div class="col-md-3"><label class="form-label">Marks</label><input type="number" step="0.01" name="marks" class="form-control" required></div>
<div class="col-md-9"><label class="form-label">Feedback</label><textarea name="feedback" class="form-control" rows="2"></textarea></div>
</div>
<button class="btn btn-brand mt-2"><i class="fas fa-check me-1"></i> Submit Grade</button>
</form>
</div></div>
<?php endforeach; ?>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/tutor/layout.php';
