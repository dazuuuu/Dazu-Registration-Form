<?php
$pwa = ['portal' => 'students', 'name' => 'Dazu Student', 'short_name' => 'Dazu Student', 'start' => 'dashboard/index.php',
        'description' => 'Your course, assignments, fees, documents and alerts — Dazu Training Institute.',
        'shortcuts' => [['My course', 'course/index.php'], ['Finance', 'payments/index.php'], ['Notifications', 'notifications/index.php']]];
require __DIR__ . '/../components/pwa/manifest.php';
