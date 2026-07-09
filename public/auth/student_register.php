<?php
// public/auth/student_register.php — redirect to standalone apply form
require_once __DIR__ . '/../../app/v1/config/paths.php';
header('Location: ' . url('apply/index.php'), true, 302);
exit;
