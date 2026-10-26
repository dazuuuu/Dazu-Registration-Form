<?php
$pwa = ['portal' => 'admin', 'name' => 'Dazu Admin', 'short_name' => 'Dazu Admin', 'start' => 'dashboard.php',
        'description' => 'Registrations, students, payments, courses and tutors — Dazu Training Institute.',
        'shortcuts' => [['Students', 'students/index.php'], ['Payments', 'payments/index.php'], ['Tutors', 'tutors/index.php']]];
require __DIR__ . '/../components/pwa/manifest.php';
