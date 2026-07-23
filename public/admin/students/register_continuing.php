<?php
// Redirect legacy URL to SmS student form
require_once __DIR__ . '/../../../app/v1/config/paths.php';
header('Location: ' . url('admin/sms/students/form.php'));
exit();
