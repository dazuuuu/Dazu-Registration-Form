<?php
// public/students/dashboard/index.php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/helpers/course_share.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/StudentModel.php';
require_once __DIR__ . '/../../../app/v1/models/PaymentModel.php';
require_once __DIR__ . '/../../../app/v1/models/RegistrationModel.php';
require_once __DIR__ . '/../../../app/v1/models/DashboardModel.php';
require_once __DIR__ . '/../../../app/v1/controllers/StudentController.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
use Models\Connection;
use Models\StudentModel;
use Models\PaymentModel;
use Models\RegistrationModel;
use Models\DashboardModel;
use Controllers\StudentController;

Middleware::requireStudent();

$pdo          = Connection::getInstance()->getConnection();
$studentModel = new StudentModel($pdo);
$paymentModel = new PaymentModel($pdo);
$userId       = (int) SessionModel::get('user_id');

$reg = $studentModel->getByUserId($userId);
if (!$reg) {
    SessionModel::setFlash('error', 'No approved registration found for your account. Please contact admin.');
    header('Location: ' . url('auth/logout.php'));
    exit();
}

$justLocked = $studentModel->ensurePortalStatus($reg);
if ($justLocked) {
    $reg = $studentModel->getByUserId($userId);
}

$portal_locked = (bool) $reg['portal_locked'];
$totalPaid     = $paymentModel->getTotalApproved((int) $reg['id']);
$totalOwed     = $studentModel->totalOwed($reg);
$balance       = max(0, $totalOwed - $totalPaid);
$hasPending    = $paymentModel->hasPending((int) $reg['id']);
$nextAmount    = $studentModel->nextInstallmentAmount($reg);
$installments  = RegistrationModel::calculateInstallments(
    (float) $reg['course_fee'],
    $reg['payment_plan'],
    (int) $reg['duration_months']
);
require_once __DIR__ . '/../../../app/v1/models/ClassworkModel.php';
require_once __DIR__ . '/../../../app/v1/models/AssignmentModel.php';
$cwModel = new \Models\ClassworkModel($pdo);
$cwItems = [];
try {
    // Assignments: open ones are to do, graded ones show their published result; locked ones are left out.
    foreach ((new \Models\AssignmentModel($pdo))->chain($userId) as $x) {
        if (in_array($x['state'], ['locked', 'skipped'], true)) continue;
        $cwItems[] = ['kind' => 'assignment', 'id' => $x['id'], 'title' => $x['title'], 'due_at' => $x['due'] ? \Models\AssignmentModel::local($x['due'], 'Y-m-d H:i:s') : null,
                      'submitted_at' => $x['state'] === 'closed' ? 'closed' : $x['submitted_at'], 'graded_at' => $x['state'] === 'graded' ? $x['result_published_at'] : null,
                      'result' => $x['state'] === 'graded' ? \Models\AssignmentModel::resultText($x) : null];
    }
    foreach ($cwModel->quizzesForStudent($userId) as $x) $cwItems[] = $x + ['kind' => 'quiz'];
} catch (Throwable $e) {
}
$cwTodo = array_filter($cwItems, fn($x) => !$x['submitted_at']);
$cwGraded = array_slice(array_filter($cwItems, fn($x) => $x['graded_at']), 0, 3);
$learnStats = (new DashboardModel($pdo))->studentStats((int)$reg['id'], (int)$reg['course_id']);

