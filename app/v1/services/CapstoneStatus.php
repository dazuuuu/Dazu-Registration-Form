<?php
// app/v1/services/CapstoneStatus.php
//
// The ONLY capstone code the admin pages (and the certificate code) use. It returns status WORDS and plain reasons, never a mark,
// number, comment, file or letter text. The rules themselves live in ONE function, cap_certificate_readiness() in CapstoneService.php;
// everything here is a thin wrapper around it. Hidden capstones (draft or not opened yet) do not exist as far as this file is concerned.

if (!function_exists('cap_status_word')) {
    /**
     * 'Not set' (no open capstone for this student in this course), 'Not marked yet', 'Passed' or 'Not passed'.
     * Looks at the student's newest attempt of their newest capstone that is open or closed. A mark that is held does not count yet.
     */
    function cap_status_word(PDO $db, int $uid, int $courseId): string
    {
        try {
            require_once __DIR__ . '/CapstoneService.php';
            $r = cap_certificate_readiness($db, $uid, $courseId);
        } catch (Throwable $e) {
            return 'Not set';   // migration 088 not run yet
        }
        return $r['applies'] ? $r['words']['capstone'] : 'Not set';
    }

    /** Pass or above, Not ready, or Fail: a word only. */
    function cap_average_word(PDO $db, int $uid, int $courseId): string
    {
        try {
            require_once __DIR__ . '/CapstoneService.php';
            $r = cap_certificate_readiness($db, $uid, $courseId);
        } catch (Throwable $e) {
            return 'Not ready';
        }
        return $r['applies'] ? $r['words']['average'] : 'Not ready';
    }

    /**
     * The words the admin Certificates page shows for one enrolment (a registrations row): words only, never a number.
     * Also keeps the transcript and letter release in step (a page that shows the word also re-checks it).
     * @return array{finished:string, fees:string, capstone:string, average:string, certificate:string}
     */
    function cap_admin_cert_words(PDO $db, array $reg): array
    {
        try {
            require_once __DIR__ . '/CapstoneService.php';
            $uid = (int) ($reg['user_id'] ?? 0);
            $courseId = (int) ($reg['course_id'] ?? 0);
            $r = $uid ? cap_certificate_readiness($db, $uid, $courseId, (int) $reg['id']) : ['applies' => false];
            if (!$r['applies']) {
                require_once __DIR__ . '/CertificateService.php';
                $paid = cert_student_balance($db, (int) $reg['id']) <= 0;
                return ['finished' => 'No', 'fees' => $paid ? 'Paid' : 'Balance due', 'capstone' => 'Not set', 'average' => 'Not ready', 'certificate' => $paid ? 'Ready' : 'Waiting for fees'];
            }
            cap_release_sync($db, $uid, $courseId);
            return $r['words'];
        } catch (Throwable $e) {
            return ['finished' => 'No', 'fees' => 'Balance due', 'capstone' => 'Not set', 'average' => 'Not ready', 'certificate' => 'Not ready'];
        }
    }

    /**
     * Gate for the actions that create or issue a certificate. For a course without a capstone it says "does not apply" and the old
     * rules stay as they were. Otherwise all four conditions must be true; the reason is plain words with no marks.
     * @return array{applies:bool, ok:bool, failed:?string, reason:string}
     */
    function cap_certificate_gate(PDO $db, int $uid, int $courseId, ?int $registrationId = null): array
    {
        try {
            require_once __DIR__ . '/CapstoneService.php';
            $r = cap_certificate_readiness($db, $uid, $courseId, $registrationId);
        } catch (Throwable $e) {
            return ['applies' => false, 'ok' => true, 'failed' => null, 'reason' => ''];
        }
        if (!$r['applies']) return ['applies' => false, 'ok' => true, 'failed' => null, 'reason' => ''];
        return ['applies' => true, 'ok' => $r['ready'], 'failed' => $r['first_failed'], 'reason' => $r['reason']];
    }

    /** True when the tutor allowed the admin to preview this student's transcript (default off). */
    function cap_admin_may_preview_transcript(PDO $db, int $uid, int $courseId): bool
    {
        try {
            $st = $db->prepare('SELECT allow_admin_preview FROM capstone_transcript_pref WHERE student_user_id = ? AND course_id = ?');
            $st->execute([$uid, $courseId]);
            return (bool) $st->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Retakes waiting for payment: the admin sees that a retake waits, who it is for and the fee to record. Never a mark.
     * @return array<int,array{attempt_id:int, student:string, course:string, amount:float, attempt_no:int}>
     */
    function cap_retakes_waiting(PDO $db): array
    {
        try {
            $st = $db->query(
                "SELECT a.id AS attempt_id, a.attempt_no, a.retake_fee, co.title AS course,
                        COALESCE((SELECT r.full_name FROM registrations r WHERE r.user_id = a.student_user_id AND r.status = 'approved' ORDER BY r.id DESC LIMIT 1),
                                 (SELECT u.username FROM users u WHERE u.id = a.student_user_id)) AS student
                 FROM capstone_attempts a JOIN capstones c ON c.id = a.capstone_id JOIN courses co ON co.id = c.course_id
                 WHERE a.status = 'awaiting_payment' AND a.retake_paid_at IS NULL ORDER BY a.id"
            );
            return array_map(fn($r) => ['attempt_id' => (int) $r['attempt_id'], 'student' => (string) $r['student'], 'course' => (string) $r['course'],
                                        'amount' => (float) $r['retake_fee'], 'attempt_no' => (int) $r['attempt_no']], $st->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Enrolments the admin Certificates page lists for capstone courses: only students with a capstone that is open or closed.
     * Hidden capstones (draft or not opened yet) never make a student appear here.
     */
    function cap_admin_enrolments(PDO $db, int $limit = 300): array
    {
        try {
            $st = $db->query(
                "SELECT r.id, r.user_id, r.course_id, r.full_name, r.registration_no, c.title AS course_title
                 FROM registrations r JOIN courses c ON c.id = r.course_id
                 WHERE r.status = 'approved' AND r.user_id IS NOT NULL AND EXISTS (
                       SELECT 1 FROM capstone_students cs JOIN capstones cp ON cp.id = cs.capstone_id
                       WHERE cs.student_user_id = r.user_id AND cp.course_id = r.course_id AND cp.status IN ('open','closed') AND cs.removed_at IS NULL)
                 ORDER BY c.title, r.full_name LIMIT " . (int) $limit
            );
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }
}
