<?php
// A secretary can NEVER delete an enquiry. Anything sent here is refused (after the usual checks) and nothing is deleted. Enquiries are kept, with their notes and history.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/helpers/secretary.php';

secretary_require('enquiries');
http_response_code(403);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'error' => 'Not allowed. Enquiries are never deleted.']);
