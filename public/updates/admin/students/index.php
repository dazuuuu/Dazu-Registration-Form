<?php
// Redirect legacy System → Students to SmS module
require_once __DIR__ . '/../../../app/v1/init.php';
header('Location: ' . url('admin/sms/students/index.php'), true, 302);
exit;
