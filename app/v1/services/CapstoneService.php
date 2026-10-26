<?php
// app/v1/services/CapstoneService.php
//
// The Capstone project: the final project of a course, for every course. Everything the rules need lives here, so every page
// (tutor, student, cron) enforces the same thing on the server:
//   - hidden / scheduled / open / closed, opened by hand or by date;
//   - marks out of 100 in four parts (Project, Documentation, Presentation, Time keeping), pass mark 50;
//   - once sent it is final unless the tutor returns it: at most 2 returns, always with instructions;
//   - a failed capstone can be retaken at most 5 times, and the student pays again each time;
//   - the mark is independent, counts in the course average (AssignmentAverage) and a pass is a certificate condition.
// Admin pages never include this file: they use CapstoneStatus.php, which returns status words only.
//
// All times are stored in UTC and shown in EAT (Models\AssignmentModel::local()).

require_once __DIR__ . '/../models/AssignmentModel.php';
require_once __DIR__ . '/../helpers/assignment_files.php';
require_once __DIR__ . '/LinkRules.php';
require_once __DIR__ . '/LinkChecker.php';
require_once __DIR__ . '/CapstoneStatus.php';

use Models\AssignmentModel;

const CAP_PASS = 50;
const CAP_MAX_RETURNS = 2;
const CAP_MAX_RETAKES = 5;
const CAP_PARTS = ['project' => 'Project', 'docs' => 'Documentation', 'present' => 'Presentation', 'time' => 'Time keeping'];
/** Student's name: the approved registration name, else the username (same rule as the Assignments pages). */
const CAP_NAME_SQL = "COALESCE((SELECT r.full_name FROM registrations r WHERE r.user_id = %s AND r.status = 'approved' ORDER BY r.id DESC LIMIT 1), (SELECT u.username FROM users u WHERE u.id = %s))";
const CAP_STATES = ['draft' => 'Draft', 'scheduled' => 'Scheduled', 'open' => 'Open', 'closed' => 'Closed'];

/** What students can send. Per kind: label, 'link' | 'file' | 'either', allowed sites for links. */
const CAP_DELIVERABLES = [
    'project_link' => ['Project link (GitHub, GitLab or Google Drive)', 'link', 'github.com,gitlab.com,drive.google.com,docs.google.com'],
    'report'       => ['Written report (PDF, up to 10 MB)', 'file', ''],
    'video'        => ['Demo video link (YouTube or Loom)', 'link', 'youtube.com,youtu.be,loom.com'],
    'slides'       => ['Slides (PDF or link)', 'either', 'docs.google.com,drive.google.com,canva.com,figma.com'],
];

