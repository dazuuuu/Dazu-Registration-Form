<?php
// app/v1/helpers/course_share.php
// Shareable course registration URLs.

if (!function_exists('course_share_request_is_https')) {
    function course_share_request_is_https(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        $fwd = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($fwd === 'https' || str_starts_with($fwd, 'https,')) {
            return true;
        }
        if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on') {
            return true;
        }
        return (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }
}

if (!function_exists('course_share_absolute_url')) {
    function course_share_absolute_url(string $path): string
    {
        if (preg_match('#^https?://#i', $path)) {
            if (course_share_request_is_https()) {
                return (string) preg_replace('#^http://#i', 'https://', $path);
            }
            return $path;
        }
        $scheme = course_share_request_is_https() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . ltrim($path, '/');
        }
        return $scheme . '://' . $host . $path;
    }
}

if (!function_exists('course_og_image_url')) {
    /**
     * Absolute cover-image URL for Open Graph / WhatsApp / Facebook previews.
     * Points at courses/cover.php so crawlers always hit the same host as the share link.
     */
    function course_og_image_url(array $course): string
    {
        $id = (int) ($course['id'] ?? 0);
        if ($id > 0) {
            return course_share_absolute_url(url('courses/cover.php?id=' . $id));
        }
        require_once __DIR__ . '/dazu_theme.php';
        require_once __DIR__ . '/seo.php';
        return seo_image_url(dazu_course_image_url($course)) ?: seo_default_image();
    }
}

if (!function_exists('course_share_invite_text')) {
    /**
     * WhatsApp / native-share body. URL first on its own line so apps generate a preview card.
     */
    function course_share_invite_text(array $course, string $shareUrl, ?string $headline = null): string
    {
        $title = trim((string) ($course['title'] ?? 'this course'));
        $headline = $headline !== null && $headline !== ''
            ? $headline
            : ('Apply for ' . $title . ' at Dazu Training Institute');
        $desc = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($course['overview'] ?? $course['description'] ?? ''))));
        if (function_exists('mb_strimwidth')) {
            $desc = mb_strimwidth($desc, 0, 180, '…');
        } else {
            $desc = strlen($desc) > 180 ? substr($desc, 0, 177) . '…' : $desc;
        }
        $text = $shareUrl . "\n\n" . $headline;
        if ($desc !== '') {
            $text .= "\n" . $desc;
        }
        return $text;
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

if (!function_exists('course_resolve_for_page')) {
    /**
     * Load a shareable course from ?id= or ?abbr= query params.
     */
    function course_resolve_for_page(\Models\CourseModel $model): ?array
    {
        $id = course_share_parse_id();
        if ($id > 0) {
            $course = $model->getById($id);
            return \Models\CourseModel::isShareable($course) ? $course : null;
        }

        $abbr = isset($_GET['abbr']) ? strtoupper(trim((string) $_GET['abbr'])) : '';
        if ($abbr !== '') {
            $course = $model->getByAbbreviation($abbr);
            return \Models\CourseModel::isShareable($course) ? $course : null;
        }

        return null;
    }
}

if (!function_exists('course_view_url')) {
    /** Canonical course detail + apply page (details left, form right). */
    function course_view_url(array $course): string
    {
        $id = (int) ($course['id'] ?? 0);
        if ($id > 0) {
            return course_share_absolute_url(url('courses/view.php?id=' . $id));
        }
        $abbr = strtoupper(trim((string) ($course['abbreviation'] ?? '')));
        if ($abbr !== '') {
            return course_share_absolute_url(url('courses/view.php?abbr=' . rawurlencode($abbr)));
        }
        return course_share_absolute_url(url('courses/index.php'));
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
