<?php
/**
 * Announcements (tables announcements + announcement_recipients, migration 073).
 *
 * Who can send to whom (each as "all of them" or "one person", picked from the system):
 *   superadmin → students, tutors, interns or admins      admin → students, tutors, interns or the superadmin
 *   tutor      → their assigned students, admins or the superadmin      intern → nobody (interns never contact students)
 * "All students" can be narrowed to one course. Each recipient gets: a full-screen pop-up on their next portal page
 * (components/announcements/overlay.php), a bell notification + phone push (PortalNotifications), and an email.
 * When expires_at passes, ann_purge_expired() deletes the announcement, its recipients, its notifications and its
 * uploaded file — nothing is kept.
 */
require_once __DIR__ . '/PortalNotifications.php';

// audience key => [label, person kind, single?].
const ANN_AUDIENCES = [
    'students' => ['All students', 'student', false], 'student' => ['One student', 'student', true],
    'tutors'   => ['All tutors', 'tutor', false],     'tutor'   => ['One tutor', 'tutor', true],
    'interns'  => ['All interns', 'intern', false],   'intern'  => ['One intern', 'intern', true],
    'admins'   => ['All admins', 'admin', false],     'admin'   => ['One admin', 'admin', true],
    'superadmins' => ['The superadmin', 'superadmin', false],
];
const ANN_PORTAL = ['student' => 'students', 'tutor' => 'tutor', 'intern' => 'intern', 'admin' => 'admin', 'superadmin' => 'superadmin'];
const ANN_UPLOAD_TYPES = [
    'image/jpeg' => ['image', 'jpg'], 'image/png' => ['image', 'png'], 'image/webp' => ['image', 'webp'], 'image/gif' => ['image', 'gif'],
    'audio/mpeg' => ['audio', 'mp3'], 'audio/mp4' => ['audio', 'm4a'], 'audio/x-m4a' => ['audio', 'm4a'], 'audio/aac' => ['audio', 'aac'],
    'audio/ogg' => ['audio', 'ogg'], 'audio/wav' => ['audio', 'wav'], 'audio/x-wav' => ['audio', 'wav'],
    'video/mp4' => ['video', 'mp4'], 'video/webm' => ['video', 'webm'], 'video/quicktime' => ['video', 'mov'],
];
const ANN_MAX_UPLOAD = 40 * 1024 * 1024;

