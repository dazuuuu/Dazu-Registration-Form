<?php
// Blog cover image upload — only real JPG/PNG/WebP/GIF images are accepted (never trust the file name).

if (!function_exists('blog_save_cover')) {
    /** @return array{ok:bool, url?:string, error?:string, none?:bool} */
    function blog_save_cover(?array $file): array
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'none' => true, 'error' => 'A cover image is required.'];
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'The cover image could not be uploaded (it may be too large).'];
        }
        if ($file['size'] > 20 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'The cover image must be smaller than 20 MB.'];
        }
        require_once __DIR__ . '/uploads.php';
        // Resized and compressed like every other upload, so the blog pages stay light.
        $up = upload_image($file, public_path('uploads/blogs'), url('uploads/blogs'), ['jpg', 'jpeg', 'png', 'webp', 'gif'], 20 * 1024 * 1024);
        if (!$up['ok']) {
            return ['ok' => false, 'error' => $up['error'] ?? 'Failed to save the cover image.'];
        }
        return ['ok' => true, 'url' => $up['path']];
    }
}

if (!function_exists('blog_inline_images_to_files')) {
    /**
     * Pasted images arrive as huge base64 "data:image/..." strings inside the post (several MB each), which makes saving
     * time out and every visit slow. Save each one as a compressed file and point the <img> at it instead.
     */
    function blog_inline_images_to_files(string $html): string
    {
        if (stripos($html, 'data:image/') === false) {
            return $html;
        }
        require_once __DIR__ . '/uploads.php';
        return (string) preg_replace_callback('#data:image/(png|jpe?g|webp|gif);base64,([A-Za-z0-9+/=\s]+)#i', static function ($m) {
            $bin = base64_decode(preg_replace('/\s+/', '', $m[2]), true);
            if ($bin === false || strlen($bin) > 20 * 1024 * 1024) return $m[0];
            $tmp = tempnam(sys_get_temp_dir(), 'blg');
            file_put_contents($tmp, $bin);
            if (!defined('UPLOAD_TEST_MODE')) define('UPLOAD_TEST_MODE', true);   // a file we wrote ourselves, not an HTTP upload
            $up = upload_image(['name' => 'inline.' . strtolower($m[1]), 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($bin)],
                public_path('uploads/blogs/content'), url('uploads/blogs/content'), ['jpg', 'jpeg', 'png', 'webp', 'gif'], 20 * 1024 * 1024);
            @unlink($tmp);
            return $up['ok'] ? $up['path'] : $m[0];
        }, $html);
    }
}
