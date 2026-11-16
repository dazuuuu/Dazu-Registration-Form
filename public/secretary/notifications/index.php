<?php
// Notifications (secretary portal): the existing notifications list, for the signed-in secretary only. Needs no switch.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/helpers/secretary_ui.php';
$sec = secretary_require();
$notifBase = 'secretary';
$sp_active = 'notifications';
require __DIR__ . '/../../components/notifications/page.php';
require __DIR__ . '/../../templates/secretary/layout.php';
