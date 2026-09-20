<?php
// Legacy student link: fee records now live in the superadmin dashboard, so admins land on the registration.
require_once __DIR__ . '/../../../app/v1/init.php';

$registrationId = (int) ($_GET['id'] ?? 0);
header('Location: ' . url($registrationId > 0 ? 'admin/registrations/view.php?id=' . $registrationId : 'admin/registrations/index.php?status=approved'), true, 302);
exit;
