<?php
// app/v1/services/StudentLetterService.php
// Letter of recommendation for every student (migration 095). Separate from the older certificate letter (RecommendationService) and
// the capstone letter; neither is changed.
//
// THE RULES, in one place:
//  1 Final letter: made by the system when the course is finished (the course time is over or the tutor marked it finished) AND the
//    fees are paid in full AND the director signature and the official stamp exist. It does not wait for the capstone or the average.
//  2 Final: made once, with its date. It is printed with the CURRENT template, footer, logo, director signature and stamp
//    (sl_print_data), so letters already made follow later changes and a stamp uploaded later appears on them too.
//  3 The sentences are set; the system fills in name, student number, course, campus, months, the course's field (security, data, AI,
//    design, software, digital: picks the skills/strength/roles words, see sl_field) and the skills learnt (sl_subjects):
//      12 months and longer: the first four subjects; shorter: the tutor's topics (up to six);
//      else the curriculum units this student completed, else the course's units, else the course name.
//  4 No marks or grades anywhere. No QR: the letter is checked by student number at dazu.ac.ke/verify (final letters only).
//  5 Every change (generation, settings, course topics) is written to letter_events.

require_once __DIR__ . '/CertificateService.php';
require_once __DIR__ . '/CertificateApplication.php';
require_once __DIR__ . '/TranscriptService.php';

