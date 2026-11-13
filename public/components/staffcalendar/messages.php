<?php
// public/components/staffcalendar/messages.php — the old address of the Birthday messages page. The message library now lives in Settings · Staff Calendar · Birthday messages (one copy),
// so this address only checks that the person is the super admin (boot.php, real role from the database) and sends them there.
$sc_portal = 'superadmin';
require __DIR__ . '/boot.php';
$q = ['tab' => 'messages'];
if (in_array($_GET['tag'] ?? '', ['Funny', 'Cherished', 'Tech', 'Sheng', 'Blessing'], true)) $q['tag'] = $_GET['tag'];
if (in_array($_GET['status'] ?? '', ['approved', 'pending', 'rejected', 'off'], true)) $q['status'] = $_GET['status'];
header('Location: ' . url('superadmin/staffcalendar/settings.php') . '?' . http_build_query($q));
exit;
