<?php
// app/v1/config/paths.php
// Single source of truth for filesystem and web URLs.

require_once __DIR__ . '/env.php';

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__, 3));
}
if (!defined('APP_V1_ROOT')) {
    define('APP_V1_ROOT', dirname(__DIR__));
}

/*
 * The one backend lives in app/v1. Everything server-side sits inside it, so nothing outside app/ and public/ needs to know
 * where things are. Web URLs are unaffected by where these folders are (only public/ is served), so links and permalinks
 * never depend on them. Always build filesystem paths from the constants and helpers below, never from hard-coded '../..'.
 *
 *   BACKEND_ROOT    app/v1                (config, models, controllers, services, helpers, templates, lib)
 *   DATABASES_PATH  app/v1/databases      (SQL migrations, migrate.php)
 *   STORAGE_PATH    app/v1/storage        (private files: SMS receipts, the mail key)
 *   VENDOR_PATH     app/v1/vendor         (Composer packages; composer.json sits beside it)
 *   TESTS_PATH      app/v1/tests
 *
 * Each can be moved with a server environment variable (APP_STORAGE_PATH, APP_DATABASES_PATH, APP_VENDOR_PATH), e.g. to keep private
 * files outside the code folder on a shared host.
 */
if (!defined('BACKEND_ROOT')) {
    define('BACKEND_ROOT', APP_V1_ROOT);
}
if (!function_exists('dazu_backend_dir')) {
    function dazu_backend_dir(string $envKey, string $default): string {
        $o = function_exists('dazu_env') ? dazu_env($envKey) : null;
        return ($o !== null && $o !== '') ? rtrim(str_replace('\\', '/', (string) $o), '/') : $default;
    }
}
if (!defined('DATABASES_PATH')) {
    define('DATABASES_PATH', dazu_backend_dir('APP_DATABASES_PATH', BACKEND_ROOT . '/databases'));
}
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', dazu_backend_dir('APP_STORAGE_PATH', BACKEND_ROOT . '/storage'));
}
if (!defined('VENDOR_PATH')) {
    define('VENDOR_PATH', dazu_backend_dir('APP_VENDOR_PATH', BACKEND_ROOT . '/vendor'));
}
if (!defined('TESTS_PATH')) {
    define('TESTS_PATH', BACKEND_ROOT . '/tests');
}

if (!function_exists('backend_path')) {
    /** Absolute path inside the backend (app/v1). */
    function backend_path(string $rel = ''): string {
        return BACKEND_ROOT . ($rel === '' ? '' : '/' . ltrim($rel, '/'));
    }
}
if (!function_exists('database_path')) {
    function database_path(string $rel = ''): string {
        return DATABASES_PATH . ($rel === '' ? '' : '/' . ltrim($rel, '/'));
    }
}
if (!function_exists('storage_path')) {
    /** Absolute path in private storage. */
    function storage_path(string $rel = ''): string {
        return STORAGE_PATH . ($rel === '' ? '' : '/' . ltrim($rel, '/'));
    }
}
if (!function_exists('storage_resolve')) {
    /**
     * Turn a path saved in the database ('storage/sms/receipts/x.pdf') into a real file. Files are looked for in the current
     * storage folder first, then in the old location at the project root, so records saved before the move still open.
     */
    function storage_resolve(?string $stored): ?string {
        if ($stored === null || $stored === '') {
            return null;
        }
        $rel = preg_replace('#^storage/#', '', ltrim(str_replace('\\', '/', $stored), '/'));
        foreach ([STORAGE_PATH . '/' . $rel, PROJECT_ROOT . '/storage/' . $rel] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }
        return STORAGE_PATH . '/' . $rel;   // where it will be (or should be) written
    }
}
if (!function_exists('vendor_autoload_files')) {
    /** Where a Composer autoloader may be, in order: the current vendor folder, then the old project-root one. */
    function vendor_autoload_files(): array {
        return [VENDOR_PATH . '/autoload.php', PROJECT_ROOT . '/vendor/autoload.php'];
    }
}

/**
 * Detect URL prefix when public/ is in the request path (local Ampps/subfolder).
 * When public/ is the document root (cPanel), returns empty string.
 */
if (!function_exists('dazu_detect_web_root')) {
    function dazu_detect_web_root(): string {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        if ($script !== '' && preg_match('#^(.*)/public(?=/|$)#', $script, $m)) {
            return $m[1] . '/public';
        }
        return '';
    }
}

/**
 * Filesystem path to web-facing files (uploads, assets).
 * cPanel: often DOCUMENT_ROOT is e.g. .../dazuaihub.com (not .../public).
 */
