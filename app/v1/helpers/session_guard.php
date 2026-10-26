<?php
// app/v1/helpers/session_guard.php — signed-in sessions end after 30 minutes of inactivity or when the
// last tab is closed (public/assets/js/session-guard.js reports both), and every sign-in starts clean.
// Students, tutors, interns and staff can be signed in on one device at a time; admins on several.

const SESSION_IDLE_LIMIT = 1800;   // seconds without activity before the session ends
const SESSION_CLOSE_GRACE = 15;   // seconds a "tab closed" report waits for a reload/next page before ending the session

// Only real page loads and form posts count as activity; background polling (fetch/XHR) does not.
function session_guard_is_activity(): bool {
    $mode = $_SERVER['HTTP_SEC_FETCH_MODE'] ?? '';
    if (preg_match('#/auth/keepalive(\.php)?$#', (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH))) return false;
    if ($mode !== '') {
        return $mode === 'navigate';
    }
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (preg_match('#/auth/keepalive(\.php)?$#', $path)) return false;   // the guard's own pings
    return strpos($path, '/api/') === false && empty($_SERVER['HTTP_X_REQUESTED_WITH']);
}

// Wipe the signed-in user but keep a fresh session running (CSRF tokens, flash messages).
function session_guard_end(bool $expired = true): void {
    $_SESSION = [];
    session_regenerate_id(true);
    if ($expired) {
        $_SESSION['session_expired'] = true;
    }
}

// Call right before writing a new user into the session, so nothing from a previous account survives.
function session_guard_login(): void {
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['last_activity'] = time();
}

function session_guard_check(): void {
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['logged_in'])) {
        return;
    }
    $now = time();
    $closedAt = (int) ($_SESSION['closing_at'] ?? 0);
    $last = (int) ($_SESSION['last_activity'] ?? $now);
    if (($closedAt && $now - $closedAt > SESSION_CLOSE_GRACE) || $now - $last > SESSION_IDLE_LIMIT) {
        session_guard_end();
        return;
    }
    unset($_SESSION['closing_at']);
    if (!session_guard_owns_account()) {
        session_guard_end(false);
        $_SESSION['session_replaced'] = true;
        return;
    }
    if (session_guard_is_activity() || !isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = $now;
    }
}

/** Admins (and the superadmin) may be signed in on several devices; everyone else on one. */
function session_guard_single_device(): bool {
    return (int) ($_SESSION['role_id'] ?? 0) > 2 && empty($_SESSION['preview']) && empty($_SESSION['tutor_admin']);
}

function session_guard_db(): ?PDO {
    try {
        require_once dirname(__DIR__) . '/models/Connection.php';
        return \Models\Connection::getInstance()->getConnection();
    } catch (Throwable $e) {
        return null;
    }
}

/** False when this account has since signed in on another device. */
function session_guard_owns_account(): bool {
    if (!session_guard_single_device() || !($db = session_guard_db())) return true;
    try {
        $st = $db->prepare('SELECT session_id FROM user_active_sessions WHERE user_id = ?');
        $st->execute([(int) $_SESSION['user_id']]);
        $sid = $st->fetchColumn();
        return $sid === false || hash_equals((string) $sid, session_id());
    } catch (Throwable $e) {   // before migration 078
        return true;
    }
}

/** Call after a successful sign-in (once the session id is final): this device becomes the account's only one. */
function session_guard_claim(): void {
    if (!session_guard_single_device() || !($db = session_guard_db())) return;
    try {
        $db->prepare('INSERT INTO user_active_sessions (user_id, session_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE session_id = VALUES(session_id)')
           ->execute([(int) $_SESSION['user_id'], session_id()]);
    } catch (Throwable $e) {
    }
}

// Adds the browser-side guard to every HTML page served to a signed-in user.
function session_guard_inject(string $html): string {
    if (empty($_SESSION['logged_in']) || stripos($html, '</body>') === false) {
        return $html;
    }
    foreach (headers_list() as $h) {
        if (stripos($h, 'content-type:') === 0 && stripos($h, 'text/html') === false) {
            return $html;
        }
    }
    // Each page gets its own token. A "tab closed" report only counts when it comes from the newest page, so a
    // late report from the page just left (e.g. after saving a blog post) can no longer sign the user out.
    $_SESSION['page_token'] = bin2hex(random_bytes(8));
    // In a portal preview the idle sign-out only ends the preview (the admin session lives on).
    $preview = !empty($_SESSION['preview']) ? (['DAZUPREVIEWT' => 'tutor', 'DAZUPREVIEWI' => 'intern'][session_name()] ?? 'students') : null;
    $cfg = htmlspecialchars(json_encode([
        'ping'   => url('auth/keepalive.php'),
        'logout' => $preview ? url($preview . '/preview-exit.php') : url('auth/logout.php?reason=idle'),
        'idle'   => SESSION_IDLE_LIMIT,
        'page'   => (string) ($_SESSION['page_token'] ?? ''),
    ]), ENT_QUOTES);
    $tag = '<script src="' . url('assets/js/session-guard.js') . '?v=' . @filemtime(dirname(__DIR__, 3) . '/public/assets/js/session-guard.js') . '" data-session-guard="' . $cfg . '" defer></script>';
    $pos = strripos($html, '</body>');
    return substr($html, 0, $pos) . $tag . substr($html, $pos);
}
