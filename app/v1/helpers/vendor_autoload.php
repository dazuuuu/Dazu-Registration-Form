<?php
// Safe Composer autoload — skips broken vendor trees (missing platform_check.php).

if (!function_exists('safe_composer_autoload')) {
    function safe_composer_autoload(): bool
    {
        static $loaded = false;
        static $attempted = false;
        if ($loaded) {
            return true;
        }
        if ($attempted) {
            return false;
        }
        $attempted = true;

        if (!defined('PROJECT_ROOT')) {
            require_once __DIR__ . '/../config/paths.php';
        }

        $candidates = [
            PROJECT_ROOT . '/vendor/autoload.php',
            dirname(__DIR__, 2) . '/vendor/autoload.php',
        ];

        foreach ($candidates as $autoload) {
            if (!is_file($autoload)) {
                continue;
            }
            $platformCheck = dirname($autoload) . '/composer/platform_check.php';
            if (!is_file($platformCheck)) {
                error_log('[vendor] Incomplete Composer install — run: composer install');
                continue;
            }
            require_once $autoload;
            $loaded = true;
            return true;
        }

        return false;
    }
}
