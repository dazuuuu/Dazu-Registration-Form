<?php
// app/v1/helpers/course_share.php
// Shareable course registration URLs.

if (!function_exists('course_share_url')) {
    /**
     * Public URL for sharing a course registration page.
     *
     * @param int         $courseId Active course ID
     * @param string|null $ref      Optional student registration_no for referral tracking
     */
    function course_share_url(int $courseId, ?string $ref = null): string
    {
        $url = url('courses/share.php?id=' . $courseId);
        if ($ref !== null && $ref !== '') {
            $url .= '&ref=' . rawurlencode($ref);
        }
        return $url;
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
