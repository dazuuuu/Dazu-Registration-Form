<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/ProgressModel.php';
require_once __DIR__ . '/../../../app/v1/models/CurriculumModel.php';
require_once __DIR__ . '/../../../app/v1/models/SubmissionModel.php';
require_once __DIR__ . '/../../../app/v1/models/TutorCourseModel.php';
require_once __DIR__ . '/../../../app/v1/services/CertificateService.php';
require_once __DIR__ . '/../../../app/v1/services/RecommendationService.php';

use Models\Connection;
use Models\TutorModel;
use Models\ProgressModel;
use Models\SubmissionModel;
use Models\TutorCourseModel;

Middleware::requireTutor();
$pdo = Connection::getInstance()->getConnection();
$tutorModel = new TutorModel($pdo);
$tutor = $tutorModel->getByUserId((int)$_SESSION['user_id']);
$tutorId = (int) $tutor['id'];
$regId = (int)($_GET['id'] ?? 0);
$tc = new TutorCourseModel($pdo);

$stmt = $pdo->prepare("SELECT r.*, c.title AS course_title FROM registrations r JOIN courses c ON r.course_id=c.id WHERE r.id=:id AND r.status='approved'");
$stmt->execute([':id'=>$regId]);
$student = $stmt->fetch();
$studentUserId = (int) ($student['user_id'] ?? 0);

// tutor_students.user_id is the student's users.id, not the registration id.
$stmt = $pdo->prepare("SELECT * FROM tutor_students WHERE tutor_id = ? AND user_id = ?");
$stmt->execute([$tutorId, $studentUserId]);
$ts_relation = $stmt->fetch();

if (!$student || !$ts_relation) {
    header('Location: index.php'); exit();
}
$self = 'view.php?id=' . $regId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
        header('Location: ' . $self); exit();
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'studies_completed') {
        $tc->setStudiesCompleted($regId, !empty($_POST['completed']), (int) $_SESSION['user_id']);
        SessionModel::setFlash('success', !empty($_POST['completed']) ? 'Marked as studies completed.' : 'Studies marked as not completed.');
    } elseif ($action === 'finish_course') {
        $fresh = $pdo->prepare("SELECT studies_completed_at FROM registrations WHERE id = ?");
        $fresh->execute([$regId]);
        if (!$fresh->fetchColumn()) {
            SessionModel::setFlash('error', 'Tick "Studies completed" first.');
        } else {
            // Same rules and steps as the admin "Generate certificate" button: zero balance, then issue and email.
            $res = cert_create_for_registration($pdo, $regId);
            if ($res['ok']) {
                // The tutor who finished the course signs it, even if the student has other tutors too.
                $pdo->prepare("UPDATE certificates SET tutor_id = ?, tutor_name = ?, taught_skills = ? WHERE id = ?")
                    ->execute([(int) $tutor['user_id'], $tutorModel->fullName($tutor), mb_substr(trim((string) ($_POST['taught_skills'] ?? '')), 0, 600) ?: null, (int) $res['id']]);
                cert_issue($pdo, (int) $res['id']);
                $mail = cert_send_email($pdo, (int) $res['id']);
                SessionModel::setFlash('success', 'Course finished. Certificate generated and added to the student\'s portal. ' . htmlspecialchars($mail['message']));
            } else {
                SessionModel::setFlash('error', htmlspecialchars($res['error']));
            }
        }
    } elseif ($action === 'cert_description') {
        // The tutor edits only the text under the student's name; everything else stays with the office.
        $c = lor_certificate($pdo, $regId);
        if ($c) {
            $pdo->prepare("UPDATE certificates SET taught_skills = ? WHERE id = ?")
                ->execute([mb_substr(trim((string) ($_POST['taught_skills'] ?? '')), 0, 600) ?: null, (int) $c['id']]);
            $msg = 'Certificate text saved.';
            if (!empty($_POST['email_copy'])) {
                $msg .= ' ' . cert_send_email($pdo, (int) $c['id'])['message'];
            }
            SessionModel::setFlash('success', htmlspecialchars($msg));
        }
    } elseif ($action === 'lor_generate') {
        $res = lor_generate($pdo, $regId, (int) $_SESSION['user_id']);
        if ($res['ok'] && empty($res['existed'])) {
            SessionModel::setFlash('success', 'Letter of recommendation generated and added to the student\'s portal. ' . htmlspecialchars(lor_send_email($pdo, (int) $res['id'])['message']));
        } else {
            SessionModel::setFlash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'This student already has a letter of recommendation.' : htmlspecialchars($res['error']));
        }
    } elseif ($action === 'reopen') {
        $tc->reopenSubmission((int) $tutor['user_id'], (int) $_POST['submission_id']);
        SessionModel::setFlash('success', 'The student can answer that question again.');
    }
    header('Location: ' . $self); exit();
}

