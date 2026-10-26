<?php
/**
 * Certificate rules shared by admin, superadmin, the student pages and the public verify page.
 *
 *  - A certificate can only be generated for an approved enrolment whose student has a zero fee balance.
 *  - The student name, course, duration and tutor are taken from the system, never typed in.
 *  - Each certificate gets a random, unguessable qr_code_hash; the QR code on the PDF links to verify/?c=<hash>.
 */

require_once __DIR__ . '/../helpers/course_share.php';

if (!defined('CERT_DIRECTOR_NAME')) {
    define('CERT_DIRECTOR_NAME', 'Simon Mwangi');
}

if (!function_exists('cert_student_balance')) {
    /**
     * Outstanding fees across every approved enrolment of the student who owns this registration
     * (same formula as the admin student page: course fee + registration fee unless waived − approved payments).
     */
    function cert_student_balance(PDO $db, int $registrationId): float
    {
        $st = $db->prepare("SELECT id, user_id FROM registrations WHERE id = ?");
        $st->execute([$registrationId]);
        $reg = $st->fetch(PDO::FETCH_ASSOC);
        if (!$reg) {
            return 0.0;
        }
        $where = $reg['user_id'] ? "r.user_id = :k AND r.status = 'approved'" : "r.id = :k";
        $st = $db->prepare(
            "SELECT r.course_fee, r.registration_fee, r.registration_fee_waived,
                    (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.registration_id = r.id AND p.status = 'approved') AS paid
             FROM registrations r WHERE $where"
        );
        $st->execute([':k' => $reg['user_id'] ?: $registrationId]);
        $fee = 0.0;
        $paid = 0.0;
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $e) {
            $fee += (float) $e['course_fee'] + ($e['registration_fee_waived'] ? 0 : (float) $e['registration_fee']);
            $paid += (float) $e['paid'];
        }
        return max(0.0, $fee - $paid);
    }
}

if (!function_exists('cert_assigned_tutor')) {
    /** The tutor assigned to this student (most recently assigned first), or null. */
    function cert_assigned_tutor(PDO $db, ?int $userId): ?array
    {
        if (!$userId) {
            return null;
        }
        $sql = "SELECT t.id, t.user_id,
                    TRIM(COALESCE(%s NULLIF(TRIM(CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, ''))), ''), u.email)) AS full_name
             FROM tutor_students ts
             JOIN tutors t ON t.id = ts.tutor_id
             JOIN users u ON u.id = t.user_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE ts.user_id = ?
             ORDER BY ts.assigned_at DESC LIMIT 1";
        // The tutor's printed name (tutors.display_name, migration 080) when set; the account name otherwise.
        try {
            $st = $db->prepare(sprintf($sql, "NULLIF(TRIM(t.display_name), ''),"));
            $st->execute([$userId]);
        } catch (PDOException $e) {
            $st = $db->prepare(sprintf($sql, ''));
            $st->execute([$userId]);
        }
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

if (!function_exists('cert_eligibility')) {
    /**
     * @return array{ok:bool, reason?:string, reg?:array, balance?:float, certificate_id?:int}
     */
    function cert_eligibility(PDO $db, int $registrationId): array
    {
        $st = $db->prepare(
            "SELECT r.*, c.title AS course_name, c.duration_months
             FROM registrations r JOIN courses c ON c.id = r.course_id WHERE r.id = ?"
        );
        $st->execute([$registrationId]);
        $reg = $st->fetch(PDO::FETCH_ASSOC);
        if (!$reg) {
            return ['ok' => false, 'reason' => 'Enrolment not found.'];
        }
        $st = $db->prepare("SELECT id FROM certificates WHERE registration_id = ? AND status <> 'withdrawn' LIMIT 1");
        $st->execute([$registrationId]);
        if ($existing = $st->fetchColumn()) {
            return ['ok' => false, 'reason' => 'A certificate already exists for this course.', 'reg' => $reg, 'certificate_id' => (int) $existing];
        }
        if ($reg['status'] !== 'approved') {
            return ['ok' => false, 'reason' => 'Enrolment is not approved.', 'reg' => $reg];
        }
        $balance = cert_student_balance($db, $registrationId);
        if ($balance > 0) {
            return ['ok' => false, 'reason' => 'Fee balance of KES ' . number_format($balance) . ' must be cleared first.', 'reg' => $reg, 'balance' => $balance];
        }
        return ['ok' => true, 'reg' => $reg, 'balance' => 0.0];
    }
}

