<?php
// Legacy URL — redirect to dynamic course apply flow
require_once __DIR__ . '/../../app/v1/config/paths.php';

$courseId = (int) ($_GET['course_id'] ?? 0);
if ($courseId > 0) {
    redirect(url('courses/view.php?id=' . $courseId));
}

redirect(url('courses/index.php'));
