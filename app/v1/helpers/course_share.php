<?php
// app/v1/helpers/course_share.php
// Shareable course registration URLs.

if (!function_exists('course_share_absolute_url')) {
    function course_share_absolute_url(string $path): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . ltrim($path, '/');
        }
        return $scheme . '://' . $host . $path;
    }
}

if (!function_exists('course_share_parse_id')) {
    /** Read course id from query string (tolerant of messy shared URLs). */
    function course_share_parse_id(): int
    {
        if (isset($_GET['id'])) {
            $id = (int) $_GET['id'];
            if ($id > 0) {
                return $id;
            }
        }
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        if ($qs !== '' && preg_match('/(?:^|&)id=(\d+)/', $qs, $m)) {
            return (int) $m[1];
        }
        return 0;
    }
}

if (!function_exists('course_is_shareable')) {
    /**
     * Whether a course row can be shown on the public share page.
     * Treats missing is_active as active (legacy/partial schemas).
     */
    function course_is_shareable(?array $course): bool
    {
        return \Models\CourseModel::isShareable($course);
    }
}

if (!function_exists('course_share_url')) {
    /**
     * Public URL for sharing a course registration page.
     *
     * @param int         $courseId Active course ID
     * @param string|null $ref      Optional student registration_no for referral tracking
     */
    function course_share_url(int $courseId, ?string $ref = null): string
    {
        $courseId = (int) $courseId;
        if ($courseId <= 0) {
            return course_share_absolute_url(url('courses/index.php'));
        }
        $path = url('courses/share.php?id=' . $courseId);
        if ($ref !== null && $ref !== '') {
            $path .= '&ref=' . rawurlencode($ref);
        }
        return course_share_absolute_url($path);
    }
}

if (!function_exists('course_share_resolve_referral')) {
    /**
     * Validate referral code and return referrer registration row if approved.
     */
    function course_share_resolve_referral($pdo, ?string $ref): ?array
    {
        $ref = trim((string) $ref);
        if ($ref === '') {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT r.id, r.registration_no, r.full_name, r.course_id, c.title AS course_title
             FROM registrations r
             JOIN courses c ON c.id = r.course_id
             WHERE r.registration_no = :ref AND r.status = 'approved'
             LIMIT 1"
        );
        $stmt->execute([':ref' => $ref]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
