<?php
// app/v1/helpers/superadmin_pin.php
// The superadmin PIN that unlocks the sensitive figures on the dashboard.
//  - Until a PIN is saved the default (111333) works. A saved PIN is stored only as a bcrypt hash.
//  - Unlocking lasts SA_PIN_TTL seconds (per session). While locked, the dashboard never receives the real figures.
//  - Wrong guesses are throttled: 5 misses lock PIN entry for 5 minutes.

if (!defined('SA_DEFAULT_PIN')) define('SA_DEFAULT_PIN', '111333');
if (!defined('SA_PIN_TTL')) define('SA_PIN_TTL', 600);

function sa_pin_key(int $userId): string { return 'sa_pin_hash_' . $userId; }

function sa_pin_hash(PDO $pdo, int $userId): ?string
{
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ?');
        $stmt->execute([sa_pin_key($userId)]);
        $v = $stmt->fetchColumn();
        return ($v !== false && $v !== null && $v !== '') ? (string) $v : null;
    } catch (Throwable $e) {
        return null;
    }
}

function sa_pin_is_default(PDO $pdo, int $userId): bool
{
    return sa_pin_hash($pdo, $userId) === null;
}

function sa_pin_valid_format(string $pin): bool
{
    return (bool) preg_match('/^\d{4,8}$/', $pin);
}

function sa_pin_verify(PDO $pdo, int $userId, string $pin): bool
{
    $hash = sa_pin_hash($pdo, $userId);
    return $hash === null ? hash_equals(SA_DEFAULT_PIN, $pin) : password_verify($pin, $hash);
}

/** Save a new PIN (null resets to the default). */
function sa_pin_set(PDO $pdo, int $userId, ?string $pin): bool
{
    $value = $pin === null ? null : password_hash($pin, PASSWORD_DEFAULT);
    $key = sa_pin_key($userId);
    try {
        $chk = $pdo->prepare('SELECT 1 FROM site_settings WHERE setting_key = ?');
        $chk->execute([$key]);
        if ($chk->fetchColumn()) {
            return $pdo->prepare('UPDATE site_settings SET setting_value = ? WHERE setting_key = ?')->execute([$value, $key]);
        }
        return $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)')->execute([$key, $value]);
    } catch (Throwable $e) {
        error_log('sa_pin_set failed: ' . $e->getMessage());
        return false;
    }
}

function sa_pin_unlocked(): bool
{
    return (int) ($_SESSION['sa_pin_until'] ?? 0) > time();
}

function sa_pin_seconds_left(): int
{
    return max(0, (int) ($_SESSION['sa_pin_until'] ?? 0) - time());
}

function sa_pin_unlock(): void
{
    $_SESSION['sa_pin_until'] = time() + SA_PIN_TTL;
    unset($_SESSION['sa_pin_fail'], $_SESSION['sa_pin_block_until']);
}

function sa_pin_lock(): void
{
    unset($_SESSION['sa_pin_until']);
}

/** Seconds PIN entry is blocked for (0 = allowed). */
function sa_pin_blocked_for(): int
{
    return max(0, (int) ($_SESSION['sa_pin_block_until'] ?? 0) - time());
}

function sa_pin_register_miss(): void
{
    $_SESSION['sa_pin_fail'] = (int) ($_SESSION['sa_pin_fail'] ?? 0) + 1;
    if ($_SESSION['sa_pin_fail'] >= 5) {
        $_SESSION['sa_pin_block_until'] = time() + 300;
        $_SESSION['sa_pin_fail'] = 0;
    }
}
