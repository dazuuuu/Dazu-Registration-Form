<?php
// Signatures (tutors and directors) for certificates. Uploads are re-encoded with GD, the paper background is made
// transparent and the empty margin trimmed, then saved privately in app/v1/storage/signatures. Saved paths look like
// "storage:signatures/<random>.png"; sig_abs() turns them (or an older public path) into a real file.

if (!function_exists('sig_abs')) {
    function sig_abs(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') return null;
        if (str_starts_with($path, 'storage:')) {
            $base = realpath(storage_path('signatures'));
            $abs = realpath(storage_path(substr($path, 8)));
            return $abs && $base && str_starts_with($abs, $base . DIRECTORY_SEPARATOR) && is_file($abs) ? $abs : null;
        }
        $abs = dirname(__DIR__, 3) . '/public/' . ltrim(preg_replace('#^/?Registration-System/public/#', '', $path), '/');
        return is_file($abs) ? $abs : null;
    }

    /** Inline image for showing a saved signature on a page (they are small and never served as files). */
    function sig_data_uri(?string $path): ?string
    {
        $abs = sig_abs($path);
        return $abs ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($abs)) : null;
    }

    /**
     * Store an uploaded signature (PNG or JPG photo/scan of the signature on white paper).
     * @return array{ok:bool, path?:string, error?:string}
     */
    function sig_store(array $file): array
    {
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) return ['ok' => false, 'error' => 'Choose an image of the signature.'];
        if ($err !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 5 * 1048576) return ['ok' => false, 'error' => 'The image did not upload or is larger than 5 MB.'];
        if (!defined('UPLOAD_TEST_MODE') && !is_uploaded_file($file['tmp_name'])) return ['ok' => false, 'error' => 'The upload failed the security check.'];
        $info = @getimagesize($file['tmp_name']);
        if (!in_array($info['mime'] ?? '', ['image/png', 'image/jpeg'], true)) return ['ok' => false, 'error' => 'Upload a PNG or JPG image.'];
        if (!function_exists('imagecreatefromstring')) return ['ok' => false, 'error' => 'Image processing is not available on this server.'];
        $src = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
        if (!$src) return ['ok' => false, 'error' => 'The image could not be read.'];

        // Scale down big photos so the work below stays quick.
        $w = imagesx($src); $h = imagesy($src);
        $scale = min(1, 1400 / max($w, $h));
        if ($scale < 1) {
            $src = imagescale($src, (int) round($w * $scale), (int) round($h * $scale)) ?: $src;
            $w = imagesx($src); $h = imagesy($src);
        }

        // Ink = clearly darker than the paper. Paper brightness is taken from the image itself (photos are rarely pure white).
        $sum = 0; $n = 0;
        for ($y = 0; $y < $h; $y += 7) for ($x = 0; $x < $w; $x += 7) {
            $c = imagecolorsforindex($src, imagecolorat($src, $x, $y));
            $sum += ($c['red'] + $c['green'] + $c['blue']) / 3 * (1 - $c['alpha'] / 127) + 255 * ($c['alpha'] / 127);
            $n++;
        }
        $paper = $n ? $sum / $n : 255;
        $cut = max(60, $paper - 55);

        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        $minX = $w; $minY = $h; $maxX = -1; $maxY = -1;
        for ($y = 0; $y < $h; $y++) for ($x = 0; $x < $w; $x++) {
            $c = imagecolorsforindex($src, imagecolorat($src, $x, $y));
            if ($c['alpha'] > 100) continue;
            $lum = ($c['red'] + $c['green'] + $c['blue']) / 3;
            if ($lum >= $cut) continue;
            // Darker ink → more opaque; soft edges keep the stroke smooth.
            $alpha = (int) round(127 * min(1, max(0, ($lum - ($cut - 70)) / 70)));
            imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, (int) ($c['red'] * .6), (int) ($c['green'] * .6), (int) ($c['blue'] * .6), $alpha));
            if ($x < $minX) $minX = $x; if ($x > $maxX) $maxX = $x;
            if ($y < $minY) $minY = $y; if ($y > $maxY) $maxY = $y;
        }
        if ($maxX < 0 || ($maxX - $minX) < 20) return ['ok' => false, 'error' => 'No signature found in the image. Sign in dark ink on plain white paper and take a clear, close photo.'];

        $pad = 8;
        $minX = max(0, $minX - $pad); $minY = max(0, $minY - $pad);
        $cw = min($w, $maxX + $pad + 1) - $minX; $ch = min($h, $maxY + $pad + 1) - $minY;
        $crop = imagecreatetruecolor($cw, $ch);
        imagealphablending($crop, false);
        imagesavealpha($crop, true);
        imagecopy($crop, $out, 0, 0, $minX, $minY, $cw, $ch);

        $dir = storage_path('signatures');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return ['ok' => false, 'error' => 'Could not create the signatures folder.'];
        if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
        $name = bin2hex(random_bytes(16)) . '.png';
        if (!imagepng($crop, $dir . '/' . $name, 6)) return ['ok' => false, 'error' => 'The signature could not be saved.'];
        return ['ok' => true, 'path' => 'storage:signatures/' . $name];
    }

    /** Delete a stored signature file (only ones in private storage). */
    function sig_delete(?string $path): void
    {
        if ($path && str_starts_with($path, 'storage:') && ($abs = sig_abs($path))) @unlink($abs);
    }

    /** The director who signs certificates, or null. */
    function sig_certificate_director(PDO $db): ?array
    {
        try {
            $r = $db->query('SELECT * FROM directors WHERE is_active = 1 AND on_certificates = 1 ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            return $r ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}
