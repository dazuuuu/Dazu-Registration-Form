<?php
// app/v1/services/CertificateApplication.php
// Apply for a certificate: the student applies when ALL FOUR are true, the tutor writes the lines under the name, then approves and sends.
//
//  The four conditions (ONE function, certificateReadiness(), used by the Apply button, the server check on Apply, the tutor's checks and the
//  approve/send step; for courses with a capstone it is the capstone function cap_certificate_readiness(), which counts the same course time):
//   1 course time finished (the course length has passed counting from the day the student was added, or the tutor marked it finished by hand;
//     the tutor can move one student's end date later, with a reason)   2 fees paid 100%   3 capstone passed (50+, mark released)
//   4 course average 50+  (from the existing average service).
//  Courses with no capstone: condition 3 does not apply; condition 4 is checked when the student has released marks.
//  One application per student and course. A returned application is sent again (same row). The existing certificate is made by the existing code
//  (cert_create_for_registration / cert_issue / cert_send_email); the tutor's lines are saved as certificates.taught_skills. Nothing shows a mark.

require_once __DIR__ . '/CertificateService.php';
require_once __DIR__ . '/AssignmentAverage.php';
require_once __DIR__ . '/CapstoneService.php';
require_once __DIR__ . '/../models/AssignmentModel.php';
require_once __DIR__ . '/CertificateNotifier.php';

if (!defined('CERTAPP_LINES_MAX')) define('CERTAPP_LINES_MAX', 300);
if (!defined('CERTAPP_REMIND_DAYS')) define('CERTAPP_REMIND_DAYS', 3);

