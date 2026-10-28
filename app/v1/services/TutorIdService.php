<?php
/**
 * Tutor ID and certificate preview (migration 080_tutor_id_preview.sql), on top of StaffIdService.
 *
 *  - Previews use the same renderer and template as the real card (idc_pdf_bytes(staff_card())) and the real certificate
 *    (cert_pdf_bytes()), with a 'PREVIEW' / 'SAMPLE' watermark. The print file (bleed + crop marks) stays admin-only.
 *  - The printed name and role (tutors.display_name / display_role) are separate from the login account.
 *  - Photo and signature checks are advice only; the result is saved for the admin. Every change goes to tutor_id_history.
 */

require_once __DIR__ . '/StaffIdService.php';
require_once __DIR__ . '/PortalNotifications.php';

const TID_PHOTO_LABELS = ['none' => 'No photo', 'ok' => 'Uploaded, looks good', 'needs_update' => 'Uploaded, needs a better photo',
                          'approved' => 'Approved by the office', 'requested' => 'New photo requested by the office'];
const TID_SIG_LABELS = ['none' => 'No signature', 'ok' => 'Looks good', 'needs_update' => 'Needs a better signature', 'requested' => 'New signature requested by the office'];
const TID_ID_LABELS = ['not_created' => 'Not created yet', 'preview_ready' => 'Preview ready', 'sent_to_print' => 'Sent to print', 'printed' => 'Printed'];

