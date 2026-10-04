<?php
// app/v1/services/PortalNotifications.php
// Notifications for the tutor and student portals: stored for the bell / unread count, and pushed to every device where the
// person installed the app and allowed notifications. Pushes go out after the response is sent, so pages never wait on them.

require_once __DIR__ . '/WebPush.php';

class PortalNotifications
{
    private static array $queue = [];
    private static bool $flushRegistered = false;

    /**
     * @param string      $path  app-relative link opened when the notification is tapped, e.g. 'students/course/index.php'
     * @param string|null $icon  app-relative picture (e.g. the tutor's photo); the app icon is used when null
     */
    public static function notify(PDO $db, int $userId, string $type, string $title, string $body = '', string $path = '', ?string $icon = null): void
    {
        if ($userId <= 0) return;
        // Older uploads were saved with the dev folder in front; keep only the path inside public/.
        $icon = $icon ? ltrim(preg_replace('#^/?Registration-System/public/#', '', $icon), '/') : null;
        try {
            $db->prepare("INSERT INTO portal_notifications (user_id, type, title, body, url, icon) VALUES (?, ?, ?, ?, ?, ?)")
               ->execute([$userId, $type, mb_substr($title, 0, 200), mb_substr($body, 0, 500), $path ?: null, $icon ?: null]);
            $id = (int) $db->lastInsertId();
        } catch (Throwable $e) {
            error_log('[PortalNotifications] ' . $e->getMessage());
            return;
        }
        self::$queue[] = [$db, $userId, $id, $title, $body, $path, $icon];
        if (!self::$flushRegistered) {
            self::$flushRegistered = true;
            register_shutdown_function([self::class, 'flush']);
        }
    }

    /** Notify every approved student of this course who is assigned to the tutor (tutor = users.id). */
    public static function courseStudents(PDO $db, int $tutorUserId, int $courseId, string $type, string $title, string $body, string $path, ?string $icon = null): void
    {
        try {
            $st = $db->prepare(
                "SELECT DISTINCT r.user_id FROM registrations r
                 JOIN tutor_students ts ON ts.user_id = r.user_id
                 JOIN tutors t ON t.id = ts.tutor_id AND t.user_id = ?
                 WHERE r.course_id = ? AND r.status = 'approved' AND r.user_id IS NOT NULL"
            );
            $st->execute([$tutorUserId, $courseId]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $uid) {
                self::notify($db, (int) $uid, $type, $title, $body, $path, $icon);
            }
        } catch (Throwable $e) {
            error_log('[PortalNotifications courseStudents] ' . $e->getMessage());
        }
    }