if (!function_exists('dazu_detect_public_root')) {
    function dazu_detect_public_root(): string {
        $override = dazu_env('APP_PUBLIC_ROOT');
        if ($override !== null && $override !== '') {
            return rtrim(str_replace('\\', '/', $override), '/');
        }

        $repoPublic = str_replace('\\', '/', PROJECT_ROOT . DIRECTORY_SEPARATOR . 'public');
        $script     = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

        // Ampps / subfolder install: .../Registration-System/public/...
        if ($script !== '' && preg_match('#/public(/|$)#', $script) && is_dir($repoPublic)) {
            return $repoPublic;
        }

        // cPanel: document root is the live site folder (dazuaihub.com, public_html, etc.)
        $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        if ($docRoot !== '' && is_dir($docRoot)) {
            return $docRoot;
        }

        return is_dir($repoPublic) ? $repoPublic : $docRoot;
    }
}

if (!defined('PUBLIC_ROOT')) {
    define('PUBLIC_ROOT', dazu_detect_public_root());
}

if (!defined('WEB_ROOT')) {
    $webRoot = dazu_env('APP_WEB_ROOT');

    if ($webRoot === null && array_key_exists('APP_WEB_ROOT', $_SERVER)) {
        $webRoot = $_SERVER['APP_WEB_ROOT'];
    }

    if ($webRoot === null) {
        $fromEnv = getenv('APP_WEB_ROOT');
        if ($fromEnv !== false) {
            $webRoot = $fromEnv;
        }
    }

    if ($webRoot === null) {
        $webRoot = dazu_detect_web_root();
    }

    define('WEB_ROOT', rtrim((string) $webRoot, '/') ?: '');
}

if (!function_exists('dazu_pretty_path')) {
    /**
     * Clean public URLs: auth/login.php → login, blogs/index.php → blog, blogs/readmore.php?slug=x → blog/x,
     * contact/index.php → contact, courses/view.php?id=3 → courses/view?id=3. public/.htaccess maps them back.
     * Only public website sections are changed — admin, portals, API and file paths are left exactly as given.
     * PHP's built-in dev server ignores .htaccess, so it keeps the .php paths unless app/v1/tools/dev_router.php is used.
     */
    function dazu_pretty_path(string $path): string
    {
        if (PHP_SAPI === 'cli-server' && !defined('DAZU_PRETTY_ROUTER')) {
            return $path;
        }
        $query = '';
        $frag = '';
        if (($h = strpos($path, '#')) !== false) {
            $frag = substr($path, $h);
            $path = substr($path, 0, $h);
        }
        if (($q = strpos($path, '?')) !== false) {
            $query = substr($path, $q + 1);
            $path = substr($path, 0, $q);
        }
        $fixed = [
            'index.php' => '', 'auth/login.php' => 'login', 'auth/forgot_password.php' => 'forgot-password',
            'auth/reset_password.php' => 'reset-password', 'blogs' => 'blog', 'blogs/' => 'blog', 'blogs/index.php' => 'blog',
        ];
        if (isset($fixed[$path])) {
            $path = $fixed[$path];
        } elseif ($path === 'blogs/readmore.php' && preg_match('/(?:^|&)slug=([^&]+)/', $query, $m)) {
            $path = 'blog/' . $m[1];
            $query = trim(preg_replace('/(?:^|&)slug=[^&]+/', '', $query), '&');
        } elseif (preg_match('#^(about|apply|blogs|contact|courses|gallery|projects|services|store|testimonials|verify)/(?:(.+?)\.php|index\.php)?$#', $path, $m)) {
            $page = $m[2] ?? '';
            $path = $m[1] . ($page !== '' && $page !== 'index' ? '/' . $page : '');
        } elseif (preg_match('#^(' . DAZU_APP_DIRS . ')(/.*)?/([A-Za-z0-9_-]+)\.php$#', $path, $m)) {
            // Every other page of the app: admin/students/view.php → admin/students/view, intake/book.php → intake/book,
            // admin/students/index.php → admin/students/. public/.htaccess serves the .php file behind the clean address.
            $path = $m[1] . ($m[2] ?? '') . '/' . ($m[3] === 'index' ? '' : $m[3]);
        }
        return $path . ($query !== '' ? '?' . $query : '') . $frag;
    }
}

if (!defined('DAZU_APP_DIRS')) {
    // Top-level folders of public/ that hold pages (regex alternation). Keep in step with public/.htaccess.
    define('DAZU_APP_DIRS', 'about|admin|announcements|api|apply|assignments|auth|calendar|capstone|classes|contact|courses|documents|employee|feedback|gallery|id_cards|intake|intern|letters|profile|projects|services|store|students|superadmin|testimonials|tutor|verify');
    // Folders that are private (portals, sign-in, files): never indexed by search engines.
    define('DAZU_PRIVATE_DIRS', 'admin|superadmin|tutor|students|intern|employee|profile|auth|api|assignments|capstone|documents|id_cards|letters|classes|calendar|intake');
}

