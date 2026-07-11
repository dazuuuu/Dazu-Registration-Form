<?php
// app/v1/helpers/dazu_theme.php — shared helpers for the Dazu hub Institute PHP UI

if (!function_exists('dazu_tailwind_css')) {
    function dazu_tailwind_css(): string
    {
        $stable = public_path('assets/css/dazu-tailwind.css');
        if (is_file($stable)) {
            return asset('css/dazu-tailwind.css');
        }

        $dir = public_path('assets/app');
        $files = glob($dir . DIRECTORY_SEPARATOR . 'index-*.css') ?: [];
        usort($files, static fn($a, $b) => filemtime($b) <=> filemtime($a));
        $file = $files[0] ?? null;
        if ($file) {
            return asset('app/' . basename($file));
        }

        return asset('css/theme.css');
    }
}

if (!function_exists('dazu_nav_active')) {
    function dazu_nav_active(string $segment): bool
    {
        $uri = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
        $self = basename($_SERVER['PHP_SELF'] ?? '');

        if ($segment === 'home') {
            return $self === 'index.php' && !preg_match('#/(admin|students|tutor|auth|store|api)/#', $uri);
        }

        $map = [
            'courses' => '/courses',
            'about' => '/about',
            'projects' => '/projects',
            'blog' => '/blogs',
            'contact' => '/contact',
            'apply' => '/apply',
            'login' => '/login',
        ];

        $needle = $map[$segment] ?? '/' . ltrim($segment, '/');
        return str_contains($uri, $needle);
    }
}

if (!function_exists('dazu_course_category')) {
    function dazu_course_category(string $abbrev): string
    {
        $map = [
            'WD' => 'Software & Web',
            'CY' => 'Security & Infrastructure',
            'DA' => 'AI & Data Science',
            'AI' => 'AI & Data Science',
            'PY' => 'Software & Web',
            'PHP' => 'Software & Web',
            'ND' => 'Software & Web',
            'FE' => 'Software & Web',
            'FS' => 'Software & Web',
        ];
        return $map[strtoupper(trim($abbrev))] ?? 'Software & Web';
    }
}

if (!function_exists('dazu_course_image_url')) {
    function dazu_course_image_url(array $course): string
    {
        $img = trim((string) ($course['card_image'] ?? $course['cover_image'] ?? ''));
        if ($img === '' || $img === 'null') {
            return 'https://images.unsplash.com/photo-1515879218367-8466d910aaa4?auto=format&fit=crop&w=600&q=80';
        }
        if (preg_match('#^https?://#i', $img)) {
            return $img;
        }
        return settings_asset_url($img);
    }
}

if (!function_exists('dazu_load_courses')) {
    /**
     * @return array{0: array, 1: bool} courses, using static fallback
     */
    function dazu_load_courses(\Models\CourseModel $courseModel): array
    {
        require_once app_path('helpers/course_catalog.php');
        return course_catalog_for_display($courseModel);
    }
}

if (!function_exists('dazu_course_apply_url')) {
    function dazu_course_apply_url(array $course): string
    {
        $id = (int) ($course['id'] ?? 0);
        if ($id > 0) {
            return url('courses/view.php?id=' . $id);
        }
        $abbr = strtoupper(trim($course['abbreviation'] ?? ''));
        if ($abbr !== '') {
            return url('courses/view.php?abbr=' . rawurlencode($abbr));
        }
        return url('courses/index.php');
    }
}

if (!function_exists('dazu_course_rating_count')) {
    function dazu_course_rating_count(string $abbrev): int
    {
        $map = ['WD' => 142, 'CY' => 158, 'DA' => 134, 'AI' => 186, 'PY' => 158, 'PHP' => 120, 'ND' => 98, 'FE' => 110, 'FS' => 175];
        return $map[strtoupper(trim($abbrev))] ?? 120;
    }
}

if (!function_exists('dazu_course_syllabus')) {
    function dazu_course_syllabus(array $course): array
    {
        if (!empty($course['tags']) && is_array($course['tags'])) {
            return $course['tags'];
        }
        $desc = strip_tags($course['description'] ?? '');
        if ($desc !== '') {
            $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $desc)));
            if ($lines) {
                return array_slice($lines, 0, 8);
            }
        }
        $overview = $course['overview'] ?? '';
        if ($overview !== '') {
            return array_slice(array_filter(array_map('trim', explode('.', $overview))), 0, 6);
        }
        return [
            'Industry-standard tools and frameworks',
            'Hands-on lab projects every week',
            'Portfolio-ready capstone assignment',
            'Career preparation and interview coaching',
            'Certificate upon successful completion',
        ];
    }
}

