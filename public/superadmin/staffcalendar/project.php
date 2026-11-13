<?php
// The project page (superadmin portal). Login and role are checked in components/staffcalendar/boot.php on every request, and again for every action.
$sc_portal = 'superadmin';
require __DIR__ . '/../../components/staffcalendar/project.php';
require $sc_layout;
