<?php
/**
 * Student ID card rules shared by admin, superadmin, the student portal and the public verify page.
 *
 *  - A card can only be generated for an approved enrolment that has a registration number.
 *  - Cards are for physical students. An admin can waive this for an online student (mode_waived = 1).
 *  - Name, course and registration number are taken from the enrolment, never typed in.
 *  - Generating puts the PDF in the student's portal (My Documents). The admin then marks the printed card
 *    "ready" at Nairobi or Thika, and "collected" once the student picks it up.
 *  - A student can request a card from My Documents; the request shows up for the admin to generate or decline.
 *  - The QR code on the back opens verify/id.php?k=<qr_hash>, which shows only name, course and status.
 */

require_once __DIR__ . '/../helpers/course_share.php';
require_once __DIR__ . '/PortalNotifications.php';

const IDC_LOCATIONS = ['nairobi' => 'Nairobi', 'thika' => 'Thika'];
const IDC_STATUS_LABELS = [
    'requested' => 'Requested',
    'generated' => 'Being printed',
    'ready'     => 'Ready for pickup',
    'collected' => 'Collected',
    'declined'  => 'Declined',
];

if (!function_exists('idc_status_badge')) {
    function idc_status_badge(string $status): string
    {
        $cls = ['requested' => 'bg-info text-dark', 'generated' => 'bg-warning text-dark', 'ready' => 'bg-success',
                'collected' => 'bg-primary', 'declined' => 'bg-secondary'][$status] ?? 'bg-secondary';
        return '<span class="badge ' . $cls . '">' . htmlspecialchars(IDC_STATUS_LABELS[$status] ?? ucfirst($status)) . '</span>';
    }
}

if (!function_exists('idc_verify_url')) {
    /** Absolute link encoded in the QR code on the back of the card. */
    function idc_verify_url(?string $hash = null): string
    {
        $u = course_share_absolute_url(url('verify/id.php'));
        return $hash ? $u . '?k=' . rawurlencode($hash) : $u;
    }
}

if (!function_exists('idc_default_valid_until')) {
    /** Year the course ends (enrolment date + course length), never earlier than this year. */
    function idc_default_valid_until(array $reg): int
    {
        $start = strtotime((string) ($reg['created_at'] ?? '')) ?: time();
        $months = max(1, (int) ($reg['duration_months'] ?? 12));
        return max((int) date('Y'), (int) date('Y', strtotime('+' . $months . ' months', $start)));
    }
}