// Fetch Tutor, Timeline, and Announcements
$stmt = $pdo->prepare("SELECT ts.*,
                              COALESCE(NULLIF(TRIM(CONCAT_WS(' ', up.first_name, up.last_name)), ''), u.username) AS tutor_name,
                              COALESCE(NULLIF(t.phone, ''), up.phone) AS tutor_phone, u.email AS tutor_email
                       FROM tutor_students ts
                       JOIN tutors t ON ts.tutor_id = t.id
                       JOIN users u ON u.id = t.user_id
                       LEFT JOIN user_profiles up ON up.user_id = u.id
                       WHERE ts.user_id = ? ORDER BY ts.assigned_at DESC LIMIT 1");
$stmt->execute([$userId]);
$tutorData = $stmt->fetch(\PDO::FETCH_ASSOC);

$announcements = [];
if ($tutorData) {
    $stmt = $pdo->prepare("SELECT * FROM tutor_announcements WHERE student_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$userId]);
    $announcements = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    // Mark as read after fetching for popup logic
    $pdo->prepare("UPDATE tutor_announcements SET is_read = 1 WHERE student_id = ?")->execute([$userId]);
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $portal_locked) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed, please refresh and try again.';
    } else {
        $controller = new StudentController();
        $result = $controller->submitPayment(
            (int) $reg['id'],
            $userId,
            $_POST['mpesa_code'] ?? '',
            $_POST['payer_reference'] ?? ''
        );
        if ($result['ok']) {
            SessionModel::setFlash('success', 'Payment submitted. Your portal will update once admin verifies the payment.');
            header('Location: ' . url('students/dashboard/index.php'));
            exit();
        }
        $error = $result['error'];
    }
}

$planLabels = [
    'monthly'  => 'Monthly Installments',
    '3months'  => '3-Month Plan',
    'full'     => 'Full Payment',
];

$page_title = 'Dashboard';
ob_start();
$pwaPortal = 'students'; require __DIR__ . '/../../components/pwa/banner.php';
$docsLink = url('students/documents/index.php'); require __DIR__ . '/../../components/documents/missing_banner.php';
?>

<?php if ($portal_locked): ?>

<div class="locked-banner">
    <i class="fas fa-lock d-block"></i>
    <h4 class="mb-2">Portal Access Suspended</h4>
    <p class="mb-0 opacity-90">Your next installment of <strong>KSh <?php echo number_format($nextAmount); ?></strong> is due.
    Submit your M-Pesa payment code below to restore access.</p>
</div>

<?php if ($hasPending): ?>
    <div class="alert alert-info">
        <i class="fas fa-hourglass-half me-2"></i>
        You have a payment awaiting admin verification. Your portal will unlock once it is approved.
    </div>
<?php else: ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card-student">
        <div class="card-head"><i class="fas fa-mobile-alt me-2"></i>Submit School Fees Payment</div>
        <div class="card-body">
            <div class="alert alert-light border mb-3">
                <strong>Amount due:</strong> KSh <?php echo number_format($nextAmount); ?><br>
                <strong>Paybill:</strong> NCBA Paybill &middot; Account: Your Student ID (<?php echo htmlspecialchars($reg['registration_no']); ?>)
            </div>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <div class="mb-3">
                    <label class="form-label">M-Pesa Confirmation Code</label>
                    <input type="text" name="mpesa_code" class="form-control" required
                           placeholder="e.g. QGH7XXXXXX">
                </div>
                <div class="mb-3">
                    <label class="form-label">Name / Phone used to pay</label>
                    <input type="text" name="payer_reference" class="form-control" required
                           placeholder="e.g. John Mwangi, 07XXXXXXXX"
                           value="<?php echo htmlspecialchars($reg['full_name'] . ', ' . $reg['phone']); ?>">
                </div>
                <button type="submit" class="btn btn-brand w-100 py-2">
                    <i class="fas fa-paper-plane me-1"></i> Submit Payment for Verification
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php else: ?>

