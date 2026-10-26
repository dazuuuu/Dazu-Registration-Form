<?php
/**
 * Intake Booking (migration 079_intake_booking.sql).
 *
 *  - One set of intakes is shared by all courses. ik_status() is the single place that decides an intake's state;
 *    everything else (course page, enrol gate, admin, badges, cron) goes through it.
 *  - Times are stored in UTC and compared/shown in the intake time zone (default Africa/Nairobi, EAT).
 *  - No intakes (or none that apply to a course): ik_course_state() returns null and the site behaves exactly as before.
 *  - Booking links carry "id-token"; the token is an HMAC of the booking id + a random salt with a server key, so
 *    only the salt is stored. Cancelling a booking revokes its links.
 *  - Emails are queued (class_email_queue, sent after the page is delivered and retried by the cron job).
 */

require_once __DIR__ . '/../models/Connection.php';

const IK_ACTIVE = ['booked', 'contacted', 'invited', 'converted'];   // statuses that hold a seat
const IK_STATUS_LABELS = [
    'booked' => 'Slot booked', 'contacted' => 'Contacted', 'invited' => 'Invited',
    'converted' => 'Converted', 'expired' => 'Expired', 'cancelled' => 'Cancelled',
];
const IK_INTAKE_LABELS = [
    'upcoming' => 'Upcoming', 'booking_open' => 'Booking open', 'early_access' => 'Early access',
    'enrol_open' => 'Enrolment open', 'closed' => 'Enrolment closed',
];
const IK_CLASS_TIMES = ['online' => ['08:00' => '8:00 AM', '12:00' => '12:00 PM', '18:00' => '6:00 PM'], 'physical' => ['05:00' => '5:00 AM']];
const IK_CAMPUSES = ['nairobi' => 'Nairobi', 'thika' => 'Thika'];