if (!function_exists('dazu_clean_links')) {
    /**
     * Output filter for every page: links written by hand (href="edit.php?id=4", action="print.php", href="/intake/book.php")
     * lose ".php" too, so the address bar, link previews and search results stay clean.
     * Only active where clean URLs are served (Apache .htaccess, or the dev router).
     */
    function dazu_clean_links(string $html): string
    {
        if ((PHP_SAPI === 'cli-server' && !defined('DAZU_PRETTY_ROUTER')) || stripos($html, '<html') === false) {
            return $html;
        }
        // Relative links (edit.php, ../x/y.php) and root links (/admin/x.php); never other sites (http…, //…).
        return preg_replace_callback('#\b(href|action)="((?:/(?!/))?(?:\.\./)*(?:[A-Za-z0-9_-]+/)*)([A-Za-z0-9_-]+)\.php((?:[?\#][^"]*)?)"#', function ($m) {
            if ($m[2] === '/' || $m[2] === '' && in_array($m[3], ['robots', 'sitemap', '404'], true)) return $m[0];
            $target = $m[3] === 'index' ? ($m[2] !== '' ? $m[2] : './') : $m[2] . $m[3];
            return $m[1] . '="' . $target . $m[4] . '"';
        }, $html) ?? $html;
    }
}

/** Web URL from site root (public). Example: url('auth/login.php') */
if (!function_exists('url')) {
    function url(string $path = ''): string {
        $path = dazu_pretty_path(ltrim($path, '/'));
        if (WEB_ROOT === '') {
            return '/' . $path;
        }
        return WEB_ROOT . '/' . $path;
    }
}

/** Static asset under /public/assets */
if (!function_exists('asset')) {
    function asset(string $path): string {
        return url('assets/' . ltrim($path, '/'));
    }
}

/** Absolute filesystem path inside the web document root */
if (!function_exists('public_path')) {
    function public_path(string $path = ''): string {
        return PUBLIC_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/'));
    }
}

/** Absolute filesystem path inside /app/v1 */
if (!function_exists('app_path')) {
    function app_path(string $path = ''): string {
        return APP_V1_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/'));
    }
}

/** Legacy alias used in settings */
if (!defined('BASE_URL')) {
    define('BASE_URL', WEB_ROOT === '' ? '/' : WEB_ROOT . '/');
}

if (!function_exists('settings_asset_url')) {
    function settings_asset_url(?string $storedPath): string {
        if ($storedPath === null || $storedPath === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $storedPath)) {
            return $storedPath;
        }

        // Clean relative path for filesystem checks
        $cleanRel = ltrim(preg_replace('#^/Registration-System/public/#', '', $storedPath), '/');
        if (WEB_ROOT !== '' && str_starts_with($cleanRel, ltrim(WEB_ROOT, '/') . '/')) {
            $cleanRel = substr($cleanRel, strlen(ltrim(WEB_ROOT, '/') . '/'));
        }

        $fullUrl = url($cleanRel);

        // Append file modification time to bust browser / service worker cache
        $abs = public_path($cleanRel);
        if (is_file($abs)) {
            $mtime = @filemtime($abs);
            if ($mtime) {
                $fullUrl .= (str_contains($fullUrl, '?') ? '&' : '?') . 'v=' . $mtime;
            }
        }

        return $fullUrl;
    }
}

if (!function_exists('dazu_share_image_for_path')) {
    /** Share picture for a page: the students and tutor portals have their own, everything else the Dazu one. */
    function dazu_share_image_for_path(string $path): string
    {
        $base = defined('WEB_ROOT') ? rtrim(WEB_ROOT, '/') : '';
        $rel = ltrim(substr($path, strlen($base)), '/');
        $which = preg_match('#^(students|auth/login)#', $rel) ? 'students' : (preg_match('#^tutor(/|$)#', $rel) ? 'tutor' : 'dazu');
        return 'assets/images/share/' . $which . '-share.png';
    }
}

if (!function_exists('dazu_favicon_tags')) {
    function dazu_favicon_tags(): string
    {
        return '<link rel="icon" href="' . url('favicon.ico') . '" sizes="any">' . "\n"
            . '  <link rel="icon" type="image/png" sizes="32x32" href="' . url('assets/icons/favicon-32.png') . '">' . "\n"
            . '  <link rel="icon" type="image/png" sizes="192x192" href="' . url('assets/icons/favicon-192.png') . '">' . "\n"
            . '  <link rel="apple-touch-icon" href="' . url('assets/icons/favicon-180.png') . '">' . "\n";
    }
}

