<?php
// public/courses/enrolment-pack.php — download or email the one-time enrolment documents.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/enrolment_offer.php';
require_once __DIR__ . '/../../app/v1/helpers/course_fee_structure.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../app/v1/models/RegistrationModel.php';
require_once __DIR__ . '/../../app/v1/services/StudentNotifier.php';

use Models\Connection;
use Models\CourseModel;
use Models\RegistrationModel;

$offer = enrolment_offer_get();
if (!$offer) {
    http_response_code(403);
    echo 'This download/email link has expired. Apply again or ask admin to email the fee structure.';
    exit;
}

$pdo = Connection::getInstance()->getConnection();
$regModel = new RegistrationModel($pdo);
$reg = $regModel->getById((int) $offer['registration_id']);
if (!$reg) {
    $reg = [
        'id' => $offer['registration_id'],
        'email' => $offer['email'],
        'full_name' => $offer['full_name'],
        'course_id' => $offer['course_id'],
        'registration_no' => $offer['registration_no'],
    ];
}

$course = (new CourseModel($pdo))->getById((int) ($reg['course_id'] ?? $offer['course_id']));
$notifier = new StudentNotifier($pdo, null, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        echo json_encode(['ok' => false, 'message' => 'Security check failed. Refresh and try again.']);
        exit;
    }
    $result = $notifier->sendRegistrationReceived($reg, is_array($course) ? $course : []);
    echo json_encode([
        'ok' => !empty($result['ok']),
        'message' => !empty($result['ok'])
            ? 'Sent to ' . ($reg['email'] ?? $offer['email'])
            : ($result['message'] ?? 'Could not send email.'),
    ]);
    exit;
}

$pack = $notifier->buildEnrolmentPack($reg, is_array($course) ? $course : []);
$files = $pack['attachments'];
$temps = $pack['temps'];

if ($files === []) {
    foreach ($temps as $tmp) {
        if (is_file($tmp)) {
            @unlink($tmp);
        }
    }
    http_response_code(500);
    echo 'Could not generate the documents.';
    exit;
}

$slug = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($offer['registration_no'] ?: 'enrolment'));
$zipName = 'dazu-enrolment-' . trim($slug, '-') . '.zip';

if (class_exists('ZipArchive')) {
    $zipPath = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . uniqid('enrolment-zip-', true) . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        foreach ($files as $file) {
            $zip->addFile($file['path'], $file['name']);
        }
        $zip->close();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        @unlink($zipPath);
        foreach ($temps as $tmp) {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
        exit;
    }
}

$first = $files[0];
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $first['name'] . '"');
header('Content-Length: ' . filesize($first['path']));
readfile($first['path']);
foreach ($temps as $tmp) {
    if (is_file($tmp)) {
        @unlink($tmp);
    }
}
exit;
