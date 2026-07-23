<?php
// Redirect legacy SmS dashboard to the main admin dashboard.
require_once __DIR__ . '/_init.php';
header('Location: ' . url('admin/dashboard.php#sms-alerts'));
exit;