    /** A student was assigned to a tutor (tutors.id): tell both of them. */
    public static function assignmentNotices(PDO $db, int $tutorId, array $studentUserIds): void
    {
        try {
            $st = $db->prepare("SELECT user_id FROM tutors WHERE id = ?");
            $st->execute([$tutorId]);
            $tutorUserId = (int) $st->fetchColumn();
            $info = $db->prepare("SELECT r.full_name, r.id, c.title FROM registrations r JOIN courses c ON c.id = r.course_id
                                  WHERE r.user_id = ? AND r.status = 'approved' ORDER BY r.id DESC LIMIT 1");
            foreach ($studentUserIds as $uid) {
                $info->execute([(int) $uid]);
                $r = $info->fetch(PDO::FETCH_ASSOC);
                if (!$r || str_starts_with((string) $r['full_name'], 'Preview ')) continue;
                self::notify($db, $tutorUserId, 'student_assigned', 'New student assigned: ' . $r['full_name'], $r['title'],
                    'tutor/students/view.php?id=' . (int) $r['id'], self::photoOf($db, (int) $uid));
                self::notify($db, (int) $uid, 'tutor_assigned', 'You have been assigned a tutor', 'Open your course to see your modules.',
                    'students/course/index.php', self::photoOf($db, $tutorUserId));
            }
        } catch (Throwable $e) {
            error_log('[PortalNotifications assignmentNotices] ' . $e->getMessage());
        }
    }

    /** Profile picture of a person (tutor photo first, then their passport photo), for the notification picture. */
    public static function photoOf(PDO $db, int $userId): ?string
    {
        try {
            $st = $db->prepare(
                "SELECT COALESCE(NULLIF(t.photo_path, ''), NULLIF(up.profile_photo_path, '')) FROM users u
                 LEFT JOIN tutors t ON t.user_id = u.id LEFT JOIN user_profiles up ON up.user_id = u.id WHERE u.id = ?"
            );
            $st->execute([$userId]);
            return $st->fetchColumn() ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** Send queued pushes once the page has been delivered to the browser. */
    public static function flush(): void
    {
        if (!self::$queue || !WebPush::supported()) return;
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            @litespeed_finish_request();
        }
        foreach (self::$queue as [$db, $userId, $id, $title, $body, $path, $icon]) {
            try {
                self::pushNow($db, $userId, $id, $title, $body, $path, $icon);
            } catch (Throwable $e) {
                error_log('[PortalNotifications push] ' . $e->getMessage());
            }
        }
        self::$queue = [];
    }

    private static function pushNow(PDO $db, int $userId, int $id, string $title, string $body, string $path, ?string $icon): void
    {
        $st = $db->prepare("SELECT id, endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?");
        $st->execute([$userId]);
        $subs = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!$subs) return;
        $keys = WebPush::vapidKeys($db);
        $payload = json_encode([
            'id' => $id,
            'title' => $title,
            'body' => $body,
            'url' => self::absolute($path ?: ''),
            'icon' => self::absolute($icon ?: 'assets/icons/icon-192.png'),
            'badge_count' => self::unreadCount($db, $userId),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $subject = 'mailto:' . (filter_var($_SERVER['SERVER_ADMIN'] ?? '', FILTER_VALIDATE_EMAIL) ?: 'notifications@' . preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost.localdomain'));
        foreach ($subs as $s) {
            $code = WebPush::send($s, $payload, $keys, $subject);
            if ($code === 404 || $code === 410) {
                $db->prepare("DELETE FROM push_subscriptions WHERE id = ?")->execute([$s['id']]); // device unsubscribed
            }
        }
    }

    public static function absolute(string $path): string
    {
        if (preg_match('#^https?://#i', $path)) return $path;
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http';
        $rel = function_exists('url') ? url(ltrim($path, '/')) : '/' . ltrim($path, '/');
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $rel;
    }

    public static function unreadCount(PDO $db, int $userId): int
    {
        try {
            $st = $db->prepare("SELECT COUNT(*) FROM portal_notifications WHERE user_id = ? AND is_read = 0");
            $st->execute([$userId]);
            return (int) $st->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    public static function latest(PDO $db, int $userId, int $limit = 20, int $offset = 0): array
    {
        $st = $db->prepare("SELECT * FROM portal_notifications WHERE user_id = ? ORDER BY id DESC LIMIT " . max(1, $limit) . " OFFSET " . max(0, $offset));
        $st->execute([$userId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function markRead(PDO $db, int $userId, int $id): ?array
    {
        $st = $db->prepare("SELECT * FROM portal_notifications WHERE id = ? AND user_id = ?");
        $st->execute([$id, $userId]);
        $n = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($n) $db->prepare("UPDATE portal_notifications SET is_read = 1 WHERE id = ?")->execute([$id]);
        return $n;
    }

    public static function markAllRead(PDO $db, int $userId): void
    {
        $db->prepare("UPDATE portal_notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$userId]);
    }

    public static function saveSubscription(PDO $db, int $userId, array $sub): bool
    {
        $endpoint = (string) ($sub['endpoint'] ?? '');
        $p256dh = (string) ($sub['keys']['p256dh'] ?? '');
        $auth = (string) ($sub['keys']['auth'] ?? '');
        if (!preg_match('#^https://#', $endpoint) || strlen(WebPush::b64urlDecode($p256dh)) !== 65 || strlen(WebPush::b64urlDecode($auth)) < 16) {
            return false;
        }
        // One device belongs to whoever signed in on it last.
        $db->prepare(
            "INSERT INTO push_subscriptions (user_id, endpoint, endpoint_hash, p256dh, auth) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), p256dh = VALUES(p256dh), auth = VALUES(auth)"
        )->execute([$userId, $endpoint, hash('sha256', $endpoint), $p256dh, $auth]);
        return true;
    }

    public static function deleteSubscription(PDO $db, int $userId, string $endpoint): void
    {
        $db->prepare("DELETE FROM push_subscriptions WHERE endpoint_hash = ? AND user_id = ?")->execute([hash('sha256', $endpoint), $userId]);
    }

    /** Small "5 min ago" label for the bell list. */
    public static function ago(string $ts): string
    {
        $d = time() - strtotime($ts);
        if ($d < 60) return 'just now';
        if ($d < 3600) return floor($d / 60) . ' min ago';
        if ($d < 86400) return floor($d / 3600) . ' h ago';
        if ($d < 604800) return floor($d / 86400) . ' d ago';
        return date('d M Y', strtotime($ts));
    }
}
