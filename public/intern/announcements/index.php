<?php
// Announcements for the intern: priority, deadline, "Confirm attendance", read / unread (components/announcements/feed.php).
require __DIR__ . '/../_boot.php';
$page_title = 'Announcements';
ob_start();
require __DIR__ . '/../../components/announcements/feed.php';
$content = ob_get_clean();
require __DIR__ . '/../../templates/intern/layout.php';