<?php
$firstName = mb_convert_case(strtok(trim((string) $reg['full_name']), ' ') ?: 'there', MB_CASE_TITLE, 'UTF-8');
$pct = (int) $learnStats['progress_pct'];
$timeline = null;
if ($tutorData && (int) $tutorData['timeline_days'] > 0 && !empty($tutorData['start_date'])) {
    $startDate = new DateTime($tutorData['start_date']);
    $endDate = (clone $startDate)->modify('+' . (int) $tutorData['timeline_days'] . ' days');
    $daysLeft = (int) (new DateTime())->diff($endDate)->format('%r%a');
    $timeline = [
        'end'  => $endDate,
        'left' => $daysLeft,
        'pct'  => min(100, max(0, ((int) $tutorData['timeline_days'] - $daysLeft) / (int) $tutorData['timeline_days'] * 100)),
    ];
}
$unreadAnn = array_filter($announcements, fn($a) => $a['is_read'] == 0);
// Latest ID card status for the quick tile
$idCard = null;
try {
    $st = $pdo->prepare("SELECT status, pickup_location FROM student_id_cards WHERE registration_id = ? ORDER BY id DESC LIMIT 1");
    $st->execute([(int) $reg['id']]);
    $idCard = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
} catch (\Throwable $e) {
}
$idText = [
    'requested' => 'Requested — awaiting the office',
    'generated' => 'Ready to download · printing',
    'ready'     => 'Ready for pickup at ' . ucfirst((string) ($idCard['pickup_location'] ?? '')),
    'collected' => 'Collected',
    'declined'  => 'Request declined',
][$idCard['status'] ?? ''] ?? 'Not requested yet';
?>
<style>
.sd-ring{--p:0;width:112px;height:112px;border-radius:50%;flex:none;display:grid;place-items:center;
  background:conic-gradient(var(--g) calc(var(--p)*1%),rgba(255,255,255,.14) 0)}
