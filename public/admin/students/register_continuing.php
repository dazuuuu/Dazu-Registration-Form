<?php
// Redirect legacy URL to unified onboard page
require_once __DIR__ . '/../../../app/v1/config/paths.php';
header('Location: ' . url('admin/students/onboard.php?tab=existing'));
exit();
