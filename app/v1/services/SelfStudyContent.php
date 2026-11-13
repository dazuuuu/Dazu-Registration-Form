<?php
// app/v1/services/SelfStudyContent.php — Self-study (step 5): the Content and Questions tabs of the tutor editor.
// Modules, topics (cleaned text, one YouTube video, pictures, private files), the question banks, and the tutor's comments on long answers.
// Only the owner and "Can edit" tutors may change anything (sst_course() decides, here and on every call); "Can view" tutors and admin may only read.
// A topic, module or question that students have already started or answered is ARCHIVED, never deleted, so no progress is lost.
require_once __DIR__ . '/SelfStudyTutor.php';
require_once __DIR__ . '/SelfStudyQuiz.php';

if (!class_exists('SscException')) {
    class SscException extends RuntimeException {}
}

if (!function_exists('ssc_guard')) {

    define('SSC_FILE_EXTS', ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'csv', 'zip', 'jpg', 'jpeg', 'png', 'webp']);
    define('SSC_FILE_MAX', 20 * 1024 * 1024);
    define('SSC_IMG_MAX_W', 1600);
    define('SSC_IMG_MAX_BYTES', 10 * 1024 * 1024);
    define('SSC_HTML_MAX', 400000);

    function ssc_installed(PDO $db): bool
    {
        try { $db->query('SELECT archived_at, edit_version, updated_by FROM self_study_topics WHERE 1 = 0'); $db->query('SELECT archived_at FROM self_study_questions WHERE 1 = 0');
              $db->query('SELECT tutor_comment, marked_at FROM self_study_answers WHERE 1 = 0'); $db->query('SELECT archived_at FROM self_study_modules WHERE 1 = 0'); return true; }
        catch (Throwable $e) { return false; }
    }

    /** The course, if this person may use it: $edit = true needs owner / "Can edit"; false is enough with "Can view" or admin. Otherwise an exception (404 / 403). */
    function ssc_guard(PDO $db, array $viewer, int $courseId, bool $edit = true): array
    {
        $c = sst_course($db, $viewer, $courseId);
        if (!$c) throw new SscException('That course was not found, or it was not shared with you.', 404);
        if ($edit && !$c['can_edit']) throw new SscException('You can open this course but not change it.', 403);
        return $c;
    }

    function ssc_clean_title(string $t, string $what = 'title'): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', strip_tags($t)));
        if ($t === '') throw new SscException('Please give the ' . $what . ' a name.', 422);
        if (mb_strlen($t) > 200) throw new SscException('The ' . $what . ' can have at most 200 letters.', 422);
        return $t;
    }

    /* ------------------------------ progress checks ------------------------------ */

    function ssc_topic_progress_ids(PDO $db, int $courseId): array
    {
        $ids = array_map('intval', array_column(ss2_rows($db, 'SELECT DISTINCT topic_id FROM self_study_topic_progress WHERE course_id = ?', [$courseId]), 'topic_id'));
        $v = array_map('intval', array_column(ss2_rows($db, 'SELECT DISTINCT v.topic_id FROM self_study_video_progress v JOIN self_study_topics t ON t.id = v.topic_id WHERE t.course_id = ?', [$courseId]), 'topic_id'));
        return array_values(array_unique(array_merge($ids, $v)));
    }

    function ssc_module_progress_ids(PDO $db, int $courseId, array $topicProgress): array
    {
        $out = [];
        foreach (ss2_rows($db, 'SELECT id, module_id FROM self_study_topics WHERE course_id = ? AND module_id IS NOT NULL', [$courseId]) as $t) if (in_array((int) $t['id'], $topicProgress, true)) $out[(int) $t['module_id']] = true;
        foreach (ss2_rows($db, "SELECT DISTINCT module_id FROM self_study_attempts WHERE course_id = ? AND kind = 'module_quiz' AND module_id IS NOT NULL", [$courseId]) as $r) $out[(int) $r['module_id']] = true;
        return array_keys($out);
    }

    /** Which of these questions students have answered, in two queries for the whole bank: id => true. */
    function ssc_answered_ids(PDO $db, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return [];
        $in = implode(',', $ids); $out = [];
        foreach (ss2_rows($db, "SELECT DISTINCT question_id FROM self_study_answers WHERE question_id IN ($in)") as $r) $out[(int) $r['question_id']] = true;
        foreach (ss2_rows($db, "SELECT DISTINCT question_id FROM self_study_attempt_answers WHERE question_id IN ($in)") as $r) $out[(int) $r['question_id']] = true;
        return $out;
    }

    function ssc_question_answered(PDO $db, int $questionId): bool
    {
        return (int) ss2_rows($db, 'SELECT COUNT(*) AS n FROM self_study_answers WHERE question_id = ?', [$questionId])[0]['n'] > 0
            || (int) ss2_rows($db, 'SELECT COUNT(*) AS n FROM self_study_attempt_answers WHERE question_id = ?', [$questionId])[0]['n'] > 0;
    }

    /* ------------------------------ the outline ------------------------------ */

    /** Everything the left side needs: counts per bank, numbering (2.1, 2.2 …), what has progress, and the archived items. */
    function ssc_outline(PDO $db, int $courseId): array
    {
        $set = ss3_course_settings($db, $courseId);
        $mods = ss2_rows($db, 'SELECT id, title, position FROM self_study_modules WHERE course_id = ? AND archived_at IS NULL ORDER BY position, id', [$courseId]);
        $tops = ss2_rows($db, 'SELECT id, module_id, title, position, is_intro, archived_at, video_id FROM self_study_topics WHERE course_id = ? ORDER BY position, id', [$courseId]);
        $cnt = ['topic' => [], 'module_quiz' => [], 'checkpoint_exam' => 0, 'final_exam' => 0, 'knowledge_check' => 0]; $usable = ['checkpoint_exam' => 0, 'final_exam' => 0, 'module_quiz' => []]; $untagged = ['module_quiz' => [], 'checkpoint_exam' => 0, 'final_exam' => 0];
        foreach (ss2_rows($db, 'SELECT place, module_id, topic_id, qtype, COUNT(*) AS n FROM self_study_questions WHERE course_id = ? AND archived_at IS NULL GROUP BY place, module_id, topic_id, qtype', [$courseId]) as $r) {
            $n = (int) $r['n']; $auto = $r['qtype'] !== 'long_answer';
            if ($r['place'] === 'topic') $cnt['topic'][(int) $r['topic_id']] = ($cnt['topic'][(int) $r['topic_id']] ?? 0) + $n;
            elseif ($r['place'] === 'module_quiz') { $m = (int) $r['module_id']; $cnt['module_quiz'][$m] = ($cnt['module_quiz'][$m] ?? 0) + $n; if ($auto) $usable['module_quiz'][$m] = ($usable['module_quiz'][$m] ?? 0) + $n; if ($r['topic_id'] === null) $untagged['module_quiz'][$m] = ($untagged['module_quiz'][$m] ?? 0) + $n; }
            elseif (isset($cnt[$r['place']])) { $cnt[$r['place']] += $n; if ($auto && isset($usable[$r['place']])) $usable[$r['place']] += $n; if ($r['topic_id'] === null && isset($untagged[$r['place']])) $untagged[$r['place']] += $n; }
        }
        $prog = ssc_topic_progress_ids($db, $courseId); $mprog = ssc_module_progress_ids($db, $courseId, $prog);
        $byMod = []; $intro = null; $archT = [];
        $modTitle = array_column(ss2_rows($db, 'SELECT id, title FROM self_study_modules WHERE course_id = ?', [$courseId]), 'title', 'id');
        foreach ($tops as $t) {
            if ($t['archived_at'] !== null) { $archT[] = ['id' => (int) $t['id'], 'title' => $t['title'], 'module' => $modTitle[$t['module_id']] ?? 'Course Introduction']; continue; }
            if ((int) $t['is_intro'] === 1) { $intro = ['id' => (int) $t['id'], 'title' => $t['title'], 'questions' => (int) ($cnt['topic'][(int) $t['id']] ?? 0), 'progress' => in_array((int) $t['id'], $prog, true), 'video' => !empty($t['video_id'])]; continue; }
            if ($t['module_id'] !== null) $byMod[(int) $t['module_id']][] = $t;
        }
        $out = [];
        foreach ($mods as $k => $m) {
            $mid = (int) $m['id']; $list = [];
            foreach ($byMod[$mid] ?? [] as $i => $t) $list[] = ['id' => (int) $t['id'], 'title' => $t['title'], 'number' => ($k + 1) . '.' . $i, 'questions' => (int) ($cnt['topic'][(int) $t['id']] ?? 0), 'progress' => in_array((int) $t['id'], $prog, true), 'video' => !empty($t['video_id'])];
            $out[] = ['id' => $mid, 'no' => $k + 1, 'title' => $m['title'], 'progress' => in_array($mid, $mprog, true), 'topics' => $list,
                      'quiz' => ['count' => (int) ($cnt['module_quiz'][$mid] ?? 0), 'usable' => (int) ($usable['module_quiz'][$mid] ?? 0), 'need' => $set['quiz_questions'], 'untagged' => (int) ($untagged['module_quiz'][$mid] ?? 0)]];
        }
        $archM = array_map(fn($m) => ['id' => (int) $m['id'], 'title' => $m['title']], ss2_rows($db, 'SELECT id, title FROM self_study_modules WHERE course_id = ? AND archived_at IS NOT NULL ORDER BY id', [$courseId]));
        return [
            'kb' => ['count' => $cnt['knowledge_check']], 'intro' => $intro, 'modules' => $out,
            'checkpoint' => ['count' => $cnt['checkpoint_exam'], 'usable' => $usable['checkpoint_exam'], 'need' => $set['checkpoint_questions'], 'untagged' => $untagged['checkpoint_exam']],
            'final' => ['count' => $cnt['final_exam'], 'usable' => $usable['final_exam'], 'need' => $set['final_questions'], 'untagged' => $untagged['final_exam']],
            'archived' => ['modules' => $archM, 'topics' => $archT],
        ];
    }

    /* ------------------------------ structure: add, rename, reorder, archive, delete ------------------------------ */

    /** Positions become 1..n in the order students meet them: the introduction, then every module with its topics. */
    function ssc_renumber(PDO $db, int $courseId): void
    {
        $order = [];
        foreach (ss2_rows($db, 'SELECT id FROM self_study_topics WHERE course_id = ? AND is_intro = 1 ORDER BY position, id', [$courseId]) as $t) $order[] = (int) $t['id'];
        $tops = []; foreach (ss2_rows($db, 'SELECT id, module_id FROM self_study_topics WHERE course_id = ? AND is_intro = 0 ORDER BY position, id', [$courseId]) as $t) $tops[(int) ($t['module_id'] ?? 0)][] = (int) $t['id'];
        foreach (ss2_rows($db, 'SELECT id FROM self_study_modules WHERE course_id = ? ORDER BY position, id', [$courseId]) as $m) foreach ($tops[(int) $m['id']] ?? [] as $t) $order[] = $t;
        $up = $db->prepare('UPDATE self_study_topics SET position = ? WHERE id = ? AND position <> ?');
        foreach ($order as $i => $id) $up->execute([$i + 1, $id, $i + 1]);
    }

    /** After any change to the structure: the module count on the cards, and every student's bar recalculated (their "last studied" date is not touched). */
    function ssc_after_change(PDO $db, int $courseId, ?int $now = null): void
    {
        $now ??= time();
        $db->prepare('UPDATE self_study_courses SET module_count = (SELECT COUNT(*) FROM self_study_modules WHERE course_id = ? AND archived_at IS NULL), updated_at = ? WHERE id = ?')->execute([$courseId, ss2_ts($now), $courseId]);
        $students = array_column(ss2_rows($db, 'SELECT student_id FROM self_study_progress WHERE course_id = ? LIMIT 5000', [$courseId]), 'student_id');
        foreach ($students as $sid) { try { ss2_save_progress($db, (int) $sid, $courseId, $now, null, true); } catch (Throwable $e) { error_log('selfstudy recalc: ' . $e->getMessage()); } }
    }

    function ssc_add_module(PDO $db, array $viewer, int $courseId, string $title, ?int $now = null): int
    {
        ssc_guard($db, $viewer, $courseId); $title = ssc_clean_title($title, 'module'); $ts = ss2_ts($now ?? time());
        $pos = 1 + (int) ss2_rows($db, 'SELECT COALESCE(MAX(position), 0) AS m FROM self_study_modules WHERE course_id = ?', [$courseId])[0]['m'];
        $db->prepare('INSERT INTO self_study_modules (course_id, title, position, created_at) VALUES (?, ?, ?, ?)')->execute([$courseId, $title, $pos, $ts]);
        $id = (int) $db->lastInsertId();
        ssc_after_change($db, $courseId, $now);
        return $id;
    }

    /** $moduleId null adds (or returns) the Course Introduction topic. */
    function ssc_add_topic(PDO $db, array $viewer, int $courseId, ?int $moduleId, string $title, ?int $now = null): int
    {
        ssc_guard($db, $viewer, $courseId); $ts = ss2_ts($now ?? time());
        if ($moduleId === null) {
            $ex = ss2_rows($db, 'SELECT id FROM self_study_topics WHERE course_id = ? AND is_intro = 1 AND archived_at IS NULL ORDER BY id LIMIT 1', [$courseId])[0] ?? null;
            if ($ex) return (int) $ex['id'];
            $db->prepare("INSERT INTO self_study_topics (course_id, module_id, title, position, content_html, is_intro, created_at, updated_at, updated_by, edit_version) VALUES (?, NULL, 'Course Introduction', 0, '', 1, ?, ?, ?, 1)")->execute([$courseId, $ts, $ts, $viewer['uid']]);
        } else {
            if (!ss2_rows($db, 'SELECT id FROM self_study_modules WHERE id = ? AND course_id = ? AND archived_at IS NULL', [$moduleId, $courseId])) throw new SscException('That module was not found.', 404);
            $title = ssc_clean_title($title, 'topic');
            $db->prepare("INSERT INTO self_study_topics (course_id, module_id, title, position, content_html, is_intro, created_at, updated_at, updated_by, edit_version) VALUES (?, ?, ?, 999999, '', 0, ?, ?, ?, 1)")->execute([$courseId, $moduleId, $title, $ts, $ts, $viewer['uid']]);
        }
        $id = (int) $db->lastInsertId();
        ssc_renumber($db, $courseId); ssc_after_change($db, $courseId, $now);
        return $id;
    }

    function ssc_rename(PDO $db, array $viewer, int $courseId, string $kind, int $id, string $title): void
    {
        ssc_guard($db, $viewer, $courseId); $title = ssc_clean_title($title, $kind === 'module' ? 'module' : 'topic');
        $t = $kind === 'module' ? 'self_study_modules' : 'self_study_topics';
        $st = $db->prepare("UPDATE $t SET title = ? WHERE id = ? AND course_id = ?"); $st->execute([$title, $id, $courseId]);
        if (!ss2_rows($db, "SELECT id FROM $t WHERE id = ? AND course_id = ?", [$id, $courseId])) throw new SscException('That item was not found.', 404);
    }

    /**
     * Drag and drop. kind 'module': $ids is the new order of the modules. kind 'topic': $ids is the new order of the topics of module $moduleId
     * (a topic dropped from another module moves into it). Numbers like 2.1 and 2.2 follow by themselves; students' bars are recalculated.
     */
    function ssc_reorder(PDO $db, array $viewer, int $courseId, string $kind, array $ids, ?int $moduleId = null, ?int $now = null): void
    {
        ssc_guard($db, $viewer, $courseId); $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($kind === 'module') {
            $have = array_map('intval', array_column(ss2_rows($db, 'SELECT id FROM self_study_modules WHERE course_id = ? AND archived_at IS NULL', [$courseId]), 'id'));
            if (count($ids) !== count($have) || array_diff($ids, $have)) throw new SscException('The module list is out of date. Reload the page.', 409);
            $up = $db->prepare('UPDATE self_study_modules SET position = ? WHERE id = ?'); foreach ($ids as $i => $id) $up->execute([$i + 1, $id]);
        } elseif ($kind === 'topic') {
            if (!$moduleId || !ss2_rows($db, 'SELECT id FROM self_study_modules WHERE id = ? AND course_id = ? AND archived_at IS NULL', [$moduleId, $courseId])) throw new SscException('That module was not found.', 404);
            $ok = array_map('intval', array_column(ss2_rows($db, 'SELECT id FROM self_study_topics WHERE course_id = ? AND is_intro = 0 AND archived_at IS NULL', [$courseId]), 'id'));
            if (array_diff($ids, $ok)) throw new SscException('The topic list is out of date. Reload the page.', 409);
            $up = $db->prepare('UPDATE self_study_topics SET module_id = ?, position = ? WHERE id = ?'); foreach ($ids as $i => $id) $up->execute([$moduleId, 500000 + $i, $id]);   // renumbered below
        } else throw new SscException('Unknown item.', 422);
        ssc_renumber($db, $courseId); ssc_after_change($db, $courseId, $now);
    }

    function ssc_archive(PDO $db, array $viewer, int $courseId, string $kind, int $id, ?int $now = null): void
    {
        ssc_guard($db, $viewer, $courseId); $t = $kind === 'module' ? 'self_study_modules' : 'self_study_topics';
        $db->prepare("UPDATE $t SET archived_at = ? WHERE id = ? AND course_id = ?")->execute([ss2_ts($now ?? time()), $id, $courseId]);
        ssc_renumber($db, $courseId); ssc_after_change($db, $courseId, $now);
    }

    function ssc_restore(PDO $db, array $viewer, int $courseId, string $kind, int $id, ?int $now = null): void
    {
        ssc_guard($db, $viewer, $courseId); $t = $kind === 'module' ? 'self_study_modules' : 'self_study_topics';
        if ($kind === 'topic') {                                      // a topic whose module is archived comes back into the first active module
            $tp = ss2_rows($db, 'SELECT module_id, is_intro FROM self_study_topics WHERE id = ? AND course_id = ?', [$id, $courseId])[0] ?? null;
            if ($tp && !(int) $tp['is_intro'] && !ss2_rows($db, 'SELECT id FROM self_study_modules WHERE id = ? AND archived_at IS NULL', [(int) $tp['module_id']])) {
                $m = ss2_rows($db, 'SELECT id FROM self_study_modules WHERE course_id = ? AND archived_at IS NULL ORDER BY position, id LIMIT 1', [$courseId])[0] ?? null;
                if (!$m) throw new SscException('Restore a module first.', 422);
                $db->prepare('UPDATE self_study_topics SET module_id = ? WHERE id = ?')->execute([$m['id'], $id]);
            }
        }
        $db->prepare("UPDATE $t SET archived_at = NULL WHERE id = ? AND course_id = ?")->execute([$id, $courseId]);
        ssc_renumber($db, $courseId); ssc_after_change($db, $courseId, $now);
    }

    function ssc_delete_files_of_topic(PDO $db, int $courseId, int $topicId): void
    {
        foreach (ss2_rows($db, 'SELECT id, file_path FROM self_study_files WHERE course_id = ? AND topic_id = ?', [$courseId, $topicId]) as $f) { @unlink(storage_path('selfstudy/' . ltrim((string) $f['file_path'], '/'))); }
        $db->prepare('DELETE FROM self_study_files WHERE course_id = ? AND topic_id = ?')->execute([$courseId, $topicId]);
    }

    /** Only an item no student has started. Anything else must be archived. */
    function ssc_delete(PDO $db, array $viewer, int $courseId, string $kind, int $id, ?int $now = null): void
    {
        ssc_guard($db, $viewer, $courseId);
        $prog = ssc_topic_progress_ids($db, $courseId);
        if ($kind === 'topic') {
            if (!ss2_rows($db, 'SELECT id FROM self_study_topics WHERE id = ? AND course_id = ?', [$id, $courseId])) throw new SscException('That topic was not found.', 404);
            if (in_array($id, $prog, true)) throw new SscException('Students have already started this topic, so it cannot be deleted. Archive it instead.', 409);
            ssc_delete_files_of_topic($db, $courseId, $id);
            $db->prepare("DELETE FROM self_study_questions WHERE course_id = ? AND place = 'topic' AND topic_id = ?")->execute([$courseId, $id]);
            $db->prepare('UPDATE self_study_questions SET topic_id = NULL WHERE course_id = ? AND topic_id = ?')->execute([$courseId, $id]);          // a quiz / exam question loses its tag (the bank warns)
            $db->prepare('DELETE FROM self_study_topics WHERE id = ? AND course_id = ?')->execute([$id, $courseId]);
        } elseif ($kind === 'module') {
            if (!ss2_rows($db, 'SELECT id FROM self_study_modules WHERE id = ? AND course_id = ?', [$id, $courseId])) throw new SscException('That module was not found.', 404);
            if (in_array($id, ssc_module_progress_ids($db, $courseId, $prog), true)) throw new SscException('Students have already started this module, so it cannot be deleted. Archive it instead.', 409);
            foreach (ss2_rows($db, 'SELECT id FROM self_study_topics WHERE course_id = ? AND module_id = ?', [$courseId, $id]) as $t) {
                ssc_delete_files_of_topic($db, $courseId, (int) $t['id']);
                $db->prepare("DELETE FROM self_study_questions WHERE course_id = ? AND place = 'topic' AND topic_id = ?")->execute([$courseId, $t['id']]);
                $db->prepare('UPDATE self_study_questions SET topic_id = NULL WHERE course_id = ? AND topic_id = ?')->execute([$courseId, $t['id']]);
            }
            $db->prepare("DELETE FROM self_study_questions WHERE course_id = ? AND place = 'module_quiz' AND module_id = ?")->execute([$courseId, $id]);
            $db->prepare('DELETE FROM self_study_topics WHERE course_id = ? AND module_id = ?')->execute([$courseId, $id]);
            $db->prepare('DELETE FROM self_study_modules WHERE id = ? AND course_id = ?')->execute([$id, $courseId]);
        } else throw new SscException('Unknown item.', 422);
        ssc_renumber($db, $courseId); ssc_after_change($db, $courseId, $now);
    }

    /* ------------------------------ a topic ------------------------------ */

    function ssc_normalize_images(string $html): string
    {
        // a picture of this site, whatever address the editor showed it under, is saved as uploads/selfstudy/…
        $html = (string) preg_replace('#(<img\b[^>]*?\bsrc=)(["\'])(?:https?:)?//[^"\']*?/(uploads/selfstudy/[A-Za-z0-9_./-]+)\2#i', '$1$2$3$2', $html);
        return $html;
    }

    /** The one cleaning every topic text goes through before it is saved: allow-list, and only pictures this site stored itself. */
    function ssc_clean_topic_html(string $html): string
    {
        if (strlen($html) > SSC_HTML_MAX) throw new SscException('This topic is too long to save. Please split it into two topics.', 422);
        $clean = ss_clean_html(ssc_normalize_images($html));
        return (string) preg_replace('#<img\b(?![^>]*\bsrc="uploads/selfstudy/)[^>]*>#i', '', $clean);
    }

    function ssc_video_seconds($v): ?int
    {
        $v = trim((string) $v); if ($v === '') return null;
        if (preg_match('/^(\d{1,3}):([0-5]?\d)$/', $v, $m)) $s = (int) $m[1] * 60 + (int) $m[2];
        elseif (ctype_digit($v)) $s = (int) $v; else throw new SscException('Write the video length as minutes:seconds, for example 5:58.', 422);
        if ($s < 10 || $s > 43200) throw new SscException('The video length looks wrong. Write it as minutes:seconds, for example 5:58.', 422);
        return $s;
    }

    function ssc_user_name(PDO $db, ?int $uid): string
    {
        return $uid ? ss3_student_name($db, $uid) : '';
    }

    /** One topic for the editor: the saved (already cleaned) text, the video, the files and the version. */
    function ssc_load_topic(PDO $db, array $viewer, int $courseId, int $topicId): array
    {
        ssc_guard($db, $viewer, $courseId, false);
        $t = ss2_rows($db, 'SELECT id, title, content_html, video_id, video_duration, is_intro, edit_version, updated_at, updated_by, archived_at FROM self_study_topics WHERE id = ? AND course_id = ?', [$topicId, $courseId])[0] ?? null;
        if (!$t) throw new SscException('That topic was not found.', 404);
        $files = array_map(fn($f) => ['id' => (int) $f['id'], 'name' => $f['file_name'], 'size' => (int) $f['file_size']], ss2_rows($db, 'SELECT id, file_name, file_size FROM self_study_files WHERE course_id = ? AND topic_id = ? ORDER BY id', [$courseId, $topicId]));
        $by = (int) ($t['updated_by'] ?? 0) !== (int) $viewer['uid'] ? ssc_user_name($db, $t['updated_by'] !== null ? (int) $t['updated_by'] : null) : '';
        $vid = ss_youtube_id($t['video_id']);
        return ['id' => (int) $t['id'], 'title' => $t['title'], 'is_intro' => (bool) $t['is_intro'], 'archived' => $t['archived_at'] !== null,
                'html' => str_replace('src="uploads/', 'src="' . url('uploads/'), ssc_clean_topic_html((string) $t['content_html'])),      // cleaned again, so nothing unsafe saved by any other route can run in the editor
                'video_id' => $vid, 'thumb' => $vid ? 'https://i.ytimg.com/vi/' . $vid . '/mqdefault.jpg' : null, 'video_length' => $t['video_duration'] ? intdiv((int) $t['video_duration'], 60) . ':' . str_pad((string) ((int) $t['video_duration'] % 60), 2, '0', STR_PAD_LEFT) : '',
                'version' => (int) $t['edit_version'], 'updated_at' => ss2_parse($t['updated_at']), 'updated_by_other' => $by, 'files' => $files];
    }

    /**
     * Save (and autosave) a topic. Cleaned on the server with the allow-list, so what is saved is exactly what students see.
     * If someone else saved it since this tutor opened it, nothing is overwritten: a conflict is returned (unless $force).
     * @return array{ok:bool, conflict?:bool, version?:int, saved_at?:int, by?:string, error?:string}
     */
    function ssc_save_topic(PDO $db, array $viewer, int $courseId, int $topicId, array $in, ?int $now = null): array
    {
        ssc_guard($db, $viewer, $courseId); $now ??= time();
        $cur = ss2_rows($db, 'SELECT id, edit_version, updated_by, updated_at, is_intro FROM self_study_topics WHERE id = ? AND course_id = ?', [$topicId, $courseId])[0] ?? null;
        if (!$cur) throw new SscException('That topic was not found.', 404);
        $title = (int) $cur['is_intro'] ? 'Course Introduction' : ssc_clean_title((string) ($in['title'] ?? ''), 'topic');
        $html = ssc_clean_topic_html((string) ($in['html'] ?? ''));
        $vidIn = trim((string) ($in['video'] ?? ''));
        $vid = null; if ($vidIn !== '') { $vid = ss_youtube_id($vidIn); if (!$vid) throw new SscException('Only YouTube links are accepted. Paste the address of the video from youtube.com or youtu.be.', 422); }
        $len = $vid ? ssc_video_seconds($in['video_length'] ?? '') : null;
        $force = !empty($in['force']); $ts = ss2_ts($now);
        $sql = 'UPDATE self_study_topics SET title = ?, content_html = ?, video_id = ?, video_duration = ?, edit_version = edit_version + 1, updated_at = ?, updated_by = ? WHERE id = ? AND course_id = ?';
        $args = [$title, $html, $vid, $len, $ts, $viewer['uid'], $topicId, $courseId];
        if (!$force) { $sql .= ' AND edit_version = ?'; $args[] = (int) ($in['version'] ?? 0); }
        $st = $db->prepare($sql); $st->execute($args);
        if ($st->rowCount() === 0) {
            $now2 = ss2_rows($db, 'SELECT edit_version, updated_by, updated_at FROM self_study_topics WHERE id = ?', [$topicId])[0];
            return ['ok' => false, 'conflict' => true, 'version' => (int) $now2['edit_version'], 'saved_at' => ss2_parse($now2['updated_at']), 'by' => ssc_user_name($db, (int) $now2['updated_by']),
                    'error' => 'Another tutor saved this topic after you opened it.'];
        }
        $v = (int) ss2_rows($db, 'SELECT edit_version FROM self_study_topics WHERE id = ?', [$topicId])[0]['edit_version'];
        $db->prepare('UPDATE self_study_courses SET updated_at = ? WHERE id = ?')->execute([$ts, $courseId]);
        return ['ok' => true, 'version' => $v, 'saved_at' => $now, 'title' => $title, 'video_id' => $vid];
    }

    /* ------------------------------ pictures and files ------------------------------ */

    /**
     * A picture for a topic text: checked like the cover (real type with the image library, JPG/PNG/WebP, up to 10 MB), hidden data removed by re-encoding,
     * never enlarged, at most 1600 px wide (sharp on the page and on retina phones), saved as WebP.
     * @return array{ok:bool,path?:string,url?:string,width?:int,height?:int,error?:string}
     */
    function ssc_upload_image(PDO $db, array $viewer, int $courseId, ?array $file, ?int $now = null): array
    {
        ssc_guard($db, $viewer, $courseId);
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['ok' => false, 'error' => 'No picture was chosen.'];
        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($file['size'] ?? 0) > SSC_IMG_MAX_BYTES) return ['ok' => false, 'error' => 'The picture is larger than 10 MB.'];
        if ($file['error'] !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => 'The picture did not upload. Please try again.'];
        if (!defined('UPLOAD_TEST_MODE') && !is_uploaded_file($file['tmp_name'])) return ['ok' => false, 'error' => 'Security check failed on the uploaded file.'];
        $o = sst_open_image($file['tmp_name']);
        if (is_string($o)) return ['ok' => false, 'error' => $o];
        [$img, , $w, $h] = $o;
        if ($w > SSC_IMG_MAX_W) {
            $nh = (int) round($h * SSC_IMG_MAX_W / $w);
            $copy = imagecreatetruecolor(SSC_IMG_MAX_W, $nh); imagealphablending($copy, false); imagesavealpha($copy, true);
            imagefill($copy, 0, 0, imagecolorallocatealpha($copy, 255, 255, 255, 127));
            imagecopyresampled($copy, $img, 0, 0, 0, 0, SSC_IMG_MAX_W, $nh, $w, $h);
            imagedestroy($img); $img = $copy; $w = SSC_IMG_MAX_W; $h = $nh;
        }
        $dir = public_path('uploads/selfstudy/' . $courseId);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) { imagedestroy($img); return ['ok' => false, 'error' => 'The picture folder could not be created.']; }
        $name = 'topic-' . bin2hex(random_bytes(6)) . '.webp';
        imagesavealpha($img, true);
        $ok = @imagewebp($img, $dir . '/' . $name, 85); imagedestroy($img);
        if (!$ok) return ['ok' => false, 'error' => 'The picture could not be saved.'];
        $rel = 'uploads/selfstudy/' . $courseId . '/' . $name;
        return ['ok' => true, 'path' => $rel, 'url' => url($rel), 'width' => $w, 'height' => $h];
    }

    /** A light check that the file really is what its name says. */
    function ssc_file_sniff(string $path, string $ext): bool
    {
        $head = (string) @file_get_contents($path, false, null, 0, 4096);
        if ($head === '') return false;
        return match ($ext) {
            'pdf' => str_starts_with($head, '%PDF-'),
            'jpg', 'jpeg', 'png', 'webp' => (bool) @getimagesize($path),
            'zip', 'docx', 'pptx', 'xlsx' => str_starts_with($head, 'PK'),
            'doc', 'ppt', 'xls' => str_starts_with($head, "\xD0\xCF\x11\xE0"),
            default => !str_contains($head, "\0"),                    // txt, csv: plain text
        };
    }

    /** Attach a file to a topic. Stored privately (app/v1/storage/selfstudy) under a random name; students download it through file.php, which checks their access. */
    function ssc_attach_file(PDO $db, array $viewer, int $courseId, int $topicId, ?array $file, ?int $now = null): array
    {
        ssc_guard($db, $viewer, $courseId);
        if (!ss2_rows($db, 'SELECT id FROM self_study_topics WHERE id = ? AND course_id = ?', [$topicId, $courseId])) throw new SscException('That topic was not found.', 404);
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['ok' => false, 'error' => 'No file was chosen.'];
        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($file['size'] ?? 0) > SSC_FILE_MAX) return ['ok' => false, 'error' => 'The file is larger than 20 MB.'];
        if ($file['error'] !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => 'The file did not upload. Please try again.'];
        if (!defined('UPLOAD_TEST_MODE') && !is_uploaded_file($file['tmp_name'])) return ['ok' => false, 'error' => 'Security check failed on the uploaded file.'];
        $orig = basename(str_replace('\\', '/', (string) $file['name']));
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, SSC_FILE_EXTS, true)) return ['ok' => false, 'error' => 'Allowed files: ' . implode(', ', SSC_FILE_EXTS) . '.'];
        if (!ssc_file_sniff($file['tmp_name'], $ext)) return ['ok' => false, 'error' => 'That file does not look like a real ' . strtoupper($ext) . ' file.'];
        $display = mb_substr(preg_replace('/[^\p{L}\p{N}._ ()-]+/u', '_', pathinfo($orig, PATHINFO_FILENAME)), 0, 120) . '.' . $ext;
        $dir = storage_path('selfstudy/' . $courseId);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return ['ok' => false, 'error' => 'The file folder could not be created.'];
        $rel = $courseId . '/' . bin2hex(random_bytes(10)) . '.' . $ext;
        $moved = defined('UPLOAD_TEST_MODE') ? @copy($file['tmp_name'], storage_path('selfstudy/' . $rel)) : @move_uploaded_file($file['tmp_name'], storage_path('selfstudy/' . $rel));
        if (!$moved) return ['ok' => false, 'error' => 'The file could not be saved.'];
        $db->prepare('INSERT INTO self_study_files (course_id, topic_id, file_name, file_path, file_size, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$courseId, $topicId, $display, $rel, (int) $file['size'], $viewer['uid'], ss2_ts($now ?? time())]);
        return ['ok' => true, 'file' => ['id' => (int) $db->lastInsertId(), 'name' => $display, 'size' => (int) $file['size']]];
    }

    function ssc_remove_file(PDO $db, array $viewer, int $courseId, int $fileId): void
    {
        ssc_guard($db, $viewer, $courseId);
        $f = ss2_rows($db, 'SELECT id, file_path FROM self_study_files WHERE id = ? AND course_id = ?', [$fileId, $courseId])[0] ?? null;
        if (!$f) throw new SscException('That file was not found.', 404);
        @unlink(storage_path('selfstudy/' . ltrim((string) $f['file_path'], '/')));
        $db->prepare('DELETE FROM self_study_files WHERE id = ?')->execute([$fileId]);
    }

    /** A tutor's download of an attached file (same private folder, checked against the course the tutor may open). */
    function ssc_file_for_tutor(PDO $db, array $viewer, int $fileId): ?array
    {
        $f = ss2_rows($db, 'SELECT id, course_id, file_name, file_path FROM self_study_files WHERE id = ?', [$fileId])[0] ?? null;
        if (!$f || !sst_course($db, $viewer, (int) $f['course_id'])) return null;
        $base = realpath(storage_path('selfstudy')); $abs = $base ? realpath($base . '/' . ltrim(str_replace('\\', '/', (string) $f['file_path']), '/')) : false;
        if (!$abs || !is_file($abs) || strncmp($abs, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) !== 0) return null;
        $f['abs'] = $abs;
        return $f;
    }

    /* ------------------------------ question banks ------------------------------ */

    /** 'topic:12', 'kb', 'module:3', 'checkpoint', 'final'  ->  [place, topic_id, module_id, label] */
    function ssc_bank(PDO $db, int $courseId, string $key): array
    {
        if (preg_match('/^topic:(\d+)$/', $key, $m)) {
            $t = ss2_rows($db, 'SELECT id, title FROM self_study_topics WHERE id = ? AND course_id = ?', [(int) $m[1], $courseId])[0] ?? null;
            if (!$t) throw new SscException('That topic was not found.', 404);
            return ['topic', (int) $t['id'], null, 'Topic questions: ' . $t['title']];
        }
        if (preg_match('/^module:(\d+)$/', $key, $m)) {
            $t = ss2_rows($db, 'SELECT id, title FROM self_study_modules WHERE id = ? AND course_id = ?', [(int) $m[1], $courseId])[0] ?? null;
            if (!$t) throw new SscException('That module was not found.', 404);
            return ['module_quiz', null, (int) $t['id'], 'Module quiz: ' . $t['title']];
        }
        return match ($key) {
            'kb' => ['knowledge_check', null, null, 'Knowledge Check'], 'checkpoint' => ['checkpoint_exam', null, null, 'Checkpoint Exam'], 'final' => ['final_exam', null, null, 'Final Exam'],
            default => throw new SscException('Unknown question bank.', 422),
        };
    }

    function ssc_allowed_types(string $place): array
    {
        return $place === 'topic' ? ['multiple_choice', 'short_answer', 'long_answer'] : ($place === 'knowledge_check' ? ['multiple_choice'] : ['multiple_choice', 'short_answer']);
    }

    /** The questions of one bank, with the answers (tutors see them), how many students answered, and the warning when the bank is too small. */
    function ssc_questions(PDO $db, array $viewer, int $courseId, string $key): array
    {
        ssc_guard($db, $viewer, $courseId, false);
        [$place, $topicId, $moduleId, $label] = ssc_bank($db, $courseId, $key);
        $sql = 'SELECT id, qtype, question_text, options_json, correct_index, accepted_answers, topic_id, position FROM self_study_questions WHERE course_id = ? AND place = ? AND archived_at IS NULL';
        $args = [$courseId, $place];
        if ($place === 'topic') { $sql .= ' AND topic_id = ?'; $args[] = $topicId; }
        if ($place === 'module_quiz') { $sql .= ' AND module_id = ?'; $args[] = $moduleId; }
        $rows = ss2_rows($db, $sql . ' ORDER BY position, id', $args);
        $qs = []; $answeredIds = ssc_answered_ids($db, array_column($rows, 'id'));
        foreach ($rows as $r) {
            $acc = json_decode((string) $r['accepted_answers'], true);
            $qs[] = ['id' => (int) $r['id'], 'type' => $r['qtype'], 'text' => $r['question_text'], 'options' => ss2_options($r['options_json']), 'correct' => $r['correct_index'] !== null ? (int) $r['correct_index'] : null,
                     'accepted' => is_array($acc) ? array_values(array_map('strval', $acc)) : [], 'tag' => $r['topic_id'] !== null && $place !== 'topic' ? (int) $r['topic_id'] : null, 'answered' => isset($answeredIds[(int) $r['id']])];
        }
        $set = ss3_course_settings($db, $courseId);
        $need = ['module_quiz' => $set['quiz_questions'], 'checkpoint_exam' => $set['checkpoint_questions'], 'final_exam' => $set['final_questions']][$place] ?? null;
        $usable = count(array_filter($qs, fn($q) => $q['type'] !== 'long_answer'));
        $warn = null;
        if ($need !== null && $usable < $need) $warn = 'This ' . ($place === 'module_quiz' ? 'quiz' : 'exam') . ' needs ' . $need . ' questions and has ' . $usable . '.';
        $untagged = $place === 'topic' || $place === 'knowledge_check' ? 0 : count(array_filter($qs, fn($q) => $q['tag'] === null));
        return ['bank' => $key, 'place' => $place, 'label' => $label, 'questions' => $qs, 'allowed' => ssc_allowed_types($place), 'needs_tag' => in_array($place, ['module_quiz', 'checkpoint_exam', 'final_exam'], true),
                'need' => $need, 'warning' => $warn, 'untagged' => $untagged, 'draw' => $place === 'topic' ? SS2_TOPIC_QUESTIONS : null,
                'tags' => ssc_tag_topics($db, $courseId, $place === 'module_quiz' ? $moduleId : null)];
    }

    /** The topics a quiz / exam question can be tagged with ("2.1 Reading CSV files"). */
    function ssc_tag_topics(PDO $db, int $courseId, ?int $onlyModule): array
    {
        $out = [];
        foreach (ssc_outline($db, $courseId)['modules'] as $m) {
            if ($onlyModule !== null && $m['id'] !== $onlyModule) continue;
            foreach ($m['topics'] as $t) $out[] = ['id' => $t['id'], 'label' => $t['number'] . ' ' . $t['title']];
        }
        return $out;
    }

    /**
     * Add or edit a question. Rules: multiple choice has 2 to 6 options and one correct; short answer has accepted answers; long answer only in topic questions;
     * the Knowledge Check is multiple choice; a quiz or exam question must be tagged with the topic it comes from.
     */
    function ssc_save_question(PDO $db, array $viewer, int $courseId, string $key, array $in, ?int $now = null): int
    {
        ssc_guard($db, $viewer, $courseId); $ts = ss2_ts($now ?? time());
        [$place, $topicId, $moduleId] = ssc_bank($db, $courseId, $key);
        $type = (string) ($in['type'] ?? '');
        if (!in_array($type, ['multiple_choice', 'short_answer', 'long_answer'], true)) throw new SscException('Choose a question type.', 422);
        if (!in_array($type, ssc_allowed_types($place), true)) throw new SscException($type === 'long_answer' ? 'Long answers are only allowed in topic questions, because quizzes, exams and the Knowledge Check are marked automatically.' : 'The Knowledge Check can only have multiple choice questions.', 422);
        $text = trim(preg_replace('/[ \t]+/u', ' ', strip_tags((string) ($in['text'] ?? ''))));
        if ($text === '') throw new SscException('Please write the question.', 422);
        if (mb_strlen($text) > 1000) throw new SscException('The question can have at most 1000 letters.', 422);
        $options = null; $correct = null; $accepted = null;
        if ($type === 'multiple_choice') {
            $raw = is_array($in['options'] ?? null) ? $in['options'] : [];
            $options = array_map(fn($o) => trim(preg_replace('/\s+/u', ' ', strip_tags((string) $o))), $raw);
            $correct = (int) ($in['correct'] ?? -1);
            if (count($options) < 2 || count($options) > 6) throw new SscException('A multiple choice question needs 2 to 6 options.', 422);
            foreach ($options as $o) { if ($o === '') throw new SscException('Every option needs some text. Remove the empty ones.', 422); if (mb_strlen($o) > 200) throw new SscException('An option can have at most 200 letters.', 422); }
            if (count(array_unique(array_map('mb_strtolower', $options))) !== count($options)) throw new SscException('Two options are the same. Each option must be different.', 422);
            if ($correct < 0 || $correct >= count($options)) throw new SscException('Choose which option is the correct one.', 422);
        } elseif ($type === 'short_answer') {
            $seen = []; $accepted = [];
            foreach ((is_array($in['accepted'] ?? null) ? $in['accepted'] : []) as $a) {
                $a = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $a))); if ($a === '') continue;
                if (mb_strlen($a) > 200) throw new SscException('An accepted answer can have at most 200 letters.', 422);
                if (isset($seen[ss2_norm($a)])) continue; $seen[ss2_norm($a)] = true; $accepted[] = $a;
            }
            if (!$accepted) throw new SscException('Add at least one accepted answer.', 422);
            if (count($accepted) > 20) throw new SscException('A short answer can have at most 20 accepted answers.', 422);
        }
        $tag = null;
        if (in_array($place, ['module_quiz', 'checkpoint_exam', 'final_exam'], true)) {
            $tag = (int) ($in['tag'] ?? 0);
            $ok = $tag ? ss2_rows($db, 'SELECT t.id, t.module_id FROM self_study_topics t WHERE t.id = ? AND t.course_id = ? AND t.is_intro = 0 AND t.archived_at IS NULL', [$tag, $courseId])[0] ?? null : null;
            if (!$ok || ($place === 'module_quiz' && (int) $ok['module_id'] !== $moduleId)) throw new SscException('Choose the topic this question comes from' . ($place === 'module_quiz' ? ' (a topic of this module).' : '.'), 422);
        }
        $topicCol = $place === 'topic' ? $topicId : $tag;
        $qid = (int) ($in['id'] ?? 0);
        if ($qid) {
            $own = ss2_rows($db, 'SELECT id FROM self_study_questions WHERE id = ? AND course_id = ? AND place = ? AND archived_at IS NULL', [$qid, $courseId, $place])[0] ?? null;
            if (!$own) throw new SscException('That question was not found in this bank.', 404);
            $db->prepare('UPDATE self_study_questions SET qtype = ?, question_text = ?, options_json = ?, correct_index = ?, accepted_answers = ?, topic_id = ?, updated_at = ? WHERE id = ?')
               ->execute([$type, $text, $options !== null ? json_encode($options, JSON_UNESCAPED_UNICODE) : null, $correct, $accepted !== null ? json_encode($accepted, JSON_UNESCAPED_UNICODE) : null, $topicCol, $ts, $qid]);
        } else {
            $pos = 1 + (int) ss2_rows($db, 'SELECT COALESCE(MAX(position), 0) AS m FROM self_study_questions WHERE course_id = ? AND place = ?', [$courseId, $place])[0]['m'];
            $db->prepare('INSERT INTO self_study_questions (course_id, place, topic_id, module_id, qtype, question_text, options_json, correct_index, accepted_answers, position, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
               ->execute([$courseId, $place, $topicCol, $moduleId, $type, $text, $options !== null ? json_encode($options, JSON_UNESCAPED_UNICODE) : null, $correct, $accepted !== null ? json_encode($accepted, JSON_UNESCAPED_UNICODE) : null, $pos, $ts, $ts]);
            $qid = (int) $db->lastInsertId();
        }
        $db->prepare('UPDATE self_study_courses SET updated_at = ? WHERE id = ?')->execute([$ts, $courseId]);
        return $qid;
    }

    /** @return bool true when it was archived (students had answered it), false when it was deleted */
    function ssc_delete_question(PDO $db, array $viewer, int $courseId, int $questionId, ?int $now = null): bool
    {
        ssc_guard($db, $viewer, $courseId);
        if (!ss2_rows($db, 'SELECT id FROM self_study_questions WHERE id = ? AND course_id = ?', [$questionId, $courseId])) throw new SscException('That question was not found.', 404);
        if (ssc_question_answered($db, $questionId)) { $db->prepare('UPDATE self_study_questions SET archived_at = ? WHERE id = ?')->execute([ss2_ts($now ?? time()), $questionId]); return true; }
        $db->prepare('DELETE FROM self_study_questions WHERE id = ?')->execute([$questionId]);
        return false;
    }

    function ssc_reorder_questions(PDO $db, array $viewer, int $courseId, string $key, array $ids): void
    {
        ssc_guard($db, $viewer, $courseId);
        [$place, $topicId, $moduleId] = ssc_bank($db, $courseId, $key);
        $sql = 'SELECT id FROM self_study_questions WHERE course_id = ? AND place = ? AND archived_at IS NULL'; $args = [$courseId, $place];
        if ($place === 'topic') { $sql .= ' AND topic_id = ?'; $args[] = $topicId; } if ($place === 'module_quiz') { $sql .= ' AND module_id = ?'; $args[] = $moduleId; }
        $have = array_map('intval', array_column(ss2_rows($db, $sql, $args), 'id')); $ids = array_values(array_unique(array_map('intval', $ids)));
        if (count($ids) !== count($have) || array_diff($ids, $have)) throw new SscException('The question list is out of date. Reload the page.', 409);
        $up = $db->prepare('UPDATE self_study_questions SET position = ? WHERE id = ?'); foreach ($ids as $i => $id) $up->execute([$i + 1, $id]);
    }

    /* ------------------------------ long answers to mark ------------------------------ */

    /** Students' long answers, the ones not yet commented first. The topic never waits for this. */
    function ssc_answers_to_mark(PDO $db, array $viewer, int $courseId): array
    {
        ssc_guard($db, $viewer, $courseId, false);
        $rows = ss2_rows($db, "SELECT a.id, a.student_id, a.answer, a.answered_at, a.tutor_comment, a.marked_at, q.question_text, t.title AS topic_title
                               FROM self_study_answers a JOIN self_study_questions q ON q.id = a.question_id AND q.qtype = 'long_answer'
                               LEFT JOIN self_study_topics t ON t.id = q.topic_id
                               WHERE a.course_id = ? ORDER BY (CASE WHEN a.marked_at IS NULL THEN 0 ELSE 1 END), a.answered_at DESC, a.id DESC LIMIT 300", [$courseId]);
        $names = ss3_student_names($db, array_column($rows, 'student_id')); foreach ($rows as &$r) { $sid = (int) $r['student_id']; $r['student'] = $names[$sid] ?? 'A student'; $r['id'] = (int) $r['id']; $r['answered_at'] = ss2_parse($r['answered_at']); $r['marked'] = $r['marked_at'] !== null; }
        return $rows;
    }

    function ssc_mark_answer(PDO $db, array $viewer, int $courseId, int $answerId, string $comment, ?int $now = null): void
    {
        ssc_guard($db, $viewer, $courseId);
        $comment = trim($comment);
        if ($comment === '') throw new SscException('Please write a short comment for the student.', 422);
        if (mb_strlen($comment) > 1000) throw new SscException('The comment can have at most 1000 letters.', 422);
        $a = ss2_rows($db, "SELECT a.id, a.student_id FROM self_study_answers a JOIN self_study_questions q ON q.id = a.question_id AND q.qtype = 'long_answer' WHERE a.id = ? AND a.course_id = ?", [$answerId, $courseId])[0] ?? null;
        if (!$a) throw new SscException('That answer was not found.', 404);
        $db->prepare('UPDATE self_study_answers SET tutor_comment = ?, marked_at = ?, marked_by = ? WHERE id = ?')->execute([$comment, ss2_ts($now ?? time()), $viewer['uid'], $answerId]);
        $c = ss2_rows($db, 'SELECT title FROM self_study_courses WHERE id = ?', [$courseId])[0]['title'] ?? 'Self-study';
        ss3_notify([(int) $a['student_id']], 'grade', 'Your tutor commented on your answer', 'Course: ' . $c, 'students/selfstudy/course.php?id=' . $courseId, $db);
    }
}
