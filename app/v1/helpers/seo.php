<?php
// Open Graph / Twitter preview images for public pages.

require_once __DIR__ . '/course_share.php';
require_once __DIR__ . '/site_logo.php';

if (!function_exists('seo_default_image')) {
    function seo_default_image(): string
    {
        return site_logo_url('https://i.ibb.co/fzfZFtGc/223025.jpg');
    }
}

if (!function_exists('seo_current_url')) {
    function seo_current_url(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return course_share_absolute_url($uri);
    }
}

if (!function_exists('seo_image_url')) {
    /**
     * Turn a stored path, relative URL, or already-absolute URL into an absolute
     * image URL suitable for og:image / twitter:image.
     */
    function seo_image_url(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '' || $path === 'null') {
            return null;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        if (function_exists('settings_asset_url')) {
            $resolved = settings_asset_url($path);
            if (is_string($resolved) && $resolved !== '') {
                $path = $resolved;
            }
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return course_share_absolute_url($path);
    }
}

if (!function_exists('seo_head_tags')) {
    function seo_head_tags(?string $title, ?string $description, ?string $image = null, ?string $url = null): string
    {
        $title = $title !== null && $title !== '' ? $title : 'Dazu hub Institute';
        $description = $description !== null && $description !== ''
            ? $description
            : 'Practical tech training in Thika Town — software, cybersecurity, data analytics and AI.';
        $image = seo_image_url($image) ?: seo_default_image();
        $url = $url ?: seo_current_url();

        $e = static function (string $v): string {
            return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        };

        return
            '<meta property="og:site_name" content="Dazu hub Institute">' . "\n"
            . '<meta property="og:type" content="website">' . "\n"
            . '<meta property="og:title" content="' . $e($title) . '">' . "\n"
            . '<meta property="og:description" content="' . $e($description) . '">' . "\n"
            . '<meta property="og:url" content="' . $e($url) . '">' . "\n"
            . '<meta property="og:image" content="' . $e($image) . '">' . "\n"
            . '<meta property="og:image:alt" content="' . $e($title) . '">' . "\n"
            . '<meta name="twitter:card" content="summary_large_image">' . "\n"
            . '<meta name="twitter:title" content="' . $e($title) . '">' . "\n"
            . '<meta name="twitter:description" content="' . $e($description) . '">' . "\n"
            . '<meta name="twitter:image" content="' . $e($image) . '">' . "\n";
    }
}
