<?php
/**
 * Secretary role: what she can switch on, how to read her record, and the server-side guard every secretary page calls.
 * The guard re-reads the database on EVERY request (role, active login, status and switch), so a hidden menu item is never the only protection.
 */
require_once __DIR__ . '/../models/Session.php';
require_once __DIR__ . '/middleware.php';

if (!function_exists('secretary_switch_defs')) {
    /**
     * The switches, in the order the admin form shows them: key => [label, help, options (value => label), default].
     * Two options = an on/off switch; three = a choice (Staff Calendar).
     */
    function secretary_switch_defs(): array
    {
        $onOff = ['on' => 'On', 'off' => 'Off'];
        return [
            'blogs_approval' => ['Blogs approval', 'On: the admin must approve her blog posts before they are published.', $onOff, 'on'],
            'blogs'          => ['Blogs', 'She can write blog posts.', $onOff, 'on'],
            'staff_calendar' => ['Staff Calendar', 'Full: the same as a tutor. View only: she sees events and adds her own birthday. Off: no access.', ['off' => 'Off', 'view' => 'View only', 'edit' => 'Full'], 'view'],
            'enquiries'      => ['Enquiries log', 'She can use the enquiries log.', $onOff, 'on'],
            'tasks'          => ['My Tasks', 'She can see and tick off her tasks.', $onOff, 'on'],
            'room_visitors'  => ['Room & Visitors', 'She can use the room and visitors page.', $onOff, 'on'],
            'id_card'        => ['My ID Card', 'She can open and download her staff ID card.', $onOff, 'off'],
        ];
    }

    function secretary_branches(): array
    {
        return ['nairobi' => 'Nairobi', 'thika' => 'Thika', 'online' => 'Online'];
    }

    function secretary_statuses(): array
    {
        return ['active' => 'Active', 'on_leave' => 'On leave', 'left' => 'Left'];
    }

    /** Her record by users.id, with her name, email and the role name of her login; null when the user has no secretary record. */
    function secretary_by_user(PDO $db, int $userId): ?array
    {
        $st = $db->prepare(
            "SELECT s.*, u.email, u.is_active AS user_active, u.must_change_password, r.role_name,
                    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name,''), ' ', COALESCE(up.last_name,''))), ''), u.username) AS full_name
             FROM secretaries s
             JOIN users u ON u.id = s.user_id
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE s.user_id = ?"
        );
        $st->execute([$userId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** All her switches as key => value. A switch with no row is off, and an unknown value is off. */
    function secretary_switches(PDO $db, int $secretaryId): array
    {
        $defs = secretary_switch_defs();
        $out = array_fill_keys(array_keys($defs), 'off');
        $st = $db->prepare('SELECT switch_key, switch_value FROM secretary_switches WHERE secretary_id = ?');
        $st->execute([$secretaryId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (isset($defs[$r['switch_key']]) && isset($defs[$r['switch_key']][2][$r['switch_value']])) {
                $out[$r['switch_key']] = $r['switch_value'];
            }
        }
        return $out;
    }

    /** Is the switch at least $need? For on/off switches 'on'; for the calendar 'view' (view or edit) or 'edit'. */
    function secretary_switch_allows(string $value, string $need): bool
    {
        if ($need === 'edit') return $value === 'edit';
        return $value !== 'off' && $value !== '';
    }

    /** The guard. Call first on every secretary page and action. Returns her record; sends everyone else away. */
    function secretary_require(?string $switch = null, string $need = 'on'): array
    {
        Middleware::requireAuth();   // signed in, idle and one-device checks
        $db = \Models\Connection::getInstance()->getConnection();
        $sec = secretary_by_user($db, (int) SessionModel::get('user_id', 0));
        if (!$sec || $sec['role_name'] !== 'secretary' || empty($sec['user_active']) || $sec['status'] === 'left') {   // On leave does NOT block her; only Left (and a suspension, which switches the login off) does
            if ($sec && $sec['role_name'] === 'secretary') SessionModel::destroy();   // a secretary who lost access is signed out; anyone else is only sent away and keeps their own session
            header('Location: ' . url('auth/login.php?error=access_denied'));
            exit();
        }
        SessionModel::set('role', 'secretary');   // the session follows the database, not the other way round
        SessionModel::set('role_id', (int) $db->query("SELECT id FROM roles WHERE role_name = 'secretary'")->fetchColumn());
        if (!empty($sec['must_change_password'])) {
            header('Location: ' . url('auth/change_password.php'));
            exit();
        }
        $sec['switches'] = secretary_switches($db, (int) $sec['id']);
        if ($switch !== null && !secretary_switch_allows($sec['switches'][$switch] ?? 'off', $need)) {
            http_response_code(403);
            exit('You do not have access to this page. Ask the admin to switch it on for you.');
        }
        return $sec;
    }

    /**
     * The guard for EVERY admin page and action about secretaries (the list, add, edit, switches, reset password, and later blog approvals, secretary tasks, the enquiries page and the activity record).
     * Only a signed-in, active user whose role in the database is exactly "admin" gets through. The super admin, a tutor (even one with admin rights), a secretary, a student and anyone
     * signed out all get a plain 403 "Not allowed.". The role is read from the database on every request, never from the session alone. Returns the admin's users.id.
     */
    function secretary_admin_require(): int
    {
        SessionModel::start();
        $deny = static function (): void { http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); echo 'Not allowed.'; exit; };
        if (SessionModel::get('logged_in') !== true || (int) SessionModel::get('user_id', 0) <= 0) $deny();
        Middleware::requireAuth();   // idle and one-device checks for a signed-in person
        require_once __DIR__ . '/../models/Connection.php';
        $st = \Models\Connection::getInstance()->getConnection()->prepare('SELECT u.is_active, r.role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?');
        $st->execute([(int) SessionModel::get('user_id', 0)]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r || $r['role_name'] !== 'admin' || empty($r['is_active'])) $deny();
        return (int) SessionModel::get('user_id', 0);
    }

    /** The name shown for a person: first and last name, else the username. Name only, never the role. */
    function secretary_person_name(PDO $db, int $userId): string
    {
        $st = $db->prepare("SELECT COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name,''), ' ', COALESCE(up.last_name,''))), ''), u.username) FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id WHERE u.id = ?");
        $st->execute([$userId]);
        return (string) ($st->fetchColumn() ?: '');
    }
}
