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
        if ($file['size'] > 8 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'The cover image must be smaller than 8 MB.'];
        }
        $info = @getimagesize($file['tmp_name']);
        $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'][$info[2] ?? 0] ?? null;
        if (!$ext) {
            return ['ok' => false, 'error' => 'The cover must be a JPG, PNG, WebP or GIF image.'];
        }
        $dir = public_path('uploads/blogs');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Cannot create the uploads/blogs folder.'];
        }
        $name = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . DIRECTORY_SEPARATOR . $name)) {
            return ['ok' => false, 'error' => 'Failed to save the cover image.'];
        }
        return ['ok' => true, 'url' => url('uploads/blogs/' . $name)];
    }
}
