<?php
// public/admin/sms/_init.php
require_once __DIR__ . '/../../../app/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/SmsModuleService.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;
use Models\SessionModel;

Middleware::requireAdmin();

$pdo = Connection::getInstance()->getConnection();
$actorId = (int) ($_SESSION['user_id'] ?? 0);
$sms = new SmsModuleService($pdo, $actorId);

function sms_flash(string $type, string $msg): void
{
    SessionModel::setFlash($type, $msg);
}

function sms_redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function sms_money(float $n): string
{
    return 'KES ' . number_format($n, 2);
}