if (!function_exists('tid_installed')) {

    function tid_installed(PDO $db): bool
    {
        static $ok = null;
        if ($ok === null) {
            try { $ok = (bool) $db->query("SHOW COLUMNS FROM tutors LIKE 'display_name'")->fetchColumn(); } catch (Throwable $e) { $ok = false; }
        }
        return $ok;
    }

    /** Printed name and role (what the ID card and certificates show). */
    function tid_name(array $t): string
    {
        return trim((string) ($t['display_name'] ?? '')) ?: (string) $t['full_name'];
    }

    function tid_role(array $t): string
    {
        return trim((string) ($t['display_role'] ?? '')) ?: 'Tutor';
    }

    /** Card status for the pill: no card until there is a photo and a staff number. */
    function tid_id_status(PDO $db, array $t): string
    {
        if (empty($t['staff_no']) || !idc_student_photo_file($db, (int) $t['user_id'])) return 'not_created';
        $s = (string) ($t['id_status'] ?? 'not_created');
        return $s === 'not_created' ? 'preview_ready' : $s;
    }

    function tid_history(PDO $db, int $tutorId, string $field, $old, $new): void
    {
        try {
            $db->prepare('INSERT INTO tutor_id_history (tutor_id, field, old_value, new_value, changed_by) VALUES (?, ?, ?, ?, ?)')
               ->execute([$tutorId, $field, $old === null ? null : (string) $old, $new === null ? null : (string) $new, (int) ($_SESSION['user_id'] ?? 0) ?: null]);
        } catch (Throwable $e) {
            error_log('[tutor id history] ' . $e->getMessage());
        }
        tid_audit($db, $tutorId, $field, ($old === null ? '' : mb_substr((string) $old, 0, 100)) . ' → ' . mb_substr((string) $new, 0, 120));
    }

    /** Audit log (id_audit_log, migration 081): uploads, edits, approvals, requests, signature changes, downloads. */
    function tid_audit(PDO $db, ?int $tutorId, string $action, string $detail = ''): void
    {
        try {
            $db->prepare('INSERT INTO id_audit_log (actor_id, tutor_id, action, detail) VALUES (?, ?, ?, ?)')
               ->execute([(int) ($_SESSION['user_id'] ?? 0) ?: null, $tutorId, mb_substr($action, 0, 40), mb_substr($detail, 0, 255)]);
        } catch (Throwable $e) { /* migration 081 not run yet */ }
    }

    /** Admin users (role_id 2) for dashboard notices. */
    function tid_admin_ids(PDO $db): array
    {
        try {
            return array_map('intval', $db->query('SELECT id FROM users WHERE role_id = 2 AND is_active = 1')->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Ready to print: active tutor (and account), ID number, photo (not flagged unless the office approved it),
     * and a director signature. Returns [bool, reason when not ready].
     */
    function tid_ready(PDO $db, array $t, ?bool $hasDirector = null): array
    {
        $hasDirector ??= (bool) dsig_active($db);
        if (empty($t['is_active']) || (isset($t['user_active']) && !$t['user_active'])) return [false, 'Tutor is not active'];
        if (empty($t['staff_no'])) return [false, 'No ID number yet'];
        if (!idc_student_photo_file($db, (int) $t['user_id'])) return [false, 'Waiting for a photo'];
        if (($t['photo_status'] ?? '') === 'requested') return [false, 'New photo requested'];
        if (($t['photo_status'] ?? '') === 'needs_update') return [false, 'Needs a better photo (approve it or request a new one)'];
        if (!$hasDirector) return [false, 'Director signature not set (Settings)'];
        return [true, ''];
    }

    /** Admin crop of the stored photo: {x, y, w} as fractions; null resets to the automatic centre-top crop. */
    function tid_set_crop(PDO $db, array $t, ?array $crop): array
    {
        $json = null;
        if ($crop) {
            $c = ['x' => (float) ($crop['x'] ?? 0), 'y' => (float) ($crop['y'] ?? 0), 'w' => (float) ($crop['w'] ?? 1)];
            if ($c['w'] < 0.05 || $c['w'] > 1 || $c['x'] < 0 || $c['y'] < 0 || $c['x'] > 1 || $c['y'] > 1) return ['ok' => false, 'error' => 'That crop is not valid.'];
            $json = json_encode(array_map(fn($v) => round($v, 4), $c));
        }
        $db->prepare('UPDATE tutors SET photo_crop = ? WHERE id = ?')->execute([$json, (int) $t['id']]);
        tid_history($db, (int) $t['id'], 'photo_crop', $t['photo_crop'] ?? null, $json ?? 'automatic');
        return ['ok' => true];
    }

    /** Lost card: a new QR token. The old printed QR stops working. */
    function tid_regen_token(PDO $db, array $t): array
    {
        $db->prepare('UPDATE tutors SET verify_token = ? WHERE id = ?')->execute([bin2hex(random_bytes(20)), (int) $t['id']]);
        tid_history($db, (int) $t['id'], 'verify_token', 'old QR', 'new QR (old one stopped)');
        return ['ok' => true];
    }

    function tid_is_admin(): bool
    {
        return in_array($_SESSION['role'] ?? '', ['admin', 'superadmin'], true) || !empty($_SESSION['tutor_admin']);
    }

    /** Uploads per session: at most 12 in 10 minutes. */
    function tid_rate_ok(): bool
    {
        $now = time();
        $_SESSION['tid_uploads'] = array_values(array_filter((array) ($_SESSION['tid_uploads'] ?? []), fn($t) => $t > $now - 600));
        if (count($_SESSION['tid_uploads']) >= 12) return false;
        $_SESSION['tid_uploads'][] = $now;
        return true;
    }

    /** Small grayscale copy for the checks (keeps them fast on big photos). */
    function _tid_gray(\GdImage $im, int $maxSide = 300): array
    {
        $w = imagesx($im); $h = imagesy($im);
        $k = min(1, $maxSide / max($w, $h));
        $sw = max(1, (int) round($w * $k)); $sh = max(1, (int) round($h * $k));
        $s = imagecreatetruecolor($sw, $sh);
        imagefill($s, 0, 0, imagecolorallocate($s, 255, 255, 255));
        imagecopyresampled($s, $im, 0, 0, 0, 0, $sw, $sh, $w, $h);
        $g = [];
        for ($y = 0; $y < $sh; $y++) for ($x = 0; $x < $sw; $x++) {
            $c = imagecolorat($s, $x, $y);
            $g[$y][$x] = 0.299 * (($c >> 16) & 255) + 0.587 * (($c >> 8) & 255) + 0.114 * ($c & 255);
        }
        return [$g, $sw, $sh];
    }

    /**
     * Passport photo check (advice): size, type, file size, portrait shape, blur (Laplacian variance), brightness,
     * busy background (edge density along the top and sides). Returns ['status' => ok|needs_update, 'notes' => [...]].
     */
    function tid_check_photo(string $abs, int $bytes = 0): array
    {
        $notes = [];
        $info = @getimagesize($abs);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) return ['status' => 'needs_update', 'notes' => ['Use a JPG or PNG photo.']];
        [$w, $h] = $info;
        if (($bytes ?: (int) @filesize($abs)) > 5 * 1024 * 1024) $notes[] = 'The file is larger than 5 MB.';
        if ($w < 600 || $h < 750) $notes[] = 'Too small. Use a photo at least 600 × 750 pixels.';
        $ratio = $w / max(1, $h);
        if ($ratio > 0.9 || $ratio < 0.65) $notes[] = 'Not portrait. Crop so your face and shoulders fill the frame.';
        $im = function_exists('imagecreatefromstring') ? @imagecreatefromstring((string) file_get_contents($abs)) : false;
        if ($im) {
            [$g, $sw, $sh] = _tid_gray($im);
            $sum = 0; $n = 0; $lap = []; $edgeBorder = 0; $borderN = 0;
            for ($y = 1; $y < $sh - 1; $y++) for ($x = 1; $x < $sw - 1; $x++) {
                $l = 4 * $g[$y][$x] - $g[$y - 1][$x] - $g[$y + 1][$x] - $g[$y][$x - 1] - $g[$y][$x + 1];
                $lap[] = $l;
                $sum += $g[$y][$x]; $n++;
                if ($y < $sh * 0.18 || $x < $sw * 0.12 || $x > $sw * 0.88) { $borderN++; if (abs($l) > 25) $edgeBorder++; }
            }
            $mean = $sum / max(1, $n);
            $avgL = array_sum($lap) / max(1, count($lap));
            $var = 0; foreach ($lap as $l) $var += ($l - $avgL) ** 2;
            $var /= max(1, count($lap));
            if ($var < 60) $notes[] = 'A bit blurry. Hold the phone steady and tap to focus.';
            if ($mean < 70) $notes[] = 'Too dark. Stand facing a window or a bright light.';
            if ($mean > 215) $notes[] = 'Too bright. Move away from direct light.';
            if ($borderN && $edgeBorder / $borderN > 0.22) $notes[] = 'Background is busy. Use a plain wall behind you.';
        }
        return ['status' => $notes ? 'needs_update' : 'ok', 'notes' => $notes];
    }

    /** Signature check on the cleaned PNG: faint, small, cut off at an edge, background left over. */
    function tid_check_signature(string $abs): array
    {
        $notes = [];
        $im = @imagecreatefrompng($abs);
        if (!$im) return ['status' => 'needs_update', 'notes' => ['The signature could not be read. Upload it again.']];
        $w = imagesx($im); $h = imagesy($im);
        $ink = 0; $semi = 0; $dark = 0; $n = 0;
        $step = max(1, (int) floor(max($w, $h) / 400));
        for ($y = 0; $y < $h; $y += $step) for ($x = 0; $x < $w; $x += $step) {
            $c = imagecolorsforindex($im, imagecolorat($im, $x, $y));
            $n++;
            $op = 1 - $c['alpha'] / 127;
            if ($op > 0.5) {
                $ink++;
                $lum = ($c['red'] + $c['green'] + $c['blue']) / 3;
                if ($lum < 90) $dark++;
            } elseif ($op > 0.12) {
                $semi++;
            }
        }
        if ($w < 160 || $h < 40) $notes[] = 'Too small. Sign bigger, filling most of the paper width.';
        if ($ink && $dark / $ink < 0.35) $notes[] = 'Too faint. Sign in dark ink (black or blue pen).';
        if ($n && $ink / $n > 0.45) $notes[] = 'Background not fully removed. Use plain white paper and good light.';
        if ($n && $semi / $n > 0.25) $notes[] = 'Background not fully removed. Use plain white paper and good light.';
        return ['status' => $notes ? 'needs_update' : 'ok', 'notes' => array_values(array_unique($notes))];
    }

    /** On the photo as uploaded (before the margins are trimmed): dark ink touching the edge means it was cut off. */
    function tid_signature_cut_off(string $tmp): bool
    {
        $im = @imagecreatefromstring((string) @file_get_contents($tmp));
        if (!$im) return false;
        [$g, $w, $h] = _tid_gray($im, 400);
        $bw = 2;   // the outermost 2 pixels of the scaled copy
        $sides = ['top' => 0, 'bottom' => 0, 'left' => 0, 'right' => 0];
        for ($y = 0; $y < $h; $y++) for ($x = 0; $x < $w; $x++) {
            if ($g[$y][$x] >= 110) continue;
            if ($y < $bw) $sides['top']++;
            if ($y >= $h - $bw) $sides['bottom']++;
            if ($x < $bw) $sides['left']++;
            if ($x >= $w - $bw) $sides['right']++;
        }
        return max($sides) >= 4;   // a pen stroke running off at least one side
    }

    /** Re-save a photo through GD: drops EXIF/metadata (location, camera) and fixes orientation. */
    function tid_reencode_photo(string $abs): void
    {
        if (!function_exists('imagecreatefromstring') || !is_file($abs)) return;
        $raw = (string) file_get_contents($abs);
        $im = @imagecreatefromstring($raw);
        if (!$im) return;
        if (function_exists('exif_read_data') && str_starts_with($raw, "\xFF\xD8")) {
            $o = (int) (@exif_read_data($abs)['Orientation'] ?? 1);
            if ($o === 3) $im = imagerotate($im, 180, 0); elseif ($o === 6) $im = imagerotate($im, -90, 0); elseif ($o === 8) $im = imagerotate($im, 90, 0);
        }
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        if ($ext === 'png') { imagesavealpha($im, true); imagepng($im, $abs, 6); }
        elseif ($ext === 'webp' && function_exists('imagewebp')) imagewebp($im, $abs, 92);
        else imagejpeg($im, $abs, 92);
    }

    /**
     * Tutor ID photo: orientation fixed, cropped to 4:5 from the centre-top and resized to the standard ID size
     * (900 × 1125 px), re-saved as JPEG (no EXIF), in private storage (tutors.id_photo_path). A small 160 × 200
     * copy goes to the profile for the portal avatar, so the avatars across the site keep working.
     */
    function tid_store_photo(PDO $db, array $t, string $tmp): array
    {
        if (!function_exists('imagecreatefromstring')) return ['ok' => false, 'error' => 'Image processing is not available on this server.'];
        $raw = (string) @file_get_contents($tmp);
        $src = @imagecreatefromstring($raw);
        if (!$src) return ['ok' => false, 'error' => 'The photo could not be read.'];
        if (function_exists('exif_read_data') && str_starts_with($raw, "\xFF\xD8")) {
            $o = (int) (@exif_read_data($tmp)['Orientation'] ?? 1);
            if ($o === 3) $src = imagerotate($src, 180, 0); elseif ($o === 6) $src = imagerotate($src, -90, 0); elseif ($o === 8) $src = imagerotate($src, 90, 0);
        }
        $fit = function (int $W, int $H) use ($src) {
            $sw = imagesx($src); $sh = imagesy($src);
            $k = max($W / $sw, $H / $sh); $cw = $W / $k; $ch = $H / $k;
            $out = imagecreatetruecolor($W, $H);
            imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
            imagecopyresampled($out, $src, 0, 0, (int) (($sw - $cw) / 2), (int) max(0, ($sh - $ch) * 0.15), $W, $H, (int) $cw, (int) $ch);
            return $out;
        };
        $dir = storage_path('tutor_photos');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return ['ok' => false, 'error' => 'Could not create the photo folder.'];
        if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
        $name = bin2hex(random_bytes(16)) . '.jpg';
        if (!imagejpeg($fit(900, 1125), $dir . '/' . $name, 90)) return ['ok' => false, 'error' => 'The photo could not be saved.'];
        try {
            $db->prepare('UPDATE tutors SET id_photo_path = ? WHERE id = ?')->execute(['storage:tutor_photos/' . $name, (int) $t['id']]);
        } catch (Throwable $e) {
            @unlink($dir . '/' . $name);
            return ['ok' => false, 'error' => 'Ask the office to run System Updates (migration 082) first.'];
        }
        $old = (string) ($t['id_photo_path'] ?? '');
        if (str_starts_with($old, 'storage:tutor_photos/') && ($oa = realpath(storage_path(substr($old, 8)))) && str_starts_with($oa, realpath($dir) . DIRECTORY_SEPARATOR)) @unlink($oa);
        // Small avatar for the portal (not the ID photo).
        $adir = dirname(__DIR__, 3) . '/public/uploads/profile_photos';
        if (is_dir($adir) || @mkdir($adir, 0775, true)) {
            $an = 'avatar_' . bin2hex(random_bytes(8)) . '.jpg';
            if (imagejpeg($fit(160, 200), $adir . '/' . $an, 85)) {
                $db->prepare("INSERT INTO user_profiles (user_id, profile_photo_path, profile_photo_submitted_at, profile_photo_status) VALUES (?, ?, NOW(), 'approved')
                              ON DUPLICATE KEY UPDATE profile_photo_path = VALUES(profile_photo_path), profile_photo_submitted_at = NOW(), profile_photo_status = 'approved'")
                   ->execute([(int) $t['user_id'], 'uploads/profile_photos/' . $an]);
            }
        }
        return ['ok' => true];
    }

    /** Save the tutor's passport photo (the existing photo storage), re-encode it, check it, then the existing card issue/email. */
    function tid_save_photo(PDO $db, array $t, array $file): array
    {
        if (!tid_rate_ok()) return ['ok' => false, 'error' => 'Too many uploads. Please wait a few minutes.'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => 'Choose a photo.'];
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) return ['ok' => false, 'error' => 'The photo must be 5 MB or smaller.'];
        $info = @getimagesize($file['tmp_name']);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) return ['ok' => false, 'error' => 'Use a JPG or PNG photo.'];
        if (!defined('UPLOAD_TEST_MODE') && !is_uploaded_file($file['tmp_name'])) return ['ok' => false, 'error' => 'The upload failed the security check.'];
        $chk = tid_check_photo($file['tmp_name'], (int) $file['size']);   // checked as uploaded, before the resize
        $r = tid_store_photo($db, $t, $file['tmp_name']);
        if (!$r['ok']) return $r;
        // request_note first: MySQL applies SET left to right, so photo_status must still hold the old value here.
        if (array_key_exists('photo_crop', $t)) $db->prepare('UPDATE tutors SET photo_crop = NULL WHERE id = ?')->execute([(int) $t['id']]);
        $db->prepare('UPDATE tutors SET request_note = IF(photo_status = \'requested\', NULL, request_note), photo_status = ?, photo_check_notes = ? WHERE id = ?')
           ->execute([$chk['status'], $chk['notes'] ? implode("\n", $chk['notes']) : null, (int) $t['id']]);
        tid_history($db, (int) $t['id'], 'photo', $t['photo_status'] ?? null, $chk['status']);
        $issue = staff_issue($db, (int) $t['user_id']);   // unchanged: issues and emails the card the first time
        $nt = staff_tutor($db, (int) $t['user_id']);
        if ($nt && !empty($nt['staff_no']) && ($nt['id_status'] ?? 'not_created') === 'not_created') {
            $db->prepare("UPDATE tutors SET id_status = 'preview_ready' WHERE id = ?")->execute([(int) $t['id']]);
        }
        tid_audit($db, (int) $t['id'], 'photo_upload', $chk['status']);
        if (!tid_is_admin()) {
            $nm = tid_name($nt ?: $t);
            [$ready] = $nt ? tid_ready($db, $nt) : [false];
            foreach (tid_admin_ids($db) as $aid) {
                PortalNotifications::notify($db, $aid, 'id_card', $ready ? $nm . "'s ID is ready to print" : $nm . ' uploaded an ID photo',
                    $chk['status'] === 'ok' ? 'The photo check looks good.' : 'The photo check suggests a better photo.', 'admin/tutors/id.php?id=' . (int) $t['id']);
            }
        }
        return ['ok' => true, 'check' => $chk, 'issue' => $issue];
    }

    function tid_save_signature(PDO $db, array $t, array $file): array
    {
        if (!tid_rate_ok()) return ['ok' => false, 'error' => 'Too many uploads. Please wait a few minutes.'];
        $cut = !empty($file['tmp_name']) && tid_signature_cut_off((string) $file['tmp_name']);
        $r = sig_store($file);   // existing: background removed, private storage
        if (!$r['ok']) return $r;
        $db->prepare('UPDATE tutors SET signature_path = ? WHERE id = ?')->execute([$r['path'], (int) $t['id']]);
        sig_delete($t['signature_path']);   // the old file goes only after the new one is saved
        $chk = tid_check_signature((string) sig_abs($r['path']));
        if ($cut) { $chk['notes'][] = 'Part of the signature is cut off. Leave space around it when you take the photo.'; $chk['status'] = 'needs_update'; }
        $db->prepare('UPDATE tutors SET request_note = IF(signature_status = \'requested\', NULL, request_note), signature_status = ?, signature_check_notes = ? WHERE id = ?')
           ->execute([$chk['status'], $chk['notes'] ? implode("\n", $chk['notes']) : null, (int) $t['id']]);
        tid_history($db, (int) $t['id'], 'signature', $t['signature_status'] ?? null, $chk['status']);
        if (!empty($t['staff_no'])) staff_issue($db, (int) $t['user_id']);
        return ['ok' => true, 'check' => $chk];
    }

    /** Warn when the name will not print clearly (same fitting rules as idc_front_html()). */
    function tid_name_warning(string $name): ?string
    {
        require_once __DIR__ . '/../helpers/tutor_card_pdf.php';
        return tcard_fit_name($name)['warn'] ? 'This name is too long to print clearly. Shorten it, for example use an initial for a middle name.' : null;
    }

    /** Edit the printed name / role (tutor or admin). A tutor's edit marks the record "Edited by tutor". */
    function tid_update_display(PDO $db, array $t, string $name, string $role, bool $byTutor, ?string $email = null): array
    {
        if ($email !== null && array_key_exists('card_email', $t)) {
            $email = strtolower(trim($email));
            if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190)) return ['ok' => false, 'error' => 'Enter a valid email for the card, or leave it empty to use your account email.'];
            $oldE = trim((string) ($t['card_email'] ?? ''));
            if ($email !== $oldE) {
                $db->prepare('UPDATE tutors SET card_email = ?' . ($byTutor ? ', edited_by_tutor = 1' : '') . ' WHERE id = ?')->execute([$email ?: null, (int) $t['id']]);
                tid_history($db, (int) $t['id'], 'card_email', $oldE ?: '(account email)', $email ?: '(account email)');
                $t['card_email'] = $email;
                $emailChanged = true;
            }
        }
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $role = trim(preg_replace('/\s+/', ' ', $role));
        if ($name === '' || mb_strlen($name) > 150) return ['ok' => false, 'error' => 'Enter your full name as it should be printed.'];
        if (!preg_match("/^[\\p{L}][\\p{L} .'\\-]*$/u", $name)) return ['ok' => false, 'error' => 'Use letters only in the name (spaces, dots, hyphens and apostrophes are fine).'];
        if ($role === '' || mb_strlen($role) > 120) return ['ok' => false, 'error' => 'Enter your role, for example "Tutor, Cyber Security".'];
        $changed = false;
        foreach (['display_name' => $name, 'display_role' => $role] as $col => $v) {
            $old = $col === 'display_name' ? tid_name($t) : tid_role($t);
            if ($v !== $old) { tid_history($db, (int) $t['id'], $col, $old, $v); $changed = true; }
        }
        if (!$changed) return ['ok' => true, 'unchanged' => empty($emailChanged), 'warning' => tid_name_warning($name)];
        $db->prepare('UPDATE tutors SET display_name = ?, display_role = ?, edited_by_tutor = ? WHERE id = ?')
           ->execute([$name, $role, $byTutor ? 1 : 0, (int) $t['id']]);
        return ['ok' => true, 'warning' => tid_name_warning($name)];
    }

    /** Admin only: create or change the staff number. */
    function tid_set_staff_no(PDO $db, array $t, string $no): array
    {
        $no = strtoupper(trim($no));
        if (!preg_match('#^[A-Z0-9/\-]{3,40}$#', $no)) return ['ok' => false, 'error' => 'Use letters, numbers, / and - only (3 to 40 characters).'];
        $st = $db->prepare('SELECT 1 FROM tutors WHERE staff_no = ? AND id <> ?');
        $st->execute([$no, (int) $t['id']]);
        if ($st->fetchColumn()) return ['ok' => false, 'error' => 'Another tutor already has ' . $no . '.'];
        $db->prepare('UPDATE tutors SET staff_no = ?, id_qr_hash = COALESCE(id_qr_hash, ?), id_issued_at = COALESCE(id_issued_at, NOW()) WHERE id = ?')
           ->execute([$no, bin2hex(random_bytes(16)), (int) $t['id']]);
        tid_history($db, (int) $t['id'], 'staff_no', $t['staff_no'], $no);
        return ['ok' => true];
    }

    function tid_set_id_status(PDO $db, array $t, string $status): array
    {
        if (!isset(TID_ID_LABELS[$status]) || $status === 'not_created') return ['ok' => false, 'error' => 'Unknown status.'];
        $db->prepare('UPDATE tutors SET id_status = ? WHERE id = ?')->execute([$status, (int) $t['id']]);
        tid_history($db, (int) $t['id'], 'id_status', $t['id_status'] ?? null, $status);
        return ['ok' => true];
    }

    function tid_approve_photo(PDO $db, array $t): array
    {
        if (!idc_student_photo_file($db, (int) $t['user_id'])) return ['ok' => false, 'error' => 'There is no photo to approve.'];
        $db->prepare("UPDATE tutors SET photo_status = 'approved', edited_by_tutor = 0 WHERE id = ?")->execute([(int) $t['id']]);
        tid_history($db, (int) $t['id'], 'photo', $t['photo_status'] ?? null, 'approved');
        return ['ok' => true];
    }

    /** Ask the tutor for a new photo or signature: dashboard notification + email, and the status on their page. */
    function tid_request(PDO $db, array $t, string $what, string $message): array
    {
        $what = $what === 'signature' ? 'signature' : 'photo';
        $message = mb_substr(trim($message), 0, 255);
        if ($message === '') return ['ok' => false, 'error' => 'Write a short message for the tutor.'];
        $col = $what === 'photo' ? 'photo_status' : 'signature_status';
        $db->prepare("UPDATE tutors SET $col = 'requested', request_note = ? WHERE id = ?")->execute([$message, (int) $t['id']]);
        tid_history($db, (int) $t['id'], $col, $t[$col] ?? null, 'requested: ' . $message);
        $title = $what === 'photo' ? 'Please upload a new passport photo' : 'Please upload a new signature';
        PortalNotifications::notify($db, (int) $t['user_id'], 'id_card', $title, $message, 'tutor/profile/index.php');
        try {
            require_once __DIR__ . '/Mailer.php';
            $p = 'font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.5;color:#111827;';
            $html = '<p style="' . $p . '">Hi ' . htmlspecialchars(explode(' ', tid_name($t))[0]) . ',</p><p style="' . $p . '">The office asked for a new ' . $what
                . ' for your staff ID card and certificates:</p><p style="' . $p . 'background:#f3f4f6;padding:10px 14px;border-radius:8px">' . htmlspecialchars($message) . '</p>'
                . '<p style="' . $p . '">Open <strong>My ID &amp; signature</strong> in the tutor portal to upload it.</p><p style="' . $p . '">Dazu Training Institute</p>';
            $cfg = require __DIR__ . '/../config/mail.php';
            (new Mailer($cfg))->send((string) $t['email'], tid_name($t), $title . ' — Dazu Training Institute', $html);
        } catch (Throwable $e) {
            error_log('[tutor id request email] ' . $e->getMessage());
        }
        return ['ok' => true];
    }

    /** The preview PDF: the real card (same renderer, same template, no watermark), not the print file. */
    function tid_card_preview_pdf(PDO $db, array $t): string
    {
        $card = staff_card($db, $t);
        if (empty($card['snapshot_reg_no'])) $card['snapshot_reg_no'] = 'Assigned by the office';
        if (empty($card['qr_hash'])) $card['qr_hash'] = 'preview';
        return idc_pdf_bytes($card, false);
    }

    /** Sample certificate: the real certificate design with this tutor's printed name, role and signature. */
    function tid_sample_certificate_pdf(PDO $db, array $t): string
    {
        require_once __DIR__ . '/../helpers/certificate_pdf.php';
        return cert_pdf_bytes([
            'id' => 0, 'certificate_no' => 'SAMPLE', 'qr_code_hash' => str_repeat('0', 32), 'issued_at' => date('Y-m-d H:i:s'),
            'snapshot_name' => 'Sample Student', 'full_name' => 'Sample Student', 'snapshot_course' => 'Sample Course in Your Subject',
            'course_name' => 'Sample Course in Your Subject', 'snapshot_duration_months' => 3,
            'taught_skills' => 'and has met all the requirements of the programme.', 'user_id' => 0,
            'tutor_name' => tid_name($t), 'tutor_title' => tid_role($t), 'tutor_id' => (int) $t['user_id'],
            'tutor_signature_path' => $t['signature_path'] ?: null,
        ]);
    }
}