if (!function_exists('ann_audiences_for')) {
    /** Audiences this sender may pick ('superadmin' | 'admin' | 'tutor'): [key => label]. Anyone else: none. */
    function ann_audiences_for(string $role): array
    {
        $kinds = [
            'superadmin' => ['student', 'tutor', 'intern', 'admin'],
            'admin'      => ['student', 'tutor', 'intern', 'superadmin'],
            'tutor'      => ['student', 'admin', 'superadmin'],   // their own students, and the office
        ][$role] ?? [];
        $out = [];
        foreach (ANN_AUDIENCES as $k => [$label, $kind]) {
            if (in_array($kind, $kinds, true)) $out[$k] = $label;
        }
        return $out;
    }

    /** People of one kind this sender may pick for a one-person announcement: [[id, name, sub]]. */
    function ann_people(PDO $db, array $sender, string $kind): array
    {
        $plural = ['student' => 'students', 'tutor' => 'tutors', 'intern' => 'interns', 'admin' => 'admins'][$kind] ?? null;
        if (!$plural || !isset(ann_audiences_for($sender['role'])[$plural])) return [];
        $rows = array_values(ann_recipients($db, $sender, $plural, null));
        usort($rows, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        return array_map(fn($r) => [(int) $r['id'], $r['name'], $r['sub'] ?: $r['email']], $rows);
    }

    /** Courses a sender can narrow "students" to. */
    function ann_courses_for(PDO $db, array $sender): array
    {
        if ($sender['role'] === 'tutor') {
            $st = $db->prepare("SELECT DISTINCT c.id, c.title FROM registrations r JOIN courses c ON c.id = r.course_id
                                JOIN tutor_students ts ON ts.user_id = r.user_id JOIN tutors t ON t.id = ts.tutor_id
                                WHERE t.user_id = ? AND r.status = 'approved' ORDER BY c.title");
            $st->execute([$sender['id']]);
            return $st->fetchAll(PDO::FETCH_KEY_PAIR);
        }
        return $db->query("SELECT id, title FROM courses WHERE is_active = 1 ORDER BY title")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** The signed-in sender: id, display name and announcement role. */
    function ann_sender(PDO $db): array
    {
        $uid = (int) ($_SESSION['user_id'] ?? 0);
        $st = $db->prepare("SELECT TRIM(CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, ''))) FROM user_profiles up WHERE up.user_id = ?");
        $st->execute([$uid]);
        $name = trim((string) $st->fetchColumn()) ?: (string) ($_SESSION['username'] ?? 'Dazu Training Institute');
        $role = (int) ($_SESSION['role_id'] ?? 0) === 1 ? 'superadmin' : (string) ($_SESSION['role'] ?? '');
        if (!empty($_SESSION['tutor_admin'])) $role = 'admin';
        return ['id' => $uid, 'name' => $name, 'role' => in_array($role, ['superadmin', 'admin', 'tutor'], true) ? $role : ($role ?: 'none')];
    }

    /** [user_id => [id, role_name, email, name, sub]] for this audience, never including the sender. $targetId: the one person. */
    function ann_recipients(PDO $db, array $sender, string $audience, ?int $courseId, ?int $targetId = null): array
    {
        $kind = ANN_AUDIENCES[$audience][1] ?? null;
        if (!$kind || !isset(ann_audiences_for($sender['role'])[$audience])) return [];
        $out = [];
        $add = function (string $sql, array $args) use ($db, &$out) {
            $st = $db->prepare($sql);
            $st->execute($args);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[(int) $r['id']] = $r;
            }
        };
        $person = "SELECT u.id, ro.role_name, u.email,
                          COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name,''), ' ', COALESCE(up.last_name,''))), ''), u.username) AS name,
                          (SELECT CONCAT(r2.registration_no, ' · ', c2.title) FROM registrations r2 JOIN courses c2 ON c2.id = r2.course_id
                           WHERE r2.user_id = u.id AND r2.status = 'approved' ORDER BY r2.id DESC LIMIT 1) AS sub
                   FROM users u JOIN roles ro ON ro.id = u.role_id LEFT JOIN user_profiles up ON up.user_id = u.id";
        $one = '';
        if (ANN_AUDIENCES[$audience][2]) {
            if (!$targetId) return [];
            $one = ' AND u.id = ' . (int) $targetId;
            $courseId = null;
        }

        if ($kind === 'student') {
            $join = '';
            $args = [];
            if ($sender['role'] === 'tutor') {
                $join = 'JOIN tutor_students ts ON ts.user_id = r.user_id JOIN tutors t ON t.id = ts.tutor_id AND t.user_id = ?';
                $args[] = $sender['id'];
            }
            $course = '';
            if ($courseId) { $course = 'AND r.course_id = ?'; $args[] = $courseId; }
            $add("$person WHERE u.is_active = 1 AND ro.role_name = 'student'$one AND u.id IN (
                    SELECT r.user_id FROM registrations r $join
                    WHERE r.status = 'approved' AND r.user_id IS NOT NULL AND r.registration_no NOT LIKE 'PREVIEW-%' $course)", $args);
        }
        $roleName = ['tutor' => 'tutor', 'intern' => 'intern', 'admin' => 'admin', 'superadmin' => 'superadmin'][$kind] ?? null;
        if ($roleName) {
            $add("$person WHERE u.is_active = 1 AND ro.role_name = '$roleName'$one", []);
        }
        unset($out[$sender['id']]);
        return $out;
    }

    /** YouTube / TikTok / Facebook link → [kind, embed URL], or null when not a supported video link. */
    function ann_video_embed(string $link): ?array
    {
        $link = trim($link);
        if (!preg_match('#^https?://#i', $link)) return null;
        if (preg_match('#(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $link, $m)) {
            return ['youtube', 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0'];
        }
        if (preg_match('#tiktok\.com/.*/video/(\d+)#', $link, $m)) {
            return ['tiktok', 'https://www.tiktok.com/embed/v2/' . $m[1]];
        }
        if (preg_match('#^https?://(?:www\.|m\.|web\.)?(?:facebook\.com|fb\.watch)/#i', $link)) {
            return ['facebook', 'https://www.facebook.com/plugins/video.php?show_text=false&href=' . rawurlencode($link)];
        }
        return null;
    }

    /** Save an uploaded image / audio / video. @return array{ok:bool, path?:string, kind?:string, error?:string} */
    function ann_store_upload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['ok' => true];
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => 'The file did not upload (it may be larger than the server allows).'];
        if ($file['size'] > ANN_MAX_UPLOAD) return ['ok' => false, 'error' => 'The file is larger than 40 MB.'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
        if (!isset(ANN_UPLOAD_TYPES[$mime])) return ['ok' => false, 'error' => 'Upload a picture (JPG, PNG, WebP, GIF), music (MP3, M4A, OGG, WAV) or a video (MP4, WebM, MOV).'];
        [$kind, $ext] = ANN_UPLOAD_TYPES[$mime];
        $dir = dirname(__DIR__, 3) . '/public/uploads/announcements';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return ['ok' => false, 'error' => 'Could not create the upload folder.'];
        $name = 'ann_' . bin2hex(random_bytes(10)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) return ['ok' => false, 'error' => 'Could not save the file.'];
        return ['ok' => true, 'path' => 'uploads/announcements/' . $name, 'kind' => $kind];
    }

    /**
     * Create and send. $in: title, summary, message, audience, course_id, media_link, expires_on (Y-m-d).
     * @return array{ok:bool, id?:int, count?:int, error?:string}
     */
    function ann_create(PDO $db, array $sender, array $in, array $file): array
    {
        $title = trim((string) ($in['title'] ?? ''));
        $summary = trim((string) ($in['summary'] ?? ''));
        $message = trim(str_replace("\r\n", "\n", (string) ($in['message'] ?? '')));
        $audience = (string) ($in['audience'] ?? '');
        $courseId = (int) ($in['course_id'] ?? 0) ?: null;
        $targetId = (int) ($in['person_id'] ?? 0) ?: null;
        $single = !empty(ANN_AUDIENCES[$audience][2]);
        $link = trim((string) ($in['media_link'] ?? ''));
        $expires = trim((string) ($in['expires_on'] ?? ''));

        if ($title === '' || mb_strlen($title) > 150) return ['ok' => false, 'error' => 'Enter a title (up to 150 characters).'];
        if ($summary === '' || mb_strlen($summary) > 300) return ['ok' => false, 'error' => 'Enter a short description (up to 300 characters).'];
        if (!isset(ann_audiences_for($sender['role'])[$audience])) return ['ok' => false, 'error' => 'Choose who receives it.'];
        if ($single && !$targetId) return ['ok' => false, 'error' => 'Choose the person who receives it.'];
        if (!$single && $courseId && !isset(ann_courses_for($db, $sender)[$courseId])) return ['ok' => false, 'error' => 'Choose one of the listed courses.'];
        if ($link !== '' && !ann_video_embed($link)) return ['ok' => false, 'error' => 'The video link must be a YouTube, TikTok or Facebook video.'];
        $expiresAt = null;
        if ($expires !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $expires);
            if (!$d || $d->format('Y-m-d') < date('Y-m-d')) return ['ok' => false, 'error' => 'The disappear date must be today or later.'];
            $expiresAt = $d->format('Y-m-d') . ' 23:59:59';
        }
        $courseId = $audience === 'students' ? $courseId : null;
        $recipients = ann_recipients($db, $sender, $audience, $courseId, $single ? $targetId : null);
        if (!$recipients) return ['ok' => false, 'error' => $single ? 'That person cannot receive announcements from you.' : 'Nobody matches that audience yet, so nothing was sent.'];
        $up = ann_store_upload($file);
        if (!$up['ok']) return $up;

        $db->prepare('INSERT INTO announcements (author_id, author_name, author_role, title, summary, message, media_link, media_path, media_kind,
                                                 audience, course_id, target_user_id, recipient_count, expires_at)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
           ->execute([$sender['id'], $sender['name'], $sender['role'], $title, $summary, $message ?: null, $link ?: null,
                      $up['path'] ?? null, $up['kind'] ?? null, $audience, $courseId, $single ? $targetId : null, count($recipients), $expiresAt]);
        $id = (int) $db->lastInsertId();
        // Priority, deadline and "Confirm attendance" (columns from migration 075; skipped on older databases).
        if (ann_has_extras($db)) {
            $priority = in_array($in['priority'] ?? '', ['normal', 'important', 'urgent'], true) ? $in['priority'] : 'normal';
            $deadline = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($in['deadline'] ?? '')) ? $in['deadline'] : null;
            $db->prepare('UPDATE announcements SET priority = ?, deadline = ?, needs_confirm = ? WHERE id = ?')->execute([$priority, $deadline, !empty($in['needs_confirm']) ? 1 : 0, $id]);
        }

        $ins = $db->prepare('INSERT IGNORE INTO announcement_recipients (announcement_id, user_id) VALUES (?, ?)');
        $mails = [];
        foreach ($recipients as $uid => $r) {
            $ins->execute([$id, $uid]);
            $portal = ANN_PORTAL[$r['role_name']] ?? 'students';
            $path = $portal . '/announcements/index.php?a=' . $id;
            PortalNotifications::notify($db, $uid, 'announcement', '📢 ' . $title, $summary, $path, PortalNotifications::photoOf($db, $sender['id']));
            if (filter_var($r['email'], FILTER_VALIDATE_EMAIL)) {
                $mails[] = ['to_email' => $r['email'], 'to_name' => $r['name'], 'subject' => $title . ' — Dazu Training Institute',
                            'html' => ann_email_html($title, $summary, $message, $link, $sender['name'], PortalNotifications::absolute($path), $r['name'])];
            }
        }
        ann_queue_emails($mails);
        return ['ok' => true, 'id' => $id, 'count' => count($recipients)];
    }

    function ann_email_html(string $title, string $summary, string $message, string $link, string $author, string $url, string $name): string
    {
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $p = 'font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.55;color:#1f2937;margin:0 0 14px;';
        return '<div style="max-width:560px;margin:0 auto;padding:8px;">'
            . '<p style="' . $p . '">Hi ' . $e(explode(' ', trim($name))[0]) . ',</p>'
            . '<p style="' . $p . 'font-size:20px;font-weight:bold;color:#0f172a;">' . $e($title) . '</p>'
            . '<p style="' . $p . 'color:#475569;">' . $e($summary) . '</p>'
            . ($message !== '' ? '<p style="' . $p . '">' . nl2br($e(mb_strimwidth($message, 0, 1500, '…'))) . '</p>' : '')
            . ($link !== '' ? '<p style="' . $p . '">Video: <a href="' . $e($link) . '">' . $e($link) . '</a></p>' : '')
            . '<p style="' . $p . '"><a href="' . $e($url) . '" style="display:inline-block;background:#0D9488;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold;">Open in the portal</a></p>'
            . '<p style="' . $p . 'font-size:13px;color:#64748b;">From ' . $e($author) . ' · Dazu Training Institute</p></div>';
    }

    /** Send after the page has been delivered, over one SMTP connection. */
    function ann_queue_emails(array $mails): void
    {
        if (!$mails) return;
        register_shutdown_function(function () use ($mails) {
            if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
            elseif (function_exists('litespeed_finish_request')) @litespeed_finish_request();
            ignore_user_abort(true);
            @set_time_limit(0);
            try {
                require_once __DIR__ . '/Mailer.php';
                (new Mailer(require __DIR__ . '/../config/mail.php'))->sendBatch($mails);
            } catch (Throwable $e) {
                error_log('[announcements email] ' . $e->getMessage());
            }
        });
    }

    /** Remove an announcement completely: notifications, recipients, uploaded file and the row itself. */
    function ann_delete(PDO $db, int $id): void
    {
        $st = $db->prepare('SELECT media_path FROM announcements WHERE id = ?');
        $st->execute([$id]);
        $path = $st->fetchColumn();
        if ($path === false) return;
        $db->prepare("DELETE FROM portal_notifications WHERE type = 'announcement' AND url LIKE ?")->execute(['%/announcements/index.php?a=' . $id]);
        $db->prepare('DELETE FROM announcements WHERE id = ?')->execute([$id]);   // recipients go with it (ON DELETE CASCADE)
        if ($path && str_starts_with($path, 'uploads/announcements/')) {
            @unlink(dirname(__DIR__, 3) . '/public/' . $path);
        }
    }

    /** Delete every announcement whose disappear day has passed. Cheap; called from the pages that show them. */
    function ann_purge_expired(PDO $db): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            foreach ($db->query('SELECT id FROM announcements WHERE expires_at IS NOT NULL AND expires_at <= NOW()')->fetchAll(PDO::FETCH_COLUMN) as $id) {
                ann_delete($db, (int) $id);
            }
        } catch (Throwable $e) {
            // table not installed yet (migration 073)
        }
    }

    /** Announcements this user received (newest first), with whether they closed the pop-up. */
    function ann_received(PDO $db, int $uid, bool $onlyOpen = false, int $limit = 50): array
    {
        ann_purge_expired($db);
        try {
            $st = $db->prepare('SELECT a.*, ar.dismissed_at' . (ann_has_extras($db) ? ', ar.read_at, ar.confirmed_at' : '') . ' FROM announcement_recipients ar JOIN announcements a ON a.id = ar.announcement_id
                                WHERE ar.user_id = ?' . ($onlyOpen ? ' AND ar.dismissed_at IS NULL' : '') . '
                                ORDER BY a.created_at DESC, a.id DESC LIMIT ' . (int) $limit);
            $st->execute([$uid]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    /** Announcements this sender may manage: their own, or (admin/superadmin) everyone's. */
    function ann_sent(PDO $db, array $sender): array
    {
        ann_purge_expired($db);
        $sql = "SELECT a.*, c.title AS course_title," . (ann_has_extras($db) ? " (SELECT COUNT(*) FROM announcement_recipients x WHERE x.announcement_id = a.id AND x.confirmed_at IS NOT NULL) AS confirmed," : '') . "
                       COALESCE(NULLIF(TRIM(CONCAT(COALESCE(tp.first_name,''), ' ', COALESCE(tp.last_name,''))), ''), tu.username) AS target_name
                FROM announcements a LEFT JOIN courses c ON c.id = a.course_id
                LEFT JOIN users tu ON tu.id = a.target_user_id LEFT JOIN user_profiles tp ON tp.user_id = tu.id";
        try {
            if (in_array($sender['role'], ['superadmin', 'admin'], true)) {
                return $db->query("$sql ORDER BY a.created_at DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
            }
            $st = $db->prepare("$sql WHERE a.author_id = ? ORDER BY a.created_at DESC LIMIT 200");
            $st->execute([$sender['id']]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    function ann_can_delete(PDO $db, array $sender, int $id): bool
    {
        if (in_array($sender['role'], ['superadmin', 'admin'], true)) return true;
        $st = $db->prepare('SELECT 1 FROM announcements WHERE id = ? AND author_id = ?');
        $st->execute([$id, $sender['id']]);
        return (bool) $st->fetchColumn();
    }

    function ann_dismiss(PDO $db, int $uid, int $id): void
    {
        $db->prepare('UPDATE announcement_recipients SET dismissed_at = NOW() WHERE announcement_id = ? AND user_id = ? AND dismissed_at IS NULL')->execute([$id, $uid]);
        if (ann_has_extras($db)) {
            $db->prepare('UPDATE announcement_recipients SET read_at = COALESCE(read_at, NOW()) WHERE announcement_id = ? AND user_id = ?')->execute([$id, $uid]);
        }
    }

    /** Mark every announcement this person can see as read (their announcements page). */
    function ann_mark_all_read(PDO $db, int $uid): void
    {
        if (ann_has_extras($db)) $db->prepare('UPDATE announcement_recipients SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL')->execute([$uid]);
    }

    /** "Confirm attendance" on an announcement that asks for it. */
    function ann_confirm(PDO $db, int $uid, int $id): bool
    {
        if (!ann_has_extras($db)) return false;
        $st = $db->prepare('UPDATE announcement_recipients ar JOIN announcements a ON a.id = ar.announcement_id
                            SET ar.confirmed_at = NOW(), ar.read_at = COALESCE(ar.read_at, NOW())
                            WHERE ar.announcement_id = ? AND ar.user_id = ? AND a.needs_confirm = 1 AND ar.confirmed_at IS NULL');
        $st->execute([$id, $uid]);
        return (bool) $st->rowCount();
    }

    /** True once migration 075 added priority / deadline / confirm / read columns. */
    function ann_has_extras(PDO $db): bool
    {
        static $has = null;
        if ($has === null) {
            try {
                $has = (bool) $db->query("SHOW COLUMNS FROM announcement_recipients LIKE 'confirmed_at'")->fetch();
            } catch (Throwable $e) {
                $has = false;
            }
        }
        return $has;
    }

    /** Media block (video embed, picture, audio or video player) for an announcement row. */
    function ann_media_html(array $a): string
    {
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $html = '';
        if (!empty($a['media_link']) && ($v = ann_video_embed($a['media_link']))) {
            $ratio = $v[0] === 'tiktok' ? 'aspect-ratio:9/16;max-height:70vh;max-width:340px;margin:0 auto;' : 'aspect-ratio:16/9;';
            $html .= '<div class="ann-embed" style="' . $ratio . '"><iframe data-src="' . $e($v[1]) . '" src="' . $e($v[1]) . '" title="Video" loading="lazy"'
                   . ' allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>';
        }
        if (!empty($a['media_path'])) {
            $src = $e(url($a['media_path']));
            $html .= match ($a['media_kind']) {
                'image' => '<img class="ann-img" src="' . $src . '" alt="">',
                'audio' => '<audio class="ann-audio" controls preload="none" src="' . $src . '"></audio>',
                'video' => '<video class="ann-video" controls preload="metadata" playsinline src="' . $src . '"></video>',
                default => '',
            };
        }
        return $html;
    }

    /** "Students of Web Development", "Everyone", … */
    function ann_audience_label(array $a): string
    {
        $label = ANN_AUDIENCES[$a['audience']][0] ?? ucfirst((string) $a['audience']);
        if (!empty($a['target_name'])) $label = ucfirst(ANN_AUDIENCES[$a['audience']][1] ?? '') . ': ' . $a['target_name'];
        if (!empty($a['course_title'])) $label .= ' · ' . $a['course_title'];
        return $label;
    }
}
