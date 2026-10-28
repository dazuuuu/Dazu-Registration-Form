<?php
// Open Graph / Twitter preview images for public pages.

require_once __DIR__ . '/course_share.php';
require_once __DIR__ . '/site_logo.php';

if (!function_exists('seo_default_image')) {
    function seo_default_image(): string
    {
        return course_share_absolute_url(url('assets/images/share/dazu-share.png'));
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
        if (!preg_match('#^https?://#i', $path) && function_exists('settings_asset_url')) {
            $resolved = settings_asset_url($path);
            if (is_string($resolved) && $resolved !== '') {
                $path = $resolved;
            }
        }
        if (!preg_match('#^https?://#i', $path)) {
            $path = course_share_absolute_url($path);
        } elseif (course_share_request_is_https()) {
            $path = (string) preg_replace('#^http://#i', 'https://', $path);
        }
        return $path;
    }
}

if (!function_exists('seo_head_tags')) {
    function seo_head_tags(?string $title, ?string $description, ?string $image = null, ?string $url = null): string
    {
        $title = $title !== null && $title !== '' ? $title : 'Dazu Training Institute';
        $description = $description !== null && $description !== ''
            ? $description
            : 'Practical tech training in Thika Town — software, cybersecurity, data analytics and AI.';
        $image = seo_image_url($image) ?: seo_default_image();
        $url = $url ?: seo_current_url();

        $e = static function (string $v): string {
            return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        };

        // The canonical link is added for public pages only by dazu_head_meta() (the page's own clean https address);
        // $url here is the address to share, which can differ (e.g. a course's share link).
        return
            '<meta property="og:site_name" content="Dazu Training Institute">' . "\n"
            . '<meta property="og:type" content="website">' . "\n"
            . '<meta property="og:title" content="' . $e($title) . '">' . "\n"
            . '<meta property="og:description" content="' . $e($description) . '">' . "\n"
            . '<meta property="og:url" content="' . $e($url) . '">' . "\n"
            . '<meta property="og:image" content="' . $e($image) . '">' . "\n"
            . '<meta property="og:image:url" content="' . $e($image) . '">' . "\n"
            . '<meta property="og:image:secure_url" content="' . $e($image) . '">' . "\n"
            . '<meta property="og:image:alt" content="' . $e($title) . '">' . "\n"
            . '<meta property="og:image:width" content="1200">' . "\n"
            . '<meta property="og:image:height" content="630">' . "\n"
            . '<meta name="twitter:card" content="summary_large_image">' . "\n"
            . '<meta name="twitter:title" content="' . $e($title) . '">' . "\n"
            . '<meta name="twitter:description" content="' . $e($description) . '">' . "\n"
            . '<meta name="twitter:image" content="' . $e($image) . '">' . "\n"
            . '<link rel="image_src" href="' . $e($image) . '">' . "\n"
            . '<meta itemprop="image" content="' . $e($image) . '">' . "\n";
    }
}

if (!function_exists('seo_page_key_from_request')) {
    function seo_page_key_from_request(): string
    {
        $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
        $path = preg_replace('#/index\.php$#', '', $path) ?: '';
        $first = explode('/', $path)[0] ?? '';
        return $first === '' ? 'home' : strtolower((string) $first);
    }
}

if (!function_exists('seo_home_hero_image')) {
    function seo_home_hero_image(): ?string
    {
        try {
            if (!class_exists('SettingModel')) {
                require_once __DIR__ . '/../models/SettingModel.php';
            }
            if (!function_exists('settings_asset_url')) {
                require_once __DIR__ . '/uploads.php';
            }
            global $pdo;
            if (!isset($pdo) || !$pdo instanceof PDO) {
                require_once __DIR__ . '/../config/db_connect.php';
            }
            if (!isset($pdo) || !$pdo instanceof PDO) {
                return null;
            }
            $slides = (new SettingModel($pdo))->getHeroSlides(true);
            foreach ($slides as $slide) {
                $path = trim((string) ($slide['image_path'] ?? ''));
                $ext = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));
                $isVideo = (($slide['media_type'] ?? '') === 'video') || in_array($ext, ['mp4', 'webm', 'mov'], true);
                if ($path !== '' && !$isVideo) {
                    return seo_image_url(settings_asset_url($path));
                }
            }
        } catch (Throwable $e) {
            return null;
        }
        return null;
    }
}

if (!function_exists('seo_page_header_image')) {
    function seo_page_header_image(string $pageKey): ?string
    {
        try {
            if (!class_exists('SettingModel')) {
                require_once __DIR__ . '/../models/SettingModel.php';
            }
            if (!function_exists('settings_asset_url')) {
                require_once __DIR__ . '/uploads.php';
            }
            global $pdo;
            if (!isset($pdo) || !$pdo instanceof PDO) {
                require_once __DIR__ . '/../config/db_connect.php';
            }
            if (!isset($pdo) || !$pdo instanceof PDO) {
                return null;
            }
            $header = (new SettingModel($pdo))->getPageHeader($pageKey);
            $image = trim((string) ($header['image_path'] ?? ''));
            return $image !== '' ? seo_image_url(settings_asset_url($image)) : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('seo_public_preview_image')) {
    function seo_public_preview_image(?string $pageKey = null): ?string
    {
        $pageKey = $pageKey !== null && $pageKey !== '' ? $pageKey : seo_page_key_from_request();
        if ($pageKey === 'home') {
            return seo_home_hero_image();
        }
        return seo_page_header_image($pageKey);
    }
}

if (!function_exists('seo_is_social_crawler')) {
    function seo_is_social_crawler(): bool
    {
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($ua === '') {
            return false;
        }
        return (bool) preg_match(
            '/facebookexternalhit|Facebot|Twitterbot|WhatsApp|TelegramBot|Slackbot|LinkedInBot|Pinterest|Discordbot|Iframely|Googlebot|bingbot|Applebot/i',
            $ua
        );
    }
}

if (!function_exists('seo_emit_course_share_preview')) {
    /** Minimal HTML for WhatsApp/Facebook crawlers so the preview card always has cover + title + description. */
    function seo_emit_course_share_preview(array $course, string $url): void
    {
        $title = trim((string) ($course['title'] ?? 'Dazu Training Institute')) . ' — Dazu Training Institute';
        $description = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($course['overview'] ?? $course['description'] ?? ''))));
        if ($description === '') {
            $description = 'Apply for this course at Dazu Training Institute, Thika Town.';
        }
        if (function_exists('mb_strimwidth')) {
            $description = mb_strimwidth($description, 0, 180, '…');
        }
        $image = course_og_image_url($course);
        $e = static function (string $v): string {
            return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        };
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
            . '<title>' . $e($title) . '</title>'
            . '<meta name="description" content="' . $e($description) . '">'
            . seo_head_tags($title, $description, $image, $url)
            . '</head><body>'
            . '<h1>' . $e((string) ($course['title'] ?? 'Course')) . '</h1>'
            . '<p>' . $e($description) . '</p>'
            . '<img src="' . $e($image) . '" alt="' . $e((string) ($course['title'] ?? 'Course')) . '" width="1200" height="630">'
            . '</body></html>';
        exit;
    }
}
