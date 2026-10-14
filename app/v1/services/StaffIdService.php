<?php
/**
 * Tutor staff ID cards. Nothing to approve: as soon as a tutor has a passport photo the card is issued (staff number,
 * QR key) and emailed to them once. It uses the student card design with staff wording, and prints the tutor's own
 * signature on the back when they have uploaded one. The QR opens verify/id.php?k=<id_qr_hash> like a student card.
 *
 * Also emails a student their ID card PDF when the office generates it (idc_email_card()).
 */

require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/PortalNotifications.php';
require_once __DIR__ . '/IdCardService.php';
require_once __DIR__ . '/../helpers/signatures.php';
require_once __DIR__ . '/../helpers/id_card_pdf.php';

if (!function_exists('staff_tutor')) {
    /** Tutor row (by users.id) with name, email, courses taught and photo. */
    function staff_tutor(PDO $db, int $userId): ?array
    {
        $st = $db->prepare(
            "SELECT t.*, u.email, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name,''), ' ', COALESCE(up.last_name,''))), ''), u.username) AS full_name
             FROM tutors t JOIN users u ON u.id = t.user_id LEFT JOIN user_profiles up ON up.user_id = u.id WHERE t.user_id = ?"
        );
        $st->execute([$userId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** The array idc_pdf_bytes() draws for a tutor. */
    function staff_card(PDO $db, array $t): array
    {
        $sig = sig_abs($t['signature_path'] ?? null);
        return [
            '_kind' => 'staff', 'user_id' => (int) $t['user_id'], 'snapshot_name' => $t['full_name'], 'snapshot_course' => 'Tutor',
            'snapshot_reg_no' => (string) $t['staff_no'], 'valid_until' => (int) date('Y', strtotime(($t['id_issued_at'] ?: 'now') . ' +2 years')),
            'qr_hash' => (string) $t['id_qr_hash'], '_photo_file' => idc_student_photo_file($db, (int) $t['user_id']), '_signature_file' => $sig,
        ];
    }

    /**
     * Issue the tutor's card if they have a photo and none yet, then email it (first time only).
     * Safe to call on every photo / signature upload. Returns ['ok', 'issued' (new card), 'emailed', 'error'?].
     */
    function staff_issue(PDO $db, int $userId): array
    {
        $t = staff_tutor($db, $userId);
        if (!$t) return ['ok' => false, 'error' => 'Not a tutor account.'];
        if (!idc_student_photo_file($db, $userId)) return ['ok' => false, 'error' => 'Upload your passport photo first.'];
        $issued = false;
        if (empty($t['staff_no'])) {
            $no = 'DTI-STF-' . str_pad((string) $t['id'], 4, '0', STR_PAD_LEFT);
            $db->prepare('UPDATE tutors SET staff_no = ?, id_qr_hash = ?, id_issued_at = NOW() WHERE id = ? AND staff_no IS NULL')
               ->execute([$no, bin2hex(random_bytes(16)), (int) $t['id']]);
            $t = staff_tutor($db, $userId);
            $issued = true;
            PortalNotifications::notify($db, $userId, 'id_card', 'Your staff ID card is ready 🪪', 'Download it from My ID & signature. A copy was emailed to you.', 'tutor/profile/index.php');
        }
        $emailed = false;
        if (empty($t['id_emailed_at'])) {
            $r = idc_send_pdf_email($t['email'], $t['full_name'], 'Your Dazu staff ID card',
                'Your staff ID card is attached. Print it, or keep it on your phone. You can download it again any time from <strong>My ID &amp; signature</strong> in the tutor portal.',
                idc_pdf_bytes(staff_card($db, $t)), idc_pdf_filename(staff_card($db, $t)));
            if ($r['ok'] && empty($r['skipped'])) {
                $db->prepare('UPDATE tutors SET id_emailed_at = NOW() WHERE id = ?')->execute([(int) $t['id']]);
                $emailed = true;
            }
        }
        return ['ok' => true, 'issued' => $issued, 'emailed' => $emailed];
    }
}

if (!function_exists('idc_send_pdf_email')) {
    /** Short email with a PDF attached. */
    function idc_send_pdf_email(string $email, string $name, string $subject, string $message, string $pdf, string $filename): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'message' => 'No valid email address.'];
        $tmp = tempnam(sys_get_temp_dir(), 'idc');
        file_put_contents($tmp, $pdf);
        $p = 'font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;color:#111827;';
        $first = explode(' ', trim($name))[0] ?: '';
        $html = '<p style="' . $p . '">Hi ' . htmlspecialchars($first) . ',</p><p style="' . $p . '">' . $message . '</p>'
              . '<p style="' . $p . '">Dazu Training Institute</p>';
        try {
            $cfg = require __DIR__ . '/../config/mail.php';
            return (new Mailer($cfg))->sendWithAttachment($email, $name, $subject . ' — Dazu Training Institute', $html, $tmp, $filename);
        } catch (Throwable $e) {
            error_log('[idc_send_pdf_email] ' . $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        } finally {
            @unlink($tmp);
        }
    }

    /** Email a student their generated ID card. Returns the Mailer result. */
    function idc_email_card(PDO $db, int $cardId): array
    {
        require_once __DIR__ . '/../helpers/id_card_pdf.php';
        $card = idc_get($db, $cardId);
        if (!$card || !in_array($card['status'], ['generated', 'ready', 'collected'], true)) return ['ok' => false, 'message' => 'Card not generated.'];
        $st = $db->prepare('SELECT email FROM users WHERE id = ?');
        $st->execute([(int) ($card['user_id'] ?: $card['reg_user_id'])]);
        $email = (string) ($st->fetchColumn() ?: $card['email']);
        $r = idc_send_pdf_email($email, (string) $card['full_name'], 'Your student ID card',
            'Your student ID card is attached. Keep a copy on your phone. We will let you know when the printed card is ready for pickup. You can also download it from <strong>My Documents</strong> in the student portal.',
            idc_pdf_bytes($card), idc_pdf_filename($card));
        if ($r['ok'] && empty($r['skipped'])) {
            $db->prepare('UPDATE student_id_cards SET emailed_at = NOW() WHERE id = ?')->execute([$cardId]);
        }
        return $r;
    }
}