if (!function_exists('certificateReadiness')) {

    /* ---------------- audit ---------------- */

    function certapp_log(PDO $db, ?int $appId, ?int $studentUid, ?int $courseId, ?int $actor, string $event, array $details = []): void
    {
        try {
            $db->prepare('INSERT INTO certificate_events (application_id, student_user_id, course_id, actor_user_id, event, details) VALUES (?, ?, ?, ?, ?, ?)')
               ->execute([$appId, $studentUid, $courseId, $actor, $event, $details ? json_encode($details) : null]);
        } catch (Throwable $e) {
            error_log('[certificate log] ' . $e->getMessage());
        }
    }

    function certapp_now(): string { return gmdate('Y-m-d H:i:s'); }

    function certapp_today(): string { return (new DateTimeImmutable('now', new DateTimeZone('Africa/Nairobi')))->format('Y-m-d'); }

    /* ---------------- 1 course time ---------------- */

    /**
     * Course time for one student: start (the day they were added), the end (start + course length, or the later end date the tutor set),
     * and whether it is finished (the end day has come, or the tutor marked the course finished by hand).
     */
    function cert_course_time(PDO $db, int $uid, int $courseId): ?array
    {
        $st = $db->prepare("SELECT r.id AS reg_id, r.created_at, c.title, c.duration_months,
                                   (SELECT ts.start_date FROM tutor_students ts WHERE ts.user_id = r.user_id ORDER BY ts.assigned_at DESC LIMIT 1) AS start_date
                            FROM registrations r JOIN courses c ON c.id = r.course_id
                            WHERE r.user_id = ? AND r.course_id = ? AND r.status = 'approved' ORDER BY r.id DESC LIMIT 1");
        $st->execute([$uid, $courseId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return null;
        $tz = new DateTimeZone('Africa/Nairobi');
        $start = new DateTimeImmutable(substr((string) ($r['start_date'] ?: $r['created_at']), 0, 10), $tz);
        $months = max(1, (int) $r['duration_months']);
        $natural = $start->modify('+' . $months . ' months');
        $ext = $db->prepare('SELECT end_date, reason FROM course_end_dates WHERE student_user_id = ? AND course_id = ? ORDER BY id DESC LIMIT 1');
        $ext->execute([$uid, $courseId]);
        $e = $ext->fetch(PDO::FETCH_ASSOC);
        $extended = $e ? new DateTimeImmutable($e['end_date'], $tz) : null;
        $end = $extended && $extended > $natural ? $extended : $natural;
        $byHand = (bool) cap_finish_row($db, $uid, $courseId);
        $today = certapp_today();
        $passed = $today >= $end->format('Y-m-d');
        $total = max(1, $end->getTimestamp() - $start->getTimestamp());
        return [
            'reg_id' => (int) $r['reg_id'], 'title' => (string) $r['title'], 'months' => $months,
            'start' => $start->format('Y-m-d'), 'natural_end' => $natural->format('Y-m-d'), 'end' => $end->format('Y-m-d'),
            'extended' => $extended && $extended > $natural, 'extended_reason' => $e['reason'] ?? null,
            'by_hand' => $byHand, 'passed' => $passed, 'finished' => $passed || $byHand,
            'percent' => (int) max(0, min(100, round((time() - $start->getTimestamp()) / $total * 100))),
        ];
    }

    /** The tutor moves the end date of ONE student's course later (for example after a break). A reason is needed. */
    function certapp_extend_end(PDO $db, int $tutorId, int $actor, int $uid, int $courseId, string $newEnd, string $reason): array
    {
        $am = new \Models\AssignmentModel($db);
        if (!$am->isTutorStudent($tutorId, $uid)) return ['ok' => false, 'error' => 'That is not one of your students.'];
        $t = cert_course_time($db, $uid, $courseId);
        if (!$t) return ['ok' => false, 'error' => 'That student is not in this course.'];
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) return ['ok' => false, 'error' => 'Write the reason for the new end date.'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newEnd) || !strtotime($newEnd)) return ['ok' => false, 'error' => 'Choose the new end date.'];
        if ($newEnd <= $t['end']) return ['ok' => false, 'error' => 'The new end date must be after the current end date (' . date('j M Y', strtotime($t['end'])) . ').'];
        if ($newEnd > date('Y-m-d', strtotime($t['natural_end'] . ' +24 months'))) return ['ok' => false, 'error' => 'That end date is too far away.'];
        $db->prepare('INSERT INTO course_end_dates (student_user_id, course_id, end_date, reason, actor_user_id) VALUES (?, ?, ?, ?, ?)')
           ->execute([$uid, $courseId, $newEnd, mb_substr($reason, 0, 300), $actor]);
        certapp_log($db, null, $uid, $courseId, $actor, 'end_extended', ['from' => $t['end'], 'to' => $newEnd, 'reason' => mb_substr($reason, 0, 300)]);
        cap_release_sync($db, $uid, $courseId, $actor);
        return ['ok' => true];
    }

    /* ---------------- the four conditions: ONE function ---------------- */

    /**
     * @return array{applies:bool, ready:bool, first_failed:?string, reason:string, conditions:array, time:?array, balance:float, reg_id:int, has_capstone:bool}
     * conditions: time, fees, capstone, average, each [ok, word, reason].
     */
    function certificateReadiness(PDO $db, int $uid, int $courseId): array
    {
        $t = cert_course_time($db, $uid, $courseId);
        if (!$t) return ['applies' => false, 'ready' => false, 'first_failed' => 'time', 'reason' => 'You are not enrolled in this course.', 'conditions' => [], 'time' => null, 'balance' => 0.0, 'reg_id' => 0, 'has_capstone' => false];
        $cap = cap_certificate_readiness($db, $uid, $courseId, $t['reg_id']);
        $hasCap = (bool) $cap['applies'];
        $balance = cert_student_balance($db, $t['reg_id']);
        $fmt = fn(string $d) => date('j M Y', strtotime($d));
        $time = ['ok' => $t['finished'], 'word' => $t['finished'] ? 'Done' : 'Waiting',
                 'reason' => $t['by_hand'] ? 'Your tutor marked the course finished.'
                     : ($t['finished'] ? $t['months'] . ' months passed from the day you were added (ended ' . $fmt($t['end']) . ').'
                                       : $t['months'] . ' months from the day you were added. Your course ends on ' . $fmt($t['end']) . '.' . ($t['extended'] ? ' (Your tutor moved the end date.)' : ''))];
        $fees = ['ok' => $balance <= 0, 'word' => $balance <= 0 ? 'Done' : 'Waiting',
                 'reason' => $balance <= 0 ? 'Course fees paid in full.' : 'Fee balance of KES ' . number_format($balance) . '. Pay the balance to continue.'];
        if ($hasCap) {
            $capC = ['ok' => $cap['conditions']['capstone']['ok'], 'word' => $cap['conditions']['capstone']['ok'] ? 'Done' : 'Waiting', 'reason' => $cap['conditions']['capstone']['reason']];
            $avgC = ['ok' => $cap['conditions']['average']['ok'], 'word' => $cap['conditions']['average']['ok'] ? 'Done' : 'Waiting', 'reason' => $cap['conditions']['average']['reason']];
        } else {
            $avg = cap_course_average($db, $uid, $courseId);
            $capC = ['ok' => true, 'word' => 'Done', 'reason' => 'This course has no capstone project.'];
            $avgC = $avg === null ? ['ok' => true, 'word' => 'Done', 'reason' => 'There are no marks to count.']
                : ['ok' => $avg >= CAP_PASS, 'word' => $avg >= CAP_PASS ? 'Done' : 'Waiting', 'reason' => $avg >= CAP_PASS ? 'The course average is 50 or more.' : 'The course average is below 50.'];
        }
        $c = ['time' => $time, 'fees' => $fees, 'capstone' => $capC, 'average' => $avgC];
        $first = null;
        foreach ($c as $k => $v) if (!$v['ok']) { $first = $k; break; }
        return ['applies' => true, 'ready' => $first === null, 'first_failed' => $first, 'reason' => $first ? $c[$first]['reason'] : '', 'conditions' => $c,
                'time' => $t, 'balance' => $balance, 'reg_id' => $t['reg_id'], 'has_capstone' => $hasCap];
    }

    /* ---------------- reading ---------------- */

    function certapp_get(PDO $db, int $uid, int $courseId): ?array
    {
        $st = $db->prepare('SELECT * FROM certificate_applications WHERE student_user_id = ? AND course_id = ?');
        $st->execute([$uid, $courseId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    function certapp_by_id(PDO $db, int $id): ?array
    {
        $st = $db->prepare("SELECT a.*, c.title AS course_title, c.duration_months,
                                   COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, ''))), ''), u.username) AS student_name
                            FROM certificate_applications a JOIN courses c ON c.id = a.course_id JOIN users u ON u.id = a.student_user_id LEFT JOIN user_profiles up ON up.user_id = u.id
                            WHERE a.id = ?");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** An application of one of this tutor's own students (tutors.id), or null. */
    function certapp_get_for_tutor(PDO $db, int $id, int $tutorId): ?array
    {
        $a = certapp_by_id($db, $id);
        if (!$a) return null;
        return (new \Models\AssignmentModel($db))->isTutorStudent($tutorId, (int) $a['student_user_id']) ? $a : null;
    }

    /** All applications of this tutor's own students. */
    function certapp_list_for_tutor(PDO $db, int $tutorId): array
    {
        $st = $db->prepare("SELECT a.id FROM certificate_applications a WHERE a.student_user_id IN (SELECT ts.user_id FROM tutor_students ts WHERE ts.tutor_id = ?) ORDER BY a.applied_at DESC");
        $st->execute([$tutorId]);
        return array_values(array_filter(array_map(fn($id) => certapp_by_id($db, (int) $id), $st->fetchAll(PDO::FETCH_COLUMN))));
    }

    function certapp_pending_count(PDO $db, int $tutorId): int
    {
        try {
            $st = $db->prepare("SELECT COUNT(*) FROM certificate_applications a WHERE a.status = 'pending' AND a.student_user_id IN (SELECT ts.user_id FROM tutor_students ts WHERE ts.tutor_id = ?)");
            $st->execute([$tutorId]);
            return (int) $st->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /** Words only, for the admin: Applied, Approved, Sent (or empty when there is no application). */
    function certapp_admin_word(PDO $db, int $uid, int $courseId): string
    {
        try {
            $a = certapp_get($db, $uid, $courseId);
        } catch (Throwable $e) {
            return '';
        }
        if (!$a) return '';
        return ['pending' => 'Applied', 'returned' => 'Applied', 'approved' => 'Approved', 'sent' => 'Sent'][$a['status']] ?? '';
    }

    /* ---------------- the first draft of the lines ---------------- */

    /**
     * A first draft for the lines under the name. It continues the existing certificate sentence ("… programme of study in the <course> at Dazu
     * Training Institute"), so it starts with a small letter: "covering Python, Databases and SQL, with a final capstone project." Max 300 letters.
     */
    function certapp_draft_lines(PDO $db, int $courseId, int $tutorUserId, bool $hasCapstone): string
    {
        $names = [];
        $tid = $db->prepare('SELECT id FROM tutors WHERE user_id = ?');
        $tid->execute([$tutorUserId]);
        $tid = (int) $tid->fetchColumn();
        if ($tid && is_file(__DIR__ . '/SubjectService.php')) {
            try {
                require_once __DIR__ . '/SubjectService.php';
                $names = array_map(fn($s) => (string) $s['name'], subj_list($db, $courseId, $tid, true));
            } catch (Throwable $e) {
            }
        }
        $tail = $hasCapstone ? ', with a final capstone project.' : '.';
        $build = function (array $n) use ($tail): string {
            if (!$n) return $tail === '.' ? '' : 'with a final capstone project.';
            $last = array_pop($n);
            return 'covering ' . ($n ? implode(', ', $n) . ' and ' : '') . $last . $tail;
        };
        while ($names && mb_strlen($build($names)) > CERTAPP_LINES_MAX) array_pop($names);
        return $build($names);
    }

    /** Clean the tutor's lines: plain text on one line, 300 letters at most. @return array{0:string,1:?string} */
    function certapp_clean_lines(string $text): array
    {
        $t = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
        if (mb_strlen($t) > CERTAPP_LINES_MAX) return [$t, 'The lines can be up to ' . CERTAPP_LINES_MAX . ' letters. Yours has ' . mb_strlen($t) . '.'];
        return [$t, null];
    }

    /* ---------------- student: apply ---------------- */

    /** @return array{ok:bool, error?:string, id?:int} */
    function certapp_apply(PDO $db, int $uid, int $courseId, string $printName, bool $confirmed): array
    {
        $t = cert_course_time($db, $uid, $courseId);
        if (!$t) return ['ok' => false, 'error' => 'You are not enrolled in this course.'];
        // The server decides, whatever the page showed.
        $ready = certificateReadiness($db, $uid, $courseId);
        if (!$ready['ready']) {
            certapp_log($db, null, $uid, $courseId, $uid, 'apply_refused', ['condition' => $ready['first_failed']]);
            return ['ok' => false, 'error' => 'You cannot apply yet. ' . $ready['reason']];
        }
        $printName = trim(preg_replace('/\s+/u', ' ', $printName));
        if (mb_strlen($printName) < 3 || mb_strlen($printName) > 150) return ['ok' => false, 'error' => 'Write your name as it should print on the certificate.'];
        if (!$confirmed) return ['ok' => false, 'error' => 'Tick the box to say your name is spelled correctly.'];
        $cert = $db->prepare("SELECT 1 FROM certificates WHERE registration_id = ? AND status <> 'withdrawn' LIMIT 1");
        $cert->execute([$t['reg_id']]);
        if ($cert->fetchColumn()) return ['ok' => false, 'error' => 'A certificate already exists for this course.'];
        $tutor = cert_assigned_tutor($db, $uid);
        if (!$tutor) return ['ok' => false, 'error' => 'No tutor is assigned to you yet. Ask the office.'];
        $existing = certapp_get($db, $uid, $courseId);
        $now = certapp_now();
        if ($existing) {
            if ($existing['status'] !== 'returned') return ['ok' => false, 'error' => 'You have already applied for this course.'];
            // A returned application is sent again: the same row (one application per course).
            $up = $db->prepare("UPDATE certificate_applications SET status = 'pending', print_name = ?, name_confirmed_at = ?, applied_at = ?, tutor_user_id = ?, updated_at = ? WHERE id = ? AND status = 'returned'");
            $up->execute([$printName, $now, $now, (int) $tutor['user_id'], $now, (int) $existing['id']]);
            if ($up->rowCount() < 1) return ['ok' => false, 'error' => 'You have already applied for this course.'];
            $id = (int) $existing['id'];
        } else {
            $draft = certapp_draft_lines($db, $courseId, (int) $tutor['user_id'], $ready['has_capstone']);
            try {
                $db->prepare('INSERT INTO certificate_applications (student_user_id, registration_id, course_id, tutor_user_id, status, print_name, name_confirmed_at, lines_text, applied_at, updated_at)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                   ->execute([$uid, $t['reg_id'], $courseId, (int) $tutor['user_id'], 'pending', $printName, $now, $draft, $now, $now]);
            } catch (PDOException $e) {
                return ['ok' => false, 'error' => 'You have already applied for this course.'];   // the UNIQUE key: one application per course
            }
            $id = (int) $db->lastInsertId();
        }
        certapp_log($db, $id, $uid, $courseId, $uid, 'applied', ['print_name' => $printName]);
        require_once __DIR__ . '/CertificateNotifier.php';
        (new CertificateNotifier($db))->requestArrived(certapp_by_id($db, $id));
        return ['ok' => true, 'id' => $id];
    }

    /* ---------------- tutor: lines, return, approve, send ---------------- */

    function certapp_save_lines(PDO $db, int $id, int $tutorId, int $actor, string $text): array
    {
        $a = certapp_get_for_tutor($db, $id, $tutorId);
        if (!$a) return ['ok' => false, 'error' => 'Request not found.'];
        if ($a['status'] !== 'pending') return ['ok' => false, 'error' => 'These lines can only be changed while the request is pending. After sending, issue a new version.'];
        [$t, $err] = certapp_clean_lines($text);
        if ($err) return ['ok' => false, 'error' => $err];
        $db->prepare('UPDATE certificate_applications SET lines_text = ?, updated_at = ? WHERE id = ?')->execute([$t, certapp_now(), $id]);
        certapp_log($db, $id, (int) $a['student_user_id'], (int) $a['course_id'], $actor, 'lines_saved');
        return ['ok' => true, 'text' => $t];
    }

    function certapp_return(PDO $db, int $id, int $tutorId, int $actor, string $note): array
    {
        $a = certapp_get_for_tutor($db, $id, $tutorId);
        if (!$a) return ['ok' => false, 'error' => 'Request not found.'];
        $note = trim($note);
        if (mb_strlen($note) < 3) return ['ok' => false, 'error' => 'Write a short note for the student.'];
        $up = $db->prepare("UPDATE certificate_applications SET status = 'returned', return_note = ?, returned_at = ?, updated_at = ? WHERE id = ? AND status = 'pending'");
        $up->execute([mb_substr($note, 0, 500), certapp_now(), certapp_now(), $id]);
        if ($up->rowCount() < 1) return ['ok' => false, 'error' => 'Only a pending request can be returned.'];
        certapp_log($db, $id, (int) $a['student_user_id'], (int) $a['course_id'], $actor, 'returned', ['note' => mb_substr($note, 0, 500)]);
        require_once __DIR__ . '/CertificateNotifier.php';
        (new CertificateNotifier($db))->returned(certapp_by_id($db, $id));
        return ['ok' => true];
    }

    /** Copy the application onto its certificate: the printed name, the tutor's lines, and the link back. */
    function certapp_fill_certificate(PDO $db, int $certId, array $a, int $version = 1): void
    {
        $db->prepare('UPDATE certificates SET snapshot_name = ?, taught_skills = ?, application_id = ?, version = ? WHERE id = ?')
           ->execute([(string) $a['print_name'], (string) $a['lines_text'] !== '' ? (string) $a['lines_text'] : null, (int) $a['id'], $version, $certId]);
    }

    /**
     * Approve: the existing code makes the certificate (it checks fees and the capstone again). $send = true also sends it.
     * @return array{ok:bool, error?:string, certificate_id?:int, email?:string}
     */
    function certapp_approve(PDO $db, int $id, int $tutorId, int $actor, bool $send): array
    {
        $a = certapp_get_for_tutor($db, $id, $tutorId);
        if (!$a) return ['ok' => false, 'error' => 'Request not found.'];
        if ($a['status'] === 'approved' && $send) return certapp_send($db, $id, $tutorId, $actor);
        if ($a['status'] !== 'pending') return ['ok' => false, 'error' => 'Only a pending request can be approved.'];
        $ready = certificateReadiness($db, (int) $a['student_user_id'], (int) $a['course_id']);
        if (!$ready['ready']) {
            certapp_log($db, $id, (int) $a['student_user_id'], (int) $a['course_id'], $actor, 'approve_refused', ['condition' => $ready['first_failed']]);
            return ['ok' => false, 'error' => 'This cannot be approved now. ' . $ready['reason']];
        }
        $made = cert_create_for_registration($db, (int) $a['registration_id']);
        if (!$made['ok']) return ['ok' => false, 'error' => $made['error']];
        certapp_fill_certificate($db, (int) $made['id'], $a);
        $db->prepare("UPDATE certificate_applications SET status = 'approved', certificate_id = ?, approved_at = ?, updated_at = ? WHERE id = ? AND status = 'pending'")
           ->execute([(int) $made['id'], certapp_now(), certapp_now(), $id]);
        certapp_log($db, $id, (int) $a['student_user_id'], (int) $a['course_id'], $actor, 'approved', ['certificate' => (int) $made['id']]);
        return $send ? certapp_send($db, $id, $tutorId, $actor) : ['ok' => true, 'certificate_id' => (int) $made['id']];
    }

    /** Put the certificate on the student's Certificates page, email it with the PDF, and release the transcript and the letter with it. */
    function certapp_send(PDO $db, int $id, int $tutorId, int $actor): array
    {
        $a = certapp_get_for_tutor($db, $id, $tutorId);
        if (!$a || !$a['certificate_id']) return ['ok' => false, 'error' => 'Approve the request first.'];
        if ($a['status'] !== 'approved') return ['ok' => false, 'error' => 'This certificate was already sent. Use Send again.'];
        if (!cert_issue($db, (int) $a['certificate_id'])) return ['ok' => false, 'error' => 'The certificate cannot be issued now. ' . cert_can_issue($db, (int) $a['certificate_id'])['reason']];
        $up = $db->prepare("UPDATE certificate_applications SET status = 'sent', sent_at = ?, last_sent_at = ?, send_count = 1, updated_at = ? WHERE id = ? AND status = 'approved'");
        $up->execute([certapp_now(), certapp_now(), certapp_now(), $id]);
        $mail = cert_send_email($db, (int) $a['certificate_id']);
        certapp_log($db, $id, (int) $a['student_user_id'], (int) $a['course_id'], $actor, 'sent', ['certificate' => (int) $a['certificate_id'], 'email_ok' => $mail['ok']]);
        cap_release_sync($db, (int) $a['student_user_id'], (int) $a['course_id'], $actor);   // the transcript and the letter, by the existing release rules
        return ['ok' => true, 'certificate_id' => (int) $a['certificate_id'], 'email' => $mail['message'], 'email_ok' => $mail['ok']];
    }

    function certapp_resend(PDO $db, int $id, int $tutorId, int $actor): array
    {
        $a = certapp_get_for_tutor($db, $id, $tutorId);
        if (!$a || $a['status'] !== 'sent' || !$a['certificate_id']) return ['ok' => false, 'error' => 'Only a sent certificate can be sent again.'];
        $mail = cert_send_email($db, (int) $a['certificate_id']);
        $db->prepare('UPDATE certificate_applications SET last_sent_at = ?, send_count = send_count + 1, updated_at = ? WHERE id = ?')->execute([certapp_now(), certapp_now(), $id]);
        certapp_log($db, $id, (int) $a['student_user_id'], (int) $a['course_id'], $actor, 'resent', ['email_ok' => $mail['ok']]);
        return ['ok' => $mail['ok'], 'error' => $mail['ok'] ? null : $mail['message'], 'email' => $mail['message']];
    }

    /**
     * A mistake in a sent certificate is fixed by a NEW version: the old one is withdrawn and marked Replaced, its number is never reused
     * (numbers come from the row id), the new one carries the corrected lines (and name) and is sent again.
     */
    function certapp_new_version(PDO $db, int $id, int $tutorId, int $actor, string $newLines, ?string $newName = null): array
    {
        $a = certapp_get_for_tutor($db, $id, $tutorId);
        if (!$a || $a['status'] !== 'sent' || !$a['certificate_id']) return ['ok' => false, 'error' => 'Only a sent certificate can get a new version.'];
        [$lines, $err] = certapp_clean_lines($newLines);
        if ($err) return ['ok' => false, 'error' => $err];
        $name = $newName !== null ? trim(preg_replace('/\s+/u', ' ', $newName)) : (string) $a['print_name'];
        if (mb_strlen($name) < 3 || mb_strlen($name) > 150) return ['ok' => false, 'error' => 'Write the name as it should print.'];
        $old = $db->prepare('SELECT id, version, certificate_no FROM certificates WHERE id = ?');
        $old->execute([(int) $a['certificate_id']]);
        $old = $old->fetch(PDO::FETCH_ASSOC);
        if (!$old) return ['ok' => false, 'error' => 'Certificate not found.'];
        $newVersion = (int) $old['version'] + 1;
        $own = !$db->inTransaction();
        if ($own) $db->beginTransaction();
        try {
            $db->prepare("UPDATE certificates SET status = 'withdrawn', withdrawn_reason = ? WHERE id = ?")->execute(['Replaced by version ' . $newVersion, (int) $old['id']]);
            $made = cert_create_for_registration($db, (int) $a['registration_id']);
            if (!$made['ok']) throw new RuntimeException($made['error']);
            $a2 = ['id' => $a['id'], 'print_name' => $name, 'lines_text' => $lines];
            certapp_fill_certificate($db, (int) $made['id'], $a2, $newVersion);
            $db->prepare('UPDATE certificates SET replaced_by_id = ? WHERE id = ?')->execute([(int) $made['id'], (int) $old['id']]);
            $db->prepare('UPDATE certificate_applications SET certificate_id = ?, print_name = ?, lines_text = ?, updated_at = ? WHERE id = ?')
               ->execute([(int) $made['id'], $name, $lines, certapp_now(), $id]);
            if (!cert_issue($db, (int) $made['id'])) throw new RuntimeException('The certificate cannot be issued now.');
            if ($own) $db->commit();
        } catch (Throwable $e) {
            if ($own && $db->inTransaction()) $db->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        $mail = cert_send_email($db, (int) $made['id']);
        $db->prepare('UPDATE certificate_applications SET last_sent_at = ?, send_count = send_count + 1 WHERE id = ?')->execute([certapp_now(), $id]);
        certapp_log($db, $id, (int) $a['student_user_id'], (int) $a['course_id'], $actor, 'new_version', ['old' => (int) $old['id'], 'new' => (int) $made['id'], 'version' => $newVersion]);
        return ['ok' => true, 'certificate_id' => (int) $made['id'], 'version' => $newVersion, 'email' => $mail['message']];
    }

    /** A certificate array for the PREVIEW (watermark "PREVIEW"), built like the real one but never saved. */
    function certapp_preview_cert(PDO $db, array $a): array
    {
        $tutor = cert_assigned_tutor($db, (int) $a['student_user_id']);
        $reg = $db->prepare('SELECT r.created_at, c.duration_months, c.title FROM registrations r JOIN courses c ON c.id = r.course_id WHERE r.id = ?');
        $reg->execute([(int) $a['registration_id']]);
        $r = $reg->fetch(PDO::FETCH_ASSOC);
        return [
            'snapshot_name' => $a['print_name'], 'snapshot_course' => $r['title'], 'snapshot_duration_months' => max(1, (int) $r['duration_months']),
            'taught_skills' => $a['lines_text'], 'certificate_no' => 'DDS-PREVIEW', 'qr_code_hash' => 'preview', 'issued_at' => certapp_now(),
            'tutor_id' => $tutor['user_id'] ?? null, 'tutor_name' => $tutor['full_name'] ?? null, 'user_id' => (int) $a['student_user_id'],
            '_watermark' => 'PREVIEW',
        ];
    }

    /* ---------------- scheduled job + page-load backup ---------------- */

    /**
     * Opens the button (tells the student, once, by notice and email) and reminds the tutor of a request that waited 3 days.
     * Safe to run often. Called by the cron job (cap_tick) and, as a backup, on page loads (cap_tick_lazy / the Certificates pages).
     * @return array{opened:int, reminded:int}
     */
    function certapp_tick(PDO $db, ?int $onlyStudent = null): array
    {
        $out = ['opened' => 0, 'reminded' => 0];
        try {
            require_once __DIR__ . '/CertificateNotifier.php';
            $n = new CertificateNotifier($db);
            $sql = "SELECT r.user_id, r.course_id FROM registrations r
                    WHERE r.status = 'approved' AND r.user_id IS NOT NULL
                      AND NOT EXISTS (SELECT 1 FROM certificates c WHERE c.registration_id = r.id AND c.status <> 'withdrawn')
                      AND NOT EXISTS (SELECT 1 FROM certificate_applications a WHERE a.student_user_id = r.user_id AND a.course_id = r.course_id)
                      AND NOT EXISTS (SELECT 1 FROM certificate_events e WHERE e.student_user_id = r.user_id AND e.course_id = r.course_id AND e.event = 'button_opened')"
                 . ($onlyStudent ? ' AND r.user_id = ' . (int) $onlyStudent : '') . ' ORDER BY r.id DESC LIMIT 2000';
            foreach ($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
                // Cheap check first: only a student whose course time is finished (or marked finished) can be ready.
                $tm = cert_course_time($db, (int) $r['user_id'], (int) $r['course_id']);
                if (!$tm || !$tm['finished']) continue;
                $ready = certificateReadiness($db, (int) $r['user_id'], (int) $r['course_id']);
                if (!$ready['applies'] || !$ready['ready']) continue;
                // claim it: the first one to write the event tells the student
                certapp_log($db, null, (int) $r['user_id'], (int) $r['course_id'], null, 'button_opened');
                $c = $db->prepare("SELECT COUNT(*) FROM certificate_events WHERE student_user_id = ? AND course_id = ? AND event = 'button_opened'");
                $c->execute([(int) $r['user_id'], (int) $r['course_id']]);
                if ((int) $c->fetchColumn() === 1) { $n->buttonOpened((int) $r['user_id'], (string) $ready['time']['title']); $out['opened']++; }
            }
            if (!$onlyStudent) {
                $due = $db->prepare("SELECT id FROM certificate_applications WHERE status = 'pending' AND applied_at <= ? AND (reminded_at IS NULL OR reminded_at <= ?)");
                $cut = gmdate('Y-m-d H:i:s', time() - CERTAPP_REMIND_DAYS * 86400);
                $due->execute([$cut, $cut]);
                foreach ($due->fetchAll(PDO::FETCH_COLUMN) as $aid) {
                    $claim = $db->prepare("UPDATE certificate_applications SET reminded_at = ? WHERE id = ? AND status = 'pending' AND (reminded_at IS NULL OR reminded_at <= ?)");
                    $claim->execute([certapp_now(), (int) $aid, $cut]);
                    if ($claim->rowCount() > 0) { $n->waiting(certapp_by_id($db, (int) $aid)); $out['reminded']++; }
                }
            }
        } catch (Throwable $e) {
            error_log('[certificate tick] ' . $e->getMessage());
        }
        return $out;
    }
}
