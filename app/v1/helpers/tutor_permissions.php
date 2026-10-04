<?php
// Tutor permissions: "Tutor portal only" or "Tutor + Admin portal".
// A tutor with admin rights signs in once and moves between both portals; Middleware switches their session's role as
// they go, and re-checks the grant on every admin page, so taking the rights away works immediately.

if (!function_exists('tutor_admin_allowed')) {
    /** True when this user is an active tutor whose admin rights are switched on. */
    function tutor_admin_allowed(PDO $db, int $userId): bool
    {
        if ($userId <= 0) return false;
        try {
            $st = $db->prepare(
                "SELECT 1 FROM users u JOIN roles r ON r.id = u.role_id JOIN tutors t ON t.user_id = u.id
                 WHERE u.id = ? AND u.is_active = 1 AND r.role_name = 'tutor' AND t.is_active = 1 AND t.can_admin = 1"
            );
            $st->execute([$userId]);
            return (bool) $st->fetchColumn();
        } catch (Throwable $e) {
            return false; // permissions not installed yet
        }
    }

    /** Only a real admin or the superadmin may change permissions (not a tutor who is using granted admin rights). */
    function tutor_permissions_can_manage(): bool
    {
        return empty($_SESSION['tutor_admin']) && in_array((int) ($_SESSION['role_id'] ?? 99), [1, 2], true);
    }

    /** @return array{ok:bool, message:string} */
    function tutor_permissions_set(PDO $db, int $tutorId, bool $canAdmin, int $byUserId): array
    {
        if (!tutor_permissions_can_manage()) {
            return ['ok' => false, 'message' => 'Only an admin or the superadmin can change permissions.'];
        }
        $st = $db->prepare("SELECT t.id, t.user_id, t.can_admin, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name,''), ' ', COALESCE(up.last_name,''))), ''), u.username) AS name
                            FROM tutors t JOIN users u ON u.id = t.user_id LEFT JOIN user_profiles up ON up.user_id = u.id WHERE t.id = ?");
        $st->execute([$tutorId]);
        $t = $st->fetch(PDO::FETCH_ASSOC);
        if (!$t) return ['ok' => false, 'message' => 'Tutor not found.'];
        if ((bool) $t['can_admin'] === $canAdmin) {
            return ['ok' => true, 'message' => 'No change.'];
        }
        $db->prepare("UPDATE tutors SET can_admin = ?, admin_granted_by = ?, admin_granted_at = " . ($canAdmin ? 'NOW()' : 'NULL') . " WHERE id = ?")
           ->execute([$canAdmin ? 1 : 0, $canAdmin ? $byUserId : null, $tutorId]);
        try {
            require_once __DIR__ . '/../services/PortalNotifications.php';
            PortalNotifications::notify($db, (int) $t['user_id'], 'permissions',
                $canAdmin ? 'You now have admin rights' : 'Your admin rights were removed',
                $canAdmin ? 'Use the "Admin portal" button in your tutor portal to open it.' : 'You can keep using your tutor portal as before.',
                'tutor/dashboard/index.php');
        } catch (Throwable $e) {
            // notifications are optional here
        }
        return ['ok' => true, 'message' => $t['name'] . ($canAdmin ? ' now has admin rights as well as the tutor portal.' : ' is now tutor-only.')];
    }
}
