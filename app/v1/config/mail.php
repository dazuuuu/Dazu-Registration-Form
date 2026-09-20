<?php
require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/../helpers/mail_settings.php';

/**
 * Mail configuration
 *
 * - enabled: general outbound mail (student registration, tutor alerts, etc.)
 * - password_reset_enabled: OTP / forgot-password emails (on by default)
 *
 * This is the ONE mail configuration file: every mailer in the system reads it.
 * Values saved in the admin portal (Settings -> Email) win; anything left empty there falls back to .env
 * (MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, ...).
 */
return mail_settings_merge([
    'transport'  => dazu_env('MAIL_TRANSPORT', 'smtp'),

    /** General mail — student enrolment always force-enables this in CourseRegistrationHandler */
    'enabled'    => filter_var(dazu_env('MAIL_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN),

    /** Password reset OTP — enabled by default */
    'password_reset_enabled' => filter_var(
        dazu_env('MAIL_PASSWORD_RESET_ENABLED', 'true'),
        FILTER_VALIDATE_BOOLEAN
    ),

    'host'       => dazu_env('MAIL_HOST', 'smtp.gmail.com'),
    'port'       => (int) dazu_env('MAIL_PORT', '587'),
    'encryption' => dazu_env('MAIL_ENCRYPTION', 'tls'),
    'username'   => dazu_env('MAIL_USERNAME', ''),
    'password'   => dazu_env('MAIL_PASSWORD', ''),

    'from_email' => dazu_env('MAIL_FROM_EMAIL', 'no-reply@dazuhub.co.ke'),
    'from_name'  => dazu_env('MAIL_FROM_NAME', 'Dazu Training Institute'),
    'logo_path'  => public_path('assets/images/logo/logo.png'),
]);
