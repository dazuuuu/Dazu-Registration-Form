<?php
$pwa = ['portal' => 'intern', 'name' => 'Dazu Intern Portal', 'short_name' => 'Dazu Intern', 'start' => 'dashboard/index.php',
        'description' => 'Clock in, see your tasks, reports and alerts — Dazu Training Institute internship.',
        'shortcuts' => [['Clock in', 'attendance/index.php'], ['My tasks', 'tasks/index.php'], ['Messages', 'messages/index.php']]];
require __DIR__ . '/../components/pwa/manifest.php';