if (!function_exists('tid_handle_post')) {
    /**
     * POST actions of the ID panel (tutor page and admin page). $admin = acting as the office.
     * Returns [type, message] for the flash.
     */
    function tid_handle_post(PDO $db, array $t, bool $admin): array
    {
        $a = (string) ($_POST['tid_action'] ?? '');
        $ok = fn(string $m) => ['success', $m];
        $err = fn(string $m) => ['error', $m];
        switch ($a) {
            case 'photo':
                $r = tid_save_photo($db, $t, $_FILES['photo'] ?? []);
                if (!$r['ok']) return $err($r['error']);
                $msg = $r['check']['status'] === 'ok' ? 'Photo saved. It looks good.' : 'Photo saved, but it could be better: ' . implode(' ', $r['check']['notes']);
                if (!empty($r['issue']['issued'])) $msg .= ' Your staff ID card is ready' . (!empty($r['issue']['emailed']) ? ' and was emailed to you.' : '.');
                return [$r['check']['status'] === 'ok' ? 'success' : 'warning', $msg];
            case 'signature':
                $r = tid_save_signature($db, $t, $_FILES['signature'] ?? []);
                if (!$r['ok']) return $err($r['error']);
                return [$r['check']['status'] === 'ok' ? 'success' : 'warning', $r['check']['status'] === 'ok'
                    ? 'Signature saved. It looks good.' : 'Signature saved, but it could be better: ' . implode(' ', $r['check']['notes'])];
            case 'display':
                $r = tid_update_display($db, $t, (string) ($_POST['display_name'] ?? ''), (string) ($_POST['display_role'] ?? ''), !$admin, isset($_POST['card_email']) ? (string) $_POST['card_email'] : null);
                if (!$r['ok']) return $err($r['error']);
                if ($admin && isset($_POST['staff_no']) && trim((string) $_POST['staff_no']) !== (string) $t['staff_no']) {
                    $s = tid_set_staff_no($db, $t, (string) $_POST['staff_no']);
                    if (!$s['ok']) return $err($s['error']);
                }
                return [!empty($r['warning']) ? 'warning' : 'success', (!empty($r['unchanged']) ? 'Nothing changed.' : 'Saved. The previews are updated.') . (!empty($r['warning']) ? ' ' . $r['warning'] : '')];
            case 'approve_photo':
                if (!$admin) break;
                $r = tid_approve_photo($db, $t);
                return $r['ok'] ? $ok('Photo approved.') : $err($r['error']);
            case 'request':
                if (!$admin) break;
                $r = tid_request($db, $t, (string) ($_POST['what'] ?? 'photo'), (string) ($_POST['message'] ?? ''));
                return $r['ok'] ? $ok('Request sent. The tutor got a notification and an email.') : $err($r['error']);
            case 'id_status':
                if (!$admin) break;
                $r = tid_set_id_status($db, $t, (string) ($_POST['id_status'] ?? ''));
                return $r['ok'] ? $ok('Card status saved.') : $err($r['error']);
            case 'crop':
                if (!$admin) break;
                $c = !empty($_POST['reset']) ? null : json_decode((string) ($_POST['crop'] ?? ''), true);
                $r = tid_set_crop($db, $t, is_array($c) ? $c : null);
                return $r['ok'] ? $ok('Photo crop saved. The previews are updated.') : $err($r['error']);
            case 'regen_token':
                if (!$admin) break;
                tid_regen_token($db, $t);
                return $ok('A new QR code was made. The old printed card no longer verifies; print a new card.');
            case 'clear_edited':
                if (!$admin) break;
                $db->prepare('UPDATE tutors SET edited_by_tutor = 0 WHERE id = ?')->execute([(int) $t['id']]);
                tid_history($db, (int) $t['id'], 'edited_by_tutor', '1', '0 (checked by the office)');
                return $ok('Marked as checked.');
        }
        return $err('Unknown action.');
    }
}

