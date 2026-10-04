<?php
// public/index.php

// Clean-URL fallback. Any address the server can't map lands here, so if public/.htaccess isn't applied (LiteSpeed,
// an older copy on the host) /courses/view?id=2, /blog/<slug>, /courses/category?slug=x would all show the homepage.
// Route them to their real page instead, the same way app/v1/tools/dev_router.php does.
$__routed = (function (): ?string {
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    if ($base !== '' && strpos($path, $base . '/') === 0) {
        $path = substr($path, strlen($base));
    }
    $rel = trim($path, '/');
    if ($rel === '' || $rel === 'index.php' || strpos($rel, '..') !== false) {
        return null;
    }

    $sections = 'about|apply|blogs|contact|courses|gallery|projects|services|store|testimonials|verify';
    $fixed = ['login' => 'auth/login.php', 'forgot-password' => 'auth/forgot_password.php',
              'reset-password' => 'auth/reset_password.php', 'blog' => 'blogs/index.php'];
    $file = null;
    if (isset($fixed[$rel])) {
        $file = $fixed[$rel];
    } elseif (preg_match('#^blog/([^/]+)$#', $rel, $m)) {
        $file = 'blogs/readmore.php';
        $_GET['slug'] = $_REQUEST['slug'] = $m[1];
    } elseif (preg_match('#^(' . $sections . ')$#', $rel)) {
        $file = $rel . '/index.php';
    } elseif (preg_match('#^(' . $sections . ')/([A-Za-z0-9_-]+)$#', $rel, $m)) {
        $file = $m[1] . '/' . $m[2] . '.php';
    }
    if ($file === null || !is_file(__DIR__ . '/' . $file)) {
        return null;
    }

    $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/' . $file;
    $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = $base . '/' . $file;
    chdir(dirname(__DIR__ . '/' . $file));
    return __DIR__ . '/' . $file;
})();
if ($__routed !== null) {
    require $__routed;   // top level, so the page's variables stay global as they would when hit directly
    exit;
}
unset($__routed);

$page_title = 'Dazu Training Institute — Practical Tech Training in Nairobi, Thika & Online';
$page_description = 'Hands-on software development, cybersecurity, data analytics and AI courses at Dazu Training Institute in Nairobi, Thika and online.';

require_once __DIR__ . '/../app/v1/init.php';
require_once __DIR__ . '/../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../app/v1/helpers/testimonials.php';
require_once __DIR__ . '/../app/v1/helpers/seo.php';
require_once __DIR__ . '/../app/v1/helpers/campus_media.php';
require_once __DIR__ . '/../app/v1/models/Connection.php';
require_once __DIR__ . '/../app/v1/models/CourseModel.php';

use Models\Connection;
use Models\CourseModel;

$pdo = Connection::getInstance()->getConnection();
$courseModel = new CourseModel($pdo);

// Hero floating cards use real counts, not the template's invented ones.
require_once __DIR__ . '/components/public/dazu_hero_helpers.php';
$heroStats = dazu_hero_stats($pdo);

$popularSection = dazu_load_popular_courses_section($pdo, $courseModel);
$popularCategories = $popularSection['categories'];
$popularCourses = $popularSection['courses'];
$campusMedia = campus_media_for_homepage($pdo);

$testimonialData = testimonial_load_for_homepage($pdo);
$textTestimonials = $testimonialData['text'];
$videoTestimonials = $testimonialData['video'];

ob_start();
include __DIR__ . '/components/public/dazu_hero.php';
include __DIR__ . '/components/public/dazu_partners_section.php';
include __DIR__ . '/components/public/dazu_stats_bar.php';
include __DIR__ . '/components/public/dazu_popular_courses.php';
include __DIR__ . '/components/public/dazu_course_match_section.php';
include __DIR__ . '/components/public/dazu_campus_media_section.php';
include __DIR__ . '/components/public/dazu_testimonials_section.php';
include __DIR__ . '/components/public/dazu_cta_section.php';
include __DIR__ . '/components/public/dazu_newsletter_section.php';
$content = ob_get_clean();
require_once __DIR__ . '/components/public/dazu_hero_helpers.php';
$og_image = seo_home_hero_image() ?: dazu_hero_image_url(
    'hero-1',
    is_file(public_path('assets/app/hero.png'))
        ? asset('app/hero.png')
        : 'https://images.unsplash.com/photo-1580894732444-8ecded7900cd?auto=format&fit=crop&w=1200&q=80'
);
require __DIR__ . '/templates/public/dazu_layout.php';