if (!function_exists('cap_e')) {
    function cap_e($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    function cap_now(): string
    {
        return AssignmentModel::now();
    }

    function cap_local(?string $utc, string $fmt = 'D j M Y, H:i'): string
    {
        return $utc ? AssignmentModel::local($utc, $fmt) : '';
    }

    function cap_installed(PDO $db): bool
    {
        static $ok = null;
        if ($ok === null) {
            try { $db->query('SELECT 1 FROM capstones LIMIT 1'); $ok = true; } catch (Throwable $e) { $ok = false; }
        }
        return $ok;
    }

    function cap_log(PDO $db, ?int $capId, ?int $attemptId, ?int $actor, ?int $studentUid, string $event, array $details = []): void
    {
        try {
            $db->prepare('INSERT INTO capstone_events (capstone_id, attempt_id, actor_user_id, student_user_id, event, details) VALUES (?, ?, ?, ?, ?, ?)')
               ->execute([$capId, $attemptId, $actor, $studentUid, $event, $details ? json_encode($details) : null]);
        } catch (Throwable $e) {
            error_log('[capstone log] ' . $e->getMessage());
        }
    }

    /** Distinction 70+, Credit 60-69, Pass 50-59, Fail below 50. */
    function cap_result_word(float $total): string
    {
        return $total >= 70 ? 'Distinction' : ($total >= 60 ? 'Credit' : ($total >= CAP_PASS ? 'Pass' : 'Fail'));
    }

    /** Run $fn in a transaction unless one is already open. */
    function cap_tx(PDO $db, callable $fn)
    {
        if ($db->inTransaction()) return $fn();
        $db->beginTransaction();
        try {
            $r = $fn();
            $db->commit();
            return $r;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /* ======================================================================= setup */

    function cap_get(PDO $db, int $id): ?array
    {
        $st = $db->prepare('SELECT c.*, co.title AS course_title FROM capstones c LEFT JOIN courses co ON co.id = c.course_id WHERE c.id = ?');
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** A tutor's own capstone only (tutors.id). */
    function cap_get_for_tutor(PDO $db, int $id, int $tutorId): ?array
    {
        $c = cap_get($db, $id);
        return $c && (int) $c['tutor_id'] === $tutorId ? $c : null;
    }

    function cap_deliverables(PDO $db, int $capId): array
    {
        $st = $db->prepare('SELECT * FROM capstone_deliverables WHERE capstone_id = ? ORDER BY sort_order, id');
        $st->execute([$capId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    function cap_milestones(PDO $db, int $capId): array
    {
        $st = $db->prepare('SELECT * FROM capstone_milestones WHERE capstone_id = ? ORDER BY sort_order, due_at, id');
        $st->execute([$capId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Students of the capstone: [user_id => row + full_name, registration_no]. */
    function cap_students(PDO $db, int $capId, bool $withRemoved = false): array
    {
        $st = $db->prepare(
            "SELECT cs.*, " . sprintf(CAP_NAME_SQL, 'cs.student_user_id', 'cs.student_user_id') . " AS full_name,
                    (SELECT r.registration_no FROM registrations r WHERE r.user_id = cs.student_user_id AND r.status = 'approved' ORDER BY r.id DESC LIMIT 1) AS registration_no
             FROM capstone_students cs WHERE cs.capstone_id = ?" . ($withRemoved ? '' : ' AND cs.removed_at IS NULL') . ' ORDER BY cs.team_no, full_name'
        );
        $st->execute([$capId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(int) $r['student_user_id']] = $r;
        return $out;
    }

    /**
     * Check the setup form data without saving. $d is normalised by the page (times already in UTC).
     * @return string[] error messages
     */
    function cap_validate(PDO $db, int $tutorId, ?array $existing, array $d): array
    {
        $am = new AssignmentModel($db);
        $err = [];
        if (trim((string) ($d['title'] ?? '')) === '') $err[] = 'Give the capstone a title.';
        $courses = $am->tutorCourses($tutorId);
        if (!isset($courses[(int) ($d['course_id'] ?? 0)])) $err[] = 'Choose one of your courses.';
        $w = ['project' => (int) $d['w_project'], 'docs' => (int) $d['w_docs'], 'present' => (int) $d['w_present'], 'time' => (int) $d['w_time']];
        foreach ($w as $k => $v) if ($v < 0 || $v > 100) $err[] = CAP_PARTS[$k] . ' must be between 0 and 100.';
        if (array_sum($w) !== 100) $err[] = 'The four parts must add up to exactly 100 (now ' . array_sum($w) . ').';
        $ms = array_sum(array_map(fn($m) => (float) $m['marks'], $d['milestones'] ?? []));
        if (abs($ms - $w['time']) > 0.001) $err[] = 'The milestone marks add up to ' . rtrim(rtrim(number_format($ms, 1), '0'), '.') . ' but Time keeping is ' . $w['time'] . '. They must be equal.';
        foreach ($d['milestones'] ?? [] as $m) if (trim((string) $m['title']) === '' || empty($m['due_at'])) { $err[] = 'Every milestone needs a title and a due date and time.'; break; }
        if (!in_array($d['mode'] ?? '', ['individual', 'team'], true)) $err[] = 'Choose Individual or Team.';
        if (empty($d['deliverables'])) $err[] = 'Switch on at least one thing students must send.';
        if (!in_array($d['visibility'] ?? '', ['hidden', 'now', 'date'], true)) $err[] = 'Choose when students see it.';
        if (($d['visibility'] ?? '') === 'date' && empty($d['opens_at'])) $err[] = 'Choose the date and time it opens.';
        if (in_array($d['visibility'] ?? '', ['now', 'date'], true) && empty($d['due_at'])) $err[] = 'Set the final due date before showing it to students.';
        if (($d['visibility'] ?? '') === 'date' && !empty($d['opens_at']) && !empty($d['due_at']) && $d['opens_at'] >= $d['due_at']) $err[] = 'It must open before it is due.';
        $mine = $am->tutorStudents($tutorId, (int) ($d['course_id'] ?? 0));
        foreach ($d['students'] ?? [] as $s) if (!isset($mine[(int) $s['uid']])) { $err[] = 'One of the chosen students is not yours or is not in this course.'; break; }
        if (($d['mode'] ?? '') === 'team') {
            $teams = [];
            foreach ($d['students'] ?? [] as $s) {
                if (empty($s['team_no'])) { $err[] = 'Put every student into a team.'; $teams = null; break; }
                $teams[(int) $s['team_no']][] = $s;
            }
            if ($teams) foreach ($teams as $no => $members) if (count($members) > max(1, min(3, (int) ($d['team_size'] ?? 3)))) { $err[] = 'Team ' . $no . ' has more than ' . (int) ($d['team_size'] ?? 3) . ' students.'; break; }
        }
        if ($existing) {
            $locked = cap_has_attempts($db, (int) $existing['id']);
            if ($locked && ($existing['mode'] ?? '') !== ($d['mode'] ?? '')) $err[] = 'You cannot change Individual or Team after students started.';
            if (cap_has_marks($db, (int) $existing['id'])) {
                foreach (['project', 'docs', 'present', 'time'] as $k) if ((int) $existing['w_' . $k] !== $w[$k]) { $err[] = 'The marks parts cannot change after marking has started.'; break; }
            }
        }
        return array_values(array_unique($err));
    }

    function cap_has_attempts(PDO $db, int $capId): bool
    {
        $st = $db->prepare('SELECT 1 FROM capstone_attempts WHERE capstone_id = ? LIMIT 1');
        $st->execute([$capId]);
        return (bool) $st->fetchColumn();
    }

    function cap_has_marks(PDO $db, int $capId): bool
    {
        $st = $db->prepare('SELECT 1 FROM capstone_marks m JOIN capstone_attempts a ON a.id = m.attempt_id WHERE a.capstone_id = ? LIMIT 1');
        $st->execute([$capId]);
        return (bool) $st->fetchColumn();
    }

    /**
     * Create or update a capstone. Validation is done first (cap_validate). visibility: hidden | now | date.
     * @return array{ok:bool, id?:int, errors?:string[]}
     */
    function cap_save(PDO $db, int $tutorId, ?int $id, array $d, int $actor): array
    {
        $existing = $id ? cap_get_for_tutor($db, $id, $tutorId) : null;
        if ($id && !$existing) return ['ok' => false, 'errors' => ['Capstone not found.']];
        $errors = cap_validate($db, $tutorId, $existing, $d);
        if ($errors) return ['ok' => false, 'errors' => $errors];

        $now = cap_now();
        return cap_tx($db, function () use ($db, $tutorId, $id, $d, $actor, $existing, $now) {
            $status = $existing['status'] ?? 'draft';
            $opensAt = $existing['opens_at'] ?? null;
            $manual = $existing['opened_manually_at'] ?? null;
            // Visibility only moves a capstone that is not open or closed yet (those use Open now / Close).
            if (!$existing || in_array($status, ['draft', 'scheduled'], true)) {
                if ($d['visibility'] === 'hidden') { $status = 'draft'; $opensAt = null; }
                elseif ($d['visibility'] === 'date') { $status = $d['opens_at'] <= $now ? 'open' : 'scheduled'; $opensAt = $d['opens_at']; }
                else { $status = 'open'; $manual = $now; $opensAt = null; }
            } elseif (($existing['status'] ?? '') === 'scheduled' || ($d['visibility'] ?? '') === 'date') {
                $opensAt = $d['opens_at'] ?? $opensAt;
            }
            $cols = [$d['course_id'], $d['intake_id'] ?: null, mb_substr(trim($d['title']), 0, 200), $d['brief'], $d['instructions'], $d['requirements'],
                     $d['example_link'] ?: null, $d['mode'], max(1, min(3, (int) ($d['team_size'] ?? 3))), $d['due_at'] ?: null,
                     (int) $d['w_project'], (int) $d['w_docs'], (int) $d['w_present'], (int) $d['w_time'], max(0.1, (float) ($d['weight'] ?? 5))];
            if ($existing) {
                $db->prepare('UPDATE capstones SET course_id=?, intake_id=?, title=?, brief=?, instructions=?, requirements=?, example_link=?, mode=?, team_size=?, due_at=?,
                              w_project=?, w_docs=?, w_present=?, w_time=?, weight=?, status=?, opens_at=?, opened_manually_at=? WHERE id=?')
                   ->execute(array_merge($cols, [$status, $opensAt, $manual, $id]));
            } else {
                $db->prepare('INSERT INTO capstones (tutor_id, course_id, intake_id, title, brief, instructions, requirements, example_link, mode, team_size, due_at,
                              w_project, w_docs, w_present, w_time, weight, status, opens_at, opened_manually_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                   ->execute(array_merge([$tutorId], $cols, [$status, $opensAt, $manual]));
                $id = (int) $db->lastInsertId();
            }
            // Deliverables, milestones: replace the lists (students have not started, or the items keep their ids by label)
            cap_replace_deliverables($db, $id, $d['deliverables']);
            cap_replace_milestones($db, $id, $d['milestones'] ?? []);
            cap_sync_students($db, $id, $d['students'] ?? [], $existing);
            if ($d['visibility'] === 'hidden' && !$existing) { /* draft: nothing visible, nothing sent */ }
            cap_log($db, $id, null, $actor, null, $existing ? 'saved' : 'created', ['status' => $status]);
            if (!$existing || $status !== ($existing['status'] ?? '')) cap_log($db, $id, null, $actor, null, 'status_' . $status, ['opens_at' => $opensAt]);
            return ['ok' => true, 'id' => $id, 'became_open' => $status === 'open' && ($existing['status'] ?? '') !== 'open'];
        });
    }

    function cap_replace_deliverables(PDO $db, int $capId, array $items): void
    {
        $old = [];
        foreach (cap_deliverables($db, $capId) as $r) $old[$r['kind'] . '|' . $r['label']] = (int) $r['id'];
        $keep = [];
        $ins = $db->prepare('INSERT INTO capstone_deliverables (capstone_id, kind, label, item_type, is_required, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
        $upd = $db->prepare('UPDATE capstone_deliverables SET item_type = ?, is_required = ?, sort_order = ? WHERE id = ?');
        foreach (array_values($items) as $i => $it) {
            $key = $it['kind'] . '|' . $it['label'];
            if (isset($old[$key])) { $upd->execute([$it['item_type'], (int) $it['is_required'], $i, $old[$key]]); $keep[] = $old[$key]; }
            else { $ins->execute([$capId, $it['kind'], $it['label'], $it['item_type'], (int) $it['is_required'], $i]); $keep[] = (int) $db->lastInsertId(); }
        }
        foreach ($old as $oid) if (!in_array($oid, $keep, true)) {
            // Students may already have sent this item: keep the row if used, else remove it.
            $used = $db->prepare('SELECT 1 FROM capstone_submission_items WHERE deliverable_id = ? LIMIT 1');
            $used->execute([$oid]);
            if (!$used->fetchColumn()) $db->prepare('DELETE FROM capstone_deliverables WHERE id = ?')->execute([$oid]);
        }
    }

    function cap_replace_milestones(PDO $db, int $capId, array $items): void
    {
        $existing = array_column(cap_milestones($db, $capId), null, 'id');
        $keep = [];
        $ins = $db->prepare('INSERT INTO capstone_milestones (capstone_id, title, kind, due_at, marks, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
        $upd = $db->prepare('UPDATE capstone_milestones SET title = ?, kind = ?, due_at = ?, marks = ?, sort_order = ? WHERE id = ? AND capstone_id = ?');
        foreach (array_values($items) as $i => $m) {
            $kind = in_array($m['kind'] ?? '', ['work', 'final', 'presentation'], true) ? $m['kind'] : 'work';
            if (!empty($m['id']) && isset($existing[(int) $m['id']])) {
                $upd->execute([mb_substr(trim($m['title']), 0, 160), $kind, $m['due_at'], (float) $m['marks'], $i, (int) $m['id'], $capId]);
                $keep[] = (int) $m['id'];
            } else {
                $ins->execute([$capId, mb_substr(trim($m['title']), 0, 160), $kind, $m['due_at'], (float) $m['marks'], $i]);
                $keep[] = (int) $db->lastInsertId();
            }
        }
        foreach ($existing as $oid => $_) if (!in_array((int) $oid, $keep, true)) {
            $used = $db->prepare('SELECT 1 FROM capstone_milestone_submissions WHERE milestone_id = ? LIMIT 1');
            $used->execute([$oid]);
            if (!$used->fetchColumn()) $db->prepare('DELETE FROM capstone_milestones WHERE id = ?')->execute([$oid]);
        }
    }

    /** $students: [[uid, team_no|null, own_due_at|null], ...]. Students who already started are never removed. */
    function cap_sync_students(PDO $db, int $capId, array $students, ?array $existing): void
    {
        $have = cap_students($db, $capId, true);
        $want = [];
        foreach ($students as $s) $want[(int) $s['uid']] = $s;
        foreach ($want as $uid => $s) {
            if (isset($have[$uid])) {
                $db->prepare('UPDATE capstone_students SET team_no = ?, own_due_at = ?, removed_at = NULL WHERE capstone_id = ? AND student_user_id = ?')
                   ->execute([$s['team_no'] ?: null, $s['own_due_at'] ?: null, $capId, $uid]);
            } else {
                $db->prepare('INSERT INTO capstone_students (capstone_id, student_user_id, team_no, own_due_at) VALUES (?, ?, ?, ?)')
                   ->execute([$capId, $uid, $s['team_no'] ?: null, $s['own_due_at'] ?: null]);
            }
        }
        foreach ($have as $uid => $row) {
            if (isset($want[$uid]) || $row['removed_at']) continue;
            $started = $db->prepare('SELECT 1 FROM capstone_attempts WHERE capstone_id = ? AND student_user_id = ? LIMIT 1');
            $started->execute([$capId, $uid]);
            if ($started->fetchColumn()) continue;   // already has work: stays
            $db->prepare('UPDATE capstone_students SET removed_at = ? WHERE capstone_id = ? AND student_user_id = ?')->execute([cap_now(), $capId, $uid]);
        }
        $cap = cap_get($db, $capId);
        if ($cap && $cap['status'] === 'open') cap_make_attempts($db, $capId);
    }

    /* ======================================================================= open, close, schedule */

    /** Make the first attempt row for every student of an open capstone (teams share one owner attempt). */
    function cap_make_attempts(PDO $db, int $capId): void
    {
        $cap = cap_get($db, $capId);
        if (!$cap) return;
        $students = cap_students($db, $capId);
        $has = $db->prepare('SELECT id FROM capstone_attempts WHERE capstone_id = ? AND student_user_id = ? AND attempt_no = 1');
        $ins = $db->prepare("INSERT INTO capstone_attempts (capstone_id, student_user_id, attempt_no, status) VALUES (?, ?, 1, 'not_started')");
        $ids = [];
        foreach ($students as $uid => $s) {
            $has->execute([$capId, $uid]);
            $aid = (int) $has->fetchColumn();
            if (!$aid) { $ins->execute([$capId, $uid]); $aid = (int) $db->lastInsertId(); }
            $ids[$uid] = $aid;
        }
        if ($cap['mode'] === 'team') cap_link_teams($db, $capId, 1);
    }

    /** In a team capstone every member's attempt points at the owner's (lowest user id of the team). */
    function cap_link_teams(PDO $db, int $capId, int $attemptNo): void
    {
        $byTeam = [];
        foreach (cap_students($db, $capId) as $uid => $s) if ($s['team_no']) $byTeam[(int) $s['team_no']][] = $uid;
        foreach ($byTeam as $uids) {
            sort($uids);
            $in = implode(',', array_fill(0, count($uids), '?'));
            $st = $db->prepare("SELECT id, student_user_id FROM capstone_attempts WHERE capstone_id = ? AND attempt_no = ? AND student_user_id IN ($in)");
            $st->execute(array_merge([$capId, $attemptNo], $uids));
            $rows = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $rows[(int) $r['student_user_id']] = (int) $r['id'];
            if (count($rows) < 1) continue;
            $owner = $rows[$uids[0]] ?? reset($rows);
            foreach ($rows as $uid => $aid) {
                $db->prepare('UPDATE capstone_attempts SET shared_attempt_id = ? WHERE id = ?')->execute([$aid === $owner ? null : $owner, $aid]);
            }
        }
    }

    /** Open now, by hand. Allowed from draft, scheduled or closed. Returns the number of students told. */
    function cap_open_now(PDO $db, int $capId, int $tutorId, int $actor): array
    {
        $cap = cap_get_for_tutor($db, $capId, $tutorId);
        if (!$cap) return ['ok' => false, 'error' => 'Capstone not found.'];
        if ($cap['status'] === 'open') return ['ok' => true, 'told' => 0];
        if (!$cap['due_at']) return ['ok' => false, 'error' => 'Set the final due date first (Edit), then open it.'];
        if (!cap_students($db, $capId)) return ['ok' => false, 'error' => 'Choose the students first (Edit).'];
        $now = cap_now();
        $db->prepare("UPDATE capstones SET status = 'open', opened_manually_at = ?, closed_at = NULL WHERE id = ?")->execute([$now, $capId]);
        cap_make_attempts($db, $capId);
        cap_log($db, $capId, null, $actor, null, 'opened_by_hand');
        return ['ok' => true, 'told' => cap_notify_open($db, $capId)];
    }

    function cap_close(PDO $db, int $capId, int $tutorId, int $actor): array
    {
        $cap = cap_get_for_tutor($db, $capId, $tutorId);
        if (!$cap) return ['ok' => false, 'error' => 'Capstone not found.'];
        $db->prepare("UPDATE capstones SET status = 'closed', closed_at = ? WHERE id = ?")->execute([cap_now(), $capId]);
        cap_log($db, $capId, null, $actor, null, 'closed');
        return ['ok' => true];
    }

    /** Tell the students once that it is open. Claimed first so a second cron run or double click never sends twice. */
    function cap_notify_open(PDO $db, int $capId): int
    {
        $claim = $db->prepare('UPDATE capstones SET notified_open_at = ? WHERE id = ? AND notified_open_at IS NULL');
        $claim->execute([cap_now(), $capId]);
        if ($claim->rowCount() < 1) return 0;
        require_once __DIR__ . '/CapstoneNotifier.php';
        return (new CapstoneNotifier($db))->opened(cap_get($db, $capId));
    }

    /**
     * Scheduled work: open capstones whose time has come, "opens soon" 3 days before, milestone reminders 2 and 1 day before,
     * and tell students when held marks are released. Safe to run often; every step is claimed so nothing is sent twice.
     * @return array<string,int>
     */
    function cap_tick(PDO $db): array
    {
        $out = ['opened' => 0, 'soon' => 0, 'reminders' => 0, 'released' => 0, 'transcripts' => 0, 'certificates' => 0, 'cert_reminders' => 0];
        // Certificate applications: opens the Apply button when the course time passes, and reminds the tutor of a request that waited 3 days.
        try {
            require_once __DIR__ . '/CertificateApplication.php';
            $ct = certapp_tick($db);
            $out['certificates'] = $ct['opened'];
            $out['cert_reminders'] = $ct['reminded'];
        } catch (Throwable $e) {
            // migration 092 not run yet
        }
        if (!cap_installed($db)) return $out;
        require_once __DIR__ . '/CapstoneNotifier.php';
        $n = new CapstoneNotifier($db);
        $now = cap_now();
        $due = $db->prepare("SELECT id FROM capstones WHERE status = 'scheduled' AND opens_at IS NOT NULL AND opens_at <= ?");
        $due->execute([$now]);
        foreach ($due->fetchAll(PDO::FETCH_COLUMN) as $cid) {
            $up = $db->prepare("UPDATE capstones SET status = 'open' WHERE id = ? AND status = 'scheduled'");
            $up->execute([(int) $cid]);
            if ($up->rowCount() < 1) continue;
            cap_make_attempts($db, (int) $cid);
            cap_log($db, (int) $cid, null, null, null, 'opened_by_date');
            $out['opened'] += cap_notify_open($db, (int) $cid) > 0 ? 1 : 0;
        }
        $soon = $db->prepare("SELECT id FROM capstones WHERE status = 'scheduled' AND opens_at IS NOT NULL AND opens_at > ? AND opens_at <= ? AND notified_soon_at IS NULL");
        $soon->execute([$now, gmdate('Y-m-d H:i:s', time() + 3 * 86400)]);
        foreach ($soon->fetchAll(PDO::FETCH_COLUMN) as $cid) {
            $claim = $db->prepare('UPDATE capstones SET notified_soon_at = ? WHERE id = ? AND notified_soon_at IS NULL');
            $claim->execute([$now, (int) $cid]);
            if ($claim->rowCount() > 0) { $n->opensSoon(cap_get($db, (int) $cid)); $out['soon']++; }
        }
        // milestone reminders: 2 days and 1 day before, only for students who have not sent that milestone
        foreach ([[2, 'reminded_2d_at'], [1, 'reminded_1d_at']] as [$days, $col]) {
            $st = $db->prepare("SELECT m.id, m.capstone_id FROM capstone_milestones m JOIN capstones c ON c.id = m.capstone_id
                                WHERE c.status = 'open' AND m.$col IS NULL AND m.due_at > ? AND m.due_at <= ?");
            $st->execute([$now, gmdate('Y-m-d H:i:s', time() + $days * 86400)]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $m) {
                $claim = $db->prepare("UPDATE capstone_milestones SET $col = ? WHERE id = ? AND $col IS NULL");
                $claim->execute([$now, (int) $m['id']]);
                if ($claim->rowCount() > 0) $out['reminders'] += $n->milestoneReminder((int) $m['id'], $days);
            }
        }
        // held marks reaching their release time
        $rel = $db->prepare('SELECT id FROM capstone_marks WHERE released_notified_at IS NULL AND held_until IS NOT NULL AND held_until <= ?');
        $rel->execute([$now]);
        foreach ($rel->fetchAll(PDO::FETCH_COLUMN) as $mid) {
            $claim = $db->prepare('UPDATE capstone_marks SET released_notified_at = ? WHERE id = ? AND released_notified_at IS NULL');
            $claim->execute([$now, (int) $mid]);
            if ($claim->rowCount() > 0) { $n->markReleased((int) $mid); $out['released']++; }
        }
        // transcript + letter: re-check everyone who finished (a fee payment or a corrected mark can change the result)
        $rel = cap_release_sweep($db);
        $out['transcripts'] = $rel['released'];
        return $out;
    }

    /** Page-load fallback for the cron job: runs the tick at most once a minute. */
    function cap_tick_lazy(PDO $db): void
    {
        if (!cap_installed($db)) return;
        $f = dirname(__DIR__) . '/storage/cache/capstone_tick.txt';
        if (is_file($f) && time() - (int) @filemtime($f) < 60) return;
        @mkdir(dirname($f), 0775, true);
        @touch($f);
        try { cap_tick($db); } catch (Throwable $e) { error_log('[capstone tick] ' . $e->getMessage()); }
    }

    /** Copy a closed capstone to the next intake: everything except dates, students, files students sent and marks. */
    function cap_copy(PDO $db, int $capId, int $tutorId, int $actor, ?int $courseId = null, ?int $intakeId = null): ?int
    {
        $c = cap_get_for_tutor($db, $capId, $tutorId);
        if (!$c) return null;
        return cap_tx($db, function () use ($db, $c, $tutorId, $actor, $courseId, $intakeId) {
            $db->prepare("INSERT INTO capstones (tutor_id, course_id, intake_id, title, brief, instructions, requirements, example_link, example_file, example_file_name, mode, team_size,
                          status, w_project, w_docs, w_present, w_time, weight, copied_from) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?, ?)")
               ->execute([$tutorId, $courseId ?: $c['course_id'], $intakeId, $c['title'] . ' (copy)', $c['brief'], $c['instructions'], $c['requirements'], $c['example_link'],
                          $c['example_file'], $c['example_file_name'], $c['mode'], $c['team_size'], $c['w_project'], $c['w_docs'], $c['w_present'], $c['w_time'], $c['weight'], $c['id']]);
            $new = (int) $db->lastInsertId();
            $di = $db->prepare('INSERT INTO capstone_deliverables (capstone_id, kind, label, item_type, is_required, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
            foreach (cap_deliverables($db, (int) $c['id']) as $d) $di->execute([$new, $d['kind'], $d['label'], $d['item_type'], $d['is_required'], $d['sort_order']]);
            // milestones keep their titles and marks; dates are left for the tutor (set to a far placeholder = tomorrow, edit before opening)
            $mi = $db->prepare('INSERT INTO capstone_milestones (capstone_id, title, kind, due_at, marks, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
            foreach (cap_milestones($db, (int) $c['id']) as $m) $mi->execute([$new, $m['title'], $m['kind'], gmdate('Y-m-d H:i:s', time() + 86400), $m['marks'], $m['sort_order']]);
            cap_log($db, $new, null, $actor, null, 'copied', ['from' => (int) $c['id']]);
            return $new;
        });
    }

    /* ======================================================================= lists */

    /** The tutor's capstones, newest first, with how many students sent work. */
    function cap_list_for_tutor(PDO $db, int $tutorId, string $filter = 'all'): array
    {
        $st = $db->prepare(
            "SELECT c.*, co.title AS course_title, i.name AS intake_name,
                    (SELECT COUNT(*) FROM capstone_students s WHERE s.capstone_id = c.id AND s.removed_at IS NULL) AS total,
                    (SELECT COUNT(*) FROM capstone_attempts a WHERE a.capstone_id = c.id AND a.status IN ('submitted','returned','marked')
                       AND a.attempt_no = (SELECT MAX(a2.attempt_no) FROM capstone_attempts a2 WHERE a2.capstone_id = a.capstone_id AND a2.student_user_id = a.student_user_id)) AS sent
             FROM capstones c LEFT JOIN courses co ON co.id = c.course_id LEFT JOIN intakes i ON i.id = c.intake_id
             WHERE c.tutor_id = ? ORDER BY c.id DESC"
        );
        $st->execute([$tutorId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $map = ['open' => 'open', 'scheduled' => 'scheduled', 'drafts' => 'draft', 'closed' => 'closed'];
        return $filter === 'all' || !isset($map[$filter]) ? $rows : array_values(array_filter($rows, fn($r) => $r['status'] === $map[$filter]));
    }

    /* ======================================================================= students: visibility and state */

    /** Capstones a student may see: only scheduled/open/closed ones given to them. A draft never appears. */
    function cap_student_capstones(PDO $db, int $uid): array
    {
        if (!cap_installed($db)) return [];
        $st = $db->prepare(
            "SELECT c.id, c.title, c.status, c.opens_at, c.due_at, c.course_id, co.title AS course_title, cs.opened_early_at, cs.own_due_at, cs.team_no
             FROM capstone_students cs JOIN capstones c ON c.id = cs.capstone_id LEFT JOIN courses co ON co.id = c.course_id
             WHERE cs.student_user_id = ? AND cs.removed_at IS NULL AND c.status IN ('scheduled','open','closed') ORDER BY c.id DESC"
        );
        $st->execute([$uid]);
        $rows = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            // Before it opens a student learns nothing but the date: no title, no due date.
            if (!cap_is_open_for($r) && $r['status'] !== 'closed') { $r['title'] = null; $r['due_at'] = null; }
            $rows[] = $r;
        }
        return $rows;
    }

    function cap_student_has_any(PDO $db, int $uid): bool
    {
        return (bool) cap_student_capstones($db, $uid);
    }

    /** Whether the student can see the capstone content now (open, or opened early for them). */
    function cap_is_open_for(array $capRow): bool
    {
        return $capRow['status'] === 'open' || ($capRow['status'] === 'scheduled' && !empty($capRow['opened_early_at']));
    }

    /** A student's view of one capstone: the capstone row joined to their link. Null when they may not see it at all. */
    function cap_for_student(PDO $db, int $capId, int $uid): ?array
    {
        $st = $db->prepare("SELECT c.*, co.title AS course_title, cs.opened_early_at, cs.own_due_at, cs.team_no
                            FROM capstone_students cs JOIN capstones c ON c.id = cs.capstone_id LEFT JOIN courses co ON co.id = c.course_id
                            WHERE cs.capstone_id = ? AND cs.student_user_id = ? AND cs.removed_at IS NULL AND c.status IN ('scheduled','open','closed')");
        $st->execute([$capId, $uid]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** The student's newest attempt (the one that counts). */
    function cap_latest_attempt(PDO $db, int $capId, int $uid): ?array
    {
        $st = $db->prepare('SELECT * FROM capstone_attempts WHERE capstone_id = ? AND student_user_id = ? ORDER BY attempt_no DESC LIMIT 1');
        $st->execute([$capId, $uid]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    function cap_attempt(PDO $db, int $id): ?array
    {
        $st = $db->prepare('SELECT * FROM capstone_attempts WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** The attempt that holds the shared work (the owner in a team; the attempt itself otherwise). */
    function cap_owner(PDO $db, array $attempt): array
    {
        return !empty($attempt['shared_attempt_id']) ? (cap_attempt($db, (int) $attempt['shared_attempt_id']) ?: $attempt) : $attempt;
    }

    /** Every attempt row of the team (or just the one) that shares this owner attempt. */
    function cap_group_attempts(PDO $db, array $owner): array
    {
        $st = $db->prepare('SELECT * FROM capstone_attempts WHERE id = ? OR shared_attempt_id = ? ORDER BY id');
        $st->execute([(int) $owner['id'], (int) $owner['id']]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** The student's attempt, created if the capstone is open for them and none exists yet. */
    function cap_ensure_attempt(PDO $db, array $capRow, int $uid): ?array
    {
        $a = cap_latest_attempt($db, (int) $capRow['id'], $uid);
        if ($a || !cap_is_open_for($capRow)) return $a;
        $db->prepare("INSERT INTO capstone_attempts (capstone_id, student_user_id, attempt_no, status) VALUES (?, ?, 1, 'not_started')")->execute([(int) $capRow['id'], $uid]);
        if (($capRow['mode'] ?? '') === 'team') cap_link_teams($db, (int) $capRow['id'], 1);
        return cap_latest_attempt($db, (int) $capRow['id'], $uid);
    }

    /** Due date for this student (their own date, or the capstone's). */
    function cap_due_for(array $capRow): ?string
    {
        return $capRow['own_due_at'] ?: $capRow['due_at'];
    }

    /** True when the student may add or change work right now. */
    function cap_can_edit(array $capRow, ?array $attempt): bool
    {
        if (!$attempt || !cap_is_open_for($capRow) || $capRow['status'] === 'closed') return false;
        return in_array($attempt['status'], ['not_started', 'in_progress', 'returned'], true);
    }

    /**
     * Words for the student and the tutor tracker: not_started, in_progress, behind, to_mark, returned, final_try, marked, awaiting_payment.
     * 'final_try' means returned twice, so the next send is final.
     */
    function cap_state(array $capRow, ?array $attempt, ?int $now = null): string
    {
        $now = $now ?? time();
        if (!$attempt) return 'not_started';
        $s = $attempt['status'];
        if ($s === 'awaiting_payment') return 'awaiting_payment';
        if ($s === 'marked') return 'marked';
        if ($s === 'submitted') return 'to_mark';
        if ($s === 'returned') return (int) $attempt['returns_used'] >= CAP_MAX_RETURNS ? 'final_try' : 'returned';
        $due = cap_due_for($capRow);
        $behind = $due && strtotime($due . ' UTC') < $now;
        if ($s === 'in_progress') return $behind ? 'behind' : 'in_progress';
        return $behind ? 'behind' : 'not_started';
    }

    /* ======================================================================= example project */

    function cap_example_acked(PDO $db, int $capId, int $uid): bool
    {
        $st = $db->prepare('SELECT 1 FROM capstone_example_ack WHERE capstone_id = ? AND student_user_id = ?');
        $st->execute([$capId, $uid]);
        return (bool) $st->fetchColumn();
    }

    function cap_ack_example(PDO $db, int $capId, int $uid): void
    {
        $db->prepare('INSERT IGNORE INTO capstone_example_ack (capstone_id, student_user_id, acked_at) VALUES (?, ?, ?)')->execute([$capId, $uid, cap_now()]);
        cap_log($db, $capId, null, $uid, $uid, 'example_acknowledged');
    }

    /** Same address once the usual noise is removed (case, www, trailing slash, .git, tracking query). */
    function cap_norm_url(?string $u): string
    {
        $u = strtolower(trim((string) $u));
        $u = preg_replace('#^https?://(www\.)?#', '', $u);
        $u = preg_replace('#[?\#].*$#', '', $u);
        return rtrim(preg_replace('#\.git$#', '', rtrim($u, '/')), '/');
    }

    /* ======================================================================= the student's work */

    /** The draft (or latest) submission of the owner attempt: the row being edited. */
    function cap_work(PDO $db, array $owner, bool $createDraft = false): ?array
    {
        $st = $db->prepare("SELECT * FROM capstone_submissions WHERE attempt_id = ? AND status = 'draft' ORDER BY version DESC LIMIT 1");
        $st->execute([(int) $owner['id']]);
        $s = $st->fetch(PDO::FETCH_ASSOC);
        if ($s) return $s;
        if (!$createDraft) {
            $st = $db->prepare('SELECT * FROM capstone_submissions WHERE attempt_id = ? ORDER BY version DESC LIMIT 1');
            $st->execute([(int) $owner['id']]);
            return $st->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        $v = (int) $db->query('SELECT COALESCE(MAX(version), 0) + 1 FROM capstone_submissions WHERE attempt_id = ' . (int) $owner['id'])->fetchColumn();
        $db->prepare("INSERT INTO capstone_submissions (attempt_id, version, status, draft_saved_at) VALUES (?, ?, 'draft', ?)")->execute([(int) $owner['id'], $v, cap_now()]);
        // a new version starts from what was sent before (so a return means "improve", not "start again")
        if ($v > 1) {
            $prev = $db->prepare("SELECT id FROM capstone_submissions WHERE attempt_id = ? AND status = 'submitted' ORDER BY version DESC LIMIT 1");
            $prev->execute([(int) $owner['id']]);
            if ($pid = (int) $prev->fetchColumn()) {
                $new = (int) $db->lastInsertId();
                $db->prepare('INSERT INTO capstone_submission_items (submission_id, deliverable_id, label, item_type, url, file_path, file_name, link_status, link_message, same_as_example)
                              SELECT ?, deliverable_id, label, item_type, url, file_path, file_name, link_status, link_message, same_as_example FROM capstone_submission_items WHERE submission_id = ?')
                   ->execute([$new, $pid]);
            }
        }
        return cap_work($db, $owner, false);
    }

    function cap_items(PDO $db, int $submissionId): array
    {
        $st = $db->prepare('SELECT * FROM capstone_submission_items WHERE submission_id = ? ORDER BY id');
        $st->execute([$submissionId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    function cap_item(PDO $db, int $id): ?array
    {
        $st = $db->prepare('SELECT * FROM capstone_submission_items WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Save a draft. $links: deliverable_id => url. $files: deliverable_id => an uploaded file array. $own: tick for this student.
     * Every link is checked with the existing LinkChecker. Returns the saved items with their check results.
     * @return array{ok:bool, errors:string[], items?:array, version?:int}
     */
    function cap_save_work(PDO $db, array $capRow, array $attempt, int $uid, array $links, array $files, bool $own, string $aiNote, ?array $tutorUser = null): array
    {
        if (!cap_can_edit($capRow, $attempt)) return ['ok' => false, 'errors' => ['This capstone cannot be changed now.']];
        $owner = cap_owner($db, $attempt);
        $errors = [];
        $dels = array_column(cap_deliverables($db, (int) $capRow['id']), null, 'id');
        return cap_tx($db, function () use ($db, $capRow, $owner, $uid, $links, $files, $own, $aiNote, $dels, &$errors, $tutorUser) {
            $work = cap_work($db, $owner, true);
            $itemsNow = [];
            foreach (cap_items($db, (int) $work['id']) as $it) if ($it['deliverable_id']) $itemsNow[(int) $it['deliverable_id']] = $it;
            $checker = null;
            foreach ($dels as $did => $d) {
                $type = $d['item_type'];
                $kindDef = CAP_DELIVERABLES[$d['kind']] ?? [null, 'link', ''];
                $sites = $kindDef[2] ?: implode(',', array_keys(ASG_LINK_SITES));
                $url = array_key_exists($did, $links) ? trim((string) $links[$did]) : null;
                $file = $files[$did] ?? null;
                if ($url !== null && $url !== '' && $type !== 'file') {
                    $clean = asg_clean_link($url, true);
                    if (!$clean) { $errors[] = $d['label'] . ': use a full link that starts with https://'; continue; }
                    if (!asg_link_site_ok($clean, $sites)) { $errors[] = $d['label'] . ': this site is not allowed. Use ' . str_replace(',', ', ', $sites) . '.'; continue; }
                    $checker ??= new LinkChecker($db);
                    $r = $checker->check($clean, $sites, $uid, null, $tutorUser['github_username'] ?? null);
                    $same = $capRow['example_link'] && cap_norm_url($clean) === cap_norm_url($capRow['example_link']) ? 1 : 0;
                    cap_put_item($db, (int) $work['id'], $did, $d, ['url' => $clean, 'file_path' => null, 'file_name' => null, 'link_status' => $r['status'], 'link_message' => mb_substr($r['title'] . ': ' . $r['message'], 0, 300), 'same' => $same]);
                } elseif ($url === '' && isset($itemsNow[$did]) && $itemsNow[$did]['item_type'] === 'link') {
                    $db->prepare('DELETE FROM capstone_submission_items WHERE id = ?')->execute([(int) $itemsNow[$did]['id']]);
                }
                if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE && $type !== 'link') {
                    $allowed = $d['kind'] === 'slides' ? ['pdf', 'pptx'] : ['pdf'];
                    $res = asg_store_document($file, 'capstone/' . (int) $capRow['id'], $allowed, ASG_FILE_MAX);
                    if (!$res['ok']) { $errors[] = $d['label'] . ': ' . $res['error']; continue; }
                    $same = 0;
                    if ($capRow['example_file'] && ($ea = asg_file_abs((string) $capRow['example_file'])) && ($na = asg_file_abs($res['path']))) $same = hash_file('sha256', $ea) === hash_file('sha256', $na) ? 1 : 0;
                    cap_put_item($db, (int) $work['id'], $did, $d, ['url' => null, 'file_path' => $res['path'], 'file_name' => mb_substr(basename((string) $file['name']), 0, 200), 'link_status' => null, 'link_message' => null, 'same' => $same]);
                }
            }
            $db->prepare('UPDATE capstone_submissions SET draft_saved_at = ? WHERE id = ?')->execute([cap_now(), (int) $work['id']]);
            if ($owner['status'] === 'not_started') {
                foreach (cap_group_attempts($db, $owner) as $g) $db->prepare("UPDATE capstone_attempts SET status = 'in_progress' WHERE id = ? AND status = 'not_started'")->execute([(int) $g['id']]);
            }
            if ($own) {
                $db->prepare('INSERT IGNORE INTO capstone_own_work (attempt_id, student_user_id, version, ticked_at, ai_note) VALUES (?, ?, ?, ?, ?)')
                   ->execute([(int) $owner['id'], $uid, (int) $work['version'], cap_now(), $aiNote !== '' ? mb_substr($aiNote, 0, 500) : null]);
            } else {
                $db->prepare('DELETE FROM capstone_own_work WHERE attempt_id = ? AND student_user_id = ? AND version = ?')->execute([(int) $owner['id'], $uid, (int) $work['version']]);
            }
            return ['ok' => !$errors, 'errors' => $errors, 'items' => cap_items($db, (int) $work['id']), 'version' => (int) $work['version']];
        });
    }

    function cap_put_item(PDO $db, int $submissionId, int $deliverableId, array $d, array $v): void
    {
        $st = $db->prepare('SELECT id FROM capstone_submission_items WHERE submission_id = ? AND deliverable_id = ?');
        $st->execute([$submissionId, $deliverableId]);
        if ($id = (int) $st->fetchColumn()) {
            $db->prepare('UPDATE capstone_submission_items SET item_type = ?, url = ?, file_path = ?, file_name = ?, link_status = ?, link_message = ?, same_as_example = ? WHERE id = ?')
               ->execute([$v['file_path'] ? 'file' : 'link', $v['url'], $v['file_path'], $v['file_name'], $v['link_status'], $v['link_message'], $v['same'], $id]);
        } else {
            $db->prepare('INSERT INTO capstone_submission_items (submission_id, deliverable_id, label, item_type, url, file_path, file_name, link_status, link_message, same_as_example) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
               ->execute([$submissionId, $deliverableId, $d['label'], $v['file_path'] ? 'file' : 'link', $v['url'], $v['file_path'], $v['file_name'], $v['link_status'], $v['link_message'], $v['same']]);
        }
    }

    /** Members of this attempt's team who have not ticked "This is my own work" for the version. */
    function cap_missing_own_work(PDO $db, array $owner, int $version): array
    {
        $missing = [];
        foreach (cap_group_attempts($db, $owner) as $g) {
            $st = $db->prepare('SELECT 1 FROM capstone_own_work WHERE attempt_id = ? AND student_user_id = ? AND version = ?');
            $st->execute([(int) $owner['id'], (int) $g['student_user_id'], $version]);
            if (!$st->fetchColumn()) $missing[] = (int) $g['student_user_id'];
        }
        return $missing;
    }

    /** What is still needed before Submit: list of plain sentences. Empty = ready. */
    function cap_submit_problems(PDO $db, array $capRow, array $owner): array
    {
        $p = [];
        $work = cap_work($db, $owner, false);
        $items = $work && $work['status'] === 'draft' ? cap_items($db, (int) $work['id']) : [];
        $have = [];
        foreach ($items as $it) if ($it['deliverable_id']) $have[(int) $it['deliverable_id']] = true;
        foreach (cap_deliverables($db, (int) $capRow['id']) as $d) if ($d['is_required'] && empty($have[(int) $d['id']])) $p[] = 'Add: ' . $d['label'];
        if (!$work || $work['status'] !== 'draft') return $p ?: ['Add your work first.'];
        if ($missing = cap_missing_own_work($db, $owner, (int) $work['version'])) {
            $p[] = count($missing) === 1 && count(cap_group_attempts($db, $owner)) === 1 ? 'Tick "This is my own work".' : 'Every team member must tick "This is my own work".';
        }
        return $p;
    }

    /**
     * Submit the capstone. Done in one atomic update, so two tabs or a double click can only submit once.
     * @return array{ok:bool, error?:string, final?:bool}
     */
    function cap_submit(PDO $db, array $capRow, array $attempt, int $uid, bool $confirmedRules): array
    {
        if (!cap_can_edit($capRow, $attempt)) return ['ok' => false, 'error' => 'This capstone cannot be sent now.'];
        $owner = cap_owner($db, $attempt);
        if ((int) $owner['attempt_no'] > 0 && ($owner['status'] === 'not_started' || $owner['status'] === 'in_progress') && !$owner['confirmed_final_rules_at'] && !$confirmedRules) {
            return ['ok' => false, 'error' => 'Please confirm that you understand you can only improve your work if your tutor returns it, and only twice.'];
        }
        if ($problems = cap_submit_problems($db, $capRow, $owner)) return ['ok' => false, 'error' => implode(' ', $problems)];
        $now = cap_now();
        $got = cap_tx($db, function () use ($db, $owner, $uid, $now, $confirmedRules) {
            $up = $db->prepare("UPDATE capstone_attempts SET status = 'submitted', submitted_at = ?, first_submitted_at = COALESCE(first_submitted_at, ?),
                                confirmed_final_rules_at = COALESCE(confirmed_final_rules_at, ?) WHERE id = ? AND status IN ('not_started','in_progress','returned')");
            $up->execute([$now, $now, $confirmedRules ? $now : null, (int) $owner['id']]);
            if ($up->rowCount() < 1) return false;
            $db->prepare("UPDATE capstone_attempts SET status = 'submitted', submitted_at = ?, first_submitted_at = COALESCE(first_submitted_at, ?) WHERE shared_attempt_id = ?")->execute([$now, $now, (int) $owner['id']]);
            $work = cap_work($db, $owner, false);
            $db->prepare("UPDATE capstone_submissions SET status = 'submitted', submitted_by = ?, submitted_at = ? WHERE id = ? AND status = 'draft'")->execute([$uid, $now, (int) $work['id']]);
            return true;
        });
        if (!$got) return ['ok' => false, 'error' => 'This capstone was already sent. Your tutor has it.'];
        cap_log($db, (int) $capRow['id'], (int) $owner['id'], $uid, $uid, 'submitted', ['returns_used' => (int) $owner['returns_used']]);
        require_once __DIR__ . '/CapstoneNotifier.php';
        (new CapstoneNotifier($db))->capSubmitted(cap_get($db, (int) $capRow['id']), $uid);
        return ['ok' => true, 'final' => (int) $owner['returns_used'] >= CAP_MAX_RETURNS];
    }

    /* ======================================================================= return (max 2) */

    function cap_can_return(array $owner): bool
    {
        return $owner['status'] === 'submitted' && (int) $owner['returns_used'] < CAP_MAX_RETURNS;
    }

    /**
     * Return the capstone to the student. Needs instructions. At most 2 returns. One atomic update, so a double click cannot give a third.
     * @return array{ok:bool, error?:string, return_no?:int}
     */
    function cap_return(PDO $db, int $attemptId, int $tutorId, string $instructions, ?string $sendByUtc, int $actor): array
    {
        $instructions = trim($instructions);
        if ($instructions === '') return ['ok' => false, 'error' => 'Write instructions for the student first.'];
        $a = cap_attempt($db, $attemptId);
        $cap = $a ? cap_get_for_tutor($db, (int) $a['capstone_id'], $tutorId) : null;
        if (!$cap) return ['ok' => false, 'error' => 'Not found.'];
        $owner = cap_owner($db, $a);
        if ((int) $owner['returns_used'] >= CAP_MAX_RETURNS) return ['ok' => false, 'error' => 'This capstone was already returned 2 times. Save the mark now.'];
        $r = cap_tx($db, function () use ($db, $owner, $instructions, $sendByUtc, $actor) {
            $up = $db->prepare("UPDATE capstone_attempts SET status = 'returned', returns_used = returns_used + 1 WHERE id = ? AND status = 'submitted' AND returns_used < " . CAP_MAX_RETURNS);
            $up->execute([(int) $owner['id']]);
            if ($up->rowCount() < 1) return null;
            $db->prepare("UPDATE capstone_attempts SET status = 'returned', returns_used = ? WHERE shared_attempt_id = ?")->execute([(int) $owner['returns_used'] + 1, (int) $owner['id']]);
            $no = (int) $owner['returns_used'] + 1;
            $work = cap_work($db, $owner, false);
            $db->prepare('INSERT INTO capstone_returns (attempt_id, submission_id, return_no, instructions, send_by, returned_by, returned_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
               ->execute([(int) $owner['id'], $work['id'] ?? null, $no, $instructions, $sendByUtc, $actor, cap_now()]);
            return $no;
        });
        if (!$r) return ['ok' => false, 'error' => 'This capstone is not waiting for you now (it may already have been returned).'];
        cap_log($db, (int) $cap['id'], (int) $owner['id'], $actor, (int) $owner['student_user_id'], 'returned', ['return_no' => $r]);
        require_once __DIR__ . '/CapstoneNotifier.php';
        (new CapstoneNotifier($db))->returned($cap, $owner, $r, $sendByUtc);
        return ['ok' => true, 'return_no' => $r];
    }

    function cap_returns(PDO $db, int $ownerAttemptId): array
    {
        $st = $db->prepare('SELECT * FROM capstone_returns WHERE attempt_id = ? ORDER BY return_no');
        $st->execute([$ownerAttemptId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Every sent version of the owner attempt with its items, newest first (the history). */
    function cap_versions(PDO $db, int $ownerAttemptId): array
    {
        $st = $db->prepare("SELECT * FROM capstone_submissions WHERE attempt_id = ? AND status = 'submitted' ORDER BY version DESC");
        $st->execute([$ownerAttemptId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $v) $out[] = $v + ['items' => cap_items($db, (int) $v['id'])];
        return $out;
    }

    /* ======================================================================= time keeping */

    /**
     * The Time keeping evidence from the tutor's milestone dates. Each row: title, due, sent_at, status
     * (on_time | late | missing | not_yet), minutes_late, earned, max. A late milestone loses its marks. Nothing is guessed:
     * the sum is only a suggestion the tutor can change.
     * @return array{rows:array, suggested:float, max:float}
     */
    function cap_time_evidence(PDO $db, array $capRow, array $owner, ?int $now = null): array
    {
        $now = $now ?? time();
        $rows = [];
        $sum = $max = 0.0;
        foreach (cap_milestones($db, (int) $capRow['id']) as $m) {
            $due = $m['due_at'];
            $sent = null;
            if ($m['kind'] === 'final') {
                $sent = $owner['first_submitted_at'] ?: null;
                $stu = $db->prepare('SELECT own_due_at FROM capstone_students WHERE capstone_id = ? AND student_user_id = ?');
                $stu->execute([(int) $capRow['id'], (int) $owner['student_user_id']]);
                if ($d2 = $stu->fetchColumn()) $due = $d2;
            } elseif ($m['kind'] === 'presentation') {
                $b = $db->prepare('SELECT b.done_at, b.late_minutes, s.starts_at FROM capstone_slot_bookings b JOIN capstone_slots s ON s.id = b.slot_id WHERE b.attempt_id = ? AND b.cancelled_at IS NULL ORDER BY b.id DESC LIMIT 1');
                $b->execute([(int) $owner['id']]);
                if ($bk = $b->fetch(PDO::FETCH_ASSOC)) {
                    $sent = gmdate('Y-m-d H:i:s', strtotime($bk['starts_at'] . ' UTC') + (int) $bk['late_minutes'] * 60);
                    $due = $bk['starts_at'];
                }
            } else {
                $ms = $db->prepare('SELECT submitted_at FROM capstone_milestone_submissions WHERE milestone_id = ? AND attempt_id = ?');
                $ms->execute([(int) $m['id'], (int) $owner['id']]);
                $sent = $ms->fetchColumn() ?: null;
            }
            $maxM = (float) $m['marks'];
            $max += $maxM;
            $dueTs = strtotime($due . ' UTC');
            if ($sent) {
                $late = max(0, (int) ceil((strtotime($sent . ' UTC') - $dueTs) / 60));
                $status = $late > 0 ? 'late' : 'on_time';
                $earned = $late > 0 ? 0.0 : $maxM;
            } elseif ($dueTs <= $now) {
                $status = 'missing'; $late = (int) ceil(($now - $dueTs) / 60); $earned = 0.0;
            } else {
                $status = 'not_yet'; $late = 0; $earned = 0.0;
            }
            $sum += $earned;
            $rows[] = ['id' => (int) $m['id'], 'title' => $m['title'], 'kind' => $m['kind'], 'due_at' => $due, 'sent_at' => $sent, 'status' => $status,
                       'minutes_late' => $late, 'earned' => $earned, 'max' => $maxM];
        }
        return ['rows' => $rows, 'suggested' => $sum, 'max' => $max];
    }

    /** "3 days late", "45 minutes late". */
    function cap_late_text(int $minutes): string
    {
        if ($minutes >= 2880) return round($minutes / 1440) . ' days late';
        if ($minutes >= 120) return round($minutes / 60) . ' hours late';
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' late';
    }

    /** A student sends something for one milestone (a short text, a link or a file). */
    function cap_milestone_send(PDO $db, array $capRow, array $attempt, int $milestoneId, int $uid, string $note, string $url, ?array $file): array
    {
        if (!cap_is_open_for($capRow) || $capRow['status'] === 'closed') return ['ok' => false, 'error' => 'This capstone is not open.'];
        $owner = cap_owner($db, $attempt);
        if (!in_array($owner['status'], ['not_started', 'in_progress', 'returned'], true)) return ['ok' => false, 'error' => 'Your capstone is already with your tutor.'];
        $m = $db->prepare('SELECT * FROM capstone_milestones WHERE id = ? AND capstone_id = ?');
        $m->execute([$milestoneId, (int) $capRow['id']]);
        $ms = $m->fetch(PDO::FETCH_ASSOC);
        if (!$ms || $ms['kind'] !== 'work') return ['ok' => false, 'error' => 'That milestone is sent automatically.'];
        $clean = null;
        if (trim($url) !== '') {
            $clean = asg_clean_link($url, true);
            if (!$clean) return ['ok' => false, 'error' => 'Use a full link that starts with https://'];
        }
        $path = $fname = null;
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $res = asg_store_document($file, 'capstone/' . (int) $capRow['id'] . '/milestones', ['pdf', 'docx', 'pptx', 'zip'], ASG_FILE_MAX);
            if (!$res['ok']) return $res;
            $path = $res['path'];
            $fname = mb_substr(basename((string) $file['name']), 0, 200);
        }
        $note = trim($note);
        if ($note === '' && !$clean && !$path) return ['ok' => false, 'error' => 'Write a short note, or add a link or a file.'];
        $exists = $db->prepare('SELECT id FROM capstone_milestone_submissions WHERE milestone_id = ? AND attempt_id = ?');
        $exists->execute([$milestoneId, (int) $owner['id']]);
        if ($exists->fetchColumn()) return ['ok' => false, 'error' => 'You already sent this milestone. Its sent time cannot change.'];
        $db->prepare('INSERT INTO capstone_milestone_submissions (milestone_id, attempt_id, note, url, file_path, file_name, submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
           ->execute([$milestoneId, (int) $owner['id'], mb_substr($note, 0, 2000), $clean, $path, $fname, cap_now()]);
        cap_log($db, (int) $capRow['id'], (int) $owner['id'], $uid, $uid, 'milestone_sent', ['milestone' => $milestoneId]);
        return ['ok' => true];
    }

    function cap_milestone_decide(PDO $db, int $capId, int $tutorId, int $milestoneSubId, bool $approve, string $comment, int $actor): bool
    {
        $st = $db->prepare('SELECT s.id FROM capstone_milestone_submissions s JOIN capstone_milestones m ON m.id = s.milestone_id JOIN capstones c ON c.id = m.capstone_id WHERE s.id = ? AND c.id = ? AND c.tutor_id = ?');
        $st->execute([$milestoneSubId, $capId, $tutorId]);
        if (!$st->fetchColumn()) return false;
        $db->prepare('UPDATE capstone_milestone_submissions SET approved_at = ?, tutor_comment = ? WHERE id = ?')->execute([$approve ? cap_now() : null, mb_substr(trim($comment), 0, 500) ?: null, $milestoneSubId]);
        cap_log($db, $capId, null, $actor, null, $approve ? 'milestone_approved' : 'milestone_commented', ['id' => $milestoneSubId]);
        return true;
    }

    /* ======================================================================= marks */

    function cap_mark(PDO $db, int $attemptId): ?array
    {
        $st = $db->prepare('SELECT * FROM capstone_marks WHERE attempt_id = ?');
        $st->execute([$attemptId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Whether the student may see this mark now. */
    function cap_mark_released(?array $mark, ?int $now = null): bool
    {
        if (!$mark) return false;
        $now = $now ?? time();
        return empty($mark['held_until']) || strtotime($mark['held_until'] . ' UTC') <= $now;
    }

    /**
     * Save (or update) the mark. The four parts must stay inside their limits; the total is the sum. In a team the same mark goes to
     * every member. $in: p_project..p_time, c_project..c_time, feedback, time_evidence (array), held_until (UTC or null).
     * @return array{ok:bool, error?:string, total?:float, result?:string, updated?:bool}
     */
    function cap_save_mark(PDO $db, int $attemptId, int $tutorId, array $in, int $actor): array
    {
        $a = cap_attempt($db, $attemptId);
        $cap = $a ? cap_get_for_tutor($db, (int) $a['capstone_id'], $tutorId) : null;
        if (!$cap) return ['ok' => false, 'error' => 'Not found.'];
        $owner = cap_owner($db, $a);
        if (!in_array($owner['status'], ['submitted', 'marked'], true)) return ['ok' => false, 'error' => 'There is nothing to mark yet: the student has not sent the capstone, or it was returned.'];
        $p = [];
        foreach (['project', 'docs', 'present', 'time'] as $k) {
            $v = $in['p_' . $k] ?? null;
            if ($v === null || $v === '' || !is_numeric($v)) return ['ok' => false, 'error' => 'Enter a mark for ' . CAP_PARTS[$k] . ' (0 if none).'];
            $v = round((float) $v, 1);
            if ($v < 0 || $v > (int) $cap['w_' . $k]) return ['ok' => false, 'error' => CAP_PARTS[$k] . ' must be between 0 and ' . (int) $cap['w_' . $k] . '.'];
            $p[$k] = $v;
        }
        $total = round(array_sum($p), 1);
        $result = cap_result_word($total);
        $held = !empty($in['held_until']) ? $in['held_until'] : null;
        $now = cap_now();
        $evidence = isset($in['time_evidence']) ? json_encode($in['time_evidence']) : null;
        $updated = (bool) cap_mark($db, (int) $owner['id']);
        $work = cap_work($db, $owner, false);
        cap_tx($db, function () use ($db, $owner, $p, $in, $total, $result, $held, $now, $actor, $evidence, $work) {
            foreach (cap_group_attempts($db, $owner) as $g) {
                $vals = [$work['id'] ?? null, $p['project'], $p['docs'], $p['present'], $p['time'],
                         mb_substr(trim((string) ($in['c_project'] ?? '')), 0, 2000), mb_substr(trim((string) ($in['c_docs'] ?? '')), 0, 2000),
                         mb_substr(trim((string) ($in['c_present'] ?? '')), 0, 2000), mb_substr(trim((string) ($in['c_time'] ?? '')), 0, 2000),
                         mb_substr(trim((string) ($in['feedback'] ?? '')), 0, 4000), $total, $result, (int) $owner['returns_used'] >= CAP_MAX_RETURNS ? 1 : 0, $held, $evidence, $actor, $now];
                if (cap_mark($db, (int) $g['id'])) {
                    $db->prepare('UPDATE capstone_marks SET submission_id=?, p_project=?, p_docs=?, p_present=?, p_time=?, c_project=?, c_docs=?, c_present=?, c_time=?, feedback=?, total=?, result=?, is_final=?, held_until=?, time_evidence=?, marked_by=?, marked_at=?, released_notified_at=NULL WHERE attempt_id=?')
                       ->execute(array_merge($vals, [(int) $g['id']]));
                } else {
                    $db->prepare('INSERT INTO capstone_marks (submission_id, p_project, p_docs, p_present, p_time, c_project, c_docs, c_present, c_time, feedback, total, result, is_final, held_until, time_evidence, marked_by, marked_at, attempt_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                       ->execute(array_merge($vals, [(int) $g['id']]));
                }
                $db->prepare("UPDATE capstone_attempts SET status = 'marked' WHERE id = ?")->execute([(int) $g['id']]);
            }
        });
        cap_log($db, (int) $cap['id'], (int) $owner['id'], $actor, (int) $owner['student_user_id'], $updated ? 'mark_updated' : 'marked', ['total' => $total, 'result' => $result, 'held' => (bool) $held]);
        if (cap_mark_released(cap_mark($db, (int) $owner['id']))) {
            // claim the release notice now, so the scheduled job does not repeat it
            foreach (cap_group_attempts($db, $owner) as $g) $db->prepare('UPDATE capstone_marks SET released_notified_at = ? WHERE attempt_id = ? AND released_notified_at IS NULL')->execute([$now, (int) $g['id']]);
            require_once __DIR__ . '/CapstoneNotifier.php';
            $n = new CapstoneNotifier($db);
            foreach (cap_group_attempts($db, $owner) as $g) $n->markReleasedFor($cap, $g, $total, $updated);
        }
        foreach (cap_group_attempts($db, $owner) as $g) cap_release_sync($db, (int) $g['student_user_id'], (int) $cap['course_id'], $actor);   // a corrected mark can release or hide
        return ['ok' => true, 'total' => $total, 'result' => $result, 'updated' => $updated];
    }

    /* ======================================================================= retake (max 5, paid again) */

    function cap_retake_fee(PDO $db, int $courseId): ?float
    {
        $st = $db->prepare('SELECT amount FROM capstone_retake_fees WHERE course_id = ?');
        $st->execute([$courseId]);
        $v = $st->fetchColumn();
        return $v === false || $v === null ? null : (float) $v;
    }

    /** @return array{ok:bool, why?:string} whether a retake may start for this student's newest attempt. */
    function cap_can_retake(PDO $db, array $cap, array $latest): array
    {
        if ($latest['status'] !== 'marked') return ['ok' => false, 'why' => 'The capstone has not been marked yet.'];
        $m = cap_mark($db, (int) $latest['id']);
        if (!$m || (float) $m['total'] >= CAP_PASS) return ['ok' => false, 'why' => 'The student passed, so no retake is needed.'];
        if ((int) $latest['attempt_no'] - 1 >= CAP_MAX_RETAKES) return ['ok' => false, 'why' => 'The student already used all ' . CAP_MAX_RETAKES . ' retakes.'];
        if (cap_retake_fee($db, (int) $cap['course_id']) === null) return ['ok' => false, 'why' => 'The office has not set the retake fee for this course yet. A retake cannot start until it is set.'];
        return ['ok' => true];
    }

    /**
     * Start a retake: a new attempt, fresh returns counter (0 of 2), a new fee. The student can work only after the fee is paid.
     * In a team every member gets a new attempt.
     * @return array{ok:bool, error?:string, attempt_id?:int}
     */
    function cap_start_retake(PDO $db, int $capId, int $studentUid, int $tutorId, int $actor): array
    {
        $cap = cap_get_for_tutor($db, $capId, $tutorId);
        if (!$cap) return ['ok' => false, 'error' => 'Capstone not found.'];
        $latest = cap_latest_attempt($db, $capId, $studentUid);
        if (!$latest) return ['ok' => false, 'error' => 'The student has no attempt yet.'];
        $ok = cap_can_retake($db, $cap, $latest);
        if (!$ok['ok']) return ['ok' => false, 'error' => $ok['why']];
        $fee = (float) cap_retake_fee($db, (int) $cap['course_id']);
        $owner = cap_owner($db, $latest);
        $newOwnerId = cap_tx($db, function () use ($db, $cap, $owner, $fee, $actor) {
            $group = cap_group_attempts($db, $owner);
            $map = [];
            foreach ($group as $g) {
                $no = (int) $g['attempt_no'] + 1;
                $db->prepare("INSERT INTO capstone_attempts (capstone_id, student_user_id, attempt_no, status, retake_fee, retake_started_by) VALUES (?, ?, ?, 'awaiting_payment', ?, ?)")
                   ->execute([(int) $cap['id'], (int) $g['student_user_id'], $no, $fee, $actor]);
                $map[(int) $g['id']] = (int) $db->lastInsertId();
            }
            $newOwner = $map[(int) $owner['id']];
            foreach ($group as $g) if ((int) $g['id'] !== (int) $owner['id']) $db->prepare('UPDATE capstone_attempts SET shared_attempt_id = ? WHERE id = ?')->execute([$newOwner, $map[(int) $g['id']]]);
            return $newOwner;
        });
        cap_log($db, $capId, $newOwnerId, $actor, $studentUid, 'retake_started', ['fee' => $fee]);
        if ($fee <= 0) { cap_retake_paid($db, $newOwnerId, 0, 'free', $actor); }
        else { require_once __DIR__ . '/CapstoneNotifier.php'; (new CapstoneNotifier($db))->retakeStarted($cap, cap_attempt($db, $newOwnerId), $fee); }
        return ['ok' => true, 'attempt_id' => $newOwnerId];
    }

    /**
     * The office recorded the retake fee. The retake opens for the student (for a team: when every member has paid).
     * Retake payments are kept in their own table, so they never change a student's course fee balance.
     */
    function cap_retake_paid(PDO $db, int $attemptId, float $amount, string $reference, int $actor): array
    {
        $a = cap_attempt($db, $attemptId);
        if (!$a || $a['status'] !== 'awaiting_payment') return ['ok' => false, 'error' => 'That retake is not waiting for payment.'];
        $now = cap_now();
        $db->prepare('INSERT IGNORE INTO capstone_retake_payments (attempt_id, amount, reference, recorded_by, paid_at) VALUES (?, ?, ?, ?, ?)')
           ->execute([$attemptId, $amount, mb_substr($reference, 0, 80) ?: null, $actor, $now]);
        $db->prepare('UPDATE capstone_attempts SET retake_paid_at = ? WHERE id = ? AND retake_paid_at IS NULL')->execute([$now, $attemptId]);
        cap_log($db, (int) $a['capstone_id'], $attemptId, $actor, (int) $a['student_user_id'], 'retake_paid', ['amount' => $amount]);
        $owner = cap_owner($db, cap_attempt($db, $attemptId));
        $group = cap_group_attempts($db, $owner);
        $allPaid = true;
        foreach ($group as $g) if (empty($g['retake_paid_at']) && (int) $g['id'] !== $attemptId) $allPaid = false;
        $opened = false;
        if ($allPaid) {
            foreach ($group as $g) $db->prepare("UPDATE capstone_attempts SET status = 'not_started' WHERE id = ? AND status = 'awaiting_payment'")->execute([(int) $g['id']]);
            $opened = true;
            require_once __DIR__ . '/CapstoneNotifier.php';
            $cap = cap_get($db, (int) $a['capstone_id']);
            foreach ($group as $g) (new CapstoneNotifier($db))->retakeOpen($cap, $g);
        }
        return ['ok' => true, 'opened' => $opened];
    }

    /** All attempts of one student in one capstone, oldest first, each with its mark (history for tutor and student). */
    function cap_history(PDO $db, int $capId, int $uid): array
    {
        $st = $db->prepare('SELECT * FROM capstone_attempts WHERE capstone_id = ? AND student_user_id = ? ORDER BY attempt_no');
        $st->execute([$capId, $uid]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) $out[] = $a + ['mark' => cap_mark($db, (int) $a['id'])];
        return $out;
    }

    /* ======================================================================= course finished */

    function cap_finish_row(PDO $db, int $uid, int $courseId): ?array
    {
        $st = $db->prepare('SELECT * FROM course_finish WHERE student_user_id = ? AND course_id = ? AND undone_at IS NULL ORDER BY id DESC LIMIT 1');
        $st->execute([$uid, $courseId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Planned time, the join date and months so far for the "Mark course finished" form. */
    function cap_finish_info(PDO $db, int $tutorId, int $uid, int $courseId): ?array
    {
        $am = new AssignmentModel($db);
        if (!$am->isTutorStudent($tutorId, $uid)) return null;
        $st = $db->prepare("SELECT r.id AS reg_id, r.created_at, c.title, c.duration_months, ts.start_date
                            FROM registrations r JOIN courses c ON c.id = r.course_id LEFT JOIN tutor_students ts ON ts.user_id = r.user_id AND ts.tutor_id = ?
                            WHERE r.user_id = ? AND r.course_id = ? AND r.status = 'approved' ORDER BY r.id DESC LIMIT 1");
        $st->execute([$tutorId, $uid, $courseId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return null;
        $start = $r['start_date'] ?: substr((string) $r['created_at'], 0, 10);
        $r['start'] = $start;
        $r['months_so_far'] = round(max(0, (time() - strtotime($start)) / 86400 / 30.4), 1);
        $r['finish'] = cap_finish_row($db, $uid, $courseId);
        $cert = $db->prepare("SELECT 1 FROM certificates WHERE registration_id = ? AND status IN ('issued','collected') LIMIT 1");
        $cert->execute([(int) $r['reg_id']]);
        $r['has_certificate'] = (bool) $cert->fetchColumn();
        return $r;
    }

    /** @return array{ok:bool, error?:string} */
    function cap_finish_course(PDO $db, int $tutorId, int $uid, int $courseId, string $finishedOn, string $note, int $actor): array
    {
        $info = cap_finish_info($db, $tutorId, $uid, $courseId);
        if (!$info) return ['ok' => false, 'error' => 'That is not one of your students in this course.'];
        if ($info['finish']) return ['ok' => false, 'error' => 'This course is already marked as finished.'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $finishedOn) || $finishedOn > date('Y-m-d', time() + 86400)) return ['ok' => false, 'error' => 'Choose a finish date that is not in the future.'];
        if ($finishedOn < $info['start']) return ['ok' => false, 'error' => 'The finish date is before the student started.'];
        $months = round((strtotime($finishedOn) - strtotime($info['start'])) / 86400 / 30.4, 1);
        $db->prepare('INSERT INTO course_finish (student_user_id, course_id, registration_id, tutor_id, planned_months, finished_after_months, finished_on, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
           ->execute([$uid, $courseId, (int) $info['reg_id'], $tutorId, (int) $info['duration_months'], $months, $finishedOn, mb_substr(trim($note), 0, 500) ?: null]);
        // Also tick the existing "Studies completed" switch, so the tutor's certificate button works as before.
        try {
            $db->prepare('UPDATE registrations SET studies_completed_at = COALESCE(studies_completed_at, ?), studies_completed_by = COALESCE(studies_completed_by, ?) WHERE id = ?')
               ->execute([cap_now(), $actor, (int) $info['reg_id']]);
        } catch (Throwable $e) {
        }
        cap_log($db, null, null, $actor, $uid, 'course_finished', ['course' => $courseId, 'months' => $months]);
        require_once __DIR__ . '/CapstoneNotifier.php';
        (new CapstoneNotifier($db))->courseFinished($uid, (string) $info['title']);
        cap_release_sync($db, $uid, $courseId, $actor);   // may release the transcript and the letter right now
        return ['ok' => true];
    }

    function cap_unfinish_course(PDO $db, int $tutorId, int $uid, int $courseId, int $actor): array
    {
        $info = cap_finish_info($db, $tutorId, $uid, $courseId);
        if (!$info || !$info['finish']) return ['ok' => false, 'error' => 'Nothing to undo.'];
        if ($info['has_certificate']) return ['ok' => false, 'error' => 'A certificate was already issued, so this cannot be undone.'];
        $db->prepare('UPDATE course_finish SET undone_at = ? WHERE id = ?')->execute([cap_now(), (int) $info['finish']['id']]);
        cap_log($db, null, null, $actor, $uid, 'course_unfinished', ['course' => $courseId]);
        cap_release_sync($db, $uid, $courseId, $actor);   // hides the transcript and the letter again (no certificate was issued)
        return ['ok' => true];
    }

    /** Tutor opens the capstone early for one student (for example a student who finished early). */
    function cap_open_for_student(PDO $db, int $capId, int $uid, int $tutorId, int $actor): array
    {
        $cap = cap_get_for_tutor($db, $capId, $tutorId);
        if (!$cap || !isset(cap_students($db, $capId)[$uid])) return ['ok' => false, 'error' => 'Not found.'];
        if (in_array($cap['status'], ['draft'], true)) return ['ok' => false, 'error' => 'Schedule the capstone first (it is still a draft).'];
        $db->prepare('UPDATE capstone_students SET opened_early_at = ? WHERE capstone_id = ? AND student_user_id = ? AND opened_early_at IS NULL')->execute([cap_now(), $capId, $uid]);
        if ($cap['status'] === 'scheduled') {
            $row = cap_for_student($db, $capId, $uid);
            if ($row) cap_ensure_attempt($db, $row, $uid);
        }
        cap_log($db, $capId, null, $actor, $uid, 'opened_early_for_student');
        return ['ok' => true];
    }

    /* ======================================================================= average, certificate, transcript */

    /** Capstone rows in the shape AssignmentAverage::compute() expects (newest attempt only; kind 'capstone'). */
    function cap_average_rows(PDO $db, int $uid, ?int $tutorId = null): array
    {
        if (!cap_installed($db)) return [];
        $sql = "SELECT c.id, c.title, c.tutor_id, c.course_id, co.title AS course_title, c.weight, c.due_at, cs.own_due_at,
                       a.id AS attempt_id, a.status AS attempt_status, a.first_submitted_at, m.total, m.marked_at, m.held_until
                FROM capstone_students cs JOIN capstones c ON c.id = cs.capstone_id AND c.status IN ('open','closed') LEFT JOIN courses co ON co.id = c.course_id
                LEFT JOIN capstone_attempts a ON a.capstone_id = c.id AND a.student_user_id = cs.student_user_id
                     AND a.attempt_no = (SELECT MAX(a2.attempt_no) FROM capstone_attempts a2 WHERE a2.capstone_id = c.id AND a2.student_user_id = cs.student_user_id)
                LEFT JOIN capstone_marks m ON m.attempt_id = a.id
                WHERE cs.student_user_id = ? AND cs.removed_at IS NULL" . ($tutorId !== null ? ' AND c.tutor_id = ?' : '');
        $st = $db->prepare($sql);
        $st->execute($tutorId !== null ? [$uid, $tutorId] : [$uid]);
        $rows = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $graded = $r['attempt_status'] === 'marked' && $r['total'] !== null;
            $rows[] = [
                'id' => 1000000 + (int) $r['id'], 'title' => $r['title'], 'tutor_id' => $r['tutor_id'], 'course_id' => $r['course_id'], 'course_title' => $r['course_title'],
                'kind' => 'capstone', 'weight' => $r['weight'], 'grading_mode' => 'marks', 'max_score' => 100, 'release_grades_at' => $r['held_until'],
                'sort_order' => 999999, 'due' => $r['own_due_at'] ?: $r['due_at'],
                'sub_status' => $graded ? 'graded' : ($r['attempt_status'] === 'submitted' ? 'submitted' : ($r['attempt_status'] === 'returned' ? 'returned' : null)),
                'submitted_at' => $r['first_submitted_at'], 'score' => $graded ? $r['total'] : null, 'pass_result' => null,
                'result_published_at' => $graded ? $r['marked_at'] : null, 'is_late' => 0,
            ];
        }
        return $rows;
    }

    /** The course average with the capstone at its weight, from the existing average service (released marks only; held marks do not count). */
    function cap_course_average(PDO $db, int $uid, int $courseId): ?float
    {
        require_once __DIR__ . '/AssignmentAverage.php';
        $all = AssignmentAverage::forStudent($db, $uid, null, time());
        return $all[$courseId]['average'] ?? null;
    }

    /** True when the student has a capstone in this course that is open or closed (a hidden one does not exist for the rules). */
    function cap_course_has_capstone(PDO $db, int $uid, int $courseId): bool
    {
        $st = $db->prepare("SELECT 1 FROM capstone_students cs JOIN capstones c ON c.id = cs.capstone_id
                            WHERE cs.student_user_id = ? AND c.course_id = ? AND c.status IN ('open','closed') AND cs.removed_at IS NULL LIMIT 1");
        $st->execute([$uid, $courseId]);
        return (bool) $st->fetchColumn();
    }

    /**
     * THE certificate readiness check for a course that has a capstone. Every place uses this one function: the tutor's checklist,
     * the admin Certificates page (words only), the release of the transcript and the letter, and the actions that issue a certificate.
     *
     * A student is Ready only when all four are true:
     *   1 finished   the tutor marked the course finished;
     *   2 fees       the course fees are paid 100% (retake payments never count and never change a balance);
     *   3 capstone   the newest attempt has a final mark of 50 or more AND the mark is released (a held mark does not count yet);
     *   4 average    the course average (existing average service, capstone at its weight, released marks only) is 50 or more.
     *
     * The result has two layers: 'words' (safe for the admin: no mark, number or comment) and 'detail' (tutor only, with numbers).
     * @return array{applies:bool, ready:bool, word:string, words:array, first_failed:?string, reason:string, conditions:array, detail:array}
     */
    function cap_certificate_readiness(PDO $db, int $uid, int $courseId, ?int $registrationId = null): array
    {
        require_once __DIR__ . '/CertificateService.php';
        require_once __DIR__ . '/AssignmentAverage.php';
        if (!cap_course_has_capstone($db, $uid, $courseId)) {
            return ['applies' => false, 'ready' => false, 'word' => 'Not set', 'words' => [], 'first_failed' => null, 'reason' => '', 'conditions' => [], 'detail' => []];
        }
        $reg = $registrationId;
        if (!$reg) {
            $st = $db->prepare("SELECT id FROM registrations WHERE user_id = ? AND course_id = ? AND status = 'approved' ORDER BY id DESC LIMIT 1");
            $st->execute([$uid, $courseId]);
            $reg = (int) $st->fetchColumn();
        }
        // 1 course finished: the tutor marked it finished, OR the course time has passed (counted from the day the student was added)
        $fin = cap_finish_row($db, $uid, $courseId);
        require_once __DIR__ . '/CertificateApplication.php';
        $timeInfo = cert_course_time($db, $uid, $courseId);
        $timeDone = (bool) $fin || ($timeInfo && $timeInfo['finished']);
        // 2 fees (the existing course fee balance only)
        $balance = $reg ? cert_student_balance($db, $reg) : 0.0;
        // 3 capstone: newest capstone of the course, newest attempt, released final mark
        $st = $db->prepare("SELECT c.id, c.title FROM capstone_students cs JOIN capstones c ON c.id = cs.capstone_id
                            WHERE cs.student_user_id = ? AND c.course_id = ? AND c.status IN ('open','closed') AND cs.removed_at IS NULL ORDER BY c.id DESC LIMIT 1");
        $st->execute([$uid, $courseId]);
        $capRow = $st->fetch(PDO::FETCH_ASSOC);
        $att = $capRow ? cap_latest_attempt($db, (int) $capRow['id'], $uid) : null;
        $mark = $att ? cap_mark($db, (int) $att['id']) : null;
        $released = $att && $att['status'] === 'marked' && cap_mark_released($mark);
        if (!$released) $capWord = 'Not marked yet';
        else $capWord = (float) $mark['total'] >= CAP_PASS ? 'Passed' : 'Not passed';
        // 4 average
        $all = AssignmentAverage::forStudent($db, $uid, null, time());
        $avgInfo = $all[$courseId] ?? null;
        $avg = $avgInfo['average'] ?? null;
        $avgOk = $avg !== null && $avg >= CAP_PASS;
        $avgWord = $avg === null ? 'Not ready' : ($avgOk ? 'Pass or above' : ($capWord === 'Not marked yet' ? 'Not ready' : 'Fail'));

        // the failed part, for the tutor
        $failedPart = null;
        if ($capWord === 'Not passed') {
            $failedPart = 'the capstone';
        } elseif ($avg !== null && !$avgOk) {
            $worst = null;
            foreach (($avgInfo['by_kind'] ?? []) as $kind => $pct) {
                if ($pct !== null && ($worst === null || $pct < $worst[1])) $worst = [$kind, $pct];
            }
            $failedPart = $worst ? 'your ' . ($worst[0] === 'weekly' ? 'weekly' : 'daily') . ' assignments (about ' . (int) round($worst[1]) . '%)' : 'your assignments';
        }

        $conditions = [
            'finished' => ['ok' => $timeDone, 'word' => $timeDone ? 'Yes' : 'No',
                           'reason' => $fin ? 'Marked finished on ' . date('j M Y', strtotime($fin['finished_on'])) . '.'
                               : ($timeDone ? 'The course time is finished (ended ' . date('j M Y', strtotime($timeInfo['end'])) . ').'
                                            : 'The course time is not finished yet' . ($timeInfo ? ' (it ends on ' . date('j M Y', strtotime($timeInfo['end'])) . ')' : '') . ' and the tutor has not marked it finished.')],
            'fees'     => ['ok' => $balance <= 0, 'word' => $balance <= 0 ? 'Paid' : 'Balance due',
                           'reason' => $balance <= 0 ? 'The course fees are paid in full.' : 'Fee balance of KES ' . number_format($balance) . ' must be cleared first.'],
            'capstone' => ['ok' => $capWord === 'Passed', 'word' => $capWord,
                           'reason' => $capWord === 'Passed' ? 'The capstone is passed.' : ($capWord === 'Not passed' ? 'The capstone was not passed. A retake is needed.'
                                       : ($att && $att['status'] === 'marked' ? 'The capstone mark is held and does not count yet.' : 'The capstone is not marked yet.'))],
            'average'  => ['ok' => $avgOk, 'word' => $avgWord,
                           'reason' => $avgOk ? 'The course average is a pass.' : ($avg === null ? 'There are no released marks yet.'
                                       : 'The course average is below the pass mark. A retake of the failed part is needed.')],
        ];
        $first = null;
        foreach (['finished', 'fees', 'capstone', 'average'] as $k) if (!$conditions[$k]['ok']) { $first = $k; break; }
        $ready = $first === null;

        // the overall Certificate word, in this order
        if (!$conditions['finished']['ok'] || $capWord === 'Not marked yet') $word = 'Not ready';
        elseif ($capWord === 'Not passed' || ($avg !== null && !$avgOk)) $word = 'Retake needed';
        elseif (!$conditions['fees']['ok']) $word = 'Waiting for fees';
        else $word = 'Ready';

        return [
            'applies' => true, 'ready' => $ready, 'word' => $word,
            'words' => ['finished' => $conditions['finished']['word'], 'fees' => $conditions['fees']['word'], 'capstone' => $capWord, 'average' => $avgWord, 'certificate' => $word],
            'first_failed' => $first, 'reason' => $first ? $conditions[$first]['reason'] : '', 'conditions' => $conditions,
            'detail' => ['balance' => $balance, 'average' => $avg, 'capstone_title' => $capRow['title'] ?? null, 'capstone_total' => $released ? (float) $mark['total'] : null,
                         'capstone_result' => $released ? $mark['result'] : null, 'capstone_held' => $att && $att['status'] === 'marked' && !$released,
                         'failed_part' => $failedPart, 'registration_id' => $reg, 'finish' => $fin, 'attempt_no' => $att ? (int) $att['attempt_no'] : null],
        ];
    }

    /* ======================================================================= release: transcript + letter */

    function cap_release_row(PDO $db, int $uid, int $courseId): ?array
    {
        $st = $db->prepare('SELECT * FROM course_release WHERE student_user_id = ? AND course_id = ?');
        $st->execute([$uid, $courseId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** True when a certificate was already issued for this enrolment: after that nothing changes the release. */
    function cap_certificate_issued(PDO $db, int $uid, int $courseId): bool
    {
        $st = $db->prepare("SELECT 1 FROM certificates c JOIN registrations r ON r.id = c.registration_id
                            WHERE r.user_id = ? AND r.course_id = ? AND c.status IN ('issued','collected') LIMIT 1");
        $st->execute([$uid, $courseId]);
        return (bool) $st->fetchColumn();
    }

    /**
     * Make the stored release match the readiness: when the student becomes Ready, the transcript and the letter are released together
     * (and the student told once); when they stop being Ready before a certificate is issued, both are hidden again.
     * Safe to call any time and from any page: the change is claimed with one conditional update.
     * @return string 'released' | 'hidden' | 'unchanged'
     */
    function cap_release_sync(PDO $db, int $uid, int $courseId, ?int $actor = null): string
    {
        try {
            $r = cap_certificate_readiness($db, $uid, $courseId);
        } catch (Throwable $e) {
            return 'unchanged';
        }
        if (!$r['applies']) return 'unchanged';
        $row = cap_release_row($db, $uid, $courseId);
        $now = cap_now();
        if ($r['ready']) {
            if ($row && $row['transcript_released_at'] && $row['letter_released_at']) return 'unchanged';
            if (!$row) {
                $db->prepare('INSERT IGNORE INTO course_release (student_user_id, course_id) VALUES (?, ?)')->execute([$uid, $courseId]);
            }
            $up = $db->prepare('UPDATE course_release SET transcript_released_at = ?, letter_released_at = ?, letter_dated_at = ?, hidden_at = NULL,
                                letter_hash = COALESCE(letter_hash, ?) WHERE student_user_id = ? AND course_id = ? AND (transcript_released_at IS NULL OR letter_released_at IS NULL)');
            $up->execute([$now, $now, $now, bin2hex(random_bytes(16)), $uid, $courseId]);
            if ($up->rowCount() < 1) return 'unchanged';
            cap_log($db, null, null, $actor, $uid, 'release_released', ['course' => $courseId]);
            $claim = $db->prepare('UPDATE course_release SET released_notified_at = ? WHERE student_user_id = ? AND course_id = ? AND (released_notified_at IS NULL OR released_notified_at < transcript_released_at)');
            $claim->execute([$now, $uid, $courseId]);
            if ($claim->rowCount() > 0) {
                require_once __DIR__ . '/CapstoneNotifier.php';
                $t = $db->prepare('SELECT title FROM courses WHERE id = ?');
                $t->execute([$courseId]);
                (new CapstoneNotifier($db))->completionReady($uid, (string) $t->fetchColumn(), $courseId);
            }
            return 'released';
        }
        if ($row && ($row['transcript_released_at'] || $row['letter_released_at']) && !cap_certificate_issued($db, $uid, $courseId)) {
            $up = $db->prepare('UPDATE course_release SET transcript_released_at = NULL, letter_released_at = NULL, hidden_at = ? WHERE student_user_id = ? AND course_id = ? AND (transcript_released_at IS NOT NULL OR letter_released_at IS NOT NULL)');
            $up->execute([$now, $uid, $courseId]);
            if ($up->rowCount() > 0) {
                cap_log($db, null, null, $actor, $uid, 'release_hidden', ['course' => $courseId, 'because' => $r['first_failed']]);
                return 'hidden';
            }
        }
        return 'unchanged';
    }

    /**
     * Is the transcript / letter released to the student right now? Re-checks the readiness first, so a student who stopped
     * being Ready is never served from an old release.
     */
    function cap_release_state(PDO $db, int $uid, int $courseId): array
    {
        cap_release_sync($db, $uid, $courseId);
        $row = cap_release_row($db, $uid, $courseId);
        return ['transcript' => (bool) ($row['transcript_released_at'] ?? null), 'letter' => (bool) ($row['letter_released_at'] ?? null), 'row' => $row];
    }

    /** The tutor's optional personal sentence on the letter. Only before the letter is released; the admin never sees it. */
    function cap_letter_note_save(PDO $db, int $tutorId, int $uid, int $courseId, string $note, int $actor): array
    {
        $am = new AssignmentModel($db);
        if (!$am->isTutorStudent($tutorId, $uid)) return ['ok' => false, 'error' => 'That is not one of your students.'];
        $row = cap_release_row($db, $uid, $courseId);
        if ($row && $row['letter_released_at']) return ['ok' => false, 'error' => 'The letter was already released, so it cannot change.'];
        $note = mb_substr(trim(preg_replace('/\s+/', ' ', $note)), 0, 300);
        $db->prepare('INSERT IGNORE INTO course_release (student_user_id, course_id) VALUES (?, ?)')->execute([$uid, $courseId]);
        $db->prepare('UPDATE course_release SET letter_note = ? WHERE student_user_id = ? AND course_id = ?')->execute([$note !== '' ? $note : null, $uid, $courseId]);
        cap_log($db, null, null, $actor, $uid, 'letter_note_saved', ['course' => $courseId]);
        return ['ok' => true];
    }

    /**
     * Students whose readiness may have changed (course finished, or already released): the scheduled job re-checks them, so a fee
     * payment or a corrected mark is picked up without anyone opening a page.
     * @return array{released:int, hidden:int}
     */
    function cap_release_sweep(PDO $db): array
    {
        $out = ['released' => 0, 'hidden' => 0];
        $rows = $db->query("SELECT student_user_id AS u, course_id AS c FROM course_finish WHERE undone_at IS NULL
                            UNION SELECT student_user_id, course_id FROM course_release WHERE transcript_released_at IS NOT NULL OR letter_released_at IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $x = cap_release_sync($db, (int) $r['u'], (int) $r['c']);
            if ($x === 'released') $out['released']++;
            if ($x === 'hidden') $out['hidden']++;
        }
        return $out;
    }

    /** Everything the letter shows (no marks): name, course, real start and finish dates, the result word, the capstone title, the tutor's sentence. */
    function cap_letter_data(PDO $db, int $uid, int $courseId): ?array
    {
        $r = cap_certificate_readiness($db, $uid, $courseId);
        if (!$r['applies']) return null;
        $st = $db->prepare("SELECT r.full_name, r.registration_no, r.created_at, c.title AS course_title, c.duration_months
                            FROM registrations r JOIN courses c ON c.id = r.course_id WHERE r.user_id = ? AND r.course_id = ? AND r.status = 'approved' ORDER BY r.id DESC LIMIT 1");
        $st->execute([$uid, $courseId]);
        $reg = $st->fetch(PDO::FETCH_ASSOC);
        if (!$reg) return null;
        $fin = $r['detail']['finish'] ?? cap_finish_row($db, $uid, $courseId);
        $row = cap_release_row($db, $uid, $courseId);
        $tsd = $db->prepare('SELECT ts.start_date FROM tutor_students ts WHERE ts.user_id = ? ORDER BY ts.assigned_at DESC LIMIT 1');
        $tsd->execute([$uid]);
        $startDate = $tsd->fetchColumn() ?: substr((string) $reg['created_at'], 0, 10);
        $months = $fin['finished_after_months'] ?? null;
        $planned = (int) ($fin['planned_months'] ?? $reg['duration_months']);
        return [
            'name' => trim((string) $reg['full_name']), 'registration_no' => $reg['registration_no'], 'course' => $reg['course_title'],
            'start' => $startDate, 'finish' => $fin['finished_on'] ?? null, 'months' => $months, 'planned' => $planned,
            'shortened' => $months !== null && $planned > 0 && (float) $months < $planned - 0.05,
            'result' => $r['detail']['capstone_result'], 'capstone_title' => $r['detail']['capstone_title'],
            'note' => $row['letter_note'] ?? null, 'hash' => $row['letter_hash'] ?? null, 'dated' => $row['letter_dated_at'] ?? null,
            'released' => (bool) ($row['letter_released_at'] ?? null),
        ];
    }

    /**
     * For the transcript built later: one row per attempt with the four part marks, total, result word, attempt number and returns used.
     * The transcript reads this function only, so the capstone code can change without touching it.
     */
    function cap_transcript_rows(PDO $db, int $uid, int $courseId): array
    {
        $st = $db->prepare(
            "SELECT c.id AS capstone_id, c.title, a.attempt_no, a.returns_used, m.p_project, m.p_docs, m.p_present, m.p_time, m.total, m.result, m.marked_at, m.held_until
             FROM capstone_attempts a JOIN capstones c ON c.id = a.capstone_id JOIN capstone_marks m ON m.attempt_id = a.id
             WHERE a.student_user_id = ? AND c.course_id = ? ORDER BY c.id, a.attempt_no"
        );
        $st->execute([$uid, $courseId]);
        $rows = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $r['released'] = cap_mark_released($r);
            $rows[] = $r;
        }
        return $rows;
    }

    function cap_transcript_pref(PDO $db, int $uid, int $courseId): bool
    {
        $st = $db->prepare('SELECT allow_admin_preview FROM capstone_transcript_pref WHERE student_user_id = ? AND course_id = ?');
        $st->execute([$uid, $courseId]);
        return (bool) $st->fetchColumn();
    }

    function cap_set_transcript_pref(PDO $db, int $uid, int $courseId, bool $on, int $actor): void
    {
        $db->prepare('INSERT INTO capstone_transcript_pref (student_user_id, course_id, allow_admin_preview) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE allow_admin_preview = VALUES(allow_admin_preview)')
           ->execute([$uid, $courseId, $on ? 1 : 0]);
        cap_log($db, null, null, $actor, $uid, 'transcript_pref', ['course' => $courseId, 'on' => $on]);
    }

    /* ======================================================================= presentation slots (Class Calendar) */

    function cap_slots(PDO $db, int $capId, bool $withCancelled = false): array
    {
        $st = $db->prepare("SELECT s.*, (SELECT COUNT(*) FROM capstone_slot_bookings b WHERE b.slot_id = s.id AND b.cancelled_at IS NULL) AS booked
                            FROM capstone_slots s WHERE s.capstone_id = ?" . ($withCancelled ? '' : ' AND s.cancelled_at IS NULL') . ' ORDER BY s.starts_at');
        $st->execute([$capId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Create a presentation slot. It becomes a class on the tutor's calendar (no students until one books). */
    function cap_slot_create(PDO $db, array $cap, int $tutorUid, string $startUtc, int $minutes, string $mode, string $place, int $capacity, int $actor): array
    {
        $minutes = max(5, min(240, $minutes));
        if ($startUtc <= cap_now()) return ['ok' => false, 'error' => 'Choose a time in the future.'];
        if (!in_array($mode, ['online', 'in_person'], true)) $mode = 'online';
        $place = trim($place);
        if ($mode === 'online' && $place !== '' && !preg_match('#^https://#i', $place)) return ['ok' => false, 'error' => 'An online slot needs a full https:// meeting link (or leave it empty and add it later).'];
        $calMode = $mode === 'online' ? 'online' : 'physical';
        $end = gmdate('Y-m-d H:i:s', strtotime($startUtc . ' UTC') + $minutes * 60);
        $classId = null;
        try {
            $db->prepare("INSERT INTO classes (tutor_id, title, course_id, topic, notes, start_utc, end_utc, tutor_timezone, mode, campus, room, meeting_link, status, reminders_on)
                          VALUES (?, ?, ?, 'Capstone presentation', ?, ?, ?, 'Africa/Nairobi', ?, ?, ?, ?, 'scheduled', 1)")
               ->execute([$tutorUid, 'Capstone presentation: ' . mb_substr((string) $cap['title'], 0, 150), $cap['course_id'], 'Presentation slot', $startUtc, $end, $calMode,
                          $calMode === 'physical' ? 'Thika' : null, $calMode === 'physical' ? mb_substr($place, 0, 80) : null, $calMode === 'online' && $place !== '' ? $place : null]);
            $classId = (int) $db->lastInsertId();
        } catch (Throwable $e) {
            error_log('[capstone slot calendar] ' . $e->getMessage());   // calendar tables missing: the slot still works without the calendar entry
        }
        $db->prepare('INSERT INTO capstone_slots (capstone_id, class_id, starts_at, minutes, mode, place, capacity) VALUES (?, ?, ?, ?, ?, ?, ?)')
           ->execute([(int) $cap['id'], $classId, $startUtc, $minutes, $mode, mb_substr($place, 0, 200) ?: null, max(1, min(50, $capacity))]);
        cap_log($db, (int) $cap['id'], null, $actor, null, 'slot_created', ['start' => $startUtc]);
        return ['ok' => true, 'id' => (int) $db->lastInsertId()];
    }

    function cap_slot_get(PDO $db, int $slotId): ?array
    {
        $st = $db->prepare('SELECT * FROM capstone_slots WHERE id = ?');
        $st->execute([$slotId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    function cap_slot_cancel(PDO $db, array $cap, int $slotId, int $actor): bool
    {
        $s = cap_slot_get($db, $slotId);
        if (!$s || (int) $s['capstone_id'] !== (int) $cap['id']) return false;
        $db->prepare('UPDATE capstone_slots SET cancelled_at = ? WHERE id = ?')->execute([cap_now(), $slotId]);
        $db->prepare('UPDATE capstone_slot_bookings SET cancelled_at = ? WHERE slot_id = ? AND cancelled_at IS NULL')->execute([cap_now(), $slotId]);
        if ($s['class_id']) try { $db->prepare("UPDATE classes SET status = 'cancelled', cancel_reason = 'Presentation slot removed' WHERE id = ?")->execute([(int) $s['class_id']]); } catch (Throwable $e) {}
        cap_log($db, (int) $cap['id'], null, $actor, null, 'slot_cancelled', ['slot' => $slotId]);
        return true;
    }

    /** The booking of this attempt group, if any. */
    function cap_booking(PDO $db, array $owner): ?array
    {
        $st = $db->prepare('SELECT b.*, s.starts_at, s.minutes, s.mode, s.place, s.class_id FROM capstone_slot_bookings b JOIN capstone_slots s ON s.id = b.slot_id
                            WHERE b.attempt_id = ? AND b.cancelled_at IS NULL AND s.cancelled_at IS NULL ORDER BY b.id DESC LIMIT 1');
        $st->execute([(int) $owner['id']]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** A student (or team) books a slot. Only when the capstone is open, one slot per attempt, and the slot is not full. */
    function cap_slot_book(PDO $db, array $capRow, array $attempt, int $slotId, int $uid): array
    {
        if (!cap_is_open_for($capRow) || $capRow['status'] === 'closed') return ['ok' => false, 'error' => 'This capstone is not open.'];
        $owner = cap_owner($db, $attempt);
        $slot = cap_slot_get($db, $slotId);
        if (!$slot || (int) $slot['capstone_id'] !== (int) $capRow['id'] || $slot['cancelled_at']) return ['ok' => false, 'error' => 'That slot is not available.'];
        if ($slot['starts_at'] <= cap_now()) return ['ok' => false, 'error' => 'That slot has already started.'];
        if (cap_booking($db, $owner)) return ['ok' => false, 'error' => 'You already booked a slot. Cancel it first to choose another.'];
        $r = cap_tx($db, function () use ($db, $slot, $owner, $uid) {
            $n = (int) $db->query('SELECT COUNT(*) FROM capstone_slot_bookings WHERE slot_id = ' . (int) $slot['id'] . ' AND cancelled_at IS NULL')->fetchColumn();
            if ($n >= (int) $slot['capacity']) return false;
            $db->prepare('INSERT INTO capstone_slot_bookings (slot_id, attempt_id, booked_by, booked_at) VALUES (?, ?, ?, ?)')->execute([(int) $slot['id'], (int) $owner['id'], $uid, cap_now()]);
            if ($slot['class_id']) {
                try {
                    foreach (cap_group_attempts($db, $owner) as $g) $db->prepare('INSERT IGNORE INTO class_students (class_id, student_id) VALUES (?, ?)')->execute([(int) $slot['class_id'], (int) $g['student_user_id']]);
                } catch (Throwable $e) { error_log('[capstone booking calendar] ' . $e->getMessage()); }
            }
            return true;
        });
        if (!$r) return ['ok' => false, 'error' => 'That slot is full. Choose another.'];
        cap_log($db, (int) $capRow['id'], (int) $owner['id'], $uid, $uid, 'slot_booked', ['slot' => $slotId]);
        return ['ok' => true];
    }

    function cap_slot_unbook(PDO $db, array $capRow, array $attempt, int $uid): bool
    {
        $owner = cap_owner($db, $attempt);
        $b = cap_booking($db, $owner);
        if (!$b || $b['starts_at'] <= cap_now() || $b['done_at']) return false;
        $db->prepare('UPDATE capstone_slot_bookings SET cancelled_at = ? WHERE id = ?')->execute([cap_now(), (int) $b['id']]);
        if ($b['class_id']) try {
            foreach (cap_group_attempts($db, $owner) as $g) $db->prepare('DELETE FROM class_students WHERE class_id = ? AND student_id = ?')->execute([(int) $b['class_id'], (int) $g['student_user_id']]);
        } catch (Throwable $e) {}
        cap_log($db, (int) $capRow['id'], (int) $owner['id'], $uid, $uid, 'slot_unbooked');
        return true;
    }

    /** The tutor marks the presentation Done, with notes and how many minutes late it started (0 = on time). */
    function cap_presentation_done(PDO $db, array $cap, array $owner, string $notes, int $lateMinutes, int $actor): bool
    {
        $b = cap_booking($db, $owner);
        if (!$b) return false;
        $db->prepare('UPDATE capstone_slot_bookings SET done_at = ?, notes = ?, late_minutes = ? WHERE id = ?')->execute([cap_now(), mb_substr(trim($notes), 0, 2000) ?: null, max(0, min(600, $lateMinutes)), (int) $b['id']]);
        cap_log($db, (int) $cap['id'], (int) $owner['id'], $actor, (int) $owner['student_user_id'], 'presentation_done', ['late' => $lateMinutes]);
        return true;
    }

    /* ======================================================================= tracker */

    /** One row per student for the tracker page. */
    function cap_tracker(PDO $db, array $cap): array
    {
        $milestones = cap_milestones($db, (int) $cap['id']);
        $now = time();
        $rows = [];
        foreach (cap_students($db, (int) $cap['id']) as $uid => $s) {
            $a = cap_latest_attempt($db, (int) $cap['id'], $uid);
            $owner = $a ? cap_owner($db, $a) : null;
            $row = $s + ['attempt' => $a, 'owner' => $owner];
            $row['state'] = cap_state($s + $cap, $a, $now);
            $row['returns_used'] = $a ? (int) $a['returns_used'] : 0;
            $row['mark'] = $a ? cap_mark($db, (int) $a['id']) : null;
            $row['ms'] = [];
            foreach ($milestones as $m) {
                $sub = null;
                if ($owner && $m['kind'] === 'work') {
                    $q = $db->prepare('SELECT * FROM capstone_milestone_submissions WHERE milestone_id = ? AND attempt_id = ?');
                    $q->execute([(int) $m['id'], (int) $owner['id']]);
                    $sub = $q->fetch(PDO::FETCH_ASSOC) ?: null;
                }
                $due = $m['due_at'];
                if ($m['kind'] === 'final') {
                    $sent = $owner['first_submitted_at'] ?? null;
                    if (!empty($s['own_due_at'])) $due = $s['own_due_at'];
                } elseif ($m['kind'] === 'presentation') {
                    $bk = $owner ? cap_booking($db, $owner) : null;
                    $sent = $bk && $bk['done_at'] ? $bk['starts_at'] : null;
                    if ($bk) $due = $bk['starts_at'];
                } else {
                    $sent = $sub['submitted_at'] ?? null;
                }
                $dueTs = strtotime($due . ' UTC');
                $word = $sent ? (strtotime($sent . ' UTC') > $dueTs ? 'Late' : (($sub['approved_at'] ?? null) ? 'Approved' : 'On time')) : ($dueTs <= $now ? 'Missing' : 'Not yet');
                if ($sent && !empty($sub['approved_at'])) $word = 'Approved';
                $row['ms'][(int) $m['id']] = ['word' => $word, 'sub' => $sub];
            }
            $row['booking'] = $owner ? cap_booking($db, $owner) : null;
            $rows[$uid] = $row;
        }
        return $rows;
    }

    /** Count of submitted capstones waiting for the tutor to mark (for menus and badges). */
    function cap_to_mark_count(PDO $db, int $tutorId): int
    {
        if (!cap_installed($db)) return 0;
        $st = $db->prepare("SELECT COUNT(*) FROM capstone_attempts a JOIN capstones c ON c.id = a.capstone_id
                            WHERE c.tutor_id = ? AND a.status = 'submitted' AND a.shared_attempt_id IS NULL");
        $st->execute([$tutorId]);
        return (int) $st->fetchColumn();
    }
}
