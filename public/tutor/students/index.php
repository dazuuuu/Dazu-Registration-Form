<?php
require_once __DIR__ . '/../../../app/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';

use Models\Connection;
use Models\TutorModel;

Middleware::requireTutor();
$pdo = Connection::getInstance()->getConnection();
$tutorModel = new TutorModel($pdo);
$tutor = $tutorModel->getByUserId((int)$_SESSION['user_id']);
$students = $tutorModel->getStudentsForTutor((int)$tutor['id']);
$page_title = 'My Students';
ob_start();
?>
<div class="card-tutor"><div class="head">Enrolled Students</div>
<?php if (empty($students)): ?><p class="p-3 text-muted mb-0">No students in your courses yet.</p>
<?php else: ?>
<div class="table-responsive"><table class="table mb-0">
<thead class="table-light"><tr><th>Student ID</th><th>Name</th><th>Course</th><th>Progress</th><th></th></tr></thead>
<tbody>
<?php foreach ($students as $s): ?>
<tr>
<td><?php echo htmlspecialchars($s['registration_no']); ?></td>
<td><?php echo htmlspecialchars($s['full_name']); ?><br><small class="text-muted"><?php echo htmlspecialchars($s['email']); ?></small></td>
<td><?php echo htmlspecialchars($s['course_title']); ?></td>
<td><?php echo (int)$s['units_completed']; ?> / <?php echo (int)$s['total_units']; ?> units</td>
<td><a href="view.php?id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-outline-primary">View</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/tutor/layout.php';
