<?php
// app/v1/services/SelfStudyTutor.php — Self-study, the tutor side (step 4): who may do what, the course Details, the cover image, publishing,
// duplicating, archiving, deleting and moving ownership. Every rule is checked HERE, on the server; the screens only show what this file allows.
//
//   owner / "Can edit" tutor : change the course, publish, archive, duplicate, delete a draft that has no progress
//   "Can view" tutor         : only open it (and preview it)
//   admin                    : see every course, unpublish it, move its ownership to another tutor (cannot edit details)
//   students                 : never see a draft or an archived course (enforced in SelfStudy.php: published + shared + not removed)
require_once __DIR__ . '/SelfStudyStudy.php';
require_once __DIR__ . '/SelfStudyQuiz.php';

if (!function_exists('sst_viewer')) {

    define('SST_MINUTES', [20, 30, 45, 60, 90, 120]);
    define('SST_COVER_MIN_WIDTH', 1200);
    define('SST_COVER_MAX_BYTES', 10 * 1024 * 1024);

    function sst_installed(PDO $db): bool
    {
        try { $db->query('SELECT archived_at, crop_x, copied_from_id, created_by_user_id, checkpoint_minutes FROM self_study_courses WHERE 1 = 0'); $db->query('SELECT 1 FROM self_study_course_editors WHERE 1 = 0'); return true; }
        catch (Throwable $e) { return false; }
    }

    /**
     * Who is looking. $portal is 'tutor' or 'admin'. Returns null when this person may not use that portal's Self-study.
     * @return array{uid:int, role:string, tutor_id:?int, name:string}|null
     */
    function sst_viewer(PDO $db, string $portal): ?array
    {
        $uid = (int) ($_SESSION['user_id'] ?? 0); $role = (string) ($_SESSION['role'] ?? '');
        if ($uid <= 0) return null;
        $name = (string) (ss2_rows($db, "SELECT COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, ''))), ''), u.username) AS n FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id WHERE u.id = ?", [$uid])[0]['n'] ?? 'User');
        if ($portal === 'admin') return in_array($role, ['admin', 'superadmin'], true) ? ['uid' => $uid, 'role' => 'admin', 'tutor_id' => null, 'name' => $name] : null;
        if ($role !== 'tutor') return null;
        $t = ss2_rows($db, 'SELECT id FROM tutors WHERE user_id = ? AND is_active = 1', [$uid])[0] ?? null;
        return $t ? ['uid' => $uid, 'role' => 'tutor', 'tutor_id' => (int) $t['id'], 'name' => $name] : null;
    }

    /** A course this person may open, with their level on it: owner, edit, view (tutors) or admin. Null = no access at all (the page answers 404). */
    function sst_course(PDO $db, array $viewer, int $courseId): ?array
    {
        if ($courseId <= 0) return null;
        $c = ss2_rows($db, 'SELECT c.*, e.access AS editor_access FROM self_study_courses c LEFT JOIN self_study_course_editors e ON e.course_id = c.id AND e.tutor_id = ? WHERE c.id = ?', [(int) ($viewer['tutor_id'] ?? 0), $courseId])[0] ?? null;
        if (!$c) return null;
        if ($viewer['role'] === 'admin') $level = 'admin';
        elseif ((int) $c['owner_tutor_id'] === (int) $viewer['tutor_id']) $level = 'owner';
        elseif ($c['editor_access'] === 'edit') $level = 'edit';
        elseif ($c['editor_access'] === 'view') $level = 'view';
        else return null;
        $c['access'] = $level;
        $c['can_edit'] = in_array($level, ['owner', 'edit'], true);
        return $c;
    }

    /** The course list for the tutor (own + shared) or admin (every course with the owner's name). One query. */
    function sst_list(PDO $db, array $viewer): array
    {
        $sql = "SELECT c.id, c.title, c.short_description, c.level, c.status, c.updated_at, c.owner_tutor_id,
                       c.cover_original, c.cover_400, c.cover_800, c.cover_1600, e.access AS editor_access,
                       (SELECT COUNT(*) FROM self_study_modules m WHERE m.course_id = c.id AND m.archived_at IS NULL) AS modules,
                       (SELECT COUNT(*) FROM self_study_access a WHERE a.course_id = c.id AND a.removed_at IS NULL) AS students,
                       COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, ''))), ''), u.username, 'Unknown') AS owner_name,
                       COALESCE(NULLIF(TRIM(CONCAT(COALESCE(sup.first_name, ''), ' ', COALESCE(sup.last_name, ''))), ''), su.username) AS shared_by
                FROM self_study_courses c
                LEFT JOIN self_study_course_editors e ON e.course_id = c.id AND e.tutor_id = ?
                LEFT JOIN tutors t ON t.id = c.owner_tutor_id
                LEFT JOIN users u ON u.id = t.user_id
                LEFT JOIN user_profiles up ON up.user_id = u.id
                LEFT JOIN users su ON su.id = e.given_by
                LEFT JOIN user_profiles sup ON sup.user_id = su.id";
        $args = [(int) ($viewer['tutor_id'] ?? 0)];
        if ($viewer['role'] !== 'admin') { $sql .= ' WHERE c.owner_tutor_id = ? OR e.id IS NOT NULL'; $args[] = (int) $viewer['tutor_id']; }
        $rows = ss2_rows($db, $sql . ' ORDER BY c.updated_at DESC, c.id DESC', $args);
        foreach ($rows as &$r) {
            $r['mine'] = $viewer['role'] === 'tutor' && (int) $r['owner_tutor_id'] === (int) $viewer['tutor_id'];
            $r['access_label'] = $viewer['role'] === 'admin' ? '' : ($r['mine'] ? 'Owner' : ($r['editor_access'] === 'edit' ? 'Can edit' : 'Can view'));
        }
        return $rows;
    }

    /** True once a student has any progress: an opened course, a finished topic, an attempt or an answer. Such a course can only be archived. */
    function sst_has_progress(PDO $db, int $courseId): bool
    {
        foreach (['self_study_progress', 'self_study_topic_progress', 'self_study_answers'] as $t) {
            if ((int) ss2_rows($db, "SELECT COUNT(*) AS n FROM $t WHERE course_id = ?", [$courseId])[0]['n'] > 0) return true;
        }
        try { if ((int) ss2_rows($db, 'SELECT COUNT(*) AS n FROM self_study_attempts WHERE course_id = ?', [$courseId])[0]['n'] > 0) return true; } catch (Throwable $e) {}
        return false;
    }

    /* ------------------------------ details ------------------------------ */

    /**
     * Save the Details tab. Creates the draft when $courseId is null (the creator becomes the owner).
     * @return array{ok:bool, id?:int, error?:string}
     */
    function sst_save_details(PDO $db, array $viewer, ?int $courseId, array $in, ?int $now = null): array
    {
        $now ??= time();
        $title = trim(preg_replace('/\s+/u', ' ', (string) ($in['title'] ?? '')));
        $desc = trim(preg_replace('/\s+/u', ' ', (string) ($in['short_description'] ?? '')));
        $level = (string) ($in['level'] ?? 'beginner');
        $cp = (int) ($in['checkpoint_minutes'] ?? 45); $fn = (int) ($in['final_minutes'] ?? 60);
        if ($title === '') return ['ok' => false, 'error' => 'Please give the course a title.'];
        if (mb_strlen($title) > 200) return ['ok' => false, 'error' => 'The title can have at most 200 letters.'];
        if (mb_strlen($desc) > 160) return ['ok' => false, 'error' => 'The short description can have at most 160 letters (it has ' . mb_strlen($desc) . ').'];
        if (!isset(SS_LEVELS[$level])) return ['ok' => false, 'error' => 'Choose Beginner, Intermediate or Advanced.'];
        if (!in_array($cp, SST_MINUTES, true) || !in_array($fn, SST_MINUTES, true)) return ['ok' => false, 'error' => 'Choose the exam time limits from the list.'];
        $ts = ss2_ts($now);
        if ($courseId === null) {
            if ($viewer['role'] !== 'tutor') return ['ok' => false, 'error' => 'Only a tutor can create a self-study course.'];
            $db->prepare("INSERT INTO self_study_courses (owner_tutor_id, title, short_description, level, status, module_count, checkpoint_minutes, final_minutes, created_at, updated_at, created_by_user_id)
                          VALUES (?, ?, ?, ?, 'draft', 0, ?, ?, ?, ?, ?)")->execute([$viewer['tutor_id'], $title, $desc, $level, $cp, $fn, $ts, $ts, $viewer['uid']]);
            return ['ok' => true, 'id' => (int) $db->lastInsertId()];
        }
        $db->prepare('UPDATE self_study_courses SET title = ?, short_description = ?, level = ?, checkpoint_minutes = ?, final_minutes = ?, updated_at = ? WHERE id = ?')->execute([$title, $desc, $level, $cp, $fn, $ts, $courseId]);
        return ['ok' => true, 'id' => $courseId];
    }

    /* ------------------------------ the cover image ------------------------------ */

    /** The largest 16:9 frame that fits the picture, centred. */
    function sst_default_crop(int $ow, int $oh): array
    {
        if ($ow / $oh > 16 / 9) { $h = $oh; $w = (int) round($oh * 16 / 9); }
        else { $w = $ow; $h = (int) round($ow * 9 / 16); }
        return ['x' => (int) floor(($ow - $w) / 2), 'y' => (int) floor(($oh - $h) / 2), 'w' => $w, 'h' => $h];
    }

    /** Make the tutor's frame valid: exactly 16:9, inside the picture, at least 1200 px wide. */
    function sst_norm_crop(int $ow, int $oh, ?array $c): array
    {
        $d = sst_default_crop($ow, $oh);
        if (!$c || !isset($c['w'], $c['x'], $c['y']) || (int) $c['w'] <= 0) return $d;
        $w = max(SST_COVER_MIN_WIDTH, min((int) $c['w'], $d['w']));
        $h = (int) round($w * 9 / 16);
        $x = max(0, min((int) $c['x'], $ow - $w)); $y = max(0, min((int) $c['y'], $oh - $h));
        return ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
    }

    function sst_cover_msg(int $cw, int $ch): string
    {
        return $cw . ' × ' . $ch . ' · ' . ($cw >= 1600 ? 'sharp enough' : 'good (1600 pixels wide or more is sharper on big screens)');
    }

    /** Open an image file with the image library (the real type, not the file name). Returns [gd image, type, width, height] or an error string. */
    function sst_open_image(string $path)
    {
        if (!function_exists('imagewebp') || !function_exists('imagecreatefromstring')) return 'This server cannot process images (GD with WebP is needed).';
        $info = @getimagesize($path);
        if (!$info || ($info[0] ?? 0) < 1) return 'That file is not a picture. Use a JPG, PNG or WebP image.';
        if (!in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) return 'Only JPG, PNG and WebP pictures are accepted.';
        if ($info[0] * $info[1] > 40000000) return 'That picture is too large to process. Please use one under 40 megapixels.';
        $img = match ($info[2]) { IMAGETYPE_JPEG => @imagecreatefromjpeg($path), IMAGETYPE_PNG => @imagecreatefrompng($path), default => @imagecreatefromwebp($path) };
        if (!$img) return 'That picture could not be read. It may be damaged.';
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {           // phones save sideways photos with a rotation flag
            $exif = @exif_read_data($path);
            $img = match ((int) ($exif['Orientation'] ?? 1)) { 3 => imagerotate($img, 180, 0), 6 => imagerotate($img, -90, 0), 8 => imagerotate($img, 90, 0), default => $img };
        }
        return [$img, $info[2], imagesx($img), imagesy($img)];
    }

    /** Remove cover files that are no longer used by the course. */
    function sst_cover_cleanup(int $courseId, array $keepRel): void
    {
        $dir = public_path('uploads/selfstudy/' . $courseId);
        $keep = array_map('basename', array_filter($keepRel));
        foreach (glob($dir . '/cover-*') ?: [] as $f) if (!in_array(basename($f), $keep, true)) @unlink($f);
    }

    /**
     * Save a new cover (from an upload) and/or move the crop frame on the cover already saved.
     * The server: checks the real file type with the image library, refuses pictures under 1200 px wide, strips hidden data by re-encoding the original,
     * applies the 16:9 crop and saves WebP copies 400, 800 and 1600 px wide (a size is only made when the crop is at least that wide, so nothing is enlarged).
     * @return array{ok:bool, none?:bool, error?:string, message?:string}
     */
    function sst_apply_cover(PDO $db, int $courseId, ?array $file, ?array $crop, ?int $now = null): array
    {
        $now ??= time();
        $c = ss2_rows($db, 'SELECT cover_original, cover_orig_w, cover_orig_h, crop_x, crop_y, crop_w, crop_h, cover_400 FROM self_study_courses WHERE id = ?', [$courseId])[0] ?? null;
        if (!$c) return ['ok' => false, 'error' => 'That course was not found.'];
        $upload = $file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $dir = public_path('uploads/selfstudy/' . $courseId);
        $stamp = substr(md5(uniqid('', true)), 0, 8);
        if ($upload) {
            $err = $file['error'];
            if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) return ['ok' => false, 'error' => 'The picture is larger than 10 MB. Please choose a smaller one.'];
            if ($err !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => 'The picture did not upload. Please try again.'];
            if (($file['size'] ?? 0) > SST_COVER_MAX_BYTES) return ['ok' => false, 'error' => 'The picture is larger than 10 MB. Please choose a smaller one.'];
            if (!defined('UPLOAD_TEST_MODE') && !is_uploaded_file($file['tmp_name'])) return ['ok' => false, 'error' => 'Security check failed on the uploaded file.'];
            $o = sst_open_image($file['tmp_name']);
            if (is_string($o)) return ['ok' => false, 'error' => $o];
            [$img, $type, $ow, $oh] = $o;
            $maxFrame = sst_default_crop($ow, $oh)['w'];
            if ($ow < SST_COVER_MIN_WIDTH) { imagedestroy($img); return ['ok' => false, 'error' => 'The picture is only ' . $ow . ' pixels wide. It must be at least ' . SST_COVER_MIN_WIDTH . ' pixels wide so it stays sharp (1920 × 1080 is best).']; }
            if ($maxFrame < SST_COVER_MIN_WIDTH) { imagedestroy($img); return ['ok' => false, 'error' => 'The picture is too short for the wide card shape. At 16:9 it needs to be at least ' . (int) round(SST_COVER_MIN_WIDTH * 9 / 16) . ' pixels tall.']; }
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) { imagedestroy($img); return ['ok' => false, 'error' => 'The cover folder could not be created.']; }
            $ext = $type === IMAGETYPE_PNG ? 'png' : ($type === IMAGETYPE_WEBP ? 'webp' : 'jpg');
            $origName = 'cover-' . $stamp . '-original.' . $ext;
            if ($type === IMAGETYPE_PNG) { imagesavealpha($img, true); $saved = @imagepng($img, $dir . '/' . $origName, 6); }
            elseif ($type === IMAGETYPE_WEBP) { imagesavealpha($img, true); $saved = @imagewebp($img, $dir . '/' . $origName, 92); }
            else { $saved = @imagejpeg($img, $dir . '/' . $origName, 92); }        // re-encoding drops EXIF, GPS and any hidden data
            if (!$saved) { imagedestroy($img); return ['ok' => false, 'error' => 'The picture could not be saved.']; }
            $origRel = 'uploads/selfstudy/' . $courseId . '/' . $origName;
        } else {
            if (!$crop || empty($c['cover_original'])) return ['ok' => true, 'none' => true];
            $path = public_path($c['cover_original']);
            if (!is_file($path)) return ['ok' => false, 'error' => 'The saved picture was not found. Please upload it again.'];
            $o = sst_open_image($path);
            if (is_string($o)) return ['ok' => false, 'error' => $o];
            [$img, , $ow, $oh] = $o;
            $origRel = $c['cover_original'];
            $same = $c['crop_w'] !== null && (int) $c['crop_x'] === (int) ($crop['x'] ?? -1) && (int) $c['crop_y'] === (int) ($crop['y'] ?? -1) && (int) $c['crop_w'] === (int) ($crop['w'] ?? -1) && !empty($c['cover_400']);
            if ($same) { imagedestroy($img); return ['ok' => true, 'none' => true]; }
        }
        $cr = sst_norm_crop($ow, $oh, $crop);
        $paths = ['cover_400' => null, 'cover_800' => null, 'cover_1600' => null];
        foreach (SS_WIDTHS as $w) {
            if ($cr['w'] < $w) continue;                                     // never enlarge: that would only make it blurry
            $h = (int) round($w * 9 / 16);
            $copy = imagecreatetruecolor($w, $h);
            imagealphablending($copy, false); imagesavealpha($copy, true);
            imagefill($copy, 0, 0, imagecolorallocatealpha($copy, 255, 255, 255, 127));
            imagecopyresampled($copy, $img, 0, 0, $cr['x'], $cr['y'], $w, $h, $cr['w'], $cr['h']);
            $name = 'cover-' . $stamp . '-' . $w . '.webp';
            if (@imagewebp($copy, $dir . '/' . $name, $w >= 1600 ? 84 : 82)) $paths['cover_' . $w] = 'uploads/selfstudy/' . $courseId . '/' . $name;
            imagedestroy($copy);
        }
        imagedestroy($img);
        if (!$paths['cover_800'] && !$paths['cover_1600'] && !$paths['cover_400']) return ['ok' => false, 'error' => 'The picture could not be prepared. Please try another one.'];
        $db->prepare('UPDATE self_study_courses SET cover_original = ?, cover_400 = ?, cover_800 = ?, cover_1600 = ?, cover_orig_w = ?, cover_orig_h = ?, crop_x = ?, crop_y = ?, crop_w = ?, crop_h = ?, updated_at = ? WHERE id = ?')
           ->execute([$origRel, $paths['cover_400'], $paths['cover_800'], $paths['cover_1600'], $ow, $oh, $cr['x'], $cr['y'], $cr['w'], $cr['h'], ss2_ts($now), $courseId]);
        sst_cover_cleanup($courseId, [$origRel, $paths['cover_400'], $paths['cover_800'], $paths['cover_1600']]);
        return ['ok' => true, 'message' => sst_cover_msg($cr['w'], $cr['h'])];
    }

    /* ------------------------------ before you publish ------------------------------ */

    /** The checklist, worked out from what is SAVED. @return array<int,array{key:string,label:string,done:bool}> */
    function sst_checklist(PDO $db, int $courseId): array
    {
        $c = ss2_rows($db, 'SELECT title, short_description, level, cover_original, cover_400, cover_800, cover_1600 FROM self_study_courses WHERE id = ?', [$courseId])[0] ?? [];
        $n = fn(string $sql) => (int) ss2_rows($db, $sql, [$courseId])[0]['n'];
        $hasTopic = $n('SELECT COUNT(*) AS n FROM self_study_topics t JOIN self_study_modules m ON m.id = t.module_id WHERE t.course_id = ? AND t.archived_at IS NULL AND m.archived_at IS NULL') > 0;
        $set = ss3_course_settings($db, $courseId);        // each exam needs as many questions as one attempt draws (30 and 40 by default)
        $cp = $n("SELECT COUNT(*) AS n FROM self_study_questions WHERE course_id = ? AND place = 'checkpoint_exam' AND qtype IN ('multiple_choice', 'short_answer') AND archived_at IS NULL AND topic_id IS NOT NULL") >= max(1, $set['checkpoint_questions']);
        $fin = $n("SELECT COUNT(*) AS n FROM self_study_questions WHERE course_id = ? AND place = 'final_exam' AND qtype IN ('multiple_choice', 'short_answer') AND archived_at IS NULL AND topic_id IS NOT NULL") >= max(1, $set['final_questions']);
        $who = $n('SELECT COUNT(*) AS n FROM self_study_access WHERE course_id = ? AND removed_at IS NULL') > 0;
        // every module quiz needs as many questions as one attempt draws (12 by default), like the two exams
        $have = array_column(ss2_rows($db, "SELECT module_id, COUNT(*) AS n FROM self_study_questions WHERE course_id = ? AND place = 'module_quiz' AND qtype IN ('multiple_choice', 'short_answer') AND archived_at IS NULL AND topic_id IS NOT NULL GROUP BY module_id", [$courseId]), 'n', 'module_id');
        $modules = array_map('intval', array_column(ss2_rows($db, 'SELECT id FROM self_study_modules WHERE course_id = ? AND archived_at IS NULL', [$courseId]), 'id'));
        $quizzes = (bool) $modules; foreach ($modules as $mid) if ((int) ($have[$mid] ?? 0) < max(1, $set['quiz_questions'])) $quizzes = false;
        return [
            ['key' => 'image', 'label' => 'Course image', 'done' => !empty($c['cover_original']) && (!empty($c['cover_800']) || !empty($c['cover_400']) || !empty($c['cover_1600']))],
            ['key' => 'text', 'label' => 'Title and description', 'done' => trim((string) ($c['title'] ?? '')) !== '' && trim((string) ($c['short_description'] ?? '')) !== ''],
            ['key' => 'level', 'label' => 'Level chosen', 'done' => isset(SS_LEVELS[$c['level'] ?? ''])],
            ['key' => 'content', 'label' => 'At least 1 module with a topic', 'done' => $hasTopic],
            ['key' => 'quizzes', 'label' => 'Enough questions for every module quiz', 'done' => $quizzes],
            ['key' => 'exams', 'label' => 'Checkpoint and Final exam questions', 'done' => $cp && $fin],
            ['key' => 'who', 'label' => 'Choose who sees it', 'done' => $who],
        ];
    }

    /* ------------------------------ publish, unpublish, archive, delete, duplicate, transfer ------------------------------ */

    /** @return array{ok:bool,error?:string,missing?:string[]} */
    function sst_publish(PDO $db, array $viewer, int $courseId, ?int $now = null): array
    {
        $c = sst_course($db, $viewer, $courseId);
        if (!$c || !$c['can_edit']) return ['ok' => false, 'error' => 'You cannot change this course.'];
        if ($c['status'] === 'archived') return ['ok' => false, 'error' => 'This course is archived. Restore it first.'];
        if ($c['status'] === 'published') return ['ok' => true];
        $missing = array_values(array_map(fn($i) => $i['label'], array_filter(sst_checklist($db, $courseId), fn($i) => !$i['done'])));
        if ($missing) return ['ok' => false, 'error' => 'This course is not finished yet: ' . implode(', ', $missing) . '.', 'missing' => $missing];
        $ts = ss2_ts($now ?? time());
        $db->prepare("UPDATE self_study_courses SET status = 'published', published_at = COALESCE(published_at, ?), updated_at = ? WHERE id = ? AND status = 'draft'")->execute([$ts, $ts, $courseId]);
        if (function_exists('ssa_notify_pending')) { try { ssa_notify_pending($db, $courseId, $now); } catch (Throwable $e) { error_log('selfstudy notify on publish: ' . $e->getMessage()); } }     // every student not told yet
        return ['ok' => true];
    }

    /** Admin only: take a published course back to a draft (students stop seeing it; their progress is kept). */
    function sst_unpublish(PDO $db, array $viewer, int $courseId, ?int $now = null): array
    {
        if ($viewer['role'] !== 'admin') return ['ok' => false, 'error' => 'Only admin can unpublish a course.'];
        $st = $db->prepare("UPDATE self_study_courses SET status = 'draft', updated_at = ? WHERE id = ? AND status = 'published'");
        $st->execute([ss2_ts($now ?? time()), $courseId]);
        return $st->rowCount() ? ['ok' => true] : ['ok' => false, 'error' => 'That course is not published.'];
    }

    function sst_archive(PDO $db, array $viewer, int $courseId, ?int $now = null): array
    {
        $c = sst_course($db, $viewer, $courseId);
        if (!$c || !$c['can_edit']) return ['ok' => false, 'error' => 'You cannot change this course.'];
        if ($c['status'] === 'archived') return ['ok' => true];
        $ts = ss2_ts($now ?? time());
        $db->prepare("UPDATE self_study_courses SET status = 'archived', archived_at = ?, updated_at = ? WHERE id = ?")->execute([$ts, $ts, $courseId]);
        return ['ok' => true];            // progress, access and attempts are not touched
    }

    function sst_unarchive(PDO $db, array $viewer, int $courseId, ?int $now = null): array
    {
        $c = sst_course($db, $viewer, $courseId);
        if (!$c || !$c['can_edit']) return ['ok' => false, 'error' => 'You cannot change this course.'];
        if ($c['status'] !== 'archived') return ['ok' => true];
        $db->prepare('UPDATE self_study_courses SET status = ?, archived_at = NULL, updated_at = ? WHERE id = ?')->execute([$c['published_at'] ? 'published' : 'draft', ss2_ts($now ?? time()), $courseId]);
        return ['ok' => true];
    }

    /** Only a DRAFT that no student has touched. Anything else is archived instead. */
    function sst_delete(PDO $db, array $viewer, int $courseId): array
    {
        $c = sst_course($db, $viewer, $courseId);
        if (!$c || !$c['can_edit']) return ['ok' => false, 'error' => 'You cannot delete this course.'];
        if ($c['status'] !== 'draft') return ['ok' => false, 'error' => 'Only a draft can be deleted. Archive this course instead.'];
        if (sst_has_progress($db, $courseId)) return ['ok' => false, 'error' => 'Students already have progress in this course, so it cannot be deleted. Archive it instead.'];
        $own = !$db->inTransaction();
        if ($own) $db->beginTransaction();
        try {
            foreach (['self_study_attempt_answers' => 'attempt_id IN (SELECT id FROM self_study_attempts WHERE course_id = ?)', 'self_study_attempts' => 'course_id = ?', 'self_study_reset_requests' => 'course_id = ?', 'self_study_resets' => 'course_id = ?',
                      'self_study_video_progress' => 'topic_id IN (SELECT id FROM self_study_topics WHERE course_id = ?)', 'self_study_answers' => 'course_id = ?', 'self_study_topic_progress' => 'course_id = ?',
                      'self_study_progress' => 'course_id = ?', 'self_study_access' => 'course_id = ?', 'self_study_files' => 'course_id = ?', 'self_study_questions' => 'course_id = ?', 'self_study_topics' => 'course_id = ?',
                      'self_study_modules' => 'course_id = ?', 'self_study_course_rules' => 'course_id = ?', 'self_study_course_editors' => 'course_id = ?', 'self_study_courses' => 'id = ?'] as $t => $where) {
                try { $db->prepare("DELETE FROM $t WHERE $where")->execute([$courseId]); }
                catch (Throwable $e) { if ($t === 'self_study_courses') throw $e; }          // a table of a later step may not exist yet
            }
            if ($own) $db->commit();
        } catch (Throwable $e) {
            if ($own && $db->inTransaction()) $db->rollBack();
            return ['ok' => false, 'error' => 'The course could not be deleted.'];
        }
        foreach (glob(public_path('uploads/selfstudy/' . $courseId . '/*')) ?: [] as $f) @unlink($f);
        @rmdir(public_path('uploads/selfstudy/' . $courseId));
        foreach (glob(storage_path('selfstudy/' . $courseId . '/*')) ?: [] as $f) @unlink($f);
        @rmdir(storage_path('selfstudy/' . $courseId));
        return ['ok' => true];
    }

    /**
     * A copy as a new draft owned by the person who copies it: cover, modules, topics, questions, files and exam rules. No students, no progress, no attempts.
     * @return array{ok:bool,id?:int,error?:string}
     */
    function sst_duplicate(PDO $db, array $viewer, int $courseId, ?int $now = null): array
    {
        $c = sst_course($db, $viewer, $courseId);
        if (!$c || !$c['can_edit'] || $viewer['role'] !== 'tutor') return ['ok' => false, 'error' => 'You cannot copy this course.'];
        $ts = ss2_ts($now ?? time());
        $own = !$db->inTransaction();
        if ($own) $db->beginTransaction();
        try {
            $title = mb_substr($c['title'], 0, 190) . ' (copy)';
            $db->prepare("INSERT INTO self_study_courses (owner_tutor_id, title, short_description, level, status, module_count, quiz_questions, checkpoint_questions, final_questions, checkpoint_minutes, final_minutes, created_at, updated_at, copied_from_id, created_by_user_id)
                          VALUES (?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
               ->execute([$viewer['tutor_id'], $title, $c['short_description'], $c['level'], (int) $c['module_count'], (int) $c['quiz_questions'], (int) $c['checkpoint_questions'], (int) $c['final_questions'], (int) $c['checkpoint_minutes'], (int) $c['final_minutes'], $ts, $ts, $courseId, $viewer['uid']]);
            $new = (int) $db->lastInsertId();
            $rule = ss2_rows($db, 'SELECT checkpoint_pass, final_pass FROM self_study_course_rules WHERE course_id = ?', [$courseId])[0] ?? null;
            if ($rule) $db->prepare('INSERT INTO self_study_course_rules (course_id, checkpoint_pass, final_pass) VALUES (?, ?, ?)')->execute([$new, $rule['checkpoint_pass'], $rule['final_pass']]);
            $mm = [];
            foreach (ss2_rows($db, 'SELECT id, title, position, pass_mark FROM self_study_modules WHERE course_id = ? AND archived_at IS NULL ORDER BY position, id', [$courseId]) as $m) {
                $db->prepare('INSERT INTO self_study_modules (course_id, title, position, pass_mark, created_at) VALUES (?, ?, ?, ?, ?)')->execute([$new, $m['title'], $m['position'], $m['pass_mark'], $ts]);
                $mm[(int) $m['id']] = (int) $db->lastInsertId();
            }
            $tm = [];
            foreach (ss2_rows($db, 'SELECT id, module_id, title, position, content_html, video_id, video_duration, is_intro FROM self_study_topics WHERE course_id = ? AND archived_at IS NULL ORDER BY position, id', [$courseId]) as $t) {
                if (!(int) $t['is_intro'] && ($t['module_id'] === null || !isset($mm[(int) $t['module_id']]))) continue;          // a topic of an archived module stays behind
                $db->prepare('INSERT INTO self_study_topics (course_id, module_id, title, position, content_html, video_id, video_duration, is_intro, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
                   ->execute([$new, $t['module_id'] !== null ? ($mm[(int) $t['module_id']] ?? null) : null, $t['title'], $t['position'], $t['content_html'], $t['video_id'], $t['video_duration'], $t['is_intro'], $ts]);
                $tm[(int) $t['id']] = (int) $db->lastInsertId();
            }
            foreach (ss2_rows($db, 'SELECT place, topic_id, module_id, qtype, question_text, options_json, correct_index, accepted_answers, position FROM self_study_questions WHERE course_id = ? AND archived_at IS NULL ORDER BY id', [$courseId]) as $q) {
                $db->prepare('INSERT INTO self_study_questions (course_id, place, topic_id, module_id, qtype, question_text, options_json, correct_index, accepted_answers, position, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                   ->execute([$new, $q['place'], $q['topic_id'] !== null ? ($tm[(int) $q['topic_id']] ?? null) : null, $q['module_id'] !== null ? ($mm[(int) $q['module_id']] ?? null) : null, $q['qtype'], $q['question_text'], $q['options_json'], $q['correct_index'], $q['accepted_answers'], $q['position'], $ts]);
            }
            @mkdir(storage_path('selfstudy/' . $new), 0775, true);
            foreach (ss2_rows($db, 'SELECT topic_id, file_name, file_path FROM self_study_files WHERE course_id = ?', [$courseId]) as $f) {
                $src = storage_path('selfstudy/' . ltrim((string) $f['file_path'], '/')); $rel = $new . '/' . basename((string) $f['file_path']);
                if (is_file($src) && @copy($src, storage_path('selfstudy/' . $rel)))
                    $db->prepare('INSERT INTO self_study_files (course_id, topic_id, file_name, file_path, created_at) VALUES (?, ?, ?, ?, ?)')->execute([$new, $f['topic_id'] !== null ? ($tm[(int) $f['topic_id']] ?? null) : null, $f['file_name'], $rel, $ts]);
            }
            // the cover: copy the files, so deleting one course's picture never touches the other's
            if (!empty($c['cover_original'])) {
                $dir = public_path('uploads/selfstudy/' . $new); @mkdir($dir, 0755, true); $paths = [];
                foreach (['cover_original', 'cover_400', 'cover_800', 'cover_1600'] as $k) {
                    $paths[$k] = null;
                    if (!empty($c[$k]) && is_file(public_path($c[$k])) && @copy(public_path($c[$k]), $dir . '/' . basename($c[$k]))) $paths[$k] = 'uploads/selfstudy/' . $new . '/' . basename($c[$k]);
                }
                $db->prepare('UPDATE self_study_courses SET cover_original = ?, cover_400 = ?, cover_800 = ?, cover_1600 = ?, cover_orig_w = ?, cover_orig_h = ?, crop_x = ?, crop_y = ?, crop_w = ?, crop_h = ? WHERE id = ?')
                   ->execute([$paths['cover_original'], $paths['cover_400'], $paths['cover_800'], $paths['cover_1600'], $c['cover_orig_w'], $c['cover_orig_h'], $c['crop_x'], $c['crop_y'], $c['crop_w'], $c['crop_h'], $new]);
            }
            if ($own) $db->commit();
        } catch (Throwable $e) {
            if ($own && $db->inTransaction()) $db->rollBack();
            error_log('selfstudy duplicate: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'The course could not be copied.'];
        }
        return ['ok' => true, 'id' => $new];
    }

    /** Admin only: give the course to another active tutor (for when the creator leaves). */
    function sst_transfer(PDO $db, array $viewer, int $courseId, int $toTutorId, ?int $now = null): array
    {
        if ($viewer['role'] !== 'admin') return ['ok' => false, 'error' => 'Only admin can move a course to another tutor.'];
        if (!ss2_rows($db, 'SELECT id FROM self_study_courses WHERE id = ?', [$courseId])) return ['ok' => false, 'error' => 'That course was not found.'];
        if (!ss2_rows($db, 'SELECT id FROM tutors WHERE id = ? AND is_active = 1', [$toTutorId])) return ['ok' => false, 'error' => 'Choose an active tutor.'];
        $db->prepare('UPDATE self_study_courses SET owner_tutor_id = ?, updated_at = ? WHERE id = ?')->execute([$toTutorId, ss2_ts($now ?? time()), $courseId]);
        $db->prepare('DELETE FROM self_study_course_editors WHERE course_id = ? AND tutor_id = ?')->execute([$courseId, $toTutorId]);   // the new owner needs no editor row
        return ['ok' => true];
    }

    function sst_active_tutors(PDO $db): array
    {
        return ss2_rows($db, "SELECT t.id, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, ''))), ''), u.username) AS name
                              FROM tutors t JOIN users u ON u.id = t.user_id LEFT JOIN user_profiles up ON up.user_id = u.id WHERE t.is_active = 1 ORDER BY name");
    }
}
