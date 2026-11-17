<?php
// Staff Calendar save / delete (secretary portal). Login, her Active status and her Staff Calendar switch are checked in components/staffcalendar/boot.php on every request; View only is enforced again for every change.
$sc_portal = 'secretary';
require __DIR__ . '/../../components/staffcalendar/action.php';

