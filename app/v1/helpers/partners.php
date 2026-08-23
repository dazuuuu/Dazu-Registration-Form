<?php
// Load active partners for the homepage.

if (!function_exists('partners_load_for_homepage')) {
    function partners_load_for_homepage(?\PDO $pdo = null): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        try {
            if ($pdo === null) {
                global $pdo;
            }
            if (!isset($pdo)) {
                require_once __DIR__ . '/../config/db_connect.php';
            }
            if (!class_exists('Models\\PartnerModel')) {
                require_once __DIR__ . '/../models/PartnerModel.php';
            }
            $model = new \Models\PartnerModel($pdo);
            $cache = $model->all(true);
        } catch (Throwable $e) {
            $cache = [];
        }
        return $cache;
    }
}
