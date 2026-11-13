<?php
// Staff Calendar (tutor portal). Login and role are checked in components/staffcalendar/boot.php on every request.
$sc_portal = 'tutor';
require __DIR__ . '/../../components/staffcalendar/page.php';
require $sc_layout;
