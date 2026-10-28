<?php
// Every user's own profile photo (user_profiles.profile_photo_path): change it or delete it, from any portal.
// pp_handle() runs at the top of a profile page (before any output); components/profile/photo_card.php draws the card.
// Deleting also removes a tutor's private ID photo, so the photo is gone everywhere (avatars, ID card, verify page).
require_once __DIR__ . '/uploads.php';
require_once __DIR__ . '/user_photo.php';

if (!function_exists('pp_handle')) {
    /** Handle a pp_upload / pp_delete POST for the signed-in user. Returns null when the request was not for this card. */
    function pp_handle(PDO $db, int $uid, string $back): ?array
    {
        $action = $_POST['pp_action'] ?? '';
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !in_array($action, ['upload', 'delete'], true) || $uid <= 0) return null;
        if (!csrf_verify($_POST['csrf_token'] ?? null)) $res = ['error', 'Security check failed. Please refresh and try again.'];
        elseif ($action === 'delete') $res = pp_delete($db, $uid);
        else $res = pp_upload($db, $uid, $_FILES['pp_photo'] ?? []);
        $_SESSION['pp_flash'] = $res;
        header('Location: ' . $back);
        exit;
    }

    function pp_upload(PDO $db, int $uid, array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['error', 'Choose a photo first.'];
        $dir = dirname(__DIR__, 3) . '/public/uploads/profile_photos';
        $up = upload_image($file, $dir, 'uploads/profile_photos', ['jpg', 'jpeg', 'png', 'webp']);
        if (!$up['ok']) return ['error', $up['error'] ?? 'The photo could not be saved.'];
        $old = pp_current_file($db, $uid);
        // Students' photos go on their ID card, so the office checks them first; everyone else's show straight away.
        $role = (string) ($_SESSION['role'] ?? '');
        $status = $role === 'student' || $role === 'user' ? 'pending' : 'approved';
        $db->prepare("INSERT INTO user_profiles (user_id, profile_photo_path, profile_photo_submitted_at, profile_photo_status) VALUES (?, ?, NOW(), ?)
                      ON DUPLICATE KEY UPDATE profile_photo_path = VALUES(profile_photo_path), profile_photo_submitted_at = NOW(), profile_photo_status = VALUES(profile_photo_status)")
           ->execute([$uid, $up['path'], $status]);
        if ($old) @unlink($old);
        return ['success', $status === 'pending' ? 'Photo saved. The office will check it for your ID card.' : 'Photo saved.'];
    }

    function pp_delete(PDO $db, int $uid): array
    {
        $old = pp_current_file($db, $uid);
        try {
            $db->prepare("UPDATE user_profiles SET profile_photo_path = NULL, profile_photo_submitted_at = NULL, profile_photo_status = 'none' WHERE user_id = ?")->execute([$uid]);
        } catch (Throwable $e) {
            // Older tables have no 'none' status: the empty path is what counts.
            $db->prepare("UPDATE user_profiles SET profile_photo_path = NULL, profile_photo_submitted_at = NULL WHERE user_id = ?")->execute([$uid]);
        }
        if ($old) @unlink($old);
        try {
            $st = $db->prepare('SELECT id_photo_path FROM tutors WHERE user_id = ?');
            $st->execute([$uid]);
            $p = (string) $st->fetchColumn();
            if (str_starts_with($p, 'storage:tutor_photos/')) {
                $abs = realpath(storage_path(substr($p, 8)));
                $base = realpath(storage_path('tutor_photos'));
                if ($abs && $base && str_starts_with($abs, $base . DIRECTORY_SEPARATOR)) @unlink($abs);
            }
            $db->prepare('UPDATE tutors SET id_photo_path = NULL WHERE user_id = ?')->execute([$uid]);
        } catch (Throwable $e) { /* not a tutor, or migration 082 not run */ }
        return ['success', 'Your photo was deleted.'];
    }

    /** The public uploads file of the user's profile photo, or null. */
    function pp_current_file(PDO $db, int $uid): ?string
    {
        $st = $db->prepare('SELECT profile_photo_path FROM user_profiles WHERE user_id = ?');
        $st->execute([$uid]);
        $rel = ltrim(preg_replace('#^/?Registration-System/public/#', '', (string) $st->fetchColumn()), '/');
        if (!str_starts_with($rel, 'uploads/profile_photos/')) return null;
        $abs = dirname(__DIR__, 3) . '/public/' . $rel;
        return is_file($abs) ? $abs : null;
    }
}
