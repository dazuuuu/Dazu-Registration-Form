<?php
// app/v1/config/env.php — optional overrides taken from the server's own environment variables. No .env file is read.

if (!function_exists('dazu_load_env')) {
    function dazu_load_env(): void {}
}

if (!function_exists('dazu_env')) {
    function dazu_env(string $key, ?string $default = null): ?string {
        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }
        $v = getenv($key);
        return $v !== false ? $v : $default;
    }
}