$stmt = $pdo->prepare("SELECT * FROM tutor_announcements WHERE tutor_id = ? AND student_id = ? ORDER BY created_at DESC");
$stmt->execute([$tutorId, $studentUserId]);
$announcements = $stmt->fetchAll();

$student = array_merge($student, ['user_id' => $studentUserId]);
$mp = $tc->progressFor($student);
$ownModules = $mp['tutor'] && (int) $mp['tutor']['id'] === $tutorId;
$balance = cert_student_balance($pdo, $regId);
$stmt = $pdo->prepare("SELECT id, certificate_no, taught_skills, snapshot_duration_months, snapshot_course FROM certificates WHERE registration_id = ? AND status <> 'withdrawn' LIMIT 1");
$stmt->execute([$regId]);
$cert = $stmt->fetch();
$letter = $cert ? lor_get_by_reg($pdo, $regId) : null;
$stmt = $pdo->prepare("SELECT status FROM student_id_cards WHERE registration_id = ? AND status IN ('generated','ready','collected') LIMIT 1");
$stmt->execute([$regId]);
$hasIdCard = (bool) $stmt->fetchColumn();
$docUrl = fn(string $doc, string $q = '') => 'document.php?id=' . $regId . '&doc=' . $doc . $q;

// Module answers of this student, grouped by module.
$answersByModule = [];
foreach ($mp['modules'] as $m) {
    $items = array_filter($tc->getItems((int) $m['id']), [TutorCourseModel::class, 'isGradable']);
    $subs = $tc->submissionsFor($regId, array_map(fn($i) => (int) $i['id'], $items));
    $answersByModule[(int) $m['id']] = ['items' => $items, 'subs' => $subs];
}

$progress = (new ProgressModel($pdo))->getProgress($regId, (int)$student['course_id']);
$legacySubs = array_filter((new SubmissionModel($pdo))->getForRegistration($regId), fn($s) => $s['submission_type'] !== 'module');
$page_title = $student['full_name'];
ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="index.php" class="btn btn-link btn-sm ps-0 mb-0">← All students</a>
    <a href="../modules/index.php?course_id=<?php echo (int) $student['course_id']; ?>" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-layer-group me-1"></i> Edit my <?php echo htmlspecialchars($student['course_title']); ?> course
    </a>
</div>