if (!function_exists('dazu_course_icon_markup')) {
    function dazu_course_icon_markup(string $abbrev): string
    {
        $abbr = strtoupper(trim($abbrev));
        $icons = [
            'PY' => '>_', 'WD' => '</>', 'CY' => '🔒', 'DA' => '📊', 'AI' => '🧠',
            'PHP' => '🐘', 'ND' => '⬡', 'FE' => '⚡', 'FS' => '🚀',
        ];
        $char = $icons[$abbr] ?? '📚';
        if (strlen($char) <= 3) {
            return '<span class="font-mono font-extrabold text-green-600 text-sm">' . htmlspecialchars($char) . '</span>';
        }
        return '<span class="text-lg">' . $char . '</span>';
    }
}

if (!function_exists('dazu_category_icon_url')) {
    function dazu_category_icon_url(?string $path): string
    {
        if ($path === null || trim($path) === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return settings_asset_url($path);
    }
}

if (!function_exists('dazu_fallback_course_categories')) {
    function dazu_fallback_course_categories(): array
    {
        return [
            ['id' => 1, 'name' => 'Web Development', 'slug' => 'web-development', 'icon_path' => null, 'course_count' => 0],
            ['id' => 2, 'name' => 'Cybersecurity', 'slug' => 'cybersecurity', 'icon_path' => null, 'course_count' => 0],
            ['id' => 3, 'name' => 'Data Analytics', 'slug' => 'data-analytics', 'icon_path' => null, 'course_count' => 0],
            ['id' => 4, 'name' => 'AI & Machine Learning', 'slug' => 'ai-machine-learning', 'icon_path' => null, 'course_count' => 0],
        ];
    }
}

if (!function_exists('dazu_abbr_category_map')) {
    /** Maps course abbreviation to fallback category id when category_id is null */
    function dazu_abbr_category_map(): array
    {
        return [
            'WD' => 1, 'PY' => 1, 'PHP' => 1, 'ND' => 1, 'FE' => 1, 'FS' => 1,
            'CY' => 2,
            'DA' => 3,
            'AI' => 4,
        ];
    }
}

if (!function_exists('dazu_resolve_course_category_id')) {
    function dazu_resolve_course_category_id(array $course): int
    {
        if (!empty($course['category_id'])) {
            return (int) $course['category_id'];
        }
        $abbr = strtoupper(trim($course['abbreviation'] ?? ''));
        $map = dazu_abbr_category_map();
        return (int) ($map[$abbr] ?? 1);
    }
}

if (!function_exists('dazu_load_popular_courses_section')) {
    /**
     * @return array{categories: array, courses: array}
     */
    function dazu_load_popular_courses_section(\PDO $pdo, \Models\CourseModel $courseModel): array
    {
        require_once app_path('models/CourseCategoryModel.php');
        $catModel = new \Models\CourseCategoryModel($pdo);

        try {
            $categories = $catModel->getAll(true);
        } catch (\Throwable $e) {
            $categories = [];
        }

        [$courses] = dazu_load_courses($courseModel);
        $courses = array_values(array_filter($courses, static fn($c) => !empty($c['is_active']) || (int)($c['id'] ?? 0) === 0));

        foreach ($courses as &$course) {
            $course['_display_category_id'] = dazu_resolve_course_category_id($course);
        }
        unset($course);

        if (empty($categories)) {
            $categories = dazu_fallback_course_categories();
            foreach ($categories as &$cat) {
                $cat['course_count'] = count(array_filter($courses, static fn($c) => (int)($c['_display_category_id'] ?? 0) === (int)$cat['id']));
            }
            unset($cat);
            $categories = array_values(array_filter($categories, static fn($c) => (int)$c['course_count'] > 0));
        }

        if (empty($categories) && !empty($courses)) {
            $categories = dazu_fallback_course_categories();
            $categories[0]['course_count'] = count($courses);
            $categories = [$categories[0]];
            foreach ($courses as &$course) {
                $course['_display_category_id'] = 1;
            }
            unset($course);
        }

        return ['categories' => $categories, 'courses' => $courses];
    }
}

if (!function_exists('dazu_fee_quote')) {
    function dazu_fee_quote(array $course, string $studyMode = 'online', string $paymentPlan = 'monthly'): array
    {
        $fee = (float) ($course['course_fee'] ?? 0);
        $mo = max(1, (int) ($course['duration_months'] ?? 1));
        $regFee = $studyMode === 'physical' ? 1500 : 1000;
        $count = match ($paymentPlan) {
            '3months' => 3,
            'full' => 1,
            default => $mo,
        };
        $inst = $fee / max(1, $count);
        return [
            'registration_fee' => $regFee,
            'installment_amount' => $inst,
            'total_due_now' => $regFee + $inst,
        ];
    }
}
