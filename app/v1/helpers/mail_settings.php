<?php
// app/v1/helpers/mail_settings.php
// Email (SMTP) settings saved from the admin portal (Settings -> Email). app/v1/config/mail.php is the ONE place the rest of
// the system reads mail configuration from, and it calls mail_settings_merge() below, so everything uses whatever is saved here.
//
//  - Values live in the site_settings table (keys mail_host, mail_port, ...).
//  - The SMTP password is encrypted (AES-256-GCM). The key comes from MAIL_SECRET_KEY in .env, or from app/v1/storage/mail.key,
//    which is created automatically the first time a password is saved and is never inside the web root.
//  - Anything left empty here falls back to the .env value, so existing installs keep working.

const MAIL_SETTING_FIELDS = ['host', 'port', 'encryption', 'username', 'password', 'from_email', 'from_name', 'enabled'];

function mail_settings_pdo(): ?PDO
{
    static $pdo = false;
    if ($pdo !== false) {
        return $pdo;
    }
    try {
        if (!class_exists('Models\\Connection')) {
            require_once __DIR__ . '/../models/Connection.php';
        }
        $pdo = \Models\Connection::getInstance()->getConnection();
    } catch (Throwable $e) {
        $pdo = null;
    }
    return $pdo;
}

function mail_settings_key_file(): string
{
    if (!function_exists('storage_path')) {
        require_once __DIR__ . '/../config/paths.php';
    }
    return storage_path('mail.key');
}

/** 32-byte encryption key, or null when none can be created. */
function mail_settings_key(bool $create = false): ?string
{
    $env = function_exists('dazu_env') ? dazu_env('MAIL_SECRET_KEY', '') : (getenv('MAIL_SECRET_KEY') ?: '');
    if ($env !== '' && $env !== null) {
        return hash('sha256', (string) $env, true);
    }
    $file = mail_settings_key_file();
    if (is_file($file)) {
        $hex = trim((string) @file_get_contents($file));
        return strlen($hex) === 64 && ctype_xdigit($hex) ? hex2bin($hex) : null;
    }
    if (!$create) {
        return null;
    }
    if (!is_dir(dirname($file)) && !@mkdir(dirname($file), 0775, true)) {
        return null;
    }
    $hex = bin2hex(random_bytes(32));
    if (@file_put_contents($file, $hex, LOCK_EX) === false) {
        return null;
    }
    @chmod($file, 0600);
    return hex2bin($hex);
}

function mail_settings_encrypt(string $plain): ?string
{
    $key = mail_settings_key(true);
    if ($key === null) {
        return null;
    }
    $iv = random_bytes(12);
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $cipher === false ? null : 'enc1:' . base64_encode($iv . $tag . $cipher);
}

function mail_settings_decrypt(string $stored): ?string
{
    if (strncmp($stored, 'enc1:', 5) !== 0) {
        return $stored;   // saved before encryption existed
    }
    $key = mail_settings_key(false);
    $raw = base64_decode(substr($stored, 5), true);
    if ($key === null || $raw === false || strlen($raw) < 29) {
        return null;
    }
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $plain === false ? null : $plain;
}

/** Saved values (password decrypted). Cached per request; call mail_settings_reset() after saving. */
function mail_settings_read(): array
{
    static $cache = null;
    if (func_num_args() === 0 && $cache !== null) {
        return $cache;
    }
    $out = [];
    $pdo = mail_settings_pdo();
    if ($pdo) {
        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'mail\\_%'");
            foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
                $field = substr((string) $k, 5);
                if (in_array($field, MAIL_SETTING_FIELDS, true) && $v !== null && $v !== '') {
                    $out[$field] = $field === 'password' ? mail_settings_decrypt((string) $v) : (string) $v;
                    if ($out[$field] === null) {
                        unset($out[$field]);
                        $out['password_unreadable'] = true;
                    }
                }
            }
        } catch (Throwable $e) {
            $out = [];
        }
    }
    $cache = $out;
    return $out;
}

function mail_settings_reset(): void
{
    mail_settings_read(true);   // any argument bypasses the cache and refills it
}

/** Called by config/mail.php: saved values win over .env defaults. */
function mail_settings_merge(array $cfg): array
{
    $saved = mail_settings_read();
    foreach (['host', 'encryption', 'username', 'password', 'from_email', 'from_name'] as $f) {
        if (isset($saved[$f]) && $saved[$f] !== '') {
            $cfg[$f] = $saved[$f];
        }
    }
    if (isset($saved['port']) && (int) $saved['port'] > 0) {
        $cfg['port'] = (int) $saved['port'];
    }
    if (isset($saved['enabled'])) {
        $on = $saved['enabled'] === '1';
        $cfg['enabled'] = $on;
        $cfg['password_reset_enabled'] = $on;
    }
    if (($cfg['encryption'] ?? '') === 'none') {
        $cfg['encryption'] = '';
    }
    return $cfg;
}

/**
 * Save settings. A blank password keeps the saved one. @return array{ok:bool, error?:string}
 */
function mail_settings_save(PDO $pdo, array $in): array
{
    $host = trim((string) ($in['host'] ?? ''));
    $port = (int) ($in['port'] ?? 0);
    $enc = (string) ($in['encryption'] ?? 'tls');
    $user = trim((string) ($in['username'] ?? ''));
    $fromEmail = trim((string) ($in['from_email'] ?? ''));
    $fromName = trim((string) ($in['from_name'] ?? ''));
    $pass = (string) ($in['password'] ?? '');

    if ($host === '' || !preg_match('/^[a-z0-9.\-]+$/i', $host)) {
        return ['ok' => false, 'error' => 'Enter the SMTP host, for example smtp.gmail.com.'];
    }
    if ($port < 1 || $port > 65535) {
        return ['ok' => false, 'error' => 'Enter a valid port (587 for TLS, 465 for SSL).'];
    }
    if (!in_array($enc, ['tls', 'ssl', 'none'], true)) {
        return ['ok' => false, 'error' => 'Choose an encryption type.'];
    }
    if ($user === '') {
        return ['ok' => false, 'error' => 'Enter the SMTP username (usually the full email address).'];
    }
    if ($fromEmail !== '' && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'The "from" email address is not valid.'];
    }
    if (mb_strlen($fromName) > 100) {
        return ['ok' => false, 'error' => 'The sender name is too long.'];
    }

    $values = [
        'mail_host' => $host, 'mail_port' => (string) $port, 'mail_encryption' => $enc, 'mail_username' => $user,
        'mail_from_email' => $fromEmail, 'mail_from_name' => $fromName, 'mail_enabled' => !empty($in['enabled']) ? '1' : '0',
    ];
    if ($pass !== '') {
        $enc = mail_settings_encrypt($pass);
        if ($enc === null) {
            return ['ok' => false, 'error' => 'Could not encrypt the password: the storage folder is not writable. Make app/v1/storage writable, or set MAIL_SECRET_KEY in .env.'];
        }
        $values['mail_password'] = $enc;
    }

    try {
        foreach ($values as $k => $v) {
            $chk = $pdo->prepare('SELECT 1 FROM site_settings WHERE setting_key = ?');
            $chk->execute([$k]);
            if ($chk->fetchColumn()) {
                $pdo->prepare('UPDATE site_settings SET setting_value = ? WHERE setting_key = ?')->execute([$v, $k]);
            } else {
                $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)')->execute([$k, $v]);
            }
        }
    } catch (Throwable $e) {
        error_log('mail_settings_save failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not save the settings.'];
    }
    mail_settings_reset();
    return ['ok' => true];
}
