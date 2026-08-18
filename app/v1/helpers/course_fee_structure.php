<?php
// Course fee breakdown: online vs physical line items stored as JSON on courses.fee_structure.
// Shape: {"online":[{"label":"Tuition","amount":40000}],"physical":[...]}
// Legacy flat arrays are treated as the same breakdown for both modes.

if (!function_exists('course_fee_structure_from_post')) {
    /**
     * @return list<array{label:string,amount:float}>
     */
    function course_fee_structure_from_post(array $post, string $mode = 'online'): array
    {
        $mode = $mode === 'physical' ? 'physical' : 'online';
        $labels = $post['fee_' . $mode . '_label'] ?? [];
        $amounts = $post['fee_' . $mode . '_amount'] ?? [];
        // Backward-compatible names from the first fee-structure form.
        if ($labels === [] && $mode === 'online') {
            $labels = $post['fee_item_label'] ?? [];
            $amounts = $post['fee_item_amount'] ?? [];
        }
        if (!is_array($labels)) {
            $labels = [];
        }
        if (!is_array($amounts)) {
            $amounts = [];
        }

        $items = [];
        $count = max(count($labels), count($amounts));
        for ($i = 0; $i < $count; $i++) {
            $label = trim((string) ($labels[$i] ?? ''));
            $rawAmount = trim((string) ($amounts[$i] ?? ''));
            if ($label === '' && $rawAmount === '') {
                continue;
            }
            $amount = (float) str_replace(',', '', $rawAmount);
            if ($label === '' || $amount < 0) {
                continue;
            }
            $items[] = [
                'label' => mb_strimwidth($label, 0, 120, ''),
                'amount' => round($amount, 2),
            ];
        }
        return $items;
    }
}

if (!function_exists('course_fee_structure_modes_from_post')) {
    /**
     * @return array{online:list<array{label:string,amount:float}>,physical:list<array{label:string,amount:float}>}
     */
    function course_fee_structure_modes_from_post(array $post): array
    {
        return [
            'online' => course_fee_structure_from_post($post, 'online'),
            'physical' => course_fee_structure_from_post($post, 'physical'),
        ];
    }
}

