<?php
require_once __DIR__ . '/../_init.php';
require_once __DIR__ . '/../../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../../app/v1/helpers/document_letterhead.php';
require_once __DIR__ . '/../../../../app/v1/services/DocumentComposeService.php';

sms_require_tables($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sms_redirect('admin/sms/documents/compose.php');
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    sms_flash('danger', 'Security check failed.');
    sms_redirect('admin/sms/documents/compose.php');
}

$action = $_POST['action'] ?? 'preview';
$template = $_POST['template'] ?? 'admission_letter';
$body = (string) ($_POST['body'] ?? '');
$subjectOverride = trim((string) ($_POST['subject'] ?? ''));

$docService = new DocumentComposeService($pdo);
if (!$docService->templateMeta($template)) {
    sms_flash('danger', 'Unknown template.');
    sms_redirect('admin/sms/documents/compose.php');
}

$courseId = (int) ($_POST['course_id'] ?? 0);
$smsStudentId = (int) ($_POST['sms_student_id'] ?? 0);
$regIsNew = !empty($_POST['reg_is_new']);
$regIsPreview = !empty($_POST['reg_is_preview']);

$ctx = $docService->defaultContext($courseId ?: null);
$ctx['recipient_name'] = trim((string) ($_POST['recipient_name'] ?? $ctx['recipient_name']));
$ctx['email'] = trim((string) ($_POST['email'] ?? $ctx['email']));
$ctx['phone'] = trim((string) ($_POST['phone'] ?? $ctx['phone']));
$ctx['study_mode'] = trim((string) ($_POST['study_mode'] ?? $ctx['study_mode']));
$ctx['payment_reference'] = trim((string) ($_POST['payment_reference'] ?? $ctx['payment_reference']));
$ctx['invoice_no'] = trim((string) ($_POST['invoice_no'] ?? $ctx['invoice_no']));
$ctx['receipt_no'] = trim((string) ($_POST['receipt_no'] ?? $ctx['receipt_no']));

$ctx = $docService->applyCourseFees(
    $ctx,
    $courseId > 0 ? $courseId : null,
    $smsStudentId > 0 ? $smsStudentId : null
);

if ($template === 'receipt') {
    $postedPaid = trim((string) ($_POST['amount_paid'] ?? ''));
    if ($postedPaid !== '') {
        $ctx['amount_paid'] = $postedPaid;
    }
}

$claimReg = $regIsNew && $regIsPreview;
$regResolved = $docService->resolveRegistrationNumber(
    $courseId,
    $smsStudentId > 0 ? $smsStudentId : null,
    $ctx['email'],
    $claimReg && in_array($action, ['download', 'email'], true)
);
$ctx['registration_no'] = $regResolved['registration_no'] !== ''
    ? $regResolved['registration_no']
    : trim((string) ($_POST['registration_no'] ?? ''));

$built = $docService->buildDocument($template, $body, $ctx);
if (empty($built['ok'])) {
    sms_flash('danger', $built['error'] ?? 'Could not build document.');
    sms_redirect('admin/sms/documents/compose.php?template=' . urlencode($template));
}

$subject = $subjectOverride !== '' ? document_merge_placeholders($subjectOverride, $ctx) : $built['subject'];
$filenameBase = $template . '-' . ($ctx['registration_no'] !== '' ? $ctx['registration_no'] : 'document');

if ($action === 'preview') {
    header('Content-Type: text/html; charset=UTF-8');
    echo $built['html'];
    exit;
}

$pdf = $docService->renderPdf($built['html'], $filenameBase);
if (empty($pdf['ok'])) {
    if ($action === 'email') {
        sms_flash('danger', $pdf['error'] ?? 'PDF failed.');
        sms_redirect('admin/sms/documents/compose.php?template=' . urlencode($template));
    }
    header('Content-Type: text/html; charset=UTF-8');
    echo '<p style="font-family:sans-serif;padding:24px;">PDF could not be generated. Showing HTML preview instead.</p>';
    echo $built['html'];
    exit;
}

if ($action === 'download') {
    $docService->recordIssuedDocument([
        'template_slug'   => $template,
        'template_label'  => $built['label'],
        'delivery'        => 'download',
        'recipient_name'  => $ctx['recipient_name'],
        'recipient_email' => $ctx['email'],
        'registration_no' => $ctx['registration_no'],
        'course_id'       => $courseId > 0 ? $courseId : null,
        'course_title'    => $ctx['course_title'],
        'sms_student_id'  => $smsStudentId > 0 ? $smsStudentId : null,
        'file_path'       => $pdf['rel_path'],
        'file_name'       => $pdf['filename'],
        'email_subject'   => $subject,
        'actor_id'        => (int) ($_SESSION['user_id'] ?? 0),
    ]);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($pdf['filename']) . '"');
    header('Content-Length: ' . filesize($pdf['path']));
    readfile($pdf['path']);
    exit;
}

if ($action === 'email') {
    $toEmail = $ctx['email'];
    $toName = $ctx['recipient_name'];
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        sms_flash('danger', 'Enter a valid recipient email before sending.');
        sms_redirect('admin/sms/documents/compose.php?template=' . urlencode($template));
    }

    $result = $docService->sendEmail($toEmail, $toName, $subject, $built['html'], $pdf['path']);
    if (!empty($result['ok'])) {
        $recordId = $docService->recordIssuedDocument([
            'template_slug'   => $template,
            'template_label'  => $built['label'],
            'delivery'        => 'email',
            'recipient_name'  => $toName,
            'recipient_email' => $toEmail,
            'registration_no' => $ctx['registration_no'],
            'course_id'       => $courseId > 0 ? $courseId : null,
            'course_title'    => $ctx['course_title'],
            'sms_student_id'  => $smsStudentId > 0 ? $smsStudentId : null,
            'file_path'       => $pdf['rel_path'],
            'file_name'       => $pdf['filename'],
            'email_subject'   => $subject,
            'actor_id'        => (int) ($_SESSION['user_id'] ?? 0),
        ]);
        $sms->log(
            'document_emailed',
            'Emailed ' . $built['label'] . ' to ' . $toEmail,
            'document',
            $recordId ?: 0
        );
        sms_flash('success', 'Document emailed to ' . $toEmail . '.');
    } else {
        sms_flash('danger', 'Email failed: ' . ($result['error'] ?? $result['message'] ?? 'Unknown error'));
    }
    sms_redirect('admin/sms/documents/compose.php?template=' . urlencode($template));
}

sms_redirect('admin/sms/documents/index.php');