if (!function_exists('dsig_versions')) {
    /** Director signature versions (admin Settings), newest first, with who uploaded them. */
    function dsig_versions(PDO $db): array
    {
        try {
            return $db->query('SELECT d.*, u.username FROM director_signatures d LEFT JOIN users u ON u.id = d.uploaded_by ORDER BY d.version DESC')->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    /** Upload a new director signature (background removed by the tutor-signature code). It becomes the active version. */
    function dsig_upload(PDO $db, array $file, string $name, string $title): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name)) ?: 'Mwangi Ngigi';
        $title = trim(preg_replace('/\s+/', ' ', $title)) ?: 'CEO & Founder';
        if (mb_strlen($name) > 150 || mb_strlen($title) > 80) return ['ok' => false, 'error' => 'The name or title is too long.'];
        if (!tid_rate_ok()) return ['ok' => false, 'error' => 'Too many uploads. Please wait a few minutes.'];
        $cut = !empty($file['tmp_name']) && tid_signature_cut_off((string) $file['tmp_name']);
        $r = sig_store($file);   // re-encoded, background removed, private storage
        if (!$r['ok']) return $r;
        $chk = tid_check_signature((string) sig_abs($r['path']));
        if ($cut) { $chk['notes'][] = 'Part of the signature is cut off. Leave space around it when you take the photo.'; $chk['status'] = 'needs_update'; }
        $ver = (int) $db->query('SELECT COALESCE(MAX(version), 0) + 1 FROM director_signatures')->fetchColumn();
        $db->beginTransaction();
        $db->exec('UPDATE director_signatures SET is_active = 0');
        $db->prepare('INSERT INTO director_signatures (file_path, name, title, version, is_active, check_notes, uploaded_by) VALUES (?, ?, ?, ?, 1, ?, ?)')
           ->execute([$r['path'], $name, $title, $ver, $chk['notes'] ? implode("\n", $chk['notes']) : null, (int) ($_SESSION['user_id'] ?? 0) ?: null]);
        $db->commit();
        tid_audit($db, null, 'director_signature', 'Uploaded version ' . $ver);
        return ['ok' => true, 'version' => $ver, 'check' => $chk];
    }

    /** Go back to an older version (files are never deleted, so every version stays usable). */
    function dsig_activate(PDO $db, int $id): array
    {
        $st = $db->prepare('SELECT version FROM director_signatures WHERE id = ?');
        $st->execute([$id]);
        if (!($ver = $st->fetchColumn())) return ['ok' => false, 'error' => 'Version not found.'];
        $db->beginTransaction();
        $db->exec('UPDATE director_signatures SET is_active = 0');
        $db->prepare('UPDATE director_signatures SET is_active = 1 WHERE id = ?')->execute([$id]);
        $db->commit();
        tid_audit($db, null, 'director_signature', 'Made version ' . $ver . ' active');
        return ['ok' => true];
    }

    /** Name and title printed under the signature (on the active version). */
    function dsig_save_names(PDO $db, string $name, string $title): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name)); $title = trim(preg_replace('/\s+/', ' ', $title));
        if ($name === '' || $title === '' || mb_strlen($name) > 150 || mb_strlen($title) > 80) return ['ok' => false, 'error' => 'Enter a name (up to 150 letters) and a title (up to 80).'];
        $n = $db->prepare('UPDATE director_signatures SET name = ?, title = ? WHERE is_active = 1');
        $n->execute([$name, $title]);
        if (!$n->rowCount() && !(int) $db->query('SELECT COUNT(*) FROM director_signatures WHERE is_active = 1')->fetchColumn()) return ['ok' => false, 'error' => 'Upload the signature first.'];
        tid_audit($db, null, 'director_signature', 'Name and title: ' . $name . ', ' . $title);
        return ['ok' => true];
    }

    /** Save the editable ID card wording (id_settings). */
    function idset_save(PDO $db, array $in): void
    {
        require_once __DIR__ . '/../helpers/tutor_card_pdf.php';
        $st = $db->prepare('INSERT INTO id_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
        foreach (tcard_defaults() as $k => $def) {
            if ($k === 'show_photo_on_verify') { $st->execute([$k, !empty($in[$k]) ? '1' : '0']); continue; }
            if (!array_key_exists($k, $in)) continue;
            $v = mb_substr(trim((string) $in[$k]), 0, 160);
            if ($k === 'verify_base' && !preg_match('#^https://[A-Za-z0-9.\-]+(:\d+)?(/[A-Za-z0-9._\-/]*)?$#', $v)) continue;
            $st->execute([$k, $v]);
        }
        idset_all($db, true);
        tid_audit($db, null, 'id_settings', 'ID card wording saved');
    }
}

