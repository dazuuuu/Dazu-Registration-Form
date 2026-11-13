<?php
// Staff Calendar (superadmin portal). Login and role are checked in components/staffcalendar/boot.php on every request.
$sc_portal = 'superadmin';
require __DIR__ . '/../../components/staffcalendar/page.php';
require $sc_layout;
