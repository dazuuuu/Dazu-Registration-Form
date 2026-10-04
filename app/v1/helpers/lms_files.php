<?php
// Uploads for the tutor course builder (materials, videos, student answers) and personal documents.
// Course files go under public/uploads/<dir>/ with random names and an extension whitelist.
// Personal documents are kept outside the web root (app/v1/storage/documents) and served by documents/file.php.

require_once __DIR__ . '/uploads.php';

if (!defined('LMS_MATERIAL_EXTS')) {
    define('LMS_MATERIAL_EXTS', ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip', 'jpg', 'jpeg', 'png', 'webp', 'mp3', 'mp4']);
    // A "folder" is submitted as a .zip (browsers cannot upload a folder as one file).
    define('LMS_ANSWER_EXTS', ['zip', 'rar', '7z', 'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'sql', 'py', 'js', 'html', 'css']);
    define('LMS_FILE_MAX_BYTES', 50 * 1024 * 1024);
    define('DOC_MAX_BYTES', 10 * 1024 * 1024);
    define('TSHIRT_SIZES', ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL']);
}

if (!function_exists('lms_store_upload')) {
    /**
     * Save one uploaded file under public/uploads/$dir. Script-like files are never stored with an executable name.
     * @return array{ok:bool, path?:string, error?:string}
     */
    function lms_store_upload(array $file, string $dir, array $exts, int $maxBytes = LMS_FILE_MAX_BYTES): array
    {
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) return ['ok' => false, 'error' => 'No file was selected.'];
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) return ['ok' => false, 'error' => 'The file is too large for the server.'];
        if ($err !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => 'Upload failed. Please try again.'];
        if (($file['size'] ?? 0) > $maxBytes) return ['ok' => false, 'error' => 'File exceeds ' . round($maxBytes / 1048576) . ' MB.'];
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $exts, true)) return ['ok' => false, 'error' => 'Allowed file types: ' . implode(', ', $exts) . '.'];
        if (!defined('UPLOAD_TEST_MODE') && !is_uploaded_file($file['tmp_name'])) return ['ok' => false, 'error' => 'Security check failed on the uploaded file.'];

        // Source-code answers are stored as .txt so the web server never runs or renders them.
        $safeExt = in_array($ext, ['py', 'js', 'html', 'css', 'sql'], true) ? $ext . '.txt' : $ext;
        $abs = dirname(__DIR__, 3) . '/public/uploads/' . $dir;
        if (!is_dir($abs) && !@mkdir($abs, 0775, true) && !is_dir($abs)) return ['ok' => false, 'error' => 'Could not create the upload folder.'];
        $name = time() . '_' . bin2hex(random_bytes(6)) . '.' . $safeExt;
        $moved = defined('UPLOAD_TEST_MODE') ? @copy($file['tmp_name'], "$abs/$name") : @move_uploaded_file($file['tmp_name'], "$abs/$name");
        return $moved ? ['ok' => true, 'path' => 'uploads/' . $dir . '/' . $name] : ['ok' => false, 'error' => 'Could not save the uploaded file.'];
    }
}

if (!function_exists('lms_video_embed')) {
    /** YouTube / Vimeo link → embeddable URL, or null for other links. */
    function lms_video_embed(?string $url): ?string
    {
        $url = trim((string) $url);
        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }
        return null;
    }
}

