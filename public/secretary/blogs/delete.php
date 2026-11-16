<?php
// A secretary can NEVER delete a blog. Anything sent here is refused (after the usual checks), and nothing is deleted. Only the admin deletes, on the admin's own blog pages.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/helpers/secretary.php';

secretary_require('blogs');
http_response_code(403);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'error' => 'Not allowed. Only the admin can delete a blog.']);
