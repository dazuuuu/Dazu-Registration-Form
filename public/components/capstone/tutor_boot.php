<?php
// Common start for the tutor Capstone pages. Login and role are checked before any input is read (the Assignments boot does it).
// Gives $pdo, $am (AssignmentModel), $tutor, $tutorId (tutors.id), $me (users.id), and asg_back() / asg_csrf_or_back().
require __DIR__ . '/../assignments/tutor_boot.php';
require_once __DIR__ . '/../../../app/v1/services/CapstoneService.php';
require_once __DIR__ . '/../../../app/v1/services/CapstoneNotifier.php';

if (!cap_installed($pdo)) {
    http_response_code(503);
    exit('The Capstone feature is not installed yet. Ask the office to run System Updates (migration 088).');
}
cap_tick_lazy($pdo);   // opens scheduled capstones even when cron is not set up
require_once __DIR__ . '/ui.php';
