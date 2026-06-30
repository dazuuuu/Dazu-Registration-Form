<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Cart API Debug</h1>";

// Check session
session_start();
echo "<h3>Session:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Check database connection
echo "<h3>Database:</h3>";
try {
    require_once __DIR__ . '/../../app/config/db_connect.php';
    echo "<p style='color:green'>✓ Database connected</p>";
    
    // Check tables
    $tables = ['store_cart', 'store_products', 'store_categories', 'users'];
    foreach ($tables as $table) {
        $result = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() > 0) {
            echo "<p style='color:green'>✓ Table '$table' exists</p>";
            
            // Show row count for cart
            if ($table == 'store_cart') {
                $count = $pdo->query("SELECT COUNT(*) FROM store_cart")->fetchColumn();
                echo "&nbsp;&nbsp;&nbsp;Total cart items: $count<br>";
                
                if (isset($_SESSION['user_id'])) {
                    $userCount = $pdo->prepare("SELECT SUM(quantity) FROM store_cart WHERE user_id = ?");
                    $userCount->execute([$_SESSION['user_id']]);
                    echo "&nbsp;&nbsp;&nbsp;Your cart count: " . ($userCount->fetchColumn() ?: 0) . "<br>";
                }
            }
        } else {
            echo "<p style='color:red'>✗ Table '$table' does NOT exist!</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Database error: " . $e->getMessage() . "</p>";
}

// Test cart count query
if (isset($_SESSION['user_id'])) {
    echo "<h3>Test Cart Query:</h3>";
    try {
        $sql = "SELECT COALESCE(SUM(quantity), 0) as total FROM store_cart WHERE user_id = :user_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>Cart count: " . ($result['total'] ?? 0) . "</p>";
    } catch (Exception $e) {
        echo "<p style='color:red'>Query error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>Not logged in. Cart count will be 0.</p>";
}
?>