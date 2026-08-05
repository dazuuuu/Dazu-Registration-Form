<?php
require_once __DIR__ . '/../_lib/bootstrap.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';

json_response(['success' => true, 'csrf_token' => csrf_token()]);
