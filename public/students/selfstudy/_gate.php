<?php
// Shared start for the Self-study pages: a student whose portal is locked for fees is sent to the dashboard, like the course lessons.
// Expects $pdo and $uid.
try {
    $st = $pdo->prepare("SELECT portal_locked FROM registrations WHERE user_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT 1");
    $st->execute([$uid]);
    if ((bool) $st->fetchColumn()) {
        header('Location: ' . url('students/dashboard/index.php'));
        exit();
    }
} catch (Throwable $e) {
    // The fee flag is not available: do not block the page.
}
