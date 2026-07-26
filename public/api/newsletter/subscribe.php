<?php
header('Content-Type: application/json');

error_reporting(0);
ini_set('display_errors', 0);

try {
    require_once __DIR__ . '/../../../app/v1/init.php';
    require_once __DIR__ . '/../../../app/v1/controllers/NewsletterController.php';

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        echo json_encode(['success' => false, 'message' => 'Security check failed. Please refresh and try again.']);
        exit;
    }

    // Honeypot — real users never fill this hidden field.
    if (!empty($_POST['website'])) {
        echo json_encode(['success' => true, 'message' => 'Thanks for subscribing!']);
        exit;
    }

    $controller = new NewsletterController($pdo);
    $result = $controller->subscribe(['email' => $_POST['email'] ?? '', 'source' => $_POST['source'] ?? 'homepage']);
    echo json_encode($result);
} catch (Throwable $e) {
    error_log('Newsletter subscribe error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred. Please try again.']);
}
