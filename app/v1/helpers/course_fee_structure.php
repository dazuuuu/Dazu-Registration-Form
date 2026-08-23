<?php
// Course school fees (online vs physical) and formal fee-structure document.

if (!function_exists('course_school_fees_from_post')) {
    /**
     * @return array{online:float,physical:float}
     */
    function course_school_fees_from_post(array $post): array
    {
        $online = (float) str_replace(',', '', trim((string) ($post['school_fee_online'] ?? '')));
        $physical = (float) str_replace(',', '', trim((string) ($post['school_fee_physical'] ?? '')));
        return [
            'online' => max(0, round($online, 2)),
            'physical' => max(0, round($physical, 2)),
        ];
    }
}

if (!function_exists('course_school_fees')) {
    /**
     * @return array{online:float,physical:float}
     */
    function course_school_fees(array $course): array
    {
        $online = isset($course['school_fee_online']) && $course['school_fee_online'] !== null && $course['school_fee_online'] !== ''
            ? (float) $course['school_fee_online']
            : 0.0;
        $physical = isset($course['school_fee_physical']) && $course['school_fee_physical'] !== null && $course['school_fee_physical'] !== ''
            ? (float) $course['school_fee_physical']
            : 0.0;

        // Legacy: fall back to course_fee or old fee_structure JSON when new columns are empty.
        if ($online <= 0 && $physical <= 0) {
            $legacy = course_fee_structure_decode($course['fee_structure'] ?? null);
            if ($legacy !== []) {
                $total = course_fee_structure_total($legacy);
                return ['online' => $total, 'physical' => $total];
            }
            $fallback = (float) ($course['course_fee'] ?? 0);
            if ($fallback > 0) {
                return ['online' => $fallback, 'physical' => $fallback];
            }
        }

        return ['online' => $online, 'physical' => $physical];
    }
}

if (!function_exists('course_catalog_fee')) {
    /** Primary fee shown on course cards — online first, then physical. */
    function course_catalog_fee(array $course): float
    {
        $fees = course_school_fees($course);
        if ($fees['online'] > 0) {
            return $fees['online'];
        }
        if ($fees['physical'] > 0) {
            return $fees['physical'];
        }
        return (float) ($course['course_fee'] ?? 0);
    }
}

if (!function_exists('course_fee_for_mode')) {
    function course_fee_for_mode(array $course, string $mode = 'online'): float
    {
        $fees = course_school_fees($course);
        $mode = $mode === 'physical' ? 'physical' : 'online';
        $fee = $fees[$mode];
        if ($fee > 0) {
            return $fee;
        }
        $other = $mode === 'physical' ? 'online' : 'physical';
        return $fees[$other] > 0 ? $fees[$other] : (float) ($course['course_fee'] ?? 0);
    }
}

if (!function_exists('course_has_school_fees')) {
    function course_has_school_fees(array $course): bool
    {
        $fees = course_school_fees($course);
        return $fees['online'] > 0 || $fees['physical'] > 0;
    }
}

if (!function_exists('course_brochure_url')) {
    function course_brochure_url(array $course): ?string
    {
        $path = trim((string) ($course['brochure_path'] ?? ''));
        if ($path === '') {
            return null;
        }
        $id = (int) ($course['id'] ?? 0);
        if ($id > 0) {
            return url('courses/brochure.php?id=' . $id);
        }
        if (!function_exists('settings_asset_url')) {
            require_once __DIR__ . '/uploads.php';
        }
        return settings_asset_url($path);
    }
}

if (!function_exists('course_fee_structure_download_url')) {
    function course_fee_structure_download_url(int $courseId, bool $download = true): string
    {
        return url('courses/fee-structure.php?id=' . $courseId . ($download ? '&download=1' : ''));
    }
}

if (!function_exists('course_duration_summary_text')) {
    function course_duration_summary_text(array $course): string
    {
        require_once __DIR__ . '/course_duration.php';
        $summary = course_study_summary($course);
        return $summary !== '' ? $summary : 'Flexible';
    }
}

