<?php
// app/v1/services/StudentLetterService.php
// Letter of recommendation for every student (migration 095). Separate from the older certificate letter (RecommendationService) and
// the capstone letter; neither is changed.
//
// THE RULES, in one place:
//  1 Final letter: made by the system when the course is finished (the course time is over or the tutor marked it finished) AND the
//    fees are paid in full AND the director signature and the official stamp exist. It does not wait for the capstone or the average.
//  2 Final = locked: the wording, name, dates, subjects sentence, footer, signature and stamp are copied in when it is made.
//  3 Wording is fixed; the system fills in name, student number, course, campus, start and end months and the subjects:
//      12 months and longer: the first four subjects of the course's subject list;
//      shorter: the topics the tutor typed once on the course (first four);
//      neither: the course name only.
//  4 No marks or grades anywhere. No QR: the letter is checked by student number at dazu.ac.ke/verify (final letters only).
//  5 Every change (generation, settings, course topics) is written to letter_events.

require_once __DIR__ . '/CertificateService.php';
require_once __DIR__ . '/CertificateApplication.php';
require_once __DIR__ . '/TranscriptService.php';

if (!defined('SL_FOOTER_DEFAULTS')) {
    define('SL_FOOTER_DEFAULTS', ['email' => 'admin@dazu.ac.ke', 'website' => 'dazu.ac.ke', 'linkedin' => 'linkedin.com/company/dazu-training-institute']);
}