if (!function_exists('tid_print_file')) {
    /**
     * Build the print file for the given tutors (tutors.id): only those ready to print, always in the same order
     * (ID number). Up to 30 cards → one PDF; more → a ZIP of PDFs of 30 each, so no single render runs too long.
     * $opt: layout ('single' | 'a4'), marks (bool), mark_sent (bool). Saves the file privately and records the batch.
     * @return array{ok:bool, error?:string, batch_id?:int, count?:int, skipped?:array}
     */
    function tid_print_file(PDO $db, array $tutorIds, array $opt): array
    {
        @set_time_limit(0);
        @ignore_user_abort(true);
        $layout = ($opt['layout'] ?? 'single') === 'a4' ? 'a4' : 'single';
        $marks = !empty($opt['marks']);
        $ids = array_values(array_unique(array_filter(array_map('intval', $tutorIds))));
        if (!$ids) return ['ok' => false, 'error' => 'Choose at least one tutor.'];
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $db->prepare("SELECT t.user_id FROM tutors t WHERE t.id IN ($in) ORDER BY t.staff_no, t.id");
        $st->execute($ids);
        $hasDir = (bool) dsig_active($db);
        $cards = []; $ready = []; $skipped = [];
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            $t = staff_tutor($db, (int) $uid);
            [$ok, $why] = $t ? tid_ready($db, $t, $hasDir) : [false, 'Not found'];
            if (!$ok) { $skipped[] = ($t ? tid_name($t) : '#' . $uid) . ': ' . $why; continue; }
            $cards[] = staff_card($db, $t);
            $ready[] = $t;
        }
        if (!$cards) return ['ok' => false, 'error' => 'None of the chosen tutors is ready to print.', 'skipped' => $skipped];

        $dir = storage_path('id_print');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return ['ok' => false, 'error' => 'Could not create the print folder.'];
        if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
        $stamp = (new DateTime('now', new DateTimeZone('Africa/Nairobi')))->format('Y-m-d-His');
        $pdfOpt = ['mode' => $layout, 'marks' => $marks];
        if (count($cards) <= 30) {
            $name = 'tutor-ids-' . $stamp . '.pdf';
            file_put_contents($dir . '/' . $name, tcard_pdf_bytes($cards, $pdfOpt));
        } else {
            $name = 'tutor-ids-' . $stamp . '.zip';
            $zip = new ZipArchive();
            if ($zip->open($dir . '/' . $name, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return ['ok' => false, 'error' => 'Could not create the ZIP file.'];
            foreach (array_chunk($cards, 30) as $i => $chunk) {
                $zip->addFromString(sprintf('tutor-ids-%s-part%02d.pdf', $stamp, $i + 1), tcard_pdf_bytes($chunk, $pdfOpt));
            }
            $zip->close();
        }
        $db->prepare('INSERT INTO id_print_batches (admin_id, count, options, file_path, file_name) VALUES (?, ?, ?, ?, ?)')
           ->execute([(int) ($_SESSION['user_id'] ?? 0) ?: null, count($cards), json_encode(['layout' => $layout, 'marks' => $marks, 'ids' => array_map(fn($t) => (int) $t['id'], $ready)]), 'storage:id_print/' . $name, $name]);
        $batch = (int) $db->lastInsertId();
        $item = $db->prepare('INSERT IGNORE INTO id_print_batch_items (batch_id, tutor_id) VALUES (?, ?)');
        foreach ($ready as $t) {
            $item->execute([$batch, (int) $t['id']]);
            if (!empty($opt['mark_sent']) && in_array($t['id_status'] ?? '', ['not_created', 'preview_ready'], true)) {
                $db->prepare("UPDATE tutors SET id_status = 'sent_to_print' WHERE id = ?")->execute([(int) $t['id']]);
                tid_history($db, (int) $t['id'], 'id_status', $t['id_status'], 'sent_to_print');
            }
        }
        tid_audit($db, null, 'print_download', $name . ' (' . count($cards) . ' IDs, ' . $layout . ($marks ? ', cut marks' : '') . ')');
        if (count($cards) > 30 && ($me = (int) ($_SESSION['user_id'] ?? 0))) {
            PortalNotifications::notify($db, $me, 'id_card', 'Your print file is ready', count($cards) . ' tutor IDs in ' . $name . '.', 'admin/tutors/ids.php');
        }
        return ['ok' => true, 'batch_id' => $batch, 'count' => count($cards), 'skipped' => $skipped];
    }

    /** [absolute path, file name] of a saved print batch, or null. */
    function tid_batch_file(PDO $db, int $batchId): ?array
    {
        $st = $db->prepare('SELECT file_path, file_name FROM id_print_batches WHERE id = ?');
        $st->execute([$batchId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r || !str_starts_with((string) $r['file_path'], 'storage:id_print/')) return null;
        $abs = realpath(storage_path(substr($r['file_path'], 8)));
        $base = realpath(storage_path('id_print'));
        return $abs && $base && str_starts_with($abs, $base . DIRECTORY_SEPARATOR) ? [$abs, (string) $r['file_name']] : null;
    }
}
