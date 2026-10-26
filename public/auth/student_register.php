<?php
// Legacy URL — redirect to dynamic course apply flow
require_once __DIR__ . '/../../app/v1/config/paths.php';

$courseId = (int) ($_GET['course_id'] ?? 0);
header('Location: ' . ($courseId > 0 ? url('courses/view.php?id=' . $courseId) : url('courses')), true, 301);
exit;
