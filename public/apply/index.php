<?php
// /apply — redirect to course apply page when course_id is set
require_once __DIR__ . '/../../app/v1/config/paths.php';

$courseId = (int) ($_GET['course_id'] ?? $_GET['id'] ?? 0);
if ($courseId > 0) {
    redirect(url('courses/view.php?id=' . $courseId));
}

$abbr = isset($_GET['abbr']) ? trim($_GET['abbr']) : '';
if ($abbr !== '') {
    redirect(url('courses/view.php?abbr=' . rawurlencode($abbr)));
}

redirect(url('courses/index.php'));