if (!function_exists('course_fee_structure_document_html')) {
    function course_fee_structure_document_html(array $course): string
    {
        require_once __DIR__ . '/currency.php';
        require_once __DIR__ . '/site_logo.php';

        $title = (string) ($course['title'] ?? 'Course');
        $abbr = strtoupper(trim((string) ($course['abbreviation'] ?? '')));
        $overview = trim((string) ($course['overview'] ?? $course['description'] ?? ''));
        $duration = course_duration_summary_text($course);
        $category = trim((string) ($course['category_name'] ?? ''));
        $fees = course_school_fees($course);
        $logo = site_logo_data_uri() ?: site_logo_url();

        $feeRows = '';
        if ($fees['online'] > 0) {
            $feeRows .= '<tr>'
                . '<td style="padding:12px 16px;border-bottom:1px solid #d4d4d4;color:#171717;">Online classes</td>'
                . '<td style="padding:12px 16px;border-bottom:1px solid #d4d4d4;text-align:right;font-weight:700;color:#171717;">'
                . htmlspecialchars(format_price($fees['online'])) . '</td></tr>';
        }
        if ($fees['physical'] > 0) {
            $feeRows .= '<tr>'
                . '<td style="padding:12px 16px;border-bottom:1px solid #d4d4d4;color:#171717;">Physical classes</td>'
                . '<td style="padding:12px 16px;border-bottom:1px solid #d4d4d4;text-align:right;font-weight:700;color:#171717;">'
                . htmlspecialchars(format_price($fees['physical'])) . '</td></tr>';
        }
        if ($feeRows === '') {
            $fallback = (float) ($course['course_fee'] ?? 0);
            $feeRows = '<tr>'
                . '<td style="padding:12px 16px;border-bottom:1px solid #d4d4d4;color:#171717;">School fees</td>'
                . '<td style="padding:12px 16px;border-bottom:1px solid #d4d4d4;text-align:right;font-weight:700;color:#171717;">'
                . htmlspecialchars(format_price($fallback)) . '</td></tr>';
        }

        $details = '';
        if ($category !== '') {
            $details .= '<tr><td style="padding:8px 0;color:#525252;font-size:13px;">Category</td>'
                . '<td style="padding:8px 0;text-align:right;font-weight:600;color:#171717;font-size:13px;">'
                . htmlspecialchars($category) . '</td></tr>';
        }
        $details .= '<tr><td style="padding:8px 0;color:#525252;font-size:13px;">Duration</td>'
            . '<td style="padding:8px 0;text-align:right;font-weight:600;color:#171717;font-size:13px;">'
            . htmlspecialchars($duration) . '</td></tr>';

        return '<div style="font-family:Georgia,\'Times New Roman\',serif;max-width:640px;margin:0 auto;border:2px solid #171717;background:#ffffff;">'
            . '<div style="padding:32px 32px 24px;text-align:center;border-bottom:4px solid #15A24A;">'
            . '<img src="' . htmlspecialchars($logo) . '" alt="Institution logo" style="max-height:72px;max-width:200px;object-fit:contain;margin-bottom:16px;filter:grayscale(100%);">'
            . '<h1 style="margin:0;font-size:22px;font-weight:700;color:#171717;letter-spacing:.04em;text-transform:uppercase;">Dazu Hub Institute</h1>'
            . '<p style="margin:10px 0 0;font-size:13px;color:#525252;letter-spacing:.12em;text-transform:uppercase;">Fee Structure</p>'
            . '</div>'
            . '<div style="padding:28px 32px;">'
            . '<p style="margin:0 0 6px;font-size:11px;color:#737373;text-transform:uppercase;letter-spacing:.14em;">Course</p>'
            . '<h2 style="margin:0 0 20px;font-size:20px;font-weight:700;color:#171717;">' . htmlspecialchars($title)
            . ($abbr !== '' ? ' <span style="color:#737373;font-size:15px;font-weight:400;">(' . htmlspecialchars($abbr) . ')</span>' : '')
            . '</h2>'
            . ($overview !== '' ? '<p style="margin:0 0 20px;font-size:14px;line-height:1.6;color:#404040;">' . htmlspecialchars($overview) . '</p>' : '')
            . '<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">' . $details . '</table>'
            . '<p style="margin:0 0 10px;font-size:11px;color:#737373;text-transform:uppercase;letter-spacing:.14em;">School fees</p>'
            . '<table style="width:100%;border-collapse:collapse;border:1px solid #d4d4d4;">'
            . '<thead><tr style="background:#f5f5f5;">'
            . '<th style="text-align:left;padding:10px 16px;border-bottom:1px solid #d4d4d4;font-size:12px;color:#525252;text-transform:uppercase;letter-spacing:.08em;">Mode</th>'
            . '<th style="text-align:right;padding:10px 16px;border-bottom:1px solid #d4d4d4;font-size:12px;color:#525252;text-transform:uppercase;letter-spacing:.08em;">Amount</th>'
            . '</tr></thead><tbody>' . $feeRows . '</tbody></table>'
            . '</div>'
            . '<div style="padding:16px 32px;border-top:1px solid #d4d4d4;text-align:center;font-size:11px;color:#737373;letter-spacing:.06em;">'
            . 'Issued ' . htmlspecialchars(date('d M Y')) . ' &middot; dazuhub.ac.ke'
            . '</div></div>';
    }
}

