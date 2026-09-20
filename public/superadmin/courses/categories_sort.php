<?php
// public/admin/courses/categories_sort.php — AJAX endpoint for drag-and-drop category ordering
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';

use Models\Connection;

Middleware::requireSuperPortal();

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit();
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    echo json_encode(['ok' => false, 'error' => 'Security check failed']);
    exit();
}

// Accepts either:
//   ids=3,1,4,2  (drag-drop ordered list — position = index)
//   sort_orders[id]=value  (legacy bulk-number form)
try {
    $pdo = Connection::getInstance()->getConnection();
    $stmt = $pdo->prepare('UPDATE course_categories SET sort_order = :sort_order WHERE id = :id');

    if (!empty($_POST['ids'])) {
        // Drag-and-drop mode: ids are in the desired display order
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'])));
        $pdo->beginTransaction();
        foreach ($ids as $position => $id) {
            $stmt->execute([':sort_order' => $position, ':id' => $id]);
        }
        $pdo->commit();
    } elseif (!empty($_POST['sort_orders']) && is_array($_POST['sort_orders'])) {
        // Legacy numeric input mode
        $pdo->beginTransaction();
        foreach ($_POST['sort_orders'] as $id => $order) {
            $stmt->execute([':sort_order' => (int) $order, ':id' => (int) $id]);
        }
        $pdo->commit();
    } else {
        echo json_encode(['ok' => false, 'error' => 'No data received']);
        exit();
    }

    echo json_encode(['ok' => true]);
} catch (\Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
