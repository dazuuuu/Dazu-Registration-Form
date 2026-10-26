<?php
// Assignments moved to students/assignments/ (order lock, drafts, extensions, results). Old links still work.
require_once __DIR__ . '/../../../app/v1/init.php';
header('Location: ' . url('students/assignments/view.php?id=' . (int) ($_GET['id'] ?? 0)), true, 301);
exit();
