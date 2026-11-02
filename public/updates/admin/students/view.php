<?php
// Redirect legacy student view to SmS module when linked
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';

use Models\Connection;

$registrationId = (int) ($_GET['id'] ?? 0);
$target = url('admin/sms/students/index.php');

if ($registrationId > 0) {
    $pdo = Connection::getInstance()->getConnection();
    $stmt = $pdo->prepare('SELECT id FROM sms_students WHERE registration_id = :rid LIMIT 1');
    $stmt->execute([':rid' => $registrationId]);
    $smsId = (int) $stmt->fetchColumn();
    if ($smsId > 0) {
        $target = url('admin/sms/students/view.php?id=' . $smsId);
    }
}

header('Location: ' . $target, true, 302);
exit;
