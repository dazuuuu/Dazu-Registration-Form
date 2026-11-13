<?php
// The Staff Calendar "Reminders" list (and its small JSON/mark-read request). Role is checked in components/staffcalendar/boot.php.
$sc_portal = 'superadmin';
require __DIR__ . '/../../components/staffcalendar/reminders.php';
if (empty($sc_plain)) require $sc_layout;
