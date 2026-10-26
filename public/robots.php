<?php
// Served as /robots.txt (public/.htaccess, or the clean-URL fallback in public/index.php).
// Plain text, so crawlers can parse it. Portals, sign-in and private files are kept out of search results;
// the Sitemap line points crawlers at /sitemap.xml on whichever domain the site is served from.
require_once __DIR__ . '/../app/v1/config/paths.php';
require_once __DIR__ . '/../app/v1/helpers/course_share.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$private = ['admin', 'superadmin', 'tutor', 'students', 'intern', 'employee', 'profile', 'auth', 'api', 'components', 'templates',
            'assignments', 'documents', 'id_cards', 'letters', 'store/checkout', 'classes', 'calendar'];
echo "User-agent: *\n";
foreach ($private as $p) echo 'Disallow: ' . url($p . '/') . "\n";
echo 'Disallow: ' . url('login') . "\n";
echo "Allow: " . url('/') . "\n\n";
echo 'Sitemap: ' . course_share_absolute_url(url('sitemap.xml')) . "\n";
