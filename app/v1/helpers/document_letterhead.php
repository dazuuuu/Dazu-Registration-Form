<?php
// Official black-and-white letterhead for PDF documents and compose templates.

if (!function_exists('document_letterhead_logo_src')) {
    function document_letterhead_logo_src(): string
    {
        require_once __DIR__ . '/site_logo.php';
        $dataUri = site_logo_data_uri();
        if ($dataUri) {
            return $dataUri;
        }
        return site_logo_url();
    }
}

if (!function_exists('document_letterhead_institute')) {
    function document_letterhead_institute(): string
    {
        require_once __DIR__ . '/site_logo.php';
        return site_institute_name();
    }
}

if (!function_exists('document_merge_placeholders')) {
    /**
     * Replace {key} placeholders in text/HTML.
     *
     * @param array<string, string> $vars
     */
    function document_merge_placeholders(string $text, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $text = str_replace('{' . $key . '}', (string) $value, $text);
        }
        return preg_replace('/\{[a-z0-9_]+\}/i', '', $text) ?? $text;
    }
}

if (!function_exists('document_body_to_html')) {
    /** Plain-text body (from compose editor) → HTML paragraphs. */
    function document_body_to_html(string $body): string
    {
        $body = trim($body);
        if ($body === '') {
            return '';
        }
        if (preg_match('/<[a-z][\s\S]*>/i', $body)) {
            return $body;
        }
        $parts = preg_split("/\r\n|\n|\r/", $body) ?: [];
        $html = '';
        foreach ($parts as $line) {
            $line = trim($line);
            if ($line === '') {
                $html .= '<p style="margin:0 0 14px;">&nbsp;</p>';
                continue;
            }
            $html .= '<p style="margin:0 0 14px;font-size:14px;line-height:1.75;color:#171717;">'
                . nl2br(htmlspecialchars($line, ENT_QUOTES, 'UTF-8'))
                . '</p>';
        }
        return $html;
    }
}

if (!function_exists('document_letterhead_wrap')) {
    /**
     * Wrap document body in official B&W letterhead (no brand colours).
     */
    function document_letterhead_wrap(string $documentTitle, string $bodyHtml, ?string $instituteName = null): string
    {
        $institute = $instituteName ?: document_letterhead_institute();
        $logo = document_letterhead_logo_src();
        $logoTag = $logo !== ''
            ? '<img src="' . htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') . '" alt="" style="max-height:64px;max-width:180px;object-fit:contain;margin:0 auto 14px;display:block;filter:grayscale(100%);">'
            : '';

        return '<div style="max-width:680px;margin:0 auto;border:1px solid #171717;padding:36px 44px;background:#fff;font-family:Georgia,\'Times New Roman\',serif;">'
            . '<div style="text-align:center;border-bottom:2px solid #171717;padding-bottom:22px;margin-bottom:28px;">'
            . $logoTag
            . '<h1 style="margin:0;font-size:20px;font-weight:700;color:#171717;letter-spacing:.08em;text-transform:uppercase;">'
            . htmlspecialchars($institute, ENT_QUOTES, 'UTF-8') . '</h1>'
            . '<p style="margin:10px 0 0;font-size:11px;color:#404040;letter-spacing:.16em;text-transform:uppercase;">'
            . htmlspecialchars($documentTitle, ENT_QUOTES, 'UTF-8') . '</p>'
            . '</div>'
            . '<div style="color:#171717;">' . $bodyHtml . '</div>'
            . '<div style="margin-top:40px;padding-top:14px;border-top:1px solid #171717;text-align:center;font-size:11px;color:#525252;line-height:1.6;">'
            . htmlspecialchars($institute, ENT_QUOTES, 'UTF-8')
            . '<br>Official correspondence — please retain for your records.'
            . '</div>'
            . '</div>';
    }
}

if (!function_exists('document_full_html_page')) {
    function document_full_html_page(string $documentTitle, string $bodyHtml, ?string $instituteName = null): string
    {
        $inner = document_letterhead_wrap($documentTitle, $bodyHtml, $instituteName);
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>'
            . htmlspecialchars($documentTitle, ENT_QUOTES, 'UTF-8')
            . '</title></head><body style="margin:0;padding:24px;background:#fff;">'
            . $inner
            . '</body></html>';
    }
}
