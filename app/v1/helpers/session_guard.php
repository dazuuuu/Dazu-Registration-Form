<?php
// app/v1/helpers/session_guard.php — signed-in sessions end after 5 minutes of inactivity or when the
// last tab is closed (public/assets/js/session-guard.js reports both), and every sign-in starts clean.

const SESSION_IDLE_LIMIT = 300;   // seconds without activity before the session ends
const SESSION_CLOSE_GRACE = 15;   // seconds a "tab closed" report waits for a reload/next page before ending the session

// Only real page loads and form posts count as activity; background polling (fetch/XHR) does not.
function session_guard_is_activity(): bool {
    $mode = $_SERVER['HTTP_SEC_FETCH_MODE'] ?? '';
    if ($mode !== '') {
        return $mode === 'navigate';
    }
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
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
    if (session_guard_is_activity() || !isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = $now;
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
    // In a portal preview the idle sign-out only ends the preview (the admin session lives on).
    $preview = !empty($_SESSION['preview']) ? (['DAZUPREVIEWT' => 'tutor', 'DAZUPREVIEWI' => 'intern'][session_name()] ?? 'students') : null;
    $cfg = htmlspecialchars(json_encode([
        'ping'   => url('auth/keepalive.php'),
        'logout' => $preview ? url($preview . '/preview-exit.php') : url('auth/logout.php?reason=idle'),
        'idle'   => SESSION_IDLE_LIMIT,
    ]), ENT_QUOTES);
    $tag = '<script src="' . url('assets/js/session-guard.js') . '" data-session-guard="' . $cfg . '" defer></script>';
    $pos = strripos($html, '</body>');
    return substr($html, 0, $pos) . $tag . substr($html, $pos);
}