if (!function_exists('cert_create_for_registration')) {
    /** @return array{ok:bool, id?:int, error?:string} */
    function cert_create_for_registration(PDO $db, int $registrationId): array
    {
        $check = cert_eligibility($db, $registrationId);
        if (!$check['ok']) {
            return ['ok' => false, 'error' => $check['reason'], 'id' => $check['certificate_id'] ?? null];
        }
        $reg = $check['reg'];
        $tutor = cert_assigned_tutor($db, $reg['user_id'] ? (int) $reg['user_id'] : null);
        require_once __DIR__ . '/../helpers/signatures.php';
        $director = sig_certificate_director($db);   // the certificate keeps this signer even if another director signs later ones
        $months = max(1, (int) $reg['duration_months']);
        $start = strtotime($reg['created_at'] ?: 'now');

        $db->prepare(
            "INSERT INTO certificates
                (registration_id, course_id, certificate_no, qr_code_hash, status,
                 snapshot_name, snapshot_course, snapshot_duration_months, snapshot_dates,
                 tutor_id, tutor_name, director_id, ceo_name)
             VALUES (?, ?, ?, ?, 'being_prepared', ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $reg['id'],
            $reg['course_id'],
            'TMP-' . bin2hex(random_bytes(6)),
            bin2hex(random_bytes(16)),
            trim($reg['full_name']),
            $reg['course_name'],
            $months,
            date('F Y', $start) . ' to ' . date('F Y'),
            $tutor['user_id'] ?? null, // certificates.tutor_id references users.id, not tutors.id
            $tutor['full_name'] ?? null,
            $director['id'] ?? null,
            $director['full_name'] ?? CERT_DIRECTOR_NAME,
        ]);
        $id = (int) $db->lastInsertId();
        // The active director signature version from admin Settings (migration 081); the certificate keeps it.
        try {
            $dsig = $db->query('SELECT id, name FROM director_signatures WHERE is_active = 1 ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            if ($dsig) $db->prepare('UPDATE certificates SET director_signature_id = ?, ceo_name = ? WHERE id = ?')->execute([(int) $dsig['id'], $dsig['name'], $id]);
        } catch (Throwable $e) { /* migration 081 not run yet */ }
        // Certificate number is derived from the row id so it is always unique.
        $db->prepare("UPDATE certificates SET certificate_no = ? WHERE id = ?")
           ->execute(['DDS-' . date('Y') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT), $id]);
        return ['ok' => true, 'id' => $id];
    }
}

if (!function_exists('cert_search_enrolments')) {
    /** Approved enrolments matching name / reg no / email / phone / course, with eligibility flags. */
    function cert_search_enrolments(PDO $db, string $q, int $limit = 15): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $st = $db->prepare(
            "SELECT r.id, r.full_name, r.registration_no, r.email, r.phone, r.user_id, c.title AS course_name,
                    (SELECT cert.id FROM certificates cert WHERE cert.registration_id = r.id AND cert.status <> 'withdrawn' LIMIT 1) AS certificate_id
             FROM registrations r JOIN courses c ON c.id = r.course_id
             WHERE r.status = 'approved'
               AND (r.full_name LIKE :q1 OR r.registration_no LIKE :q2 OR r.email LIKE :q3 OR r.phone LIKE :q4 OR c.title LIKE :q5)
             ORDER BY (r.full_name LIKE :q6) DESC, r.full_name LIMIT " . (int) $limit
        );
        $st->execute([':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like, ':q5' => $like, ':q6' => $q . '%']);
        $rows = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $bal = cert_student_balance($db, (int) $r['id']);
            $tutor = cert_assigned_tutor($db, $r['user_id'] ? (int) $r['user_id'] : null);
            $rows[] = [
                'id' => (int) $r['id'],
                'name' => $r['full_name'],
                'reg_no' => $r['registration_no'],
                'email' => $r['email'],
                'phone' => $r['phone'],
                'course' => $r['course_name'],
                'tutor' => $tutor['full_name'] ?? null,
                'balance' => $bal,
                'certificate_id' => $r['certificate_id'] ? (int) $r['certificate_id'] : null,
                'eligible' => $bal <= 0 && !$r['certificate_id'],
            ];
        }
        return $rows;
    }
}

