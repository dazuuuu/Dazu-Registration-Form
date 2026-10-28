<?php
// app/v1/services/TranscriptService.php
// Academic transcript (migration 094). Only for courses of 12 months and longer; a shorter course has no transcript at all.
//
// THE RULES, in one place:
//  1 Rows: one per subject of the course (code = student number prefix / subject number, e.g. DDDS194 / 101), the mark is the automatic
//    subject mark (SubjectService::subjectMarks), plus the capstone as its own row (DDDS194 / CAP). Nobody types a mark.
//  2 Course average = the existing course average (AssignmentAverage, capstone at its weight). Nothing here changes a mark or an average.
//  3 Release: when the student is Ready (cap_certificate_readiness: finished, fees paid, capstone passed, average 50+) AND the stamp and
//    the director signature are set. Release copies everything printed into `transcripts`, so it is locked from then on.
//  4 Editable before release only, by the student's tutor: surname, other names, recommendation wording (blank = automatic wording).
//  5 Number: DT-<release year>-<5 digits>, counted per year in transcript_counters, never reused.
//  6 Every change (names, wording, release, settings) is written to transcript_events.

require_once __DIR__ . '/SubjectService.php';
require_once __DIR__ . '/CapstoneService.php';
require_once __DIR__ . '/AssignmentAverage.php';
require_once __DIR__ . '/StaffIdService.php';
require_once __DIR__ . '/../helpers/course_duration.php';
require_once __DIR__ . '/../helpers/signatures.php';

if (!defined('TR_MIN_MONTHS')) {
    define('TR_MIN_MONTHS', 12);
    define('TR_WATERMARK', ['light' => 'Light', 'medium' => 'Medium', 'strong' => 'Strong']);
}