if (!defined('SL_FOOTER_DEFAULTS')) {
    define('SL_MAX_TOPICS', 6);
    define('SL_LONG_MONTHS', 12);   // 12 months and longer: subjects + "monthly projects" (not the transcript rule)
    define('SL_GREEN', '#048C44');   // the green of the Dazu logo: wave, Student No, footer icons
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
        return $course && course_effective_months($course) >= SL_LONG_MONTHS;
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
        if (!sl_tutor_teaches($db, $actor, $courseId)) return ['ok' => false, 'error' => 'Only the tutor of this course can change its topics.'];
        $lines = array_values(array_filter(array_map(fn($l) => mb_substr(trim(preg_replace('/\s+/', ' ', $l), " \t.,;"), 0, 80), preg_split('/\r\n|\r|\n/', $text))));
        if (count($lines) > SL_MAX_TOPICS) return ['ok' => false, 'error' => 'Up to ' . SL_MAX_TOPICS . ' short topics, one per line.'];
        foreach ($lines as $l) if (!preg_match("/^[\\p{L}\\p{N} &'(),.\\/+#-]+$/u", $l)) return ['ok' => false, 'error' => 'Topics can use letters, numbers, spaces and & ( ) , . / + # - only.'];
        $old = sl_course_topics($db, $courseId);
        if ($old === $lines) return ['ok' => true];
        $db->prepare('INSERT INTO course_letter_topics (course_id, topics, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE topics = VALUES(topics), updated_by = VALUES(updated_by)')
           ->execute([$courseId, implode("\n", $lines), $actor]);
        sl_log($db, null, null, $courseId, $actor, 'course_topics_saved', ['from' => $old, 'to' => $lines]);
        return ['ok' => true];
    }

    /** Whether this user is a tutor assigned to the course (the only person who may change its topics). */
    function sl_tutor_teaches(PDO $db, int $userId, int $courseId): bool
    {
        require_once __DIR__ . '/../models/TutorModel.php';
        $t = (new \Models\TutorModel($db))->getByUserId($userId);
        foreach ($t['courses'] ?? [] as $c) if ((int) $c['id'] === $courseId) return true;
        return false;
    }

    /** The short courses of a tutor that have no topics yet (the reminder on My Courses). */
    function sl_courses_missing_topics(PDO $db, array $courses): array
    {
        if (!sl_installed($db)) return [];
        $out = [];
        foreach ($courses as $c) {
            $full = sl_course($db, (int) $c['id']);
            if ($full && !sl_long_course($full) && !sl_course_topics($db, (int) $c['id'])) $out[] = (int) $c['id'];
        }
        return $out;
    }

    /** The subjects for the sentence: the first four of the course's subject list (12 months+), else the tutor's topics (up to six). */
    /**
     * The skills sentence. In order, the first that gives anything:
     *   12 months and longer: the course's subjects;  shorter: the topics the tutor typed;
     *   then the curriculum units this student completed;  then all the course's units.
     * Up to 4 for long courses, 6 for short ones. Unit numbers ("Unit 3:", "Module 2 -") are dropped.
     */
    function sl_subjects(PDO $db, array $course, int $regId = 0): array
    {
        $long = sl_long_course($course);
        $cid = (int) $course['id'];
        $lists = [];
        if ($long) {
            tr_number_subjects($db, $cid);
            $lists[] = sl_column($db, 'SELECT name FROM course_subjects WHERE course_id = ? AND is_active = 1 ORDER BY COALESCE(subject_no, 65535), sort_order, id', [$cid]);
        } else {
            $lists[] = sl_course_topics($db, $cid);
        }
        if ($regId) {
            $lists[] = sl_column($db, 'SELECT u.title FROM student_unit_progress p JOIN curriculum_units u ON u.id = p.unit_id
                WHERE p.registration_id = ? AND u.course_id = ? AND u.is_active = 1 AND (p.completed_at IS NOT NULL OR p.project_completed = 1 OR p.test_completed = 1)
                ORDER BY u.sort_order, u.id', [$regId, $cid]);
        }
        $lists[] = sl_column($db, 'SELECT title FROM curriculum_units WHERE course_id = ? AND is_active = 1 ORDER BY sort_order, id', [$cid]);
        $max = $long ? 4 : SL_MAX_TOPICS;
        foreach ($lists as $names) {
            $out = [];
            foreach ($names as $n) {
                $n = trim(preg_replace('/^\s*(unit|module|topic|week|chapter|lesson|part|subject)\s*\d+\s*[:.\-–—)]*\s*/iu', '', (string) $n));
                $n = trim(preg_replace('/^\d+\s*[:.\-–—)]\s*/u', '', $n));
                if ($n !== '' && !in_array(mb_strtolower($n), array_map('mb_strtolower', $out), true)) $out[] = $n;
                if (count($out) === $max) break;
            }
            if ($out) return $out;
        }
        return [];
    }

    function sl_column(PDO $db, string $sql, array $args): array
    {
        try {
            $st = $db->prepare($sql);
            $st->execute($args);
            return $st->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            return [];   // table not there yet
        }
    }

    /**
     * The field the course belongs to, from its title and category: picks the words of paragraphs 2, 4 and 5 so a cybersecurity
     * letter does not read like a marketing one. Unknown fields get the general wording.
     */
    function sl_field(PDO $db, array $course): array
    {
        $cat = '';
        if (!empty($course['category_id'])) {
            $cat = (string) (sl_column($db, 'SELECT name FROM course_categories WHERE id = ?', [(int) $course['category_id']])[0] ?? '');
        }
        $text = mb_strtolower(($course['title'] ?? '') . ' ' . $cat);
        $fields = [
            'security' => ['/secur|cyber|cuber|hack|forensic|network|pentest/', 'practical cybersecurity skills', 'securing systems and solving technical problems carefully',
                           'roles in cybersecurity, networking and IT support'],
            'data'     => ['/data|analy|excel|power ?bi|statist|machine learning|\bml\b/', 'practical data skills', 'working with data accurately and explaining what it shows',
                           'roles in data analysis, reporting and business intelligence'],
            'ai'       => ['/\bai\b|artificial|generative|automation|prompt/', 'practical artificial intelligence skills', 'using AI tools responsibly to solve real problems',
                           'roles in AI, automation and digital innovation'],
            'design'   => ['/design|\bui\b|\bux\b|graphic|figma/', 'practical design skills', 'designing clear, user-friendly work and taking feedback on it',
                           'roles in UI/UX, graphic and product design'],
            'web'      => ['/web|software|program|python|java|develop|coding|mpesa|hosting|app/', 'practical software development skills', 'writing working code and fixing problems patiently',
                           'roles in software and web development'],
            'digital'  => ['/market|social media|virtual assist|content|seo|digital/', 'practical digital skills', 'communicating clearly and managing work online',
                           'roles in digital marketing, social media and online support'],
        ];
        foreach ($fields as $k => $f) {
            if (preg_match($f[0], $text)) return ['key' => $k, 'skills' => $f[1], 'strength' => $f[2], 'roles' => $f[3]];
        }
        return ['key' => 'general', 'skills' => 'practical, job-ready technology skills', 'strength' => 'learning new tools quickly and applying them well',
                'roles' => 'roles in technology and related fields'];
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

    /** "in March 2026" when it starts and ends in the same month or the course is one month long, else "from March 2026 to June 2026". */
    function sl_when(string $start, string $end, ?array $course): string
    {
        $oneMonth = $course && function_exists('course_effective_months') && course_effective_months($course) <= 1;
        return $start === $end || $oneMonth ? 'in ' . $start : 'from ' . $start . ' to ' . $end;
    }

    /**
     * The fixed wording, filled in. Paragraphs are plain text with **bold** marks for the parts the system fills in.
     */
    /**
     * The wording of the approved template (the design the office signed off). Only the student's details are filled in:
     * name, student number, course, campus, months, the topics, "monthly projects" (12 months and longer), the capstone
     * sentence (only when it was completed), the verify website and the contact email.
     */
    function sl_paragraphs(array $f): array
    {
        $n = '**' . $f['name'] . '**';
        $where = $f['campus'] ? 'Dazu Training Institute, **' . $f['campus'] . ' campus**,' : 'Dazu Training Institute (**online**),';
        $training = $f['subjects'] ? sl_join($f['subjects']) : $f['course'];
        $email = $f['email'] ?? SL_FOOTER_DEFAULTS['email'];
        return [
            'To Whom It May Concern,',
            'I am pleased to write this letter to recommend ' . $n . ', Student No. **' . $f['student_no'] . '**, who studied **' . $f['course'] . '** at ' . $where . ' ' . $f['when'] . '. '
                . 'We are happy to confirm that ' . $n . ' has completed the course in full, and we are glad to speak for the effort, discipline and skill that they showed during their time with us.',
            'Dazu Training Institute teaches digital skills that lead to real jobs, and our courses are practical from the first week. '
                . 'During the course, ' . $n . ' worked through hands-on training in ' . $training . '. Each topic was taught with practical exercises, so that the student learns by doing '
                . 'and not only by listening, and they gained skills they can use at work from the first day.',
            'Throughout the course, ' . $n . ' handed in assignments and ' . ($f['long'] ? 'monthly projects' : 'projects') . ', and each one was marked by our tutors, who gave feedback on how to improve. '
                . $n . ' took that feedback well, corrected mistakes quickly and kept getting better as the course went on.'
                . ($f['capstone'] ? ' They also completed a capstone project, where the student had to bring together everything they had learned and show that they can solve a real problem from start to finish.' : ''),
            'Beyond the technical work, we found ' . $n . ' to be committed, dependable and eager to learn. They met deadlines, asked good questions, worked well with their tutors and classmates, '
                . 'and showed the patience that this kind of work needs. These are qualities that employers value, and they are the reason we are comfortable giving this recommendation.',
            'We therefore recommend ' . $n . ' without hesitation for any job, internship, attachment or further study that they may seek, and we are confident that they will make a valuable '
                . 'contribution wherever they go. You can confirm that this letter is real by entering the student number above at ' . $f['verify_host'] . '/verify, and you are welcome to write to us at '
                . $email . ' if you need anything more.',
        ];
    }

    /** The student passed the course's capstone (released mark). No capstone, or not passed: false. */
    function sl_capstone_done(PDO $db, array $reg): bool
    {
        if (empty($reg['user_id'])) return false;
        require_once __DIR__ . '/CapstoneStatus.php';
        return cap_status_word($db, (int) $reg['user_id'], (int) $reg['course_id']) === 'Passed';
    }

    /** The site's current brand logo (the file behind brand.php?f=logo), so letters follow a logo change. */
    function sl_logo_file(PDO $db): ?string
    {
        require_once __DIR__ . '/../helpers/brand.php';
        return brand_file('logo') ?: tr_current_logo_file($db);
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
                   'start' => $start, 'end' => $end, 'when' => sl_when($start, $end, $course), 'subjects' => sl_subjects($db, $course, (int) $reg['id']), 'field' => sl_field($db, $course),
                   'long' => sl_long_course($course), 'capstone' => sl_capstone_done($db, $reg), 'verify_host' => preg_replace('#/.*$#', '', $site), 'email' => sl_settings($db)['email']];
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
        $logoAbs = sl_logo_file($db);
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
    /**
     * What the printed page needs. A final letter keeps its number and the date it was made, but everything else follows the
     * current template and Settings (wording, footer, logo, director signature, stamp), so letters already made pick up changes
     * and a stamp uploaded later. The signature and stamp saved when it was made are used only if none is set now.
     */
    function sl_print_data(PDO $db, ?array $letter, ?array $live): array
    {
        $set = sl_settings($db);
        $sig = dsig_active($db);
        $stampNow = tr_abs(tr_settings($db)['stamp_path']);
        if ($letter) {
            $reg = sl_registration($db, (int) $letter['registration_id']);
            $paras = $reg ? sl_build($db, $reg)['paragraphs'] : (json_decode((string) $letter['paragraphs_json'], true) ?: []);
            return [
                'draft' => false, 'student_no' => $letter['student_no'], 'date' => $letter['generated_at'], 'paragraphs' => $paras,
                'email' => $set['email'], 'website' => $set['website'], 'linkedin' => $set['linkedin'],
                'logo' => sl_logo_file($db), 'signature' => ($sig['file'] ?? null) ?: tr_abs($letter['signature_path']), 'stamp' => $stampNow ?: tr_abs($letter['stamp_path']),
                'badges' => array_values(array_filter(array_map('tr_abs', json_decode((string) $letter['badges_json'], true) ?: []))),
            ];
        }
        return [
            'draft' => true, 'student_no' => $live['student_no'], 'date' => null, 'paragraphs' => $live['paragraphs'],
            'email' => $set['email'], 'website' => $set['website'], 'linkedin' => $set['linkedin'],
            'logo' => sl_logo_file($db), 'signature' => null, 'stamp' => null, 'badges' => [],
        ];
    }
}
