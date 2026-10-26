<?php
// The old "manage my booking" page belonged to the retired intake system. Old email links land on the courses page.
require_once __DIR__ . '/../../app/v1/config/paths.php';
header('Location: ' . url('courses/index.php'), true, 301);
exit;
