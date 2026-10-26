<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/TutorCourseModel.php';
require_once __DIR__ . '/../../../app/v1/services/CertificateService.php';

use Models\Connection;
use Models\TutorModel;
use Models\TutorCourseModel;

Middleware::requireTutor();
$pdo = Connection::getInstance()->getConnection();
require_once __DIR__ . '/../../../app/v1/helpers/user_photo.php';
$tutorModel = new TutorModel($pdo);
$tutor = $tutorModel->getByUserId((int)$_SESSION['user_id']);
$students = $tutorModel->getStudentsForTutor((int)$tutor['id']);
$tc = new TutorCourseModel($pdo);

$certs = [];
if ($students) {
    $ids = implode(',', array_map(fn($s) => (int) $s['id'], $students));
    $certs = $pdo->query("SELECT registration_id, certificate_no FROM certificates WHERE status <> 'withdrawn' AND registration_id IN ($ids)")->fetchAll(\PDO::FETCH_KEY_PAIR);
}
$page_title = 'My Students';
ob_start();
?>
<style>
.ts-bar{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:14px}
.ts-search{position:relative;flex:1;max-width:380px;min-width:200px}@media(max-width:575.98px){.ts-search{max-width:none}}
.ts-search i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted)}
.ts-search input{padding-left:38px;background:#fff;border:0;box-shadow:var(--shadow)}
.ts-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:12px}
@media(min-width:768px){.ts-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:1200px){.ts-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
.ts-card{background:#fff;border-radius:var(--r);box-shadow:var(--shadow);padding:16px;display:flex;flex-direction:column;gap:12px}
.ts-top{display:flex;gap:12px;align-items:center}
.ts-top .as-ava{width:46px;height:46px}
.ts-top .nm{font-weight:600;line-height:1.2}
.ts-top .sb{font-size:.76rem;color:var(--muted)}
.ts-course{font-size:.82rem;color:#4a4e47;background:#f6f7f4;border-radius:10px;padding:8px 10px}
.ts-meta{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;text-align:center}
.ts-meta div{background:#fafbf8;border-radius:10px;padding:8px 4px}
.ts-meta small{display:block;font-size:.66rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em}
.ts-meta b{font-size:.9rem}
.ts-act{display:flex;gap:8px}
.ts-act>*{flex:1}
.ts-act form{display:flex}
.ts-act form .btn{flex:1}
</style>
<div class="ts-bar">
    <div class="ts-search"><i class="fas fa-search"></i><input type="search" class="form-control" id="tsFilter" placeholder="Search your <?php echo count($students); ?> students…"></div>
    <span class="small text-muted"><i class="fas fa-circle-info me-1"></i>“Resend login” emails a new temporary password.</span>
</div>
<?php if (empty($students)): ?>
    <div class="card-tutor"><div class="as-empty"><i class="fas fa-user-graduate"></i>No students have been assigned to you yet.</div></div>
<?php else: ?>
<div class="ts-grid">
<?php foreach ($students as $s):
    $p = $tc->progressFor($s);
    $ini = strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($s['full_name'])), 0, 2))));
?>
    <div class="ts-card" data-q="<?php echo htmlspecialchars(mb_strtolower($s['full_name'] . ' ' . $s['registration_no'] . ' ' . $s['course_title'] . ' ' . $s['email'])); ?>">
        <div class="ts-top">
            <?php $avUrl = user_photo_url($pdo, (int) ($s['user_id'] ?? 0)); ?><span class="as-ava"><?php if ($avUrl): ?><img src="<?php echo htmlspecialchars($avUrl); ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:inherit"><?php else: echo htmlspecialchars($ini); endif; ?></span>
            <div style="min-width:0;flex:1">
                <div class="nm text-truncate"><?php echo htmlspecialchars($s['full_name']); ?></div>
                <div class="sb"><?php echo htmlspecialchars($s['registration_no']); ?> · <?php echo $s['study_mode'] === 'physical' ? 'Physical' : 'Online'; ?></div>
            </div>
            <?php if (isset($certs[$s['id']])): ?><span class="badge bg-success"><i class="fas fa-award me-1"></i>Certified</span>
            <?php elseif (!empty($s['studies_completed_at'])): ?><span class="badge bg-info text-dark">Completed</span>
            <?php else: ?><span class="badge bg-light text-dark border">In progress</span><?php endif; ?>
        </div>
        <div class="ts-course"><i class="fas fa-book-open me-1 text-success"></i><?php echo htmlspecialchars($s['course_title']); ?>
            <?php if ($p['total']): ?><div class="progress mt-2" style="height:6px"><div class="progress-bar" style="width:<?php echo (int) $p['percent']; ?>%"></div></div><?php endif; ?>
        </div>
        <div class="ts-meta">
            <div><small>Modules</small><b><?php echo $p['total'] ? $p['done'] . '/' . $p['total'] : '—'; ?></b></div>
            <div><small>Grade</small><b><?php echo $p['grade_percent'] !== null ? $p['grade_percent'] . '%' : '—'; ?></b></div>
        </div>
        <div class="ts-act">
            <a href="view.php?id=<?php echo (int) $s['id']; ?>" class="btn btn-sm btn-brand"><i class="fas fa-arrow-right me-1"></i>Open</a>
            <form method="POST" action="credentials.php" onsubmit="return confirm('Send <?php echo htmlspecialchars(addslashes($s['full_name']), ENT_QUOTES); ?> a new temporary password by email? Their current password will stop working.')">
                <?php echo csrf_field(); ?><input type="hidden" name="reg_id" value="<?php echo (int) $s['id']; ?>">
                <button class="btn btn-sm btn-outline-success"><i class="fas fa-key me-1"></i>Resend login</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
</div>
<p class="text-center text-muted small mt-3 d-none" id="tsNone">No students match your search.</p>
<script>
(function () {
    var f = document.getElementById('tsFilter'), cards = document.querySelectorAll('.ts-card[data-q]'), none = document.getElementById('tsNone');
    f.addEventListener('input', function () {
        var q = f.value.trim().toLowerCase(), shown = 0;
        cards.forEach(function (c) { var ok = !q || c.dataset.q.indexOf(q) > -1; c.style.display = ok ? '' : 'none'; shown += ok; });
        none.classList.toggle('d-none', shown > 0);
    });
})();
</script>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/tutor/layout.php';
