<?php
// public/tutor/students/manage.php — the tutor pauses, resumes or removes one of their own students.
// Pause: from today, no pay for that student until resumed. Remove: the student leaves the tutor's list and earns nothing this month.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/PayrollModel.php';

use Models\PayrollCalculator;

Middleware::requireTutor();
$pdo = \Models\Connection::getInstance()->getConnection();
$tutor = (new \Models\TutorModel($pdo))->getByUserId((int) $_SESSION['user_id']);
$back = url('tutor/students/' . (($_POST['from'] ?? '') === 'previous' ? 'previous.php' : 'index.php'));
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$tutor) { header('Location: ' . $back); exit; }
if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    $_SESSION['error_msg'] = 'Security check failed. Please refresh and try again.';
    header('Location: ' . $back); exit;
}
$tid = (int) $tutor['id'];
$uid = (int) ($_POST['user_id'] ?? 0);
$payroll = new \Models\PayrollModel($pdo);
$today = (new DateTimeImmutable('now', new DateTimeZone('Africa/Nairobi')))->format('Y-m-d');
$name = '';
$st = $pdo->prepare("SELECT COALESCE(NULLIF(TRIM(r.full_name), ''), u.username) FROM tutor_students ts JOIN users u ON u.id = ts.user_id
                     LEFT JOIN registrations r ON r.user_id = u.id WHERE ts.tutor_id = ? AND ts.user_id = ? ORDER BY r.id DESC LIMIT 1");
$st->execute([$tid, $uid]);
$name = (string) $st->fetchColumn();
if ($name === '') { $_SESSION['error_msg'] = 'That student is not on your list.'; header('Location: ' . $back); exit; }

switch ($_POST['action'] ?? '') {
    case 'pause':
        $r = $payroll->startPause($tid, $uid, 'pause', $today, null, 'Paused by the tutor');
        $_SESSION[$r['ok'] ? 'success_msg' : 'error_msg'] = $r['ok'] ? $name . ' is paused. You are not paid for them until you resume.' : $r['error'];
        break;
    case 'resume':
        $p = \Models\PayrollModel::currentPause($payroll->getPauses($tid), $uid);
        $ok = $p && $payroll->endPause((int) $p['id'], $tid, $today);
        $_SESSION[$ok ? 'success_msg' : 'error_msg'] = $ok ? $name . ' is back. Pay for them starts again.' : 'This student is not paused.';
        break;
    case 'remove':
        $ok = $payroll->unassignStudent($tid, $uid);
        if ($ok) {
            // Removed students earn nothing this month either.
            $key = PayrollCalculator::currentMonth();
            $h = $pdo->prepare("SELECT id, earned_months FROM tutor_payroll_history WHERE tutor_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1");
            $h->execute([$tid, $uid]);
            if ($row = $h->fetch(PDO::FETCH_ASSOC)) {
                $left = implode(',', array_filter(explode(',', (string) $row['earned_months']), fn($m) => $m !== '' && $m !== $key));
                $pdo->prepare("UPDATE tutor_payroll_history SET earned_months = ? WHERE id = ?")->execute([$left, (int) $row['id']]);
            }
            error_log('Tutor ' . $tid . ' removed student user ' . $uid);
        }
        $_SESSION[$ok ? 'success_msg' : 'error_msg'] = $ok ? $name . ' was removed from your students.' : 'Could not remove this student.';
        break;
    default:
        $_SESSION['error_msg'] = 'Unknown action.';
}
header('Location: ' . $back);
exit;