if (!function_exists('tr_installed')) {

    function tr_installed(PDO $db): bool
    {
        static $ok = null;
        if ($ok === null) {
            try { $db->query('SELECT 1 FROM transcripts LIMIT 1'); $ok = true; } catch (Throwable $e) { $ok = false; }
        }
        return $ok;
    }

    function tr_log(PDO $db, ?int $transcriptId, ?int $uid, ?int $courseId, ?int $actor, string $event, array $details = []): void
    {
        $db->prepare('INSERT INTO transcript_events (transcript_id, student_user_id, course_id, actor_user_id, event, details) VALUES (?, ?, ?, ?, ?, ?)')
           ->execute([$transcriptId, $uid, $courseId, $actor, $event, $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null]);
    }

    /** True when the course is 12 months or longer (the only courses that get a transcript). */
    function tr_course_eligible(PDO $db, int $courseId): bool
    {
        $st = $db->prepare('SELECT * FROM courses WHERE id = ?');
        $st->execute([$courseId]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        return $c && course_effective_months($c) >= TR_MIN_MONTHS;
    }

    /* ---------------- settings: stamp, logo, watermark ---------------- */

    function tr_settings(PDO $db): array
    {
        $s = ['stamp_path' => '', 'logo_path' => '', 'watermark_on' => '0', 'watermark_level' => 'light'];
        try {
            foreach ($db->query('SELECT k, v FROM transcript_settings')->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
                if (array_key_exists($k, $s) && $v !== null) $s[$k] = (string) $v;
            }
        } catch (Throwable $e) {
        }
        if (!isset(TR_WATERMARK[$s['watermark_level']])) $s['watermark_level'] = 'light';
        return $s;
    }

    function tr_setting_save(PDO $db, string $k, ?string $v, int $actor): void
    {
        $old = tr_settings($db)[$k] ?? null;
        if ($old === $v) return;
        $db->prepare('INSERT INTO transcript_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)')->execute([$k, $v]);
        tr_log($db, null, null, null, $actor, 'setting_' . $k, ['from' => $old, 'to' => $v]);
    }

    /** A file saved by tr_store_image() ("storage:transcripts/...") as a real path, or null. */
    function tr_abs(?string $path): ?string
    {
        $path = trim((string) $path);
        if (!str_starts_with($path, 'storage:transcripts/')) return null;
        $base = realpath(storage_path('transcripts'));
        $abs = realpath(storage_path(substr($path, 8)));
        return $abs && $base && str_starts_with($abs, $base . DIRECTORY_SEPARATOR) && is_file($abs) ? $abs : null;
    }

    /** The private folder for transcript images (never served directly). */
    function tr_dir(string $sub): ?string
    {
        $root = storage_path('transcripts');
        $dir = $root . '/' . $sub;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return null;
        if (!is_file($root . '/.htaccess')) @file_put_contents($root . '/.htaccess', "Require all denied\nDeny from all\n");
        return $dir;
    }

    /**
     * Save an uploaded stamp or logo (PNG or JPG, up to 5 MB). It is re-encoded as PNG (transparency kept), so nothing but the picture is stored.
     * @return array{ok:bool, path?:string, error?:string}
     */
    function tr_store_image(array $file, string $kind): array
    {
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) return ['ok' => false, 'error' => 'Choose an image.'];
        if ($err !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 5 * 1048576) return ['ok' => false, 'error' => 'The image did not upload or is larger than 5 MB.'];
        if (!defined('UPLOAD_TEST_MODE') && !is_uploaded_file($file['tmp_name'])) return ['ok' => false, 'error' => 'The upload failed the security check.'];
        $info = @getimagesize($file['tmp_name']);
        if (!in_array($info['mime'] ?? '', ['image/png', 'image/jpeg'], true)) return ['ok' => false, 'error' => 'Upload a PNG or JPG image.'];
        $src = function_exists('imagecreatefromstring') ? @imagecreatefromstring((string) file_get_contents($file['tmp_name'])) : false;
        if (!$src) return ['ok' => false, 'error' => 'The image could not be read.'];
        $w = imagesx($src); $h = imagesy($src);
        $scale = min(1, 1200 / max($w, $h));
        $out = imagecreatetruecolor(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $src, 0, 0, 0, 0, imagesx($out), imagesy($out), $w, $h);
        $dir = tr_dir('assets');
        if (!$dir) return ['ok' => false, 'error' => 'Could not create the transcripts folder.'];
        $name = $kind . '-' . bin2hex(random_bytes(12)) . '.png';
        if (!imagepng($out, $dir . '/' . $name, 6)) return ['ok' => false, 'error' => 'The image could not be saved.'];
        return ['ok' => true, 'path' => 'storage:transcripts/assets/' . $name];
    }

    /** The logo to print now: the transcript logo from Settings, else the site logo. */
    function tr_current_logo_file(PDO $db): ?string
    {
        require_once __DIR__ . '/../helpers/certificate_pdf.php';
        return tr_abs(tr_settings($db)['logo_path']) ?: cert_logo_file();
    }

    /** What still blocks a release in Settings (empty = nothing). Words safe for anyone. */
    function tr_settings_missing(PDO $db): array
    {
        $miss = [];
        if (!tr_abs(tr_settings($db)['stamp_path'])) $miss[] = 'the official stamp';
        if (!dsig_active($db)) $miss[] = 'the director signature';
        return $miss;
    }

    /* ---------------- subject numbers ---------------- */

    /**
     * Give a number to every subject of the course that has none yet: 101, 102, ... in the order the subjects were set up.
     * A number, once given, never changes. Safe to call any time; two calls at once cannot give the same number (unique key, retried).
     */
    function tr_number_subjects(PDO $db, int $courseId): void
    {
        try {
            $st = $db->prepare('SELECT id FROM course_subjects WHERE course_id = ? AND subject_no IS NULL ORDER BY tutor_id, sort_order, id');
            $st->execute([$courseId]);
            $ids = $st->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            return;   // migration 094 not run yet
        }
        foreach ($ids as $id) tr_number_one_subject($db, (int) $id, $courseId);
    }

    function tr_number_one_subject(PDO $db, int $subjectId, int $courseId): void
    {
        for ($try = 0; $try < 5; $try++) {
            try {
                $up = $db->prepare('UPDATE course_subjects SET subject_no = (SELECT n FROM (SELECT GREATEST(100, COALESCE(MAX(subject_no), 100)) + 1 AS n FROM course_subjects WHERE course_id = ?) x)
                                    WHERE id = ? AND subject_no IS NULL');
                $up->execute([$courseId, $subjectId]);
                return;
            } catch (PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) !== 1062) return;   // only a clash with another new subject is retried
            }
        }
    }

    /* ---------------- the student and the rows ---------------- */

    /** The approved enrolment of the student in the course, with the course row. */
    function tr_registration(PDO $db, int $uid, int $courseId): ?array
    {
        $st = $db->prepare("SELECT r.*, c.title AS course_title FROM registrations r JOIN courses c ON c.id = r.course_id
                            WHERE r.user_id = ? AND r.course_id = ? AND r.status = 'approved' ORDER BY r.id DESC LIMIT 1");
        $st->execute([$uid, $courseId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** "DDDS194/26" -> "DDDS194" (the part before the year). */
    function tr_code_prefix(string $studentNo): string
    {
        $p = trim(explode('/', $studentNo)[0]);
        return $p !== '' ? $p : 'DTI';
    }

    function tr_grade(?float $mark): string
    {
        if ($mark === null) return '—';
        return $mark >= 70 ? 'Distinction' : ($mark >= 60 ? 'Credit' : ($mark >= 50 ? 'Pass' : 'Fail'));
    }

    function tr_pct(?float $v): string
    {
        return $v === null ? '—' : (int) round($v) . '%';
    }

    /** Surname = last word of the registered name, other names = the rest. */
    function tr_split_name(string $full): array
    {
        $parts = preg_split('/\s+/', trim($full)) ?: [];
        if (count($parts) < 2) return [$parts[0] ?? '', ''];
        $sur = array_pop($parts);
        return [$sur, implode(' ', $parts)];
    }

    function tr_draft(PDO $db, int $uid, int $courseId): array
    {
        $st = $db->prepare('SELECT * FROM transcript_drafts WHERE student_user_id = ? AND course_id = ?');
        $st->execute([$uid, $courseId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    function tr_released(PDO $db, int $uid, int $courseId): ?array
    {
        $st = $db->prepare('SELECT * FROM transcripts WHERE student_user_id = ? AND course_id = ?');
        $st->execute([$uid, $courseId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** The automatic recommendation wording, from the rows. */
    function tr_auto_recommendation(array $rows, ?float $average): string
    {
        $low = [];
        $missing = 0;
        foreach ($rows as $r) {
            if ($r['mark'] === null) $missing++;
            elseif ($r['mark'] < 50) $low[] = $r['subject'];
        }
        if ($average === null || $missing === count($rows)) return 'Results are not complete yet.';
        if ($low) return 'Retake needed in: ' . implode(', ', $low) . '.';
        if ($missing) return 'Results are not complete yet.';
        return $average >= 50 ? 'Proceed to certificate. All subjects passed.' : 'Course average is below the pass mark.';
    }

    /**
     * Everything the transcript shows, worked out now from the marks (the tutor's live view, and what is copied at release).
     * Each row: code, subject, detail (capstone parts), mark (float|null), grade.
     */
    function tr_build(PDO $db, int $uid, int $courseId): ?array
    {
        $reg = tr_registration($db, $uid, $courseId);
        if (!$reg) return null;
        tr_number_subjects($db, $courseId);
        $prefix = tr_code_prefix((string) $reg['registration_no']);

        $nos = [];
        try {
            $st = $db->prepare('SELECT id, subject_no FROM course_subjects WHERE course_id = ?');
            $st->execute([$courseId]);
            $nos = $st->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Throwable $e) {
        }
        $rows = [];
        foreach (subjectMarks($db, $uid, $courseId) as $m) {
            $no = (int) ($nos[$m['subject_id']] ?? 0);
            $rows[] = ['sort' => $no ?: 9000 + $m['subject_id'], 'code' => $prefix . ' / ' . ($no ?: '—'), 'subject' => $m['name'], 'detail' => '',
                       'mark' => $m['mark'], 'grade' => tr_grade($m['mark'])];
        }
        usort($rows, fn($a, $b) => $a['sort'] <=> $b['sort']);

        // The capstone: the newest released mark of the course, with its four parts.
        $cap = null;
        foreach (cap_transcript_rows($db, $uid, $courseId) as $r) if ($r['released']) $cap = $r;
        if ($cap || cap_course_has_capstone($db, $uid, $courseId)) {
            $detail = '';
            if ($cap) {
                $w = $db->prepare('SELECT w_project, w_docs, w_present, w_time FROM capstones WHERE id = ?');
                $w->execute([(int) $cap['capstone_id']]);
                $w = $w->fetch(PDO::FETCH_ASSOC) ?: ['w_project' => 50, 'w_docs' => 20, 'w_present' => 20, 'w_time' => 10];
                $n = fn($v) => rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.');
                $detail = 'Project ' . $n($cap['p_project']) . '/' . (int) $w['w_project'] . ' · Docs ' . $n($cap['p_docs']) . '/' . (int) $w['w_docs']
                        . ' · Present. ' . $n($cap['p_present']) . '/' . (int) $w['w_present'] . ' · Time ' . $n($cap['p_time']) . '/' . (int) $w['w_time'];
            }
            $mark = $cap ? (float) $cap['total'] : null;
            $rows[] = ['sort' => 99999, 'code' => $prefix . ' / CAP', 'subject' => 'Capstone project', 'detail' => $detail, 'mark' => $mark, 'grade' => tr_grade($mark)];
        }
        foreach ($rows as &$r) unset($r['sort']);
        unset($r);

        $avg = AssignmentAverage::forStudent($db, $uid, null, time())[$courseId]['average'] ?? null;
        $avg = $avg === null ? null : round((float) $avg, 2);
        [$sur, $other] = tr_split_name((string) $reg['full_name']);
        $draft = tr_draft($db, $uid, $courseId);
        $auto = tr_auto_recommendation($rows, $avg);

        $start = null;
        try {
            $s = $db->prepare('SELECT start_date FROM tutor_students WHERE user_id = ? AND start_date IS NOT NULL ORDER BY assigned_at DESC LIMIT 1');
            $s->execute([$uid]);
            $start = $s->fetchColumn() ?: null;
        } catch (Throwable $e) {
        }
        $campus = ($reg['study_mode'] ?? '') === 'physical' ? (trim((string) ($reg['study_location'] ?? '')) ?: 'Physical') : 'Online';

        return [
            'registration_id' => (int) $reg['id'], 'student_no' => (string) $reg['registration_no'], 'course_title' => (string) $reg['course_title'],
            'surname' => trim((string) ($draft['surname'] ?? '')) ?: $sur, 'other_names' => trim((string) ($draft['other_names'] ?? '')) ?: $other,
            'campus' => ucfirst($campus), 'admitted_on' => $start ?: substr((string) $reg['created_at'], 0, 10),
            'rows' => $rows, 'average' => $avg, 'average_word' => tr_grade($avg),
            'recommendation' => trim((string) ($draft['recommendation'] ?? '')) ?: $auto, 'recommendation_auto' => $auto,
            'recommendation_custom' => trim((string) ($draft['recommendation'] ?? '')) !== '',
        ];
    }

    /* ---------------- status and release ---------------- */

    /**
     * Where the transcript stands. 'state': none (course under 12 months or not enrolled) | progress | waiting_settings | ready | released.
     * 'word' is the status word the admin sees (never a mark).
     */
    function tr_status(PDO $db, int $uid, int $courseId): array
    {
        if (!tr_installed($db) || !tr_course_eligible($db, $courseId) || !tr_registration($db, $uid, $courseId)) {
            return ['state' => 'none', 'word' => 'No transcript', 'ready' => false, 'missing' => [], 'released' => null, 'readiness' => null];
        }
        if ($t = tr_released($db, $uid, $courseId)) {
            return ['state' => 'released', 'word' => 'Released', 'ready' => true, 'missing' => [], 'released' => $t, 'readiness' => null];
        }
        $r = cap_certificate_readiness($db, $uid, $courseId);
        $miss = tr_settings_missing($db);
        if ($r['ready'] && $miss) return ['state' => 'waiting_settings', 'word' => 'Waiting for ' . implode(' and ', $miss), 'ready' => true, 'missing' => $miss, 'released' => null, 'readiness' => $r];
        if ($r['ready']) return ['state' => 'ready', 'word' => 'Ready', 'ready' => true, 'missing' => [], 'released' => null, 'readiness' => $r];
        $word = !$r['applies'] ? 'In progress' : ($r['word'] === 'Ready' ? 'Ready' : $r['word']);
        return ['state' => 'progress', 'word' => $word === 'Not ready' ? 'In progress' : $word, 'ready' => false, 'missing' => $miss, 'released' => null, 'readiness' => $r];
    }

    /** Copy a picture into the released folder so later uploads in Settings never change this transcript. */
    function tr_snapshot_file(string $abs, int $transcriptKey, string $kind): ?string
    {
        $dir = tr_dir('released');
        if (!$dir) return null;
        $name = $transcriptKey . '-' . $kind . '-' . bin2hex(random_bytes(6)) . '.' . (strtolower(pathinfo($abs, PATHINFO_EXTENSION)) === 'jpg' ? 'jpg' : 'png');
        return @copy($abs, $dir . '/' . $name) ? 'storage:transcripts/released/' . $name : null;
    }

    /**
     * Release the transcript when everything is in place. Safe to call from any page or the scheduled job: the unique key on
     * (student, course) means it can only happen once, and the number is taken inside the same database transaction.
     * @return string 'released' | 'already' | 'not_ready' | 'none'
     */
    function tr_sync(PDO $db, int $uid, int $courseId, ?int $actor = null): string
    {
        $s = tr_status($db, $uid, $courseId);
        if ($s['state'] === 'none') return 'none';
        if ($s['state'] === 'released') return 'already';
        if (!$s['ready'] || $s['missing']) return 'not_ready';
        $d = tr_build($db, $uid, $courseId);
        if (!$d) return 'not_ready';

        $set = tr_settings($db);
        $sig = dsig_active($db);
        $stampAbs = tr_abs($set['stamp_path']);
        $logoAbs = tr_current_logo_file($db);
        $key = $uid . '-' . $courseId;
        $stamp = $stampAbs ? tr_snapshot_file($stampAbs, crc32($key), 'stamp') : null;
        $sigPath = $sig ? tr_snapshot_file($sig['file'], crc32($key), 'signature') : null;
        $logo = $logoAbs ? tr_snapshot_file($logoAbs, crc32($key), 'logo') : null;
        if (!$stamp || !$sigPath) return 'not_ready';

        $now = new DateTimeImmutable('now', new DateTimeZone('Africa/Nairobi'));
        $year = (int) $now->format('Y');
        try {
            $db->beginTransaction();
            $db->prepare('INSERT IGNORE INTO transcript_counters (release_year, last_seq) VALUES (?, 0)')->execute([$year]);
            $c = $db->prepare('SELECT last_seq FROM transcript_counters WHERE release_year = ? FOR UPDATE');
            $c->execute([$year]);
            $seq = (int) $c->fetchColumn() + 1;
            $no = sprintf('DT-%d-%05d', $year, $seq);
            $ins = $db->prepare('INSERT IGNORE INTO transcripts (student_user_id, course_id, registration_id, transcript_no, release_year, release_seq, verify_token, surname, other_names,
                                 student_no, course_title, campus, admitted_on, rows_json, average, average_word, recommendation, logo_path, stamp_path, signature_path,
                                 director_name, director_title, watermark, released_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ins->execute([$uid, $courseId, $d['registration_id'], $no, $year, $seq, bin2hex(random_bytes(20)), $d['surname'], $d['other_names'],
                           $d['student_no'], $d['course_title'], $d['campus'], $d['admitted_on'], json_encode($d['rows'], JSON_UNESCAPED_UNICODE), $d['average'],
                           $d['average_word'], $d['recommendation'], $logo, $stamp, $sigPath, $sig['name'] ?? null, $sig['title'] ?? null,
                           $set['watermark_on'] === '1' ? $set['watermark_level'] : 'off', $now->format('Y-m-d H:i:s')]);
            if ($ins->rowCount() < 1) {   // released by another request a moment ago
                $db->rollBack();
                return 'already';
            }
            $id = (int) $db->lastInsertId();
            $db->prepare('UPDATE transcript_counters SET last_seq = ? WHERE release_year = ?')->execute([$seq, $year]);
            tr_log($db, $id, $uid, $courseId, $actor, 'released', ['transcript_no' => $no]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Transcript release failed: ' . $e->getMessage());
            return 'not_ready';
        }
        return 'released';
    }

    /** The scheduled job: release every transcript that has become ready (same students the capstone release checks). */
    function tr_sweep(PDO $db): int
    {
        if (!tr_installed($db)) return 0;
        $n = 0;
        try {
            $rows = $db->query('SELECT student_user_id AS u, course_id AS c FROM course_finish WHERE undone_at IS NULL
                                UNION SELECT student_user_id, course_id FROM course_release WHERE transcript_released_at IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return 0;
        }
        foreach ($rows as $r) if (tr_sync($db, (int) $r['u'], (int) $r['c']) === 'released') $n++;
        return $n;
    }

    /* ---------------- the tutor's edits (before release only) ---------------- */

    /** @return array{ok:bool, error?:string} */
    function tr_save_draft(PDO $db, int $uid, int $courseId, int $actor, array $in): array
    {
        if (tr_released($db, $uid, $courseId)) return ['ok' => false, 'error' => 'This transcript is released and locked. It cannot change.'];
        $old = tr_draft($db, $uid, $courseId);
        $clean = fn($v, $max) => mb_substr(trim(preg_replace('/\s+/', ' ', (string) $v)), 0, $max);
        $new = $old;
        if (array_key_exists('surname', $in)) {
            $sur = $clean($in['surname'], 80);
            $oth = $clean($in['other_names'] ?? '', 120);
            if ($sur === '' || !preg_match("/^[\\p{L}' .-]+$/u", $sur . ($oth !== '' ? ' ' . $oth : ''))) return ['ok' => false, 'error' => 'Names can use letters, spaces, apostrophes and dashes only, and the surname is needed.'];
            $new['surname'] = $sur;
            $new['other_names'] = $oth;
        }
        if (array_key_exists('recommendation', $in)) {
            $rec = $clean($in['recommendation'], 300);
            $new['recommendation'] = $rec === '' ? null : $rec;
        }
        $changes = [];
        foreach (['surname', 'other_names', 'recommendation'] as $k) {
            if (($old[$k] ?? null) !== ($new[$k] ?? null)) $changes[$k] = ['from' => $old[$k] ?? null, 'to' => $new[$k] ?? null];
        }
        if (!$changes) return ['ok' => true];
        $db->prepare('INSERT INTO transcript_drafts (student_user_id, course_id, surname, other_names, recommendation, updated_by) VALUES (?, ?, ?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE surname = VALUES(surname), other_names = VALUES(other_names), recommendation = VALUES(recommendation), updated_by = VALUES(updated_by)')
           ->execute([$uid, $courseId, $new['surname'] ?? null, $new['other_names'] ?? null, $new['recommendation'] ?? null, $actor]);
        tr_log($db, null, $uid, $courseId, $actor, isset($changes['recommendation']) && count($changes) === 1 ? 'recommendation_saved' : 'draft_saved', $changes);
        return ['ok' => true];
    }

    /* ---------------- printing ---------------- */

    function tr_verify_url(string $token): string
    {
        return course_share_absolute_url(url('verify/transcript.php')) . '?t=' . rawurlencode($token);
    }

    /** What the printed page needs, from a released row (locked) or from a live build (the tutor's draft view). */
    function tr_print_data(PDO $db, ?array $released, ?array $live): array
    {
        if ($released) {
            return [
                'draft' => false, 'no' => $released['transcript_no'], 'surname' => $released['surname'], 'other_names' => $released['other_names'],
                'course_title' => $released['course_title'], 'student_no' => $released['student_no'], 'campus' => $released['campus'], 'admitted_on' => $released['admitted_on'],
                'rows' => json_decode((string) $released['rows_json'], true) ?: [], 'average' => $released['average'] === null ? null : (float) $released['average'],
                'average_word' => $released['average_word'], 'recommendation' => $released['recommendation'],
                'logo' => tr_abs($released['logo_path']), 'stamp' => tr_abs($released['stamp_path']), 'signature' => tr_abs($released['signature_path']),
                'director_name' => $released['director_name'], 'director_title' => $released['director_title'], 'watermark' => $released['watermark'],
                'date' => $released['released_at'], 'qr' => tr_verify_url($released['verify_token']),
            ];
        }
        $set = tr_settings($db);
        return $live + [
            'draft' => true, 'no' => null, 'logo' => tr_current_logo_file($db), 'stamp' => null, 'signature' => null,
            'director_name' => dsig_active($db)['name'] ?? null, 'director_title' => null,
            'watermark' => $set['watermark_on'] === '1' ? $set['watermark_level'] : 'off', 'date' => null, 'qr' => null,
        ];
    }
}
