<?php
// app/helpers/contact.php — Dazu Hub public contact + WhatsApp sharing

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
    /** Open a WhatsApp chat with the institute (optional pre-filled message). */
    function whatsapp_chat_url(string $message = ''): string
    {
        $base = 'https://wa.me/' . site_whatsapp_e164();
        $message = trim($message);
        return $message === '' ? $base : $base . '?text=' . rawurlencode($message);
    }
}

if (!function_exists('course_whatsapp_share_url')) {
    /**
     * Share a course via WhatsApp to the institute number.
     */
    function course_whatsapp_share_url(array $course, ?string $pageUrl = null): string
    {
        $title = trim((string) ($course['title'] ?? 'a course'));
        $id = (int) ($course['id'] ?? 0);

        if ($pageUrl === null || $pageUrl === '') {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            $scheme = $https ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $path = $id > 0
                ? '/Registration-System/public/courses/view.php?id=' . $id
                : '/Registration-System/public/#courses';
            $pageUrl = $scheme . '://' . $host . $path;
        }

        $text = "Hi Dazu Hub Institute, I'm interested in the course: {$title}\n{$pageUrl}";
        return whatsapp_chat_url($text);
    }
}
