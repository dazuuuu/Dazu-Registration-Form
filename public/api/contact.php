<?php
/**
 * Pentagon UI — contact form endpoint.
 * POST /api/contact.php
 *
 * Accepts JSON or form fields from Elementor / Contact Form 7 style payloads.
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

error_reporting(0);
ini_set('display_errors', '0');

try {
    require_once __DIR__ . '/../../app/v1/bootstrap.php';
    require_once __DIR__ . '/../../app/v1/config/db_connect.php';
    require_once __DIR__ . '/../../app/v1/controllers/EnquiryController.php';

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    // Elementor nested fields: form_fields[name], form_fields[email], etc.
    if (!empty($input['form_fields']) && is_array($input['form_fields'])) {
        $ff = $input['form_fields'];
        $input = array_merge($input, [
            'name' => $ff['name'] ?? $ff['field_name'] ?? '',
            'email' => $ff['email'] ?? '',
            'phone' => $ff['phone'] ?? $ff['tel'] ?? $ff['telephone'] ?? '',
            'service' => $ff['field_c4ab847'] ?? $ff['subject'] ?? $ff['service'] ?? '',
            'message' => $ff['message'] ?? $ff['field_message'] ?? '',
        ]);
    }

    $payload = [
        'name' => trim((string) ($input['name'] ?? '')),
        'email' => trim((string) ($input['email'] ?? '')),
        'phone' => trim((string) ($input['phone'] ?? '')),
        'service' => trim((string) ($input['service'] ?? $input['subject'] ?? '')),
        'message' => trim((string) ($input['message'] ?? '')),
    ];

    if ($payload['phone'] === '') {
        $payload['phone'] = '0000000000';
        if ($payload['message'] !== '') {
            $payload['message'] = "[Contact form — no phone provided]\n" . $payload['message'];
        }
    }
    if ($payload['message'] === '' && $payload['service'] !== '') {
        $payload['message'] = 'Subject: ' . $payload['service'];
    }

    $controller = new EnquiryController($pdo);
    $result = $controller->submit($payload);
    echo json_encode($result);
} catch (Throwable $e) {
    error_log('Pentagon contact API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again or call us directly.']);
}
