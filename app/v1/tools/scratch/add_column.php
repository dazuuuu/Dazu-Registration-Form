<?php
require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../models/Connection.php';
$pdo = \Models\Connection::getInstance()->getConnection();
try {
    $pdo->query("ALTER TABLE courses ADD COLUMN certificate_template VARCHAR(255) NULL");
    echo "Column added.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
