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





    /**
     * The stage of an opening, worked out by itself (all times are East Africa Time): upcoming | bookings | early | open | closed.
     * early = early access: only people who booked (and paid) can enroll; everyone else still sees Book now.
     * override_mode 'closed' / 'open' wins. An opening with no bookings_open_at and no opens_at is an older one and keeps working as before:
     * enrolment open while its status is open.
     */
    function en_stage(array $o, ?string $now = null, ?int $earlyHours = null): string
    {
        $now = $now ?? en_now();
        $mode = (string) ($o['override_mode'] ?? 'auto');
        if ($mode === 'closed') return 'closed';
        if ($mode === 'open') return 'open';
        if (($o['status'] ?? 'open') === 'closed') return 'closed';
        $closes = (string) ($o['closes_at'] ?? '');
        if ($closes !== '' && $closes <= $now) return 'closed';
        if (empty($o['bookings_open_at']) && empty($o['opens_at'])) return 'open';   // an older opening
        if (!empty($o['opens_at']) && $o['opens_at'] <= $now) return 'open';
        if (!empty($o['opens_at']) && en_early_start($o, $earlyHours) <= $now) return 'early';
        if (empty($o['bookings_open_at']) || $o['bookings_open_at'] <= $now) return 'bookings';
        return 'upcoming';
    }

    /** When early access starts for an opening: its own early_access_at, else enrolment opens minus the early-access hours (48 by default). */
    function en_early_start(array $o, ?int $hours = null): string
    {
        if (!empty($o['early_access_at'])) return (string) $o['early_access_at'];
        $d = new DateTimeImmutable((string) $o['opens_at'], new DateTimeZone('UTC'));   // plain arithmetic on the local clock time
        return $d->modify('-' . max(1, $hours ?? 48) . ' hours')->format('Y-m-d H:i:s');
    }

    /** The early-access hours from Book now settings (48 by default). Cached for the request. */
    function en_early_hours(PDO $db): int
    {
        static $h = null;
        if ($h === null) {
            $h = 48;
            try {
                $j = json_decode((string) en_setting_model($db)->get('en_fee_cfg'), true);
                if (is_array($j) && !empty($j['early_hours'])) $h = max(1, (int) $j['early_hours']);
            } catch (Throwable $e) {
            }
        }
        return $h;
    }

    if (!defined('EN_STAGE_LABELS')) define('EN_STAGE_LABELS', ['upcoming' => 'Upcoming', 'bookings' => 'Bookings open', 'early' => 'Early access', 'open' => 'Enrolment open', 'closed' => 'Closed']);

    /** Every opening that applies to a course (its own, or for all courses), newest first, each with its 'stage'. */
    function en_openings_for(PDO $db, int $courseId): array
    {
        if (!en_installed($db)) return [];
        en_autoclose($db);
        $st = $db->prepare("SELECT * FROM enrolment_openings WHERE course_id = ? OR course_id IS NULL ORDER BY id DESC");
        $st->execute([$courseId]);
        $now = en_now();
        $hours = en_early_hours($db);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $o) { $o['stage'] = en_stage($o, $now, $hours); $out[] = $o; }
        return $out;
    }

    /** The opening whose enrolment is open for a course (its own first, else the one for all courses), or null. */
    function en_open_for(PDO $db, int $courseId): ?array
    {
        static $cache = [];
        if (!array_key_exists($courseId, $cache)) {
            $cache[$courseId] = null;
            $best = null;
            foreach (en_openings_for($db, $courseId) as $o) {
                if ($o['stage'] !== 'open') continue;
                if ($best === null || ($best['course_id'] === null && $o['course_id'] !== null)) $best = $o;
            }
            $cache[$courseId] = $best;
        }
        return $cache[$courseId];
    }

    /** Seats for a course in an opening: ['limit' => ?int (null = no limit), 'taken' => int, 'left' => ?int]. */
    function en_seat_info(PDO $db, array $o, int $courseId): array
    {
        $limit = $o['seats'] !== null ? (int) $o['seats'] : null;
        try {
            $st = $db->prepare("SELECT seats FROM enrolment_opening_seats WHERE opening_id = ? AND course_id = ?");
            $st->execute([(int) $o['id'], $courseId]);
            if (($v = $st->fetchColumn()) !== false) $limit = (int) $v;
        } catch (Throwable $e) {
        }
        $taken = en_taken($db, (int) $o['id'], $courseId);
        return ['limit' => $limit, 'taken' => $taken, 'left' => $limit === null ? null : max(0, $limit - $taken)];
    }

    /** Slots held for a course in an opening: confirmed bookings and bookings whose payment code is waiting for the office. */
    function en_taken(PDO $db, int $openingId, int $courseId): int
    {
        $st = $db->prepare("SELECT COUNT(*) FROM enrolment_bookings WHERE opening_id = ? AND course_id = ? AND (status IN ('confirmed','payment_received','converted') OR (status = 'waiting' AND offered_at IS NOT NULL))");
        $st->execute([$openingId, $courseId]);
        return (int) $st->fetchColumn();
    }

    /**
     * What a course page needs. 'open' => bool (enrolment is open), 'opening' => the open opening, 'seats'/'taken'/'left' for it,
     * 'next' => the opening to show Book now for (a bookable one, else an upcoming one), 'bookable' => openings taking bookings,
     * 'last_closed' => the opening that closed most recently, 'upcoming' => the next opening that has not opened bookings yet.
     */
    function en_state(PDO $db, int $courseId): array
    {
        $list = en_openings_for($db, $courseId);
        $open = en_open_for($db, $courseId);
        $s = ['open' => (bool) $open, 'opening' => $open, 'seats' => null, 'taken' => 0, 'left' => null, 'next' => null, 'bookable' => [], 'last_closed' => null, 'upcoming' => null, 'any' => (bool) $list, 'early' => null];
        if ($open) {
            $i = en_seat_info($db, $open, $courseId);
            $s['seats'] = $i['limit']; $s['taken'] = $i['taken']; $s['left'] = $i['left'];
        }
        $up = [];
        foreach ($list as $o) {
            if (in_array($o['stage'], ['bookings', 'early', 'open'], true)) $s['bookable'][] = $o;
            if ($o['stage'] === 'upcoming') $up[] = $o;
            if ($o['stage'] === 'early' && $s['early'] === null) $s['early'] = $o;
            if ($o['stage'] === 'closed' && ($s['last_closed'] === null || ($o['closes_at'] ?? '') > ($s['last_closed']['closes_at'] ?? ''))) $s['last_closed'] = $o;
        }
        usort($s['bookable'], fn($a, $b) => strcmp((string) ($a['opens_at'] ?: $a['classes_start'] ?: '9'), (string) ($b['opens_at'] ?: $b['classes_start'] ?: '9')));
        usort($up, fn($a, $b) => strcmp((string) $a['bookings_open_at'], (string) $b['bookings_open_at']));
        $s['upcoming'] = $up[0] ?? null;
        // Book now is for an opening still to come (stage bookings); an older-style open opening is its own Book now target.
        foreach ($s['bookable'] as $o) { if (in_array($o['stage'], ['bookings', 'early'], true)) { $s['next'] = $o; break; } }
        if (!$s['next'] && $open && empty($open['opens_at']) && empty($open['bookings_open_at'])) $s['next'] = $open;
        return $s;
    }

    /** Badge text for a course card, or null. */
    function en_badge(PDO $db, int $courseId): ?string
    {
        $s = en_state($db, $courseId);
        if ($s['open']) {
            if ($s['left'] !== null && $s['left'] > 0 && $s['left'] <= 5) return 'Enrolment open · ' . $s['left'] . ' slot' . ($s['left'] === 1 ? '' : 's') . ' left';
            return 'Enrolment open';
        }
        return $s['next'] ? 'Book now' : null;
    }

    /**
     * Book now. $in: opening_id, full_name, email, phone, phone2, study_mode, class_time, campus, consent, pay_method, pay_code.
     * The booking fee is a must: a valid, unused payment code is required (checked again here, the button is only a convenience).
     * If the course is full the person joins the waiting list instead, with no payment until a slot is offered.
     * @return array{ok:bool, error?:string, booking?:array, waitlisted?:bool, duplicate?:array, pay_error?:bool}
     */
    function en_book(PDO $db, array $course, array $in, string $ip): array
    {
        $courseId = (int) $course['id'];
        $state = en_state($db, $courseId);
        $o = null;
        foreach ($state['bookable'] as $cand) if ((int) $cand['id'] === (int) ($in['opening_id'] ?? 0)) $o = $cand;
        if (!$o) return ['ok' => false, 'error' => 'That intake is not taking bookings right now. Please choose another.'];

        $name = trim(preg_replace('/\s+/', ' ', (string) ($in['full_name'] ?? '')));
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        $phone = en_phone((string) ($in['phone'] ?? ''));
        $phone2Raw = trim((string) ($in['phone2'] ?? ''));
        $phone2 = $phone2Raw === '' ? null : en_phone($phone2Raw);
        $mode = ($in['study_mode'] ?? '') === 'physical' ? 'physical' : (($in['study_mode'] ?? '') === 'online' ? 'online' : '');
        $time = (string) ($in['class_time'] ?? '');
        $campus = $mode === 'physical' && in_array($in['campus'] ?? '', ['thika', 'nairobi'], true) ? (string) $in['campus'] : '';
        if (mb_strlen($name) < 3 || mb_strlen($name) > 150) return ['ok' => false, 'error' => 'Enter your full name.'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Enter a valid email address.'];
        if (!$phone) return ['ok' => false, 'error' => 'Enter a valid Kenyan phone number, for example 0712 345 678 or +254 712 345 678.'];
        if ($phone2Raw !== '' && !$phone2) return ['ok' => false, 'error' => 'The secondary phone number is not valid. Leave it empty or use 07xx, 01xx or +254.'];
        if ($mode === '') return ['ok' => false, 'error' => 'Choose Online or Physical classes.'];
        if (!isset(EN_CLASS_TIMES[$mode][$time])) return ['ok' => false, 'error' => 'Choose your preferred class time.'];
        if ($mode === 'physical' && $campus === '') return ['ok' => false, 'error' => 'Choose the campus: Nairobi or Thika.'];
        if (empty($in['consent'])) return ['ok' => false, 'error' => 'Please tick that you agree to be contacted about your booking.'];

        $st = $db->prepare("SELECT id FROM enrolment_bookings WHERE opening_id = ? AND course_id = ? AND (LOWER(email) = ? OR phone = ?) AND status <> 'cancelled' LIMIT 1");
        $st->execute([(int) $o['id'], $courseId, $email, $phone]);
        if ($old = (int) $st->fetchColumn()) return ['ok' => false, 'error' => 'You have already booked this intake.', 'duplicate' => en_booking($db, $old)];

        $st = $db->prepare("SELECT COUNT(*) FROM enrolment_bookings WHERE ip = ? AND created_at >= ?");
        $st->execute([$ip, date('Y-m-d H:i:s', strtotime(en_now()) - 3600)]);
        if ((int) $st->fetchColumn() >= 10) return ['ok' => false, 'error' => 'Too many bookings from this device. Please try again later or call us.'];

        $seat = en_seat_info($db, $o, $courseId);
        $full = $seat['left'] !== null && $seat['left'] <= 0;
        $pay = null;
        if (!$full) {
            $pay = en_check_code($db, (string) ($in['pay_method'] ?? ''), (string) ($in['pay_code'] ?? ''));
            if (empty($pay['ok'])) return ['ok' => false, 'error' => $pay['error'], 'pay_error' => true];
        }

        $db->beginTransaction();
        try {
            // Look again inside the transaction, so two people booking the last slot cannot both get it.
            $db->prepare("SELECT id FROM enrolment_openings WHERE id = ? FOR UPDATE")->execute([(int) $o['id']]);
            $seat = en_seat_info($db, $o, $courseId);
            $full = $seat['left'] !== null && $seat['left'] <= 0;
            if ($full && $pay) { $pay = null; }   // the last slot went while this person filled the form: they join the waiting list, unpaid
            if (!$full && !$pay) { $db->rollBack(); return ['ok' => false, 'error' => 'A slot is free again. Please send your payment code to book it.', 'pay_error' => true]; }
            do {
                $ref = 'BK-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
                $chk = $db->prepare("SELECT 1 FROM enrolment_bookings WHERE reference = ?");
                $chk->execute([$ref]);
            } while ($chk->fetchColumn());
            $cfg = en_cfg($db);
            $db->prepare("INSERT INTO enrolment_bookings (opening_id, course_id, reference, full_name, email, phone, phone2, study_mode, campus, class_time, consent, source, status, payment_status,
                              payment_amount, payment_reference, pay_method, fee_kind, token_salt, ip, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, 'unpaid', ?, ?, ?, ?, ?, ?, ?)")
               ->execute([(int) $o['id'], $courseId, $ref, mb_substr($name, 0, 150), $email, $phone, $phone2, $mode, $campus ?: null, $time, en_source(),
                          $full ? 'waiting' : 'payment_received', $full ? null : $cfg['booking_fee'], $full ? null : $pay['code'], $full ? null : (string) $in['pay_method'],
                          $full ? null : en_fee_kind($mode), bin2hex(random_bytes(16)), $ip, en_now()]);
            $id = (int) $db->lastInsertId();
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ((string) $e->getCode() === '23000') return ['ok' => false, 'error' => 'This payment code has already been used. Check the code from your payment message.', 'pay_error' => true];
            error_log('[enrolment] book: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'We could not save your booking. Please try again.'];
        }
        $b = en_booking($db, $id);
        if ($b['status'] === 'waiting') {
            en_mail_waiting($db, $b);
            return ['ok' => true, 'booking' => $b, 'waitlisted' => true];
        }
        en_after_payment_code($db, $b);
        return ['ok' => true, 'booking' => $b];
    }

    function en_booking(PDO $db, int $id): ?array
    {
        $st = $db->prepare("SELECT b.*, c.title AS course_title, o.title AS opening_title, o.classes_start, o.seats, o.course_id AS opening_course, o.opens_at, o.closes_at
                            FROM enrolment_bookings b JOIN courses c ON c.id = b.course_id JOIN enrolment_openings o ON o.id = b.opening_id WHERE b.id = ?");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* ---------- Admin ---------- */

    /**
     * Create or update an opening. $in: id (to update), course_id, title (the name), bookings_open_at, opens_at, closes_at (datetime-local, EAT),
     * classes_start (date), seats (per course), course_seats[course_id] (a different number for one course), override_mode.
     * Opening dates in the old date-only form (closes_at as Y-m-d) still work.
     * @return array{ok:bool, error?:string, id?:int}
     */
    function en_open(PDO $db, array $in, int $actor): array
    {
        $id = (int) ($in['id'] ?? 0);
        $courseId = (int) ($in['course_id'] ?? 0) ?: null;
        $title = trim((string) ($in['title'] ?? ''));
        $seats = trim((string) ($in['seats'] ?? ''));
        if ($title === '') $title = 'Intake ' . date('F Y');
        if ($seats !== '' && (!ctype_digit($seats) || (int) $seats < 1)) return ['ok' => false, 'error' => 'Slots must be a whole number, or leave it empty for no limit.'];
        $dt = function (string $k, bool $endOfDay = false) use ($in) {
            $v = trim((string) ($in[$k] ?? ''));
            if ($v === '') return [null, null];
            $d = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $v) ?: DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $v) ?: DateTimeImmutable::createFromFormat('!Y-m-d', $v);
            if (!$d) return [null, 'Choose a valid date and time.'];
            if (strlen($v) === 10 && $endOfDay) $d = $d->setTime(23, 59, 59);
            return [$d->format('Y-m-d H:i:s'), null];
        };
        [$bo, $e1] = $dt('bookings_open_at'); [$op, $e2] = $dt('opens_at'); [$cl, $e3] = $dt('closes_at', true); [$ea, $e4] = $dt('early_access_at');
        if ($e1 || $e2 || $e3 || $e4) return ['ok' => false, 'error' => $e1 ?: ($e2 ?: ($e3 ?: $e4))];
        if ($ea && (!$op || $ea >= $op)) return ['ok' => false, 'error' => 'Early access must start before enrolment opens.'];
        if ($ea && $bo && $ea < $bo) return ['ok' => false, 'error' => 'Early access cannot start before bookings open.'];
        $start = trim((string) ($in['classes_start'] ?? '')) ?: null;
        if ($start && !DateTimeImmutable::createFromFormat('!Y-m-d', $start)) return ['ok' => false, 'error' => 'Choose a valid start date.'];
        if ($bo && $op && $bo > $op) return ['ok' => false, 'error' => 'Bookings must open before enrolment opens.'];
        if ($op && $cl && $op >= $cl) return ['ok' => false, 'error' => 'Enrolment must close after it opens.'];
        if (($bo || $op) && !$cl) return ['ok' => false, 'error' => 'Enter the date and time enrolment closes (the end of the intake).'];
        if (!$id && $cl && !$bo && !$op && $cl <= en_now()) return ['ok' => false, 'error' => 'The closing date has already passed.'];
        $mode = in_array($in['override_mode'] ?? '', ['open', 'closed'], true) ? $in['override_mode'] : 'auto';
        $seatsVal = $seats === '' ? null : (int) $seats;
        if ($id) {
            $db->prepare("UPDATE enrolment_openings SET course_id = ?, title = ?, seats = ?, classes_start = ?, bookings_open_at = ?, early_access_at = ?, opens_at = ?, closes_at = ?, override_mode = ? WHERE id = ?")
               ->execute([$courseId, mb_substr($title, 0, 120), $seatsVal, $start, $bo, $ea, $op, $cl, $mode, $id]);
            $result = ['ok' => true, 'id' => $id, 'updated' => true];
        } else {
            $db->prepare("INSERT INTO enrolment_openings (course_id, title, seats, classes_start, bookings_open_at, early_access_at, opens_at, closes_at, override_mode, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', ?, ?)")
               ->execute([$courseId, mb_substr($title, 0, 120), $seatsVal, $start, $bo, $ea, $op, $cl, $mode, $actor, en_now()]);
            $id = (int) $db->lastInsertId();
            $result = ['ok' => true, 'id' => $id];
        }
        // A different number of seats for one course.
        $db->prepare("DELETE FROM enrolment_opening_seats WHERE opening_id = ?")->execute([$id]);
        foreach ((array) ($in['course_seats'] ?? []) as $cid => $n) {
            if (is_string($n) && ctype_digit(trim($n)) && (int) $cid > 0) {
                $db->prepare("INSERT INTO enrolment_opening_seats (opening_id, course_id, seats) VALUES (?, ?, ?)")->execute([$id, (int) $cid, (int) trim($n)]);
            }
        }
        if (function_exists('en_notify_interest')) en_notify_interest($db);
        return $result;
    }

    /** Every opening with its stage and counts, for the admin list (newest first). */
    function en_openings(PDO $db): array
    {
        en_autoclose($db);
        $rows = $db->query("SELECT o.*, c.title AS course_title,
                                  (SELECT COUNT(*) FROM enrolment_bookings b WHERE b.opening_id = o.id AND b.status = 'confirmed') AS confirmed,
                                  (SELECT COUNT(*) FROM enrolment_bookings b WHERE b.opening_id = o.id AND b.status = 'payment_received') AS payment_waiting,
                                  (SELECT COUNT(*) FROM enrolment_bookings b WHERE b.opening_id = o.id AND b.status = 'waiting') AS waiting,
                                  (SELECT COUNT(*) FROM enrolment_bookings b WHERE b.opening_id = o.id AND b.status = 'converted') AS converted
                           FROM enrolment_openings o LEFT JOIN courses c ON c.id = o.course_id ORDER BY o.id DESC")->fetchAll(PDO::FETCH_ASSOC);
        $now = en_now();
        $hours = en_early_hours($db);
        foreach ($rows as &$r) $r['stage'] = en_stage($r, $now, $hours);
        unset($r);
        usort($rows, fn($a, $b) => [$a['stage'] === 'closed', -$a['id']] <=> [$b['stage'] === 'closed', -$b['id']]);
        return $rows;
    }

    function en_set_status(PDO $db, int $id, bool $open): void
    {
        $o = $db->prepare("SELECT * FROM enrolment_openings WHERE id = ?");
        $o->execute([$id]);
        $row = $o->fetch(PDO::FETCH_ASSOC);
        if (!$row) return;
        $legacy = empty($row['bookings_open_at']) && empty($row['opens_at']);
        if ($open) {
            // Reopening: drop a passed closing date; an older opening also closes any other open one for the same course.
            if ($legacy) {
                $cid = $row['course_id'];
                $db->prepare("UPDATE enrolment_openings SET status = 'closed', closed_at = ? WHERE status = 'open' AND id <> ? AND " . ($cid === null ? "course_id IS NULL" : "course_id = ?"))
                   ->execute($cid === null ? [en_now(), $id] : [en_now(), $id, (int) $cid]);
            }
            $db->prepare("UPDATE enrolment_openings SET status = 'open', closed_at = NULL, override_mode = 'auto', closes_at = IF(closes_at IS NOT NULL AND closes_at <= ?, NULL, closes_at) WHERE id = ?")
               ->execute([en_now(), $id]);
        } else {
            $db->prepare("UPDATE enrolment_openings SET status = 'closed', closed_at = ? WHERE id = ?")->execute([en_now(), $id]);
        }
    }

    /** Delete an opening and all its bookings. */
    function en_delete_opening(PDO $db, int $id): void
    {
        $db->prepare("DELETE FROM enrolment_bookings WHERE opening_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM enrolment_opening_seats WHERE opening_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM enrolment_openings WHERE id = ?")->execute([$id]);
    }

    /** Bookings, newest first. Filters: opening, course, status, pay (unpaid | paid), q (name, email, phone, reference or payment code). */
    function en_bookings(PDO $db, array $f = []): array
    {
        $sql = "SELECT b.*, c.title AS course_title, o.title AS opening_title FROM enrolment_bookings b
                JOIN courses c ON c.id = b.course_id JOIN enrolment_openings o ON o.id = b.opening_id WHERE 1 = 1";
        $args = [];
        if (!empty($f['opening'])) { $sql .= " AND b.opening_id = ?"; $args[] = (int) $f['opening']; }
        if (!empty($f['course'])) { $sql .= " AND b.course_id = ?"; $args[] = (int) $f['course']; }
        if (!empty($f['status'])) { $sql .= " AND b.status = ?"; $args[] = $f['status']; }
        if (!empty($f['pay'])) { $sql .= " AND b.payment_status = ?"; $args[] = $f['pay']; }
        if (!empty($f['q'])) {
            $sql .= " AND (b.full_name LIKE ? OR b.email LIKE ? OR b.phone LIKE ? OR b.reference LIKE ? OR b.payment_reference LIKE ?)";
            array_push($args, ...array_fill(0, 5, '%' . $f['q'] . '%'));
        }
        $st = $db->prepare($sql . " ORDER BY b.id DESC LIMIT 1000");
        $st->execute($args);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Older action: give a slot to a waiting booking (now an offer: the person pays the booking fee), or cancel / put back on the waiting list. */
    function en_set_booking(PDO $db, int $id, string $status): bool
    {
        if (!in_array($status, ['confirmed', 'waiting', 'cancelled'], true)) return false;
        $b = en_booking($db, $id);
        if (!$b) return false;
        if ($status === 'cancelled') { en_cancel($db, $b, 'Cancelled by the office'); return true; }
        if ($status === 'confirmed' && $b['status'] === 'waiting') { en_offer($db, $b); return true; }
        $db->prepare("UPDATE enrolment_bookings SET status = ? WHERE id = ?")->execute([$status, $id]);
        return true;
    }

    function en_e($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    function en_date(?string $d, string $f = 'D j M Y'): string
    {
        return $d ? date($f, strtotime($d)) : '';
    }



    /* ---------- Admin ---------- */







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
            . '<p style="margin:20px 0 8px"><a href="' . en_e(course_clean_url((int) $b['course_id'])) . '" style="display:inline-block;background:#44A024;color:#fff;padding:11px 26px;border-radius:8px;text-decoration:none;font-weight:700;font-size:13px">VIEW THE COURSE</a></p>'
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

require_once __DIR__ . '/EnrolmentFeeService.php';
