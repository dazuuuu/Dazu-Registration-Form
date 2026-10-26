<?php
// Partial — included by other pages, never opened directly.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit('Not found'); }
// public/superadmin/sms/_init.php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/SmsModuleService.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;

Middleware::requireSuperPortal();

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

function sms_require_tables(PDO $pdo): void
{
    if (!SmsModuleService::isInstalled($pdo)) {
        sms_flash('warning', 'SmS tables are not set up yet. Please run the database migration first.');
        sms_redirect('superadmin/sms/migrate.php');
    }
}