if (!function_exists('cert_verify_url')) {
    /** Absolute link encoded in the certificate QR code. */
    function cert_verify_url(?string $hash = null): string
    {
        $u = course_share_absolute_url(url('verify/'));
        return $hash ? $u . '?c=' . rawurlencode($hash) : $u;
    }
}

if (!function_exists('cert_duration_label')) {
    function cert_duration_label(int $months): string
    {
        return $months . '-month';
    }
}

if (!function_exists('cert_save_signature')) {
    /** Save an uploaded PNG/JPEG signature; returns its path relative to public/, or null. */
    function cert_save_signature(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 5 * 1024 * 1024) {
            return null;
        }
        $info = @getimagesize($file['tmp_name']);
        $ext = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg'][$info[2] ?? 0] ?? null;
        if (!$ext) {
            return null;
        }
        $dir = dirname(__DIR__, 3) . '/public/uploads/certificates/signatures';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        $name = 'sig_' . bin2hex(random_bytes(8)) . '.' . $ext;
        return move_uploaded_file($file['tmp_name'], $dir . '/' . $name) ? 'uploads/certificates/signatures/' . $name : null;
    }
}

if (!function_exists('cert_issue')) {
    /** Mark a certificate issued (keeps the first issue date). Statuses are admin-only tracking. */
    function cert_issue(PDO $db, int $certId): void
    {
        $st = $db->prepare("UPDATE certificates SET status = 'issued', issued_at = COALESCE(issued_at, NOW())
                      WHERE id = ? AND status NOT IN ('issued', 'collected')");
        $st->execute([$certId]);
        if ($st->rowCount()) {
            require_once __DIR__ . '/PortalNotifications.php';
            $q = $db->prepare("SELECT r.user_id, c.snapshot_course FROM certificates c JOIN registrations r ON r.id = c.registration_id WHERE c.id = ?");
            $q->execute([$certId]);
            if ($row = $q->fetch(PDO::FETCH_ASSOC)) {
                PortalNotifications::notify($db, (int) $row['user_id'], 'certificate', 'Your certificate is ready 🎓',
                    $row['snapshot_course'] . ' — download it from your portal.', 'students/certificates/index.php');
            }
        }
    }
}

if (!function_exists('cert_send_email')) {
    /**
     * Email the certificate PDF (attached) to the student, or to $toEmail when given.
     * @return array{ok:bool, message:string}
     */
    function cert_send_email(PDO $db, int $certId, ?string $toEmail = null): array
    {
        require_once __DIR__ . '/../models/CertificateModel.php';
        require_once __DIR__ . '/../helpers/certificate_pdf.php';
        require_once __DIR__ . '/StudentNotifier.php';

        $cert = (new \Models\CertificateModel($db))->getCertificateById($certId);
        if (!$cert) {
            return ['ok' => false, 'message' => 'Certificate not found.'];
        }
        if ($toEmail !== null && !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Enter a valid email address.'];
        }
        $st = $db->prepare("SELECT r.*, u.email AS login_email FROM registrations r LEFT JOIN users u ON u.id = r.user_id WHERE r.id = ?");
        $st->execute([$cert['registration_id']]);
        $reg = $st->fetch(PDO::FETCH_ASSOC);
        if (!$reg) {
            return ['ok' => false, 'message' => 'Could not find the student record.'];
        }
        $to = $toEmail ?: (trim((string) $reg['email']) ?: trim((string) $reg['login_email']));
        if ($to === '') {
            return ['ok' => false, 'message' => 'The student has no email address on record.'];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'cert');
        try {
            file_put_contents($tmp, cert_pdf_bytes($cert));
            $res = (new StudentNotifier($db))->sendCertificatePdf($reg, $cert, $tmp, $to);
        } catch (Throwable $e) {
            error_log('[certificate email] ' . $e->getMessage());
            $res = ['ok' => false, 'message' => 'The certificate PDF could not be created.'];
        } finally {
            @unlink($tmp);
        }
        if (!empty($res['skipped'])) {
            return ['ok' => false, 'message' => 'Email is not set up yet (Admin → Settings → Email), so nothing was sent.'];
        }
        return $res['ok']
            ? ['ok' => true, 'message' => 'Certificate PDF emailed to ' . $to . '.']
            : ['ok' => false, 'message' => 'Email could not be sent: ' . ($res['message'] ?? 'unknown error')];
    }
}

if (!function_exists('cert_public_pdf_url')) {
    /** Direct PDF link keyed by the certificate's unguessable QR hash (used in WhatsApp messages). */
    function cert_public_pdf_url(string $hash): string
    {
        return course_share_absolute_url(url('verify/pdf.php?c=' . rawurlencode($hash)));
    }
}

if (!function_exists('cert_student_photo')) {
    function cert_student_photo(PDO $db, ?int $userId): ?string
    {
        if (!$userId) {
            return null;
        }
        $st = $db->prepare("SELECT profile_photo_path FROM user_profiles WHERE user_id = ? AND profile_photo_status <> 'rejected'");
        $st->execute([$userId]);
        return $st->fetchColumn() ?: null;
    }
}

if (!function_exists('cert_save_student_photo')) {
    /**
     * Save a passport photo for the student (uploaded by an admin or by the student) — shown on the verify page.
     * @return array{ok:bool, error?:string}
     */
    function cert_save_student_photo(PDO $db, int $userId, array $file): array
    {
        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'This student has no portal account yet, so a photo cannot be saved.'];
        }
        $up = upload_image($file, dirname(__DIR__, 3) . '/public/uploads/profile_photos', 'uploads/profile_photos',
            ['jpg', 'jpeg', 'png', 'webp'], 5 * 1024 * 1024);
        if (!$up['ok']) {
            return ['ok' => false, 'error' => $up['error'] ?? 'Upload failed.'];
        }
        $db->prepare(
            "INSERT INTO user_profiles (user_id, profile_photo_path, profile_photo_submitted_at, profile_photo_status)
             VALUES (?, ?, NOW(), 'approved')
             ON DUPLICATE KEY UPDATE profile_photo_path = VALUES(profile_photo_path),
                                     profile_photo_submitted_at = NOW(), profile_photo_status = 'approved'"
        )->execute([$userId, ltrim((string) $up['path'], '/')]);
        return ['ok' => true];
    }
}

if (!function_exists('cert_student_has_any')) {
    /** True when this student account has at least one certificate (not withdrawn) — controls the Certificates menu. */
    function cert_student_has_any(int $userId): bool
    {
        static $cache = [];
        if (!isset($cache[$userId])) {
            try {
                $st = \Models\Connection::getInstance()->getConnection()->prepare(
                    "SELECT 1 FROM certificates c JOIN registrations r ON r.id = c.registration_id
                     WHERE r.user_id = ? AND c.status <> 'withdrawn' LIMIT 1"
                );
                $st->execute([$userId]);
                $cache[$userId] = (bool) $st->fetchColumn();
            } catch (Throwable $e) {
                $cache[$userId] = false;
            }
        }
        return $cache[$userId];
    }
}
