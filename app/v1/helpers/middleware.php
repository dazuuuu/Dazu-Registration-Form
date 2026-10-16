<?php
// app/helpers/middleware.php

// Include Session model
require_once __DIR__ . '/../models/Session.php';

class Middleware {
    
    /**
     * Require user to be logged in
     */
    public static function requireAuth() {
        SessionModel::start();
        
        if (!SessionModel::has('logged_in') || SessionModel::get('logged_in') !== true) {
            header('Location: ' . url('auth/login.php'));
            exit();
        }
    }
    
    /**
     * Require user to be NOT logged in (for login/register pages)
     */
    public static function requireGuest() {
        SessionModel::start();
        
        if (SessionModel::has('logged_in') && SessionModel::get('logged_in') === true) {
            $role = SessionModel::get('role');
            if ($role === 'superadmin') {
                header('Location: ' . url('superadmin/dashboard.php'));
            } elseif ($role === 'admin') {
                header('Location: ' . url('admin/dashboard.php'));
            } elseif ($role === 'employee') {
                header('Location: ' . url('employee/dashboard/index.php'));
            } elseif ($role === 'intern') {
                header('Location: ' . url('intern/dashboard/index.php'));
            } else {
                header('Location: ' . url('profile/'));
            }
            exit();
        }
    }
    
    /**
     * Require tutor role access
     */
    public static function requireTutor() {
        self::requireAuth();
        if (SessionModel::get('role') !== 'tutor') {
            // A tutor using granted admin rights keeps one session for both portals. If the rights were taken away
            // meanwhile, the session goes back to plain tutor.
            if (SessionModel::get('tutor_admin')) {
                require_once __DIR__ . '/tutor_permissions.php';
                require_once __DIR__ . '/../models/Connection.php';
                if (!tutor_admin_allowed(\Models\Connection::getInstance()->getConnection(), (int) SessionModel::get('user_id', 0))) {
                    self::enterTutorMode();
                    SessionModel::set('tutor_admin', false);
                }
                return;
            }
            header('Location: ' . url('auth/login.php?error=access_denied'));
            exit();
        }
    }

    /**
     * Require the superadmin (role_id 1) and send everyone else to the superadmin login page.
     */
    public static function requireSuperPortal() {
        SessionModel::start();
        if (!SessionModel::has('logged_in') || SessionModel::get('logged_in') !== true || (int) SessionModel::get('role_id') !== 1) {
            header('Location: ' . url('superadmin/login.php'));
            exit();
        }
        self::refreshSessionFromDatabase();
        if ((int) SessionModel::get('role_id') !== 1) {
            header('Location: ' . url('superadmin/login.php'));
            exit();
        }
    }

    /**
     * Require intern role access (intern portal)
     */
    public static function requireIntern() {
        self::requireAuth();
        if (SessionModel::get('role') !== 'intern') {
            header('Location: ' . url('auth/login.php?error=access_denied'));
            exit();
        }
    }

    /**
     * Require employee role access
     */
    public static function requireEmployee() {
        self::requireAuth();
        if (SessionModel::get('role') !== 'employee') {
            header('Location: ' . url('auth/login.php?error=access_denied'));
            exit();
        }
    }

    /**
     * Require student role access
     */
    public static function requireStudent() {
        self::requireAuth();

        $role = SessionModel::get('role');
        if ($role !== 'student') {
            header('Location: ' . url('auth/login.php?error=access_denied'));
            exit();
        }
    }

    /**
     * Require admin access (admin or superadmin)
     */
    public static function requireAdmin() {
        self::requireAuth();
        
        $roleId = SessionModel::get('role_id');

        // The superadmin has a separate portal. Admin pages are for admins only.
        if ((int) $roleId === 1) {
            header('Location: ' . url('superadmin/dashboard.php'));
            exit();
        }
        
        // role_id 1 = superadmin, 2 = admin, 3 = user
        if ($roleId > 2) {
            // A tutor who has been given admin rights may use the admin portal too.
            require_once __DIR__ . '/tutor_permissions.php';
            require_once __DIR__ . '/../models/Connection.php';
            if (SessionModel::get('role') === 'tutor'
                && tutor_admin_allowed(\Models\Connection::getInstance()->getConnection(), (int) SessionModel::get('user_id', 0))) {
                self::enterAdminMode();
            } else {
                header('Location: ' . url('auth/login.php?error=access_denied'));
                exit();
            }
        }

        self::refreshSessionFromDatabase();
    }

