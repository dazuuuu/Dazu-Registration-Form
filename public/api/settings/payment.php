<?php
require_once __DIR__ . '/../_lib/bootstrap.php';
require_once __DIR__ . '/../../../app/v1/helpers/payment_credentials.php';

json_response(['success' => true, 'data' => payment_credentials()]);
