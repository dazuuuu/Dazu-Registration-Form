<?php
$pwa = ['portal' => 'tutor', 'name' => 'Dazu Tutor', 'short_name' => 'Dazu Tutor', 'start' => 'dashboard/index.php',
        'description' => 'Your students, course builder, submissions and alerts — Dazu Training Institute.',
        'shortcuts' => [['Submissions', 'submissions/index.php'], ['My students', 'students/index.php'], ['Notifications', 'notifications/index.php']]];
require __DIR__ . '/../components/pwa/manifest.php';
