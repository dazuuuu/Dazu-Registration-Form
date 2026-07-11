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
