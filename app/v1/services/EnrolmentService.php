<?php
// app/v1/services/EnrolmentService.php
// Simple enrolment (migration 086). The admin opens enrolment for one course or for all courses, optionally with a
// number of slots. While it is open, that course shows "Enrolment open" and a "Book a slot" button; every other course
// looks exactly as normal. Students leave a few details and get an email. Bookings past the slots are still taken
// (status "waiting") and the admin allocates them later. The normal Apply form is never blocked by this.
// Payment for a slot can be added later: enrolment_bookings already has payment_status / payment_amount / payment_reference.

require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/../helpers/course_share.php';

if (!function_exists('en_installed')) {
    function en_installed(PDO $db): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                $db->query("SELECT 1 FROM enrolment_openings LIMIT 0");
                $ok = true;
            } catch (Throwable $e) {
                $ok = false;
            }
        }
        return $ok;
    }

    function en_now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Africa/Nairobi')))->format('Y-m-d H:i:s');
    }

    /** Close openings whose closing time has passed (called on every read, so no cron is needed). */
    function en_autoclose(PDO $db): void
    {
        $db->prepare("UPDATE enrolment_openings SET status = 'closed', closed_at = closes_at WHERE status = 'open' AND closes_at IS NOT NULL AND closes_at <= ?")
           ->execute([en_now()]);
    }

    /** The open opening that applies to a course: its own opening first, else one for all courses. Null = enrolment not open. */
    function en_open_for(PDO $db, int $courseId): ?array
    {
        if (!en_installed($db)) return null;
        static $cache = [];
        if (!array_key_exists($courseId, $cache)) {
            en_autoclose($db);
            $st = $db->prepare("SELECT * FROM enrolment_openings WHERE status = 'open' AND (course_id = ? OR course_id IS NULL) ORDER BY course_id IS NULL, id DESC LIMIT 1");
            $st->execute([$courseId]);
            $cache[$courseId] = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        return $cache[$courseId];
    }

    /** Slots booked (not cancelled) for a course in an opening. */
    function en_taken(PDO $db, int $openingId, int $courseId): int
    {
        $st = $db->prepare("SELECT COUNT(*) FROM enrolment_bookings WHERE opening_id = ? AND course_id = ? AND status <> 'cancelled'");
        $st->execute([$openingId, $courseId]);
        return (int) $st->fetchColumn();
    }

    /** ['open' => bool, 'opening' => row, 'seats' => ?int, 'taken' => int, 'left' => ?int] for a course. */
    function en_state(PDO $db, int $courseId): array
    {
        $o = en_open_for($db, $courseId);
        if (!$o) return ['open' => false];
        $taken = en_taken($db, (int) $o['id'], $courseId);
        $seats = $o['seats'] !== null ? (int) $o['seats'] : null;
        return ['open' => true, 'opening' => $o, 'seats' => $seats, 'taken' => $taken, 'left' => $seats === null ? null : max(0, $seats - $taken)];
    }

    /** Badge text for a course card, or null (no badge when enrolment is not open). */
    function en_badge(PDO $db, int $courseId): ?string
    {
        $s = en_state($db, $courseId);
        if (!$s['open']) return null;
        if ($s['left'] !== null && $s['left'] > 0 && $s['left'] <= 5) return 'Enrolment open · ' . $s['left'] . ' slot' . ($s['left'] === 1 ? '' : 's') . ' left';
        return 'Enrolment open';
    }

    function en_e($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    function en_date(?string $d, string $f = 'D j M Y'): string
    {
        return $d ? date($f, strtotime($d)) : '';
    }

    /**
     * Book a slot. $in: full_name, email, phone, study_mode, campus, note. Accepts bookings past the slots (waiting).
     * @return array{ok:bool, error?:string, booking?:array}
     */
    function en_book(PDO $db, array $course, array $in, string $ip): array
    {
        $state = en_state($db, (int) $course['id']);
        if (!$state['open']) return ['ok' => false, 'error' => 'Booking for this course is not open right now.'];
        $name = trim(preg_replace('/\s+/', ' ', (string) ($in['full_name'] ?? '')));
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        $phone = preg_replace('/[^\d+]/', '', (string) ($in['phone'] ?? ''));
        $mode = ($in['study_mode'] ?? '') === 'physical' ? 'physical' : 'online';
        $campus = $mode === 'physical' && in_array($in['campus'] ?? '', ['thika', 'nairobi'], true) ? $in['campus'] : null;
        if (mb_strlen($name) < 3) return ['ok' => false, 'error' => 'Enter your full name.'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Enter a valid email address.'];
        if (strlen(preg_replace('/\D/', '', $phone)) < 9) return ['ok' => false, 'error' => 'Enter a valid phone number.'];
        if ($mode === 'physical' && !$campus) return ['ok' => false, 'error' => 'Choose the campus: Thika or Nairobi.'];
        $o = $state['opening'];
        $st = $db->prepare("SELECT * FROM enrolment_bookings WHERE opening_id = ? AND course_id = ? AND email = ? AND status <> 'cancelled' LIMIT 1");
        $st->execute([(int) $o['id'], (int) $course['id'], $email]);
        if ($old = $st->fetch(PDO::FETCH_ASSOC)) {
            return ['ok' => true, 'booking' => $old + ['course_title' => $course['title'], 'opening' => $o], 'again' => true];
        }
        $st = $db->prepare("SELECT COUNT(*) FROM enrolment_bookings WHERE ip = ? AND created_at >= ?");
        $st->execute([$ip, date('Y-m-d H:i:s', strtotime(en_now()) - 3600)]);
        if ((int) $st->fetchColumn() >= 10) return ['ok' => false, 'error' => 'Too many bookings from this device. Please try again later or call us.'];

        $db->beginTransaction();
        try {
            // Count again inside the transaction, so two people booking the last slot cannot both get it.
            $db->prepare("SELECT id FROM enrolment_openings WHERE id = ? FOR UPDATE")->execute([(int) $o['id']]);
            $taken = en_taken($db, (int) $o['id'], (int) $course['id']);
            $status = $o['seats'] !== null && $taken >= (int) $o['seats'] ? 'waiting' : 'confirmed';
            do {
                $ref = 'BK-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
                $chk = $db->prepare("SELECT 1 FROM enrolment_bookings WHERE reference = ?");
                $chk->execute([$ref]);
            } while ($chk->fetchColumn());
            $db->prepare("INSERT INTO enrolment_bookings (opening_id, course_id, reference, full_name, email, phone, study_mode, campus, note, status, ip, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
               ->execute([(int) $o['id'], (int) $course['id'], $ref, mb_substr($name, 0, 150), $email, mb_substr($phone, 0, 30), $mode, $campus,
                          trim((string) ($in['note'] ?? '')) !== '' ? mb_substr(trim((string) $in['note']), 0, 500) : null, $status, $ip, en_now()]);
            $id = (int) $db->lastInsertId();
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[enrolment] book: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'We could not save your booking. Please try again.'];
        }
        $b = en_booking($db, $id);
        en_mail_booked($db, $b);
        return ['ok' => true, 'booking' => $b];
    }

    function en_booking(PDO $db, int $id): ?array
    {
        $st = $db->prepare("SELECT b.*, c.title AS course_title, o.title AS opening_title, o.classes_start, o.seats, o.course_id AS opening_course
                            FROM enrolment_bookings b JOIN courses c ON c.id = b.course_id JOIN enrolment_openings o ON o.id = b.opening_id WHERE b.id = ?");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* ---------- Admin ---------- */

    /** @return array{ok:bool, error?:string, id?:int} */
    function en_open(PDO $db, array $in, int $actor): array
    {
        $courseId = (int) ($in['course_id'] ?? 0) ?: null;
        $title = trim((string) ($in['title'] ?? ''));
        $seats = trim((string) ($in['seats'] ?? ''));
        if ($title === '') $title = 'Intake ' . date('F Y');
        if ($seats !== '' && (!ctype_digit($seats) || (int) $seats < 1)) return ['ok' => false, 'error' => 'Slots must be a whole number, or leave it empty for no limit.'];
        $start = trim((string) ($in['classes_start'] ?? '')) ?: null;
        $closes = trim((string) ($in['closes_at'] ?? '')) ?: null;
        if ($start && !DateTimeImmutable::createFromFormat('!Y-m-d', $start)) return ['ok' => false, 'error' => 'Choose a valid start date.'];
        if ($closes) {
            $c = DateTimeImmutable::createFromFormat('!Y-m-d', $closes);
            if (!$c) return ['ok' => false, 'error' => 'Choose a valid closing date.'];
            $closes = $c->format('Y-m-d') . ' 23:59:59';
            if ($closes <= en_now()) return ['ok' => false, 'error' => 'The closing date has already passed.'];
        }
        // One open opening per course (and one for all courses): opening again replaces the open one's settings.
        $st = $db->prepare("SELECT id FROM enrolment_openings WHERE status = 'open' AND " . ($courseId ? "course_id = ?" : "course_id IS NULL"));
        $st->execute($courseId ? [$courseId] : []);
        if ($existing = (int) $st->fetchColumn()) {
            $db->prepare("UPDATE enrolment_openings SET title = ?, seats = ?, classes_start = ?, closes_at = ? WHERE id = ?")
               ->execute([mb_substr($title, 0, 120), $seats === '' ? null : (int) $seats, $start, $closes, $existing]);
            return ['ok' => true, 'id' => $existing, 'updated' => true];
        }
        $db->prepare("INSERT INTO enrolment_openings (course_id, title, seats, classes_start, closes_at, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, 'open', ?, ?)")
           ->execute([$courseId, mb_substr($title, 0, 120), $seats === '' ? null : (int) $seats, $start, $closes, $actor, en_now()]);
        return ['ok' => true, 'id' => (int) $db->lastInsertId()];
    }

    function en_openings(PDO $db): array
    {
        en_autoclose($db);
        return $db->query("SELECT o.*, c.title AS course_title,
                                  (SELECT COUNT(*) FROM enrolment_bookings b WHERE b.opening_id = o.id AND b.status = 'confirmed') AS confirmed,
                                  (SELECT COUNT(*) FROM enrolment_bookings b WHERE b.opening_id = o.id AND b.status = 'waiting') AS waiting
                           FROM enrolment_openings o LEFT JOIN courses c ON c.id = o.course_id
                           ORDER BY o.status = 'open' DESC, o.id DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    function en_set_status(PDO $db, int $id, bool $open): void
    {
        if ($open) {
            // Reopening: drop a passed closing date, and close any other open one for the same course.
            $st = $db->prepare("SELECT course_id FROM enrolment_openings WHERE id = ?");
            $st->execute([$id]);
            $cid = $st->fetchColumn();
            $db->prepare("UPDATE enrolment_openings SET status = 'closed', closed_at = ? WHERE status = 'open' AND id <> ? AND " . ($cid === null ? "course_id IS NULL" : "course_id = ?"))
               ->execute($cid === null ? [en_now(), $id] : [en_now(), $id, (int) $cid]);
            $db->prepare("UPDATE enrolment_openings SET status = 'open', closed_at = NULL, closes_at = IF(closes_at IS NOT NULL AND closes_at <= ?, NULL, closes_at) WHERE id = ?")
               ->execute([en_now(), $id]);
        } else {
            $db->prepare("UPDATE enrolment_openings SET status = 'closed', closed_at = ? WHERE id = ?")->execute([en_now(), $id]);
        }
    }

    /** Delete an opening and all its bookings. */
    function en_delete_opening(PDO $db, int $id): void
    {
        $db->prepare("DELETE FROM enrolment_bookings WHERE opening_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM enrolment_openings WHERE id = ?")->execute([$id]);
    }

    /** Bookings, newest first. Filters: opening, course, status, q (name/email/phone/reference). */
    function en_bookings(PDO $db, array $f = []): array
    {
        $sql = "SELECT b.*, c.title AS course_title, o.title AS opening_title FROM enrolment_bookings b
                JOIN courses c ON c.id = b.course_id JOIN enrolment_openings o ON o.id = b.opening_id WHERE 1 = 1";
        $args = [];
        if (!empty($f['opening'])) { $sql .= " AND b.opening_id = ?"; $args[] = (int) $f['opening']; }
        if (!empty($f['course'])) { $sql .= " AND b.course_id = ?"; $args[] = (int) $f['course']; }
        if (!empty($f['status'])) { $sql .= " AND b.status = ?"; $args[] = $f['status']; }
        if (!empty($f['q'])) {
            $sql .= " AND (b.full_name LIKE ? OR b.email LIKE ? OR b.phone LIKE ? OR b.reference LIKE ?)";
            array_push($args, ...array_fill(0, 4, '%' . $f['q'] . '%'));
        }
        $st = $db->prepare($sql . " ORDER BY b.id DESC LIMIT 1000");
        $st->execute($args);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** confirm (give a slot to a waiting booking) or cancel. Tells the student when a slot is given. */
    function en_set_booking(PDO $db, int $id, string $status): bool
    {
        if (!in_array($status, ['confirmed', 'waiting', 'cancelled'], true)) return false;
        $b = en_booking($db, $id);
        if (!$b) return false;
        $db->prepare("UPDATE enrolment_bookings SET status = ? WHERE id = ?")->execute([$status, $id]);
        if ($status === 'confirmed' && $b['status'] === 'waiting') en_mail_booked($db, en_booking($db, $id), true);
        return true;
    }

    function en_delete_booking(PDO $db, int $id): void
    {
        $db->prepare("DELETE FROM enrolment_bookings WHERE id = ?")->execute([$id]);
    }

    /* ---------- Email ---------- */

    function en_mail_booked(PDO $db, array $b, bool $allocated = false): void
    {
        $confirmed = $b['status'] === 'confirmed';
        $subject = $confirmed ? 'Your slot is booked: ' . $b['course_title'] : 'We received your booking: ' . $b['course_title'];
        $first = ucfirst(strtolower(strtok(trim($b['full_name']), ' ') ?: 'there'));
        $rows = [['Course', $b['course_title']], ['Booking number', $b['reference']], ['Intake', $b['opening_title']]];
        if ($b['classes_start']) $rows[] = ['Classes start', en_date($b['classes_start'])];
        $rows[] = ['Study mode', $b['study_mode'] === 'physical' ? 'Physical, ' . ucfirst((string) $b['campus']) . ' campus' : 'Online'];
        $intro = $allocated ? 'Good news: a slot is now yours.'
            : ($confirmed ? 'Your slot is booked. We look forward to having you.' : 'All slots for this intake are taken, but your booking is saved. We will contact you as soon as we can give you a slot.');
        $html = '<div style="background:#f4f5f1;padding:24px 12px;font-family:Poppins,Arial,sans-serif;color:#1d1b1c"><div style="max-width:560px;margin:0 auto;background:#fff;border:1px solid #e9ebe6;border-radius:16px;overflow:hidden">'
            . '<div style="background:#373435;padding:18px 22px;color:#fff;font-weight:700">Dazu Training Institute</div><div style="padding:24px 26px;font-size:14px;line-height:1.6">'
            . '<p style="margin:0 0 10px;font-weight:600">Hello ' . en_e($first) . ',</p><p>' . en_e($intro) . '</p><table style="width:100%;border-collapse:collapse;margin:14px 0">';
        foreach ($rows as [$k, $v]) $html .= '<tr><td style="padding:8px;border-bottom:1px solid #eee;color:#6b7067">' . en_e($k) . '</td><td style="padding:8px;border-bottom:1px solid #eee;font-weight:600;text-align:right">' . en_e($v) . '</td></tr>';
        $html .= '</table><p>Keep your booking number. To finish enrolling, apply on the course page or call or WhatsApp us on 0722 922 454.</p>'
            . '<p style="margin:20px 0 8px"><a href="' . en_e(course_share_absolute_url(url('courses/view?id=' . (int) $b['course_id']))) . '" style="display:inline-block;background:#44A024;color:#fff;padding:11px 26px;border-radius:8px;text-decoration:none;font-weight:700;font-size:13px">VIEW THE COURSE</a></p>'
            . '</div><div style="padding:14px 26px;border-top:1px solid #e9ebe6;color:#6b7067;font-size:12px;text-align:center">Dazu Training Institute | 0722 922 454 | dazu.ac.ke</div></div></div>';
        $msgs = [['to_email' => $b['email'], 'to_name' => $b['full_name'], 'subject' => $subject, 'html' => $html]];
        // A copy to the admissions inbox, so new bookings are seen without opening the admin.
        try {
            $cfg = require __DIR__ . '/../config/mail.php';
            $admissions = function_exists('site_contact_setting_value') ? (string) site_contact_setting_value('contact_email', '') : '';
            if (!$allocated && filter_var($admissions, FILTER_VALIDATE_EMAIL)) {
                $msgs[] = ['to_email' => $admissions, 'to_name' => 'Admissions', 'subject' => 'New booking ' . $b['reference'] . ': ' . $b['course_title'] . ($confirmed ? '' : ' (waiting for a slot)'),
                           'html' => '<p>' . en_e($b['full_name']) . ' · ' . en_e($b['email']) . ' · ' . en_e($b['phone']) . '</p><p>' . en_e($b['course_title']) . ' · ' . en_e($b['study_mode']) . ($b['campus'] ? ' (' . en_e($b['campus']) . ')' : '') . '</p>'];
            }
            register_shutdown_function(function () use ($db, $cfg, $msgs, $b) {
                if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
                $r = (new Mailer($cfg))->sendBatch($msgs);
                if ($r['sent'] > 0) $db->prepare("UPDATE enrolment_bookings SET emailed_at = ? WHERE id = ?")->execute([en_now(), (int) $b['id']]);
            });
        } catch (Throwable $e) {
            error_log('[enrolment] mail: ' . $e->getMessage());
        }
    }
}