.sd-ring>div{width:88px;height:88px;border-radius:50%;background:var(--char);display:grid;place-items:center;text-align:center;line-height:1.05}
.sd-ring b{font-size:1.45rem;color:#fff}.sd-ring small{font-size:.65rem;opacity:.7;display:block}
.sd-hero-row{display:flex;gap:20px;align-items:center;flex-wrap:wrap}
.sd-hero-row .grow{flex:1;min-width:220px}
.sd-chip{display:inline-flex;align-items:center;gap:6px;font-size:.72rem;background:rgba(255,255,255,.12);border-radius:999px;padding:4px 10px;margin:8px 6px 0 0}
.sd-tl{margin-top:14px;max-width:420px}
.sd-tl .bar{height:6px;border-radius:99px;background:rgba(255,255,255,.16);overflow:hidden}
.sd-tl .bar>div{height:100%;background:#9ad97c;border-radius:99px}
.sd-tutor{display:flex;gap:12px;align-items:center}
.sd-tutor .as-ava{width:48px;height:48px;border-radius:14px;background:var(--g);font-size:1.1rem}
.sd-kv{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
@media(min-width:768px){.sd-kv{grid-template-columns:repeat(3,minmax(0,1fr))}}
.sd-kv small{display:block;color:var(--muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.05em}
.sd-kv strong{font-weight:600}
.sd-ann{max-height:260px;overflow-y:auto}
.sd-ann .item{padding:12px 20px;border-bottom:1px solid var(--line)}
.sd-ann .item:last-child{border-bottom:0}
.sd-ann .item.new{background:#fbfdf9;box-shadow:inset 3px 0 0 var(--g)}
.sd-id{display:flex;align-items:center;gap:12px;text-decoration:none;color:inherit}
.sd-id .card-mini{width:64px;aspect-ratio:85.6/54;border-radius:7px;flex:none;position:relative;overflow:hidden;background:#fff;border:1px solid var(--line);box-shadow:0 2px 6px rgba(0,0,0,.08)}
.sd-id .card-mini:before{content:"";position:absolute;right:-10px;top:-12px;width:44px;height:26px;background:var(--char);transform:rotate(28deg);box-shadow:-3px 3px 0 var(--g)}
.sd-id .card-mini:after{content:"";position:absolute;left:0;right:0;bottom:0;height:7px;background:var(--g)}
.sd-id .card-mini span{position:absolute;left:5px;top:13px;width:11px;height:14px;border:2px solid var(--g);border-radius:2px;background:#e6e8e3}
</style>

<div class="as-hero">
    <div class="sd-hero-row">
        <div class="grow">
            <div class="hi"><?php echo date('l, j F'); ?></div>
            <h1>Hi <?php echo htmlspecialchars($firstName); ?> 👋</h1>
            <p>You're studying <strong><?php echo htmlspecialchars($reg['course_title']); ?></strong>. Keep going — every unit counts.</p>
            <span class="sd-chip"><i class="fas fa-id-badge"></i><?php echo htmlspecialchars($reg['registration_no']); ?></span>
            <span class="sd-chip"><i class="fas <?php echo $reg['study_mode'] === 'physical' ? 'fa-school' : 'fa-laptop'; ?>"></i><?php echo $reg['study_mode'] === 'physical' ? 'Physical' : 'Online'; ?></span>
            <?php if ($timeline): ?>
                <div class="sd-tl">
                    <div class="d-flex justify-content-between small mb-1" style="opacity:.85">
                        <span>Study timeline · goal <?php echo $timeline['end']->format('j M Y'); ?></span>
                        <span><?php echo $timeline['left'] < 0 ? 'Overdue ' . abs($timeline['left']) . 'd' : $timeline['left'] . ' days left'; ?></span>
                    </div>
                    <div class="bar"><div style="width:<?php echo round($timeline['pct']); ?>%"></div></div>
                </div>
            <?php endif; ?>
            <a href="<?php echo url('students/course/index.php'); ?>" class="btn btn-light mt-3"><i class="fas fa-play me-2 text-success"></i>Continue learning</a>
        </div>
        <div class="sd-ring" style="--p:<?php echo $pct; ?>"><div><span><b><?php echo $pct; ?>%</b><small><?php echo (int) $learnStats['units_done']; ?>/<?php echo (int) $learnStats['units_total']; ?> units</small></span></div></div>
    </div>
</div>

<div class="as-stats">
    <div class="as-stat"><span class="ic"><i class="fas fa-chart-line"></i></span><span class="k">Progress</span><span class="v"><?php echo $pct; ?>%</span></div>
    <div class="as-stat"><span class="ic dark"><i class="fas fa-layer-group"></i></span><span class="k">Installments</span><span class="v"><?php echo (int) $reg['installments_paid']; ?> / <?php echo $installments['count']; ?></span></div>
    <div class="as-stat"><span class="ic"><i class="fas fa-coins"></i></span><span class="k">Total paid</span><span class="v">KSh <?php echo number_format($totalPaid); ?></span></div>
    <a class="as-stat" href="<?php echo url('students/payments/index.php'); ?>"><span class="ic <?php echo $balance > 0 ? 'red' : ''; ?>"><i class="fas fa-wallet"></i></span><span class="k">Balance</span>
        <span class="v" style="color:<?php echo $balance > 0 ? '#b42318' : 'var(--g-d)'; ?>"><?php echo $balance > 0 ? 'KSh ' . number_format($balance) : 'Paid'; ?></span></a>
</div>

<?php if ($hasPending): ?>
<div class="alert alert-warning"><i class="fas fa-hourglass-half me-2"></i>You have a payment awaiting admin verification.</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card-student">
            <div class="card-head"><i class="fas fa-book"></i>My course</div>
            <div class="card-body">
                <h5 class="mb-3"><?php echo htmlspecialchars($reg['course_title']); ?></h5>
                <div class="sd-kv mb-3">
                    <div><small>Study mode</small><strong><?php echo $reg['study_mode'] === 'physical' ? 'Physical' : 'Online'; ?></strong></div>
                    <div><small>Duration</small><strong><?php echo (int) $reg['duration_months']; ?> months</strong></div>
                    <div><small>Payment plan</small><span class="badge badge-plan"><?php echo $planLabels[$reg['payment_plan']] ?? $reg['payment_plan']; ?></span></div>
                    <div><small>Course fee</small><strong>KSh <?php echo number_format($reg['course_fee']); ?></strong></div>
                    <?php if ($reg['next_payment_due'] && $reg['payment_plan'] !== 'full'): ?>
                        <div><small>Next payment</small><strong class="<?php echo strtotime($reg['next_payment_due']) < time() ? 'text-danger' : ''; ?>"><?php echo date('d M Y', strtotime($reg['next_payment_due'])); ?></strong></div>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="<?php echo url('students/course/index.php'); ?>" class="btn btn-brand"><i class="fas fa-book-open me-1"></i>Open course</a>
                    <?php if ($reg['payment_plan'] !== 'full' && $nextAmount > 0 && !$hasPending): ?>
                        <a href="<?php echo url('students/payments/index.php'); ?>" class="btn btn-outline-success"><i class="fas fa-money-bill-wave me-1"></i>Pay KSh <?php echo number_format($nextAmount); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($cwItems): ?>
        <div class="card-student">
            <div class="card-head justify-content-between"><span><i class="fas fa-file-pen me-2"></i>Classwork</span>
                <?php if ($cwTodo): ?><span class="badge bg-warning text-dark rounded-pill"><?php echo count($cwTodo); ?> to do</span><?php endif; ?></div>
            <div class="list-group list-group-flush">
                <?php foreach (array_merge(array_slice($cwTodo, 0, 4), $cwGraded) as $x):
                    $href = url($x['kind'] === 'quiz' ? 'students/classwork/quiz.php?id=' . (int) $x['id']
                        : 'students/assignments/' . ($x['graded_at'] ? 'result' : 'view') . '.php?id=' . (int) $x['id']); ?>
                    <a href="<?php echo $href; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2">
                        <div style="min-width:0"><div class="small fw-semibold text-truncate"><i class="fas <?php echo $x['kind'] === 'quiz' ? 'fa-circle-question' : 'fa-file-pen'; ?> me-1 text-success"></i><?php echo htmlspecialchars($x['title']); ?></div>
                            <div class="small text-muted"><?php echo \Models\ClassworkModel::dueLabel($x['due_at']); ?></div></div>
                        <?php if ($x['graded_at']): ?><span class="badge bg-success"><?php echo htmlspecialchars($x['result'] ?? ((float) $x['score'] . ' / ' . (float) $x['max_score'])); ?></span>
                        <?php else: ?><span class="badge bg-warning text-dark"><?php echo $x['kind'] === 'quiz' ? 'Take quiz' : 'Submit'; ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="card-body py-2"><a href="<?php echo url('students/classwork/index.php'); ?>" class="small">All classwork →</a></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($announcements)): ?>
        <div class="card-student">
            <div class="card-head justify-content-between"><span><i class="fas fa-bullhorn me-2"></i>Announcements</span>
                <?php if ($unreadAnn): ?><span class="badge bg-danger rounded-pill"><?php echo count($unreadAnn); ?> new</span><?php endif; ?></div>
            <div class="sd-ann">
                <?php foreach ($announcements as $ann): ?>
                    <div class="item <?php echo $ann['is_read'] == 0 ? 'new' : ''; ?>">
                        <div class="d-flex justify-content-between gap-2"><strong class="small"><?php echo htmlspecialchars($ann['title']); ?></strong>
                            <small class="text-muted"><?php echo date('M d', strtotime($ann['created_at'])); ?></small></div>
                        <div class="small text-muted mt-1"><?php echo nl2br(htmlspecialchars($ann['message'])); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <?php if ($tutorData): ?>
        <div class="card-student">
            <div class="card-head"><i class="fas fa-chalkboard-user"></i>Your tutor</div>
            <div class="card-body">
                <div class="sd-tutor">
                    <span class="as-ava"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($tutorData['tutor_name'] ?? 'T', 0, 1))); ?></span>
                    <div style="min-width:0">
                        <div class="fw-semibold"><?php echo htmlspecialchars($tutorData['tutor_name']); ?></div>
                        <?php if ($tutorData['tutor_email']): ?><div class="small text-muted text-truncate"><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($tutorData['tutor_email']); ?></div><?php endif; ?>
                        <?php if ($tutorData['tutor_phone']): ?><div class="small text-muted"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($tutorData['tutor_phone']); ?></div><?php endif; ?>
                    </div>
                </div>
                <?php if ($tutorData['tutor_phone']): ?>
                    <div class="d-flex gap-2 mt-3">
                        <a class="btn btn-sm btn-outline-success flex-fill" href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $tutorData['tutor_phone'])); ?>"><i class="fas fa-phone me-1"></i>Call</a>
                        <a class="btn btn-sm btn-success flex-fill" target="_blank" rel="noopener" href="https://wa.me/<?php echo htmlspecialchars(preg_replace('/^0/', '254', preg_replace('/\D+/', '', $tutorData['tutor_phone']))); ?>"><i class="fab fa-whatsapp me-1"></i>WhatsApp</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card-student">
            <div class="card-body">
                <a class="sd-id" href="<?php echo url('students/documents/index.php'); ?>">
                    <span class="card-mini"><span></span></span>
                    <div class="flex-grow-1"><div class="fw-semibold">Student ID card</div><div class="small text-muted"><?php echo htmlspecialchars($idText); ?></div></div>
                    <i class="fas fa-chevron-right text-muted"></i>
                </a>
            </div>
        </div>

        <div class="card-student">
            <div class="card-head"><i class="fas fa-share-nodes"></i>Invite a friend</div>
            <div class="card-body">
                <p class="text-muted small mb-3">Share <strong><?php echo htmlspecialchars($reg['course_title']); ?></strong> — friends can see the course and register directly.</p>
                <?php
                $share_url = course_share_url((int) $reg['course_id'], $reg['registration_no'] ?? null);
                $share_title = 'Join me at Dazu Training Institute — ' . ($reg['course_title'] ?? 'tech course');
                $input_id = 'studentCourseShare';
                ?>
                <div class="input-group mb-2">
                    <input type="text" class="form-control form-control-sm" id="<?php echo $input_id; ?>" readonly value="<?php echo htmlspecialchars($share_url); ?>">
                    <button type="button" class="btn btn-brand btn-sm dh-share-copy" data-target="<?php echo $input_id; ?>"><i class="fas fa-copy"></i> Copy</button>
                    <a href="https://wa.me/?text=<?php echo rawurlencode(course_share_invite_text(['title' => $reg['course_title'] ?? 'tech course', 'overview' => ''], $share_url, $share_title)); ?>" class="btn btn-success btn-sm" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i></a>
                </div>
                <a href="<?php echo htmlspecialchars($share_url); ?>" target="_blank" rel="noopener" class="small"><i class="fas fa-external-link-alt me-1"></i>Preview share page</a>
            </div>
        </div>
    </div>
</div>

<?php if ($unreadAnn): ?>
    <div class="modal fade" id="announcementModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold"><i class="fas fa-bell me-2 text-success"></i>You have <?php echo count($unreadAnn); ?> new announcement<?php echo count($unreadAnn) > 1 ? 's' : ''; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php foreach ($unreadAnn as $ann): ?>
                        <div class="mb-3 border-bottom pb-2">
                            <h6 class="fw-bold"><?php echo htmlspecialchars($ann['title']); ?> <small class="text-muted float-end"><?php echo date('M d', strtotime($ann['created_at'])); ?></small></h6>
                            <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars($ann['message'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="modal-footer border-0"><button type="button" class="btn btn-brand w-100" data-bs-dismiss="modal">Got it</button></div>
            </div>
        </div>
    </div>
    <?php $extra_js = ($extra_js ?? '') . "<script>document.addEventListener('DOMContentLoaded', function() { new bootstrap.Modal(document.getElementById('announcementModal')).show(); });</script>"; ?>
<?php endif; ?>
<script>
document.querySelectorAll('.dh-share-copy').forEach(function(btn){
    btn.addEventListener('click', function(){
        var inp = document.getElementById(btn.getAttribute('data-target'));
        if(!inp) return;
        inp.select();
        (navigator.clipboard ? navigator.clipboard.writeText(inp.value) : Promise.resolve(document.execCommand('copy')))
            .then(function(){ btn.innerHTML='<i class="fas fa-check"></i> Copied'; setTimeout(function(){ btn.innerHTML='<i class="fas fa-copy"></i> Copy'; }, 2000); });
    });
});
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/students/layout.php';
