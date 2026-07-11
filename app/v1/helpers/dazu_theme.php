<?php
// app/v1/helpers/dazu_theme.php — shared helpers for the Dazu hub Institute PHP UI

if (!function_exists('dazu_tailwind_css')) {
    function dazu_tailwind_css(): string
    {
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
