<?php
require_once __DIR__ . '/paths.php';

/**
 * Mail configuration
 *
 * - enabled: general outbound mail (student registration, tutor alerts, etc.)
 * - password_reset_enabled: OTP / forgot-password emails (on by default)
 *
 * Override via .env: MAIL_ENABLED, MAIL_PASSWORD_RESET_ENABLED, MAIL_HOST, etc.
 */
return [
    'transport'  => dazu_env('MAIL_TRANSPORT', 'smtp'),

    /** General mail — keep false to avoid SMTP on account creation */
    'enabled'    => filter_var(dazu_env('MAIL_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN),

    /** Password reset OTP — enabled by default */
    'password_reset_enabled' => filter_var(
        dazu_env('MAIL_PASSWORD_RESET_ENABLED', 'true'),
        FILTER_VALIDATE_BOOLEAN
    ),

    'host'       => dazu_env('MAIL_HOST', 'smtp.gmail.com'),
    'port'       => (int) dazu_env('MAIL_PORT', '587'),
    'encryption' => dazu_env('MAIL_ENCRYPTION', 'tls'),
    'username'   => dazu_env('MAIL_USERNAME', 'njugunavickie7@gmail.com'),
    'password'   => dazu_env('MAIL_PASSWORD', 'yyne ltcw lwmj trso'),

    'from_email' => dazu_env('MAIL_FROM_EMAIL', 'no-reply@dazuhub.co.ke'),
    'from_name'  => dazu_env('MAIL_FROM_NAME', 'Dazu Institute'),
    'logo_path'  => public_path('assets/images/logo/logo.png'),
];
