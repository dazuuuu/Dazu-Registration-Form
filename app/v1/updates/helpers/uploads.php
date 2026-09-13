<?php
// app/v1/updates/helpers/uploads.php
//
// Security-conscious image upload + CSRF helpers, plus view helpers
// for resolving the logo / hero URLs. Safe to include more than once.

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/paths.php';
}

/* ====================================================================
   CSRF
   ==================================================================== */
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
    }

    function csrf_verify(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return !empty($_SESSION['csrf_token'])
            && is_string($token)
            && hash_equals($_SESSION['csrf_token'], $token);
    }
}

/* ====================================================================
   IMAGE UPLOAD
   ==================================================================== */
if (!function_exists('validate_image_upload')) {
    /**
     * Pure validation (no filesystem move) — easy to unit test.
     * @return array{ok:bool, ext?:string, error?:string}
     */
    function validate_image_upload(
        array $file,
        array $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'],
        int $maxBytes = 26214400        // 25 MB
    ): array {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['ok' => false, 'error' => 'Invalid upload parameters.'];
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return ['ok' => false, 'error' => 'No file was selected.'];
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['ok' => false, 'error' => 'The file is too large for the server upload limit.'];
            default:
                return ['ok' => false, 'error' => 'Upload failed (code ' . $file['error'] . ').'];
        }

        if (($file['size'] ?? 0) > $maxBytes) {
            return ['ok' => false, 'error' => 'File exceeds ' . round($maxBytes / 1048576, 1) . ' MB.'];
        }

        $mimeToExt = [
            'image/jpeg'    => 'jpg',
            'image/png'     => 'png',
            'image/webp'    => 'webp',
            'image/gif'     => 'gif',
            'image/avif'    => 'avif',
            'image/svg+xml' => 'svg',
        ];

        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = (string) @finfo_file($finfo, $file['tmp_name']);
                @finfo_close($finfo);
            }
        }

        // getimagesize reliably validates raster images; SVG needs MIME + XML checks.
        if ($mime === 'image/svg+xml' || strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)) === 'svg') {
            $bytes = @file_get_contents($file['tmp_name']);
            if ($bytes === false || stripos($bytes, '<svg') === false || preg_match('/<script\b/i', $bytes)) {
                return ['ok' => false, 'error' => 'That SVG file is not valid.'];
            }
            $mime = 'image/svg+xml';
        } else {
            $info = @getimagesize($file['tmp_name']);
            if ($info === false) {
                return ['ok' => false, 'error' => 'That file is not a valid image.'];
            }
            $mime = $info['mime'] ?? $mime;
        }

        if (!isset($mimeToExt[$mime])) {
            return ['ok' => false, 'error' => 'Unsupported image type. Use JPG, PNG, WebP, AVIF, GIF or SVG.'];
        }

        $ext = $mimeToExt[$mime];
        if (!in_array($ext, $allowedExt, true)) {
            return ['ok' => false, 'error' => 'This image type is not allowed here.'];
        }

        return ['ok' => true, 'ext' => $ext];
    }
}

