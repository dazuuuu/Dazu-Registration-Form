<?php
// public/admin/index.php
require_once __DIR__ . '/../../app/v1/config/paths.php';
header('Location: ' . url('admin/portal.php'));
exit();