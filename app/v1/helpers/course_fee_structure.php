<?php
// Course fee breakdown: admin line items stored as JSON on courses.fee_structure.

if (!function_exists('course_fee_structure_from_post')) {
    /**
     * @return list<array{label:string,amount:float}>
     */
    function course_fee_structure_from_post(array $post): array
    {
        $labels = $post['fee_item_label'] ?? [];
        $amounts = $post['fee_item_amount'] ?? [];
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

if (!function_exists('course_fee_structure_decode')) {
    /**
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

if (!function_exists('course_fee_structure_encode')) {
    function course_fee_structure_encode(array $items): ?string
    {
        $clean = course_fee_structure_decode($items);
        if ($clean === []) {
            return null;
        }
        return json_encode($clean, JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('course_fee_structure_total')) {
    function course_fee_structure_total(array $items): float
    {
        $sum = 0.0;
        foreach ($items as $item) {
            $sum += (float) ($item['amount'] ?? 0);
        }
        return round($sum, 2);
    }
}

if (!function_exists('course_fee_structure_items')) {
    /**
     * @return list<array{label:string,amount:float}>
     */
    function course_fee_structure_items(array $course): array
    {
        return course_fee_structure_decode($course['fee_structure'] ?? null);
    }
}

if (!function_exists('course_fee_structure_download_url')) {
    function course_fee_structure_download_url(int $courseId, bool $download = true): string
    {
        $path = url('courses/fee-structure.php?id=' . $courseId . ($download ? '&download=1' : ''));
        return $path;
    }
}

if (!function_exists('course_fee_structure_document_html')) {
    function course_fee_structure_document_html(array $course, array $items): string
    {
        require_once __DIR__ . '/currency.php';
        $title = (string) ($course['title'] ?? 'Course');
        $abbr = strtoupper(trim((string) ($course['abbreviation'] ?? '')));
        $total = course_fee_structure_total($items);
        if ($total <= 0) {
            $total = (float) ($course['course_fee'] ?? 0);
        }
        $logo = 'https://i.ibb.co/fzfZFtGc/223025.jpg';
        $rows = '';
        foreach ($items as $item) {
            $rows .= '<tr>'
                . '<td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;">'
                . htmlspecialchars($item['label']) . '</td>'
                . '<td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600;">'
                . htmlspecialchars(format_price($item['amount'])) . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;">Course fee</td>'
                . '<td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600;">'
                . htmlspecialchars(format_price($total)) . '</td></tr>';
        }

        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:640px;margin:0 auto;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">'
            . '<div style="background:#fff;padding:24px;text-align:center;border-bottom:3px solid #15A24A;">'
            . '<img src="' . $logo . '" alt="Dazu Hub Institute" style="width:56px;height:56px;border-radius:8px;object-fit:cover;margin-bottom:8px;">'
            . '<h1 style="margin:0;font-size:20px;color:#15A24A;">Dazu Hub Institute</h1>'
            . '<p style="margin:8px 0 0;font-size:14px;color:#475569;">Course Fee Structure</p>'
            . '</div>'
            . '<div style="padding:24px;">'
            . '<p style="margin:0 0 4px;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.08em;">Course</p>'
            . '<h2 style="margin:0 0 16px;font-size:18px;color:#0f172a;">' . htmlspecialchars($title)
            . ($abbr !== '' ? ' <span style="color:#64748b;font-size:14px;">(' . htmlspecialchars($abbr) . ')</span>' : '')
            . '</h2>'
            . '<table style="width:100%;border-collapse:collapse;font-size:14px;">'
            . '<thead><tr style="background:#f8fafc;">'
            . '<th style="text-align:left;padding:10px 12px;border-bottom:1px solid #cbd5e1;">Item</th>'
            . '<th style="text-align:right;padding:10px 12px;border-bottom:1px solid #cbd5e1;">Amount</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody>'
            . '<tfoot><tr>'
            . '<td style="padding:12px;font-weight:700;">Total</td>'
            . '<td style="padding:12px;text-align:right;font-weight:800;color:#15A24A;">' . htmlspecialchars(format_price($total)) . '</td>'
            . '</tr></tfoot></table>'
            . '</div>'
            . '<div style="background:#f8fafc;padding:14px 24px;text-align:center;font-size:12px;color:#64748b;">'
            . 'Issued ' . htmlspecialchars(date('d M Y')) . ' · dazuhub.ac.ke'
            . '</div></div>';
    }
}
