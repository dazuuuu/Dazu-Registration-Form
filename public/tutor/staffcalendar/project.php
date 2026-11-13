<?php
// The project page (tutor portal). Login and role are checked in components/staffcalendar/boot.php on every request, and again for every action.
$sc_portal = 'tutor';
require __DIR__ . '/../../components/staffcalendar/project.php';
require $sc_layout;
