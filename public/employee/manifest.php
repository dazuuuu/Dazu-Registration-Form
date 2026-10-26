<?php
$pwa = ['portal' => 'employee', 'name' => 'Dazu Staff', 'short_name' => 'Dazu Staff', 'start' => 'dashboard/index.php',
        'description' => 'Your staff portal — Dazu Training Institute.',
        'shortcuts' => [['My profile', 'profile/index.php']]];
require __DIR__ . '/../components/pwa/manifest.php';