if (!function_exists('ik_installed')) {

    function ik_installed(PDO $db): bool
    {
        static $ok = null;
        if ($ok === null) {
            try { $ok = (bool) $db->query("SHOW TABLES LIKE 'intake_bookings'")->fetchColumn(); } catch (Throwable $e) { $ok = false; }
        }
        return $ok;
    }

    function ik_settings(PDO $db): array
    {
        static $s = null;
        if ($s === null) {
            $s = ['early_access_hours' => 48, 'hold_days' => 3, 'timezone' => 'Africa/Nairobi', 'last_summary_on' => null];
            try {
                $row = $db->query('SELECT * FROM intake_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
                if ($row) $s = $row + $s;
            } catch (Throwable $e) {
            }
        }
        return $s;
    }

    function ik_tz(PDO $db): DateTimeZone
    {
        try { return new DateTimeZone((string) ik_settings($db)['timezone']); } catch (Throwable $e) { return new DateTimeZone('Africa/Nairobi'); }
    }

    function ik_tz_label(PDO $db): string
    {
        return ik_tz($db)->getName() === 'Africa/Nairobi' ? 'EAT' : ik_tz($db)->getName();
    }

    function ik_ts(?string $utc): ?int
    {
        return $utc ? (int) strtotime($utc . ' UTC') : null;
    }

    /** A TIMESTAMP column (created_at) comes back in the database session's zone: turn it into a real Unix timestamp. */
    function ik_db_ts(PDO $db, ?string $value): ?int
    {
        static $offset = null;
        if (!$value) return null;
        if ($offset === null) {
            try { $offset = (int) $db->query('SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW())')->fetchColumn(); } catch (Throwable $e) { $offset = 0; }
        }
        return (int) strtotime($value . ' UTC') - $offset;
    }

    /** UTC timestamp → text in the intake zone, e.g. ik_fmt($db, $ts, 'D, j M Y, g:i A'). */
    function ik_fmt(PDO $db, ?int $ts, string $format = 'j M Y, g:i A'): string
    {
        if (!$ts) return '';
        return (new DateTimeImmutable('@' . $ts))->setTimezone(ik_tz($db))->format($format);
    }

    /** A date/time typed by the admin in the intake zone ('Y-m-d\TH:i' or 'Y-m-d') → UTC 'Y-m-d H:i:s'. */
    function ik_local_to_utc(PDO $db, string $local, bool $endOfDay = false): ?string
    {
        $local = trim($local);
        if ($local === '') return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $local)) $local .= $endOfDay ? ' 23:59:59' : ' 00:00:00';
        try {
            $d = new DateTimeImmutable(str_replace('T', ' ', $local), ik_tz($db));
            return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return null;
        }
    }

    /** UTC → value for <input type="datetime-local"> in the intake zone. */
    function ik_input_value(PDO $db, ?string $utc): string
    {
        return $utc ? ik_fmt($db, ik_ts($utc), 'Y-m-d\TH:i') : '';
    }

    function ik_early_ts(PDO $db, array $i): int
    {
        return $i['early_access_at'] ? ik_ts($i['early_access_at']) : ik_ts($i['enrol_opens_at']) - (int) ik_settings($db)['early_access_hours'] * 3600;
    }

    /** The one status function: upcoming | booking_open | early_access | enrol_open | closed (live, override first). */
    function ik_status(PDO $db, array $i, ?int $now = null): string
    {
        $now = $now ?? time();
        if ($i['override_mode'] === 'closed') return 'closed';
        if ($i['override_mode'] === 'open') return 'enrol_open';
        if ($now > ik_ts($i['enrol_closes_at'])) return 'closed';   // open through the closing second (11:59:59 PM)
        if ($now >= ik_ts($i['enrol_opens_at'])) return 'enrol_open';
        if ($now >= ik_early_ts($db, $i)) return 'early_access';
        if ($now >= ik_ts($i['bookings_open_at'])) return 'booking_open';
        return 'upcoming';
    }

    /** All intakes, oldest enrolment first, each with 'status'. */
    function ik_intakes(PDO $db, bool $fresh = false): array
    {
        static $rows = null;
        if ($rows === null || $fresh) {
            $rows = [];
            if (ik_installed($db)) {
                foreach ($db->query('SELECT * FROM intakes ORDER BY enrol_opens_at, id')->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $r['status'] = ik_status($db, $r);
                    $rows[(int) $r['id']] = $r;
                }
            }
        }
        return $rows;
    }

    function ik_intake(PDO $db, int $id): ?array
    {
        return ik_intakes($db, true)[$id] ?? null;
    }

    function ik_course_rule(PDO $db, int $intakeId, int $courseId, bool $fresh = false): ?array
    {
        static $map = null;
        if ($map === null || $fresh) {
            $map = [];
            try {
                foreach ($db->query('SELECT * FROM intake_course_seats')->fetchAll(PDO::FETCH_ASSOC) as $r) $map[$r['intake_id'] . ':' . $r['course_id']] = $r;
            } catch (Throwable $e) {
            }
        }
        return $map[$intakeId . ':' . $courseId] ?? null;
    }

    function ik_applies(PDO $db, array $i, int $courseId): bool
    {
        return empty(ik_course_rule($db, (int) $i['id'], $courseId)['excluded']);
    }

    function ik_seat_limit(PDO $db, array $i, int $courseId): ?int
    {
        $r = ik_course_rule($db, (int) $i['id'], $courseId);
        if ($r && $r['seats'] !== null) return (int) $r['seats'];
        return $i['seats_default'] !== null ? (int) $i['seats_default'] : null;
    }

    /** Seats taken: active bookings + students who enrolled directly while this intake was open. */
    function ik_seats_taken(PDO $db, array $i, int $courseId): int
    {
        $st = $db->prepare("SELECT COUNT(*) FROM intake_bookings WHERE intake_id = ? AND course_id = ? AND status IN ('booked','contacted','invited','converted')");
        $st->execute([(int) $i['id'], $courseId]);
        $n = (int) $st->fetchColumn();
        try {
            $st = $db->prepare("SELECT COUNT(*) FROM registrations WHERE course_id = ? AND booking_id IS NULL AND status <> 'rejected' AND UNIX_TIMESTAMP(created_at) >= ? AND UNIX_TIMESTAMP(created_at) <= ?");
            $st->execute([$courseId, ik_ts($i['enrol_opens_at']), ik_ts($i['enrol_closes_at'])]);
            $n += (int) $st->fetchColumn();
        } catch (Throwable $e) {
        }
        return $n;
    }

    /** ['limit' => ?int, 'taken' => int, 'left' => ?int] */
    function ik_seats(PDO $db, array $i, int $courseId): array
    {
        $limit = ik_seat_limit($db, $i, $courseId);
        $taken = ik_seats_taken($db, $i, $courseId);
        return ['limit' => $limit, 'taken' => $taken, 'left' => $limit === null ? null : max(0, $limit - $taken)];
    }

    /**
     * What a course page shows. null = no intake applies to this course: keep today's behaviour.
     * ['enrol' => intake|null (enrolment open), 'early' => intake|null (early access running),
     *  'last_closed' => intake|null, 'bookable' => [intakes taking bookings], 'next_start' => intake|null]
     */
    function ik_course_state(PDO $db, int $courseId): ?array
    {
        $list = array_values(array_filter(ik_intakes($db), fn($i) => ik_applies($db, $i, $courseId)));
        if (!$list) return null;
        $s = ['enrol' => null, 'early' => null, 'last_closed' => null, 'bookable' => [], 'next_start' => null, 'upcoming' => null];
        foreach ($list as $i) {
            if ($i['status'] === 'enrol_open' && !$s['enrol']) $s['enrol'] = $i;
            if ($i['status'] === 'early_access' && !$s['early']) $s['early'] = $i;
            if ($i['status'] === 'closed') $s['last_closed'] = $i;
            if (in_array($i['status'], ['booking_open', 'early_access'], true)) $s['bookable'][] = $i;
            if ($i['status'] === 'upcoming' && !$s['upcoming']) $s['upcoming'] = $i;
            if ($i['status'] !== 'closed' && !$s['next_start'] && $i['classes_start_on'] && $i['status'] !== 'enrol_open') $s['next_start'] = $i;
        }
        return $s;
    }

    /** Server-side enrol gate, used by the shared enrolment handler. */
    function ik_enrol_check(PDO $db, int $courseId, ?array $booking): array
    {
        if (!ik_installed($db) || !($s = ik_course_state($db, $courseId))) return ['ok' => true];
        if ($booking && (int) $booking['course_id'] === $courseId && in_array($booking['status'], ['booked', 'contacted', 'invited'], true)) {
            $bi = ik_intakes($db)[(int) $booking['intake_id']] ?? null;
            $held = !$booking['hold_until'] || ik_ts($booking['hold_until']) > time();
            if ($bi && $held && in_array($bi['status'], ['early_access', 'enrol_open'], true)) return ['ok' => true, 'booking' => $booking, 'intake' => $bi];
        }
        if ($s['enrol']) {
            $seats = ik_seats($db, $s['enrol'], $courseId);
            if ($seats['left'] !== null && $seats['left'] <= 0) {
                return ['ok' => false, 'error' => 'This course is full for the ' . $s['enrol']['name'] . ' intake. You can book your slot in the next intake or join the waiting list.'];
            }
            return ['ok' => true, 'intake' => $s['enrol']];
        }
        if ($s['early']) {
            return ['ok' => false, 'error' => 'Enrolment for the ' . $s['early']['name'] . ' intake is open early only for people who booked. It opens for everyone on '
                . ik_fmt($db, ik_ts($s['early']['enrol_opens_at']), 'j F Y, g:i A') . ' ' . ik_tz_label($db) . '. You can still book your slot now.'];
        }
        return ['ok' => false, 'error' => 'Hello, our intake has closed. You can still book your slot in the next intake.'];
    }

    // ---------- links ----------

    function ik_key(): string
    {
        static $k = null;
        if ($k === null) {
            $f = storage_path('intake_link.key');
            $k = is_file($f) ? trim((string) file_get_contents($f)) : '';
            if (strlen($k) < 32) {
                $k = bin2hex(random_bytes(32));
                @file_put_contents($f, $k);
                @chmod($f, 0600);
            }
        }
        return $k;
    }

    function ik_token(array $b): string
    {
        return (int) $b['id'] . '-' . hash_hmac('sha256', $b['id'] . '|' . $b['token_salt'], ik_key());
    }

    function ik_by_token(PDO $db, string $token): ?array
    {
        if (!preg_match('/^(\d+)-([a-f0-9]{64})$/', $token, $m)) return null;
        $b = ik_booking($db, (int) $m[1]);
        return $b && hash_equals(ik_token($b), $token) ? $b : null;
    }

    function ik_booking(PDO $db, int $id): ?array
    {
        $st = $db->prepare('SELECT b.*, c.title AS course_title FROM intake_bookings b JOIN courses c ON c.id = b.course_id WHERE b.id = ?');
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    function ik_by_number_email(PDO $db, string $number, string $email): ?array
    {
        $st = $db->prepare('SELECT id FROM intake_bookings WHERE number = ? AND LOWER(email) = ?');
        $st->execute([strtoupper(trim($number)), strtolower(trim($email))]);
        $id = (int) $st->fetchColumn();
        return $id ? ik_booking($db, $id) : null;
    }

    /**
     * Absolute link for emails. Web requests use their own address and remember it, so emails sent by the cron job
     * (no web request) link to the same site. APP_URL in the environment overrides; https://dazu.ac.ke is the last resort.
     */
    function ik_abs(string $path): string
    {
        static $base = null;
        if ($base === null) {
            $f = storage_path('site_base_url.txt');
            if ($env = getenv('APP_URL')) {
                $base = rtrim($env, '/');
            } elseif (!empty($_SERVER['HTTP_HOST']) && PHP_SAPI !== 'cli') {
                $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
                $base = ($https ? 'https' : 'http') . '://' . preg_replace('/[^A-Za-z0-9.\-:]/', '', (string) $_SERVER['HTTP_HOST']);
                if (!is_file($f) || trim((string) @file_get_contents($f)) !== $base) @file_put_contents($f, $base);
            } else {
                $base = is_file($f) ? trim((string) file_get_contents($f)) : '';
                $base = $base !== '' ? $base : 'https://dazu.ac.ke';
            }
        }
        return $base . url($path);
    }

    function ik_manage_url(array $b): string
    {
        return ik_abs('intake/manage.php?t=' . ik_token($b));
    }

    function ik_early_url(array $b): string
    {
        return ik_abs('courses/view.php?id=' . (int) $b['course_id'] . '&ea=' . ik_token($b));
    }

    // ---------- validation, logging, rate limits ----------

    /** Kenyan phone → '07xxxxxxxx' / '01xxxxxxxx', or null. */
    function ik_phone(string $p): ?string
    {
        $d = preg_replace('/[\s\-()]/', '', $p);
        if (preg_match('/^(?:\+?254|0)([17]\d{8})$/', $d, $m)) return '0' . $m[1];
        return null;
    }

    function ik_rate_ok(PDO $db, string $action, int $max, int $seconds): bool
    {
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 45);
        try {
            $st = $db->prepare('SELECT COUNT(*) FROM intake_rate_log WHERE ip = ? AND action = ? AND created_at > ?');
            $st->execute([$ip, $action, gmdate('Y-m-d H:i:s', time() - $seconds)]);
            if ((int) $st->fetchColumn() >= $max) return false;
            $db->prepare('INSERT INTO intake_rate_log (ip, action, created_at) VALUES (?, ?, UTC_TIMESTAMP())')->execute([$ip, $action]);
            if (mt_rand(1, 50) === 1) $db->exec("DELETE FROM intake_rate_log WHERE created_at < UTC_TIMESTAMP() - INTERVAL 1 DAY");
        } catch (Throwable $e) {
        }
        return true;
    }

    function ik_log_intake(PDO $db, ?int $intakeId, string $action, $old, $new, ?string $reason): void
    {
        $enc = fn($v) => $v === null ? null : (is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $db->prepare('INSERT INTO intake_log (intake_id, admin_id, action, old_value, new_value, reason) VALUES (?, ?, ?, ?, ?, ?)')
           ->execute([$intakeId, (int) ($_SESSION['user_id'] ?? 0) ?: null, $action, $enc($old), $enc($new), $reason !== null ? mb_substr($reason, 0, 255) : null]);
    }

    function ik_log_booking(PDO $db, int $bookingId, string $actor, string $action, string $details = ''): void
    {
        $db->prepare('INSERT INTO intake_booking_log (booking_id, actor, action, details) VALUES (?, ?, ?, ?)')
           ->execute([$bookingId, mb_substr($actor, 0, 60), $action, $details !== '' ? mb_substr($details, 0, 500) : null]);
    }

    function ik_actor(): string
    {
        return !empty($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['admin', 'superadmin', 'tutor'], true)
            ? 'admin:' . (int) $_SESSION['user_id'] : 'student';
    }

    function ik_source(): string
    {
        $u = trim((string) ($_GET['utm_source'] ?? $_POST['utm_source'] ?? $_SESSION['ik_utm']['source'] ?? ''));
        $c = trim((string) ($_GET['utm_campaign'] ?? $_POST['utm_campaign'] ?? $_SESSION['ik_utm']['campaign'] ?? ''));
        if ($u !== '') return mb_substr($u . ($c !== '' ? ' / ' . $c : ''), 0, 120);
        $ref = (string) ($_POST['ik_ref'] ?? $_SESSION['ik_utm']['ref'] ?? '');
        $host = strtolower((string) parse_url($ref, PHP_URL_HOST));
        if ($host === '' || $host === strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''))) return 'Direct';
        foreach (['tiktok' => 'TikTok', 'whatsapp' => 'WhatsApp', 'wa.me' => 'WhatsApp', 'facebook' => 'Facebook', 'fb.' => 'Facebook', 'instagram' => 'Instagram',
                  'google' => 'Google', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn', 't.co' => 'X', 'twitter' => 'X'] as $k => $v) {
            if (str_contains($host, $k)) return $v;
        }
        return mb_substr($host, 0, 120);
    }

    /** Remember where the visitor came from (first landing) so a booking made later still has its source. */
    function ik_track_source(): void
    {
        ik_abs('');   // remember this site's address for emails sent by the cron job
        if (session_status() !== PHP_SESSION_ACTIVE || !empty($_SESSION['ik_utm'])) return;
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $_SESSION['ik_utm'] = ['source' => mb_substr((string) ($_GET['utm_source'] ?? ''), 0, 60), 'campaign' => mb_substr((string) ($_GET['utm_campaign'] ?? ''), 0, 60),
                               'ref' => mb_substr($ref, 0, 255)];
    }

    // ---------- bookings ----------

    function ik_bookable_for(PDO $db, int $courseId): array
    {
        $s = ik_course_state($db, $courseId);
        return $s ? $s['bookable'] : [];
    }

    /**
     * Book a slot. $in: intake_id, full_name, email, phone, phone2, mode, class_time, campus, consent, website (honeypot).
     * @return array{ok:bool, booking?:array, waitlisted?:bool, error?:string, duplicate?:array}
     */
    function ik_book(PDO $db, array $in, array $course): array
    {
        if (trim((string) ($in['website'] ?? '')) !== '') return ['ok' => false, 'error' => 'Your booking could not be sent. Please try again.'];
        if (!ik_rate_ok($db, 'book', 8, 3600)) return ['ok' => false, 'error' => 'Too many bookings from this connection. Please try again in an hour.'];
        $courseId = (int) $course['id'];
        $intakeId = (int) ($in['intake_id'] ?? 0);
        $intake = null;
        foreach (ik_bookable_for($db, $courseId) as $i) if ((int) $i['id'] === $intakeId) $intake = $i;
        if (!$intake) return ['ok' => false, 'error' => 'That intake is not taking bookings right now. Please choose another intake.'];

        $name = trim(preg_replace('/\s+/', ' ', (string) ($in['full_name'] ?? '')));
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        $phone = ik_phone((string) ($in['phone'] ?? ''));
        $phone2Raw = trim((string) ($in['phone2'] ?? ''));
        $phone2 = $phone2Raw === '' ? null : ik_phone($phone2Raw);
        $mode = ($in['mode'] ?? '') === 'physical' ? 'physical' : (($in['mode'] ?? '') === 'online' ? 'online' : '');
        $time = (string) ($in['class_time'] ?? '');
        $campus = (string) ($in['campus'] ?? '');
        if ($name === '' || mb_strlen($name) > 150) return ['ok' => false, 'error' => 'Enter your full name.'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Enter a valid email address.'];
        if (!$phone) return ['ok' => false, 'error' => 'Enter a valid Kenyan phone number, e.g. 0712 345 678 or +254 712 345 678.'];
        if ($phone2Raw !== '' && !$phone2) return ['ok' => false, 'error' => 'The secondary phone number is not valid. Leave it blank or use 07xx / 01xx / +254.'];
        if ($mode === '') return ['ok' => false, 'error' => 'Choose Online or Physical classes.'];
        if (!isset(IK_CLASS_TIMES[$mode][$time])) return ['ok' => false, 'error' => 'Choose your preferred class time.'];
        if ($mode === 'physical' && !isset(IK_CAMPUSES[$campus])) return ['ok' => false, 'error' => 'Choose your campus: Nairobi or Thika.'];
        if ($mode !== 'physical') $campus = '';
        if (empty($in['consent'])) return ['ok' => false, 'error' => 'Please tick that you agree to be contacted about your booking.'];

        $dup = $db->prepare("SELECT id FROM intake_bookings WHERE intake_id = ? AND course_id = ? AND (LOWER(email) = ? OR phone = ?) AND status IN ('booked','contacted','invited','converted') LIMIT 1");
        $dup->execute([$intakeId, $courseId, $email, $phone]);
        if ($d = (int) $dup->fetchColumn()) return ['ok' => false, 'error' => 'You have already booked this intake.', 'duplicate' => ik_booking($db, $d)];

        $source = ik_source();
        $db->beginTransaction();
        try {
            // Lock the intake row so two people cannot take the last seat together.
            $db->prepare('SELECT id FROM intakes WHERE id = ? FOR UPDATE')->execute([$intakeId]);
            $seats = ik_seats($db, $intake, $courseId);
            if ($seats['left'] !== null && $seats['left'] <= 0) {
                $db->prepare('INSERT INTO intake_waiting_list (intake_id, course_id, name, email, phone, mode, class_time, campus, source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
                   ->execute([$intakeId, $courseId, $name, $email, $phone, $mode, $time, $campus ?: null, $source]);
                $db->commit();
                ik_mail_waitlisted($db, ['full_name' => $name, 'email' => $email, 'course_title' => $course['title']], $intake);
                return ['ok' => true, 'waitlisted' => true];
            }
            $id = ik_insert_booking($db, $intake, $courseId, $name, $email, $phone, $phone2, $mode, $time, $campus, $source);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[intake booking] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Your booking could not be saved. Please try again.'];
        }
        $b = ik_booking($db, $id);
        ik_log_booking($db, $id, 'student', 'booked', 'Booked ' . $intake['name'] . ' (source: ' . $source . ')');
        ik_mail_booked($db, $b, $intake);
        ik_mail_admissions_new($db, $b, $intake);
        if ($intake['status'] === 'early_access') {   // booked during early access: invited at once, with its own hold
            ik_invite($db, $b, $intake);
            $b = ik_booking($db, $id);
        }
        return ['ok' => true, 'booking' => $b];
    }

    /** Inside a transaction: next DZB-YYYY-NNNN number (YYYY = intake year) and the row. */
    function ik_insert_booking(PDO $db, array $intake, int $courseId, string $name, string $email, string $phone, ?string $phone2, string $mode, string $time, string $campus, ?string $source): int
    {
        $year = ik_fmt($db, $intake['classes_start_on'] ? strtotime($intake['classes_start_on'] . ' 12:00 UTC') : ik_ts($intake['enrol_opens_at']), 'Y');
        $prefix = 'DZB-' . $year . '-';
        $st = $db->prepare('SELECT MAX(CAST(SUBSTRING(number, 10) AS UNSIGNED)) FROM intake_bookings WHERE number LIKE ? FOR UPDATE');
        $st->execute([$prefix . '%']);
        $number = $prefix . str_pad((string) (((int) $st->fetchColumn()) + 1), 4, '0', STR_PAD_LEFT);
        $db->prepare('INSERT INTO intake_bookings (number, intake_id, course_id, full_name, email, phone, phone2, mode, class_time, campus, token_salt, source, consent)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)')
           ->execute([$number, (int) $intake['id'], $courseId, $name, $email, $phone, $phone2, $mode, $time, $campus ?: null, bin2hex(random_bytes(16)), $source]);
        return (int) $db->lastInsertId();
    }

    /** Start the early-access invite and the hold (hold_days from now). Safe to call twice. */
    function ik_invite(PDO $db, array $b, array $intake): void
    {
        if (!in_array($b['status'], ['booked', 'contacted'], true)) return;
        $hold = time() + (int) ik_settings($db)['hold_days'] * 86400;
        $st = $db->prepare("UPDATE intake_bookings SET status = 'invited', invited_at = UTC_TIMESTAMP(), early_access_until = ?, hold_until = ?, reminder_sent_at = NULL
                            WHERE id = ? AND status IN ('booked','contacted')");
        $st->execute([$intake['enrol_opens_at'], gmdate('Y-m-d H:i:s', $hold), (int) $b['id']]);
        if (!$st->rowCount()) return;
        ik_log_booking($db, (int) $b['id'], 'system', 'invited', 'Early access invite; seat held until ' . ik_fmt($db, $hold) . ' ' . ik_tz_label($db));
        ik_mail_invite($db, ik_booking($db, (int) $b['id']), $intake);
    }

    function ik_cancel(PDO $db, array $b, string $reason = ''): array
    {
        if (!in_array($b['status'], ['booked', 'contacted', 'invited'], true)) return ['ok' => false, 'error' => 'This booking can no longer be cancelled.'];
        $db->prepare("UPDATE intake_bookings SET status = 'cancelled' WHERE id = ?")->execute([(int) $b['id']]);
        ik_log_booking($db, (int) $b['id'], ik_actor(), 'cancelled', $reason);
        $intake = ik_intakes($db, true)[(int) $b['intake_id']] ?? null;
        ik_mail_simple($db, $b, 'Your booking is cancelled (' . $b['number'] . ')', 'Your booking is cancelled',
            'We have cancelled booking <strong>' . ik_e($b['number']) . '</strong> for ' . ik_e($b['course_title']) . '. You can book again at any time.',
            [['Book again', ik_abs('intake/book.php?course=' . (int) $b['course_id'])]]);
        if ($intake) ik_offer_waitlist($db, $intake, (int) $b['course_id']);
        return ['ok' => true];
    }

    function ik_move(PDO $db, array $b, int $newIntakeId): array
    {
        if (!in_array($b['status'], ['booked', 'contacted', 'invited', 'expired'], true)) return ['ok' => false, 'error' => 'This booking can no longer be moved.'];
        if ($newIntakeId === (int) $b['intake_id']) return ['ok' => false, 'error' => 'The booking is already for that intake.'];
        $new = null;
        foreach (ik_bookable_for($db, (int) $b['course_id']) as $i) if ((int) $i['id'] === $newIntakeId) $new = $i;
        if (!$new) return ['ok' => false, 'error' => 'That intake is not taking bookings right now.'];
        $dup = $db->prepare("SELECT 1 FROM intake_bookings WHERE intake_id = ? AND course_id = ? AND id <> ? AND (LOWER(email) = ? OR phone = ?) AND status IN ('booked','contacted','invited','converted')");
        $dup->execute([$newIntakeId, (int) $b['course_id'], (int) $b['id'], strtolower($b['email']), $b['phone']]);
        if ($dup->fetchColumn()) return ['ok' => false, 'error' => 'You already have a booking for that intake.'];
        $db->beginTransaction();
        try {
            $db->prepare('SELECT id FROM intakes WHERE id = ? FOR UPDATE')->execute([$newIntakeId]);
            $seats = ik_seats($db, $new, (int) $b['course_id']);
            if ($seats['left'] !== null && $seats['left'] <= 0) {
                $db->rollBack();
                return ['ok' => false, 'error' => 'The ' . $new['name'] . ' intake is full for this course.'];
            }
            $db->prepare("UPDATE intake_bookings SET intake_id = ?, status = 'booked', invited_at = NULL, early_access_until = NULL, hold_until = NULL, reminder_sent_at = NULL WHERE id = ?")
               ->execute([$newIntakeId, (int) $b['id']]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['ok' => false, 'error' => 'The booking could not be moved. Please try again.'];
        }
        $old = ik_intakes($db, true)[(int) $b['intake_id']] ?? null;
        ik_log_booking($db, (int) $b['id'], ik_actor(), 'moved', 'From ' . ($old['name'] ?? '?') . ' to ' . $new['name']);
        $nb = ik_booking($db, (int) $b['id']);
        ik_mail_simple($db, $nb, 'Your booking is moved to ' . $new['name'] . ' (' . $b['number'] . ')', 'Your booking is moved',
            'Booking <strong>' . ik_e($b['number']) . '</strong> for ' . ik_e($b['course_title']) . ' is now for the <strong>' . ik_e($new['name']) . '</strong> intake.'
            . ($new['classes_start_on'] ? ' Classes start ' . ik_e(ik_fmt($db, strtotime($new['classes_start_on'] . ' 12:00 UTC'), 'D, j M Y')) . '.' : ''),
            [['Manage my booking', ik_manage_url($nb)]]);
        if ($new['status'] === 'early_access') ik_invite($db, $nb, $new);
        if ($old) ik_offer_waitlist($db, $old, (int) $b['course_id']);
        return ['ok' => true];
    }

    function ik_update_prefs(PDO $db, array $b, string $mode, string $time, string $campus): array
    {
        if (!in_array($b['status'], ['booked', 'contacted', 'invited'], true)) return ['ok' => false, 'error' => 'This booking can no longer be changed.'];
        if (!isset(IK_CLASS_TIMES[$mode][$time])) return ['ok' => false, 'error' => 'Choose a class time.'];
        if ($mode === 'physical' && !isset(IK_CAMPUSES[$campus])) return ['ok' => false, 'error' => 'Choose your campus.'];
        $db->prepare('UPDATE intake_bookings SET mode = ?, class_time = ?, campus = ? WHERE id = ?')->execute([$mode, $time, $mode === 'physical' ? $campus : null, (int) $b['id']]);
        ik_log_booking($db, (int) $b['id'], ik_actor(), 'changed', ucfirst($mode) . ' · ' . IK_CLASS_TIMES[$mode][$time] . ($mode === 'physical' ? ' · ' . IK_CAMPUSES[$campus] : ''));
        return ['ok' => true];
    }

    /** A seat came free: offer it to the first person waiting (a booking with the normal hold when enrolment is running). */
    function ik_offer_waitlist(PDO $db, array $intake, int $courseId): void
    {
        $intake = ik_intakes($db, true)[(int) $intake['id']] ?? $intake;
        if ($intake['status'] === 'closed') return;
        $seats = ik_seats($db, $intake, $courseId);
        if ($seats['left'] !== null && $seats['left'] <= 0) return;
        $st = $db->prepare("SELECT * FROM intake_waiting_list WHERE intake_id = ? AND course_id = ? AND status = 'waiting' ORDER BY id LIMIT 1");
        $st->execute([(int) $intake['id'], $courseId]);
        if (!($w = $st->fetch(PDO::FETCH_ASSOC))) return;
        $db->beginTransaction();
        try {
            $db->prepare('SELECT id FROM intakes WHERE id = ? FOR UPDATE')->execute([(int) $intake['id']]);
            $id = ik_insert_booking($db, $intake, $courseId, $w['name'], $w['email'], $w['phone'], null, $w['mode'], (string) $w['class_time'], (string) $w['campus'], $w['source']);
            $db->prepare("UPDATE intake_waiting_list SET status = 'booked', offered_at = UTC_TIMESTAMP(), booking_id = ? WHERE id = ?")->execute([$id, (int) $w['id']]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[intake waitlist] ' . $e->getMessage());
            return;
        }
        $b = ik_booking($db, $id);
        ik_log_booking($db, $id, 'system', 'offered', 'Seat offered from the waiting list');
        if (in_array($intake['status'], ['early_access', 'enrol_open'], true)) {
            ik_invite($db, $b, $intake);   // the invite email is the offer, with the 3-day hold
        } else {
            ik_mail_booked($db, $b, $intake, true);
        }
    }

    /** Booking → student, after the shared enrolment created the registration. */
    function ik_mark_converted(PDO $db, int $bookingId, int $registrationId): void
    {
        $db->prepare("UPDATE intake_bookings SET status = 'converted', converted_registration_id = ? WHERE id = ? AND status <> 'converted'")->execute([$registrationId, $bookingId]);
        try { $db->prepare('UPDATE registrations SET booking_id = ? WHERE id = ?')->execute([$bookingId, $registrationId]); } catch (Throwable $e) {}
        ik_log_booking($db, $bookingId, ik_actor(), 'converted', 'Registration #' . $registrationId);
        ik_send_registration_email($db, $registrationId);
    }

    /** The existing registration-number email (StudentNotifier::sendRegistrationReceived), sent after the page is delivered. */
    function ik_send_registration_email(PDO $db, int $registrationId): void
    {
        register_shutdown_function(function () use ($db, $registrationId) {
            if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
            ignore_user_abort(true);
            try {
                require_once __DIR__ . '/../models/RegistrationModel.php';
                require_once __DIR__ . '/../models/CourseModel.php';
                require_once __DIR__ . '/StudentNotifier.php';
                $reg = (new \Models\RegistrationModel($db))->getById($registrationId);
                if (!$reg) return;
                $course = (new \Models\CourseModel($db))->getById((int) $reg['course_id']) ?: [];
                (new StudentNotifier($db))->sendRegistrationReceived($reg, $course);
            } catch (Throwable $e) {
                error_log('[intake registration email] ' . $e->getMessage());
            }
        });
    }

    /** Admin "Convert to enrolment": the same enrolment service the public form uses (RegistrationModel::submit). */
    function ik_admin_convert(PDO $db, array $b): array
    {
        if (!in_array($b['status'], ['booked', 'contacted', 'invited', 'expired'], true)) return ['ok' => false, 'error' => 'Only open bookings can be converted.'];
        require_once __DIR__ . '/../models/RegistrationModel.php';
        require_once __DIR__ . '/../models/CourseModel.php';
        $res = (new \Models\RegistrationModel($db))->submit([
            'full_name' => $b['full_name'], 'email' => $b['email'], 'phone' => $b['phone'], 'secondary_phone' => (string) $b['phone2'],
            'course_id' => (int) $b['course_id'], 'study_mode' => $b['mode'], 'payment_plan' => 'monthly', 'id_document_path' => null,
            'payment_reference' => 'Intake booking ' . $b['number'] . ' — payment to follow', 'free_course_reason' => '', 'referral_registration_no' => null,
        ], new \Models\CourseModel($db));
        if (empty($res['ok'])) return ['ok' => false, 'error' => $res['error'] ?? 'The student could not be created.'];
        if ($b['mode'] === 'physical' && $b['campus']) {
            try { $db->prepare('UPDATE registrations SET study_location = ? WHERE id = ?')->execute([$b['campus'], (int) $res['id']]); } catch (Throwable $e) {}
        }
        ik_mark_converted($db, (int) $b['id'], (int) $res['id']);
        return ['ok' => true, 'registration_no' => $res['registration_no'] ?? ''];
    }

    // ---------- course page helpers ----------

    /** Booking for early access kept in the session after the visitor opened their invite link. */
    function ik_session_booking(PDO $db, int $courseId): ?array
    {
        $id = (int) ($_SESSION['ik_booking'][$courseId] ?? 0);
        if (!$id) return null;
        $b = ik_booking($db, $id);
        return $b && in_array($b['status'], ['booked', 'contacted', 'invited'], true) ? $b : null;
    }

    function ik_remember_booking(array $b): void
    {
        $_SESSION['ik_booking'][(int) $b['course_id']] = (int) $b['id'];
    }

    /** Small badge for course cards: ['text', 'tone'] or null. */
    function ik_badge(PDO $db, int $courseId): ?array
    {
        if (!ik_installed($db) || !($s = ik_course_state($db, $courseId))) return null;
        if ($s['enrol']) return ['Enrolment open', 'open'];
        if ($s['bookable'] || $s['early']) {
            $n = $s['bookable'][0] ?? $s['early'];
            if ($n && $n['classes_start_on'] && $s['last_closed'] === null) return ['Starts ' . ik_fmt($db, strtotime($n['classes_start_on'] . ' 12:00 UTC'), 'j M'), 'soon'];
            return ['Enrolment closed. Book the next intake', 'closed'];
        }
        if ($s['upcoming'] && $s['upcoming']['classes_start_on']) return ['Starts ' . ik_fmt($db, strtotime($s['upcoming']['classes_start_on'] . ' 12:00 UTC'), 'j M'), 'soon'];
        return ['Enrolment closed', 'closed'];
    }

    /** "Enrolment closes in 5 days" / "Enrolment opens 1 January" / null. */
    function ik_countdown(PDO $db, array $s): ?string
    {
        if ($s['enrol'] && $s['enrol']['override_mode'] !== 'open') {
            $left = ik_ts($s['enrol']['enrol_closes_at']) + 1 - time();
            if ($left < 86400) return 'Enrolment closes in ' . max(1, (int) ceil($left / 3600)) . ' hour' . ($left > 3600 ? 's' : '');
            $d = max(1, (int) floor($left / 86400));
            return 'Enrolment closes in ' . $d . ' day' . ($d > 1 ? 's' : '');
        }
        $n = $s['early'] ?? ($s['bookable'][0] ?? null);
        return $n ? 'Enrolment opens ' . ik_fmt($db, ik_ts($n['enrol_opens_at']), 'j F') : null;
    }

    function ik_time_label(array $b): string
    {
        return ucfirst($b['mode']) . ' · ' . ($b['mode'] === 'physical'
            ? (IK_CAMPUSES[$b['campus']] ?? 'Campus') . ' · ' . (IK_CLASS_TIMES['physical'][$b['class_time']] ?? '5:00 AM')
            : (IK_CLASS_TIMES['online'][$b['class_time']] ?? ''));
    }

    function ik_ics(PDO $db, array $b, array $intake): string
    {
        $start = $intake['classes_start_on'] ?: substr(ik_fmt($db, ik_ts($intake['enrol_opens_at']), 'Y-m-d'), 0, 10);
        $d = str_replace('-', '', $start);
        $end = date('Ymd', strtotime($start . ' +1 day'));
        $esc = fn($s) => addcslashes(str_replace(["\r", "\n"], ' ', $s), ',;\\');
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Dazu Training Institute//Intake Booking//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'BEGIN:VEVENT',
            'UID:' . $b['number'] . '@dazu.ac.ke', 'DTSTAMP:' . gmdate('Ymd\THis\Z'), 'DTSTART;VALUE=DATE:' . $d, 'DTEND;VALUE=DATE:' . $end,
            'SUMMARY:' . $esc('Classes start: ' . $b['course_title'] . ' (' . $intake['name'] . ')'),
            'DESCRIPTION:' . $esc('Booking ' . $b['number'] . '. Enrolment opens ' . ik_fmt($db, ik_ts($intake['enrol_opens_at'])) . ' ' . ik_tz_label($db) . '. Manage: ' . ik_manage_url($b)),
            'END:VEVENT', 'END:VCALENDAR'];
        // Lines longer than 75 bytes are folded (RFC 5545).
        $fold = fn(string $l) => rtrim(chunk_split($l, 74, "\r\n "), "\r\n ");
        return implode("\r\n", array_map($fold, $lines)) . "\r\n";
    }

    // ---------- emails ----------

    function ik_e($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    /** Branded email: dark header with the green corner, rows table, buttons. */
    function ik_email_html(string $heading, string $intro, array $rows = [], array $buttons = [], string $after = ''): string
    {
        $phone = function_exists('site_contact_setting_value') ? site_contact_setting_value('contact_phone', '0722 922 454') : '0722 922 454';
        $mail = function_exists('site_contact_setting_value') ? site_contact_setting_value('contact_email', 'admin@dazuhub.com') : 'admin@dazuhub.com';
        $logo = ik_abs('assets/icons/favicon-192.png');
        $h = '<div style="background:#ecebe6;padding:24px 12px;font-family:Arial,Helvetica,sans-serif"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden">'
            . '<tr><td style="background:#373435;padding:20px 24px;border-bottom:6px solid #44A024"><img src="' . ik_e($logo) . '" width="40" height="40" alt="Dazu" style="background:#fff;border-radius:8px;vertical-align:middle">'
            . '<span style="color:#fff;font-weight:bold;font-size:18px;margin-left:10px;vertical-align:middle">DAZU</span> <span style="color:#cfd0cc;font-size:12px;vertical-align:middle">Training Institute</span></td></tr>'
            . '<tr><td style="padding:26px 24px 8px;color:#2b2a2b"><h1 style="margin:0 0 10px;font-size:22px;color:#2b2a2b">' . $heading . '</h1><p style="margin:0 0 16px;font-size:15px;line-height:1.55;color:#4a4a4a">' . $intro . '</p>';
        if ($rows) {
            $h .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f2f0;border-radius:10px;font-size:14px">';
            foreach ($rows as $k => $v) $h .= '<tr><td style="padding:10px 14px;color:#6b6b6b;border-bottom:1px solid #e3e3df">' . ik_e($k) . '</td><td style="padding:10px 14px;text-align:right;font-weight:bold;color:#2b2a2b;border-bottom:1px solid #e3e3df">' . ik_e($v) . '</td></tr>';
            $h .= '</table>';
        }
        if ($buttons) {
            $h .= '<p style="margin:20px 0 6px">';
            foreach ($buttons as $i => [$label, $href]) {
                $h .= '<a href="' . ik_e($href) . '" style="display:inline-block;margin:0 8px 8px 0;padding:12px 20px;border-radius:9px;font-weight:bold;font-size:14px;text-decoration:none;'
                    . ($i === 0 ? 'background:#44A024;color:#fff' : 'background:#fff;color:#2f7a14;border:1px solid #44A024') . '">' . ik_e($label) . '</a>';
            }
            $h .= '</p>';
        }
        $h .= $after . '<p style="font-size:13px;color:#6b6b6b;margin:14px 0 20px">Questions? Call ' . ik_e($phone) . ' or email ' . ik_e($mail) . '.</p></td></tr>'
            . '<tr><td style="background:#f2f2f0;padding:12px 24px;font-size:12px;color:#7a7a7a">Dazu Training Institute · Nairobi and Thika · dazu.ac.ke</td></tr></table></div>';
        return $h;
    }

    function ik_queue(PDO $db, string $to, string $name, string $subject, string $html): void
    {
        require_once __DIR__ . '/ClassCalendarService.php';
        try {
            cal_queue_mail($db, $to, $name, $subject, $html);
        } catch (Throwable $e) {
            error_log('[intake mail queue] ' . $e->getMessage());
        }
    }

    function ik_first(string $name): string
    {
        return explode(' ', trim($name))[0] ?: $name;
    }

    function ik_intake_start(PDO $db, array $i): string
    {
        return $i['classes_start_on'] ? ik_fmt($db, strtotime($i['classes_start_on'] . ' 12:00 UTC'), 'D, j M Y') : 'to be confirmed';
    }

    function ik_mail_booked(PDO $db, array $b, array $intake, bool $fromWaitlist = false): void
    {
        $s = ik_course_state($db, (int) $b['course_id']);
        $buttons = [];
        if ($s && $s['enrol']) $buttons[] = ['Enrol now instead', ik_abs('courses/view.php?id=' . (int) $b['course_id'])];
        $buttons[] = ['Manage or cancel booking', ik_manage_url($b)];
        ik_queue($db, $b['email'], $b['full_name'], 'Your slot is booked: ' . $intake['name'] . ' intake (' . $b['number'] . ')',
            ik_email_html('Hello ' . ik_e(ik_first($b['full_name'])) . ', your slot is booked ✅',
                ($fromWaitlist ? 'Good news: a seat came free and it is now yours. ' : 'Thank you for booking with Dazu Training Institute. ')
                . 'We are keeping a seat for you in the ' . ik_e($intake['name']) . ' intake. You do not need to pay anything now.',
                ['Booking number' => $b['number'], 'Course' => $b['course_title'], 'Intake' => $intake['name'] . ' · starts ' . ik_intake_start($db, $intake),
                 'Classes' => ik_time_label($b) . ' ' . ik_tz_label($db), 'Status' => 'Slot booked'],
                $buttons,
                '<p style="font-size:14px;color:#2b2a2b;margin:6px 0 0"><strong>What happens next</strong><br>1. We keep your seat.<br>2. We email you before enrolment opens. You can enrol '
                . (int) ik_settings($db)['early_access_hours'] . ' hours before everyone else.<br>3. You enrol and pay, using your booking number.</p>'));
    }

    function ik_mail_waitlisted(PDO $db, array $p, array $intake): void
    {
        ik_queue($db, $p['email'], $p['full_name'], 'You are on the waiting list: ' . $intake['name'] . ' intake',
            ik_email_html('Hello ' . ik_e(ik_first($p['full_name'])) . ', you are on the waiting list',
                'The ' . ik_e($intake['name']) . ' intake for ' . ik_e($p['course_title']) . ' is full right now. When a seat comes free, we offer it to the first person on the list and email you straight away.'));
    }

    function ik_mail_invite(PDO $db, array $b, array $intake): void
    {
        $hours = (int) ik_settings($db)['early_access_hours'];
        $days = (int) ik_settings($db)['hold_days'];
        $tz = ' ' . ik_tz_label($db);
        ik_queue($db, $b['email'], $b['full_name'], 'You can enrol first: ' . $intake['name'] . ' intake is opening (' . $b['number'] . ')',
            ik_email_html('Hello ' . ik_e(ik_first($b['full_name'])) . ', you can enrol first ⚡',
                'The ' . ik_e($intake['name']) . ' intake is opening. Because you booked, you can enrol <strong>' . $hours . ' hours before everyone else</strong>. Your seat is held for you for ' . $days . ' days.',
                ['Booking number' => $b['number'], 'Course' => $b['course_title'],
                 'Early access ends' => ik_fmt($db, ik_ts($intake['enrol_opens_at']), 'D, j M Y, g:i A') . $tz,
                 'Your seat is held until' => ik_fmt($db, ik_ts($b['hold_until']), 'D, j M Y, g:i A') . $tz,
                 'Classes start' => ik_intake_start($db, $intake)],
                [['Enrol now with early access', ik_early_url($b)], ['Move or cancel', ik_manage_url($b)]],
                '<p style="font-size:13px;color:#6b6b6b;margin:6px 0 0">We will send a reminder 24 hours before your seat is released. If you do nothing, the seat goes to the next person on the list.</p>'));
    }

    function ik_mail_simple(PDO $db, array $b, string $subject, string $heading, string $body, array $buttons = []): void
    {
        ik_queue($db, $b['email'], $b['full_name'], $subject, ik_email_html('Hello ' . ik_e(ik_first($b['full_name'])) . ', ' . lcfirst($heading), $body, [], $buttons));
    }

    function ik_admissions_email(): string
    {
        $e = getenv('INTAKE_ADMISSIONS_EMAIL') ?: (function_exists('site_contact_setting_value') ? site_contact_setting_value('contact_email', '') : '');
        return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : '';
    }

    function ik_mail_admissions_new(PDO $db, array $b, array $intake): void
    {
        if (!($to = ik_admissions_email())) return;
        ik_queue($db, $to, 'Admissions', 'New booking ' . $b['number'] . ': ' . $b['full_name'] . ' (' . $intake['name'] . ')',
            ik_email_html('New intake booking', ik_e($b['full_name']) . ' booked a slot.',
                ['Booking number' => $b['number'], 'Course' => $b['course_title'], 'Intake' => $intake['name'], 'Classes' => ik_time_label($b),
                 'Phone' => $b['phone'], 'Email' => $b['email'], 'Source' => (string) $b['source']],
                [['Open Intake Bookings', ik_abs('admin/intakes/bookings.php?q=' . urlencode($b['number']))]]));
    }

    // ---------- scheduled jobs (cron every 5 minutes; every step is safe to run twice) ----------

    function ik_cron(PDO $db): array
    {
        $out = ['invites' => 0, 'reminders' => 0, 'expired' => 0, 'interest' => 0, 'summary' => 0];
        if (!ik_installed($db)) return $out;
        $intakes = ik_intakes($db, true);

        // 1. Early access invites: every open booking of an intake that reached early access (or enrolment).
        foreach ($intakes as $i) {
            if (!in_array($i['status'], ['early_access', 'enrol_open'], true)) continue;
            $st = $db->prepare("SELECT b.*, c.title AS course_title FROM intake_bookings b JOIN courses c ON c.id = b.course_id WHERE b.intake_id = ? AND b.status IN ('booked','contacted') AND b.invited_at IS NULL");
            $st->execute([(int) $i['id']]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $b) { ik_invite($db, $b, $i); $out['invites']++; }
            if (!$i['invites_sent_at']) $db->prepare('UPDATE intakes SET invites_sent_at = UTC_TIMESTAMP() WHERE id = ?')->execute([(int) $i['id']]);
        }

        // 2. Reminder 24 hours before the hold ends.
        $st = $db->query("SELECT b.*, c.title AS course_title FROM intake_bookings b JOIN courses c ON c.id = b.course_id
                          WHERE b.status = 'invited' AND b.reminder_sent_at IS NULL AND b.hold_until > UTC_TIMESTAMP() AND b.hold_until <= UTC_TIMESTAMP() + INTERVAL 24 HOUR");
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $b) {
            $u = $db->prepare('UPDATE intake_bookings SET reminder_sent_at = UTC_TIMESTAMP() WHERE id = ? AND reminder_sent_at IS NULL');
            $u->execute([(int) $b['id']]);
            if (!$u->rowCount()) continue;
            ik_mail_simple($db, $b, 'Your seat is held until ' . ik_fmt($db, ik_ts($b['hold_until']), 'D j M, g:i A') . ' (' . $b['number'] . ')', 'Your held seat ends in 24 hours',
                'Your seat in ' . ik_e($b['course_title']) . ' is held until <strong>' . ik_e(ik_fmt($db, ik_ts($b['hold_until']), 'D, j M Y, g:i A') . ' ' . ik_tz_label($db))
                . '</strong>. Enrol before then, or the seat goes to the next person.', [['Enrol now', ik_early_url($b)], ['Move or cancel', ik_manage_url($b)]]);
            ik_log_booking($db, (int) $b['id'], 'system', 'reminded', 'Hold ending reminder');
            $out['reminders']++;
        }

        // 3. Expire ended holds, release the seat, offer it to the waiting list.
        $st = $db->query("SELECT b.*, c.title AS course_title FROM intake_bookings b JOIN courses c ON c.id = b.course_id WHERE b.status = 'invited' AND b.hold_until <= UTC_TIMESTAMP()");
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $b) {
            $u = $db->prepare("UPDATE intake_bookings SET status = 'expired' WHERE id = ? AND status = 'invited'");
            $u->execute([(int) $b['id']]);
            if (!$u->rowCount()) continue;
            ik_log_booking($db, (int) $b['id'], 'system', 'expired', 'Hold ended without enrolment; seat released');
            ik_mail_simple($db, $b, 'Your held seat has been released (' . $b['number'] . ')', 'Your held seat has been released',
                'Booking ' . ik_e($b['number']) . ' was not used within ' . (int) ik_settings($db)['hold_days'] . ' days of the invite, so we gave the seat to the next person. '
                . "Don't worry: if enrolment is still open and seats are left, you can enrol now. Otherwise you can book the next intake.",
                [['See my options', ik_manage_url($b)]]);
            if (isset($intakes[(int) $b['intake_id']])) ik_offer_waitlist($db, $intakes[(int) $b['intake_id']], (int) $b['course_id']);
            $out['expired']++;
        }

        // 4. Interest list: tell people once an intake takes bookings.
        foreach ($intakes as $i) {
            if ($i['interest_notified_at'] || !in_array($i['status'], ['booking_open', 'early_access', 'enrol_open'], true)) continue;
            $u = $db->prepare('UPDATE intakes SET interest_notified_at = UTC_TIMESTAMP() WHERE id = ? AND interest_notified_at IS NULL');
            $u->execute([(int) $i['id']]);
            if (!$u->rowCount()) continue;
            $rows = $db->query("SELECT l.*, c.title AS course_title FROM intake_interest_list l LEFT JOIN courses c ON c.id = l.course_id WHERE l.notified_at IS NULL")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $p) {
                $db->prepare('UPDATE intake_interest_list SET notified_at = UTC_TIMESTAMP() WHERE id = ?')->execute([(int) $p['id']]);
                ik_queue($db, $p['email'], $p['name'], 'The ' . $i['name'] . ' intake is open for booking',
                    ik_email_html('Hello ' . ik_e(ik_first($p['name'])) . ', the next intake is here',
                        'You asked us to tell you when the next intake opens. You can now book your slot in the <strong>' . ik_e($i['name']) . '</strong> intake'
                        . ($p['course_title'] ? ' for ' . ik_e($p['course_title']) : '') . '. Booking is free.',
                        [], [['Book my slot', ik_abs($p['course_id'] ? 'intake/book.php?course=' . (int) $p['course_id'] : 'courses/index.php')]]));
                $out['interest']++;
            }
        }

        // 5. Daily summary for admissions (once a day, after 7 AM in the intake zone).
        $today = ik_fmt($db, time(), 'Y-m-d');
        if ((int) ik_fmt($db, time(), 'G') >= 7 && ik_settings($db)['last_summary_on'] !== $today && ($to = ik_admissions_email())) {
            $u = $db->prepare('UPDATE intake_settings SET last_summary_on = ? WHERE id = 1 AND (last_summary_on IS NULL OR last_summary_on <> ?)');
            $u->execute([$today, $today]);
            if ($u->rowCount()) {
                $new = (int) $db->query("SELECT COUNT(*) FROM intake_bookings WHERE created_at >= NOW() - INTERVAL 1 DAY")->fetchColumn();
                $soon = (int) $db->query("SELECT COUNT(*) FROM intake_bookings WHERE status = 'invited' AND hold_until BETWEEN UTC_TIMESTAMP() AND UTC_TIMESTAMP() + INTERVAL 1 DAY")->fetchColumn();
                $exp = (int) $db->query("SELECT COUNT(*) FROM intake_booking_log WHERE action = 'expired' AND created_at >= NOW() - INTERVAL 1 DAY")->fetchColumn();
                $notEnrolled = (int) $db->query("SELECT COUNT(*) FROM intake_bookings WHERE status IN ('booked','contacted','invited')")->fetchColumn();
                ik_queue($db, $to, 'Admissions', 'Intake bookings today: ' . $new . ' new, ' . $soon . ' holds ending',
                    ik_email_html('Daily intake summary', 'Here is what happened with intake bookings in the last 24 hours.',
                        ['New bookings' => (string) $new, 'Holds ending in the next 24 hours' => (string) $soon, 'Holds expired' => (string) $exp, 'Booked but not enrolled yet' => (string) $notEnrolled],
                        [['Open Intake Bookings', ik_abs('admin/intakes/bookings.php')]]));
                $out['summary'] = 1;
            }
        }
        return $out;
    }

    // ---------- admin: intakes ----------

    /** Validate + save an intake from the admin form. Returns ['ok', 'id', 'warnings' => [...], 'error']. */
    function ik_save_intake(PDO $db, array $in, ?array $existing): array
    {
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) return ['ok' => false, 'error' => 'Give the intake a name, e.g. "January 2027".'];
        $f = [
            'name' => $name,
            'short_label' => mb_substr(trim((string) ($in['short_label'] ?? '')), 0, 30) ?: null,
            'bookings_open_at' => ik_local_to_utc($db, (string) ($in['bookings_open_at'] ?? '')),
            'early_access_at' => ik_local_to_utc($db, (string) ($in['early_access_at'] ?? '')),
            'enrol_opens_at' => ik_local_to_utc($db, (string) ($in['enrol_opens_at'] ?? '')),
            'enrol_closes_at' => ik_local_to_utc($db, (string) ($in['enrol_closes_at'] ?? ''), true),
            'classes_start_on' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($in['classes_start_on'] ?? '')) ? $in['classes_start_on'] : null,
            'seats_default' => ($in['seats_default'] ?? '') === '' ? null : max(1, (int) $in['seats_default']),
            'override_mode' => in_array($in['override_mode'] ?? 'auto', ['auto', 'open', 'closed'], true) ? ($in['override_mode'] ?? 'auto') : 'auto',
        ];
        // A closing time typed without seconds means the end of that minute (11:59 PM → 11:59:59 PM).
        if ($f['enrol_closes_at'] && str_ends_with($f['enrol_closes_at'], ':00') && preg_match('/T\d{2}:\d{2}$/', (string) $in['enrol_closes_at'])) {
            $f['enrol_closes_at'] = gmdate('Y-m-d H:i:s', ik_ts($f['enrol_closes_at']) + 59);
        }
        if (!$f['bookings_open_at'] || !$f['enrol_opens_at'] || !$f['enrol_closes_at']) return ['ok' => false, 'error' => 'Fill in when bookings open, when enrolment opens and when it closes.'];
        $bo = ik_ts($f['bookings_open_at']); $eo = ik_ts($f['enrol_opens_at']); $ec = ik_ts($f['enrol_closes_at']);
        $ea = $f['early_access_at'] ? ik_ts($f['early_access_at']) : $eo - (int) ik_settings($db)['early_access_hours'] * 3600;
        if ($ec <= $eo) return ['ok' => false, 'error' => 'Enrolment must close after it opens.'];
        if ($eo < $bo) return ['ok' => false, 'error' => 'Bookings must open before enrolment opens.'];
        if ($ea > $eo) return ['ok' => false, 'error' => 'Early access must start before enrolment opens.'];
        $warnings = [];
        if ($ea < $bo) $warnings[] = 'Early access starts before bookings open, so nobody will have booked yet when it starts.';
        if ($f['classes_start_on'] && strtotime($f['classes_start_on'] . ' 23:59:59 UTC') < $eo) $warnings[] = 'Classes start before enrolment opens.';
        foreach (ik_intakes($db, true) as $o) {
            if ($existing && (int) $o['id'] === (int) $existing['id']) continue;
            if ($eo < ik_ts($o['enrol_closes_at']) && $ec > ik_ts($o['enrol_opens_at'])) $warnings[] = 'Its enrolment dates overlap with the ' . $o['name'] . ' intake.';
        }
        if ($warnings && empty($in['confirm_warnings'])) return ['ok' => false, 'warnings' => $warnings, 'error' => 'Please check the dates.'];

        $cols = array_keys($f);
        if ($existing) {
            $db->prepare('UPDATE intakes SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?')->execute([...array_values($f), (int) $existing['id']]);
            $id = (int) $existing['id'];
            $old = array_intersect_key($existing, $f);
            $changed = array_filter($f, fn($v, $k) => (string) $v !== (string) ($old[$k] ?? ''), ARRAY_FILTER_USE_BOTH);
            if ($changed) ik_log_intake($db, $id, 'edited', array_intersect_key($old, $changed), $changed, trim((string) ($in['reason'] ?? '')) ?: null);
        } else {
            $db->prepare('INSERT INTO intakes (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute(array_values($f));
            $id = (int) $db->lastInsertId();
            ik_log_intake($db, $id, 'created', null, $f, trim((string) ($in['reason'] ?? '')) ?: null);
        }
        // Per-course seats and exclusions.
        $db->prepare('DELETE FROM intake_course_seats WHERE intake_id = ?')->execute([$id]);
        foreach ((array) ($in['course_seats'] ?? []) as $cid => $seats) {
            $excl = !empty($in['course_excluded'][$cid]);
            $seats = trim((string) $seats);
            if ($seats === '' && !$excl) continue;
            $db->prepare('INSERT INTO intake_course_seats (intake_id, course_id, seats, excluded) VALUES (?, ?, ?, ?)')->execute([$id, (int) $cid, $seats === '' ? null : max(0, (int) $seats), $excl ? 1 : 0]);
        }
        ik_intakes($db, true);
        ik_course_rule($db, 0, 0, true);
        return ['ok' => true, 'id' => $id, 'warnings' => $warnings];
    }

    function ik_extend(PDO $db, array $i, string $how, string $custom, string $reason): array
    {
        if (trim($reason) === '') return ['ok' => false, 'error' => 'Give a reason. It is saved in the log.'];
        $base = max(ik_ts($i['enrol_closes_at']), 0);
        if ($how === 'week') $new = $base + 7 * 86400;
        elseif ($how === 'month') $new = (new DateTimeImmutable('@' . $base))->setTimezone(ik_tz($db))->modify('+1 month')->getTimestamp();
        else {
            $u = ik_local_to_utc($db, $custom, true);
            if (!$u) return ['ok' => false, 'error' => 'Choose the new closing date.'];
            $new = ik_ts($u);
            if (preg_match('/T\d{2}:\d{2}$/', $custom)) $new += 59;
        }
        if ($new <= time()) return ['ok' => false, 'error' => 'The new closing date must be in the future.'];
        if ($new <= ik_ts($i['enrol_opens_at'])) return ['ok' => false, 'error' => 'The new closing date must be after enrolment opens.'];
        $newUtc = gmdate('Y-m-d H:i:s', $new);
        $db->prepare("UPDATE intakes SET enrol_closes_at = ?, override_mode = IF(override_mode = 'closed', 'auto', override_mode) WHERE id = ?")->execute([$newUtc, (int) $i['id']]);
        ik_intakes($db, true);
        ik_log_intake($db, (int) $i['id'], 'extended', ['enrol_closes_at' => $i['enrol_closes_at'], 'override_mode' => $i['override_mode']], ['enrol_closes_at' => $newUtc], $reason);
        return ['ok' => true, 'closes' => $new];
    }

    function ik_set_override(PDO $db, array $i, string $mode, string $reason, string $action): array
    {
        if (trim($reason) === '') return ['ok' => false, 'error' => 'Give a reason. It is saved in the log.'];
        if ($action === 'reopen' && ik_ts($i['enrol_closes_at']) <= time()) {
            return ['ok' => false, 'error' => 'The closing date has passed. Use Extend to choose a new closing date, which reopens enrolment.'];
        }
        $db->prepare('UPDATE intakes SET override_mode = ? WHERE id = ?')->execute([$mode, (int) $i['id']]);
        ik_intakes($db, true);
        ik_log_intake($db, (int) $i['id'], $action, ['override_mode' => $i['override_mode']], ['override_mode' => $mode], $reason);
        return ['ok' => true];
    }

    function ik_save_rules(PDO $db, int $hours, int $days, string $tz): array
    {
        if ($hours < 1 || $hours > 720) return ['ok' => false, 'error' => 'Early access must be between 1 and 720 hours.'];
        if ($days < 1 || $days > 60) return ['ok' => false, 'error' => 'The hold must be between 1 and 60 days.'];
        if ($days * 24 <= $hours) return ['ok' => false, 'error' => 'The hold must be longer than early access, so a held seat lasts into normal enrolment.'];
        try { new DateTimeZone($tz); } catch (Throwable $e) { return ['ok' => false, 'error' => 'Unknown time zone.']; }
        $old = ik_settings($db);
        $db->prepare('UPDATE intake_settings SET early_access_hours = ?, hold_days = ?, timezone = ? WHERE id = 1')->execute([$hours, $days, $tz]);
        ik_log_intake($db, null, 'rules', ['early_access_hours' => (int) $old['early_access_hours'], 'hold_days' => (int) $old['hold_days'], 'timezone' => $old['timezone']],
            ['early_access_hours' => $hours, 'hold_days' => $days, 'timezone' => $tz], 'Rules for all intakes');
        return ['ok' => true];
    }
}
