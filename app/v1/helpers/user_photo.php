<?php
// The real photo of any user, for avatars everywhere (portal headers, notification icons, lists).
// Tutors' ID photos live in private storage, so photos are always served through public/profile/photo.php, which checks
// who may see whom. user_photo_url() returns null when the person has no photo, and the page shows initials instead.
require_once __DIR__ . '/../services/IdCardService.php';

if (!function_exists('user_photo_url')) {
    /** Photo URL for a user (cache-busted when the photo changes), or null when they have none. */
    function user_photo_url(PDO $db, int $userId): ?string
    {
        static $cache = [];
        if ($userId <= 0) return null;
        if (!array_key_exists($userId, $cache)) {
            $abs = idc_student_photo_file($db, $userId);
            $cache[$userId] = $abs ? url('profile/photo.php?u=' . $userId . '&v=' . substr(md5($abs . '|' . @filemtime($abs)), 0, 10)) : null;
        }
        return $cache[$userId];
    }

    /** May the signed-in user ($viewer, role id $roleId) see $target's photo? */
    function user_photo_allowed(PDO $db, int $viewer, int $roleId, int $target): bool
    {
        if ($viewer <= 0) return false;
        if ($viewer === $target || $roleId <= 2 || !empty($_SESSION['tutor_admin'])) return true;
        // Tutors are staff: their photo may be shown to anyone signed in (it is on their ID card too).
        $st = $db->prepare("SELECT 1 FROM tutors WHERE user_id = ? AND is_active = 1");
        $st->execute([$target]);
        if ($st->fetchColumn()) return true;
        // A tutor may see their own students' photos.
        $st = $db->prepare("SELECT 1 FROM tutor_students ts JOIN tutors t ON t.id = ts.tutor_id WHERE t.user_id = ? AND ts.user_id = ?");
        $st->execute([$viewer, $target]);
        return (bool) $st->fetchColumn();
    }
}