if (!function_exists('course_fee_structure_render_pdf')) {
    /** @return string|null PDF binary */
    function course_fee_structure_render_pdf(array $course): ?string
    {
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:24px;background:#fff;">'
            . course_fee_structure_document_html($course)
            . '</body></html>';

        $autoload = null;
        if (defined('PROJECT_ROOT') && is_file(PROJECT_ROOT . '/vendor/autoload.php')) {
            $autoload = PROJECT_ROOT . '/vendor/autoload.php';
        } elseif (is_file(dirname(__DIR__, 2) . '/vendor/autoload.php')) {
            $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        }
        if ($autoload) {
            require_once $autoload;
        }
        if (!class_exists('Dompdf\\Dompdf')) {
            return null;
        }
        try {
            $dompdf = new \Dompdf\Dompdf([
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
            ]);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            return $dompdf->output();
        } catch (Throwable $e) {
            error_log('[fee-structure] PDF render failed: ' . $e->getMessage());
            return null;
        }
    }
}

/* ---- Legacy helpers kept for backward compatibility ---- */

if (!function_exists('course_fee_structure_from_post')) {
    function course_fee_structure_from_post(array $post): array
    {
        return [];
    }
}

if (!function_exists('course_fee_structure_decode')) {
    function course_fee_structure_decode($raw): array
    {
        if (is_array($raw)) {
            if (array_key_exists('online', $raw) || array_key_exists('physical', $raw)) {
                return course_fee_structure_decode($raw['online'] ?? []);
            }
            $decoded = $raw;
        } else {
            $json = trim((string) $raw);
            if ($json === '') {
                return [];
            }
            $decoded = json_decode($json, true);
            if (!is_array($decoded)) {
                return [];
            }
            if (array_key_exists('online', $decoded) || array_key_exists('physical', $decoded)) {
                return course_fee_structure_decode($decoded['online'] ?? []);
            }
        }

        $items = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            $amount = (float) ($row['amount'] ?? 0);
            if ($label === '' || $amount < 0) {
                continue;
            }
            $items[] = ['label' => $label, 'amount' => round($amount, 2)];
        }
        return $items;
    }
}

if (!function_exists('course_fee_structure_encode')) {
    function course_fee_structure_encode(array $items): ?string
    {
        return null;
    }
}

if (!function_exists('course_fee_structure_total')) {
    function course_fee_structure_total(array $items): float
    {
        $sum = 0.0;
        foreach ($items as $item) {
            if (!is_array($item) || !array_key_exists('amount', $item)) {
                continue;
            }
            $sum += (float) $item['amount'];
        }
        return round($sum, 2);
    }
}

if (!function_exists('course_fee_structure_items')) {
    function course_fee_structure_items(array $course): array
    {
        return [];
    }
}
