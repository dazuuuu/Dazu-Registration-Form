<?php
// public/api/intake/stk_initiate.php — Book now, step 2: trigger the Daraja STK push for the booking fee.
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();
header('Content-Type: application/json; charset=utf-8');

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level() > 0) ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Fatal: ' . $e['message']]);
    }
});

function out($d, $code = 200) { while (ob_get_level() > 0) ob_end_clean(); http_response_code($code); echo json_encode($d); exit; }

require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../../app/v1/services/BookingPaymentService.php';

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') out(['success' => false, 'message' => 'Method not allowed'], 405);

$pdo = \Models\Connection::getInstance()->getConnection();
if (!en_installed($pdo) || !bkp_installed($pdo)) out(['success' => false, 'message' => 'Booking is not available right now.'], 500);

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) $body = $_POST;

if (!csrf_verify($body['csrf_token'] ?? null)) out(['success' => false, 'message' => 'Security check failed. Please refresh the page and try again.'], 422);
if (trim((string) ($body['website'] ?? '')) !== '') out(['success' => false, 'message' => 'Please try again.'], 422);

$course = (new \Models\CourseModel($pdo))->getById((int) ($body['course_id'] ?? 0));
if (!$course || !course_is_shareable($course)) out(['success' => false, 'message' => 'That course is not available.'], 404);

$st = $pdo->prepare("SELECT COUNT(*) FROM enrolment_stk_payments WHERE ip = ? AND created_at >= ?");
$st->execute([(string) ($_SERVER['REMOTE_ADDR'] ?? ''), date('Y-m-d H:i:s', strtotime(en_now()) - 3600)]);
if ((int) $st->fetchColumn() >= 10) out(['success' => false, 'message' => 'Too many attempts from this device. Please try again later or call us.'], 429);

$r = bkp_initiate($pdo, $course, $body, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
out($r, $r['ok'] ? 200 : 422);
