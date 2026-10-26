<?php
// Letter of recommendation POST actions on the certificate edit page (admin + superadmin, already authorised).
// Expects $db, $cert, $self. Include before the page's own POST handling; it exits after handling a lor_* action.
require_once __DIR__ . '/../../../app/v1/services/RecommendationService.php';

$lorAction = $_POST['lor_action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $lorAction !== '') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['error_msg'] = 'Security check failed. Please refresh and try again.';
        header('Location: ' . $self . '#lor');
        exit;
    }
    $me = (int) ($_SESSION['user_id'] ?? 0);
    $regId = (int) $cert['registration_id'];
    $letter = lor_get_by_reg($db, $regId);
    if ($lorAction === 'generate') {
        $res = lor_generate($db, $regId, $me);
        if ($res['ok'] && empty($res['existed'])) {
            $mail = lor_send_email($db, (int) $res['id']);
            $_SESSION['success_msg'] = 'Letter of recommendation generated and added to the student\'s Certificates page. ' . $mail['message'];
        } else {
            $_SESSION[$res['ok'] ? 'success_msg' : 'error_msg'] = $res['ok'] ? 'This student already has a letter of recommendation.' : $res['error'];
        }
    } elseif ($letter && $lorAction === 'save') {
        lor_update_body($db, (int) $letter['id'], (string) ($_POST['lor_body'] ?? ''), $me);
        $_SESSION['success_msg'] = 'Letter of recommendation saved.';
    } elseif ($letter && $lorAction === 'reset') {
        lor_update_body($db, (int) $letter['id'], '', $me);
        $_SESSION['success_msg'] = 'Letter text reset to the system wording for this course.';
    } elseif ($letter && $lorAction === 'email') {
        $mail = lor_send_email($db, (int) $letter['id']);
        $_SESSION[$mail['ok'] ? 'success_msg' : 'error_msg'] = $mail['message'];
    }
    header('Location: ' . $self . '#lor');
    exit;
}
