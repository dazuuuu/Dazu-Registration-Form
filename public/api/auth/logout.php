<?php
require_once __DIR__ . '/../_lib/bootstrap.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

require_method('POST');
SessionModel::destroy();
json_response(['success' => true, 'message' => 'Logged out']);
