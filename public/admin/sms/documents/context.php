<?php
require_once __DIR__ . '/../_init.php';
require_once __DIR__ . '/../../../../app/v1/helpers/document_letterhead.php';
require_once __DIR__ . '/../../../../app/v1/services/DocumentComposeService.php';

sms_require_tables($pdo);

header('Content-Type: application/json; charset=UTF-8');

$docService = new DocumentComposeService($pdo);
$template = $_GET['template'] ?? 'admission_letter';
if (!$docService->templateMeta($template)) {
    echo json_encode(['ok' => false, 'error' => 'Unknown template']);
    exit;
}

$courseId = (int) ($_GET['course_id'] ?? 0);
$smsStudentId = (int) ($_GET['sms_student_id'] ?? 0);
$prospectId = (int) ($_GET['prospect_id'] ?? 0);
$email = trim((string) ($_GET['email'] ?? ''));

$built = $docService->buildComposeContext(
    $courseId ?: null,
    $smsStudentId ?: null,
    $prospectId ?: null,
    $email !== '' ? $email : null
);

$ctx = $built['ctx'];
$meta = $docService->templateMeta($template);
$fieldUi = $docService->templateFieldUi($template);

echo json_encode([
    'ok' => true,
    'ctx' => $ctx,
    'reg' => $built['reg'],
    'field_ui' => $fieldUi,
    'body_template' => $docService->bodyTemplate($template),
    'subject_template' => $meta['subject'],
    'body' => $docService->defaultBody($template, $ctx),
    'subject' => document_merge_placeholders($meta['subject'], $ctx),
]);
