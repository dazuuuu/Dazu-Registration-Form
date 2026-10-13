<?php
// Assignments: private file storage, upload checks and the formatting allowlist for assignment details.
// Files live in app/v1/storage/assignments (outside public/) and are only served by public/assignments/file.php.

require_once __DIR__ . '/uploads.php';

if (!defined('ASG_IMAGE_MAX')) {
    define('ASG_IMAGE_MAX', 5 * 1024 * 1024);
    define('ASG_FILE_MAX', 10 * 1024 * 1024);
    define('ASG_MAX_IMAGES', 10);
    define('ASG_MAX_FILES', 5);
    define('ASG_MAX_ATTACHMENTS', 10);
    // Sites a tutor can restrict links to (host suffix => label).
    define('ASG_LINK_SITES', [
        'github.com' => 'GitHub', 'gitlab.com' => 'GitLab', 'docs.google.com' => 'Google Docs, Sheets, Slides',
        'drive.google.com' => 'Google Drive', 'canva.com' => 'Canva', 'figma.com' => 'Figma', 'loom.com' => 'Loom',
        'youtube.com' => 'YouTube', 'youtu.be' => 'YouTube (short link)',
    ]);
}

function asg_storage_dir(): string
{
    $dir = dirname(__DIR__) . '/storage/assignments';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
    return $dir;
}

/** Absolute path of a stored file, or null if it escapes the storage folder or is missing. */
function asg_file_abs(string $rel): ?string
{
    $base = realpath(asg_storage_dir());
    $abs = realpath($base . '/' . $rel);
    return $abs && $base && strpos($abs, $base . DIRECTORY_SEPARATOR) === 0 && is_file($abs) ? $abs : null;
}

/** Re-shape $_FILES['x'] (single or multiple) into a list of single-file arrays, skipping empty slots. */
function asg_files_list(?array $f): array
{
    if (!$f || !isset($f['name'])) return [];
    if (!is_array($f['name'])) return ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$f];
    $out = [];
    foreach ($f['name'] as $i => $n) {
        if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        $out[] = ['name' => $n, 'type' => $f['type'][$i] ?? '', 'tmp_name' => $f['tmp_name'][$i] ?? '', 'error' => $f['error'][$i], 'size' => $f['size'][$i] ?? 0];
    }
    return $out;
}

function asg_upload_error(array $file, int $max): ?string
{
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) return 'is too large for the server.';
    if ($err !== UPLOAD_ERR_OK) return 'did not upload. Please try again.';
    if (($file['size'] ?? 0) > $max) return 'is larger than ' . round($max / 1048576) . ' MB.';
    if (!defined('UPLOAD_TEST_MODE') && !is_uploaded_file($file['tmp_name'])) return 'failed the security check.';
    return null;
}

function asg_mime(string $path): string
{
    $fi = finfo_open(FILEINFO_MIME_TYPE);
    $m = (string) finfo_file($fi, $path);
    finfo_close($fi);
    return $m;
}

/**
 * Store a PNG or JPG after re-encoding it with GD (drops metadata and anything hidden in the file).
 * @return array{ok:bool, path?:string, mime?:string, bytes?:int, error?:string}
 */
function asg_store_image(array $file, string $subdir): array
{
    $label = '"' . basename((string) $file['name']) . '" ';
    if ($e = asg_upload_error($file, ASG_IMAGE_MAX)) return ['ok' => false, 'error' => $label . $e];
    $info = @getimagesize($file['tmp_name']);
    $mime = $info['mime'] ?? '';
    if (!in_array($mime, ['image/png', 'image/jpeg'], true)) return ['ok' => false, 'error' => $label . 'is not a PNG or JPG image.'];
    if (!function_exists('imagecreatefromstring')) return ['ok' => false, 'error' => 'Image processing is not available on this server.'];
    $img = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
    if (!$img) return ['ok' => false, 'error' => $label . 'could not be read as an image.'];
    $dir = asg_storage_dir() . '/' . $subdir;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return ['ok' => false, 'error' => 'Could not create the upload folder.'];
    $ext = $mime === 'image/png' ? 'png' : 'jpg';
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if ($ext === 'png') {
        imagesavealpha($img, true);
        $ok = imagepng($img, "$dir/$name", 6);
    } else {
        $ok = imagejpeg($img, "$dir/$name", 88);
    }
    if (!$ok) return ['ok' => false, 'error' => $label . 'could not be saved.'];
    return ['ok' => true, 'path' => "$subdir/$name", 'mime' => $mime, 'bytes' => (int) filesize("$dir/$name")];
}

/**
 * Store a document after checking its real type. $allowed: list of 'pdf','docx','xlsx','pptx','zip'.
 * @return array{ok:bool, path?:string, mime?:string, bytes?:int, error?:string}
 */
