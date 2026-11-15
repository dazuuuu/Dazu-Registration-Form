<?php
// Staff Calendar (admin portal (allowed admins only)). Login and role are checked in components/staffcalendar/boot.php on every request.
$sc_portal = 'admin';
require __DIR__ . '/../../components/staffcalendar/page.php';
require $sc_layout;
