<?php
// The project page (admin portal (allowed admins only)). Login and role are checked in components/staffcalendar/boot.php on every request, and again for every action.
$sc_portal = 'admin';
require __DIR__ . '/../../components/staffcalendar/project.php';
require $sc_layout;