if (!function_exists('upload_image')) {
    /**
     * Validate, optimize (if raster) and store the uploaded image.
     * Large raster images are automatically scaled down to max 1920px and
     * compressed into lightweight WebP for lightning-fast site delivery.
     *
     * @param string $absDir  absolute destination dir
     * @param string $webDir  web path (relative to /public) saved in DB, no trailing slash
     * @return array{ok:bool, path?:string, error?:string}
     */
    function upload_image(
        array $file,
        string $absDir,
        string $webDir,
        array $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'],
        int $maxBytes = 26214400
    ): array {
        $v = validate_image_upload($file, $allowedExt, $maxBytes);
        if (!$v['ok']) {
            return $v;
        }

        // In a real request the temp file must be an HTTP upload.
        // Tests define UPLOAD_TEST_MODE to exercise the move with copy().
        if (!defined('UPLOAD_TEST_MODE') && !is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'Security check failed on the uploaded file.'];
        }

        if (!is_dir($absDir) && !@mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            return ['ok' => false, 'error' => 'Could not create the upload folder.'];
        }

        $ext = $v['ext'];
        $canOptimize = extension_loaded('gd') && in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'avif'], true);

        if ($canOptimize) {
            $raw = @file_get_contents($file['tmp_name']);
            $srcImg = $raw !== false ? @imagecreatefromstring($raw) : false;
            if ($srcImg !== false) {
                $srcW = imagesx($srcImg);
                $srcH = imagesy($srcImg);

                // Downscale if wider or taller than 1920px
                $maxDim = 1920;
                $targetW = $srcW;
                $targetH = $srcH;
                if ($srcW > $maxDim || $srcH > $maxDim) {
                    if ($srcW >= $srcH) {
                        $targetW = $maxDim;
                        $targetH = (int) round(($srcH * $maxDim) / $srcW);
                    } else {
                        $targetH = $maxDim;
                        $targetW = (int) round(($srcW * $maxDim) / $srcH);
                    }
                }

                $dstImg = imagecreatetruecolor($targetW, $targetH);
                imagealphablending($dstImg, false);
                imagesavealpha($dstImg, true);
                $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
                imagefilledrectangle($dstImg, 0, 0, $targetW, $targetH, $transparent);
                imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);

                $outExt = function_exists('imagewebp') ? 'webp' : ($ext === 'png' ? 'png' : 'jpg');
                $name = time() . '_' . bin2hex(random_bytes(6)) . '.' . $outExt;
                $dest = rtrim($absDir, '/\\') . DIRECTORY_SEPARATOR . $name;

                $saved = false;
                if ($outExt === 'webp') {
                    $saved = @imagewebp($dstImg, $dest, 85);
                } elseif ($outExt === 'png') {
                    $saved = @imagepng($dstImg, $dest, 7);
                } else {
                    $saved = @imagejpeg($dstImg, $dest, 85);
                }

                if ($saved) {
                    return ['ok' => true, 'path' => rtrim($webDir, '/') . '/' . $name];
                }
            }
        }

        // Standard move/copy fallback
        $name = time() . '_' . bin2hex(random_bytes(6)) . '.' . $v['ext'];
        $dest = rtrim($absDir, '/\\') . DIRECTORY_SEPARATOR . $name;

        $moved = defined('UPLOAD_TEST_MODE')
            ? @copy($file['tmp_name'], $dest)
            : @move_uploaded_file($file['tmp_name'], $dest);

        if (!$moved) {
            return ['ok' => false, 'error' => 'Could not save the uploaded file.'];
        }

        return ['ok' => true, 'path' => rtrim($webDir, '/') . '/' . $name];
    }
}

if (!function_exists('validate_pdf_upload')) {
    /**
     * @return array{ok:bool, error?:string}
     */
    function validate_pdf_upload(array $file, int $maxBytes = 10485760): array
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['ok' => false, 'error' => 'Invalid upload parameters.'];
        }
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'No file was selected.'];
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Upload failed.'];
        }
        if (($file['size'] ?? 0) > $maxBytes) {
            return ['ok' => false, 'error' => 'PDF exceeds ' . round($maxBytes / 1048576, 1) . ' MB.'];
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if ($mime !== 'application/pdf') {
            return ['ok' => false, 'error' => 'Only PDF files are accepted.'];
        }
        return ['ok' => true];
    }
}

if (!function_exists('upload_pdf')) {
    /**
     * @return array{ok:bool, path?:string, error?:string}
     */
    function upload_pdf(array $file, string $absDir, string $webDir, int $maxBytes = 10485760): array
    {
        $v = validate_pdf_upload($file, $maxBytes);
        if (!$v['ok']) {
            return $v;
        }
        if (!defined('UPLOAD_TEST_MODE') && !is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'Security check failed on the uploaded file.'];
        }
        if (!is_dir($absDir) && !@mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            return ['ok' => false, 'error' => 'Could not create the upload folder.'];
        }
        $name = time() . '_' . bin2hex(random_bytes(6)) . '.pdf';
        $dest = rtrim($absDir, '/\\') . DIRECTORY_SEPARATOR . $name;
        $moved = defined('UPLOAD_TEST_MODE')
            ? @copy($file['tmp_name'], $dest)
            : @move_uploaded_file($file['tmp_name'], $dest);
        if (!$moved) {
            return ['ok' => false, 'error' => 'Could not save the uploaded file.'];
        }
        return ['ok' => true, 'path' => rtrim($webDir, '/') . '/' . $name];
    }
}