if (!function_exists('course_fee_structure_decode')) {
    /**
     * Decode a flat list of line items.
     * @return list<array{label:string,amount:float}>
     */
    function course_fee_structure_decode($raw): array
    {
        if (is_array($raw)) {
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

if (!function_exists('course_fee_structure_modes_decode')) {
    /**
     * @return array{online:list<array{label:string,amount:float}>,physical:list<array{label:string,amount:float}>}
     */
    function course_fee_structure_modes_decode($raw): array
    {
        $empty = ['online' => [], 'physical' => []];
        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $json = trim((string) $raw);
            if ($json === '') {
                return $empty;
            }
            $decoded = json_decode($json, true);
            if (!is_array($decoded)) {
                return $empty;
            }
        }

        if (array_key_exists('online', $decoded) || array_key_exists('physical', $decoded)) {
            return [
                'online' => course_fee_structure_decode($decoded['online'] ?? []),
                'physical' => course_fee_structure_decode($decoded['physical'] ?? []),
            ];
        }

        $legacy = course_fee_structure_decode($decoded);
        return ['online' => $legacy, 'physical' => $legacy];
    }
}

if (!function_exists('course_fee_structure_modes_encode')) {
    function course_fee_structure_modes_encode(array $modes): ?string
    {
        $clean = [
            'online' => course_fee_structure_decode($modes['online'] ?? []),
            'physical' => course_fee_structure_decode($modes['physical'] ?? []),
        ];
        if ($clean['online'] === [] && $clean['physical'] === []) {
            return null;
        }
        return json_encode($clean, JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('course_fee_structure_encode')) {
    function course_fee_structure_encode(array $items): ?string
    {
        if (isset($items['online']) || isset($items['physical'])) {
            return course_fee_structure_modes_encode($items);
        }
        $clean = course_fee_structure_decode($items);
        if ($clean === []) {
            return null;
        }
        return course_fee_structure_modes_encode(['online' => $clean, 'physical' => $clean]);
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

if (!function_exists('course_fee_structure_all')) {
    /**
     * @return array{online:list<array{label:string,amount:float}>,physical:list<array{label:string,amount:float}>}
     */
    function course_fee_structure_all(array $course): array
    {
        return course_fee_structure_modes_decode($course['fee_structure'] ?? null);
    }
}

if (!function_exists('course_fee_structure_items')) {
    /**
     * @return list<array{label:string,amount:float}>
     */
    function course_fee_structure_items(array $course, string $mode = 'online'): array
    {
        $modes = course_fee_structure_all($course);
        $mode = $mode === 'physical' ? 'physical' : 'online';
        if (!empty($modes[$mode])) {
            return $modes[$mode];
        }
        $other = $mode === 'physical' ? 'online' : 'physical';
        return $modes[$other];
    }
}

if (!function_exists('course_fee_for_mode')) {
    function course_fee_for_mode(array $course, string $mode = 'online'): float
    {
        $items = course_fee_structure_items($course, $mode);
        $sum = course_fee_structure_total($items);
        if ($sum > 0) {
            return $sum;
        }
        return (float) ($course['course_fee'] ?? 0);
    }
}

if (!function_exists('course_fee_structure_catalog_total')) {
    /** Stored course_fee / listing total: online first, then physical. */
    function course_fee_structure_catalog_total(array $modes, $fallback = 0): float
    {
        $online = course_fee_structure_total($modes['online'] ?? []);
        if ($online > 0) {
            return $online;
        }
        $physical = course_fee_structure_total($modes['physical'] ?? []);
        if ($physical > 0) {
            return $physical;
        }
        return (float) $fallback;
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

if (!function_exists('course_fee_structure_table_html')) {
    function course_fee_structure_table_html(array $items, float $fallbackTotal = 0): string
    {
        require_once __DIR__ . '/currency.php';
        $total = course_fee_structure_total($items);
        if ($total <= 0) {
            $total = $fallbackTotal;
        }
        $rows = '';
        foreach ($items as $item) {
            $rows .= '<tr>'
                . '<td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;">'
                . htmlspecialchars((string) $item['label']) . '</td>'
                . '<td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600;">'
                . htmlspecialchars(format_price($item['amount'])) . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;">Course fee</td>'
                . '<td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600;">'
                . htmlspecialchars(format_price($total)) . '</td></tr>';
        }
        return '<table style="width:100%;border-collapse:collapse;font-size:13px;margin:0 0 8px;">'
            . '<thead><tr style="background:#f8fafc;">'
            . '<th style="text-align:left;padding:8px 10px;border-bottom:1px solid #cbd5e1;">Item</th>'
            . '<th style="text-align:right;padding:8px 10px;border-bottom:1px solid #cbd5e1;">Amount</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody>'
            . '<tfoot><tr>'
            . '<td style="padding:10px;font-weight:700;">Total</td>'
            . '<td style="padding:10px;text-align:right;font-weight:800;color:#15A24A;">' . htmlspecialchars(format_price($total)) . '</td>'
            . '</tr></tfoot></table>';
    }
}

if (!function_exists('course_fee_structure_document_html')) {
    function course_fee_structure_document_html(array $course, array $items = []): string
    {
        require_once __DIR__ . '/site_logo.php';
        $title = (string) ($course['title'] ?? 'Course');
        $abbr = strtoupper(trim((string) ($course['abbreviation'] ?? '')));
        $logo = site_logo_data_uri() ?: site_logo_url();
        $modes = course_fee_structure_all($course);
        if ($items !== [] && $modes['online'] === [] && $modes['physical'] === []) {
            $modes['online'] = $items;
        }

        $sections = '';
        foreach (['online' => 'Online classes', 'physical' => 'Physical classes'] as $key => $heading) {
            $list = $modes[$key] ?? [];
            $fallback = (float) ($course['course_fee'] ?? 0);
            $sections .= '<h3 style="margin:18px 0 8px;font-size:14px;color:#0f172a;text-transform:uppercase;letter-spacing:.06em;">'
                . htmlspecialchars($heading) . '</h3>'
                . course_fee_structure_table_html($list, $fallback);
        }

        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:640px;margin:0 auto;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">'
            . '<div style="background:#fff;padding:24px;text-align:center;border-bottom:3px solid #15A24A;">'
            . '<img src="' . htmlspecialchars($logo) . '" alt="Dazu Hub Institute" style="max-height:64px;max-width:180px;object-fit:contain;margin-bottom:8px;">'
            . '<h1 style="margin:0;font-size:20px;color:#15A24A;">Dazu Hub Institute</h1>'
            . '<p style="margin:8px 0 0;font-size:14px;color:#475569;">Course Fee Structure</p>'
            . '</div>'
            . '<div style="padding:24px;">'
            . '<p style="margin:0 0 4px;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.08em;">Course</p>'
            . '<h2 style="margin:0 0 8px;font-size:18px;color:#0f172a;">' . htmlspecialchars($title)
            . ($abbr !== '' ? ' <span style="color:#64748b;font-size:14px;">(' . htmlspecialchars($abbr) . ')</span>' : '')
            . '</h2>'
            . $sections
            . '</div>'
            . '<div style="background:#f8fafc;padding:14px 24px;text-align:center;font-size:12px;color:#64748b;">'
            . 'Issued ' . htmlspecialchars(date('d M Y')) . ' · dazuhub.ac.ke'
            . '</div></div>';
    }
}

if (!function_exists('course_fee_structure_render_pdf')) {
    /** @return string|null PDF binary */
    function course_fee_structure_render_pdf(array $course): ?string
    {
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'
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
