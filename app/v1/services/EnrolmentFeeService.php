<?php
/**
 * app/v1/services/EnrolmentFeeService.php  (loaded by EnrolmentService.php; migration 101)
 * The Book now booking fee on top of simple enrolment: the KES 1,500 fee, the payment code (M-Pesa or bank), Confirm / Reject by the office,
 * the receipt, the waiting list, "Check my booking", "Tell me when the next intake opens", and the server-side block that keeps the Enroll
 * form shut while enrolment is closed.
 *
 * A booking with a valid payment code has status payment_received and holds a seat. The office checks the payment by hand and presses
 * Confirm (status confirmed, payment_status paid, receipt emailed) or Reject (booking cancelled, seat released, student told why).
 *
 * AUTOMATIC M-PESA CHECK LATER: put the Daraja call in en_verify_payment(), the one place that decides "is this payment real".
 * Today it always answers verified = false, so every payment waits for the office. Nothing else needs to change.
 *
 * The system has no SMS or WhatsApp sender, so the "Booking payment received, please confirm" message goes to the office by email, and the
 * student gets a button that opens WhatsApp with a ready message to the institution number (en_whatsapp_url()).
 */

if (!defined('EN_CLASS_TIMES')) {
    define('EN_CLASS_TIMES', ['online' => ['08:00' => '8:00 AM', '12:00' => '12:00 PM', '18:00' => '6:00 PM'], 'physical' => ['05:00' => '5:00 AM']]);
    define('EN_STATUS_LABELS', ['confirmed' => 'Confirmed', 'payment_received' => 'Payment code received', 'waiting' => 'Waiting list', 'converted' => 'Converted', 'expired' => 'Expired', 'cancelled' => 'Cancelled']);
    define('EN_PAY_LABELS', ['unpaid' => 'Not paid', 'paid' => 'Paid', 'waived' => 'Waived']);
}

