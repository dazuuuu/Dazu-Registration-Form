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

if (!function_exists('dazu_course_icon_svg')) {
    /** Lucide-style SVG icon for course card overlay (ported from Dazu-frontend) */
    function dazu_course_icon_svg(string $abbrev): string
    {
        $abbr = strtoupper(trim($abbrev));
        $paths = [
            'WD' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/>',
            'PY' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
            'CY' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>',
            'DA' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
            'AI' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>',
        ];
        $path = $paths[$abbr] ?? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>';
        return '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' . $path . '</svg>';
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

if (!function_exists('dazu_recount_course_categories')) {
    /**
     * Recompute course_count per category using resolved display category ids.
     *
     * @param array<int, array<string, mixed>> $categories
     * @param array<int, array<string, mixed>> $courses
     * @return array<int, array<string, mixed>>
     */
    function dazu_recount_course_categories(array $categories, array $courses): array
    {
        foreach ($categories as &$cat) {
            $catId = (int) ($cat['id'] ?? 0);
            $cat['course_count'] = count(array_filter(
                $courses,
                static fn($c) => (int) ($c['_display_category_id'] ?? 0) === $catId
            ));
        }
        unset($cat);

        return array_values(array_filter($categories, static fn($c) => (int) ($c['course_count'] ?? 0) > 0));
    }
}

if (!function_exists('dazu_abbr_category_slug_map')) {
    function dazu_abbr_category_slug_map(): array
    {
        return [
            'WD' => 'web-development',
            'PY' => 'web-development',
            'PHP' => 'web-development',
            'ND' => 'web-development',
            'FE' => 'web-development',
            'FS' => 'web-development',
            'CY' => 'cybersecurity',
            'DA' => 'data-analytics',
            'AI' => 'ai-machine-learning',
        ];
    }
}

if (!function_exists('dazu_resolve_course_category_id')) {
    function dazu_resolve_course_category_id(array $course, ?array $categories = null): int
    {
        if (!empty($course['category_id'])) {
            return (int) $course['category_id'];
        }

        $abbr = strtoupper(trim($course['abbreviation'] ?? ''));
        if ($categories) {
            $slug = dazu_abbr_category_slug_map()[$abbr] ?? 'web-development';
            foreach ($categories as $cat) {
                if (($cat['slug'] ?? '') === $slug) {
                    return (int) $cat['id'];
                }
            }
        }

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

        if (empty($categories)) {
            $categories = dazu_fallback_course_categories();
        }

        foreach ($courses as &$course) {
            $course['_display_category_id'] = dazu_resolve_course_category_id($course, $categories);
        }
        unset($course);

        $categories = dazu_recount_course_categories($categories, $courses);

        if (empty($categories) && !empty($courses)) {
            $categories = dazu_fallback_course_categories();
            $categories[0]['course_count'] = count($courses);
            $categories = [$categories[0]];
            foreach ($courses as &$course) {
                $course['_display_category_id'] = (int) $categories[0]['id'];
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
