<?php
// app/v1/services/BookingPaymentService.php
// Daraja STK push for the "Book now" booking fee: initiate the push, handle Safaricom's callback
// (create the booking + a real registration + receipt once paid), and answer the frontend's poll.
require_once __DIR__ . '/EnrolmentService.php';
require_once __DIR__ . '/MpesaService.php';
require_once __DIR__ . '/../models/CourseModel.php';

if (!function_exists('bkp_installed')) {

    function bkp_installed(PDO $db): bool
    {
        static $ok = null;
        if ($ok === null) {
            try { $db->query('SELECT 1 FROM enrolment_stk_payments LIMIT 0'); $ok = true; } catch (Throwable $e) { $ok = false; }
        }
        return $ok;
    }

    /**
     * Validate the booking details and trigger an STK push for the booking fee. If the intake is
     * already full, the booking is created straight away on the waiting list instead (no payment).
     * @return array{ok:bool, error?:string, waitlisted?:bool, booking?:array, checkout_request_id?:string, amount?:float}
     */
    function bkp_initiate(PDO $db, array $course, array $in, string $ip): array
    {
        $courseId = (int) $course['id'];
        $state = en_state($db, $courseId);
        $o = null;
        foreach ($state['bookable'] as $cand) if ((int) $cand['id'] === (int) ($in['opening_id'] ?? 0)) $o = $cand;
        if (!$o) return ['ok' => false, 'error' => 'That intake is not taking bookings right now. Please choose another.'];

        $name = trim(preg_replace('/\s+/', ' ', (string) ($in['full_name'] ?? '')));
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        $phone = en_phone((string) ($in['phone'] ?? ''));
        $mode = ($in['study_mode'] ?? '') === 'physical' ? 'physical' : (($in['study_mode'] ?? '') === 'online' ? 'online' : '');
        $time = (string) ($in['class_time'] ?? '');
        $campus = $mode === 'physical' && in_array($in['campus'] ?? '', ['thika', 'nairobi'], true) ? (string) $in['campus'] : '';
        if (mb_strlen($name) < 3 || mb_strlen($name) > 150) return ['ok' => false, 'error' => 'Enter your full name.'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Enter a valid email address.'];
        if (!$phone) return ['ok' => false, 'error' => 'Enter a valid Kenyan phone number, for example 0712 345 678.'];
        if ($mode === '') return ['ok' => false, 'error' => 'Choose Online or Physical classes.'];
        if (!isset(EN_CLASS_TIMES[$mode][$time])) return ['ok' => false, 'error' => 'Choose your preferred class time.'];
        if ($mode === 'physical' && $campus === '') return ['ok' => false, 'error' => 'Choose the campus: Nairobi or Thika.'];
        if (empty($in['consent'])) return ['ok' => false, 'error' => 'Please tick that you agree to be contacted about your booking.'];

        $dup = $db->prepare("SELECT id FROM enrolment_bookings WHERE opening_id = ? AND course_id = ? AND (LOWER(email) = ? OR phone = ?) AND status <> 'cancelled' LIMIT 1");
        $dup->execute([(int) $o['id'], $courseId, $email, $phone]);
        if ($old = (int) $dup->fetchColumn()) return ['ok' => false, 'error' => 'You have already booked this intake.', 'duplicate' => en_booking($db, $old)];

        $seat = en_seat_info($db, $o, $courseId);
        $full = $seat['left'] !== null && $seat['left'] <= 0;
        if ($full) {
            // No payment yet: joins the waiting list straight away.
            $r = en_book($db, $course, $in, $ip);
            return $r['ok'] ? ['ok' => true, 'waitlisted' => true, 'booking' => $r['booking']] : $r;
        }

        $payerPhone = trim((string) ($in['pay_phone'] ?? $in['phone'] ?? ''));
        $cfg = require __DIR__ . '/../config/mpesa.php';
        $mpesa = new MpesaService($cfg);
        $norm = $mpesa->normalizePhone($payerPhone);
        if (!$norm) return ['ok' => false, 'error' => 'Enter a valid Safaricom number for the M-Pesa prompt, for example 0712 345 678.'];

        $amount = (float) en_cfg($db)['booking_fee'];
        try {
            $resp = $mpesa->stkPush($norm, (int) round($amount), 'BK' . $courseId, 'Booking fee');
        } catch (Throwable $e) {
            error_log('[booking stk] stkPush: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not reach M-Pesa right now. Please try again or pay by bank and contact us.'];
        }
        if (($resp['ResponseCode'] ?? '1') !== '0') {
            return ['ok' => false, 'error' => $resp['errorMessage'] ?? $resp['ResponseDescription'] ?? 'M-Pesa declined the request.'];
        }

        $db->prepare("INSERT INTO enrolment_stk_payments (course_id, opening_id, full_name, email, phone, phone2, study_mode, campus, class_time, consent, amount,
                          merchant_request_id, checkout_request_id, status, ip, created_at)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, 'pending', ?, ?)")
           ->execute([$courseId, (int) $o['id'], mb_substr($name, 0, 150), $email, $phone, trim((string) ($in['phone2'] ?? '')) ?: null, $mode, $campus ?: null, $time,
                      $amount, $resp['MerchantRequestID'] ?? null, $resp['CheckoutRequestID'] ?? null, $ip, en_now()]);

        return ['ok' => true, 'checkout_request_id' => (string) ($resp['CheckoutRequestID'] ?? ''), 'amount' => $amount];
    }

    /** Safaricom's STK callback: mark the row paid/failed and, on success, create the booking + registration + receipt. */
    function bkp_handle_callback(PDO $db, array $cb): void
    {
        if (empty($cb['valid']) || empty($cb['checkout_request_id'])) return;
        $st = $db->prepare("SELECT * FROM enrolment_stk_payments WHERE checkout_request_id = ?");
        $st->execute([$cb['checkout_request_id']]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['status'] !== 'pending') return;   // unknown or already handled (Safaricom retries callbacks)

        if ((int) $cb['result_code'] !== 0) {
            $db->prepare("UPDATE enrolment_stk_payments SET status = 'failed', result_desc = ?, updated_at = ? WHERE id = ?")
               ->execute([mb_substr((string) ($cb['result_desc'] ?? ''), 0, 255), en_now(), (int) $row['id']]);
            return;
        }

        try {
            $course = (new \Models\CourseModel($db))->getById((int) $row['course_id']);
            $in = ['opening_id' => $row['opening_id'], 'full_name' => $row['full_name'], 'email' => $row['email'], 'phone' => $row['phone'],
                   'phone2' => $row['phone2'], 'study_mode' => $row['study_mode'], 'class_time' => $row['class_time'], 'campus' => $row['campus']];
            $mpesaReceipt = (string) ($cb['receipt'] ?? '');
            $r = en_book_after_stk($db, $course, $in, (string) $row['ip'], $mpesaReceipt, (float) $row['amount']);
            if (!$r['ok']) {
                $db->prepare("UPDATE enrolment_stk_payments SET status = 'failed', result_desc = ?, updated_at = ? WHERE id = ?")
                   ->execute([mb_substr((string) $r['error'], 0, 255), en_now(), (int) $row['id']]);
                return;
            }
            $b = $r['booking'];
            if (!empty($r['waitlisted'])) {
                $db->prepare("UPDATE enrolment_stk_payments SET status = 'paid', mpesa_receipt = ?, phone_confirmed = ?, booking_id = ?, result_desc = 'Paid but seat filled first; waitlisted, needs manual follow-up', updated_at = ? WHERE id = ?")
                   ->execute([$mpesaReceipt, (string) ($cb['phone'] ?? ''), (int) $b['id'], en_now(), (int) $row['id']]);
                return;
            }
            $fee = en_after_stk_payment($db, $b, $mpesaReceipt);
            if (empty($fee['ok'])) {
                $db->prepare("UPDATE enrolment_stk_payments SET status = 'paid', mpesa_receipt = ?, phone_confirmed = ?, booking_id = ?, result_desc = ?, updated_at = ? WHERE id = ?")
                   ->execute([$mpesaReceipt, (string) ($cb['phone'] ?? ''), (int) $b['id'], mb_substr((string) ($fee['error'] ?? ''), 0, 255), en_now(), (int) $row['id']]);
                return;
            }
            $db->prepare("UPDATE enrolment_stk_payments SET status = 'paid', mpesa_receipt = ?, phone_confirmed = ?, booking_id = ?, registration_id = ?, payment_id = ?, updated_at = ? WHERE id = ?")
               ->execute([$mpesaReceipt, (string) ($cb['phone'] ?? ''), (int) $b['id'], (int) $fee['registration']['id'], (int) $fee['payment_id'], en_now(), (int) $row['id']]);
        } catch (Throwable $e) {
            error_log('[booking stk callback] ' . $e->getMessage());
            $db->prepare("UPDATE enrolment_stk_payments SET status = 'failed', result_desc = ?, updated_at = ? WHERE id = ?")
               ->execute(['Server error: ' . mb_substr($e->getMessage(), 0, 200), en_now(), (int) $row['id']]);
        }
    }

    /** Polled by the browser. @return array{success:bool, payment_status?:string, registration_no?:string, receipt_html?:string, message?:string} */
    function bkp_status(PDO $db, string $checkoutRequestId): array
    {
        $st = $db->prepare("SELECT * FROM enrolment_stk_payments WHERE checkout_request_id = ?");
        $st->execute([$checkoutRequestId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return ['success' => false, 'message' => 'Not found'];
        $out = ['success' => true, 'payment_status' => $row['status']];
        if ($row['status'] === 'paid') {
            if (!empty($row['registration_id'])) {
                $reg = $db->prepare("SELECT registration_no FROM registrations WHERE id = ?");
                $reg->execute([(int) $row['registration_id']]);
                $out['registration_no'] = (string) $reg->fetchColumn();
            }
            if (!empty($row['payment_id'])) {
                $p = $db->prepare("SELECT receipt_html FROM payments WHERE id = ?");
                $p->execute([(int) $row['payment_id']]);
                $out['receipt_html'] = (string) $p->fetchColumn();
                if (!empty($out['registration_no'])) $out['receipt_url'] = en_receipt_url((int) $row['payment_id'], $out['registration_no']);
            }
            if (!empty($row['booking_id'])) {
                $b = en_booking($db, (int) $row['booking_id']);
                if ($b) { $out['reference'] = $b['reference']; $out['waitlisted'] = $b['status'] === 'waiting'; }
            }
        } elseif ($row['status'] === 'failed') {
            $out['message'] = $row['result_desc'] ?: 'Payment was not completed.';
        }
        return $out;
    }
}