if (!function_exists('sl_installed')) {
    function sl_installed(PDO $db): bool
    {
        static $ok = null;
        if ($ok === null) {
            try { $db->query('SELECT 1 FROM student_letters LIMIT 1'); $ok = true; } catch (Throwable $e) { $ok = false; }
        }
        return $ok;
    }

    function sl_log(PDO $db, ?int $letterId, ?int $regId, ?int $courseId, ?int $actor, string $event, array $details = []): void
    {
        $db->prepare('INSERT INTO letter_events (letter_id, registration_id, course_id, actor_user_id, event, details) VALUES (?, ?, ?, ?, ?, ?)')
           ->execute([$letterId, $regId, $courseId, $actor, $event, $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null]);
    }

    /* ---------------- footer settings ---------------- */

    function sl_settings(PDO $db): array
    {
        $s = SL_FOOTER_DEFAULTS;
        try {
            foreach ($db->query('SELECT k, v FROM letter_settings')->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
                if (array_key_exists($k, $s) && trim((string) $v) !== '') $s[$k] = (string) $v;
            }
        } catch (Throwable $e) {
        }
        return $s;
    }

    /** @return array{ok:bool, error?:string} */
    function sl_settings_save(PDO $db, array $in, int $actor): array
    {
        $new = [];
        foreach (SL_FOOTER_DEFAULTS as $k => $def) {
            $v = mb_substr(trim((string) ($in[$k] ?? '')), 0, $k === 'linkedin' ? 200 : 160);
            $v = preg_replace('#^https?://#i', '', $v);
            if ($v === '') return ['ok' => false, 'error' => 'Fill in the email, the website and LinkedIn.'];
            $new[$k] = $v;
        }
        if (!filter_var($new['email'], FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Enter a valid email address.'];
        foreach (['website', 'linkedin'] as $k) {
            if (!preg_match('#^[A-Za-z0-9.-]+\.[A-Za-z]{2,}(/[A-Za-z0-9._~%/-]*)?$#', $new[$k])) return ['ok' => false, 'error' => 'The ' . $k . ' must look like dazu.ac.ke or linkedin.com/company/name.'];
        }
        $old = sl_settings($db);
        foreach ($new as $k => $v) {
            if ($old[$k] === $v) continue;
            $db->prepare('INSERT INTO letter_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)')->execute([$k, $v]);
            sl_log($db, null, null, null, $actor, 'setting_' . $k, ['from' => $old[$k], 'to' => $v]);
        }
        return ['ok' => true];
    }

    /** What blocks a final letter in Settings (words only). */
    function sl_settings_missing(PDO $db): array
    {
        return tr_installed($db) ? tr_settings_missing($db) : ['the official stamp', ...(dsig_active($db) ? [] : ['the director signature'])];
    }

    /* ---------------- topics ---------------- */

    function sl_course(PDO $db, int $courseId): ?array
    {
        $st = $db->prepare('SELECT * FROM courses WHERE id = ?');
        $st->execute([$courseId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    function sl_long_course(?array $course): bool
    {
        return $course && course_effective_months($course) >= TR_MIN_MONTHS;
    }

    /** The topics a tutor typed for a short course (one per line), or []. */
    function sl_course_topics(PDO $db, int $courseId): array
    {
        try {
            $st = $db->prepare('SELECT topics FROM course_letter_topics WHERE course_id = ?');
            $st->execute([$courseId]);
            $t = (string) $st->fetchColumn();
        } catch (Throwable $e) {
            return [];
        }
        return array_values(array_filter(array_map(fn($l) => trim(preg_replace('/\s+/', ' ', $l), " \t.,;"), preg_split('/\r\n|\r|\n/', $t))));
    }

    /** @return array{ok:bool, error?:string} */
    function sl_course_topics_save(PDO $db, int $courseId, string $text, int $actor): array
    {
        $lines = array_values(array_filter(array_map(fn($l) => mb_substr(trim(preg_replace('/\s+/', ' ', $l), " \t.,;"), 0, 80), preg_split('/\r\n|\r|\n/', $text))));
        if (count($lines) > 12) return ['ok' => false, 'error' => 'Up to 12 topics, one per line. The letter uses the first four.'];
        foreach ($lines as $l) if (!preg_match("/^[\\p{L}\\p{N} &'(),.\\/+#-]+$/u", $l)) return ['ok' => false, 'error' => 'Topics can use letters, numbers, spaces and & ( ) , . / + # - only.'];
        $old = sl_course_topics($db, $courseId);
        if ($old === $lines) return ['ok' => true];
        $db->prepare('INSERT INTO course_letter_topics (course_id, topics, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE topics = VALUES(topics), updated_by = VALUES(updated_by)')
           ->execute([$courseId, implode("\n", $lines), $actor]);
        sl_log($db, null, null, $courseId, $actor, 'course_topics_saved', ['from' => $old, 'to' => $lines]);
        return ['ok' => true];
    }

    /** Up to four subjects for the sentence: the course's subject list (12 months+), else the tutor's topics. */
    function sl_subjects(PDO $db, array $course): array
    {
        if (sl_long_course($course)) {
            tr_number_subjects($db, (int) $course['id']);
            try {
                $st = $db->prepare('SELECT name FROM course_subjects WHERE course_id = ? AND is_active = 1 ORDER BY COALESCE(subject_no, 65535), sort_order, id');
                $st->execute([(int) $course['id']]);
                $names = $st->fetchAll(PDO::FETCH_COLUMN);
            } catch (Throwable $e) {
                $names = [];
            }
        } else {
            $names = sl_course_topics($db, (int) $course['id']);
        }
        $out = [];
        foreach ($names as $n) {
            $n = trim((string) $n);
            if ($n !== '' && !in_array(mb_strtolower($n), array_map('mb_strtolower', $out), true)) $out[] = $n;
            if (count($out) === 4) break;
        }
        return $out;
    }

    /** "A", "A and B", "A, B and C". */
    function sl_join(array $items): string
    {
        if (count($items) < 2) return (string) ($items[0] ?? '');
        $last = array_pop($items);
        return implode(', ', $items) . ' and ' . $last;
    }

    /* ---------------- the student and the letter ---------------- */

    function sl_registration(PDO $db, int $regId): ?array
    {
        $st = $db->prepare("SELECT r.*, c.title AS course_title FROM registrations r JOIN courses c ON c.id = r.course_id WHERE r.id = ? AND r.status = 'approved'");
        $st->execute([$regId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    function sl_letter(PDO $db, int $regId): ?array
    {
        $st = $db->prepare('SELECT * FROM student_letters WHERE registration_id = ?');
        $st->execute([$regId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Course finished (time over, or marked finished by the tutor) and fees paid in full. */
    function sl_conditions(PDO $db, array $reg): array
    {
        $uid = (int) $reg['user_id'];
        $time = $uid ? cert_course_time($db, $uid, (int) $reg['course_id']) : null;
        $finished = $time ? (bool) $time['finished'] : false;
        $balance = cert_student_balance($db, (int) $reg['id']);
        return [
            'finished' => ['ok' => $finished, 'label' => 'Course finished', 'reason' => $finished ? '' : 'The course is not finished yet' . ($time ? ' (it ends on ' . date('j M Y', strtotime($time['end'])) . ').' : '.')],
            'fees' => ['ok' => $balance <= 0, 'label' => 'Fees paid in full', 'reason' => $balance <= 0 ? '' : 'A fee balance of KES ' . number_format($balance) . ' is still due.'],
            '_time' => $time,
        ];
    }

    /** Start and end months: the class start (or enrolment) date, and the finish date (or the planned end for a draft). */
    function sl_months(PDO $db, array $reg, ?array $time): array
    {
        $start = $time['start'] ?? substr((string) $reg['created_at'], 0, 10);
        $fin = $reg['user_id'] ? cap_finish_row($db, (int) $reg['user_id'], (int) $reg['course_id']) : null;
        $end = $fin['finished_on'] ?? ($time['end'] ?? date('Y-m-d'));
        if ($end > date('Y-m-d') && ($time['finished'] ?? false)) $end = date('Y-m-d');
        return [date('F Y', strtotime($start)), date('F Y', strtotime($end))];
    }

    /**
     * The fixed wording, filled in. Paragraphs are plain text with **bold** marks for the parts the system fills in.
     */
    function sl_paragraphs(array $f): array
    {
        $n = '**' . $f['name'] . '**';
        $where = $f['campus'] ? 'Dazu Training Institute, **' . $f['campus'] . ' campus**' : 'Dazu Training Institute (**online**)';
        $training = $f['subjects'] ? sl_join($f['subjects']) : $f['course'];
        return [
            'To Whom It May Concern,',
            'I am pleased to recommend ' . $n . ', Student No. **' . $f['student_no'] . '**, who studied **' . $f['course'] . '** at ' . $where . ', from ' . $f['start'] . ' to ' . $f['end'] . '. '
                . $n . ' has completed the course and is leaving us with practical, job-ready skills.',
            'During the course, ' . $n . ' worked through hands-on training in ' . $training . ', and handed in assignments and monthly projects that were marked by our tutors. '
                . 'They showed steady effort, took feedback well and kept improving as the course went on.',
            'We found ' . $n . ' to be committed, dependable and eager to learn, and we recommend them for any job, internship or further study they may seek. '
                . 'You can confirm that this letter is real by entering the student number above at ' . $f['verify_host'] . '/verify.',
        ];
    }

    /** Everything the letter shows, worked out now (the tutor's draft, and what is copied when the final letter is made). */
    function sl_build(PDO $db, array $reg): array
    {
        $course = sl_course($db, (int) $reg['course_id']) ?: ['id' => (int) $reg['course_id'], 'title' => $reg['course_title']];
        $cond = sl_conditions($db, $reg);
        [$start, $end] = sl_months($db, $reg, $cond['_time']);
        $name = mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string) $reg['full_name'])));
        $campus = ($reg['study_mode'] ?? '') === 'physical' ? ucfirst(trim((string) ($reg['study_location'] ?? ''))) : '';
        $site = sl_settings($db)['website'];
        $fields = ['name' => $name, 'student_no' => (string) $reg['registration_no'], 'course' => trim((string) $reg['course_title']), 'campus' => $campus,
                   'start' => $start, 'end' => $end, 'subjects' => sl_subjects($db, $course), 'verify_host' => preg_replace('#/.*$#', '', $site)];
        return $fields + ['paragraphs' => sl_paragraphs($fields), 'conditions' => $cond];
    }

    /**
     * 'state': none (no approved enrolment) | draft (not finished or fees due) | waiting_settings | ready | final. 'word' is safe for the admin.
     */
    function sl_status(PDO $db, int $regId): array
    {
        $reg = sl_installed($db) ? sl_registration($db, $regId) : null;
        if (!$reg) return ['state' => 'none', 'word' => 'No letter', 'reg' => null, 'letter' => null, 'conditions' => [], 'missing' => []];
        if ($l = sl_letter($db, $regId)) return ['state' => 'final', 'word' => 'Issued', 'reg' => $reg, 'letter' => $l, 'conditions' => [], 'missing' => []];
        $cond = sl_conditions($db, $reg);
        $miss = sl_settings_missing($db);
        if (!$cond['finished']['ok']) $word = 'Draft (course not finished)';
        elseif (!$cond['fees']['ok']) $word = 'Draft (waiting for fees)';
        elseif ($miss) $word = 'Waiting for ' . implode(' and ', $miss);
        else $word = 'Ready';
        $state = !$cond['finished']['ok'] || !$cond['fees']['ok'] ? 'draft' : ($miss ? 'waiting_settings' : 'ready');
        return ['state' => $state, 'word' => $word, 'reg' => $reg, 'letter' => null, 'conditions' => $cond, 'missing' => $miss];
    }

    /**
     * Make the final letter when everything is in place. Safe to call from any page or the scheduled job: the unique key on the
     * enrolment means it can only happen once.
     * @return string 'made' | 'already' | 'not_ready' | 'none'
     */
    function sl_sync(PDO $db, int $regId, ?int $actor = null): string
    {
        $s = sl_status($db, $regId);
        if ($s['state'] === 'none') return 'none';
        if ($s['state'] === 'final') return 'already';
        if ($s['state'] !== 'ready') return 'not_ready';
        $reg = $s['reg'];
        $b = sl_build($db, $reg);
        $set = sl_settings($db);
        $sig = dsig_active($db);
        $stampAbs = tr_abs(tr_settings($db)['stamp_path']);
        $logoAbs = tr_current_logo_file($db);
        $key = crc32('letter-' . $regId);
        $sigPath = $sig ? tr_snapshot_file($sig['file'], $key, 'lsig') : null;
        $stamp = $stampAbs ? tr_snapshot_file($stampAbs, $key, 'lstamp') : null;
        $logo = $logoAbs ? tr_snapshot_file($logoAbs, $key, 'llogo') : null;
        if (!$sigPath || !$stamp) return 'not_ready';
        $now = (new DateTimeImmutable('now', new DateTimeZone('Africa/Nairobi')))->format('Y-m-d H:i:s');
        $ins = $db->prepare('INSERT IGNORE INTO student_letters (registration_id, student_user_id, course_id, student_no, full_name, course_title, paragraphs_json,
                             footer_email, footer_website, footer_linkedin, logo_path, signature_path, stamp_path, badges_json, generated_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $ins->execute([$regId, $reg['user_id'] ?: null, (int) $reg['course_id'], $b['student_no'], $b['name'], $b['course'], json_encode($b['paragraphs'], JSON_UNESCAPED_UNICODE),
                       $set['email'], $set['website'], $set['linkedin'], $logo, $sigPath, $stamp, json_encode([]), $now]);
        if ($ins->rowCount() < 1) return 'already';
        $id = (int) $db->lastInsertId();
        sl_log($db, $id, $regId, (int) $reg['course_id'], $actor, 'generated', ['student_no' => $b['student_no']]);
        if ($reg['user_id']) {
            try {
                require_once __DIR__ . '/PortalNotifications.php';
                PortalNotifications::notify($db, (int) $reg['user_id'], 'letter', 'Your letter of recommendation is ready', $b['course'], 'letters/student.php?reg=' . $regId);
            } catch (Throwable $e) {
            }
        }
        return 'made';
    }

    /** The scheduled job: make every final letter that has become ready. */
    function sl_sweep(PDO $db): int
    {
        if (!sl_installed($db)) return 0;
        $n = 0;
        $ids = $db->query("SELECT r.id FROM registrations r LEFT JOIN student_letters l ON l.registration_id = r.id
                           WHERE r.status = 'approved' AND l.id IS NULL AND (r.registration_no IS NULL OR r.registration_no NOT LIKE 'PREVIEW-%')")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) if (sl_sync($db, (int) $id) === 'made') $n++;
        return $n;
    }

    /** The approved enrolments of a student (their own letters list). */
    function sl_student_regs(PDO $db, int $uid): array
    {
        $st = $db->prepare("SELECT r.id, c.title FROM registrations r JOIN courses c ON c.id = r.course_id WHERE r.user_id = ? AND r.status = 'approved' ORDER BY r.created_at");
        $st->execute([$uid]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** What the printed page needs, from a final letter (locked) or from a live build (the tutor's draft). */
    function sl_print_data(PDO $db, ?array $letter, ?array $live): array
    {
        if ($letter) {
            return [
                'draft' => false, 'student_no' => $letter['student_no'], 'date' => $letter['generated_at'],
                'paragraphs' => json_decode((string) $letter['paragraphs_json'], true) ?: [],
                'email' => $letter['footer_email'], 'website' => $letter['footer_website'], 'linkedin' => $letter['footer_linkedin'],
                'logo' => tr_abs($letter['logo_path']), 'signature' => tr_abs($letter['signature_path']), 'stamp' => tr_abs($letter['stamp_path']),
                'badges' => array_values(array_filter(array_map('tr_abs', json_decode((string) $letter['badges_json'], true) ?: []))),
            ];
        }
        $set = sl_settings($db);
        return [
            'draft' => true, 'student_no' => $live['student_no'], 'date' => null, 'paragraphs' => $live['paragraphs'],
            'email' => $set['email'], 'website' => $set['website'], 'linkedin' => $set['linkedin'],
            'logo' => tr_current_logo_file($db), 'signature' => null, 'stamp' => null, 'badges' => [],
        ];
    }
}
