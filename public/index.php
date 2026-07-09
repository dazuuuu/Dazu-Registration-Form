<?php
// public/index.php — Dazu Hub Institute homepage
$page_title       = 'Dazu Hub Tech Skills Institute — Upgrade Your Tech Skills';
$page_description = 'Practical, hands-on training in web development, cybersecurity, data analytics, and AI. Build your future with Dazu Hub in Nairobi, Kenya.';

require_once __DIR__ . '/../app/v1/init.php';
require_once __DIR__ . '/../app/v1/helpers/course_catalog.php';
require_once __DIR__ . '/../app/v1/models/Connection.php';
require_once __DIR__ . '/../app/v1/models/CourseModel.php';

use Models\Connection;
use Models\CourseModel;

$pdo = Connection::getInstance()->getConnection();
$courseModel = new CourseModel($pdo);
[$displayCourses, $usingStaticCourses] = course_catalog_for_display($courseModel);

$extra_css = '<link rel="stylesheet" href="' . asset('css/homepage.css') . '">'
    . '<link rel="stylesheet" href="' . asset('css/apply-modal.css') . '">';

ob_start();
include __DIR__ . '/components/public/homepage_markup.php';
$content = ob_get_clean();

$extra_js = '<script>window.DH_APPLY_URL=' . json_encode(url('apply/index.php')) . ';</script>'
    . '<script src="' . asset('js/apply-form.js') . '"></script>';

require __DIR__ . '/templates/public/layout.php';