if (!function_exists('dazu_head_meta')) {
    /**
     * Output filter: any HTML page whose <head> has no favicon or no share preview gets them,
     * so portals, admin pages and one-off pages all show the Dazu icon and a picture when a link is shared.
     */
    function dazu_head_meta(string $html): string
    {
        $pos = stripos($html, '</head>');
        if ($pos === false) {
            return $html;
        }
        foreach (headers_list() as $h) {
            if (stripos($h, 'content-type:') === 0 && stripos($h, 'text/html') === false) {
                return $html;
            }
        }
        $head = substr($html, 0, $pos);
        $add = '';
        if (!preg_match('#<link[^>]+rel=["\'](?:shortcut )?icon["\']#i', $head)) {
            $add .= dazu_favicon_tags();
        }
        if (stripos($head, 'og:image') === false) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
            $origin = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            $img = $origin . url(dazu_share_image_for_path($path));
            $title = preg_match('#<title[^>]*>(.*?)</title>#is', $head, $m) ? trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8')) : '';
            $title = $title !== '' ? $title : 'Dazu Training Institute';
            $e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
            $add .= '<meta property="og:site_name" content="Dazu Training Institute">' . "\n"
                . '<meta property="og:type" content="website">' . "\n"
                . '<meta property="og:title" content="' . $e($title) . '">' . "\n"
                . '<meta property="og:url" content="' . $e($origin . $path) . '">' . "\n"
                . '<meta property="og:image" content="' . $e($img) . '">' . "\n"
                . '<meta property="og:image:width" content="1200">' . "\n"
                . '<meta property="og:image:height" content="630">' . "\n"
                . '<meta name="twitter:card" content="summary_large_image">' . "\n"
                . '<meta name="twitter:image" content="' . $e($img) . '">' . "\n";
        }
        // Search engines: private areas get noindex; public pages get a canonical link (clean https address) and a description.
        $reqPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $rel = trim(substr($reqPath, strlen(defined('WEB_ROOT') ? WEB_ROOT : '')), '/');
        $private = preg_match('#^(' . DAZU_PRIVATE_DIRS . ')(/|$)#', $rel) || in_array($rel, ['login', 'forgot-password', 'reset-password'], true)
            || http_response_code() >= 400;
        $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        if (!preg_match('#<meta[^>]+name=["\']robots["\']#i', $head)) {
            $add .= '<meta name="robots" content="' . ($private ? 'noindex, nofollow' : 'index, follow') . '">' . "\n";
        }
        if (!$private) {
            if (stripos($head, 'rel="canonical"') === false && stripos($head, "rel='canonical'") === false) {
                // Keep only the query parts that pick a different page (a course, a post, a category); drop tracking and filters.
                parse_str((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY), $q);
                $keep = array_intersect_key($q, array_flip(['id', 'slug', 'abbr', 'category', 'page']));
                $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
                $isLocal = preg_match('#^(localhost|127\.|\[::1\])#', $host);
                $canonPath = preg_replace('#\.php$#', '', $reqPath);
                // A course's share page shows the same course as its main page: point search engines at the main page.
                $canonPath = preg_replace('#/courses/share$#', '/courses/view', $canonPath);
                $canon = ($isLocal ? 'http' : 'https') . '://' . preg_replace('#^www\.#i', '', $host) . $canonPath . ($keep ? '?' . http_build_query($keep) : '');
                $add .= '<link rel="canonical" href="' . $esc($canon) . '">' . "\n";
            }
            if (!preg_match('#<meta[^>]+name=["\']description["\']#i', $head)) {
                $t = preg_match('#<title[^>]*>(.*?)</title>#is', $head, $tm) ? trim(html_entity_decode(strip_tags($tm[1]), ENT_QUOTES, 'UTF-8')) : '';
                $add .= '<meta name="description" content="' . $esc(($t !== '' ? $t . '. ' : '') . 'Practical tech training at Dazu Training Institute in Nairobi, Thika and online.') . '">' . "\n";
            }
        }
        $html = $add === '' ? $html : substr($html, 0, $pos) . $add . substr($html, $pos);
        // Images further down the page load only when scrolled to (the first two, usually the logo/hero, load at once).
        $n = 0;
        return (string) preg_replace_callback('#<img\b(?![^>]*\bloading=)([^>]*)>#i', static function ($m) use (&$n) {
            return ++$n <= 2 ? $m[0] : '<img loading="lazy" decoding="async"' . $m[1] . '>';
        }, $html);
    }
}
