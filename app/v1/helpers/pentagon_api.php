<?php
/**
 * Map Registration-System entities to Pentagon UI package/offer shapes.
 */

require_once __DIR__ . '/course_duration.php';

if (!function_exists('pentagon_shape_package')) {
    function pentagon_shape_package(array $course): array
    {
        $cover = trim((string) ($course['cover_image'] ?? $course['card_image'] ?? ''));
        if ($cover !== '' && !preg_match('#^https?://#i', $cover)) {
            $cover = function_exists('settings_asset_url')
                ? settings_asset_url($cover)
                : url(ltrim($cover, '/'));
        }
        if ($cover === '' || $cover === 'null') {
            $cover = 'https://images.unsplash.com/photo-1515879218367-8466d910aaa4?auto=format&fit=crop&w=800&q=80';
        }

        $duration = course_study_summary($course);
        if ($duration === '') {
            $months = (int) ($course['duration_months'] ?? 0);
            $duration = $months > 0 ? $months . ' Month' . ($months === 1 ? '' : 's') : 'Flexible';
        }

        $fee = (float) ($course['course_fee'] ?? 0);
        $id = (int) ($course['id'] ?? 0);
        $abbr = trim((string) ($course['abbreviation'] ?? ''));

        return [
            'id' => $id,
            'title' => (string) ($course['title'] ?? ''),
            'description' => (string) ($course['overview'] ?? $course['description'] ?? ''),
            'price' => $fee,
            'price_label' => $fee > 0 ? 'KES ' . number_format($fee) : 'Enquire',
            'duration' => $duration,
            'location' => (string) ($course['category_name'] ?? 'Thika Town'),
            'image' => $cover,
            'rating' => (string) ($course['rating'] ?? '4.8'),
            'abbreviation' => $abbr,
            'url' => function_exists('url')
                ? url('courses/view.php?id=' . $id)
                : '/courses/view.php?id=' . $id,
            'apply_url' => function_exists('url')
                ? url('apply/index.php?course_id=' . $id)
                : '/apply/index.php?course_id=' . $id,
        ];
    }
}

if (!function_exists('pentagon_shape_offer')) {
    /** Seasonal / featured offer card (homepage carousel). */
    function pentagon_shape_offer(array $course): array
    {
        $pkg = pentagon_shape_package($course);
        $pkg['badge'] = 'Featured';
        $pkg['cta'] = 'View Course';
        return $pkg;
    }
}
