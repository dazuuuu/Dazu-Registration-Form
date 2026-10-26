<?php
// Redirect legacy onboard flow to SmS student form
require_once __DIR__ . '/../../../app/v1/init.php';
header('Location: ' . url('admin/registrations/index.php?status=approved'), true, 302);
exit;
