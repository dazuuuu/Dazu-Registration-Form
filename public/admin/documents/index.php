<?php
// Legacy route — documents moved to SmS module.
require_once __DIR__ . '/../../../app/v1/init.php';
header('Location: ' . url('admin/sms/documents/index.php'));
exit;