if (!function_exists('validate_video_upload')) {
    /**
     * @return array{ok:bool, ext?:string, error?:string}
     */
    function validate_video_upload(array $file, int $maxBytes = 52428800): array
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['ok' => false, 'error' => 'Invalid upload parameters.'];
        }
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'No file was selected.'];
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Upload failed.'];
        }
        if (($file['size'] ?? 0) > $maxBytes) {
            return ['ok' => false, 'error' => 'Video exceeds ' . round($maxBytes / 1048576, 1) . ' MB.'];
        }

        $mimeToExt = [
            'video/mp4'       => 'mp4',
            'video/webm'      => 'webm',
            'video/quicktime' => 'mov',
        ];
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = (string) @finfo_file($finfo, $file['tmp_name']);
                @finfo_close($finfo);
            }
        }
        if (!isset($mimeToExt[$mime])) {
            return ['ok' => false, 'error' => 'Unsupported video type. Use MP4, WebM or MOV.'];
        }
        return ['ok' => true, 'ext' => $mimeToExt[$mime]];
    }
}

if (!function_exists('upload_video')) {
    /**
     * @return array{ok:bool, path?:string, error?:string}
     */
    function upload_video(array $file, string $absDir, string $webDir, int $maxBytes = 52428800): array
    {
        $v = validate_video_upload($file, $maxBytes);
        if (!$v['ok']) {
            return $v;
        }
        if (!defined('UPLOAD_TEST_MODE') && !is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'Security check failed on the uploaded file.'];
        }
        if (!is_dir($absDir) && !@mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            return ['ok' => false, 'error' => 'Could not create the upload folder.'];
        }
        $name = time() . '_' . bin2hex(random_bytes(6)) . '.' . $v['ext'];
        $dest = rtrim($absDir, '/\\') . DIRECTORY_SEPARATOR . $name;
        $moved = defined('UPLOAD_TEST_MODE')
            ? @copy($file['tmp_name'], $dest)
            : @move_uploaded_file($file['tmp_name'], $dest);
        if (!$moved) {
            return ['ok' => false, 'error' => 'Could not save the uploaded file.'];
        }
        return ['ok' => true, 'path' => rtrim($webDir, '/') . '/' . $name];
    }
}

/* ====================================================================
   VIEW HELPERS
   ==================================================================== */
if (!function_exists('settings_asset_url')) {
    /** Turn a stored relative path (uploads/hero/x.png) into a full web URL with cache busting. */
    function settings_asset_url(?string $relPath): ?string
    {
        if ($relPath === null || $relPath === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $relPath)) {
            return $relPath;
        }

        $cleanRel = ltrim(preg_replace('#^/Registration-System/public/#', '', $relPath), '/');
        if (defined('WEB_ROOT') && WEB_ROOT !== '' && str_starts_with($cleanRel, ltrim(WEB_ROOT, '/') . '/')) {
            $cleanRel = substr($cleanRel, strlen(ltrim(WEB_ROOT, '/') . '/'));
        }

        $fullUrl = function_exists('url') ? url($cleanRel) : (rtrim(BASE_URL, '/') . '/' . $cleanRel);

        $abs = function_exists('public_path') ? public_path($cleanRel) : null;
        if ($abs && is_file($abs)) {
            $mtime = @filemtime($abs);
            if ($mtime) {
                $fullUrl .= (str_contains($fullUrl, '?') ? '&' : '?') . 'v=' . $mtime;
            }
        }

        return $fullUrl;
    }
}
