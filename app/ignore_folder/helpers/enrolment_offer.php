<?php
// One-time enrolment document prompt (download / email) stored in the session.

if (!function_exists('enrolment_offer_store')) {
    function enrolment_offer_store(array $reg, array $course, array $result): void
    {
        $_SESSION['enrolment_offer'] = [
            'token' => bin2hex(random_bytes(16)),
            'registration_id' => (int) ($result['id'] ?? $reg['id'] ?? 0),
            'registration_no' => (string) ($result['registration_no'] ?? $reg['registration_no'] ?? ''),
            'email' => (string) ($reg['email'] ?? ''),
            'full_name' => (string) ($reg['full_name'] ?? ''),
            'course_id' => (int) ($course['id'] ?? $reg['course_id'] ?? 0),
            'at' => time(),
        ];
        $_SESSION['enrolment_offer_show'] = 1;
    }
}

if (!function_exists('enrolment_offer_get')) {
    function enrolment_offer_get(): ?array
    {
        $offer = $_SESSION['enrolment_offer'] ?? null;
        if (!is_array($offer) || empty($offer['token'])) {
            return null;
        }
        if (time() - (int) ($offer['at'] ?? 0) > 3600) {
            unset($_SESSION['enrolment_offer'], $_SESSION['enrolment_offer_show']);
            return null;
        }
        return $offer;
    }
}

if (!function_exists('enrolment_offer_consume_show')) {
    /** True only on the first page view after a successful apply. Refresh returns false. */
    function enrolment_offer_consume_show(): bool
    {
        if (empty($_SESSION['enrolment_offer_show'])) {
            return false;
        }
        unset($_SESSION['enrolment_offer_show']);
        return enrolment_offer_get() !== null;
    }
}
