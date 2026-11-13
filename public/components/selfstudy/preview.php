<?php
// public/components/selfstudy/preview.php — "Preview as student": the real study screen (students/selfstudy/course.php) for a tutor or admin who may open this course.
// Nothing is saved: no progress, no attempts, no answers, no "last studied" date. The study screen shows a banner and keeps every topic open so the tutor can look around.
require __DIR__ . '/boot.php';

$id = (int) ($_GET['id'] ?? 0);
$c = $sst_ready ? sst_course($db, $viewer, $id) : null;
if (!$c) sst_deny(404, 'That course was not found, or it was not shared with you.', $sst_base);
$ss_preview = ['course' => $c, 'self' => $sst_base . 'preview.php', 'close' => $sst_base . 'edit.php?id=' . $id];
$_GET['id'] = $id;
require __DIR__ . '/../../students/selfstudy/course.php';
