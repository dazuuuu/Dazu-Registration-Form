<?php
// The Staff Calendar "Reminders" list (secretary portal). Login, her Active status and her Staff Calendar switch are checked in components/staffcalendar/boot.php on every request; View only is enforced again for every change.
$sc_portal = 'secretary';
require __DIR__ . '/../../components/staffcalendar/reminders.php';
if (empty($sc_plain)) require $sc_layout;
