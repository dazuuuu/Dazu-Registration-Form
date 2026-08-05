<?php
// app/v1/helpers/contact.php — Dazu Hub public contact + WhatsApp sharing

if (!function_exists('site_whatsapp_e164')) {
    /** Digits only for wa.me links (no + or spaces). */
    function site_whatsapp_e164(): string
    {
        return '254750116600';
    }
}

if (!function_exists('site_whatsapp_display')) {
    function site_whatsapp_display(): string
    {
        return '0750 116 600';
    }
}

if (!function_exists('site_phone_display')) {
    function site_phone_display(): string
    {
        return '+254 750 116 600';
    }
}

if (!function_exists('site_phone_tel')) {
    function site_phone_tel(): string
    {
        return '+254750116600';
    }
}

if (!function_exists('whatsapp_chat_url')) {
    /** Open WhatsApp chat with the institute (optional pre-filled message). */
    function whatsapp_chat_url(string $message = ''): string
    {
        $base = 'https://wa.me/' . site_whatsapp_e164();
        $message = trim($message);
        return $message === '' ? $base : $base . '?text=' . rawurlencode($message);
    }
}

if (!function_exists('phone_to_whatsapp_e164')) {
    /** Normalize a Kenyan phone number to digits-only E.164 for wa.me (e.g. 2547XXXXXXXX). */
    function phone_to_whatsapp_e164(string $phone): ?string
    {
        $d = preg_replace('/\D+/', '', $phone);
        if ($d === '') {
            return null;
        }
        if (strlen($d) === 10 && $d[0] === '0') {
            $d = '254' . substr($d, 1);
        } elseif (strlen($d) === 9 && ($d[0] === '7' || $d[0] === '1')) {
            $d = '254' . $d;
        }
        if (strlen($d) === 12 && strpos($d, '254') === 0 && ($d[3] === '7' || $d[3] === '1')) {
            return $d;
        }
        return null;
    }
}

if (!function_exists('student_login_whatsapp_url')) {
    /** Pre-filled WhatsApp message with student portal login details. */
    function student_login_whatsapp_url(string $phone, string $studentName, array $credentials): ?string
    {
        $e164 = phone_to_whatsapp_e164($phone);
        if ($e164 === null) {
            return null;
        }

        $loginUrl = $credentials['login_url'] ?? '';
        $text = "Hi {$studentName},\n\n"
            . "Your Dazu Hub student portal account is ready.\n\n"
            . "Student ID: " . ($credentials['reg_no'] ?? $credentials['username'] ?? '') . "\n"
            . "Login email: " . ($credentials['login_email'] ?? '') . "\n"
            . "Password: " . ($credentials['temp_password'] ?? '') . "\n"
            . "Login page: {$loginUrl}\n\n"
            . "Please change your password after your first login.";

        return 'https://wa.me/' . $e164 . '?text=' . rawurlencode($text);
    }
}

if (!function_exists('course_whatsapp_share_url')) {
    /**
     * Share a course enquiry via WhatsApp to the institute number.
     */
    function course_whatsapp_share_url(array $course, ?string $pageUrl = null): string
    {
        if (!function_exists('course_view_url')) {
            require_once __DIR__ . '/course_share.php';
        }

        $title = trim((string) ($course['title'] ?? 'a course'));
        if ($pageUrl === null || $pageUrl === '') {
            $pageUrl = course_view_url($course);
        }

        $text = "Hi Dazu Hub Institute, I'm interested in the course: {$title}\n{$pageUrl}";
        return whatsapp_chat_url($text);
    }
}
