<?php
// My ID Card (secretary portal). Not built yet. The server refuses it unless the admin switched "id_card" on for her (checked on every request).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/helpers/secretary_ui.php';
$sec = secretary_require('id_card');
$soon = ['My ID Card', 'fa-id-card', 'Your staff ID card will be here soon.', 'id_card'];
require __DIR__ . '/../../components/secretary/soon.php';
