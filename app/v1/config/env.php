<?php
// app/v1/config/env.php — load .env once (project root)

if (!function_exists('dazu_load_env')) {
    function dazu_load_env(): void {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $root = dirname(__DIR__, 3);
        $envFile = $root . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($envFile)) {
            return;
        }

        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            if ($k !== '' && getenv($k) === false) {
                putenv("$k=$v");
            }
        }
    }
}

if (!function_exists('dazu_env')) {
    function dazu_env(string $key, ?string $default = null): ?string {
        dazu_load_env();
        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }
        $v = getenv($key);
        return $v !== false ? $v : $default;
    }
}
