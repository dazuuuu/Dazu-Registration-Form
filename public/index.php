<?php
// public/index.php
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
include __DIR__ . '/components/public/dazu_stats_bar.php';
include __DIR__ . '/components/public/dazu_popular_courses.php';
include __DIR__ . '/components/public/dazu_partners_section.php';
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