if (!function_exists('en_cfg')) {

    /* ---------- settings ---------- */

    function en_setting_model(PDO $db): SettingModel
    {
        if (!class_exists('SettingModel')) require_once __DIR__ . '/../models/SettingModel.php';
        return new SettingModel($db);
    }

    /** Fee and payment details (admin settings). */
    function en_cfg(PDO $db): array
    {
        $c = ['booking_fee' => 1500.0, 'mpesa_type' => 'paybill', 'mpesa_number' => '', 'mpesa_account' => '', 'bank_name' => '', 'bank_account_name' => '',
              'bank_account_number' => '', 'notify_phone' => '0722922454', 'early_hours' => 48, 'hold_days' => 3];
        try {
            $j = json_decode((string) en_setting_model($db)->get('en_fee_cfg'), true);
            if (is_array($j)) foreach ($c as $k => $v) if (isset($j[$k]) && $j[$k] !== '') $c[$k] = $k === 'booking_fee' ? (float) $j[$k] : (in_array($k, ['early_hours', 'hold_days'], true) ? (int) $j[$k] : (string) $j[$k]);
        } catch (Throwable $e) {
        }
        return $c;
    }

    /** Save the fee, the payment details and the setting "Hide the Enroll form when enrolment is closed". */
    function en_cfg_save(PDO $db, array $in): array
    {
        $fee = (float) str_replace(',', '', (string) ($in['booking_fee'] ?? 0));
        if ($fee < 1 || $fee > 1000000) return ['ok' => false, 'error' => 'Enter the booking fee in KES, for example 1500.'];
        $phone = en_phone((string) ($in['notify_phone'] ?? ''));
        if (!$phone) return ['ok' => false, 'error' => 'Enter a valid phone number for payment messages (07xx, 01xx or +254).'];
        $t = fn($k, $n) => mb_substr(trim((string) ($in[$k] ?? '')), 0, $n);
        $early = (int) ($in['early_hours'] ?? 48);
        $hold = (int) ($in['hold_days'] ?? 3);
        if ($early < 1 || $early > 720) return ['ok' => false, 'error' => 'Early access must be between 1 and 720 hours.'];
        if ($hold < 1 || $hold > 60) return ['ok' => false, 'error' => 'The seat hold must be between 1 and 60 days.'];
        if ($hold * 24 <= $early) return ['ok' => false, 'error' => 'The seat hold must be longer than early access.'];
        $cfg = ['booking_fee' => $fee, 'mpesa_type' => ($in['mpesa_type'] ?? '') === 'till' ? 'till' : 'paybill', 'mpesa_number' => $t('mpesa_number', 20),
                'mpesa_account' => $t('mpesa_account', 60), 'bank_name' => $t('bank_name', 80), 'bank_account_name' => $t('bank_account_name', 80),
                'bank_account_number' => $t('bank_account_number', 40), 'notify_phone' => $phone, 'early_hours' => $early, 'hold_days' => $hold];
        $m = en_setting_model($db);
        $m->set('en_fee_cfg', json_encode($cfg));
        $m->set('en_hide_form', !empty($in['hide_form']) ? '1' : '0');
        return ['ok' => true];
    }

    /** "Hide the Enroll form when enrolment is closed": on by default. */
    function en_hide_form(PDO $db): bool
    {
        try { return en_setting_model($db)->get('en_hide_form', '1') !== '0'; } catch (Throwable $e) { return true; }
    }

    function en_money(float $n): string
    {
        return 'KES ' . number_format($n, 0);
    }

    /** What the fee counts as: Physical = the registration fee; Online = a payment towards the course fees. */
    function en_fee_kind(string $mode): string
    {
        return $mode === 'physical' ? 'registration_fee' : 'course_fee_credit';
    }

    function en_fee_kind_line(string $mode): string
    {
        return $mode === 'physical' ? 'It is your registration fee.' : 'It will be deducted from your course fees.';
    }

    /** A Kenyan number as 07xxxxxxxx or 01xxxxxxxx (from 07xx, 01xx, 7xx or +254), or null. */
    function en_phone(string $p): ?string
    {
        $d = preg_replace('/\D+/', '', $p);
        if (strlen($d) === 12 && strncmp($d, '254', 3) === 0) $d = '0' . substr($d, 3);
        elseif (strlen($d) === 9 && in_array($d[0], ['7', '1'], true)) $d = '0' . $d;
        return preg_match('/^0[17]\d{8}$/', $d) ? $d : null;
    }

    /** Where the visitor came from (utm_source / utm_campaign or the referring site), kept for the booking. */
    function en_track_source(): void
    {
        if (PHP_SAPI === 'cli' || !empty($_SESSION['en_source'])) return;
        $src = trim((string) ($_GET['utm_source'] ?? ''));
        if ($src !== '') {
            $camp = trim((string) ($_GET['utm_campaign'] ?? ''));
            $_SESSION['en_source'] = mb_substr($src . ($camp !== '' ? ' / ' . $camp : ''), 0, 120);
        } elseif (!empty($_SERVER['HTTP_REFERER'])) {
            $h = (string) parse_url((string) $_SERVER['HTTP_REFERER'], PHP_URL_HOST);
            if ($h !== '' && stripos($h, (string) ($_SERVER['HTTP_HOST'] ?? '')) === false) $_SESSION['en_source'] = mb_substr($h, 0, 120);
        }
    }

    function en_source(): ?string
    {
        return !empty($_SESSION['en_source']) ? (string) $_SESSION['en_source'] : null;
    }

    /* ---------- the payment code ---------- */

    /**
     * Is this a valid code for the chosen way of paying, and is it unused? M-Pesa: exactly 10 letters and numbers. Bank: 6 to 30 characters.
     * A code is never accepted twice, for any booking (or for an M-Pesa payment already recorded). Stored in capitals.
     * @return array{ok:bool, code?:string, error?:string}
     */
    function en_check_code(PDO $db, string $method, string $raw, int $exceptBookingId = 0): array
    {
        $code = strtoupper(preg_replace('/\s+/', '', trim($raw)));
        if ($method === 'mpesa') {
            if (!preg_match('/^[A-Z0-9]{10}$/', $code)) return ['ok' => false, 'error' => 'An M-Pesa code has 10 letters and numbers, for example QGH7K2M9XP.'];
        } elseif ($method === 'bank') {
            if (!preg_match('/^[A-Z0-9\-\/_.]{6,30}$/', $code)) return ['ok' => false, 'error' => 'A bank code has 6 to 30 letters and numbers.'];
        } else {
            return ['ok' => false, 'error' => 'Choose how you paid: M-Pesa or bank.'];
        }
        $st = $db->prepare("SELECT 1 FROM enrolment_bookings WHERE UPPER(payment_reference) = ? AND id <> ? LIMIT 1");
        $st->execute([$code, $exceptBookingId]);
        $used = (bool) $st->fetchColumn();
        if (!$used && $method === 'mpesa') {
            try {
                $p = $db->prepare("SELECT 1 FROM payments WHERE UPPER(mpesa_code) = ? LIMIT 1");
                $p->execute([$code]);
                $used = (bool) $p->fetchColumn();
            } catch (Throwable $e) {
            }
        }
        if ($used) return ['ok' => false, 'error' => 'This payment code has already been used. Check the code from your payment message.'];
        return ['ok' => true, 'code' => $code];
    }

    /**
     * THE PLACE FOR AN AUTOMATIC M-PESA (DARAJA) CHECK. Ask the provider whether $code paid $amount to our till; return
     * ['verified' => true, 'source' => 'daraja'] and en_after_payment_code() will confirm the booking by itself. Today: always by hand.
     */
    function en_verify_payment(PDO $db, string $method, string $code, float $amount): array
    {
        return ['verified' => false, 'source' => 'manual'];
    }

    /** A valid code is in: tell the student it was received and the office that a payment waits. */
    function en_after_payment_code(PDO $db, array $b): void
    {
        en_notify_office($db, $b);
        en_queue($db, $b['email'], $b['full_name'], 'We received your payment code (' . $b['reference'] . ')', en_email_html(
            'Hello ' . en_e(en_first($b['full_name'])) . ', we received your payment code',
            'Your seat is held. We check every payment by hand and will email your receipt as soon as it is confirmed. This fee is not refundable. ' . en_e(en_fee_kind_line((string) $b['study_mode'])),
            en_summary_rows($b), [['Check my booking', en_manage_url($b)]]));
        $v = en_verify_payment($db, (string) $b['pay_method'], (string) $b['payment_reference'], (float) $b['payment_amount']);
        if (!empty($v['verified'])) en_pay_confirm($db, $b, 0);
    }

    /** "Booking payment received, please confirm" with the student's details. */
    function en_office_text(array $b): string
    {
        return 'Booking payment received, please confirm. Name: ' . $b['full_name'] . '. Phone: ' . $b['phone'] . '. Course: ' . ($b['course_title'] ?? '')
            . '. Classes: ' . ucfirst((string) $b['study_mode']) . ($b['campus'] ? ' (' . ucfirst((string) $b['campus']) . ')' : '') . '. Amount: ' . en_money((float) $b['payment_amount'])
            . '. Code: ' . $b['payment_reference'] . '. Booking: ' . $b['reference'] . '.';
    }

    /** Alert the office (staff alert). Email to the admissions address; the admin menu also shows the number waiting. */
    function en_notify_office(PDO $db, array $b): void
    {
        $to = function_exists('site_contact_setting_value') ? (string) site_contact_setting_value('contact_email', '') : '';
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return;
        en_queue($db, $to, 'Dazu admissions', 'Booking payment received, please confirm (' . $b['reference'] . ')', en_email_html(
            'Booking payment received, please confirm', 'A student sent a payment code. Check the payment, then press <strong>Confirm</strong> or <strong>Reject</strong>.',
            ['Booking' => $b['reference'], 'Name' => $b['full_name'], 'Phone' => $b['phone'], 'Course' => $b['course_title'] ?? '', 'Intake' => $b['opening_title'] ?? '',
             'Classes' => ucfirst((string) $b['study_mode']) . ($b['campus'] ? ', ' . ucfirst((string) $b['campus']) : ''), 'Amount' => en_money((float) $b['payment_amount']),
             'Paid by' => strtoupper((string) $b['pay_method']), 'Code' => $b['payment_reference']],
            [['Open the booking', en_abs('admin/intakes/bookings.php?q=' . rawurlencode((string) $b['reference']))]]));
    }

    /** wa.me link with a ready message to the institution number: reference, name and code. */
    function en_whatsapp_url(PDO $db, array $b): string
    {
        $num = preg_replace('/\D+/', '', (string) (en_phone(en_cfg($db)['notify_phone']) ?: '0722922454'));
        if (strlen($num) === 10 && $num[0] === '0') $num = '254' . substr($num, 1);
        return 'https://wa.me/' . $num . '?text=' . rawurlencode('Booking payment received, please confirm. Booking ' . $b['reference'] . '. Name: ' . $b['full_name'] . '. Code: ' . $b['payment_reference'] . '.');
    }

    /** Office presses Confirm: booking confirmed, payment_status paid, receipt emailed. */
    function en_pay_confirm(PDO $db, array $b, int $adminId): array
    {
        if (($b['status'] ?? '') !== 'payment_received') return ['ok' => false, 'error' => 'Only a booking with a payment code waiting can be confirmed.'];
        $st = $db->prepare("UPDATE enrolment_bookings SET status = 'confirmed', payment_status = 'paid', paid_at = ?, reviewed_by = ?, reviewed_at = ?, receipt_sent_at = ? WHERE id = ? AND status = 'payment_received'");
        $st->execute([en_now(), $adminId ?: null, en_now(), en_now(), (int) $b['id']]);
        if (!$st->rowCount()) return ['ok' => false, 'error' => 'This payment was already handled.'];
        $b = en_booking($db, (int) $b['id']);
        $rows = en_summary_rows($b) + ['Amount paid' => en_money((float) $b['payment_amount']), 'Payment code' => $b['payment_reference']];
        en_queue($db, $b['email'], $b['full_name'], 'Receipt: booking fee received (' . $b['reference'] . ')', en_email_html(
            'Hello ' . en_e(en_first($b['full_name'])) . ', your booking is confirmed',
            'We have received your booking fee. This is your receipt. The fee is not refundable. ' . en_e(en_fee_kind_line((string) $b['study_mode'])),
            $rows, [['Check my booking', en_manage_url($b)]]));
        en_invite($db, $b);   // a booking confirmed while early access is running is invited at once, with its own hold from now (invites go only to Confirmed bookings)
        return ['ok' => true];
    }

    /** Office presses Reject (with a reason): booking cancelled, seat released, student told why and how to try again. */
    function en_pay_reject(PDO $db, array $b, int $adminId, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') return ['ok' => false, 'error' => 'Write a short reason, so the student knows what to fix.'];
        if (($b['status'] ?? '') !== 'payment_received') return ['ok' => false, 'error' => 'Only a booking with a payment code waiting can be rejected.'];
        $st = $db->prepare("UPDATE enrolment_bookings SET status = 'cancelled', reject_reason = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ? AND status = 'payment_received'");
        $st->execute([mb_substr($reason, 0, 255), $adminId ?: null, en_now(), (int) $b['id']]);
        if (!$st->rowCount()) return ['ok' => false, 'error' => 'This payment was already handled.'];
        $b = en_booking($db, (int) $b['id']);
        en_queue($db, $b['email'], $b['full_name'], 'We could not confirm your payment (' . $b['reference'] . ')', en_email_html(
            'Hello ' . en_e(en_first($b['full_name'])) . ', we could not confirm your payment',
            'We checked booking <strong>' . en_e($b['reference']) . '</strong> and could not confirm the payment. Reason: <strong>' . en_e($reason) . '</strong>. Your booking has been cancelled and the seat released. '
            . 'You can book again and pay with the correct code at any time.', [], [['Book now', en_abs('intake/book.php?course=' . (int) $b['course_id'])]]));
        en_offer_next($db, (int) $b['opening_id'], (int) $b['course_id']);
        return ['ok' => true];
    }

    /**
     * When a booked person enrols (the Apply form), their booking becomes Converted and is linked to the new registration. The booking fee is not
     * asked for again: it is recorded on the student's record when the office approves the student (en_apply_fee_on_approval), and it is taken
     * off the default first deposit then. Physical: it pays the registration fee. Online: it is a payment towards the course fees.
     * $bookingId: the booking of the early-access link; otherwise the paid booking with the same email for this course.
     */
    function en_link_booking_on_enrol(PDO $db, int $registrationId, string $email, int $courseId, int $bookingId = 0): void
    {
        try {
            if ($bookingId > 0) {   // the person came through their early-access link: that very booking
                $st = $db->prepare("SELECT * FROM enrolment_bookings WHERE id = ? AND course_id = ? AND status IN ('confirmed','expired') AND payment_status = 'paid' AND fee_applied_registration_id IS NULL");
                $st->execute([$bookingId, $courseId]);
            } else {
                $st = $db->prepare("SELECT * FROM enrolment_bookings WHERE course_id = ? AND LOWER(email) = ? AND status IN ('confirmed','expired') AND payment_status = 'paid' AND fee_applied_registration_id IS NULL ORDER BY id DESC LIMIT 1");
                $st->execute([$courseId, strtolower(trim($email))]);
            }
            $b = $st->fetch(PDO::FETCH_ASSOC);
            if (!$b) return;
            $db->prepare("UPDATE enrolment_bookings SET status = 'converted', converted_registration_id = ?, converted_at = ? WHERE id = ?")->execute([$registrationId, en_now(), (int) $b['id']]);
            // Say so on the registration, so the office sees it when approving.
            $db->prepare("UPDATE registrations SET payment_reference = ? WHERE id = ?")->execute([mb_substr('Booking ' . $b['reference'] . ' ' . strtoupper((string) $b['pay_method']) . ' ' . $b['payment_reference'] . ' (' . en_money((float) $b['payment_amount']) . ' paid)', 0, 250), $registrationId]);
            unset($_SESSION['en_ea'][$courseId]);
        } catch (Throwable $e) {
            error_log('[enrolment convert] ' . $e->getMessage());
        }
    }

    /** The booking fee that belongs to a registration and is not on the student's record yet (a Converted booking, paid). 0 when there is none. */
    function en_pending_fee_credit(PDO $db, int $registrationId): float
    {
        try {
            $st = $db->prepare("SELECT payment_amount FROM enrolment_bookings WHERE converted_registration_id = ? AND payment_status = 'paid' AND fee_applied_registration_id IS NULL ORDER BY id DESC LIMIT 1");
            $st->execute([$registrationId]);
            return (float) $st->fetchColumn();
        } catch (Throwable $e) {
            return 0.0;
        }
    }

    /**
     * The student was approved: put the booking fee on their record as an approved payment (once), with the booking code. Physical: the registration fee,
     * paid. Online: a payment towards the course fees, so the balance is lower. $adminId is the office person who approved.
     */
    function en_apply_fee_on_approval(PDO $db, int $registrationId, int $adminId): void
    {
        try {
            $st = $db->prepare("SELECT * FROM enrolment_bookings WHERE converted_registration_id = ? AND payment_status = 'paid' AND fee_applied_registration_id IS NULL ORDER BY id DESC LIMIT 1");
            $st->execute([$registrationId]);
            $b = $st->fetch(PDO::FETCH_ASSOC);
            if (!$b || $adminId <= 0) return;
            require_once __DIR__ . '/../models/PaymentModel.php';
            $kind = (string) ($b['fee_kind'] ?: en_fee_kind((string) $b['study_mode']));
            $note = ($kind === 'registration_fee' ? 'Registration fee paid by booking ' : 'Payment towards course fees from booking ') . $b['reference'] . ' (code ' . $b['payment_reference'] . ')';
            (new \Models\PaymentModel($db))->recordManual($registrationId, (float) $b['payment_amount'], 'Booking ' . $b['reference'] . ' ' . strtoupper((string) $b['pay_method']) . ' ' . $b['payment_reference'], $adminId, $note);
            $db->prepare("UPDATE enrolment_bookings SET fee_applied_registration_id = ? WHERE id = ?")->execute([$registrationId, (int) $b['id']]);
        } catch (Throwable $e) {
            error_log('[enrolment fee] ' . $e->getMessage());
        }
    }

    /* ---------- the waiting list ---------- */

    /** A slot is now yours: the waiting person is told to pay the booking fee. Held for them (offered_at). */
    function en_offer(PDO $db, array $b): void
    {
        if (($b['status'] ?? '') !== 'waiting' || !empty($b['offered_at'])) return;
        $db->prepare("UPDATE enrolment_bookings SET offered_at = ? WHERE id = ? AND status = 'waiting'")->execute([en_now(), (int) $b['id']]);
        $b = en_booking($db, (int) $b['id']);
        en_queue($db, $b['email'], $b['full_name'], 'A slot is now yours: pay the booking fee (' . $b['reference'] . ')', en_email_html(
            'Hello ' . en_e(en_first($b['full_name'])) . ', a slot is now yours',
            'A place has opened in <strong>' . en_e($b['course_title']) . '</strong>. Pay the booking fee of <strong>' . en_e(en_money(en_cfg($db)['booking_fee'])) . '</strong> and send your payment code to keep it. This fee is not refundable.',
            en_summary_rows($b), [['Pay the booking fee', en_manage_url($b)]]));
    }

    /** A seat was released (cancel, reject): offer it to the first person on the waiting list. */
    function en_offer_next(PDO $db, int $openingId, int $courseId): void
    {
        $o = $db->prepare("SELECT * FROM enrolment_openings WHERE id = ?");
        $o->execute([$openingId]);
        $op = $o->fetch(PDO::FETCH_ASSOC);
        if (!$op) return;
        $seat = en_seat_info($db, $op, $courseId);
        if ($seat['left'] !== null && $seat['left'] <= 0) return;
        $w = $db->prepare("SELECT id FROM enrolment_bookings WHERE opening_id = ? AND course_id = ? AND status = 'waiting' AND offered_at IS NULL ORDER BY id LIMIT 1");
        $w->execute([$openingId, $courseId]);
        if ($id = (int) $w->fetchColumn()) en_offer($db, en_booking($db, $id));
    }

    function en_mail_waiting(PDO $db, array $b): void
    {
        en_queue($db, $b['email'], $b['full_name'], 'You are on the waiting list (' . $b['reference'] . ')', en_email_html(
            'Hello ' . en_e(en_first($b['full_name'])) . ', you are on the waiting list',
            'All slots for <strong>' . en_e($b['course_title']) . '</strong> are taken for now, so you have nothing to pay yet. If a slot opens we will email you, and you can then pay the booking fee to keep it.',
            en_summary_rows($b), [['Check my booking', en_manage_url($b)]]));
    }

    /** A waiting person with an offered slot sends their payment code. */
    function en_pay_submit_existing(PDO $db, array $b, string $method, string $raw): array
    {
        if (($b['status'] ?? '') !== 'waiting' || empty($b['offered_at'])) return ['ok' => false, 'error' => 'A slot has not been offered to this booking yet.'];
        $chk = en_check_code($db, $method, $raw, (int) $b['id']);
        if (empty($chk['ok'])) return ['ok' => false, 'error' => $chk['error']];
        try {
            $st = $db->prepare("UPDATE enrolment_bookings SET status = 'payment_received', payment_status = 'unpaid', payment_amount = ?, payment_reference = ?, pay_method = ?, fee_kind = ?
                                WHERE id = ? AND status = 'waiting'");
            $st->execute([en_cfg($db)['booking_fee'], $chk['code'], $method, en_fee_kind((string) $b['study_mode']), (int) $b['id']]);
        } catch (PDOException $e) {
            return ['ok' => false, 'error' => 'This payment code has already been used. Check the code from your payment message.'];
        }
        if (!$st->rowCount()) return ['ok' => false, 'error' => 'This booking cannot take a payment code now.'];
        $b = en_booking($db, (int) $b['id']);
        en_after_payment_code($db, $b);
        return ['ok' => true, 'booking' => $b];
    }

    /* ---------- check my booking ---------- */

    function en_link_key(): string
    {
        $f = storage_path('enrolment_link.key');
        if (!is_file($f)) { @mkdir(dirname($f), 0775, true); @file_put_contents($f, bin2hex(random_bytes(32))); }
        return (string) @file_get_contents($f);
    }

    /** The secure link token: the booking id plus an HMAC made with a server key and the booking's own salt (only the salt is stored). */
    function en_token(array $b): string
    {
        return (int) $b['id'] . '-' . substr(hash_hmac('sha256', (int) $b['id'] . '|' . $b['token_salt'], en_link_key()), 0, 40);
    }

    function en_manage_url(array $b): string
    {
        return en_abs('intake/manage.php?b=' . en_token($b));
    }

    function en_by_token(PDO $db, string $token): ?array
    {
        if (!preg_match('/^(\d+)-([a-f0-9]{40})$/', $token, $m)) return null;
        $b = en_booking($db, (int) $m[1]);
        return $b && !empty($b['token_salt']) && hash_equals(en_token($b), $token) ? $b : null;
    }

    function en_by_ref_email(PDO $db, string $ref, string $email): ?array
    {
        $st = $db->prepare("SELECT id FROM enrolment_bookings WHERE UPPER(reference) = ? AND LOWER(email) = ? LIMIT 1");
        $st->execute([strtoupper(trim($ref)), strtolower(trim($email))]);
        $id = (int) $st->fetchColumn();
        return $id ? en_booking($db, $id) : null;
    }

    /** Cancel a booking (no refund). The seat is released and offered to the waiting list. */
    function en_cancel(PDO $db, array $b, string $reason = ''): array
    {
        if (!in_array($b['status'], ['payment_received', 'confirmed', 'waiting'], true)) return ['ok' => false, 'error' => 'This booking can no longer be cancelled.'];
        $db->prepare("UPDATE enrolment_bookings SET status = 'cancelled' WHERE id = ?")->execute([(int) $b['id']]);
        en_queue($db, $b['email'], $b['full_name'], 'Your booking is cancelled (' . $b['reference'] . ')', en_email_html(
            'Hello ' . en_e(en_first($b['full_name'])) . ', your booking is cancelled',
            'We cancelled booking <strong>' . en_e($b['reference']) . '</strong> for ' . en_e($b['course_title']) . '. The booking fee is not refundable. You can book again at any time.', [],
            [['Book now', en_abs('intake/book.php?course=' . (int) $b['course_id'])]]));
        en_offer_next($db, (int) $b['opening_id'], (int) $b['course_id']);
        return ['ok' => true];
    }

    /** Move a booking to another opening of the same course. The payment moves with it. */
    function en_move(PDO $db, array $b, int $newOpeningId): array
    {
        if (!in_array($b['status'], ['payment_received', 'confirmed', 'waiting'], true)) return ['ok' => false, 'error' => 'This booking can no longer be moved.'];
        $state = en_state($db, (int) $b['course_id']);
        $new = null;
        foreach ($state['bookable'] as $o) if ((int) $o['id'] === $newOpeningId && (int) $o['id'] !== (int) $b['opening_id']) $new = $o;
        if (!$new) return ['ok' => false, 'error' => 'Choose another intake that is taking bookings.'];
        $dup = $db->prepare("SELECT 1 FROM enrolment_bookings WHERE opening_id = ? AND course_id = ? AND id <> ? AND (LOWER(email) = ? OR phone = ?) AND status <> 'cancelled' LIMIT 1");
        $dup->execute([$newOpeningId, (int) $b['course_id'], (int) $b['id'], strtolower((string) $b['email']), $b['phone']]);
        if ($dup->fetchColumn()) return ['ok' => false, 'error' => 'You already have a booking in that intake.'];
        if (in_array($b['status'], ['payment_received', 'confirmed'], true)) {
            $seat = en_seat_info($db, $new, (int) $b['course_id']);
            if ($seat['left'] !== null && $seat['left'] <= 0) return ['ok' => false, 'error' => 'That intake is full. Choose another one.'];
        }
        $old = (int) $b['opening_id'];
        $db->prepare("UPDATE enrolment_bookings SET opening_id = ?, invited_at = NULL, hold_until = NULL, reminder_sent_at = NULL WHERE id = ?")->execute([$newOpeningId, (int) $b['id']]);
        en_offer_next($db, $old, (int) $b['course_id']);
        $b = en_booking($db, (int) $b['id']);
        en_invite($db, $b);   // if the new opening is already in early access
        en_queue($db, $b['email'], $b['full_name'], 'Your booking was moved (' . $b['reference'] . ')', en_email_html(
            'Hello ' . en_e(en_first($b['full_name'])) . ', your booking was moved', 'Your booking now belongs to the <strong>' . en_e($b['opening_title']) . '</strong> intake. Your payment moved with it.',
            en_summary_rows($b), [['Check my booking', en_manage_url($b)]]));
        return ['ok' => true];
    }

    /** Change the class time or the way of studying. Changing between Physical and Online changes how the fee counts. */
    function en_update_prefs(PDO $db, array $b, string $mode, string $time, string $campus): array
    {
        if (!in_array($b['status'], ['payment_received', 'confirmed', 'waiting'], true)) return ['ok' => false, 'error' => 'This booking can no longer be changed.'];
        $mode = $mode === 'physical' ? 'physical' : 'online';
        if (!isset(EN_CLASS_TIMES[$mode][$time])) return ['ok' => false, 'error' => 'Choose a class time.'];
        if ($mode === 'physical' && !in_array($campus, ['nairobi', 'thika'], true)) return ['ok' => false, 'error' => 'Choose the campus: Nairobi or Thika.'];
        $db->prepare("UPDATE enrolment_bookings SET study_mode = ?, class_time = ?, campus = ?, fee_kind = " . ($b['payment_reference'] ? "?" : "fee_kind") . " WHERE id = ?")
           ->execute($b['payment_reference'] ? [$mode, $time, $mode === 'physical' ? $campus : null, en_fee_kind($mode), (int) $b['id']] : [$mode, $time, $mode === 'physical' ? $campus : null, (int) $b['id']]);
        return ['ok' => true];
    }

    /* ---------- tell me when the next intake opens ---------- */

    function en_interest_add(PDO $db, int $courseId, string $name, string $email, string $phone): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $email = strtolower(trim($email));
        $ph = trim($phone) === '' ? null : en_phone($phone);
        if (mb_strlen($name) < 3) return ['ok' => false, 'error' => 'Enter your full name.'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Enter a valid email address.'];
        if (trim($phone) !== '' && !$ph) return ['ok' => false, 'error' => 'Enter a valid Kenyan phone number or leave it empty.'];
        $st = $db->prepare("SELECT 1 FROM enrolment_interest WHERE LOWER(email) = ? AND (course_id = ? OR course_id IS NULL) AND notified_at IS NULL LIMIT 1");
        $st->execute([$email, $courseId]);
        if (!$st->fetchColumn()) {
            $db->prepare("INSERT INTO enrolment_interest (course_id, name, email, phone, created_at) VALUES (?, ?, ?, ?, ?)")->execute([$courseId ?: null, mb_substr($name, 0, 150), $email, $ph, en_now()]);
        }
        return ['ok' => true];
    }

    /** Tell the people who asked, once an opening that applies to their course is taking bookings. Run from the cron and when an opening is saved. */
    function en_notify_interest(PDO $db): int
    {
        if (!en_installed($db)) return 0;
        $n = 0;
        foreach ($db->query("SELECT * FROM enrolment_interest WHERE notified_at IS NULL ORDER BY id LIMIT 200")->fetchAll(PDO::FETCH_ASSOC) as $i) {
            $courseId = (int) ($i['course_id'] ?? 0);
            $st = $courseId ? en_state($db, $courseId) : null;
            if ($courseId && !$st['bookable']) continue;
            if (!$courseId) {
                $any = (int) $db->query("SELECT COUNT(*) FROM enrolment_openings WHERE status = 'open'")->fetchColumn();
                if (!$any) continue;
            }
            $link = $courseId ? course_clean_url($courseId) : en_abs('courses/index.php');
            en_queue($db, $i['email'], $i['name'], 'The next intake is open for bookings', en_email_html('Hello ' . en_e(en_first($i['name'])) . ', the next intake is open',
                'You asked us to tell you when the next intake opens. You can book your place now.', [], [['Book now', $link]]));
            $db->prepare("UPDATE enrolment_interest SET notified_at = ? WHERE id = ?")->execute([en_now(), (int) $i['id']]);
            $n++;
        }
        return $n;
    }

    /* ---------- the Enroll form while enrolment is closed ---------- */

    /** "Hello, our intake has closed. Enrolment for the [September 2026] intake closed on [31 October 2026]. You can still book your place in the next intake." */
    function en_closed_message(array $state): string
    {
        $m = 'Hello, our intake has closed.';
        $c = $state['last_closed'] ?? null;
        if ($c) {
            $when = !empty($c['closes_at']) ? $c['closes_at'] : ($c['closed_at'] ?? '');
            $m .= ' Enrolment for the ' . $c['title'] . ' intake closed' . ($when ? ' on ' . date('j F Y', strtotime($when)) : '') . '.';
        }
        return $m . ' You can still book your place in the next intake.';
    }

    /** Staff (admin and superadmin) can always register a student by hand, whatever the setting says. */
    function en_is_staff(): bool
    {
        return class_exists('SessionModel') && in_array((string) SessionModel::get('role'), ['admin', 'superadmin'], true);
    }

    /**
     * The server-side block: the closed message when this course's enrolment is closed and "Hide the Enroll form when enrolment is closed" is on,
     * else null. CourseRegistrationHandler::process() calls this, so a direct link or a posted form cannot get around the page.
     */
    function en_enrol_blocked(PDO $db, array $course): ?string
    {
        if (!en_installed($db) || !en_hide_form($db) || en_is_staff()) return null;
        $state = en_state($db, (int) $course['id']);
        if ($state['open'] || en_ea_booking($db, (int) $course['id'])) return null;   // enrolment is open, or this person came through their early-access link
        return en_closed_message($state);
    }


    /* ---------- early access, the seat hold, reminders and expiry ---------- */

    function en_hold_days(PDO $db): int
    {
        return en_cfg($db)['hold_days'];
    }

    function en_opening(PDO $db, int $id): ?array
    {
        $st = $db->prepare("SELECT * FROM enrolment_openings WHERE id = ?");
        $st->execute([$id]);
        $o = $st->fetch(PDO::FETCH_ASSOC);
        if ($o) $o['stage'] = en_stage($o, null, en_early_hours($db));
        return $o ?: null;
    }

    /** "Thu 1 Jan 2027, 8:00 AM EAT" */
    function en_when(?string $dt): string
    {
        return $dt ? date('D j M Y, g:i A', strtotime($dt)) . ' EAT' : '';
    }

    /** The "Enroll now with early access" link: the course page with the booking's secure token. */
    function en_early_url(array $b): string
    {
        return course_clean_url((int) $b['course_id']) . '?ea=' . en_token($b);
    }

    /** "2 days 4 hours" / "5 hours" / "under an hour" until a time (EAT clock time). */
    function en_left(string $until): string
    {
        $s = strtotime($until) - strtotime(en_now());
        if ($s <= 0) return 'now';
        $d = intdiv($s, 86400); $h = intdiv($s % 86400, 3600);
        if ($d > 0) return $d . ' day' . ($d === 1 ? '' : 's') . ($h ? ' ' . $h . ' hour' . ($h === 1 ? '' : 's') : '');
        return $h > 0 ? $h . ' hour' . ($h === 1 ? '' : 's') : 'under an hour';
    }

    /**
     * Send the early-access invite and start the hold (hold days from now). Only for a Confirmed, paid booking whose opening is in early access,
     * and only once. Unpaid, rejected or cancelled bookings get no invite. Returns true when an invite was sent.
     */
    function en_invite(PDO $db, array $b): bool
    {
        if (($b['status'] ?? '') !== 'confirmed' || ($b['payment_status'] ?? '') !== 'paid' || !empty($b['invited_at'])) return false;
        $o = en_opening($db, (int) $b['opening_id']);
        if (!$o || $o['stage'] !== 'early') return false;
        $hold = (new DateTimeImmutable(en_now(), new DateTimeZone('UTC')))->modify('+' . en_hold_days($db) . ' days')->format('Y-m-d H:i:s');
        $st = $db->prepare("UPDATE enrolment_bookings SET invited_at = ?, hold_until = ?, reminder_sent_at = NULL WHERE id = ? AND status = 'confirmed' AND invited_at IS NULL");
        $st->execute([en_now(), $hold, (int) $b['id']]);
        if (!$st->rowCount()) return false;
        $b = en_booking($db, (int) $b['id']);
        $rows = en_summary_rows($b) + ['Early access ends' => en_when($o['opens_at']), 'Your seat is held until' => en_when($hold)];
        en_queue($db, $b['email'], $b['full_name'], 'Early access: enroll now for ' . $b['course_title'] . ' (' . $b['reference'] . ')', en_email_html(
            'Hello ' . en_e(en_first($b['full_name'])) . ', you can enroll early',
            'Because you booked and paid, you can enroll before everyone else. Early access ends in <strong>' . en_e(en_left($o['opens_at'])) . '</strong>. Your seat is held for you until <strong>' . en_e(en_when($hold)) . '</strong>. '
            . 'Press the button, check your details, then press Enroll now. You do not pay the booking fee again.',
            $rows, [['Enroll now with early access', en_early_url($b)], ['Not ready? Move or cancel my booking', en_manage_url($b)]]));
        return true;
    }

    /** 24 hours before the hold ends, remind the person (once). */
    function en_remind(PDO $db, array $b): bool
    {
        if (($b['status'] ?? '') !== 'confirmed' || empty($b['hold_until']) || !empty($b['reminder_sent_at'])) return false;
        $st = $db->prepare("UPDATE enrolment_bookings SET reminder_sent_at = ? WHERE id = ? AND status = 'confirmed' AND reminder_sent_at IS NULL");
        $st->execute([en_now(), (int) $b['id']]);
        if (!$st->rowCount()) return false;
        en_queue($db, $b['email'], $b['full_name'], 'Your seat is held until ' . date('D j M, g:i A', strtotime($b['hold_until'])) . ' (' . $b['reference'] . ')', en_email_html(
            'Hello ' . en_e(en_first($b['full_name'])) . ', your seat is held for one more day',
            'Your seat in <strong>' . en_e($b['course_title']) . '</strong> is held until <strong>' . en_e(en_when($b['hold_until'])) . '</strong> (' . en_e(en_left($b['hold_until'])) . ' from now). '
            . 'If you have not enrolled by then, we release the seat to someone else. The booking fee is not refundable.',
            en_summary_rows($b), [['Enroll now with early access', en_early_url($b)], ['Not ready? Move or cancel my booking', en_manage_url($b)]]));
        return true;
    }

    /** The hold ended and the person has not enrolled: the booking becomes Expired, the seat is released (waiting list first, then everyone) and the person is told. */
    function en_expire(PDO $db, array $b): bool
    {
        if (($b['status'] ?? '') !== 'confirmed' || empty($b['hold_until']) || $b['hold_until'] > en_now()) return false;
        $st = $db->prepare("UPDATE enrolment_bookings SET status = 'expired' WHERE id = ? AND status = 'confirmed'");
        $st->execute([(int) $b['id']]);
        if (!$st->rowCount()) return false;
        $b = en_booking($db, (int) $b['id']);
        en_queue($db, $b['email'], $b['full_name'], 'Your held seat was released (' . $b['reference'] . ')', en_email_html(
            'Hello ' . en_e(en_first($b['full_name'])) . ', your held seat was released',
            'Your seat in <strong>' . en_e($b['course_title']) . '</strong> was held until ' . en_e(en_when($b['hold_until'])) . ' and you had not enrolled, so we released it. '
            . 'You can enroll if enrolment is still open and seats are left, or book your place in the next intake.', en_summary_rows($b), [['See my options', en_manage_url($b)]]));
        en_offer_next($db, (int) $b['opening_id'], (int) $b['course_id']);   // the waiting list first; then the seat is free for everyone
        return true;
    }

    /**
     * The 5-minute job: send the invites when early access starts, send the 24-hour reminders, and expire the holds that ended.
     * @return array{invites:int, reminders:int, expired:int}
     */
    function en_cron(PDO $db): array
    {
        $out = ['invites' => 0, 'reminders' => 0, 'expired' => 0];
        if (!en_installed($db)) return $out;
        en_autoclose($db);
        $now = en_now();
        // 1 expire first, so a seat freed now is offered before anything else
        $st = $db->prepare("SELECT id FROM enrolment_bookings WHERE status = 'confirmed' AND hold_until IS NOT NULL AND hold_until <= ?");
        $st->execute([$now]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) if (en_expire($db, en_booking($db, (int) $id))) $out['expired']++;
        // 2 invites: every Confirmed, paid booking of an opening that is in early access
        $hours = en_early_hours($db);
        foreach ($db->query("SELECT * FROM enrolment_openings")->fetchAll(PDO::FETCH_ASSOC) as $o) {
            if (en_stage($o, $now, $hours) !== 'early') continue;
            $q = $db->prepare("SELECT id FROM enrolment_bookings WHERE opening_id = ? AND status = 'confirmed' AND payment_status = 'paid' AND invited_at IS NULL ORDER BY id");
            $q->execute([(int) $o['id']]);
            foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) if (en_invite($db, en_booking($db, (int) $id))) $out['invites']++;
        }
        // 3 reminders: 24 hours before the hold ends
        $soon = date('Y-m-d H:i:s', strtotime($now) + 86400);
        $st = $db->prepare("SELECT id FROM enrolment_bookings WHERE status = 'confirmed' AND hold_until IS NOT NULL AND hold_until > ? AND hold_until <= ? AND reminder_sent_at IS NULL");
        $st->execute([$now, $soon]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) if (en_remind($db, en_booking($db, (int) $id))) $out['reminders']++;
        return $out;
    }

    /* ---------- the early-access link ---------- */

    /** A booking from its secure token whatever its status (used by the course page and the link). Null when the token is not valid for this course. */
    function en_ea_lookup(PDO $db, string $token, int $courseId): ?array
    {
        $b = en_by_token($db, $token);
        return $b && (int) $b['course_id'] === $courseId ? $b : null;
    }

    /** Remember the booking for this visit, so the page and the server know the person came through their early-access link. */
    function en_ea_remember(array $b): void
    {
        $_SESSION['en_ea'][(int) $b['course_id']] = (int) $b['id'];
    }

    /**
     * The booking this visitor may enroll with before enrolment opens, or null: it must be Confirmed and paid, its hold not over,
     * and its opening in early access or open. Checked on every page load and on every enrolment, so it cannot be used after the hold ends.
     */
    function en_ea_booking(PDO $db, int $courseId): ?array
    {
        $id = (int) ($_SESSION['en_ea'][$courseId] ?? 0);
        if (!$id) return null;
        $b = en_booking($db, $id);
        if (!$b || $b['status'] !== 'confirmed' || $b['payment_status'] !== 'paid' || (int) $b['course_id'] !== $courseId) return null;
        if (!empty($b['hold_until']) && $b['hold_until'] <= en_now()) return null;
        $o = en_opening($db, (int) $b['opening_id']);
        return $o && in_array($o['stage'], ['early', 'open'], true) ? $b + ['stage' => $o['stage']] : null;
    }

    /* ---------- email ---------- */

    function en_first(string $name): string
    {
        return ucfirst(strtolower(strtok(trim($name), ' ') ?: 'there'));
    }

    function en_abs(string $path): string
    {
        return function_exists('absolute_url') ? absolute_url($path) : rtrim((string) (function_exists('site_base_url') ? site_base_url() : ''), '/') . '/' . ltrim($path, '/');
    }

    function en_summary_rows(array $b): array
    {
        $r = ['Booking number' => $b['reference'], 'Course' => $b['course_title'] ?? '', 'Intake' => $b['opening_title'] ?? ''];
        if (!empty($b['classes_start'])) $r['Classes start'] = en_date($b['classes_start']);
        $r['Classes'] = ucfirst((string) $b['study_mode']) . ($b['campus'] ? ', ' . ucfirst((string) $b['campus']) . ' campus' : '') . (!empty($b['class_time']) ? ', ' . (EN_CLASS_TIMES[$b['study_mode']][$b['class_time']] ?? $b['class_time']) : '');
        return $r;
    }

    function en_email_html(string $heading, string $intro, array $rows = [], array $buttons = []): string
    {
        $h = '<div style="background:#f4f5f1;padding:24px 12px;font-family:Poppins,Arial,sans-serif;color:#1d1b1c"><div style="max-width:560px;margin:0 auto;background:#fff;border:1px solid #e9ebe6;border-radius:14px;overflow:hidden">'
           . '<div style="background:#373435;padding:18px 22px;color:#fff;font-weight:700">Dazu Training Institute</div><div style="padding:24px 26px;font-size:14px;line-height:1.6">'
           . '<p style="margin:0 0 10px;font-weight:600;font-size:16px">' . $heading . '</p><p>' . $intro . '</p>';
        if ($rows) {
            $h .= '<table style="width:100%;border-collapse:collapse;margin:14px 0">';
            foreach ($rows as $k => $v) $h .= '<tr><td style="padding:8px;border-bottom:1px solid #eee;color:#6b7067">' . en_e($k) . '</td><td style="padding:8px;border-bottom:1px solid #eee;font-weight:600;text-align:right">' . en_e($v) . '</td></tr>';
            $h .= '</table>';
        }
        foreach ($buttons as $i => [$label, $href]) {
            $h .= '<p style="margin:16px 0 6px"><a href="' . en_e($href) . '" style="display:inline-block;background:' . ($i === 0 ? '#44A024' : '#373435') . ';color:#fff;padding:11px 26px;border-radius:8px;font-weight:700;text-decoration:none">' . en_e($label) . '</a></p>';
        }
        return $h . '</div><div style="padding:14px 26px;border-top:1px solid #e9ebe6;color:#6b7067;font-size:12px;text-align:center">Dazu Training Institute | 0722 922 454 | dazu.ac.ke</div></div></div>';
    }

    /** Queue an email (sent by the 5-minute job, and retried if it fails). */
    function en_queue(PDO $db, string $to, string $name, string $subject, string $html): void
    {
        try {
            require_once __DIR__ . '/ClassCalendarService.php';
            cal_queue_mail($db, $to, $name, $subject, $html);
        } catch (Throwable $e) {
            error_log('[enrolment mail queue] ' . $e->getMessage());
        }
    }
}
