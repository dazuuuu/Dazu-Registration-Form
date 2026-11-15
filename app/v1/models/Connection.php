<?php
// app/models/Connection.php
namespace Models;

use PDO;
use PDOException;

class Connection {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        $config = require __DIR__ . '/../config/database.php';
        
        try {
            require_once __DIR__ . '/../lib/DazuPdo.php';
            $this->connection = \DazuPdo::open($config);   // MySQL or PostgreSQL, from config/database.php
            require_once __DIR__ . '/../helpers/schema_guard.php';
            dazu_schema_guard($this->connection);
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
}
?>