function asg_store_document(array $file, string $subdir, array $allowed, int $max = ASG_FILE_MAX): array
{
    $label = '"' . basename((string) $file['name']) . '" ';
    if ($e = asg_upload_error($file, $max)) return ['ok' => false, 'error' => $label . $e];
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) return ['ok' => false, 'error' => $label . 'is not allowed. Use ' . strtoupper(implode(', ', $allowed)) . '.'];
    $mime = asg_mime($file['tmp_name']);
    $office = ['docx' => 'word/', 'xlsx' => 'xl/', 'pptx' => 'ppt/'];
    $ok = false;
    if ($ext === 'pdf') {
        $ok = $mime === 'application/pdf';
    } elseif ($ext === 'zip') {
        $ok = in_array($mime, ['application/zip', 'application/x-zip-compressed'], true);
    } elseif (isset($office[$ext]) && class_exists('ZipArchive')) {
        // Office files are zip packages: check they really contain the matching part.
        $z = new ZipArchive();
        if ($z->open($file['tmp_name']) === true) {
            $ok = $z->locateName('[Content_Types].xml') !== false;
            $found = false;
            for ($i = 0; $ok && $i < $z->numFiles && !$found; $i++) {
                $found = str_starts_with((string) $z->getNameIndex($i), $office[$ext]);
            }
            $ok = $ok && $found;
            $z->close();
        }
    }
    if (!$ok) return ['ok' => false, 'error' => $label . 'is not a real ' . strtoupper($ext) . ' file.'];
    $dir = asg_storage_dir() . '/' . $subdir;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return ['ok' => false, 'error' => 'Could not create the upload folder.'];
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $moved = defined('UPLOAD_TEST_MODE') ? @copy($file['tmp_name'], "$dir/$name") : @move_uploaded_file($file['tmp_name'], "$dir/$name");
    if (!$moved) return ['ok' => false, 'error' => $label . 'could not be saved.'];
    $mimes = ['pdf' => 'application/pdf', 'zip' => 'application/zip',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'];
    return ['ok' => true, 'path' => "$subdir/$name", 'mime' => $mimes[$ext], 'bytes' => (int) filesize("$dir/$name")];
}

/** Only http(s) links; returns the cleaned URL or null. */
function asg_clean_link(?string $url, bool $httpsOnly = false): ?string
{
    $url = trim((string) $url);
    if ($url === '' || mb_strlen($url) > 500 || !filter_var($url, FILTER_VALIDATE_URL)) return null;
    return preg_match($httpsOnly ? '#^https://#i' : '#^https?://#i', $url) ? $url : null;
}

/** True when the link's host is one of the allowed site suffixes (or no list is set). */
function asg_link_site_ok(string $url, ?string $sites): bool
{
    if (!$sites) return true;
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    foreach (explode(',', $sites) as $s) {
        $s = trim($s);
        if ($s !== '' && ($host === $s || str_ends_with($host, '.' . $s))) return true;
    }
    return false;
}

/**
 * Keep only bold, italic, underline, paragraphs, line breaks, lists and http(s)/mailto links; everything else becomes text.
 */
function asg_clean_html(?string $html): string
{
    $html = trim((string) $html);
    if ($html === '') return '';
    $allowed = ['p', 'br', 'b', 'strong', 'i', 'em', 'u', 'ul', 'ol', 'li', 'a', 'div'];
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="asg-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    $root = $doc->getElementById('asg-root');
    if (!$root) return nl2br(htmlspecialchars(strip_tags($html)));

    $walk = function (DOMNode $node) use (&$walk, $allowed, $doc) {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'template'], true)) {
                    $node->removeChild($child);
                    continue;
                }
                $walk($child);
                if (!in_array($tag, $allowed, true)) {
                    while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                    $node->removeChild($child);
                    continue;
                }
                $href = $tag === 'a' ? trim($child->getAttribute('href')) : '';
                foreach (iterator_to_array($child->attributes) as $attr) $child->removeAttribute($attr->name);
                if ($tag === 'div') {
                    // contenteditable makes <div> lines; keep them as paragraphs.
                    $p = $doc->createElement('p');
                    while ($child->firstChild) $p->appendChild($child->firstChild);
                    $node->replaceChild($p, $child);
                } elseif ($tag === 'a') {
                    if (preg_match('#^(https?://|mailto:)#i', $href)) {
                        $child->setAttribute('href', $href);
                        $child->setAttribute('target', '_blank');
                        $child->setAttribute('rel', 'noopener noreferrer');
                    } else {
                        while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                        $node->removeChild($child);
                    }
                }
            } elseif (!($child instanceof DOMText)) {
                $node->removeChild($child); // comments, processing instructions
            }
        }
    };
    $walk($root);
    $out = '';
    foreach ($root->childNodes as $c) $out .= $doc->saveHTML($c);
    return mb_substr(trim($out), 0, 60000);
}

function asg_human_bytes(int $b): string
{
    return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' KB';
}