<?php require_once __DIR__ . '/../../../app/v1/helpers/user_photo.php'; $ini = strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($student['full_name'])), 0, 2)))); ?>
<div class="card-tutor mb-3"><div class="p-3 d-flex flex-wrap gap-3 align-items-center">
    <?php $avUrl = user_photo_url($pdo, (int) ($student['user_id'] ?? 0)); ?><span class="as-ava" style="width:56px;height:56px;font-size:1.1rem;border-radius:16px;overflow:hidden"><?php if ($avUrl): ?><img src="<?php echo htmlspecialchars($avUrl); ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:inherit"><?php else: echo htmlspecialchars($ini); endif; ?></span>
    <div class="flex-grow-1" style="min-width:200px">
        <div class="fw-semibold fs-5 lh-sm"><?php echo htmlspecialchars($student['full_name']); ?></div>
        <div class="small text-muted"><?php echo htmlspecialchars($student['registration_no']); ?> · <?php echo htmlspecialchars($student['course_title']); ?> · <?php echo $student['study_mode'] === 'physical' ? 'Physical' : 'Online'; ?></div>
        <div class="small text-muted"><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($student['email']); ?><?php if (!empty($student['phone'])): ?> · <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($student['phone']); ?><?php endif; ?></div>
    </div>
    <div class="d-flex flex-wrap gap-2">
    <?php if ($hasIdCard): ?><a class="btn btn-outline-secondary" target="_blank" href="<?php echo $docUrl('id'); ?>"><i class="fas fa-id-card me-1"></i>Student ID</a><?php endif; ?>
    <?php require_once __DIR__ . '/../../../app/v1/services/StudentLetterService.php';
    if (sl_installed($pdo)): ?><a class="btn btn-outline-secondary" target="_blank" href="<?php echo htmlspecialchars(url('letters/student.php?reg=' . $regId)); ?>"><i class="fas fa-envelope-open-text me-1"></i>Letter</a><?php endif; ?>
    <?php require_once __DIR__ . '/../../../app/v1/services/TranscriptService.php';
    if (tr_installed($pdo) && tr_course_eligible($pdo, (int) $student['course_id'])): ?><a class="btn btn-outline-secondary" target="_blank" href="<?php echo htmlspecialchars(url('capstone/transcript.php?course=' . (int) $student['course_id'] . '&student=' . (int) $student['user_id'])); ?>"><i class="fas fa-file-lines me-1"></i>Transcript</a><?php endif; ?>
    <a class="btn btn-outline-secondary" target="_blank" href="<?php echo htmlspecialchars(url('id_cards/pdf.php?staff=' . (int) $_SESSION['user_id'])); ?>" title="Your staff ID card (upload your photo in My ID &amp; signature first)"><i class="fas fa-id-badge me-1"></i>My ID</a>
    <form method="POST" action="credentials.php" onsubmit="return confirm('Email this student a new temporary password? Their current password will stop working.')">
        <?php echo csrf_field(); ?><input type="hidden" name="reg_id" value="<?php echo $regId; ?>"><input type="hidden" name="return" value="view">
        <button class="btn btn-outline-success"><i class="fas fa-key me-1"></i>Resend login details</button>
    </form>
    </div>
</div></div>

