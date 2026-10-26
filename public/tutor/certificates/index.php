<?php
// Tutor: certificate requests from their own students (Pending, Returned, Approved, Sent). Marks are never shown here.
require __DIR__ . '/../../components/assignments/tutor_boot.php';
require_once __DIR__ . '/../../../app/v1/services/CertificateApplication.php';

use Models\AssignmentModel;

certapp_tick($pdo);   // page-load backup for the scheduled job (3-day reminders)
CertificateNotifier::flush();
$all = certapp_list_for_tutor($pdo, $tutorId);   // only this tutor's own students
$tabs = ['pending' => 'Pending', 'returned' => 'Returned', 'approved' => 'Approved', 'sent' => 'Sent'];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'pending';
$count = array_fill_keys(array_keys($tabs), 0);
foreach ($all as $a) $count[$a['status']]++;
$sentMonth = count(array_filter($all, fn($a) => $a['status'] === 'sent' && strtotime($a['sent_at'] . ' UTC') >= strtotime('first day of this month 00:00 UTC')));
$list = array_values(array_filter($all, fn($a) => $a['status'] === $tab));
$chip = ['pending' => ['Pending', 'st-extension'], 'returned' => ['Returned', 'st-returned'], 'approved' => ['Approved', 'st-progress'], 'sent' => ['Sent', 'st-graded']];
$page_title = 'Certificate requests';
ob_start();
?>
<div class="row g-2 mb-3">
    <?php foreach ([['Pending', $count['pending'], 'waiting for you'], ['Returned', $count['returned'], 'with the student'], ['Approved', $count['approved'], 'not sent yet'], ['Sent this month', $sentMonth, '']] as [$k, $v, $sub]): ?>
    <div class="col-6 col-md-3"><div class="card-tutor p-3 h-100"><div class="small text-muted"><?php echo asg_e($k); ?></div><div class="fs-3 fw-bold"><?php echo (int) $v; ?></div><div class="small text-muted"><?php echo asg_e($sub); ?></div></div></div>
    <?php endforeach; ?>
</div>
<div class="d-flex flex-wrap gap-1 mb-3"><?php foreach ($tabs as $k => $lbl): ?><a href="?tab=<?php echo $k; ?>" class="btn btn-sm <?php echo $tab === $k ? 'btn-brand' : 'btn-light'; ?>"><?php echo $lbl . ' ' . (int) $count[$k]; ?></a><?php endforeach; ?></div>
<div class="card-tutor p-0">
    <?php if (!$list): ?><div class="p-4 text-muted small">Nothing here. A student can apply when course time is finished, fees are 100%, the capstone is passed and the average is 50 or more.</div>
    <?php else: ?>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Student</th><th>Course</th><th>Applied</th><th>Status</th><th></th></tr></thead><tbody>
        <?php foreach ($list as $a): ?>
        <tr><td class="fw-semibold"><?php echo asg_e($a['student_name']); ?></td><td class="small"><?php echo asg_e($a['course_title']); ?></td>
            <td class="small"><?php echo asg_e(AssignmentModel::local($a['applied_at'], 'D j M, H:i')); ?></td>
            <td><span class="asg-chip <?php echo $chip[$a['status']][1]; ?>"><?php echo $chip[$a['status']][0]; ?></span></td>
            <td class="text-end"><a class="btn btn-sm <?php echo $a['status'] === 'pending' ? 'btn-brand' : 'btn-outline-dark'; ?>" href="request.php?id=<?php echo (int) $a['id']; ?>"><?php echo $a['status'] === 'pending' ? 'Write and approve' : 'Open'; ?></a></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</div>
<p class="small text-muted mt-2"><i class="fas fa-circle-info me-1"></i>A student can only apply when course time is finished, fees are 100%, the capstone is passed and the average is 50 or more. You can mark a course finished by hand for a student who finishes early, or move the end date after a break (My Students, then the student, then Mark course finished).</p>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