    /** Session of a tutor with admin rights while they are in the admin portal (every admin check then just works). */
    private static function enterAdminMode(): void {
        SessionModel::set('tutor_admin', true);
        SessionModel::set('role', 'admin');
        SessionModel::set('role_id', 2);
    }

    /** Back to the tutor portal: their real tutor role. */
    private static function enterTutorMode(): void {
        require_once __DIR__ . '/../models/Connection.php';
        $pdo = \Models\Connection::getInstance()->getConnection();
        $st = $pdo->prepare("SELECT u.role_id, r.role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?");
        $st->execute([(int) SessionModel::get('user_id', 0)]);
        $u = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$u || $u['role_name'] !== 'tutor') {
            self::logoutRedirect('session_expired');
        }
        SessionModel::set('role', 'tutor');
        SessionModel::set('role_id', (int) $u['role_id']);
    }

    /**
     * Ensure session user still exists (common break after DB re-import on cPanel).
     */
    private static function refreshSessionFromDatabase(): void {
        $userId = (int) SessionModel::get('user_id', 0);
        if ($userId <= 0) {
            self::logoutRedirect('session_expired');
        }

        require_once __DIR__ . '/../models/Connection.php';
        $pdo = \Models\Connection::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            "SELECT u.id, u.username, u.email, u.role_id, u.is_active, r.role_name
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($user && (int) $user['is_active'] && (int) $user['role_id'] > 2 && SessionModel::get('tutor_admin')) {
            require_once __DIR__ . '/tutor_permissions.php';
            if (tutor_admin_allowed($pdo, (int) $user['id'])) {
                // Tutor using granted admin rights: keep the admin role for this portal, refresh the rest.
                SessionModel::set('username', $user['username']);
                SessionModel::set('email', $user['email']);
                return;
            }
            // Rights were taken away: back to their tutor portal (not signed out).
            self::enterTutorMode();
            SessionModel::set('tutor_admin', false);
            SessionModel::setFlash('error', 'Your admin rights have been removed. You can keep using your tutor portal.');
            header('Location: ' . url('tutor/dashboard/index.php'));
            exit();
        }
        if (!$user || !(int) $user['is_active'] || (int) $user['role_id'] > 2) {
            self::logoutRedirect('session_expired');
        }

        SessionModel::set('user_id', (int) $user['id']);
        SessionModel::set('role_id', (int) $user['role_id']);
        SessionModel::set('role', $user['role_name']);
        SessionModel::set('username', $user['username']);
        SessionModel::set('email', $user['email']);
    }

    private static function logoutRedirect(string $error): void {
        SessionModel::destroy();
        header('Location: ' . url('auth/login.php?error=' . rawurlencode($error)));
        exit();
    }
    
    /**
     * Require superadmin only access
     */
    public static function requireSuperAdmin() {
        self::requireAuth();
        
        $roleId = SessionModel::get('role_id');
        
        if ($roleId !== 1) {
            header('Location: ' . url('admin/dashboard.php?error=unauthorized'));
            exit();
        }
    }
    
    /**
     * Check if current user has specific role
     */
    public static function hasRole($roleName) {
        SessionModel::start();
        $userRole = SessionModel::get('role');
        return $userRole === $roleName;
    }
    
    /**
     * Check if current user is admin (including superadmin)
     */
    public static function isAdmin() {
        SessionModel::start();
        $roleId = SessionModel::get('role_id');
        return $roleId <= 2;
    }
    
    /**
     * Check if current user is superadmin
     */
    public static function isSuperAdmin() {
        SessionModel::start();
        $roleId = SessionModel::get('role_id');
        return $roleId === 1;
    }
    
    /**
     * Get current user's role
     */
    public static function getCurrentRole() {
        SessionModel::start();
        return SessionModel::get('role');
    }
    
    /**
     * Get current user's role ID
     */
    public static function getCurrentRoleId() {
        SessionModel::start();
        return SessionModel::get('role_id');
    }
}
?>