<div class="card-tutor mb-3">
    <div class="head"><i class="fas fa-flag-checkered me-1"></i>Course completion &amp; certificate</div>
    <div class="p-3">
        <div class="row g-3 mb-3 text-center">
            <div class="col-6 col-md-4"><div class="border rounded p-2"><div class="small text-muted">Modules done</div><div class="fs-5 fw-bold"><?php echo $mp['done']; ?> / <?php echo $mp['total']; ?></div></div></div>
            <div class="col-6 col-md-4"><div class="border rounded p-2"><div class="small text-muted">Overall grade</div><div class="fs-5 fw-bold"><?php echo $mp['grade_percent'] !== null ? $mp['grade_percent'] . '%' : '—'; ?></div></div></div>
            <div class="col-12 col-md-4"><div class="border rounded p-2"><div class="small text-muted">Certificate</div><div class="fs-6 fw-bold <?php echo $cert ? 'text-success' : 'text-muted'; ?>"><?php echo $cert ? htmlspecialchars($cert['certificate_no']) : 'Not yet'; ?></div></div></div>
        </div>
        <?php if ($mp['total']): ?><div class="progress mb-3" style="height:8px"><div class="progress-bar bg-success" style="width:<?php echo $mp['percent']; ?>%"></div></div><?php endif; ?>
        <?php if (!$ownModules && $mp['total']): ?><p class="small text-warning">This student follows another tutor's modules for this course.</p><?php endif; ?>

        <?php if ($cert): ?>
            <div class="alert alert-success mb-3"><i class="fas fa-certificate me-1"></i>Course finished — certificate <?php echo htmlspecialchars($cert['certificate_no']); ?> has been issued and is in the student's portal.</div>
            <form method="POST" class="mb-3">
                <?php echo csrf_field(); ?><input type="hidden" name="action" value="cert_description">
                <label class="form-label fw-semibold mb-1" for="certText">Certificate text (under the student's name)</label>
                <div class="small text-muted mb-2">It always starts: <em>“Has successfully completed a <?php echo (int) $cert['snapshot_duration_months']; ?>-month programme of study in the <strong><?php echo htmlspecialchars($cert['snapshot_course']); ?></strong> at Dazu Training Institute”</em>, then your text. Your signature is printed as the course tutor.</div>
                <textarea id="certText" name="taught_skills" rows="3" maxlength="600" class="form-control mb-2" placeholder="and has met all the requirements of the programme. The programme covered …"><?php echo htmlspecialchars((string) $cert['taught_skills']); ?></textarea>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <button class="btn btn-success btn-sm"><i class="fas fa-save me-1"></i>Save text</button>
                    <a class="btn btn-outline-secondary btn-sm" target="_blank" href="<?php echo $docUrl('cert'); ?>"><i class="fas fa-eye me-1"></i>Preview certificate</a>
                    <label class="form-check small mb-0 ms-1"><input type="checkbox" class="form-check-input" name="email_copy" value="1"> Email the updated certificate to the student</label>
                </div>
            </form>
            <div class="border rounded p-3 d-flex flex-wrap gap-2 align-items-center">
                <div class="flex-grow-1"><div class="fw-semibold"><i class="fas fa-file-signature text-success me-1"></i>Letter of recommendation</div>
                    <div class="small text-muted"><?php echo $letter ? 'Generated ' . date('j M Y', strtotime($letter['created_at'])) . ' — the office can edit its wording.' : 'Written by the system from the course. Emailed to the student and added to their portal.'; ?></div></div>
                <?php if ($letter): ?>
                    <a class="btn btn-outline-secondary btn-sm" target="_blank" href="<?php echo htmlspecialchars(url('letters/recommendation.php?id=' . (int) $letter['id'])); ?>"><i class="fas fa-eye me-1"></i>Preview letter</a>
                <?php else: ?>
                    <form method="POST" class="m-0" onsubmit="return confirm('Generate the letter of recommendation? It will be emailed to the student.')"><?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="lor_generate">
                        <button class="btn btn-success btn-sm"><i class="fas fa-wand-magic-sparkles me-1"></i>Generate letter</button></form>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <form method="POST" class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                <?php echo csrf_field(); ?><input type="hidden" name="action" value="studies_completed">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="studiesDone" name="completed" value="1" <?php echo $student['studies_completed_at'] ? 'checked' : ''; ?> onchange="this.form.submit()">
                    <label class="form-check-label fw-semibold" for="studiesDone">Studies completed</label>
                </div>
                <span class="small text-muted"><?php echo $student['studies_completed_at']
                    ? 'since ' . date('d M Y', strtotime($student['studies_completed_at'])) . ($student['studies_completed_by'] ? '' : ' (ticked automatically when all modules were done)')
                    : 'Ticks itself when the student finishes every module.'; ?></span>
            </form>
            <p class="small mb-2"><a class="btn btn-outline-dark btn-sm" href="<?php echo htmlspecialchars(url('tutor/capstone/finish.php?student=' . $studentUserId . '&course=' . (int) $student['course_id'])); ?>"><i class="fas fa-flag-checkered me-1"></i>Mark course finished (capstone &amp; certificate checklist)</a></p>
            <p class="small mb-3"><a class="btn btn-outline-secondary btn-sm" target="_blank" href="<?php echo $docUrl('cert'); ?>"><i class="fas fa-eye me-1"></i>Preview certificate</a>
                <span class="text-muted ms-1">A draft (number “DRAFT”) — the real one is created when you finish the course.</span></p>
            <?php $canFinish = $student['studies_completed_at'] && $balance <= 0; ?>
            <form method="POST" onsubmit="return confirm('Mark the course as finished and generate the certificate? It will be emailed to the student and added to their portal.')">
                <?php echo csrf_field(); ?><input type="hidden" name="action" value="finish_course">
                <label class="form-label fw-semibold mb-1" for="certTextNew">Certificate text (under the student's name) <span class="text-muted fw-normal">— optional, you can edit it later</span></label>
                <textarea id="certTextNew" name="taught_skills" rows="2" maxlength="600" class="form-control mb-2" placeholder="and has met all the requirements of the programme. The programme covered …"></textarea>
                <button class="btn btn-success" <?php echo $canFinish ? '' : 'disabled'; ?>><i class="fas fa-award me-1"></i>Course finished — generate certificate</button>
                <?php if (!$student['studies_completed_at']): ?><div class="small text-muted mt-1">Available once studies are completed.</div>
                <?php elseif ($balance > 0): ?><div class="small text-muted mt-1">The office has to clear the student's account before the certificate can be generated.</div><?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card-tutor mb-3">
    <div class="head">Module progress &amp; answers</div>
    <?php if (!$mp['modules']): ?><p class="p-3 text-muted mb-0">No modules yet. <a href="../modules/index.php?course_id=<?php echo (int) $student['course_id']; ?>">Build your course</a>.</p>
    <?php else: ?>
    <div class="accordion accordion-flush" id="modAcc">
        <?php foreach ($mp['modules'] as $i => $m): $a = $answersByModule[(int) $m['id']]; ?>
        <div class="accordion-item">
            <h2 class="accordion-header"><button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#m<?php echo (int) $m['id']; ?>">
                <?php echo $m['is_done'] ? '<i class="fas fa-check-circle text-success me-2"></i>' : '<i class="far fa-circle text-muted me-2"></i>'; ?>
                <?php echo ($i + 1) . '. ' . htmlspecialchars($m['title']); ?>
                <span class="small text-muted ms-2">(<?php echo count($a['subs']); ?>/<?php echo count($a['items']); ?> answered)</span>
            </button></h2>
            <div id="m<?php echo (int) $m['id']; ?>" class="accordion-collapse collapse" data-bs-parent="#modAcc"><div class="accordion-body p-0">
                <?php if (!$a['items']): ?><p class="p-3 small text-muted mb-0">No questions in this module<?php echo $m['is_done'] ? ' — student marked it as done.' : '.'; ?></p><?php endif; ?>
                <ul class="list-group list-group-flush">
                <?php foreach ($a['items'] as $it): $s = $a['subs'][(int) $it['id']] ?? null; ?>
                    <li class="list-group-item small">
                        <div class="d-flex justify-content-between gap-2 flex-wrap">
                            <strong><?php echo htmlspecialchars($it['title']); ?></strong>
                            <?php if (!$s): ?><span class="badge bg-light text-dark border">Not answered</span>
                            <?php elseif ($s['status'] === 'graded'): ?><span class="badge bg-success"><?php echo rtrim(rtrim(number_format((float) $s['marks'], 2), '0'), '.'); ?> / <?php echo (int) ($it['max_score'] ?: 10); ?></span>
                            <?php else: ?><a class="badge bg-warning text-dark text-decoration-none" href="<?php echo url('tutor/submissions/index.php#sub-' . (int) $s['id']); ?>">Awaiting your grade</a><?php endif; ?>
                        </div>
                        <?php if ($s): ?>
                            <div class="text-muted mt-1">
                                <?php if ($it['answer_mode'] === 'choice'): ?>Chose: <?php echo htmlspecialchars($it['options'][(int) $s['answer_text']] ?? '?'); ?>
                                <?php elseif ($s['answer_text']): ?><span style="white-space:pre-wrap"><?php echo htmlspecialchars(mb_strimwidth($s['answer_text'], 0, 300, '…')); ?></span><?php endif; ?>
                                <?php if ($s['file_path']): ?><a href="<?php echo htmlspecialchars(settings_asset_url($s['file_path'])); ?>" target="_blank" class="ms-1"><i class="fas fa-download"></i> file</a><?php endif; ?>
                                <?php if ($s['github_url']): ?><a href="<?php echo htmlspecialchars($s['github_url']); ?>" target="_blank" rel="noopener" class="ms-1"><i class="fab fa-github"></i> repo</a><?php endif; ?>
                            </div>
                            <form method="POST" class="mt-1" onsubmit="return confirm('Delete this answer so the student can answer again?')"><?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="reopen"><input type="hidden" name="submission_id" value="<?php echo (int) $s['id']; ?>">
                                <button class="btn btn-link btn-sm p-0 text-danger">Let student re-answer</button></form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                </ul>
            </div></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<div class="card-tutor mb-3">
    <div class="head">Study Timeline</div>
    <div class="p-3">
        <form action="timeline.php" method="POST" class="row align-items-end g-2">
            <input type="hidden" name="student_id" value="<?php echo $studentUserId; ?>"><input type="hidden" name="reg_id" value="<?php echo $regId; ?>">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Start Date</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($ts_relation['start_date'] ?? date('Y-m-d')); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Timeline (Days)</label>
                <input type="number" name="timeline_days" class="form-control form-control-sm" placeholder="e.g. 90" value="<?php echo (int)($ts_relation['timeline_days'] ?? 0); ?>" required>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-brand btn-sm w-100">Save Timeline</button>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <?php if ($progress): ?>
        <div class="card-tutor mb-3"><div class="head">Curriculum Unit Progress</div><div class="p-0">
        <table class="table mb-0"><thead class="table-light"><tr><th>Unit</th><th>Test</th><th>Project</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($progress as $p): ?>
        <tr>
        <td><?php echo htmlspecialchars($p['title']); ?></td>
        <td><?php echo $p['test_completed'] ? '✅' : ($p['is_unlocked'] ? '⏳' : '—'); ?></td>
        <td><?php echo $p['project_completed'] ? '✅' : ($p['is_unlocked'] ? '⏳' : '—'); ?></td>
        <td><?php echo $p['completed_at'] ? '<span class="badge bg-success">Complete</span>' : ($p['is_unlocked'] ? '<span class="badge bg-primary">In progress</span>' : '<span class="badge bg-secondary">Locked</span>'); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div></div>
        <?php endif; ?>

        <?php if ($legacySubs): ?>
        <div class="card-tutor mb-3"><div class="head">Unit Submissions</div>
        <div class="list-group list-group-flush">
        <?php foreach ($legacySubs as $sub): ?>
        <div class="list-group-item d-flex justify-content-between">
        <span><?php echo ucfirst($sub['submission_type']); ?>: <?php echo htmlspecialchars((string) $sub['item_title']); ?>
        <span class="badge bg-<?php echo $sub['status']==='graded'?'success':'warning'; ?> ms-2"><?php echo $sub['status']; ?></span>
        <?php if ($sub['marks']!==null): ?> — <?php echo $sub['marks']; ?> marks<?php endif; ?>
        </span>
        <?php if ($sub['status']==='submitted'): ?><a href="<?php echo url('tutor/submissions/index.php#sub-' . (int)$sub['id']); ?>" class="btn btn-sm btn-brand">Grade</a><?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div></div>
        <?php endif; ?>
    </div>

    <div class="col-md-6">
        <div class="card-tutor mb-3">
            <div class="head">Send Announcement</div>
            <div class="p-3">
                <form action="announce.php" method="POST">
                    <input type="hidden" name="student_id" value="<?php echo $studentUserId; ?>"><input type="hidden" name="reg_id" value="<?php echo $regId; ?>">
                    <div class="mb-2">
                        <input type="text" name="title" class="form-control form-control-sm" placeholder="Title" required>
                    </div>
                    <div class="mb-2">
                        <textarea name="message" class="form-control form-control-sm" rows="3" placeholder="Message to student..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-brand btn-sm w-100">Send to Student</button>
                </form>
            </div>

            <?php if (!empty($announcements)): ?>
                <div class="list-group list-group-flush border-top">
                    <?php foreach ($announcements as $ann): ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <strong class="small"><?php echo htmlspecialchars($ann['title']); ?></strong>
                                <small class="text-muted" style="font-size: 0.7rem;"><?php echo date('M d', strtotime($ann['created_at'])); ?></small>
                            </div>
                            <p class="mb-0 small text-muted text-truncate"><?php echo htmlspecialchars($ann['message']); ?></p>
                            <small class="badge bg-<?php echo $ann['is_read'] ? 'success' : 'secondary'; ?> mt-1" style="font-size: 0.6rem;"><?php echo $ann['is_read'] ? 'Read' : 'Unread'; ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/tutor/layout.php';
