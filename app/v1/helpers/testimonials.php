<?php
// app/v1/helpers/testimonials.php

require_once __DIR__ . '/../models/TestimonialModel.php';
require_once __DIR__ . '/../models/SettingModel.php';

if (!function_exists('testimonial_settings')) {
    function testimonial_settings(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        global $pdo;
        if (!isset($pdo)) {
            require_once app_path('config/db_connect.php');
        }

        $settings = new SettingModel($pdo);
        $cache = [
            'students_open' => $settings->get('testimonials_students_open', '0') === '1',
            'auto_publish' => $settings->get('testimonials_auto_publish', '1') === '1',
        ];

        return $cache;
    }
}

if (!function_exists('testimonials_students_open')) {
    function testimonials_students_open(): bool
    {
        return testimonial_settings()['students_open'];
    }
}

if (!function_exists('testimonial_youtube_id')) {
    function testimonial_youtube_id(string $url): ?string
    {
        if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([a-zA-Z0-9_-]{11})#', $url, $m)) {
            return $m[1];
        }
        return null;
    }
}

if (!function_exists('testimonial_video_thumb')) {
    function testimonial_video_thumb(array $t): string
    {
        if (!empty($t['thumbnail_url'])) {
            return $t['thumbnail_url'];
        }
        $id = testimonial_youtube_id($t['video_url'] ?? '');
        if ($id) {
            return 'https://img.youtube.com/vi/' . $id . '/hqdefault.jpg';
        }
        return 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=600&q=80';
    }
}

if (!function_exists('testimonial_initials')) {
    function testimonial_initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $initials .= strtoupper($p[0] ?? '');
        }
        return $initials ?: 'DH';
    }
}

if (!function_exists('testimonial_load_for_homepage')) {
    /** @return array{text: array, video: array} */
    function testimonial_load_for_homepage(PDO $pdo): array
    {
        $model = new TestimonialModel($pdo);
        $approved = $model->getAll('approved', 50, 0, false);

        $text = array_values(array_filter($approved, static fn($r) => ($r['testimonial_type'] ?? 'text') !== 'video'));
        $video = array_values(array_filter($approved, static fn($r) => ($r['testimonial_type'] ?? 'text') === 'video'));

        if (!$text) {
            $text = testimonial_static_text_fallbacks();
        }
        if (!$video) {
            $video = testimonial_static_video_fallbacks();
        }

        return ['text' => $text, 'video' => $video];
    }
}

if (!function_exists('testimonial_static_text_fallbacks')) {
    function testimonial_static_text_fallbacks(): array
    {
        return [
            [
                'id' => 0,
                'customer_name' => 'Mercy Chepkoech',
                'customer_initial' => 'MC',
                'rating' => 5,
                'testimonial_text' => 'The Python and Tableau modules are world-class. Our trainers gave us real enterprise datasets to analyze. I was able to transition from an administrative role to technical Data Analytics smoothly!',
                'service_tag' => 'Data Analytics',
                'role' => 'Data Analyst at Fintech Africa',
                'testimonial_type' => 'text',
            ],
            [
                'id' => 0,
                'customer_name' => 'Joseph Njuguna',
                'customer_initial' => 'JN',
                'rating' => 5,
                'testimonial_text' => 'Dazu Hub\'s physical labs in Room 529 gave me the confidence to deploy full-stack apps. The mentorship is practical — you write code every single day.',
                'service_tag' => 'Software Development',
                'role' => 'Junior Developer at Thika Tech Hub',
                'testimonial_type' => 'text',
            ],
            [
                'id' => 0,
                'customer_name' => 'Alex Kipkoech',
                'customer_initial' => 'AK',
                'rating' => 5,
                'testimonial_text' => 'Cybersecurity labs with real routers and ethical hacking exercises prepared me for CompTIA-style interviews. Highly recommend Dazu Hub.',
                'service_tag' => 'Cybersecurity',
                'role' => 'Security Analyst',
                'testimonial_type' => 'text',
            ],
        ];
    }
}

if (!function_exists('testimonial_static_video_fallbacks')) {
    function testimonial_static_video_fallbacks(): array
    {
        return [
            [
                'id' => 0,
                'customer_name' => 'Faith Wambui',
                'customer_initial' => 'FW',
                'service_tag' => 'Web Development',
                'role' => 'Full-Stack Graduate',
                'testimonial_type' => 'video',
                'video_url' => '',
                'video_provider' => 'none',
                'thumbnail_url' => '',
                'bg_class' => 'from-emerald-600 to-teal-900',
            ],
            [
                'id' => 0,
                'customer_name' => 'Kevin Mwangi',
                'customer_initial' => 'KM',
                'service_tag' => 'Cybersecurity & Networks',
                'role' => 'Security Graduate',
                'testimonial_type' => 'video',
                'video_url' => '',
                'video_provider' => 'none',
                'thumbnail_url' => '',
                'bg_class' => 'from-blue-700 to-indigo-950',
            ],
            [
                'id' => 0,
                'customer_name' => 'Grace Akinyi',
                'customer_initial' => 'GA',
                'service_tag' => 'AI & Data Science',
                'role' => 'Data Science Cohort',
                'testimonial_type' => 'video',
                'video_url' => '',
                'video_provider' => 'none',
                'thumbnail_url' => '',
                'bg_class' => 'from-purple-700 to-fuchsia-950',
            ],
        ];
    }
}

if (!function_exists('testimonial_video_embed_url')) {
    function testimonial_video_embed_url(array $t): ?string
    {
        $url = trim($t['video_url'] ?? '');
        if ($url === '') {
            return null;
        }
        $id = testimonial_youtube_id($url);
        if ($id) {
            return 'https://www.youtube.com/embed/' . $id . '?autoplay=1&rel=0';
        }
        if (($t['video_provider'] ?? '') === 'vimeo' && preg_match('#vimeo\.com/(\d+)#', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1] . '?autoplay=1';
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        return null;
    }
}
