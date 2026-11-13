<?php
// app/v1/services/SelfStudy.php — Self-study for students, step 1: what the student sees (read only) and the cover image pipeline.
// A student sees a course only when it is published AND they have an access row that is not removed. Both are checked in SQL, on the server.

if (!function_exists('ss_courses_for_student')) {

    define('SS_LEVELS', ['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced']);
    define('SS_WIDTHS', [400, 800, 1600]);

    /** True once migration 104 is applied (the page then shows the empty message instead of an error before that). */
    function ss_installed(PDO $db): bool
    {
        try { $db->query('SELECT 1 FROM self_study_courses WHERE 1 = 0'); $db->query('SELECT 1 FROM self_study_access WHERE 1 = 0'); $db->query('SELECT 1 FROM self_study_progress WHERE 1 = 0'); return true; }
        catch (Throwable $e) { return false; }
    }

    define('SS_SELECT', "SELECT c.id, c.title, c.short_description, c.level, c.module_count, c.created_at,
                              c.cover_original, c.cover_400, c.cover_800, c.cover_1600,
                              COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, ''))), ''), u.username, 'Your tutor') AS tutor_name,
                              COALESCE(p.percent_complete, 0) AS percent, p.last_studied_at, p.completed_at
                       FROM self_study_courses c
                       JOIN self_study_access a ON a.course_id = c.id AND a.student_id = ? AND a.removed_at IS NULL
                       LEFT JOIN self_study_progress p ON p.course_id = c.id AND p.student_id = a.student_id
                       LEFT JOIN tutors t ON t.id = c.owner_tutor_id
                       LEFT JOIN users u ON u.id = t.user_id
                       LEFT JOIN user_profiles up ON up.user_id = u.id
                       WHERE c.status = 'published'");

    /** Every course shared with this student, with the tutor and the saved progress: ONE query. Newest first. */
    function ss_courses_for_student(PDO $db, int $studentId): array
    {
        if ($studentId <= 0 || !ss_installed($db)) return [];
        $st = $db->prepare(SS_SELECT . ' ORDER BY c.created_at DESC, c.id DESC');
        $st->execute([$studentId]);
        return array_map('ss_decorate', $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /** One course, only if it is published and shared with this student; otherwise null (so a course id can never be guessed). */
    function ss_course_for_student(PDO $db, int $courseId, int $studentId): ?array
    {
        if ($courseId <= 0 || $studentId <= 0 || !ss_installed($db)) return null;
        $st = $db->prepare(SS_SELECT . ' AND c.id = ?');
        $st->execute([$studentId, $courseId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ? ss_decorate($r) : null;
    }

    /** Adds the state (not_started, in_progress, completed), the percentage and the "Last studied" line. */
    function ss_decorate(array $r, ?int $now = null): array
    {
        $pct = max(0, min(100, (int) $r['percent']));
        $done = !empty($r['completed_at']);
        if ($done) $pct = 100;
        $r['percent'] = $pct;
        $r['state'] = $done ? 'completed' : ($pct > 0 ? 'in_progress' : 'not_started');
        $r['level'] = isset(SS_LEVELS[$r['level']]) ? $r['level'] : 'beginner';
        $r['last_text'] = ss_last_text($r['state'], $r['last_studied_at'] ?? null, $r['completed_at'] ?? null, $now);
        return $r;
    }

    /** "Last studied yesterday", "Not started", "Finished 3 Nov". Days are counted in Nairobi time. */
    function ss_last_text(string $state, ?string $lastUtc, ?string $doneUtc, ?int $now = null): string
    {
        if ($state === 'completed') return $doneUtc ? 'Finished ' . ss_local($doneUtc, 'j M Y') : 'Finished';
        if ($state === 'not_started' || !$lastUtc) return $state === 'not_started' ? 'Not started' : 'In progress';
        $tz = new DateTimeZone('Africa/Nairobi');
        $today = (new DateTime('@' . ($now ?? time())))->setTimezone($tz)->setTime(0, 0);
        $then = (new DateTime($lastUtc, new DateTimeZone('UTC')))->setTimezone($tz)->setTime(0, 0);
        $days = max(0, (int) $today->diff($then)->days);
        if ($days === 0) return 'Last studied today';
        if ($days === 1) return 'Last studied yesterday';
        if ($days < 14) return 'Last studied ' . $days . ' days ago';
        return 'Last studied ' . ss_local($lastUtc, 'j M Y');
    }

    function ss_local(string $utc, string $fmt): string
    {
        return (new DateTime($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Africa/Nairobi'))->format($fmt);
    }

    /** Filter chip counts: All, In progress, Not started, Completed. */
    function ss_counts(array $courses): array
    {
        $n = ['all' => count($courses), 'in_progress' => 0, 'not_started' => 0, 'completed' => 0];
        foreach ($courses as $c) $n[$c['state']]++;
        return $n;
    }

    /** The label of the one button: Start at 0%, Continue while in progress, nothing once completed (a green badge shows instead). */
    function ss_button(array $c): string
    {
        return $c['state'] === 'not_started' ? 'Start' : ($c['state'] === 'in_progress' ? 'Continue' : '');
    }

    /** Web path (relative to /public) -> full url, or null. */
    function ss_img_url(?string $rel): ?string
    {
        return $rel ? url(ltrim($rel, '/')) : null;
    }

    /**
     * What the <img> needs: srcset (only the sizes that exist), sizes, and a src fallback.
     * @return array{src:?string, srcset:string, sizes:string}|null  null = no cover at all
     */
    function ss_cover_attrs(array $c): ?array
    {
        $set = [];
        foreach (SS_WIDTHS as $w) {
            if (!empty($c['cover_' . $w])) $set[] = ss_img_url($c['cover_' . $w]) . ' ' . $w . 'w';
        }
        $src = ss_img_url($c['cover_800'] ?: ($c['cover_400'] ?: ($c['cover_1600'] ?: ($c['cover_original'] ?? null))));
        if (!$src) return null;
        // Three cards per row on a desktop (a third of the content), two on a tablet, one on a phone.
        return ['src' => $src, 'srcset' => implode(', ', $set), 'sizes' => '(max-width: 599px) 100vw, (max-width: 991px) 50vw, 380px'];
    }

    /* ---------------- cover images (used by the tutor step that comes next) ---------------- */

    /**
     * Store a cover: the original exactly as uploaded plus WebP copies 400, 800 and 1600 pixels wide.
     * A copy is only made when the original is at least that wide, so nothing is stretched or blurred.
     * Files go to uploads/selfstudy/<courseId>/. Returns the four paths to save in self_study_courses.
     * @return array{ok:bool, error?:string, paths?:array{cover_original:string, cover_400:?string, cover_800:?string, cover_1600:?string}}
     */
    function ss_cover_from_file(string $source, string $ext, int $courseId): array
    {
        if (!function_exists('imagewebp')) return ['ok' => false, 'error' => 'This server cannot make WebP images (GD with WebP is needed).'];
        $info = @getimagesize($source);
        if (!$info || $info[0] < 1 || $info[1] < 1) return ['ok' => false, 'error' => 'That file is not a valid image.'];
        if ($info[0] * $info[1] > 60000000) return ['ok' => false, 'error' => 'That image is too large to process.'];
        $dir = public_path('uploads/selfstudy/' . $courseId);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return ['ok' => false, 'error' => 'The cover folder could not be created.'];

        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source), IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => @imagecreatefromwebp($source), IMAGETYPE_GIF => @imagecreatefromgif($source), default => false,
        };
        if (!$img) return ['ok' => false, 'error' => 'That image type cannot be processed. Use JPG, PNG, WebP or GIF.'];
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {   // phones save sideways photos with a rotation flag
            $exif = @exif_read_data($source);
            $img = match ((int) ($exif['Orientation'] ?? 1)) { 3 => imagerotate($img, 180, 0), 6 => imagerotate($img, -90, 0), 8 => imagerotate($img, 90, 0), default => $img };
        }
        $ow = imagesx($img);
        $oh = imagesy($img);
        $stamp = substr(md5(uniqid('', true)), 0, 8);
        $orig = 'cover-' . $stamp . '-original.' . preg_replace('/[^a-z0-9]/', '', strtolower($ext));
        if (!@copy($source, $dir . '/' . $orig)) { imagedestroy($img); return ['ok' => false, 'error' => 'The cover could not be saved.']; }

        $paths = ['cover_original' => 'uploads/selfstudy/' . $courseId . '/' . $orig, 'cover_400' => null, 'cover_800' => null, 'cover_1600' => null];
        foreach (SS_WIDTHS as $w) {
            if ($ow < $w) continue;
            $h = max(1, (int) round($oh * $w / $ow));
            $copy = imagecreatetruecolor($w, $h);
            imagealphablending($copy, false);
            imagesavealpha($copy, true);
            imagefill($copy, 0, 0, imagecolorallocatealpha($copy, 255, 255, 255, 127));
            imagecopyresampled($copy, $img, 0, 0, 0, 0, $w, $h, $ow, $oh);
            $name = 'cover-' . $stamp . '-' . $w . '.webp';
            if (@imagewebp($copy, $dir . '/' . $name, $w >= 1600 ? 82 : 80)) $paths['cover_' . $w] = 'uploads/selfstudy/' . $courseId . '/' . $name;
            imagedestroy($copy);
        }
        imagedestroy($img);
        // An original narrower than 400 px still needs something sharp to show: serve the original itself.
        return ['ok' => true, 'paths' => $paths];
    }

    /** From a $_FILES entry: checks it is a real image, then makes the copies. */
    function ss_cover_save(?array $file, int $courseId, int $maxBytes = 20971520): array
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['ok' => false, 'none' => true, 'error' => 'No cover image was chosen.'];
        require_once __DIR__ . '/../helpers/uploads.php';
        $v = validate_image_upload($file, ['jpg', 'png', 'webp', 'gif'], $maxBytes);
        if (!$v['ok']) return $v;
        return ss_cover_from_file($file['tmp_name'], $v['ext'], $courseId);
    }
}