if (!function_exists('idc_registration')) {
    function idc_registration(PDO $db, int $registrationId): ?array
    {
        $st = $db->prepare(
            "SELECT r.*, c.title AS course_name, c.duration_months
             FROM registrations r JOIN courses c ON c.id = r.course_id WHERE r.id = ?"
        );
        $st->execute([$registrationId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

if (!function_exists('idc_active_card')) {
    /** The live card (or open request) for this enrolment — anything except a declined request. */
    function idc_active_card(PDO $db, int $registrationId): ?array
    {
        $st = $db->prepare("SELECT * FROM student_id_cards WHERE registration_id = ? AND status <> 'declined' ORDER BY id DESC LIMIT 1");
        $st->execute([$registrationId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

if (!function_exists('idc_get')) {
    /** One card with its enrolment details (user_id, study mode, login email). */
    function idc_get(PDO $db, int $id): ?array
    {
        $st = $db->prepare(
            "SELECT k.*, r.full_name, r.registration_no, r.study_mode, r.study_location, r.email, r.user_id AS reg_user_id,
                    c.title AS course_name
             FROM student_id_cards k
             JOIN registrations r ON r.id = k.registration_id
             JOIN courses c ON c.id = r.course_id
             WHERE k.id = ?"
        );
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

if (!function_exists('idc_list')) {
    /** Every card / request for the admin list, open requests first. */
    function idc_list(PDO $db, string $status = ''): array
    {
        $where = '';
        $args = [];
        if ($status !== '' && isset(IDC_STATUS_LABELS[$status])) {
            $where = 'WHERE k.status = ?';
            $args[] = $status;
        }
        $st = $db->prepare(
            "SELECT k.*, r.full_name, r.registration_no, r.study_mode, r.study_location, c.title AS course_name
             FROM student_id_cards k
             JOIN registrations r ON r.id = k.registration_id
             JOIN courses c ON c.id = r.course_id
             $where
             ORDER BY (k.status = 'requested') DESC, k.updated_at DESC, k.id DESC"
        );
        $st->execute($args);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('idc_counts')) {
    function idc_counts(PDO $db): array
    {
        $out = array_fill_keys(array_keys(IDC_STATUS_LABELS), 0);
        try {
            foreach ($db->query("SELECT status, COUNT(*) n FROM student_id_cards GROUP BY status") as $r) {
                $out[$r['status']] = (int) $r['n'];
            }
        } catch (Throwable $e) {
        }
        return $out;
    }
}

if (!function_exists('idc_search_enrolments')) {
    /** Approved enrolments matching name / reg no / email / phone / course, with study mode and card status. */
    function idc_search_enrolments(PDO $db, string $q, int $limit = 20): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $st = $db->prepare(
            "SELECT r.id, r.full_name, r.registration_no, r.email, r.phone, r.study_mode, r.study_location, r.created_at,
                    c.title AS course_name, c.duration_months,
                    (SELECT k.status FROM student_id_cards k WHERE k.registration_id = r.id AND k.status <> 'declined' ORDER BY k.id DESC LIMIT 1) AS card_status,
                    (SELECT k.id FROM student_id_cards k WHERE k.registration_id = r.id AND k.status <> 'declined' ORDER BY k.id DESC LIMIT 1) AS card_id
             FROM registrations r JOIN courses c ON c.id = r.course_id
             WHERE r.status = 'approved'
               AND (r.full_name LIKE :q1 OR r.registration_no LIKE :q2 OR r.email LIKE :q3 OR r.phone LIKE :q4 OR c.title LIKE :q5)
             ORDER BY (r.full_name LIKE :q6) DESC, r.full_name LIMIT " . (int) $limit
        );
        $st->execute([':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like, ':q5' => $like, ':q6' => $q . '%']);
        $rows = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rows[] = [
                'id'          => (int) $r['id'],
                'name'        => $r['full_name'],
                'reg_no'      => $r['registration_no'],
                'email'       => $r['email'],
                'phone'       => $r['phone'],
                'course'      => $r['course_name'],
                'mode'        => $r['study_mode'],
                'location'    => $r['study_location'],
                'valid_until' => idc_default_valid_until($r),
                'card_status' => $r['card_status'],
                'card_id'     => $r['card_id'] ? (int) $r['card_id'] : null,
            ];
        }
        return $rows;
    }
}

if (!function_exists('idc_generate')) {
    /**
     * Generate (or regenerate) the card for an enrolment. An open request for the same enrolment is fulfilled.
     * @return array{ok:bool, id?:int, error?:string}
     */
    function idc_generate(PDO $db, int $registrationId, bool $waiveMode = false, ?int $validUntil = null): array
    {
        $reg = idc_registration($db, $registrationId);
        if (!$reg) {
            return ['ok' => false, 'error' => 'Enrolment not found.'];
        }
        if ($reg['status'] !== 'approved') {
            return ['ok' => false, 'error' => 'The enrolment is not approved yet.'];
        }
        if (trim((string) $reg['registration_no']) === '') {
            return ['ok' => false, 'error' => 'This student has no registration number yet.'];
        }
        $online = $reg['study_mode'] !== 'physical';
        if ($online && !$waiveMode) {
            return ['ok' => false, 'error' => 'ID cards are for physical students. Tick "Waive" to generate one for this online student.'];
        }
        $year = $validUntil && $validUntil >= 2000 && $validUntil <= 2100 ? $validUntil : idc_default_valid_until($reg);

        $existing = idc_active_card($db, $registrationId);
        if ($existing && $existing['status'] !== 'requested') {
            return ['ok' => false, 'error' => 'This student already has an ID card.', 'id' => (int) $existing['id']];
        }

        $data = [trim((string) $reg['full_name']), $reg['course_name'], $reg['registration_no'], $year, $online ? 1 : 0];
        if ($existing) {
            $db->prepare(
                "UPDATE student_id_cards SET status = 'generated', snapshot_name = ?, snapshot_course = ?, snapshot_reg_no = ?,
                        valid_until = ?, mode_waived = ?, generated_at = NOW() WHERE id = ?"
            )->execute(array_merge($data, [$existing['id']]));
            $id = (int) $existing['id'];
        } else {
            $db->prepare(
                "INSERT INTO student_id_cards
                    (registration_id, user_id, qr_hash, status, snapshot_name, snapshot_course, snapshot_reg_no, valid_until, mode_waived, generated_at)
                 VALUES (?, ?, ?, 'generated', ?, ?, ?, ?, ?, NOW())"
            )->execute(array_merge([$registrationId, $reg['user_id'] ?: null, bin2hex(random_bytes(16))], $data));
            $id = (int) $db->lastInsertId();
        }
        if ($reg['user_id']) {
            PortalNotifications::notify($db, (int) $reg['user_id'], 'id_card', 'Your student ID card is ready to download 🪪',
                'Download it from My Documents. We will tell you when the printed card is ready for pickup.', 'students/documents/index.php');
        }
        return ['ok' => true, 'id' => $id];
    }
}

if (!function_exists('idc_set_status')) {
    /**
     * Move a generated card along: ready (needs a pickup location) → collected, or decline an open request.
     * @return array{ok:bool, error?:string}
     */
    function idc_set_status(PDO $db, int $id, string $status, ?string $location = null, ?string $note = null): array
    {
        $card = idc_get($db, $id);
        if (!$card) {
            return ['ok' => false, 'error' => 'ID card not found.'];
        }
        $userId = (int) ($card['user_id'] ?: $card['reg_user_id']);
        switch ($status) {
            case 'ready':
                if (!isset(IDC_LOCATIONS[$location ?? ''])) {
                    return ['ok' => false, 'error' => 'Choose where the student should pick up the card (Nairobi or Thika).'];
                }
                if (!in_array($card['status'], ['generated', 'ready', 'collected'], true)) {
                    return ['ok' => false, 'error' => 'Generate the card first.'];
                }
                $db->prepare("UPDATE student_id_cards SET status = 'ready', pickup_location = ?, ready_at = NOW(), collected_at = NULL WHERE id = ?")
                   ->execute([$location, $id]);
                PortalNotifications::notify($db, $userId, 'id_card', 'Your ID card is ready — go pick it 📍',
                    'Collect your printed student ID card at our ' . IDC_LOCATIONS[$location] . ' branch.', 'students/documents/index.php');
                return ['ok' => true];
            case 'collected':
                if (!in_array($card['status'], ['generated', 'ready'], true)) {
                    return ['ok' => false, 'error' => 'Only a generated card can be marked collected.'];
                }
                $db->prepare("UPDATE student_id_cards SET status = 'collected', collected_at = NOW() WHERE id = ?")->execute([$id]);
                return ['ok' => true];
            case 'generated':
                // Undo "ready" / "collected" (e.g. marked by mistake)
                $db->prepare("UPDATE student_id_cards SET status = 'generated', ready_at = NULL, collected_at = NULL WHERE id = ? AND status IN ('ready','collected')")
                   ->execute([$id]);
                return ['ok' => true];
            case 'declined':
                if ($card['status'] !== 'requested') {
                    return ['ok' => false, 'error' => 'Only an open request can be declined.'];
                }
                $db->prepare("UPDATE student_id_cards SET status = 'declined', admin_note = ? WHERE id = ?")
                   ->execute([$note !== null && $note !== '' ? mb_substr($note, 0, 255) : null, $id]);
                PortalNotifications::notify($db, $userId, 'id_card', 'ID card request declined',
                    $note ?: 'Your ID card request was declined. Contact the office for details.', 'students/documents/index.php');
                return ['ok' => true];
        }
        return ['ok' => false, 'error' => 'Unknown status.'];
    }
}

if (!function_exists('idc_delete')) {
    function idc_delete(PDO $db, int $id): void
    {
        $db->prepare("DELETE FROM student_id_cards WHERE id = ?")->execute([$id]);
    }
}

if (!function_exists('idc_student_enrolments')) {
    /** The student's approved enrolments, each with its current card / request (if any). */
    function idc_student_enrolments(PDO $db, int $userId): array
    {
        $st = $db->prepare(
            "SELECT r.id, r.registration_no, r.study_mode, c.title AS course_name
             FROM registrations r JOIN courses c ON c.id = r.course_id
             WHERE r.user_id = ? AND r.status = 'approved' ORDER BY r.created_at DESC"
        );
        $st->execute([$userId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $q = $db->prepare("SELECT * FROM student_id_cards WHERE registration_id = ? ORDER BY id DESC LIMIT 1");
            $q->execute([$r['id']]);
            $r['card'] = $q->fetch(PDO::FETCH_ASSOC) ?: null;
            $out[] = $r;
        }
        return $out;
    }
}

if (!function_exists('idc_request')) {
    /** Student asks for a card for one of their own approved enrolments. */
    function idc_request(PDO $db, int $userId, int $registrationId, string $note = ''): array
    {
        $st = $db->prepare("SELECT id FROM registrations WHERE id = ? AND user_id = ? AND status = 'approved'");
        $st->execute([$registrationId, $userId]);
        if (!$st->fetchColumn()) {
            return ['ok' => false, 'error' => 'Enrolment not found.'];
        }
        if ($card = idc_active_card($db, $registrationId)) {
            return ['ok' => false, 'error' => $card['status'] === 'requested' ? 'You have already requested an ID card.' : 'You already have an ID card.'];
        }
        $db->prepare(
            "INSERT INTO student_id_cards (registration_id, user_id, qr_hash, status, request_note, requested_at)
             VALUES (?, ?, ?, 'requested', ?, NOW())"
        )->execute([$registrationId, $userId, bin2hex(random_bytes(16)), $note !== '' ? mb_substr($note, 0, 255) : null]);
        return ['ok' => true];
    }
}

if (!function_exists('idc_student_photo_file')) {
    /** Absolute path of the student's passport photo, or null. */
    function idc_student_photo_file(PDO $db, ?int $userId): ?string
    {
        if (!$userId) {
            return null;
        }
        $st = $db->prepare("SELECT profile_photo_path FROM user_profiles WHERE user_id = ? AND profile_photo_status <> 'rejected'");
        $st->execute([$userId]);
        $rel = (string) $st->fetchColumn();
        if ($rel === '') {
            return null;
        }
        $rel = ltrim(preg_replace('#^/?Registration-System/public/#', '', $rel), '/');
        $abs = dirname(__DIR__, 3) . '/public/' . $rel;
        return is_file($abs) ? $abs : null;
    }
}