if (!function_exists('lms_clean_url')) {
    /** Only http(s) links are kept (blocks javascript: and similar). */
    function lms_clean_url(?string $url): ?string
    {
        $url = trim((string) $url);
        return $url !== '' && preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
}

/* ---------- Personal documents, passport photo, T-shirt size ---------- */

if (!function_exists('docs_storage_dir')) {
    function docs_storage_dir(): string
    {
        return dirname(__DIR__) . '/storage/documents';
    }

    function docs_list(PDO $db, int $userId): array
    {
        $st = $db->prepare("SELECT * FROM personal_documents WHERE user_id = ? ORDER BY uploaded_at DESC, id DESC");
        $st->execute([$userId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    function docs_profile(PDO $db, int $userId): array
    {
        $st = $db->prepare("SELECT profile_photo_path, profile_photo_status, tshirt_size FROM user_profiles WHERE user_id = ?");
        $st->execute([$userId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: ['profile_photo_path' => null, 'profile_photo_status' => null, 'tshirt_size' => null];
    }

    /**
     * Save several PDFs at once. $files is the $_FILES['documents'] array (multiple), $names the matching names.
     * @return array{saved:int, errors:string[]}
     */
    function docs_save_many(PDO $db, int $userId, array $files, array $names): array
    {
        $saved = 0;
        $errors = [];
        $dir = docs_storage_dir() . '/' . $userId;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['saved' => 0, 'errors' => ['Could not create the document folder.']];
        }
        // In case the app folder is ever reachable from the web, files are only served through documents/file.php.
        if (!is_file(docs_storage_dir() . '/.htaccess')) {
            @file_put_contents(docs_storage_dir() . '/.htaccess', "Require all denied\nDeny from all\n");
        }
        foreach ((array) ($files['name'] ?? []) as $i => $orig) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            $one = ['name' => $orig, 'type' => $files['type'][$i] ?? '', 'tmp_name' => $files['tmp_name'][$i] ?? '',
                    'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE, 'size' => $files['size'][$i] ?? 0];
            $label = trim((string) ($names[$i] ?? ''));
            if ($label === '') {
                $errors[] = '"' . $orig . '": give the document a name.';
                continue;
            }
            $v = validate_pdf_upload($one, DOC_MAX_BYTES);
            if (!$v['ok']) {
                $errors[] = '"' . $orig . '": ' . $v['error'];
                continue;
            }
            $name = time() . '_' . bin2hex(random_bytes(6)) . '.pdf';
            $moved = defined('UPLOAD_TEST_MODE')
                ? @copy($one['tmp_name'], "$dir/$name")
                : is_uploaded_file($one['tmp_name']) && @move_uploaded_file($one['tmp_name'], "$dir/$name");
            if (!$moved) {
                $errors[] = '"' . $orig . '": could not be saved.';
                continue;
            }
            $db->prepare("INSERT INTO personal_documents (user_id, doc_name, file_path, original_name, file_size) VALUES (?, ?, ?, ?, ?)")
               ->execute([$userId, mb_substr($label, 0, 150), $userId . '/' . $name, mb_substr((string) $orig, 0, 255), (int) $one['size']]);
            $saved++;
        }
        return ['saved' => $saved, 'errors' => $errors];
    }

    function docs_delete(PDO $db, int $userId, int $docId): void
    {
        $st = $db->prepare("SELECT file_path FROM personal_documents WHERE id = ? AND user_id = ?");
        $st->execute([$docId, $userId]);
        if ($path = $st->fetchColumn()) {
            @unlink(docs_storage_dir() . '/' . $path);
            $db->prepare("DELETE FROM personal_documents WHERE id = ?")->execute([$docId]);
        }
    }

    function docs_save_tshirt(PDO $db, int $userId, string $size): bool
    {
        if (!in_array($size, TSHIRT_SIZES, true)) return false;
        $db->prepare("INSERT INTO user_profiles (user_id, tshirt_size) VALUES (?, ?) ON DUPLICATE KEY UPDATE tshirt_size = VALUES(tshirt_size)")
           ->execute([$userId, $size]);
        return true;
    }

    /** Passport photo: same field the certificate verify page uses. */
    function docs_save_photo(PDO $db, int $userId, array $file): array
    {
        require_once dirname(__DIR__) . '/services/CertificateService.php';
        return cert_save_student_photo($db, $userId, $file);
    }

    /**
     * Handle a POST from the "My documents" page (upload / delete / photo / T-shirt) and redirect back.
     * Messages go to $_SESSION['success_msg'] / ['error_msg'].
     */
    function docs_handle_post(PDO $db, int $userId, string $back): void
    {
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            $_SESSION['error_msg'] = 'Security check failed. Please refresh and try again.';
        } else {
            switch ($_POST['docs_action'] ?? '') {
                case 'upload':
                    $r = docs_save_many($db, $userId, $_FILES['documents'] ?? [], (array) ($_POST['doc_names'] ?? []));
                    if ($r['saved']) $_SESSION['success_msg'] = $r['saved'] . ' document' . ($r['saved'] > 1 ? 's' : '') . ' submitted.';
                    if ($r['errors']) $_SESSION['error_msg'] = implode(' ', $r['errors']);
                    if (!$r['saved'] && !$r['errors']) $_SESSION['error_msg'] = 'Choose at least one PDF.';
                    break;
                case 'delete':
                    docs_delete($db, $userId, (int) ($_POST['doc_id'] ?? 0));
                    $_SESSION['success_msg'] = 'Document removed.';
                    break;
                case 'photo':
                    $r = docs_save_photo($db, $userId, $_FILES['photo'] ?? []);
                    $_SESSION[$r['ok'] ? 'success_msg' : 'error_msg'] = $r['ok'] ? 'Passport photo saved.' : $r['error'];
                    break;
                case 'tshirt':
                    $ok = docs_save_tshirt($db, $userId, (string) ($_POST['tshirt_size'] ?? ''));
                    $_SESSION[$ok ? 'success_msg' : 'error_msg'] = $ok ? 'T-shirt size saved.' : 'Choose a T-shirt size.';
                    break;
            }
        }
        header('Location: ' . $back);
        exit;
    }

    /** True when the person still has to provide their passport photo or T-shirt size (or any document). */
    function docs_missing(PDO $db, int $userId): array
    {
        $p = docs_profile($db, $userId);
        $missing = [];
        if (empty($p['profile_photo_path'])) $missing[] = 'passport photo';
        if (empty($p['tshirt_size'])) $missing[] = 'T-shirt size';
        $st = $db->prepare("SELECT COUNT(*) FROM personal_documents WHERE user_id = ?");
        $st->execute([$userId]);
        if (!(int) $st->fetchColumn()) $missing[] = 'academic documents';
        return $missing;
    }
}
